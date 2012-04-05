<?php
/**
 * base_view.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package mastodon\ui
 * @filesource
 */

namespace mastodon\ui\widget;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * Base class for widgets which view forms.
 * 
 * @abstract
 * @package cenozo\ui
 */
abstract class base_form_view extends \cenozo\ui\widget\base_record
{
  /**
   * Constructor
   * 
   * Defines all variables which need to be set for the associated template.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $subject The subject being viewed.
   * @param array $args An associative array of arguments to be processed by th  widget
   * @throws exception\argument
   * @access public
   */
  public function __construct( $subject, $args )
  {
    parent::__construct( $subject, 'view', $args );
    
    // make sure we have an id (we don't actually need to use it since the parent does)
    $this->get_argument( 'id' );

    // determine properties based on the current user's permissions
    $operation_class_name = lib::get_class_name( 'database\operation' );
    $session = lib::create( 'business\session' );

    $this->set_heading( 'Viewing '.$this->get_subject().' details' );

    // Set the two form entries
    $form_entry_list_method = sprintf( 'get_%s_entry_list', $this->get_subject() );
    $form_entry_list = $this->get_record()->$form_entry_list_method();
    $db_form_entry_1 = current( $form_entry_list );
    $db_form_entry_2 = next( $form_entry_list ); 

    $this->set_form_entries(
      false == $db_form_entry_1 ? NULL : $db_form_entry_1,
      false == $db_form_entry_2 ? NULL : $db_form_entry_2 );
  }
  
  /**
   * Finish setting the variables in a widget.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function finish()
  {
    parent::finish();
    
    // validate the entries
    $error_list_1 = array();
    if( !is_null( $this->form_entry_1 ) )
    {
      $args = array( 'id' => $this->form_entry_1->id );
      $operation = lib::create( sprintf( 'ui\pull\%s_entry_validate', $this->get_subject() ), $args );
      $error_list_1 = $operation->finish();
    }
    $error_list_2 = array();
    if( !is_null( $this->form_entry_2 ) )
    {
      $args = array( 'id' => $this->form_entry_2->id );
      $operation = lib::create( sprintf( 'ui\pull\%s_entry_validate', $this->get_subject() ), $args );
      $error_list_2 = $operation->finish();
    }

    foreach( $this->items as $item_id => $item )
    {
      $this->items[$item_id]['entry_1'] = is_null( $this->form_entry_1 )
        ? array( 'user' => 'n/a',
                 'error' => false,
                 'value' => '(no value)' )
        : array( 'user' => sprintf( '%s%s',
                                    $this->form_entry_1->get_user()->name,
                                    $this->form_entry_1->deferred ? ' (deferred)' : '' ),
                 'error' => array_key_exists( $item_id, $error_list_1 )
                          ? $error_list_1[$item_id] : false,
                 'value' => is_null( $this->form_entry_1->$item_id )
                          ? '(no value)' : $this->form_entry_1->$item_id );
      $this->items[$item_id]['entry_2'] = is_null( $this->form_entry_2 )
        ? array( 'user' => 'n/a',
                 'error' => false,
                 'value' => '(no value)' )
        : array( 'user' => sprintf( '%s%s',
                                    $this->form_entry_2->get_user()->name,
                                    $this->form_entry_2->deferred ? ' (deferred)' : '' ),
                 'error' => array_key_exists( $item_id, $error_list_2 )
                          ? $error_list_2[$item_id] : false,
                 'value' => is_null( $this->form_entry_2->$item_id ) 
                          ? '(no value)' : $this->form_entry_2->$item_id );
    }

    $this->set_variable( 'entry_1', is_null( $this->form_entry_1 )
      ? array( 'exists' => false )
      : array( 'exists' => true,
               'id' => $this->form_entry_1->id,
               'deferred' => $this->form_entry_1->deferred,
               'user' => $this->form_entry_1->get_user()->name ) );
    $this->set_variable( 'entry_2', is_null( $this->form_entry_2 )
      ? array( 'exists' => false )
      : array( 'exists' => true,
               'id' => $this->form_entry_2->id,
               'deferred' => $this->form_entry_2->deferred,
               'user' => $this->form_entry_2->get_user()->name ) );

    $this->set_variable( 'item', $this->items );
    $this->set_variable( 'allow_adjudication',
      !is_null( $this->form_entry_1 ) && !$this->form_entry_1->deferred &&
      !is_null( $this->form_entry_2 ) && !$this->form_entry_2->deferred );
  }
  
  /**
   * Add an item to the form view.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $item_id The item's id, can be one of the record's column names.
   * @param string $heading The item's heading as it will appear in the view
   * @param string $note A note to add below the item.
   * @access public
   */
  public function add_item( $item_id, $heading, $note = NULL )
  {
    $this->items[$item_id] = array(
      'heading' => $heading,
      'type' => 'constant' );
    if( !is_null( $note ) ) $this->items[$item_id]['note'] = $note;
  }

  /**
   * Sets and item's value and additional data.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $item_id The item's id, can be one of the record's column names.
   * @param record $form_entry_1 The first entry to the form.
   * @param record $form_entry_2 The second entry to the form.
   * @throws exception\argument
   * @access public
   */
  public function set_form_entries( $form_entry_1 = NULL, $form_entry_2 = NULL )
  {
    $this->form_entry_1 = $form_entry_1;
    $this->form_entry_2 = $form_entry_2;
  }

  /**
   * An associative array where the key is a unique identifier (usually a column name) and the
   * value is an associative array which includes:
   * "heading" => the label to display
   * "type" => the type of variable (see {@link add_item} for details)
   * "value" => the value of the column
   * "enum" => all possible values if the item type is "enum"
   * "required" => boolean describes whether the value can be left blank
   * @var array
   * @access private
   */
  private $items = array();

  /**
   * The first user's entry for this form.
   * @var database\form_entry $form_entry_1
   * @access protected
   */
  protected $form_entry_1 = NULL;

  /**
   * The second user's entry for this form.
   * @var database\form_entry $form_entry_1
   * @access protected
   */
  protected $form_entry_2 = NULL;
}
?>
