<?php
/**
 * quota_report.class.php
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
class quota_report extends \cenozo\ui\pull\base_report
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
    parent::__construct( 'quota', $args );
  }

  /**
   * Processes arguments, preparing them for the operation.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @throws exception\notice
   * @access protected
   */
  protected function prepare()
  {
    parent::prepare();

    // check to see if a cohort-specific template exists for this report
    $db_cohort = lib::create( 'database\cohort', $this->get_argument( 'cohort_id' ) );
    $filename = sprintf( '%s/report/%s_%s.xls',
                         DOC_PATH,
                         $this->get_full_name(),
                         $db_cohort->name );
    if( file_exists( $filename ) ) $this->report = lib::create( 'business\report', $filename );
  }

  /**
   * Builds the report.
   * @author Dean Inglis <inglisd@mcmaster.ca>
   * @access protected
   */
  protected function build()
  {
    $this->report->set_orientation( 'landscape' );

    $service_class_name = lib::get_class_name( 'database\service' );
    $quota_class_name = lib::get_class_name( 'database\quota' );
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $event_type_class_name = lib::get_class_name( 'database\event_type' );
    $db = lib::create( 'business\session' )->get_database();

    $db_cohort = lib::create( 'database\cohort', $this->get_argument( 'cohort_id' ) );
    $low_education = $this->get_argument( 'low_education', false );
    if( 'comprehensive' == $db_cohort->name )
    {
      $site_breakdown = true;
      $db_service = $service_class_name::get_unique_record( 'name', 'Beartooth' );
      $db_first_attempt_event_type =
        $event_type_class_name::get_unique_record( 'name', 'first attempt (Baseline Home)' );
      $db_reached_event_type =
        $event_type_class_name::get_unique_record( 'name', 'reached (Baseline Home)' );
      $db_completed_event_type =
        $event_type_class_name::get_unique_record( 'name', 'completed (Baseline Home)' );

      // create a temporary last appointment table
      $participant_class_name::db()->execute(
        'CREATE TEMPORARY TABLE temp_last_appointment '.
        'SELECT * FROM beartooth_participant_last_appointment' );
      $participant_class_name::db()->execute(
        'ALTER TABLE temp_last_appointment '.
        'ADD INDEX dk_participant_id ( participant_id )' );
    }
    else
    {
      $site_breakdown = false;
      $db_service = $service_class_name::get_unique_record( 'name', 'Sabretooth' );
      $db_first_attempt_event_type =
        $event_type_class_name::get_unique_record( 'name', 'first attempt (Baseline)' );
      $db_reached_event_type =
        $event_type_class_name::get_unique_record( 'name', 'reached (Baseline)' );
      $db_completed_event_type =
        $event_type_class_name::get_unique_record( 'name', 'completed (Baseline)' );

      // create a temporary last appointment table
      $participant_class_name::db()->execute(
        'CREATE TEMPORARY TABLE temp_last_appointment '.
        'SELECT * FROM sabretooth_participant_last_appointment' );
      $participant_class_name::db()->execute(
        'ALTER TABLE temp_last_appointment '.
        'ADD INDEX dk_participant_id ( participant_id )' );

      // create a temporary last written consent table
      $participant_class_name::db()->execute(
        'CREATE TEMPORARY TABLE temp_last_written_consent '.
        'SELECT * FROM participant_last_written_consent' );
      $participant_class_name::db()->execute(
        'ALTER TABLE temp_last_written_consent '.
        'ADD INDEX dk_participant_id ( participant_id )' );
    }

    $collection_id = $this->get_argument( 'restrict_collection_id' );
    $db_collection = $collection_id ? lib::create( 'database\collection', $collection_id ) : NULL;
    $source_id = $this->get_argument( 'restrict_source_id' );
    $db_source = $source_id ? lib::create( 'database\source', $source_id ) : NULL;
    $restrict_start_date = $this->get_argument( 'restrict_start_date' );
    $restrict_end_date = $this->get_argument( 'restrict_end_date' );
    $start_datetime_obj = NULL;
    $end_datetime_obj = NULL;

    if( 0 < strlen( $restrict_start_date ) )
      $start_datetime_obj = util::get_datetime_object( $restrict_start_date );
    if( 0 < strlen( $restrict_end_date ) )
      $end_datetime_obj = util::get_datetime_object( $restrict_end_date );
    if( 0 < strlen( $restrict_start_date ) && 0 < strlen( $restrict_end_date ) &&
        $end_datetime_obj < $start_datetime_obj )
    {   
      $temp_datetime_obj = clone $start_datetime_obj;
      $start_datetime_obj = clone $end_datetime_obj;
      $end_datetime_obj = clone $temp_datetime_obj;
    }   

    if( $site_breakdown )
    {
      // create a temporary last consent table
      $participant_class_name::db()->execute(
        'CREATE TEMPORARY TABLE temp_site '.
        'SELECT * FROM participant_site' );
      $participant_class_name::db()->execute(
        'ALTER TABLE temp_site '.
        'ADD INDEX dk_participant_id_site_id ( participant_id, site_id )' );
    }

    // create a temporary last consent table
    $participant_class_name::db()->execute(
      'CREATE TEMPORARY TABLE temp_last_consent '.
      'SELECT * FROM participant_last_consent' );
    $participant_class_name::db()->execute(
      'ALTER TABLE temp_last_consent '.
      'ADD INDEX dk_participant_id ( participant_id )' );

    // loop through all quotas by region or site (based on breakdown), age group and gender
    $quota_mod = lib::create( 'database\modifier' );
    $quota_mod->where( 'site.service_id', '=', $db_service->id );
    $quota_mod->order( $site_breakdown ? 'site.name' : 'region.name' );
    $quota_mod->order( 'age_group.lower' );
    $quota_mod->order( 'gender' );
    foreach( $quota_class_name::select( $quota_mod ) as $db_quota )
    {
      $column = 'B';
      $no_interview_column_list = array();

      // determine the id of the site or region (based on breakdown)
      $site_region_id = $site_breakdown ? $db_quota->site_id : $db_quota->region_id;

      // common modifier used by all queries
      $base_mod = lib::create( 'database\modifier' );
      $base_mod->where( 'cohort_id', '=', $db_cohort->id );

      // the following is temporary
      if( $low_education && $participant_class_name::column_exists( 'low_education', true ) )
        $base_mod->where( 'low_education', '=', true );
      // //////////////////////////

      if( $site_breakdown )
      {
        $base_mod->where( 'participant.id', '=', 'temp_site.participant_id', false );
        $base_mod->where( 'temp_site.site_id', '=', $site_region_id );
      }
      else
      {
        $base_mod->where( 'participant_primary_address.address_id', '=', 'address.id', false );
        $base_mod->where( 'address.region_id', '=', $site_region_id );
      }
      $base_mod->where( 'age_group_id', '=', $db_quota->age_group_id );
      $base_mod->where( 'gender', '=', $db_quota->gender );
      if( !is_null( $start_datetime_obj ) )
        $base_mod->where( 'participant.create_timestamp', '>=',
          $start_datetime_obj->format( 'Y-m-d 00:00:00' ) );
      if( !is_null( $end_datetime_obj ) )
        $base_mod->where( 'participant.create_timestamp', '<=',
          $end_datetime_obj->format( 'Y-m-d 23:59:59' ) );
      if( !is_null( $db_collection ) )
        $base_mod->where( 'collection_has_participant.collection_id', '=', $db_collection->id );
      if( !is_null( $db_source ) ) $base_mod->where( 'source_id', '=', $db_source->id );

      // sql to determine which participants in this category have completed the interview
      $completed_sql = sprintf(
        'SELECT participant.id FROM event '.
        'WHERE event.participant_id = participant.id AND event.event_type_id = %s',
        $db->format_string( $db_completed_event_type->id ) );

      $completed_sql = '( '.$completed_sql.' )';

      // pre-recruit (total participants) //////////////////////////////////////////////////////////
      $participant_mod = clone $base_mod;
      $this->population_data
        [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
          intval( $participant_class_name::count( $participant_mod ) );
      $column++;

      // open for access (released participants) ///////////////////////////////////////////////////
      $participant_mod = clone $base_mod;
      $participant_mod->where( 'service_has_participant.service_id', '=', $db_service->id );
      $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );

      $this->population_data
        [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
          intval( $participant_class_name::count( $participant_mod ) );
      $column++;

      // contact attempted (Baseline for Sabretooth, Baseline Home for Beartooth) //////////////////
      $participant_mod = clone $base_mod;
      $participant_mod->where( 'service_has_participant.service_id', '=', $db_service->id );
      $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );
      $participant_mod->where( 'event.event_type_id', '=', $db_first_attempt_event_type->id );

      // but has not completed the interview
      $participant_mod->where( 'participant.id', 'NOT IN', $completed_sql, false );
      $no_interview_column_list[] = $column;

      $this->population_data
        [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
          intval( $participant_class_name::count( $participant_mod ) );
      $column++;

      // reached and viable ////////////////////////////////////////////////////////////////////////
      if( 'sabretooth' == $db_service->name )
      {
        $participant_mod = clone $base_mod;
        $participant_mod->where( 'service_has_participant.service_id', '=', $db_service->id );
        $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );
        $participant_mod->where( 'event.event_type_id', '=', $db_reached_event_type->id );

        // and is eligible
        $participant_mod->where( 'participant.state_id', '=', NULL );
        $participant_mod->where( 'participant.id', '=', 'temp_last_consent.participant_id', false );
        $participant_mod->where( 'IFNULL( temp_last_consent.accept, true )', '=', true );

        // but has not completed the interview
        $participant_mod->where( 'participant.id', 'NOT IN', $completed_sql, false );
        $no_interview_column_list[] = $column;

        $this->population_data
          [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
            intval( $participant_class_name::count( $participant_mod ) );
        $column++;
      }

      // with appointment //////////////////////////////////////////////////////////////////////////
      $participant_mod = clone $base_mod;
      $participant_mod->where( 'service_has_participant.service_id', '=', $db_service->id );
      $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );
      $participant_mod->where( 'participant.id', '=', 'temp_last_appointment.participant_id', false );
      $participant_mod->where( 'temp_last_appointment.appointment_id', '!=', NULL );
      if( 'sabretooth' == $db_service->name )
      { // sabretooth appointment with no reached status
        $participant_mod->where( 'temp_last_appointment.reached', '=', NULL );
      }
      else
      { // beartooth appointment which has not been completed
        $participant_mod->where( 'temp_last_appointment.completed', '=', false );
      }

      // and is eligible
      $participant_mod->where( 'participant.state_id', '=', NULL );
      $participant_mod->where( 'participant.id', '=', 'temp_last_consent.participant_id', false );
      $participant_mod->where( 'IFNULL( temp_last_consent.accept, true )', '=', true );

      // but has not completed the interview
      $participant_mod->where( 'participant.id', 'NOT IN', $completed_sql, false );
      $no_interview_column_list[] = $column;

      $this->population_data
        [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
          intval( $participant_class_name::count( $participant_mod ) );
      $column++;

      // interview complete (Baseline for Sabretooth, Baseline Home for Beartooth) /////////////////
      $participant_mod = clone $base_mod;
      $participant_mod->where( 'service_has_participant.service_id', '=', $db_service->id );
      $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );
      $participant_mod->where( 'event.event_type_id', '=', $db_completed_event_type->id );

      // and is eligible to continue
      $participant_mod->where( 'participant.state_id', '=', NULL );
      $participant_mod->where( 'participant.id', '=', 'temp_last_consent.participant_id', false );
      $participant_mod->where( 'IFNULL( temp_last_consent.accept, true )', '=', true );
      $count = intval( $participant_class_name::count( $participant_mod ) );
      $this->population_data
        [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] = $count;

      // add the completed interview count to those columns where they were removed above
      foreach( $no_interview_column_list as $no_interview_column )
        $this->population_data
          [$site_region_id][$db_quota->age_group_id][$no_interview_column][$db_quota->gender] +=
            $count;

      $column++;

      if( 'beartooth' == $db_service->name )
      {
        // interview complete (beartooth site interview) ///////////////////////////////////////////
        $db_event_type =
          $event_type_class_name::get_unique_record( 'name', 'completed (Baseline Site)' );
        $participant_mod = clone $base_mod;
        $participant_mod->where( 'service_has_participant.service_id', '=', $db_service->id );
        $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );
        $participant_mod->where( 'event.event_type_id', '=', $db_event_type->id );
        $this->population_data
          [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
            intval( $participant_class_name::count( $participant_mod ) );
        $column++;
      }
      else
      {
        // with consent //////////////////////////////////////////////////////////////////////////////
        $participant_mod = clone $base_mod;
        $participant_mod->where( 'service_has_participant.service_id', '=', $db_service->id );
        $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );
        $participant_mod->where( 'event.event_type_id', '=', $db_completed_event_type->id );
        $participant_mod->where(
          'participant.id', '=', 'temp_last_written_consent.participant_id', false );
        $participant_mod->where( 'temp_last_written_consent.accept', '=', true );
        $this->population_data
          [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
            intval( $participant_class_name::count( $participant_mod ) );
        $column++;
      }

      // grab the quota data itself ////////////////////////////////////////////////////////////////
      $this->population_data
        [$site_region_id][$db_quota->age_group_id][$column][$db_quota->gender] =
          intval( $db_quota->population );
      $column++;
    }
  }

  /**
   * This method creates the report based on work done in the build() method.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access protected
   */
  protected function generate()
  {
    // replace the parent method (parent generate() method isn't called on purpose)

    // the initial row is predefined by the report template
    $row = 16;

    foreach( $this->population_data as $site_region_data )
    {
      foreach( $site_region_data as $age_data )
      {
        foreach( $age_data as $column => $gender_data )
        {
          $this->report->set_cell( $column.$row, $gender_data['male'], false );
          $this->report->set_cell( $column.( $row + 1 ), $gender_data['female'], false );
        }
        $row += 2; // jump to the next age block
      }
      $row += 2; // jump to the next site/region block
    }
    
    // set the titles
    $db_cohort = lib::create( 'database\cohort', $this->get_argument( 'cohort_id' ) );
    $source_id = $this->get_argument( 'restrict_source_id' );
    $source = $source_id ? lib::create( 'database\source', $source_id )->name : 'all sources';
    $this->report->set_size( 16 );
    $this->report->set_bold( true );
    $this->report->set_horizontal_alignment( 'center' );
    $this->report->merge_cells( 'A1:M1' );
    
    $title = sprintf( '%s Quota Report for %s',
                      ucwords( $db_cohort->name ),
                      ucwords( $source ) );
    $collection_id = $this->get_argument( 'restrict_collection_id' );
    if( $collection_id )
    {
      $db_collection = lib::create( 'database\collection', $collection_id );
      $title .= sprintf( ' (for the "%s" collection)', $db_collection->name );
    }
    $this->report->set_cell( 'A1', $title, false );

    $now_datetime_obj = util::get_datetime_object();
    $this->report->merge_cells( 'A2:M2' );
    $this->report->set_cell( 'A2',
      sprintf( 'Generated on %s at %s',
               $now_datetime_obj->format( 'Y-m-d' ),
               $now_datetime_obj->format( 'H:i T' ) ),
      false );

    $restrict_start_date = $this->get_argument( 'restrict_start_date' );
    $restrict_end_date = $this->get_argument( 'restrict_end_date' );

    if( 0 < strlen( $restrict_start_date ) && is_null( $restrict_end_date ) )
      $this->report->set_cell( 'A3',
        sprintf( 'Restricted to participants imported from %s',
                 $restrict_start_date ),
        false );

    if( is_null( $restrict_start_date ) && 0 < strlen( $restrict_end_date ) )
      $this->report->set_cell( 'A3',
        sprintf( 'Restricted to participants imported up to %s',
                 $restrict_end_date ),
        false );

    if( 0 < strlen( $restrict_start_date ) && 0 < strlen( $restrict_end_date ) )
      $this->report->set_cell( 'A3',
        sprintf( 'Restricted to participants imported from %s to %s',
                 $restrict_start_date,
                 $restrict_end_date ),
        false );

    $this->data = $this->report->get_file( $this->get_argument( 'format' ) );
  }

  /**
   * An internal array which holds all of the data used by the report
   * @var array
   * @access private
   */
  private $population_data;
}
