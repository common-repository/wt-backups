<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * WebTotem backups
 *
 * @copyright  2021 WebTotem
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: WebTotem backups
 * Plugin URI:  https://wordpress.org/plugins/wt-backups
 * Description: The <a href="https://wtotem.com/" target="_blank">WebTotem backups</a> Site backup plugin.
 * Author URI: https://wtotem.com/
 * Author: WebTotem Team
 * Text Domain: wt-backups
 * Domain Path: /lang
 * Version: 1.0.0
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

/**
 * Main file to control the plugin.
 */
define( 'WT_BACKUPS_INIT', true );

/**
 * Plugin dependencies.
 *
 * list of required WordPress functions for the plugin to work.
 */
$wt_backups_dependencies = array(
	'wp',
	'wp_die',
	'add_action',
	'remove_action',
);

// Stopping execution if dependencies are not met.
foreach ( $wt_backups_dependencies as $dependency ) {
	if ( ! function_exists( $dependency ) ) {
		// Report invalid access.
		header( 'HTTP/1.1 403 Forbidden' );
		die( "Protected By WebTotem! Dependencies are not met" );
	}
}

// Stopping execution if the ABSPATH constant is not available
if ( ! defined( 'ABSPATH' ) ) {
	// Report invalid access.
	header( 'HTTP/1.1 403 Forbidden' );
	die( "Protected By WebTotem! ABSPATH constant is not available" );
}

/**
 * Current version of the plugin's code.
 */
define( 'WT_BACKUPS_VERSION', '1.0.0' );

/**
 * The name of the folder where the plugin's files will be located.
 */
define( "WT_BACKUPS_PLUGIN_FOLDER", basename( dirname( __FILE__ ) ) );

/**
 * The fullpath where the plugin's files will be located.
 */
define( 'WT_BACKUPS_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . WT_BACKUPS_PLUGIN_FOLDER );

/**
 * The local URL where the plugin's files and assets are served.
 */
define( 'WT_BACKUPS_URL', rtrim( plugin_dir_url( __FILE__ ), '/' ) );

/**
 * The domain name of the current site, without protocol and www.
 */
define("WT_BACKUPS_SITE_DOMAIN", str_replace(['http://', 'https://', '//', '://', 'www.'], '', get_site_url()));

/* Load plugin translations */
function wt_backups_load_plugin_textdomain() {
    load_plugin_textdomain('wt-backups', false, basename(dirname(__FILE__)) . '/lang/');
}
add_action('plugins_loaded', 'wt_backups_load_plugin_textdomain');


/**
 * DEBUG.
 */
define( 'WT_BACKUPS_DEBUG', true );

/**
 * Unique name of the plugin through out all the code.
 */
define( "WT_BACKUPS", 'wt_backups' );

/* Load all classes before anything else. */
require_once 'libs/Ajax.php';
require_once 'libs/Checker.php';
require_once 'libs/DB.php';
require_once 'libs/Helper.php';
require_once 'libs/API.php';
require_once 'libs/Interface.php';
require_once 'libs/Logger.php';
require_once 'libs/Option.php';
require_once 'libs/Progress.php';
require_once 'libs/Scanner.php';
require_once 'libs/Template.php';
require_once 'libs/DB_exporter.php';
require_once 'libs/DB_importer.php';
require_once 'libs/Unzip.php';
require_once 'libs/Zip.php';
require_once 'libs/Zipper.php';
require_once 'libs/FTP.php';
require_once 'libs/GoogleDriveApi.php';
require_once 'libs/DropboxApi.php';

/* Load page and ajax handlers */
require_once 'entry/PageHandler.php';

/* Load common variables and triggers */
require_once 'entry/Common.php';

/**
 * Uninstalled the plugin
 *
 * @return void
 */
function wt_backups_uninstall() {
	/* Delete settings from the database */
	WT_Backups_DB::uninstall();
}

register_uninstall_hook( __FILE__, 'wt_backups_uninstall' );

/**
 * Deactivation plugin
 *
 * @return void
 */
function wt_backups_activation() {
	WT_Backups_DB::install();
}

register_activation_hook( __FILE__, 'wt_backups_activation' );
