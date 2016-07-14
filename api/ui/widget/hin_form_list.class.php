<?php
/**
 * hin_form_list.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\ui\widget;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * widget hin_form list
 */
class hin_form_list extends base_form_list
{
  /**
   * Constructor
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args An associative array of arguments to be processed by the widget
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'hin', $args );
  }

  /**
   * Overrides the parent class method to restrict form list
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the list.
   * @return int
   * @access protected
   */
  public function determine_record_count( $modifier = NULL )
  {
    // restrict by cohort, if necessary
    $restrict_cohort_id = $this->get_argument( 'restrict_cohort_id', 0 );
    if( 0 < $restrict_cohort_id )
    {
      $sub_mod = lib::create( 'database\modifier' );
      $sub_mod->where( 'hin_form_id', '=', 'hin_form.id', false );
      $sub_mod->where( 'hin_form_entry.uid', '=', 'participant.uid', false );
      $sub_mod->where( 'participant.cohort_id', '=', $restrict_cohort_id );
      $min_hin_form_entry_sql = sprintf(
        '( SELECT MIN( hin_form_entry.id ) FROM hin_form_entry, participant %s )',
        $sub_mod->get_sql() );
      if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'hin_form_entry.id', '=', $min_hin_form_entry_sql, false );
    }

    return parent::determine_record_count( $modifier );
  }
  
  /** 
   * Overrides the parent class method to restrict the list
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the list.
   * @return array( record )
   * @access protected
   */
  public function determine_record_list( $modifier = NULL )
  {
    // restrict by cohort, if necessary
    $restrict_cohort_id = $this->get_argument( 'restrict_cohort_id', 0 );
    if( 0 < $restrict_cohort_id )
    {
      $sub_mod = lib::create( 'database\modifier' );
      $sub_mod->where( 'hin_form_id', '=', 'hin_form.id', false );
      $sub_mod->where( 'hin_form_entry.uid', '=', 'participant.uid', false );
      $sub_mod->where( 'participant.cohort_id', '=', $restrict_cohort_id );
      $min_hin_form_entry_sql = sprintf(
        '( SELECT MIN( hin_form_entry.id ) FROM hin_form_entry, participant %s )',
        $sub_mod->get_sql() );
      if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'hin_form_entry.id', '=', $min_hin_form_entry_sql, false );
    }

    return parent::determine_record_list( $modifier );
  }
}
