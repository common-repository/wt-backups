<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}

/**
 * WebTotem Zipper class.
 */
class WT_Backups_Zipper {

    private $wp_filesystem;

    public function __construct() {
        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
    }

	public function makeZIP($files, $output, $name, &$zip_progress, $cron = false) {

		// Verbose
		WT_Backups_Logger::log(__("Creating backup ", 'wt-backups'));
		WT_Backups_Logger::log(__("Found ", 'wt-backups') . sizeof($files) . __(" files to backup.", 'wt-backups'));


		// Start microtime for ZIP Process
		$start = microtime(true);

		// Logs
		$zip_progress->log(__("Preparing map of files...", 'wt-backups'), 'step');

		// Try to catch error
		try {

			// Create new ZIP
			$zip = new WT_Backups_Zip();
			$zip->zip_start($output, $files, $name, $zip_progress, $start);

			// Logs
			$zip_progress->log(__("Files prepared.", 'wt-backups'), 'success');
			$zip_progress->log(__("Starting compression process...", 'wt-backups'), 'info');

			// Close ZIP and Save
			$result = $zip->zip_end(2, $cron);
			if (!$result) {
				$zip_progress->log(__("Something went wrong (pclzip) – removing backup files...", 'wt-backups'), 'error');

				return false;
			}

			return true;
		} catch (\Throwable $e) {

			// Error print
			$zip_progress->log(__("Reverting backup, removing file...", 'wt-backups'), 'step');
			$zip_progress->log(__("There was an error during backup...", 'wt-backups'), 'error');
			$zip_progress->log($e->getMessage(), 'error');

			return false;
		} catch (\Exception $e) {

			// Error print
			$zip_progress->log(__("Reverting backup, removing file...", 'wt-backups'), 'step');
			$zip_progress->log(__("There was an error during backup...", 'wt-backups'), 'error');
			$zip_progress->log($e->getMessage(), 'error');

			return false;
		}

	}

	public function getZipFileContent($zipname, $filename) {
		if (class_exists('ZipArchive') || class_exists('\ZipArchive')) {
			$zip = new \ZipArchive();

			if ($zip->open($zipname) === true) {
				if ($content = $zip->getFromName($filename)) {
					return json_decode($content);
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			if (!class_exists('PclZip')) {
				require_once trailingslashit(ABSPATH) . 'wp-admin/includes/class-pclzip.php';
			}
			$lib = new \PclZip($zipname);
			$content = $lib->extract(PCLZIP_OPT_BY_NAME, $filename, PCLZIP_OPT_EXTRACT_AS_STRING);
			if (isset($content[0]) && isset($content[0]['content'])) {
				return json_decode($content[0]['content']);
			} else {
				return false;
			}
		}
	}

	public function getZipContentList($zippath, $savepath) {

		if (class_exists('ZipArchive') || class_exists('\ZipArchive')) {

			$zip = new \ZipArchive();
			$zip->open($zippath);

			if (!isset($zip->numFiles) || $zip->numFiles == 0 || $zip->numFiles === false) {
				$zip->close();
				return false;
			}

            $content = $this->wp_filesystem->get_contents($savepath);
			$totalAmount = $zip->numFiles;

			for ($i = 0; $i < $zip->numFiles; ++$i) {

				$stat = $zip->statIndex($i);
                $content .= $stat['name'] . "\n";
                unset($stat);

			}

            $this->wp_filesystem->put_contents($savepath, $content);
			$zip->close();

			return $totalAmount;

		} else {

			if (!class_exists('PclZip')) {
				require_once trailingslashit(ABSPATH) . 'wp-admin/includes/class-pclzip.php';
			}

			$zip = new \PclZip($zippath);
			$list = $zip->listContent();
			if ($list == 0) {
				return false;
			}

            $content = $this->wp_filesystem->get_contents($savepath);

			$totalAmount = sizeof($list);
			for ($i = 0; $i < $totalAmount; ++$i) {
                $content .= $list[$i]['filename'] . "\n";
			}

            $this->wp_filesystem->put_contents($savepath, $content);

			return $totalAmount;

		}

	}

	public function getZipFileContentPlain($zipname, $filename) {
		if (class_exists('ZipArchive')) {
			$zip = new \ZipArchive();

			if ($zip->open($zipname) === true) {
				if ($content = $zip->getFromName($filename)) {
					return $content;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			if (!class_exists('PclZip')) {
				require_once trailingslashit(ABSPATH) . 'wp-admin/includes/class-pclzip.php';
			}
			$lib = new \PclZip($zipname);

			$content = $lib->extract(PCLZIP_OPT_BY_NAME, $filename, PCLZIP_OPT_EXTRACT_AS_STRING);
			if (sizeof($content) > 0) {
				return $content[0]['content'];
			} else {
				return false;
			}
		}
	}

	public function lock_zip($zippath, $unlock = false) {
		try {

			// Path to lock file
			$filename = '.lock';

			// Load lib
			if (!class_exists('PclZip')) {
				require_once trailingslashit(ABSPATH) . 'wp-admin/includes/class-pclzip.php';
			}
			$lib = new \PclZip($zippath);

			// Unlocking case
			if ($unlock) {
				if ($this->is_locked_zip($zippath)) {
					$lib->delete(PCLZIP_OPT_BY_NAME, $filename);
				} else {
					return true;
				}
			} else {
				if (!$this->is_locked_zip($zippath)) {

					// Locking case
					$content = wp_json_encode(['locked' => 'true']);
					$lib->add([[PCLZIP_ATT_FILE_NAME => $filename, PCLZIP_ATT_FILE_CONTENT => $content]]);
				}
			}

			return true;
		} catch (\Exception $e) {
			WT_Backups_Logger::error($e);

			return false;
		} catch (\Throwable $e) {
			WT_Backups_Logger::error($e);

			return false;
		}
	}

	public function is_locked_zip($zippath) {
		$lock = $this->getZipFileContent($zippath, '.lock');
		if ($lock) {
			if ($lock->locked == 'true') {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}
