<?php
/**
 * import.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace mastodon\database;
use cenozo\lib, cenozo\log, mastodon\util;

/**
 * import: record
 */
class import extends \cenozo\database\record
{
  /**
   * Overrides the parent method in order to read the data column.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $column_name The name of the column or table being fetched from the database
   * @return mixed
   * @access public
   */
  public function __get( $column_name )
  {
    // only override if the column is "data"
    if( 'data' != $column_name ) return parent::__get( $column_name );

    // the record does not read mediumblob types, so custom sql is needed
    if( !is_null( $this->id ) )
    { // read the data from the database
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'id', '=', $this->id );
      $this->data_value = static::db()->get_one( sprintf(
        'SELECT data FROM %s %s',
        static::get_table_name(),
        $modifier->get_sql() ) );
    }

    return $this->data_value;
  }

  /**
   * Overrides the parent method in order to write to the data column.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $column_name The name of the column
   * @param mixed $value The value to set the contents of a column to
   * @access public
   */
  public function __set( $column_name, $value )
  {
    if( 'data' != $column_name ) parent::__set( $column_name, $value );
    else
    {
      $this->data_value = $value;
      $this->data_changed = true;
    }
  }

  /**
   * Overrides the parent method in order to deal with the data column.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function save()
  {
    // first save the record as usual
    parent::save();

    if( $this->read_only )
    {
      log::warning( 'Tried to save read-only record.' );
      return;
    }

    // now save the data if it is not null
    if( $this->data_changed && !is_null( $this->id ) )
    {
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'id', '=', $this->id );
      static::db()->execute( sprintf(
        'UPDATE %s SET data = %s %s',
        static::get_table_name(),
        static::db()->format_string( $this->data_value ),
        $modifier->get_sql() ) );
    }
  }

  /**
   * Whether or not the data column has been changed.
   * @var boolean $data_changed
   * @access protected
   */
  protected $data_changed = false;

  /**
   * A temporary ivar to hold the value of the data column (if it is set).
   * @var boolean $data_value
   * @access protected
   */
  protected $data_value = NULL;
}
