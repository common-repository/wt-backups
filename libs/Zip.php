<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
/**
 * WebTotem Zip class.
 */
class WT_Backups_Zip {
	protected $lib;
	protected $org_files;
	protected $new_file_path;
	protected $backupname;
	protected $zip_progress;

	protected $extr_file;
	protected $extr_dirc;
    protected $start_zip;
    protected $wp_filesystem;

	public function __construct() {
		$this->lib = 0;
		$this->extr_file = 0;
		$this->new_file_path = 0;
		$this->org_files = [];
        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
	}

	public function zip_start($file_path, $files = [], $name = '', &$zip_progress = null, $start = null) {

		// save the new file path
		$this->new_file_path = $file_path;
		$this->backupname = $name;
		$this->zip_progress = $zip_progress;
		$this->start_zip = $start;

		if (sizeof($files) > 0) {
			$this->org_files = $files;
		}

		// Some php installations doesn't have the ZipArchive
		// So in this case we'll use another lib called PclZip
		if (class_exists("ZipArchive") || class_exists("\ZipArchive")) {
			$this->lib = 1;
		} else {
			$this->lib = 2;
		}

		return true;

	}

	public function return_bytes($val) {
		$val = trim($val);
		$last = strtolower($val[strlen($val) - 1]);
		$val = substr($val, 0, -1);

		switch ($last) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			// no break
			case 'm':
				$val *= 1024;
			// no break
			case 'k':
				$val *= 1024;
		}

		return $val;
	}

	public function zip_failed($error) {
		WT_Backups_Logger::error(__("There was an error during backup (packing)...", 'wt-backups'));
		WT_Backups_Logger::error($error);

		if ($this->zip_progress != null) {
			$this->zip_progress->log(__("Issues during backup (packing)...", 'wt-backups'), 'ERROR');
			$this->zip_progress->log($error, 'ERROR');
		}
	}

	public function restore_failed($error) {
		WT_Backups_Logger::error(__("There was an error during restore process (extracting)...", 'wt-backups'));
		WT_Backups_Logger::error($error);

		if ($this->zip_progress != null) {
			$this->zip_progress->log(__("Issues during restore process (extracting)...", 'wt-backups'), 'ERROR');
			$this->zip_progress->log($error, 'ERROR');
		}
	}

	public function zip_add($in) {

		// Just to make sure.. if the user haven't called the earlier method
		if ($this->lib === 0 || $this->new_file_path === 0) {
			throw new \Exception("PHP-ZIP: must call zip_start before zip_add");
		}

		// Push file
		array_push($this->org_files, $in);

		// Return
		return true;
	}

	public function createDatabaseDump($dbbackupname, $better_database_files_dir, &$database_file, $cron) {
		$backupSettings = $cron ? json_decode( WT_Backups_Option::getOption( 'backup_settings' ), true ) :  WT_Backups_Option::getSessionOption( 'backup_settings' );
		$backup_database = array_key_exists('backup_database', $backupSettings) ? $backupSettings['backup_database'] : true;

		if ($backup_database) {

			$this->zip_progress->log(__("Iterating database...", 'wt-backups'), 'INFO');

			if (!is_dir($better_database_files_dir)) $this->wp_filesystem->mkdir($better_database_files_dir, 0755);
			$db_exporter = new WT_Backups_Database($better_database_files_dir, $this->zip_progress);

			$all_files = sizeof($this->org_files);
			if($all_files < 0){
				$progress_data = [
					'total_progress' => $all_files + floor($all_files/2),
					'db_progress' => floor($all_files/2),
				];
			} else{
				$progress_data = [
					'total_progress' => 100,
					'db_progress' => 100,
				];
			}


			$db_exporter->export($progress_data);
			$this->db_exporter_files = $db_exporter->files;
			$this->db_total_size = $db_exporter->total_size;
			$this->db_exporter_queries = $db_exporter->total_queries;

			$this->zip_progress->total_queries = $this->db_exporter_queries;

			$this->dbDumped = true;
			$this->zip_progress->log(__("Database backup finished", 'wt-backups'), 'SUCCESS');

		} else {

			$this->dbDumped = false;
			$this->zip_progress->log(__("Omitting database backup (due to settings)...", 'wt-backups'), 'WARN');
			$database_file = false;
			$this->db_exporter_files = [];
			$this->db_exporter_queries = 0;
			$this->db_total_size =  0;
			$this->zip_progress->total_queries = 0;

		}

	}

	public function zip_end($force_lib = false, $cron = false) {

        $max_execution_time_initial_value = @ini_get('max_execution_time');
		@ini_set('max_execution_time', '1800');

		// Try to set limit
		$this->zip_progress->log(__("Smart memory calculation...", 'wt-backups'), 'STEP');
		$this->zip_progress->log(str_replace('%s', ini_get('max_execution_time'), __("There is %s sec max execution time", 'wt-backups')), 'INFO');

		if ((intval($this->return_bytes(ini_get('memory_limit'))) / 1024 / 1024) < 384) {
            $memory_limit_initial_value = ini_get('memory_limit');
		    @ini_set('memory_limit', '384M');
		}
		if (defined('WP_MAX_MEMORY_LIMIT')) $maxwp = WP_MAX_MEMORY_LIMIT;
		else $maxwp = '1M';

		$memory_limit = (intval($this->return_bytes(ini_get('memory_limit'))) / 1024 / 1024);
		$maxwp = (intval($this->return_bytes($maxwp)) / 1024 / 1024);

		if ($maxwp > $memory_limit) $memory_limit = $maxwp;
		$this->zip_progress->log(str_replace('%s', $memory_limit, __("There is %s MBs of memory to use", 'wt-backups')), 'INFO');
		$this->zip_progress->log(str_replace('%s', $maxwp, __("WordPress memory limit: %s MBs", 'wt-backups')), 'INFO');
		$safe_limit = intval($memory_limit / 4);
		if ($safe_limit > 64) $safe_limit = 64;
		if ($memory_limit === 384) $safe_limit = 96;
		if ($memory_limit >= 512) $safe_limit = 128;
		if ($memory_limit >= 1024) $safe_limit = 256;

		// $real_memory = intval(memory_get_usage() * 0.9 / 1024 / 1024);
		// if ($real_memory < $safe_limit) $safe_limit = $real_memory;
		$safe_limit = intval($safe_limit * 0.9);

		$this->zip_progress->log(str_replace('%s', $safe_limit, __("Setting the safe limit to %s MB", 'wt-backups')), 'SUCCESS');

		$abs = WT_Backups_Helper::fixSlashes(ABSPATH) . DIRECTORY_SEPARATOR;

		$dbbackupname = 'wtb_database_backup.sql';
		$database_file = WT_Backups_Helper::fixSlashes(WT_BACKUPS_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'htaccess' . DIRECTORY_SEPARATOR . $dbbackupname);
		$database_file_dir = WT_Backups_Helper::fixSlashes((dirname($database_file))) . DIRECTORY_SEPARATOR;
		$better_database_files_dir = $database_file_dir . 'db_tables';

		// force usage of specific lib (for testing purposes)
		if ($force_lib === 2) {

			$this->lib = 2;

		} elseif ($force_lib === 1) {

			$this->lib = 1;

		}

		$this->dbDumped = false;
		$this->db_exporter_queries = 0;
		$this->zip_progress->total_queries = 0;
		$this->db_exporter_files = [];

		// just to make sure.. if the user haven't called the earlier method
		if ($this->lib === 0 || $this->new_file_path === 0) {
            @ini_set('max_execution_time', $max_execution_time_initial_value);
			throw new \Exception('PHP-ZIP: zip_start and zip_add haven\'t been called yet');
		}

		// using PclZip
		if ($this->lib === 2) {
			$max = sizeof($this->org_files);
			
			$this->zip_progress->log(__("Using PclZip module to create the backup", 'wt-backups'), 'INFO');
			$this->zip_progress->log(__("Legacy setting: Using default modules depending on user server", 'wt-backups'), 'INFO');

			// Create DB Dump
			$this->createDatabaseDump($dbbackupname, $better_database_files_dir, $database_file, $cron);

			$this->zip_progress->log(__("Making archive", 'wt-backups'), 'STEP');
			$this->zip_progress->log(__("Compressing...", 'wt-backups'), 'INFO');
			
			// require the lib
			if (!class_exists('PclZip')) {
				if (!defined('WT_BACKUPS_PCLZIP_TEMP_DIR')) {
					$wtb_tmp_dir = WT_BACKUPS_PLUGIN_PATH . '/tmp';
					if (!file_exists($wtb_tmp_dir)) {
                        $this->wp_filesystem->mkdir($wtb_tmp_dir, 0775, true);
					}
					define('WT_BACKUPS_PCLZIP_TEMP_DIR', $wtb_tmp_dir . '/wtb-');
				}

				require_once trailingslashit(ABSPATH) . 'wp-admin/includes/class-pclzip.php';
			}

			if (!$lib = new \PclZip($this->new_file_path)) {
                @ini_set('max_execution_time', $max_execution_time_initial_value);
                if(isset($memory_limit_initial_value)){
                    @ini_set('memory_limit', $memory_limit_initial_value);
                }
				throw new \Exception('PHP-ZIP: Permission Denied or zlib can\'t be found');
			}

			if ($this->dbDumped === true) {
				try {

					$this->zip_progress->log(__('Adding database SQL file(s) to the backup file.', 'wt-backups'), 'STEP');

					$files = [];

					if ($database_file !== false && !($this->db_exporter_files && sizeof($this->db_exporter_files) > 0)) {
						$files[] = $database_file;
					}

					if ($this->db_exporter_files && sizeof($this->db_exporter_files) > 0) {
						for ($i = 0; $i < sizeof($this->db_exporter_files); ++$i) {
							$files[] = $this->db_exporter_files[$i];
						}
					}

					$dbback = $lib->add($files, PCLZIP_OPT_REMOVE_PATH, $database_file_dir);

					if ($dbback == 0) {
						$this->zip_failed($lib->errorInfo(true));
                        @ini_set('max_execution_time', $max_execution_time_initial_value);
                        if(isset($memory_limit_initial_value)){
                            @ini_set('memory_limit', $memory_limit_initial_value);
                        }
						return false;
					}

				} catch (\Exception $e) {
					$this->zip_failed($e->getMessage());
                    @ini_set('max_execution_time', $max_execution_time_initial_value);
                    if(isset($memory_limit_initial_value)){
                        @ini_set('memory_limit', $memory_limit_initial_value);
                    }

					return false;
				} catch (\Throwable $e) {
					$this->zip_failed($e->getMessage());
                    @ini_set('max_execution_time', $max_execution_time_initial_value);
                    if(isset($memory_limit_initial_value)){
                        @ini_set('memory_limit', $memory_limit_initial_value);
                    }

					return false;
				}

				$this->zip_progress->log(__('Database added to the backup successfully.', 'wt-backups'), 'SUCCESS');
			}

			$this->zip_progress->log(__('Performing site files backup...', 'wt-backups'), 'STEP');

			try {
				$splitby = 200; $milestoneby = 500;
				$filestotal = sizeof($this->org_files);
				if ($filestotal < 3000) { $splitby = 250; $milestoneby = 500; }
				if ($filestotal > 5000) { $splitby = 500; $milestoneby = 500; }
				if ($filestotal > 10000) { $splitby = 1000; $milestoneby = 1000; }
				if ($filestotal > 15000) { $splitby = 2000; $milestoneby = 2000; }
				if ($filestotal > 20000) { $splitby = 4000; $milestoneby = 4000; }
				if ($filestotal > 25000) { $splitby = 6000; $milestoneby = 6000; }
				if ($filestotal > 30000) { $splitby = 8000; $milestoneby = 8000; }
				if ($filestotal > 32000) { $splitby = 10000; $milestoneby = 10000; }
				if ($filestotal > 60500) { $splitby = 20000; $milestoneby = 20000; }
				if ($filestotal > 90500) { $splitby = 40000; $milestoneby = 40000; }

				$this->zip_progress->log(__("Chunks contain ", 'wt-backups') . $splitby . __(" files.", 'wt-backups'));

				$chunks = array_chunk($this->org_files, $splitby);
				$chunkslen = count($chunks);
				if ($chunkslen > 0) {
					$sizeoflast = count($chunks[$chunkslen - 1]);
					if ($chunkslen > 1 && $sizeoflast == 1) {
						$buffer = array_slice($chunks[$chunkslen - 2], -1);
						$chunks[$chunkslen - 2] = array_slice($chunks[$chunkslen - 2], 0, -1);
						$chunks[$chunkslen - 1][] = $buffer[0];
					}
				}

				for ($i = 0; $i < $chunkslen; ++$i) {

					// Abort if user wants it (check every 100 files)
					if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
						break;
					}

					$chunk = $chunks[$i];
					$back = $lib->add($chunk, PCLZIP_OPT_REMOVE_PATH, $abs, PCLZIP_OPT_ADD_PATH, 'wordpress' . DIRECTORY_SEPARATOR, PCLZIP_OPT_TEMP_FILE_THRESHOLD, $safe_limit);
					if ($back == 0) {
						$this->zip_failed($lib->errorInfo(true));
                        @ini_set('max_execution_time', $max_execution_time_initial_value);
                        if(isset($memory_limit_initial_value)){
                            @ini_set('memory_limit', $memory_limit_initial_value);
                        }
						return false;
					}

					$cur_files = (($i * $splitby) + $splitby);
					$this->zip_progress->progress($cur_files . '/' . ($max + floor($max/3)));
					if ($cur_files % $milestoneby === 0 && $cur_files < $max) {
						$this->zip_progress->log(__("Milestone: ", 'wt-backups') . ($cur_files . '/' . $max), 'info');
					}
				}

			} catch (\Exception $e) {
				$this->zip_failed($e->getMessage());
                @ini_set('max_execution_time', $max_execution_time_initial_value);
                if(isset($memory_limit_initial_value)){
                    @ini_set('memory_limit', $memory_limit_initial_value);
                }

				return false;
			} catch (\Throwable $e) {
				$this->zip_failed($e->getMessage());
                @ini_set('max_execution_time', $max_execution_time_initial_value);
                if(isset($memory_limit_initial_value)){
                    @ini_set('memory_limit', $memory_limit_initial_value);
                }

				return false;
			}

			if (file_exists(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . '.abort')) {

				if (file_exists($database_file_dir . 'wtb_backup_manifest.json')) {
					 wp_delete_file($database_file_dir . 'wtb_backup_manifest.json');
				}
				if (file_exists($database_file_dir . 'wtb_logs_this_backup.log')) {
					 wp_delete_file($database_file_dir . 'wtb_logs_this_backup.log');
				}

			} else {

				// End
				$this->zip_progress->log(__("Milestone: ", 'wt-backups') . ($max . '/' . $max), 'info');
				$this->zip_progress->log(sprintf(__('Compressed %s files', 'wt-backups'), $max), 'SUCCESS');

				// Log time of ZIP Process
				$this->zip_progress->log(sprintf(__('Archiving of %s files took: %s s', 'wt-backups'), $max, number_format(microtime(true) - $this->start_zip, 2)), 'INFO');

				$this->zip_progress->log(__("Finalizing backup", 'wt-backups'), 'STEP');
				$this->zip_progress->log(__("Adding manifest...", 'wt-backups'), 'INFO');
				$this->zip_progress->log(__("Closing files and archives", 'wt-backups'), 'STEP');
				//$this->zip_progress->log(__( "Successful", 'wt-backups' ), 'END');

				//$this->zip_progress->end();

                $this->wp_filesystem->put_contents($database_file_dir . 'wtb_backup_manifest.json', $this->zip_progress->createManifest($cron));
                $this->wp_filesystem->put_contents($database_file_dir . 'wtb_logs_this_backup.log', $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'latest.log'));

				//$this->zip_progress->start(true);

				$files = [];

				if (file_exists($database_file_dir . 'wtb_logs_this_backup.log')) $files[] = $database_file_dir . 'wtb_logs_this_backup.log';
				if (file_exists($database_file_dir . 'wtb_backup_manifest.json')) $files[] = $database_file_dir . 'wtb_backup_manifest.json';
				else {

					$this->zip_failed('Manifest file could not be added, manifest does not exist.');
                    @ini_set('max_execution_time', $max_execution_time_initial_value);
                    if(isset($memory_limit_initial_value)){
                        @ini_set('memory_limit', $memory_limit_initial_value);
                    }
					return false;
				}

				try {

					$maback = $lib->add($files, PCLZIP_OPT_REMOVE_PATH, $database_file_dir);

					if ($maback == 0) {
						$this->zip_failed($lib->errorInfo(true));
                        @ini_set('max_execution_time', $max_execution_time_initial_value);
                        if(isset($memory_limit_initial_value)){
                            @ini_set('memory_limit', $memory_limit_initial_value);
                        }
						return false;
					}

				} catch (\Exception $e) {
					$this->zip_failed($e->getMessage());
                    @ini_set('max_execution_time', $max_execution_time_initial_value);
                    if(isset($memory_limit_initial_value)){
                        @ini_set('memory_limit', $memory_limit_initial_value);
                    }

					return false;
				} catch (\Throwable $e) {
					$this->zip_failed($e->getMessage());
                    @ini_set('max_execution_time', $max_execution_time_initial_value);
                    if(isset($memory_limit_initial_value)){
                        @ini_set('memory_limit', $memory_limit_initial_value);
                    }

					return false;
				}

				if (file_exists($database_file_dir . 'wtb_backup_manifest.json')) {
					 wp_delete_file($database_file_dir . 'wtb_backup_manifest.json');
				}
				if (file_exists($database_file_dir . 'wtb_logs_this_backup.log')) {
					 wp_delete_file($database_file_dir . 'wtb_logs_this_backup.log');
				}

				$this->zip_progress->progress(100 . '/' . 100);

			}
		}

		// Remove DB SQL Files
		if ($this->db_exporter_files && sizeof($this->db_exporter_files) > 0) {
			for ($i = 0; $i < sizeof($this->db_exporter_files); ++$i) {
				if (file_exists($this->db_exporter_files[$i]))  wp_delete_file($this->db_exporter_files[$i]);
			}
            $this->wp_filesystem->rmdir($better_database_files_dir);
		}

		if ($database_file && file_exists($database_file))  wp_delete_file($database_file);
		if (!file_exists($this->new_file_path)) {
            @ini_set('max_execution_time', $max_execution_time_initial_value);
			throw new \Exception('PHP-ZIP: After doing the zipping file can not be found');
		}
		if (filesize($this->new_file_path) === 0) {
            @ini_set('max_execution_time', $max_execution_time_initial_value);
			throw new \Exception('PHP-ZIP: After doing the zipping file size is still 0 bytes');
		}

		// empty the array
		$this->org_files = [];
        @ini_set('max_execution_time', $max_execution_time_initial_value);
        if(isset($memory_limit_initial_value)){
            @ini_set('memory_limit', $memory_limit_initial_value);
        }

		return true;
	}

	public function zip_files($files, $to) {
		$this->zip_start($to);
		$this->zip_add($files);

		return $this->zip_end();
	}

	public function unzip_file($file_path, $target_dir = null, &$zip_progress = null) {

		// Progress
		$this->zip_progress = $zip_progress;

		// if it doesn't exist
		if (!file_exists($file_path)) {
			throw new \Exception("PHP-ZIP: File doesn't Exist");
		}

		$this->extr_file = $file_path;

		$this->lib = 2;

		if ($target_dir !== null) {
			return $this->unzip_to($target_dir);
		} else {
			return true;
		}
	}

	public function extract_files($zip_path, $files, $target_dir = null, &$zip_progress = null, $isFirstExtract = true) {

		$this->zip_progress = $zip_progress;

		// it exists, but it's not a directory
		if (file_exists($target_dir) && (!is_dir($target_dir))) {
			throw new \Exception("PHP-ZIPv2: Target directory exists as a file not a directory");
		}
		// it doesn't exist
		if (!file_exists($target_dir)) {
			if (!$this->wp_filesystem->mkdir($target_dir)) {
				throw new \Exception("PHP-ZIPv2: Directory not found, and unable to create it");
			}
		}
		// validations -- end //
		if (class_exists("ZipArchive") || class_exists("\ZipArchive")) {

			$zip = new \ZipArchive;
			$res = $zip->open($zip_path);

			if ($res === true) {

				if ($isFirstExtract) {
					$this->zip_progress->log(__("Using ZipArchive, omiting memory limit calculations...", 'wt-backups'), 'INFO');
				}

				$zip->extractTo($target_dir, $files);
				$zip->close();
				return true;

			} else {

				$this->restore_failed('PHP-ZIPv2: Could not open Backup with ZipArchive.');
				return false;

			}

		} else {

			if ($isFirstExtract) {
				$this->zip_progress->log(__("ZipArchive is not available, using PclZIP.", 'wt-backups'), 'INFO');
			}

			$safe_limit = $this->smartMemory($isFirstExtract);
			$this->loadPclZip($isFirstExtract);
			$lib = new \PclZip($zip_path);
			$restor = $lib->extract(PCLZIP_OPT_BY_NAME, $files, PCLZIP_OPT_PATH, $target_dir, PCLZIP_OPT_TEMP_FILE_THRESHOLD, $safe_limit);

			if ($restor == 0) {

				$this->restore_failed($lib->errorInfo(true));
				return false;

			}

			return true;

		}

	}

	public function loadPclZip($isFirstExtract = true) {

		if (!class_exists('PclZip')) {
			if (!defined('WT_BACKUPS_PCLZIP_TEMP_DIR')) {
				$wtb_tmp_dir = WT_BACKUPS_STORAGE . '/tmp';
				if (!file_exists($wtb_tmp_dir)) {
                    $this->wp_filesystem->mkdir($wtb_tmp_dir, 0775);
				}
				define('WT_BACKUPS_PCLZIP_TEMP_DIR', $wtb_tmp_dir . '/wtb-');
			}

			require_once trailingslashit(ABSPATH) . 'wp-admin/includes/class-pclzip.php';
		}

	}

	public function smartMemory($isFirstExtract = true) {

		// Smart memory -- start //
		if ($this->zip_progress != null && $isFirstExtract) {
			$this->zip_progress->log(__("Smart memory calculation...", 'wt-backups'), 'STEP');
		}

		if ((intval($this->return_bytes(ini_get('memory_limit'))) / 1024 / 1024) < 384) {
			@ini_set('memory_limit', '384M');
		}

		$memory_limit = (intval($this->return_bytes(ini_get('memory_limit'))) / 1024 / 1024);
		if ($this->zip_progress != null && $isFirstExtract) {
			$this->zip_progress->log(str_replace('%s', $memory_limit, __("There is %s MBs of memory to use", 'wt-backups')), 'INFO');
		}

		$safe_limit = intval($memory_limit / 4);
		if ($safe_limit > 64) $safe_limit = 64;
		if ($memory_limit === 384) $safe_limit = 78;
		if ($memory_limit >= 512) $safe_limit = 104;
		if ($memory_limit >= 1024) $safe_limit = 228;
		if ($memory_limit >= 2048) $safe_limit = 428;

		// $real_memory = intval(memory_get_usage() * 0.9 / 1024 / 1024);
		// if ($real_memory < $safe_limit) $safe_limit = $real_memory;
		$safe_limit = intval($safe_limit * 0.8);

		if ($this->zip_progress != null && $isFirstExtract) {
			$this->zip_progress->log(str_replace('%s', $safe_limit, __("Setting the safe limit to %s MB", 'wt-backups')), 'SUCCESS');
		}
		// Smart memory -- end //

		return $safe_limit;

	}

	public function unzip_to($target_dir) {

		// validations -- start //
		if ($this->lib === 0 && $this->extr_file === 0) {
			throw new \Exception("PHP-ZIP: unzip_file hasn't been called");
		}
		// it exists, but it's not a directory
		if (file_exists($target_dir) && (!is_dir($target_dir))) {
			throw new \Exception("PHP-ZIP: Target directory exists as a file not a directory");
		}
		// it doesn't exist
		if (!file_exists($target_dir)) {
			if (!$this->wp_filesystem->mkdir($target_dir)) {
				throw new \Exception("PHP-ZIP: Directory not found, and unable to create it");
			}
		}
		// validations -- end //

		// Target Directory
		$this->extr_dirc = $target_dir;

		// Smart Memory
		$safe_limit = $this->smartMemory();

		// Extract msg
		$this->zip_progress->log(__('Extracting files into temporary directory (this process can take some time)...', 'wt-backups'), 'STEP');

		// Force PCL Zip
		$this->lib = 2;

		// extarct using PclZip
		if ($this->lib === 2) {
			$this->loadPclZip();
			$lib = new \PclZip($this->extr_file);
			$restor = $lib->extract(PCLZIP_OPT_PATH, $this->extr_dirc, PCLZIP_OPT_TEMP_FILE_THRESHOLD, $safe_limit);
			if ($restor == 0) {
				$this->restore_failed($lib->errorInfo(true));

				return false;
			}
		}

		return true;
	}

	private function dir_to_assoc_arr(DirectoryIterator $dir) {
		$data = [];
		foreach ($dir as $node) {
			if ($node->isDir() && !$node->isDot()) {
				$data[$node->getFilename()] = $this->dir_to_assoc_arr(new DirectoryIterator($node->getPathname()));
			} elseif ($node->isFile()) {
				$data[] = $node->getFilename();
			}
		}

		return $data;
	}

	private function path() {
		return join(DIRECTORY_SEPARATOR, func_get_args());
	}

	private function commonPath($files, $remove = true) {
		foreach ($files as $index => $filesStr) {
			$files[$index] = explode(DIRECTORY_SEPARATOR, $filesStr);
		}
		$toDiff = $files;
		foreach ($toDiff as $arr_i => $arr) {
			foreach ($arr as $name_i => $name) {
				$toDiff[$arr_i][$name_i] = $name . "___" . $name_i;
			}
		}
		$diff = call_user_func_array("array_diff", $toDiff);
		reset($diff);
		$i = key($diff) - 1;
		if ($remove) {
			foreach ($files as $index => $arr) {
				$files[$index] = implode(DIRECTORY_SEPARATOR, array_slice($files[$index], $i));
			}
		} else {
			foreach ($files as $index => $arr) {
				$files[$index] = implode(DIRECTORY_SEPARATOR, array_slice($files[$index], 0, $i));
			}
		}

		return $files;
	}
}
