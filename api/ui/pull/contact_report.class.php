<?php
/**
 * contact_report.class.php
 * 
 * @author Dean Inglis <inglisd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\ui\pull;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * Mailout required report data.
 * 
 * @abstract
 */
class contact_report extends \cenozo\ui\pull\base_report
{
  /**
   * Constructor
   * 
   * @author Dean Inglis <inglisd@mcmaster.ca>
   * @param array $args Pull arguments.
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'contact', $args );
  }

  /**
   * Builds the report.
   * @author Dean Inglis <inglisd@mcmaster.ca>
   * @access protected
   */
  protected function build()
  {
    $participant_class_name = lib::get_class_name( 'database\participant' );

    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'service.id', '=', lib::create( 'business\session' )->get_service()->id );
    $modifier->where( 'uid', 'IN', $this->uid_list );

    // create temporary table of last address
    $participant_class_name::db()->execute(
      'CREATE TEMPORARY TABLE temp_first_address '.
      'SELECT * FROM participant_first_address' );
    $participant_class_name::db()->execute(
      'ALTER TABLE temp_first_address '.
      'ADD INDEX dk_participant_id_address_id ( participant_id, address_id )' );

    // create temporary table of last consent
    $participant_class_name::db()->execute(
      'CREATE TEMPORARY TABLE temp_last_consent '.
      'SELECT * FROM participant_last_consent' );
    $participant_class_name::db()->execute(
      'ALTER TABLE temp_last_consent '.
      'ADD INDEX dk_participant_id_consent_id ( participant_id, consent_id )' );

    $sql =
      'SELECT cohort.name AS cohort, '.
             'IFNULL( language.code, service_language.code ) AS language, '.
             'uid, '.
             'first_name, '.
             'last_name, '.
             'IF( address2 IS NULL, address1, CONCAT( address1, ", ", address2 ) ) AS address, '.
             'city, '.
             'region.abbreviation AS province, '.
             'postcode, '.
             'region.country, '.
             'IFNULL( state.name, "None" ) AS state, '.
             'IFNULL( email, "" ) AS email, '.
             'IF( temp_last_consent.consent_id IS NULL, '.
                 '"None", '.
                 'CONCAT( '.
                   'IF( written, "Written ", "Verbal " ), '.
                   'IF( accept, "Accept", "Deny" ) '.
                 ') '.
             ') AS consent '.
      'FROM service '.
      'CROSS JOIN participant '.
      'JOIN language AS service_language ON service.language_id = service_language.id '.
      'JOIN cohort ON participant.cohort_id = cohort.id '.
      'LEFT JOIN language ON participant.language_id = language.id '.
      'JOIN temp_first_address ON participant.id = temp_first_address.participant_id '.
      'JOIN address ON temp_first_address.address_id = address.id '.
      'JOIN region ON address.region_id = region.id '.
      'LEFT JOIN state ON participant.state_id = state.id '.
      'JOIN temp_last_consent ON participant.id = temp_last_consent.participant_id '.
      $modifier->get_sql();
    
    $rows = $participant_class_name::db()->get_all( $sql );

    $header = array();
    $content = array();
    foreach( $rows as $row )
    {   
      // set up the header
      if( 0 == count( $header ) ) 
        foreach( $row as $column => $value )
          $header[] = ucwords( str_replace( '_', ' ', $column ) );

      $content[] = array_values( $row );
    }   

    $this->add_table( NULL, $header, $content, NULL );
  }
}
