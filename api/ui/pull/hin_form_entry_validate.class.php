<?php
/**
 * hin_form_entry_validate.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\ui\pull;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * pull: hin_form_entry validate
 */
class hin_form_entry_validate extends \cenozo\ui\pull\base_record
{
  /**
   * Constructor
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args Pull arguments.
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'hin_form_entry', 'validate', $args );
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

    $participant_class_name = lib::get_class_name( 'database\participant' );
    $errors = array();

    // validate each entry value in the form
    if( is_null( $this->get_record()->uid ) )
      $errors['uid'] = 'This value cannot be left blank.';
    else
    {
      $db_participant =
        $participant_class_name::get_unique_record( 'uid', $this->get_record()->uid );
      if( is_null( $db_participant ) )
        $errors['uid'] = 'No such participant exists.';
    }

    $this->data = $errors;
  }

  /**
   * Implements the parent's abstract method (data type is always json)
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return string
   * @access public
   */
  public function get_data_type()
  {
    return 'json';
  }
}
