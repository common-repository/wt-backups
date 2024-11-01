<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}

/**
 * WebTotem Checker class.
 */

class WT_Backups_Logger {

	public static function append($type, $log) {

        $wp_filesystem = WT_Backups_Helper::wpFileSystem();
        $file = WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'complete_logs.log';
        $date = '[' . gmdate('Y-m-d H:i:s') . '] ';

        $content = $wp_filesystem->get_contents($file);
        $log_string = $date . $type . ' ' . $log . "\n";
        $wp_filesystem->put_contents($file, $content . $log_string);
	}

	public static function log($log) {
		self::append('[LOG]', $log);
	}

	public static function error($log) {
		self::append('[ERROR]', $log);
	}

	public static function debug($log) {
		if (WT_BACKUPS_DEBUG === TRUE) {
			self::append('[DEBUG]', $log);
		}
	}

}
