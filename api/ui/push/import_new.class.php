<?php
/**
 * import_new.class.php
 *
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\ui\push;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * push: import new
 *
 * Import a list of new participants.
 */
class import_new extends \cenozo\ui\push
{
  /**
   * Constructor.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args Push arguments
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'import', 'new', $args );
  }

  /**
   * Validate the operation.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @throws exception\notice
   * @access protected
   */
  protected function validate()
  {
    parent::validate();

    if( 0 == $_SERVER['CONTENT_LENGTH'] )
      throw lib::create( 'exception\notice',
        'Tried to import participant data without a valid CSV file.',
        __METHOD__ );
  }

  /**
   * This method executes the operation's purpose.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access protected
   */
  protected function execute()
  {
    parent::execute();

    $import_class_name = lib::get_class_name( 'database\import' );
    $region_class_name = lib::get_class_name( 'database\region' );
    $source_class_name = lib::get_class_name( 'database\source' );

    // store the data
    $filename = $_SERVER['HTTP_X_FILENAME'];
    $data = utf8_encode( file_get_contents( 'php://input' ) );
    $md5 = md5( $data );

    // see if the file has already been processed either by name or md5 hash
    $db_import = $import_class_name::get_unique_record( 'name', $filename );
    if( !is_null( $db_import ) )
    {
      if( $db_import->processed )
        throw lib::create( 'exception\notice',
          'A file by the same name has already been imported.', __METHOD__ );

      // delete the old entries
      foreach( $db_import->get_import_entry_list() as $db_import_entry )
        $db_import_entry->delete();
    }
    else
    {
      $db_import = $import_class_name::get_unique_record( 'md5', $md5 );
      if( !is_null( $db_import ) )
      {
        if( $db_import->processed )
          throw lib::create( 'exception\notice',
            'This file has already been imported under a different name ('.
            $db_import->name.').', __METHOD__ );

        // delete the old entries
        foreach( $db_import->get_import_entry_list() as $db_import_entry )
          $db_import_entry->delete();
      }
    }

    if( is_null( $db_import ) ) $db_import = lib::create( 'database\import' );

    // update the import record
    $db_import->name = $filename;
    $db_import->date = util::get_datetime_object()->format( 'Y-m-d' );
    $db_import->processed = false;
    $db_import->md5 = $md5;
    $db_import->data = $data;
    $db_import->save();

    // now process the data
    $import_type = NULL;
    $row = 0;
    foreach( preg_split( '/[\n\r]+/', $data ) as $line )
    {
      $values = str_getcsv( $line );

      // use the header line to identify the import file type
      if( 0 == $row )
      {
        if( in_array( 'nuage_id', $values ) ) $import_type = 'nuage';
        else if( 'first_name' == $values[0] ) $import_type = 'leger';
      }
      else if( 18 == count( $values ) )
      {
        if( 'leger' == $import_type )
        { // leger import file
          $db_source = $source_class_name::get_unique_record( 'name', 'rdd' );
          $db_import_entry = lib::create( 'database\import_entry' );
          $db_import_entry->import_id = $db_import->id;
          $db_import_entry->source_id = $db_source->id;
          $db_import_entry->row = $row;
          $db_import_entry->first_name = $values[0];
          $db_import_entry->last_name = $values[1];
          $db_import_entry->apartment = 0 == strlen( $values[2] ) ? NULL : $values[2];
          $db_import_entry->street = $values[3];
          $db_import_entry->address_other = 0 == strlen( $values[4] ) ? NULL : $values[4];
          $db_import_entry->city = $values[5];
          $db_import_entry->province = $values[6];
          $db_import_entry->postcode = $values[7];
          $db_import_entry->home_phone = $values[8];
          // null mobile phone entries may be 999-999-9999
          $db_import_entry->mobile_phone =
            0 == strlen( $values[9] ) || '999-999-9999' == $values[9] || $values[8] == $values[9] ?
            NULL : $values[9];
          if( 0 == strcasecmp( 'home', $values[10] ) )
            $db_import_entry->phone_preference = 'home';
          else if( 0 == strcasecmp( 'cell', $values[10] ) )
            $db_import_entry->phone_preference = 'mobile';
          $db_import_entry->email = 0 == strlen( $values[11] ) ? NULL : $values[11];
          if( 0 == strcasecmp( 'f', $values[12] ) ) $db_import_entry->gender = 'female';
          else if( 0 == strcasecmp( 'm', $values[12] ) ) $db_import_entry->gender = 'male';
          else $db_import_entry->gender = '';

          $db_import_entry->date_of_birth = $values[13];

          $db_import_entry->language = 0 == strlen( $values[13] ) ? NULL : $values[13];
          $db_import_entry->cohort = $values[14];
          $db_import_entry->date = $values[15];
          
          $db_import_entry->low_education = 'Any Post-Secondary Education' != $values[16];

          // column index 2 has "XX-XX" (age-group)
          $year = date( 'Y' );
          if( '45-54' == $values[17] )
            $db_import_entry->date_of_birth = sprintf( '%d-01-01', $year - 50 );
          else if( '55-64' == $values[17] )
            $db_import_entry->date_of_birth = sprintf( '%d-01-01', $year - 60 );
          else if( '65-74' == $values[17] )
            $db_import_entry->date_of_birth = sprintf( '%d-01-01', $year - 70 );
          else if( '75-85' == $values[17] )
            $db_import_entry->date_of_birth = sprintf( '%d-01-01', $year - 80 );

          $db_import_entry->monday = false;
          $db_import_entry->tuesday = false;
          $db_import_entry->wednesday = false;
          $db_import_entry->thursday = false;
          $db_import_entry->friday = false;
          $db_import_entry->saturday = false;
          $db_import_entry->time_9_10 = false;
          $db_import_entry->time_10_11 = false;
          $db_import_entry->time_11_12 = false;
          $db_import_entry->time_12_13 = false;
          $db_import_entry->time_13_14 = false;
          $db_import_entry->time_14_15 = false;
          $db_import_entry->time_15_16 = false;
          $db_import_entry->time_16_17 = false;
          $db_import_entry->time_17_18 = false;
          $db_import_entry->time_18_19 = false;
          $db_import_entry->time_19_20 = false;
          $db_import_entry->time_20_21 = false;

          $db_import_entry->validate();
          try
          {
            $db_import_entry->save();
          }
          catch( \cenozo\exception\database $e )
          {
            throw lib::create( 'exception\notice',
              sprintf( 'There was a problem importing row %d.', $row ), __METHOD__, $e );
          }
        }
      }
      else if( 17 == count( $values ) )
      {
        if( 'nuage' == $import_type )
        { // nuage import file
          $db_source = $source_class_name::get_unique_record( 'name', 'nuage' );
          $db_import_entry = lib::create( 'database\import_entry' );
          $db_import_entry->import_id = $db_import->id;
          $db_import_entry->source_id = $db_source->id;
          $db_import_entry->row = $row;
          $db_import_entry->first_name = $values[0];
          $db_import_entry->last_name = $values[1];
          $db_import_entry->apartment = 0 == strlen( $values[2] ) ? NULL : $values[2];
          $db_import_entry->street = $values[3];
          $db_import_entry->address_other = 0 == strlen( $values[4] ) ? NULL : $values[4];
          $db_import_entry->city = $values[5];
          $db_import_entry->province = $values[6];
          $db_import_entry->postcode = $values[7];
          $db_import_entry->home_phone = $values[8];
          // null mobile phone entries may be 999-999-9999
          $db_import_entry->mobile_phone =
            0 == strlen( $values[9] ) || '999-999-9999' == $values[9] || $values[8] == $values[9] ?
            NULL : $values[9];
          if( 0 == strcasecmp( 'home', $values[10] ) )
            $db_import_entry->phone_preference = 'home';
          else if( 0 == strcasecmp( 'cell', $values[10] ) )
            $db_import_entry->phone_preference = 'mobile';
          $db_import_entry->email = 0 == strlen( $values[11] ) ? NULL : $values[11];
          if( 0 == strcasecmp( 'f', $values[12] ) ) $db_import_entry->gender = 'female';
          else if( 0 == strcasecmp( 'm', $values[12] ) ) $db_import_entry->gender = 'male';
          else $db_import_entry->gender = '';

          $db_import_entry->date_of_birth = $values[13];

          $db_import_entry->language = 0 == strlen( $values[14] ) ? NULL : $values[14];
          $db_import_entry->cohort = 'comprehensive';
          $db_import_entry->date = $values[15];
          
          $db_import_entry->low_education = 'Any Post-Secondary Education' != $values[16];

          $db_import_entry->monday = false;
          $db_import_entry->tuesday = false;
          $db_import_entry->wednesday = false;
          $db_import_entry->thursday = false;
          $db_import_entry->friday = false;
          $db_import_entry->saturday = false;
          $db_import_entry->time_9_10 = false;
          $db_import_entry->time_10_11 = false;
          $db_import_entry->time_11_12 = false;
          $db_import_entry->time_12_13 = false;
          $db_import_entry->time_13_14 = false;
          $db_import_entry->time_14_15 = false;
          $db_import_entry->time_15_16 = false;
          $db_import_entry->time_16_17 = false;
          $db_import_entry->time_17_18 = false;
          $db_import_entry->time_18_19 = false;
          $db_import_entry->time_19_20 = false;
          $db_import_entry->time_20_21 = false;

          $db_import_entry->validate();
          try
          {
            $db_import_entry->save();
          }
          catch( \cenozo\exception\database $e )
          {
            throw lib::create( 'exception\notice',
              sprintf( 'There was a problem importing row %d.', $row ), __METHOD__, $e );
          }
        }
      }

      $row++;
    }
  }
}
