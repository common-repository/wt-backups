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

class WT_Backups_Checker {

	function __construct($progress = false) {

		$this->issues = array();
		$this->progress = $progress;
        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();

	}

	public function logs($log, $status = 'INFO') {

		if ($this->progress) {
			$this->progress->log($log, $status);
		}

	}

	public function is_enabled($func) {

		$disabled = explode(',', ini_get('disable_functions'));
		$isDisabled = in_array($func, $disabled);
		if (!$isDisabled && function_exists($func)) return true;
		else return false;

	}

	public function check_free_space($size) {

		$this->logs(__('Requires at least ', 'wt-backups') . $size . __(' bytes.', 'wt-backups') . ' [' . WT_Backups_Helper::humanFilesize($size) . ']');
		if ($this->is_enabled('disk_free_space') && intval(disk_free_space(WT_BACKUPS_STORAGE)) > 100) {

			$this->logs(__('Disk free space function is not disabled - using it...', 'wt-backups'));
			$this->logs(__('Checking this path/partition: ', 'wt-backups') . WT_BACKUPS_STORAGE);
			$free = intval(disk_free_space(WT_BACKUPS_STORAGE));
			$this->logs(__('There is ', 'wt-backups') . number_format($free / 1024 / 1024, 2) . __(' MB free.', 'wt-backups') . ' [' . WT_Backups_Helper::humanFilesize($free) . ']', 'SUCCESS');
			if ($free > $size) {
				$this->logs(__('Great! We have enough space.', 'wt-backups'), 'SUCCESS');
				return true;
			} else {
				return false;
			}

		} else {

			// Log
			$this->logs(__('Disk free space function is disabled by hosting.', 'wt-backups'));
			$this->logs(__('Using dummy file to check free space (it can take some time).', 'wt-backups'));

			// TMP Filename
			$file = WT_BACKUPS_STORAGE . '/' . '.space_check';
			try {

				// 2 GB = (1024 * 1024 * 1024 * 2)
				$total = $size;

				$chunk = 1024;
				while ($size > 0) {
                    $this->wp_filesystem->put_contents($file, str_pad('', min($chunk, $size)));
					$size -= $chunk;
				}

				$fs = filesize($file);
				 wp_delete_file($file);

				if ($fs > ($total - 100)) return true;
				else return false;

			} catch (\Exception $e) {

				WT_Backups_Logger::error($e);
				if (file_exists($file)) wp_delete_file($file);

				return false;

			} catch (\Throwable $e) {

				WT_Backups_Logger::error($e);
				if (file_exists($file)) wp_delete_file($file);

				return false;

			}

		}

	}

}
