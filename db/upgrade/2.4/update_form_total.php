#!/usr/bin/php
<?php
/**
 * This is a special script used to redefine the update_*_form_total procedures
 * 
 * This script couldn't be done in an SQL script because the procedures make reference to tables
 * in the cenozo database.  Since there is no way to make dynamic SQL inside of a procedure it
 * has to be done in an external script
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

ini_set( 'display_errors', '1' );
error_reporting( E_ALL | E_STRICT );
ini_set( 'date.timezone', 'US/Eastern' );

// utility functions
function out( $msg ) { printf( '%s: %s'."\n", date( 'Y-m-d H:i:s' ), $msg ); }
function error( $msg ) { out( sprintf( 'ERROR! %s', $msg ) ); }


class patch
{
  /**
   * Reads the framework and application settings
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function read_settings()
  {
    // include the initialization settings
    global $SETTINGS;
    require_once '../../../settings.ini.php';
    require_once '../../../settings.local.ini.php';
    require_once $SETTINGS['path']['CENOZO'].'/src/initial.class.php';
    $initial = new \cenozo\initial();
    $this->settings = $initial->get_settings();
  }

  public function connect_database()
  {
    $server = $this->settings['db']['server'];
    $username = $this->settings['db']['username'];
    $password = $this->settings['db']['password'];
    $name = $this->settings['db']['database_prefix'] . $this->settings['general']['instance_name'];
    $this->db = new \mysqli( $server, $username, $password, $name );
    if( $this->db->connect_error )
    {
      error( $this->db->connect_error );
      die();
    }
  }

  /**
   * Executes the patch
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function execute()
  {
    out( 'Reading configuration parameters' );
    $this->read_settings();

    out( 'Connecting to database' );
    $this->connect_database();

    $cenozo = sprintf( '%s%s', $this->settings['db']['database_prefix'], $this->settings['general']['framework_name'] );
    foreach( array( 'consent', 'extended_hin', 'general_proxy', 'hin', 'proxy' ) as $type )
    {
      out( sprintf( 'Updating update_%s_form_total procedure', $type ) );

      $result = $this->db->query( sprintf( 'DROP procedure IF EXISTS update_%s_form_total', $type ) );

      if( false === $result )
      {
        error( $this->db->error );
        die();
      }

      $sql = sprintf( 
        "CREATE PROCEDURE update_%s_form_total(IN proc_%s_form_id INT(10) UNSIGNED)\n".
        "BEGIN\n".
        "\n".
        "  REPLACE INTO %s_form_total\n".
        "  SET %s_form_id = proc_%s_form_id,\n".
        "      entry_total = (\n".
        "        SELECT COUNT(*) FROM %s_form_entry\n".
        "        WHERE %s_form_id = proc_%s_form_id\n".
        "      ),\n".
        "      submitted_total = (\n".
        "        SELECT COUNT(*) FROM %s_form_entry\n".
        "        WHERE %s_form_id = proc_%s_form_id\n".
        "        AND submitted = true\n".
        "      ),\n".
        "      uid = (\n".
        "        SELECT GROUP_CONCAT( DISTINCT uid ORDER BY uid SEPARATOR ',' )\n".
        "        FROM %s_form_entry\n".
        "        WHERE %s_form_id = proc_%s_form_id\n".
        "        GROUP BY %s_form_id\n".
        "      ),\n".
        "      cohort = (\n".
        "        SELECT GROUP_CONCAT( DISTINCT cohort.name ORDER BY cohort.name SEPARATOR ',' )\n".
        "        FROM %s_form_entry\n".
        "        LEFT JOIN %s.participant ON %s_form_entry.uid = participant.uid\n".
        "        LEFT JOIN %s.cohort ON participant.cohort_id = cohort.id\n".
        "        WHERE %s_form_id = proc_%s_form_id\n".
        "        GROUP BY %s_form_id\n".
        "      );\n".
        "END",
        $type, $type,
        $type, $type, $type,
        $type, $type, $type,
        $type, $type, $type,
        $type, $type, $type, $type,
        $type, $cenozo, $type, $cenozo, $type, $type, $type
      );

      $result = $this->db->query( $sql );

      if( false === $result )
      {
        error( $this->db->error );
        die();
      }
    }

    out( 'Done' );
  }

  /**
   * Contains all initialization parameters.
   * @var array
   * @access private
   */
  private $settings = array();
}

$patch = new patch();
$patch->execute();
