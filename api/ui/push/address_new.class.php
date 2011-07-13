<?php
/**
 * address_new.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package mastodon\ui
 * @filesource
 */

namespace mastodon\ui\push;
use mastodon\log, mastodon\util;
use mastodon\business as bus;
use mastodon\database as db;
use mastodon\exception as exc;

/**
 * push: address new
 *
 * Create a new address.
 * @package mastodon\ui
 */
class address_new extends base_new
{
  /**
   * Constructor.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args Push arguments
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'address', $args );
  }

  /**
   * Overrides the parent method to make sure the postcode is valid.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @throws exception\notice
   * @access public
   */
  public function finish()
  {
    $columns = $this->get_argument( 'columns' );
    $postcode = $columns['postcode'];
    
    // validate the postcode
    if( !preg_match( '/^[A-Z][0-9][A-Z] [0-9][A-Z][0-9]$/', $postcode ) && // postal code
        !preg_match( '/^[0-9]{5}$/', $postcode ) )  // zip code
      throw new exc\notice(
        'Postal codes must be in "A1A 1A1" format, zip codes in "01234" format.', __METHOD__ );

    // no errors, go ahead and make the change
    parent::finish();
  }
}
?>
