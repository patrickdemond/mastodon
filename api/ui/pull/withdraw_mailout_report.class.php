<?php
/**
 * withdraw_mailout_report.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\ui\pull;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * Productivity report data.
 * 
 * @abstract
 */
class withdraw_mailout_report extends \cenozo\ui\pull\base_report
{
  /**
   * Constructor
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $subject The subject to retrieve the primary information from.
   * @param array $args Pull arguments.
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'withdraw_mailout', $args );
  }

  /**
   * Builds the report.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access protected
   */
  protected function build()
  {
    $event_type_class_name = lib::get_class_name( 'database\event_type' );
    $db = lib::create( 'business\session' )->get_database();
    $participant_class_name = lib::get_class_name( 'database\participant' );

    $collection_id = $this->get_argument( 'restrict_collection_id' );
    $db_collection = $collection_id ? lib::create( 'database\collection', $collection_id ) : NULL;
    $mark_mailout = $this->get_argument( 'mark_mailout' );
    $db_event_type = $event_type_class_name::get_unique_record( 'name', 'withdraw mailed' );

    $sql = sprintf(
      'SELECT DISTINCT participant.id FROM participant '.
      'JOIN event ON participant.id = event.participant_id '.
      'AND event.event_type_id = %s ',
      $db->format_string( $db_event_type->id ) );

    // create the participant modifier based on the withdraw script
    $participant_mod = lib::create( 'database\modifier' );
    if( !is_null( $db_collection ) )
      $participant_mod->where(
        'collection_has_participant.collection_id', '=', $db_collection->id );
    $participant_mod->where( 'withdraw_letter', '!=', NULL );
    $participant_mod->where( 'withdraw_letter', '<', 'o' );
    $participant_mod->where( 'withdraw_letter', '!=', '0' );
    $participant_mod->where( 'id', 'NOT IN', sprintf( '( %s )', $sql ), false );
    $participant_mod->order( 'uid' );

    // create the content
    $content = array();
    foreach( $participant_class_name::select( $participant_mod ) as $db_participant )
    {
      $db_address = $db_participant->get_first_address();
      if( is_null( $db_address ) ) continue;
      $db_region = $db_address->get_region();

      $address = $db_address->address1;
      if( !is_null( $db_address->address2 ) ) $address .= ' '.$db_address->address2;

      $db_language = $db_participant->get_language();
      if( is_null( $db_language ) )
        $db_language = lib::create( 'business\session' )->get_service()->get_language();

      $content[] = array(
        $db_language->code,
        $db_participant->first_name,
        $db_participant->last_name,
        $address,
        $db_address->city,
        $db_region->name,
        $db_address->postcode,
        $db_participant->withdraw_letter );

      // add withdraw mailed event if requested to
      if( $mark_mailout )
      {
        $db_event = lib::create( 'database\event' );
        $db_event->participant_id = $db_participant->id;
        $db_event->event_type_id = $db_event_type->id;
        $db_event->datetime = util::get_datetime_object()->format( 'Y-m-d H:i:s' );
        $db_event->save();
      }
    }

    // create the header
    $header = array(
      'Language',
      'First Name',
      'Last Name',
      'Address',
      'City',
      'Province',
      'Postal Code',
      'Withdraw Type' );

    $this->add_table( NULL, $header, $content );
  }
}
