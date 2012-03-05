<?php
/**
 * consent_form_list.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package mastodon\ui
 * @filesource
 */

namespace mastodon\ui\widget;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * widget consent_form list
 * 
 * @package mastodon\ui
 */
class consent_form_list extends \cenozo\ui\widget\base_list
{
  /**
   * Constructor
   * 
   * Defines all variables required by the consent_form list.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args An associative array of arguments to be processed by the widget
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'consent_form', $args );
    
    $this->add_column( 'id', 'number', 'ID', true );
    $this->add_column( 'date', 'date', 'Date Added', true );
    $this->add_column( 'conflict', 'boolean', 'Conflict', false );
  }
  
  /**
   * Set the rows array needed by the template.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function finish()
  {
    parent::finish();
    
    $session = lib::create( 'business\session' );
    $db_user = $session->get_user();
    $db_role = $session->get_role();

    foreach( $this->get_record_list() as $record )
    {
      // determine if the form has a conflict
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'deferred', '!=', true );
      $conflict = 1 < count( $record->get_consent_form_entry_list( $modifier ) );

      $this->add_row( $record->id,
        array( 'id' => $record->id,
               'date' => util::get_formatted_date( $record->date ),
               'conflict' => $conflict ) );
    }

    $this->finish_setting_rows();
  }

  /**
   * Overrides the parent class method to restrict consent_form list
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the list.
   * @return int
   * @access protected
   */
  protected function determine_record_count( $modifier = NULL )
  {
    if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'invalid', '!=', true );
    $modifier->where( 'consent_id', '=', NULL );

    return parent::determine_record_count( $modifier );
  }
  
  /**
   * Overrides the parent class method to restrict consent_form list
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the list.
   * @return array( record )
   * @access protected
   */
  protected function determine_record_list( $modifier = NULL )
  {
    if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'invalid', '!=', true );
    $modifier->where( 'consent_id', '=', NULL );

    return parent::determine_record_list( $modifier );
  }
}
?>
