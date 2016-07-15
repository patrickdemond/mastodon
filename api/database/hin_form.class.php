<?php
/**
 * hin_form.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\database;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * hin_form: record
 */
class hin_form extends base_form
{
  /**
   * Implements the parent's abstract import method.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\form_entry $db_base_form_entry The entry to be used as the valid data.
   * @access public
   */
  public function import( $db_hin_form_entry )
  {
    if( is_null( $db_hin_form_entry ) || !$db_hin_form_entry->id )
    {
      throw lib::create( 'exception\runtime',
        'Tried to import invalid hin form entry.', __METHOD__ );
    }

    $database_class_name = lib::get_class_name( 'database\database' );
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $hin_class_name = lib::get_class_name( 'database\hin' );

    $db_participant = $participant_class_name::get_unique_record( 'uid', $db_hin_form_entry->uid );

    // link to the form
    $this->validated_hin_form_entry_id = $db_hin_form_entry->id;

    // import the data to the hin table
    $db_hin = $hin_class_name::get_unique_record( 'participant_id', $db_participant->id );
    if( is_null( $db_hin ) )
    {
      $db_hin = lib::create( 'database\hin' );
      $db_hin->participant_id = $db_participant->id;
    }
    $db_hin->extended_access = $db_consent_form_entry->accept;
    $db_hin->save();

    // save the participant record to the form
    $this->complete = true;
    $this->participant_id = $db_participant->id;
    $this->save();
  }
}
