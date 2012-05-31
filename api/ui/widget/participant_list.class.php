<?php
/**
 * participant_list.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package mastodon\ui
 * @filesource
 */

namespace mastodon\ui\widget;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * widget participant list
 * 
 * @package mastodon\ui
 */
class participant_list extends site_restricted_list
{
  /**
   * Constructor
   * 
   * Defines all variables required by the participant list.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args An associative array of arguments to be processed by the widget
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'participant', $args );
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
    
    $this->add_column( 'uid', 'string', 'Unique ID', true );
    $this->add_column( 'first_name', 'string', 'First Name', true );
    $this->add_column( 'last_name', 'string', 'Last Name', true );
    $this->add_column( 'active', 'boolean', 'Active', true );
    $this->add_column( 'source.name', 'string', 'Source', true );
    $this->add_column( 'cohort', 'string', 'Cohort', true );
    $this->add_column( 'primary_site', 'string', 'Site', false );

    // participants are either jurisdiction or participant_site based
    $this->extended_site_selection = true;
  }
  
  /**
   * Set the rows array needed by the template.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access protected
   */
  protected function setup()
  {
    parent::setup();
    
    foreach( $this->get_record_list() as $record )
    {
      $db_source = $record->get_source();
      $source_name = is_null( $db_source ) ? '(none)' : $db_source->name;
      $this->add_row( $record->id,
        array( 'uid' => $record->uid ? $record->uid : '(none)',
               'first_name' => $record->first_name,
               'last_name' => $record->last_name,
               'active' => $record->active,
               'source.name' => $source_name,
               'cohort' => $record->cohort,
               'primary_site' => $record->get_primary_site()->name,
               // note count isn't a column, it's used for the note button
               'note_count' => $record->get_note_count() ) );
    }

    $operation_class_name = lib::get_class_name( 'database\operation' );

    $db_operation = $operation_class_name::get_operation( 'widget', 'import', 'add' );
    if( lib::create( 'business\session' )->is_allowed( $db_operation ) )
      $this->add_action( 'import', 'Import', $db_operation,
        'Import participants from an external CSV file' );

    $db_operation =
      $operation_class_name::get_operation( 'widget', 'participant', 'site_reassign' );
    if( lib::create( 'business\session' )->is_allowed( $db_operation ) )
      $this->add_action( 'reassign', 'Site Reassign', $db_operation,
        'Change the preferred site of multiple participants at once' );
  }
}
?>
