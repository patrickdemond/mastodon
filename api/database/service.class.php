<?php
/**
 * service.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\database;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * service: record
 */
class service extends \cenozo\database\service
{
  /**
   * Releases participants to this service according to the provided modifier or,
   * if the $get_unreleased paramter is set to true, returns a list of participants who have
   * not yet been released.
   * If no modifier is provided then all unreleased participants will be released.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the selection.
   * @param boolean $get_unreleased Whether to return the unreleased participants instead of
   *                releasing them.
   * @access public
   */
  public function release_participant( $modifier = NULL, $get_unreleased = false )
  {
    $database_class_name = lib::get_class_name( 'database\database' );

    if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'service_has_participant.datetime', '=', NULL );

    $datetime = util::get_datetime_object()->format( 'Y-m-d H:i:s' );

    $sql = $get_unreleased
         ? 'SELECT DISTINCT participant.id '
         : sprintf( 'INSERT INTO service_has_participant'.
                    '( service_id, participant_id, create_timestamp, datetime ) '.
                    'SELECT %s, participant.id, NULL, %s ',
                    $database_class_name::format_string( $this->id ),
                    $database_class_name::format_string( $datetime ) );

    $sql .= sprintf(
      'FROM participant '.
      'JOIN service_has_cohort '.
      'ON service_has_cohort.cohort_id = participant.cohort_id '.
      'AND service_has_cohort.service_id = %s '.
      'LEFT JOIN service_has_participant '.
      'ON service_has_participant.participant_id = participant.id '.
      'LEFT JOIN import_entry '.
      'ON import_entry.participant_id = participant.id '.
      'LEFT JOIN contact_form '.
      'ON contact_form.participant_id = participant.id %s',
      $database_class_name::format_string( $this->id ),
      $modifier->get_sql() );
    
    if( !$get_unreleased )
      $sql .= sprintf( ' ON DUPLICATE KEY UPDATE datetime = %s',
                       $database_class_name::format_string( $datetime ) );

    if( $get_unreleased )
    {
      $id_list = static::db()->get_col( $sql );
      $records = array();
      foreach( $id_list as $id ) $records[] = lib::create( 'database\participant', $id );
      return $records;
    }
    else
    {
      static::db()->execute( $sql );
    }
  }
}
