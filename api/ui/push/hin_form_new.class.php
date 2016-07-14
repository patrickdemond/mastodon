<?php
/**
 * hin_form_new.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\ui\push;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * push: hin_form new
 *
 * This is a special operation that creates a hin form including a entry and immediately
 * attempts to import the data.  This is used by Beartooth in order to validate and import
 * hin forms from Onyx.
 */
class hin_form_new extends \cenozo\ui\push\base_new
{
  /**
   * Constructor.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args Push arguments
   * @access public
   */
  public function __construct( $args )
  {
    if( array_key_exists( 'noid', $args ) )
    {
      $user_class_name = lib::get_class_name( 'database\user' );

      // use the noid argument and remove it from the args input
      $noid = $args['noid'];
      unset( $args['noid'] );

      // make sure there is sufficient information
      if( !is_array( $noid ) ||
          !array_key_exists( 'user.name', $noid ) )
        throw lib::create( 'exception\argument', 'noid', $noid, __METHOD__ );
      
      $db_user = $user_class_name::get_unique_record( 'name', $noid['user.name'] );
      if( !$db_user ) throw lib::create( 'exception\argument', 'noid', $noid, __METHOD__ );
      $args['entry']['user_id'] = $db_user->id;
    }

    parent::__construct( 'hin_form', $args );
  }

  /**
   * Executes the push.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access protected
   */
  protected function finish()
  {
    parent::finish();

    // if a form variable was included try to decode it and store it as a hin form
    $form = $this->get_argument( 'form', NULL );
    if( !is_null( $form ) ) 
    {
      $form_decoded = base64_decode( chunk_split( $form ) );
      if( false == $form_decoded )
        throw lib::create( 'exception\runtime', 'Unable to decode form argument.', __METHOD__ );

      // create a new hin form
      $this->get_record()->write_form( $form_decoded );
    }

    // if an entry was included add it and try importing the form immediately
    $entry = $this->get_argument( 'entry', NULL );    
    if( !is_null( $entry ) && is_array( $entry ) )
    {
      $db_hin_form_entry = lib::create( 'database\hin_form_entry' );
      $db_hin_form_entry->hin_form_id = $this->get_record()->id;
      $db_hin_form_entry->deferred = false;
      $db_hin_form_entry->signed = !is_null( $form );
      foreach( $entry as $column => $value ) $db_hin_form_entry->$column = $value;

      $db_hin_form_entry->save();

      // validate the entry
      $op_validate = lib::create( 'ui\pull\hin_form_entry_validate',
                                  array( 'id' => $db_hin_form_entry->id ) );
      $op_validate->process();
      $errors = $op_validate->get_data();

      // if there are no errors import the entry
      if( 0 == count( $errors ) ) $this->get_record()->import( $db_hin_form_entry );
    }
  }
}
