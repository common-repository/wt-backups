<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		/* Report invalid access if possible. */
		header('HTTP/1.1 403 Forbidden');
	}
	exit("Protected By WebTotem! Not plugin init");
}

/**
 * WebTotem Database class for Wordpress.
 */
class WT_Backups_DB {

    const WT_BACKUPS_TABLE_SETTINGS = 'wt_backups_settings';

	/**
	 * Creating a database with plugin settings.
	 */
	public static function install () {
		global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $settings_table = self::add_prefix(self::WT_BACKUPS_TABLE_SETTINGS);

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $settings_table));

        if ($table_exists != $settings_table) {

            $sql = "CREATE TABLE " . $settings_table . " (
              id bigint NOT NULL AUTO_INCREMENT,
              name tinytext NOT NULL,
              value longtext,
              UNIQUE KEY id (id)
            );";

            dbDelta($sql);
        }

		return true;
	}

	/**
	 * Add (or update) data to the table.
	 */
	public static function setData ($options, $table, $where = false) {
		global $wpdb;
        $table_name = self::getTable($table);

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

        if ($table_exists == $table_name) {
          if($where && $current = self::getData($where, $table)){
            $options['id'] = $current['id'];
          }

          $wpdb->replace( $table_name, $options );
        }
	}

	/**
	 * Delete data from the table.
	 */
	public static function deleteData ($params, $table) {
		global $wpdb;

    $table_name = self::getTable($table);
    if($params){
      $wpdb->delete( $table_name, $params );
    }
	}

	/**
	 * Getting values from the table.
	 *
	 * @param string $option
	 *    Option name.
	 *
	 * @return array
	 */
	public static function getData ($option, $table) {
		global $wpdb;
		$table_name = self::getTable($table);

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

        if ($table_exists == $table_name) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE name = %s", $table_name, $option ));
            return (array) $row;
        }

		return [];
	}


	/**
	 * Deleting wtotem tables.
	 */
	public static function uninstall() {
        $tables = [
            self::WT_BACKUPS_TABLE_SETTINGS
        ];
        foreach ($tables as $table) {
            global $wpdb;
            $table = self::add_prefix($table);
            $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table ));
        }
	}

	/**
	 * Returns the table with the site prefix added.
	 *
	 * @param string $table
	 *    Table name.
	 * @return string
	 */
	public static function add_prefix($table) {
		global $wpdb;
		return $wpdb->base_prefix . $table;
	}

    /**
     * Get table name.
     */
    private static function getTable($name) {
        if ($name == 'settings') {
            return self::add_prefix(self::WT_BACKUPS_TABLE_SETTINGS);
        }

        throw new \OutOfBoundsException('Unknown key: ' . $name);
    }

}