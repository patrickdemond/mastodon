<?php
/**
 * service_participant_release.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\ui\push;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * push: service participant_release
 *
 * Syncs service information between Sabretooth and Mastodon
 */
class service_participant_release extends \cenozo\ui\push
{
  /**
   * Constructor.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args Push arguments
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'service', 'participant_release', $args );
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

    $db_service = lib::create( 'database\service', $this->get_argument( 'service_id' ) );
    $uid_list_string = preg_replace( '/[^a-zA-Z0-9]/', ' ', $this->get_argument( 'uid_list' ) );
    $uid_list_string = trim( $uid_list_string );
    $start_date = $this->get_argument( 'start_date', '' );
    $end_date = $this->get_argument( 'end_date', '' );
    
    $service_mod = lib::create( 'database\modifier' );

    // include participants in the list only
    $uid_list = array_unique( preg_split( '/\s+/', $uid_list_string ) );
    if( 1 == count( $uid_list ) && '' == $uid_list[0] ) $uid_list = array();

    if( 0 < count( $uid_list ) ) $service_mod->where( 'uid', 'IN', $uid_list );

    if( 0 < strlen( $start_date ) || 0 < strlen( $end_date ) )
    { // use start/end date to select participants
      $service_mod->where_bracket( true );
      $service_mod->where_bracket( true );
      if( 0 < strlen( $start_date ) )
        $service_mod->where( 'import_entry.date', '>=', $start_date );
      if( 0 < strlen( $end_date ) )
        $service_mod->where( 'import_entry.date', '<=', $end_date );
      $service_mod->where_bracket( false );
      $service_mod->where_bracket( true, true ); // or
      if( 0 < strlen( $start_date ) )
        $service_mod->where( 'contact_form.date', '>=', $start_date );
      if( 0 < strlen( $end_date ) )
        $service_mod->where( 'contact_form.date', '<=', $end_date );
      $service_mod->where_bracket( false );
      $service_mod->where_bracket( false );
    }
    else
    { // do not allow all participants if there is no date span
      if( 0 == count( $uid_list ) ) $service_mod->where( 'uid', 'IN', array() );
    }
    
    $db_service->release_participant( $service_mod );
  }
}
