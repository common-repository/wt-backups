<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

/**
 * WebTotem Ajax class.
 */
class WT_Backups_Ajax
{
    /**
     * @var WT_Backups_RestoreProgress
     */
    private $restore_progress;
    private $zip_progress;
    private $wp_filesystem;

    public function __construct()
    {
        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
    }

    /**
     * Activation plugin.
     *
     * @return void
     */
    public static function activation()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_activation_nonce' );

        if ($api_key = sanitize_text_field($_POST['api_key'])) {

            $result = WT_Backups_API::auth($api_key);

            if ($result == 'success') {
                $link = WT_Backups_Helper::adminURL('admin.php?page=wt_backups');
                wp_send_json([
                    'link' => $link,
                    'success' => true,
                ], 200);
            } else {
                $notifications = self::notifications();
                wp_send_json([
                    'notifications' => $notifications['notifications'],
                    'notifications_row' => $notifications['notifications_row'],
                    'success' => false,
                ], 200);
            }
        }

    }

    /**
     * Preliminary checks backup.
     *
     * @return void|array
     */
    public function check_folder_path()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_check_folder_path_nonce' );

        $folder_path = sanitize_text_field($_POST['folder_path']);
        if (is_dir($folder_path)) {
            if ($this->wp_filesystem->is_writable($folder_path)) {
                $result = 'The check was successful. The directory exists and is writable.';
            } else {
                $result = 'The directory is not writable.';
            }
        } else {
            $result = 'The directory does not exist.';
        }

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'result' => $result,
        ], 200);
    }

    /**
     * Preliminary checks backup.
     *
     * @return void|array
     */
    public function backup_checking()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_backup_checking_nonce' );

        global $wp_version;

        $cron = sanitize_text_field($_POST['schedule']);
        $backupSettings = $cron ? json_decode(WT_Backups_Option::getOption('backup_settings'), true) : WT_Backups_Option::getSessionOption('backup_settings');


        $backup_name_info = '';
        if (!$cron and $backupSettings['backup_name'] and WT_Backups_Helper::check_zip_exist($backupSettings['backup_name'])) {
            $backupSettings['backup_name'] = str_replace('.zip', '', $backupSettings['backup_name']) . '_' . time();

            $backup_name_info = '<span style="color:#b98000">(The file name has been changed because the file already exists)</span>';
            WT_Backups_Option::setSessionOptions(['backup_settings' => $backupSettings]);
        }

        if (!array_key_exists('folder_path', $backupSettings)) {
            $backupSettings['folder_path'] = WT_BACKUPS_STORAGE;
        }

        $hosting_info = '';
        $db_info = '';

        // Progress & Logs
        $progress = new WT_Backups_CheckProgress();

        $progress->log(__("Initializing checks...", 'wt-backups'), 'step');

        // Logs
        $progress->log((__("WT backups version: ", 'wt-backups') . WT_BACKUPS_VERSION), 'info');
        $progress->log(__("Site which will be backed up: ", 'wt-backups') . site_url(), 'info');
        $progress->log(__("PHP Version: ", 'wt-backups') . PHP_VERSION, 'info');
        $progress->log(__("WP Version: ", 'wt-backups') . $wp_version, 'info');
        $progress->log(__("MySQL Version: ", 'wt-backups') . $GLOBALS['wpdb']->db_version(), 'info');
        $progress->log(__("MySQL Max Length: ", 'wt-backups') . $GLOBALS['wpdb']->get_results("SHOW VARIABLES LIKE 'max_allowed_packet';")[0]->Value, 'info');

        $hosting_info .= __("WT backups version: ", 'wt-backups') . WT_BACKUPS_VERSION . '<br>';
        $hosting_info .= __("Site which will be backed up: ", 'wt-backups') . site_url() . '<br>';
        $hosting_info .= __("PHP Version: ", 'wt-backups') . PHP_VERSION . '<br>';
        $hosting_info .= __("WP Version: ", 'wt-backups') . $wp_version . '<br>';

        $db_info .= __("MySQL Version: ", 'wt-backups') . $GLOBALS['wpdb']->db_version() . '<br>';;
        $db_info .= __("MySQL Max Length: ", 'wt-backups') . $GLOBALS['wpdb']->get_results("SHOW VARIABLES LIKE 'max_allowed_packet';")[0]->Value . '<br>';;

        if (isset($_SERVER['SERVER_SOFTWARE']) && !empty(sanitize_text_field($_SERVER['SERVER_SOFTWARE']))) {
            $server_software = sanitize_text_field($_SERVER['SERVER_SOFTWARE']);
            $progress->log(__("Web server: ", 'wt-backups') . wp_kses($server_software, 'post '), 'info');
            $hosting_info .= __("Web server: ", 'wt-backups') . wp_kses($server_software, 'post ') . '<br>';
        } else {
            $progress->log(__("Web server: Not available", 'wt-backups'), 'info');
        }

        // Error handler
        $progress->log(__("Initializing custom error handler", 'wt-backups'), 'info');
        $this->zip_progress = &$progress;
        $this->backupErrorHandler();
        $this->backupExceptionHandler();

        $progress->progress(20);

        // Checker
        $checker = new WT_Backups_Checker($progress);
        $progress->log(__("Checking if backup dir is writable...", 'wt-backups'), 'info');

        if (!$this->wp_filesystem->is_writable(dirname($backupSettings['folder_path']))) {
            $progress->log(__("Backup directory is not writable...", 'wt-backups'), 'error');
            $progress->log(__("Path: ", 'wt-backups') . $backupSettings['folder_path'], 'error');

            $progress->log('Fail', 'END');
            $directory = ['status' => 'error', 'message' => __("Backup directory is not writable...", 'wt-backups')];

        } else {
            $progress->log(__("Yup it is writable...", 'wt-backups'), 'success');
        }

        $progress->progress(30);

        // Get file names (huge list mostly)

        if (!$backupSettings['db_only']) {
            $progress->log(__("Scanning files...", 'wt-backups'), 'step');
            $files = $this->scanFilesForBackup($progress, $cron);
            $this->parseFilesForBackup($files, $progress);
        } else {
            $progress->log(__("Omitting files (due to settings)...", 'wt-backups'), 'warn');
        }

        $progress->progress(70);

        // If only database backup
        if (!isset($this->total_size_for_backup)) {
            $this->total_size_for_backup = 0;
        }
        if (!isset($this->total_size_for_backup_in_mb)) {
            $this->total_size_for_backup_in_mb = 0;
        }

        // Check if there is enough space
        $bytes = intval($this->total_size_for_backup * 1.2);
        $progress->log(__("Checking free space, reserving...", 'wt-backups'), 'step');
        if ($this->total_size_for_backup_in_mb >= 2000) {

            // Abort backup
            $progress->log(str_replace('%s', 2, __("Site weights more than %s GB.", 'wt-backups')), 'error');

            $hosting_info .= str_replace('%s', 2, __("Site weights more than %s GB.", 'wt-backups')) . '<br>';

            $progress->log('Fail', 'END');
            $backup_weights = ['status' => 'error', 'message' => str_replace('%s', 2, __("Site weights more than %s GB.", 'wt-backups'))];

        }

        $progress->progress(80);

        if (!$checker->check_free_space($bytes)) {

            $progress->log(sprintf(__('There is no space for that backup, checked: %s bytes', 'wt-backups'), $bytes), 'error');

            // Close backup
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }

            // Log and close log
            $progress->log('Fail', 'END');

            $free_space = ['status' => 'error', 'message' => __('There is no space for that backup', 'wt-backups')];

            $hosting_info .= __('There is no space for that backup', 'wt-backups') . '<br>';


        } else {
            $progress->log(__("Confirmed, there is more than enough space, checked: ", 'wt-backups') . ($bytes) . __(" bytes", 'wt-backups'), 'success');

            $hosting_info .= __("Confirmed, there is more than enough space, checked: ", 'wt-backups') . ($bytes) . __(" bytes", 'wt-backups') . '<br>';
            $progress->bytes = $this->total_size_for_backup;
        }

        $progress->progress(90);

        $storages = json_decode(WT_Backups_Option::getOption('storages'), true);
        $storages_status = 'good';
        $storages_info['list'] = '';
        $i = 1;

        if ($storages) {
            foreach ($backupSettings['storages'] as $storage) {
                $_storage = $storages[$storage];
                if ($_storage['type'] == 'ftp/sftp') {
                    $class = 'text-green-800';
                    $result = self::check_ftp_connection($_storage['params']);
                    if ($result['status'] == 'error') {
                        $storages_status = 'error';
                        $progress->log($result['message'], 'warning');
                        $class = 'text-red-800';
                    }

                    $storages_info['list'] .= $i . '. ' . $_storage['type'] . ', ' . $_storage['dest'] . ', <span class="' . $class . '">' . $result['message'] . '</span><br>';
                } elseif ($_storage['type'] == 'cloud') {

                    $massage = '';
                    if ($backupSettings['backup_name']) {
                        if ($_storage['params']['storage'] == 'dropbox') {
                            $dropbox_auth = new WT_Backups_Dropbox($_storage['params']['data'], $_storage['params']['timestamp'], $storage);
                            $list = $dropbox_auth->getFileList();
                            foreach ($list as $item) {
                                if ($backupSettings['backup_name'] . '.zip' == $item) {
                                    $storages_status = 'warning';
                                    $massage = '<span style="color:#b98000">' . __("Such a file already exists in the repository.", 'wt-backups') . '</span>';
                                }
                            }
                        }
                        if ($_storage['params']['storage'] == 'google_drive') {
                            $GoogleDriveApi = new WT_Backups_GoogleDriveApi($_storage['params']['access_token'], $storage);

                            $progress->log('Checking exist folder...', 'info');
                            $progress->log('folder_id: ' . $_storage['params']['folder_id'], 'info');

                            $folder_data = $GoogleDriveApi->CheckExistFolder($_storage['params']['folder_id']);

                            if ($folder_data['is_new']) {
                                $storages = json_decode(WT_Backups_Option::getOption('storages'), true);
                                $storages[$_storage]['params']['folder_id'] = $folder_data['folder_id'];
                                WT_Backups_Option::setOptions(['storages' => $storages]);
                            }

                            $progress->log('Checking exist file...', 'info');

                            $list = $GoogleDriveApi->GetAllFiles($folder_data['folder_id']);
                            foreach ($list['files'] as $item) {
                                if ($backupSettings['backup_name'] . '.zip' == $item['name']) {
                                    $storages_status = 'warning';
                                    $massage = '<span style="color:#b98000">' . __("Such a file already exists in the repository.", 'wt-backups') . '</span>';
                                }
                            }
                        }
                    }


                    $storages_info['list'] .= $i . '. ' . $_storage['type'] . ', ' . $_storage['dest'] . ' ' . $massage . '<br>';
                } else {
                    $storages_info['list'] .= $i . '. ' . $_storage['type'] . ', ' . $_storage['dest'] . '<br>';
                }
                $i++;
            }
            if ($storages_info['status'] == 'error') {
                $progress->log(__("Storages are writable ", 'wt-backups'), 'success');
            }

        }

        $backup_info = '';
        if ($this->total_size_for_backup_in_mb !== 0) {
            if (round($this->total_size_for_backup_in_mb)) {
                $total_size = round($this->total_size_for_backup_in_mb);
            } else {
                for ($i = 2; ; $i++) {
                    $total_size = round($this->total_size_for_backup_in_mb, $i);
                    if ($total_size > 0) {
                        break;
                    }
                }
            }

            $backup_info .= 'Total size: ' . $total_size . ' Mb <br>';
        }

        if ($backupSettings['backup_name']) {
            $backup_info .= 'Backup name by user: ' . str_replace('.zip', '', $backupSettings['backup_name']) . '.zip' . ' ' . $backup_name_info . ' <br>';
        }

        if ($backupSettings['db_only']) {
            $backup_info .= 'Backup contents: database only <br>';
        } elseif (!$backupSettings['choose_folders']) {
            $backup_info .= 'Backup contents: All files and folders. Database. <br>';
        } else {
            $folders = [];
            foreach ($backupSettings['folders'] as $folder => $value) {
                if ($value) {
                    $folders[] = $folder;
                }
            }
            $backup_info .= 'Backup contents: ' . implode(', ', $folders) . ' folders and database. <br>';
        }

        WT_Backups_Option::setSessionOptions(['backupChecks' => [
            'hosting_info' => $hosting_info,
            'db_info' => $db_info,
            'storages_info' => $storages_info,
            'storages_status' => $storages_status,
            'directory' => $directory ?? ['status' => 'good'],
            'backup_weights' => $backup_weights ?? ['status' => 'good'],
            'free_space' => $free_space ?? ['status' => 'good'],
            'backup_info' => $backup_info
        ]]);

        $progress->progress(100);

        $progress->log('Successful', 'END');
        // WT_Backups_Option::setNotification('success', __( "All checks were successful", 'wt-backups' ));

        return $directory ?? ($backup_weights ?? ($free_space ?? ['status' => 'success', 'message' => __("All checks were successful", 'wt-backups')]));

    }

    /**
     * Create backup.
     *
     * @return void|array
     */
    public function backup($cron = false)
    {
        if(!$cron){
            wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_create_backup_nonce' );
        }

        $backupSettings = $cron ? json_decode(WT_Backups_Option::getOption('backup_settings'), true) : WT_Backups_Option::getSessionOption('backup_settings');

        if (!array_key_exists('folder_path', $backupSettings)) {
            $backupSettings['folder_path'] = WT_BACKUPS_STORAGE;
        }

        $user_backup_name = array_key_exists('backup_name', $backupSettings) ? $backupSettings['backup_name'] : '';
        $backup_name = $user_backup_name ? str_replace('.zip', '', $user_backup_name) . '.zip' : $this->makeBackupName();

        // Progress & Logs
        $progress = new WT_Backups_Progress($backup_name, 100, 0);
        $progress->start();

        // Just in case (e.g. syntax error, we can close the file correctly)
        $GLOBALS['wt_backups_backup_progress'] = $progress;

        $progress->log(__("Initializing backup...", 'wt-backups'), 'step');

        // Checker
        $checker = new WT_Backups_Checker($progress);

        if (!$this->wp_filesystem->is_writable(dirname($backupSettings['folder_path']))) {

            // Abort backup
            $progress->log(__("Backup directory is not writable...", 'wt-backups'), 'error');
            $progress->log(__("Path: ", 'wt-backups') . $backupSettings['folder_path'], 'error');

            // Close backup
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }

            // Log and close log
            $progress->log('Fail', 'END');
            $progress->end();

            // Return error
            return ['status' => 'error'];
        } else {
            $progress->log(__("Yup it is writable...", 'wt-backups'), 'success');
        }

        if (!file_exists(WT_BACKUPS_STORAGE)) {
            $this->wp_filesystem->mkdir(WT_BACKUPS_STORAGE, 0755);
        }

        // Get file names (huge list mostly)
        if (!$backupSettings['db_only']) {
            $progress->log(__("Scanning files...", 'wt-backups'), 'step');
            $files = $this->scanFilesForBackup($progress, $cron);
            $files = $this->parseFilesForBackup($files, $progress, $cron);
        } else {
            $progress->log(__("Omitting files (due to settings)...", 'wt-backups'), 'warn');
            $files = [];
        }

        // If only database backup
        if (!isset($this->total_size_for_backup)) {
            $this->total_size_for_backup = 0;
        }
        if (!isset($this->total_size_for_backup_in_mb)) {
            $this->total_size_for_backup_in_mb = 0;
        }

        // Check if there is enough space
        $bytes = intval($this->total_size_for_backup * 1.2);
        $progress->log(__("Checking free space, reserving...", 'wt-backups'), 'step');
        if ($this->total_size_for_backup_in_mb >= 2000) {

            // Abort backup
            $progress->log(__("Aborting backup...", 'wt-backups'), 'step');
            $progress->log(str_replace('%s', 2, __("Site weights more than %s GB.", 'wt-backups')), 'error');

            // Close backup
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }

            // Log and close log
            $progress->log('Fail', 'END');
            $progress->end();

            // Return error
            WT_Backups_Option::setNotification('error', str_replace('%s', 2, __("Site weights more than %s GB.", 'wt-backups')));

        }


        if (!$checker->check_free_space($bytes)) {

            // Abort backup
            $progress->log(__("Aborting backup...", 'wt-backups'), 'step');
            $progress->log(__("There is no space for that backup, checked: ", 'wt-backups') . ($bytes) . __(" bytes", 'wt-backups'), 'error');

            // Close backup
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }

            // Log and close log
            $progress->log('Fail', 'END');
            $progress->end();

            // Return error
            //return [ 'status' => 'error' ];

            WT_Backups_Option::setNotification('error', __('There is no space for that backup', 'wt-backups'));

        } else {
            $progress->log(__("Confirmed, there is more than enough space, checked: ", 'wt-backups') . ($bytes) . __(" bytes", 'wt-backups'), 'success');
            $progress->bytes = $this->total_size_for_backup;
        }


        // Log and set files length
        $progress->log(__("Scanning done - found ", 'wt-backups') . sizeof($files) . __(" files...", 'wt-backups'), 'info');
        $progress->files = sizeof($files);

        // Make Backup
        $progress->log(__("Backup initialized...", 'wt-backups'), 'success');
        $progress->log(__("Initializing archiving system...", 'wt-backups'), 'step');

        $this->createBackup($files, $backup_name, $progress, $cron);

        if ($backupSettings['storages']) {
            $progress->log(__("Start checking storages...", 'wt-backups'), 'info');
            $fullpath = trailingslashit(WT_BACKUPS_STORAGE) . $backup_name;

            $backup_storages = json_decode(WT_Backups_Option::getOption('storages'), true);

            foreach ($backupSettings['storages'] as $_storage) {
                $timer_start = microtime(true);
                $storage = $backup_storages[$_storage];
                // FTP
                if ($storage['type'] == 'ftp/sftp') {
                    $ftp = new WT_Backups_FTP($progress, $storage['params']['ftp_type'], $storage['params']['ftp_host'], $storage['params']['ftp_user'], $storage['params']['ftp_password'], $storage['params']['ftp_path'], $storage['params']['ftp_port']);

                    $progress->log(sprintf(__('Try connect to storage: %s', 'wt-backups'), $storage['params']['ftp_host']), 'info');

                    if (is_wp_error($ftp) || !$ftp->connect()) {
                        if (is_wp_error($ftp)) {
                            $progress->log($ftp, 'error');
                        } else {
                            $progress->log(__("Failure: we did not successfully log in with those credentials.", 'wt-backups'), 'error');
                        }
                        $progress->log(__("FTP login failure", 'wt-backups'), 'error');

                    } else {
                        $progress->log("upload attempt: $backup_name -> ftp://" . $storage['params']['ftp_user'] . "@" . $storage['params']['ftp_host'] . "/" . $storage['params']['ftp_path'], 'info');

                        if ($ftp->put($fullpath, $backup_name)) {
                            $size_k = round(filesize($fullpath) / 1024, 1);
                            $progress->log("upload attempt successful (" . $size_k . "KB in " . (round(microtime(true) - $timer_start, 2)) . 's)', 'success');
                        } else {
                            $progress->log(__("ERROR: FTP/SFTP upload failed", 'wt-backups'), 'error');
                        }
                    }
                } elseif ($storage['type'] == 'cloud') {

                    $max_execution_time_initial_value = @ini_get('max_execution_time');
                    try {
                        @ini_set('max_execution_time', '1800');

                        $size_k = round(filesize($fullpath) / 1024, 1);
                        if ($storage['params']['storage'] == 'google_drive') {
                            $file_content = $this->wp_filesystem->get_contents($fullpath);
                            $mime_type = WT_Backups_Helper::get_mime($fullpath);
                            $GoogleDriveApi = new WT_Backups_GoogleDriveApi($storage['params']['access_token'], $_storage);
                            $progress->log(sprintf(__('Try connect to storage: %s', 'wt-backups'), $storage['dest']), 'info');

                            // Upload file to Google drive
                            $drive_file_id = $GoogleDriveApi->UploadFileToDrive($file_content, $mime_type);

                            $folder_id = $storage['params']['folder_id'];
                            $folder_data = $GoogleDriveApi->CheckExistFolder($folder_id);
                            $folder_id = $folder_data['folder_id'];

                            if ($folder_data['is_new']) {
                                $storages = json_decode(WT_Backups_Option::getOption('storages'), true);
                                $storages[$_storage]['params']['folder_id'] = $folder_id;
                                WT_Backups_Option::setOptions(['storages' => $storages]);
                            }

                            if ($drive_file_id) {
                                $file_meta = array(
                                    'name' => basename($backup_name)
                                );

                                // Update file metadata in Google drive
                                $drive_file_meta = $GoogleDriveApi->UpdateFileMeta($drive_file_id, $file_meta, $folder_id);

                                if ($drive_file_meta) {
                                    $progress->log("upload attempt successful (" . $size_k . "KB in " . (round(microtime(true) - $timer_start, 2)) . 's)', 'success');
                                }
                            }
                        } elseif ($storage['params']['storage'] == 'dropbox') {
                            $progress->log(sprintf(__('Try connect to storage: %s', 'wt-backups'), $storage['dest']), 'info');
                            $dropbox = new WT_Backups_Dropbox($storage['params']['data'], $storage['params']['timestamp'], $_storage);
                            $dropbox->UploadFileToStorage($fullpath, $backup_name);
                            $progress->log("upload attempt successful (" . $size_k . "KB in " . (round(microtime(true) - $timer_start, 2)) . 's)', 'success');
                        }

                        @ini_set('max_execution_time', $max_execution_time_initial_value);

                    } catch (Exception $e) {
                        @ini_set('max_execution_time', $max_execution_time_initial_value);
                        $statusMsg = $e->getMessage();
                        $progress->log($statusMsg, 'error');
                    }
                } elseif ($storage['type'] == 'local') {

                    $progress->log(sprintf(__('Try copy zip to storage: %s', 'wt-backups'), $storage['dest']), 'info');
                    $file_content = $this->wp_filesystem->get_contents($fullpath);
                    $this->wp_filesystem->put_contents(trailingslashit($storage['params']['folder_path']) . $backup_name, $file_content);

                    $size_k = round(filesize($fullpath) / 1024, 1);
                    $progress->log("copy attempt successful (" . $size_k . "KB in " . (round(microtime(true) - $timer_start, 2)) . 's)', 'success');

                }
            }
        } else {
            $progress->log(__("No Storage ", 'wt-backups'), 'info');
        }

        $progress->log(__("Successful", 'wt-backups'), 'END');
        $progress->end();

        if (!$cron) {
            WT_Backups_Option::setSessionOptions(['backup_settings' => []]);
        }

        $progress->start(true);
        WT_Backups_Helper::getAvailableBackups();
    }

    public function createBackup($files, $name, &$progress, $cron = false)
    {

        // Backup name
        $backup_path = WT_BACKUPS_STORAGE . '/' . $name;

        // Check time if not bugged
        if (file_exists(WT_BACKUPS_STORAGE . '/.running') && (time() - filemtime(WT_BACKUPS_STORAGE . '/.running')) > 65) {
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }
        }

        // Mark as in progress
        if (!file_exists(WT_BACKUPS_STORAGE . '/.running')) {
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . '/.running');
        } else {
            WT_Backups_Option::setNotification('warning', __('Backup process already running, please wait till it complete.', 'wt-backups'));
            return ['success' => false];

        }

        // Initialized
        $progress->log(__("Archive system initialized...", 'wt-backups'), 'success');

        // Make ZIP
        $zipper = new WT_Backups_Zipper();
        $zippy = $zipper->makeZIP($files, $backup_path, $name, $progress, $cron);
        if (!$zippy) {

            // Make sure it's open
            $progress->start();

            // Abort backup
            $progress->log(__("Aborting backup...", 'wt-backups'), 'step');

            // Close backup
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }

            // Log and close log
            $progress->log('Fail', 'END');
            $progress->end();

            // Return error
            if (file_exists($backup_path)) {
                wp_delete_file($backup_path);
            }

            //return [ 'status' => 'error' ];
            WT_Backups_Option::setNotification('error', __('Aborting backup...', 'wt-backups'));
            return ['success' => false];

        }
//        else {
//            $zip = new ZipArchive();
//            $zip_status = $zip->open($backup_path);
//
//            if ($zip_status === true) {
//                if ($zip->setPassword("MySecretPassword")) {
//                    $progress->log(__("The password has been added.", 'wt-backups'), 'warn');
//                }
//
//                $zip->close();
//            } else {
//
//                $progress->log(__("The password has not been added.", 'wt-backups'), 'error');
//            }
//        }

        // Backup aborted
        if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {

            // Make sure it's open
            $progress->start();

            if (file_exists($backup_path)) {
                wp_delete_file($backup_path);
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }

            // Log and close log
            $progress->log(__("Backup process aborted.", 'wt-backups'), 'warn');
            $progress->log('Fail', 'END');
            $progress->end();

            WT_Backups_Logger::log(__("Backup process aborted.", 'wt-backups'));

            //return [ 'status' => 'msg', 'why' => __( 'Backup process aborted.', 'wt-backups' ), 'level' => 'info' ];

            WT_Backups_Option::setNotification('info', __('Backup process aborted', 'wt-backups'));
            return ['success' => false];

        }

        if (!file_exists($backup_path)) {

            // Make sure it's open
            $progress->start();

            // Abort backup
            $progress->log(__("Aborting backup...", 'wt-backups'), 'step');
            $progress->log(__("There is no backup file...", 'wt-backups'), 'error');
            $progress->log(__("We could not find backup file when it already should be here.", 'wt-backups'), 'error');
            $progress->log(__("This error may be related to missing space. (filled during backup)", 'wt-backups'), 'error');
            $progress->log(__("Path: ", 'wt-backups') . $backup_path, 'error');

            // Close backup
            if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
            }

            // Log and close log
            $progress->log('Fail', 'END');
            $progress->end();

            // Return error
            //return [ 'status' => 'error' ];
            WT_Backups_Option::setNotification('error', __('Aborting backup...', 'wt-backups'));
            return ['success' => false];

        }

        // End zip log
        $progress->log(__("New backup created and its name is: ", 'wt-backups') . $name, 'success');


        // Unlink progress
        if (file_exists(WT_BACKUPS_STORAGE . '/.running')) {
            wp_delete_file(WT_BACKUPS_STORAGE . '/.running');
        }
        if (file_exists(WT_BACKUPS_STORAGE . '/.abort')) {
            wp_delete_file(WT_BACKUPS_STORAGE . '/.abort');
        }

        // Return
        WT_Backups_Logger::log(__("New backup created and its name is: ", 'wt-backups') . $name);

        $GLOBALS['wt_backups_error_handled'] = true;

        WT_Backups_Option::setNotification('success', __("New backup created and its name is: ", 'wt-backups') . $name);
        return ['success' => true];

    }

    public function makeBackupName()
    {
        $name = 'WT_Backup_%Y-%m-%d_%H_%i_%s_%hash';

        $hash = WT_Backups_Helper::generateRandomString(16);
        $name = str_replace('%hash', $hash, $name);
        $name = str_replace('%Y', gmdate('Y'), $name);
        $name = str_replace('%M', gmdate('M'), $name);
        $name = str_replace('%D', gmdate('D'), $name);
        $name = str_replace('%d', gmdate('d'), $name);
        $name = str_replace('%j', gmdate('j'), $name);
        $name = str_replace('%m', gmdate('m'), $name);
        $name = str_replace('%n', gmdate('n'), $name);
        $name = str_replace('%Y', gmdate('Y'), $name);
        $name = str_replace('%y', gmdate('y'), $name);
        $name = str_replace('%a', gmdate('a'), $name);
        $name = str_replace('%A', gmdate('A'), $name);
        $name = str_replace('%B', gmdate('B'), $name);
        $name = str_replace('%g', gmdate('g'), $name);
        $name = str_replace('%G', gmdate('G'), $name);
        $name = str_replace('%h', gmdate('h'), $name);
        $name = str_replace('%H', gmdate('H'), $name);
        $name = str_replace('%i', gmdate('i'), $name);
        $name = str_replace('%s', gmdate('s'), $name);
        $name = str_replace('%s', gmdate('s'), $name);

        $i = 2;
        $tmpname = $name;

        while (file_exists($tmpname . '.zip')) {
            $tmpname = $name . '_' . $i;
            $i++;
        }

        $name = $tmpname . '.zip';

        $GLOBALS['wt_backups_current_backup_name'] = $name;

        return $name;
    }

    public function backupErrorHandler()
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {

            if (strpos($errstr, 'deprecated') !== false) {
                return;
            }
            if (strpos($errstr, 'php_uname') !== false) {
                return;
            }
            if ((strpos($errstr, 'wt_backups') !== false) && (strpos($errstr, 'wt_backups') !== false)) {
                return;
            }

            if ($errno != E_ERROR && $errno != E_CORE_ERROR && $errno != E_COMPILE_ERROR && $errno != E_USER_ERROR && $errno != E_RECOVERABLE_ERROR) {

                if (strpos($errfile, 'wt_backups') === false && strpos($errfile, 'wt_backups') === false) {
                    return;
                }
                WT_Backups_Logger::error(__('There was an error before request shutdown (but it was not logged to restore log)', 'wt-backups'));
                WT_Backups_Logger::error(__('Error message: ', 'wt-backups') . $errstr);
                WT_Backups_Logger::error(__('Error file/line: ', 'wt-backups') . $errfile . '|' . $errline);

                return;

            }
            if (strpos($errstr, 'unlink(') !== false) {
                WT_Backups_Logger::error(__("Restore process was not aborted due to this error.", 'wt-backups'));
                WT_Backups_Logger::error($errstr);

                return;
            }
            if (strpos($errfile, 'pclzip') !== false) {
                WT_Backups_Logger::error(__("Restore process was not aborted due to this error.", 'wt-backups'));
                WT_Backups_Logger::error($errstr);

                return;
            }

            $this->zip_progress->log(__("There was an error during backup:", 'wt-backups'), 'error');
            $this->zip_progress->log(__("Message: ", 'wt-backups') . $errstr, 'error');
            $this->zip_progress->log(__("File/line: ", 'wt-backups') . $errfile . '|' . $errline, 'error');
            $this->zip_progress->log(__('Unfortunately we had to remove the backup (if partly created).', 'wt-backups'), 'error');

            $backup = $GLOBALS['wt_backups_current_backup_name'];
            $backup_path = WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . $backup;
            if (file_exists($backup_path)) {
                wp_delete_file($backup_path);
            }
            if (file_exists(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . '.running')) {
                wp_delete_file(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . '.running');
            }
            if (file_exists(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . '.abort')) {
                wp_delete_file(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . '.abort');
            }

            $this->zip_progress->log(__("Aborting backup...", 'wt-backups'), 'step');
            $this->zip_progress->log(__("Successful", 'wt-backups'), 'END');
            $this->zip_progress->end();

            $GLOBALS['wt_backups_error_handled'] = true;
            exit;

        }, E_ALL);
    }

    public function backupExceptionHandler()
    {
        set_exception_handler(function ($exception) {
            $this->zip_progress->log(__("Exception: ", 'wt-backups') . $exception->getMessage(), 'warn');
            WT_Backups_Logger::log(__("Exception: ", 'wt-backups') . $exception->getMessage());
        });
    }

    public function scanFilesForBackup(&$progress, $cron)
    {

        // Get settings form settings
        $backupSettings = $cron ? json_decode(WT_Backups_Option::getOption('backup_settings'), true) : WT_Backups_Option::getSessionOption('backup_settings');

        if ($backupSettings['choose_folders']) {
            $f_plugins = $backupSettings['folders']['plugins'];
            $f_themes = $backupSettings['folders']['themes'];
            $f_uploads = $backupSettings['folders']['uploads'];
            $f_others = $backupSettings['folders']['others'];
            $f_core = $backupSettings['folders']['core'];
        } else {
            $f_plugins = $f_themes = $f_uploads = $f_others = $f_core = true;
        }

        $ignored_paths_default = [WT_BACKUPS_STORAGE, WT_BACKUPS_PLUGIN_PATH, $this->backup_storage];
        $ignored_paths_default[] = "***ABSPATH***/wp-content/backup-backup";
        $ignored_paths_default[] = "***ABSPATH***/wp-content/uploads/wp-clone";
        $ignored_paths_default[] = "***ABSPATH***/wp-content/updraft";

        $ignored_paths = $ignored_paths_default;
        $ignored_folders = [];

        // Fix slashes for current system (directories)
        for ($i = 0; $i < sizeof($ignored_paths); ++$i) {
            $ignored_paths[$i] = str_replace('***ABSPATH***', untrailingslashit(ABSPATH), $ignored_paths[$i]);
            $ignored_paths[$i] = WT_Backups_Helper::fixSlashes($ignored_paths[$i]);
        }

        // WordPress Paths
        $plugins_path = WT_Backups_Helper::fixSlashes(WP_PLUGIN_DIR);
        $themes_path = WT_Backups_Helper::fixSlashes(dirname(get_template_directory()));
        $uploads_path = WT_Backups_Helper::fixSlashes(wp_upload_dir()['basedir']);
        $wp_contents = WT_Backups_Helper::fixSlashes(WP_CONTENT_DIR);
        $wp_install = WT_Backups_Helper::fixSlashes(ABSPATH);

        // Getting plugins
        $sfgp = WT_Backups_Scanner::equalFolderByPath($wp_install, $plugins_path, $ignored_folders);
        if ($f_plugins && !$sfgp) {
            $plugins_path_files = WT_Backups_Scanner::scanFilesGetNamesWithIgnoreFBC($plugins_path, $ignored_folders, $ignored_paths);
        }

        // Getting themes
        $sfgt = WT_Backups_Scanner::equalFolderByPath($wp_install, $themes_path, $ignored_folders);
        if ($f_themes && !$sfgt) {
            $themes_path_files = WT_Backups_Scanner::scanFilesGetNamesWithIgnoreFBC($themes_path, $ignored_folders, $ignored_paths);
        }

        // Getting uploads
        $sfgu = WT_Backups_Scanner::equalFolderByPath($wp_install, $uploads_path, $ignored_folders);
        if ($f_uploads && !$sfgu) {
            $uploads_path_files = WT_Backups_Scanner::scanFilesGetNamesWithIgnoreFBC($uploads_path, $ignored_folders, $ignored_paths);
        }

        // Ignore above paths
        $sfgoc = WT_Backups_Scanner::equalFolderByPath($wp_install, $wp_contents, $ignored_folders);
        if ($f_others && !$sfgoc) {

            // Ignore common folders (already scanned)
            $content_folders = [$plugins_path, $themes_path, $uploads_path];
            WT_Backups_Helper::merge_arrays($content_folders, $ignored_paths);

            // Getting other contents
            $wp_contents_files = WT_Backups_Scanner::scanFilesGetNamesWithIgnoreFBC($wp_contents, $ignored_folders, $content_folders);
        }

        // Ignore contents path
        if ($f_core) {

            // Ignore contents file
            $ignored_paths[] = $wp_contents;

            // Getting WP Installation
            $wp_install_files = WT_Backups_Scanner::scanFilesGetNamesWithIgnoreFBC($wp_install, $ignored_folders, $ignored_paths);
        }

        // Concat all file paths
        $all_files = [];
        if ($f_plugins && !$sfgp) {
            WT_Backups_Helper::merge_arrays($all_files, $plugins_path_files);
            unset($plugins_path_files);
        }

        if ($f_themes && !$sfgt) {
            WT_Backups_Helper::merge_arrays($all_files, $themes_path_files);
            unset($themes_path_files);
        }

        if ($f_uploads && !$sfgu) {
            WT_Backups_Helper::merge_arrays($all_files, $uploads_path_files);
            unset($uploads_path_files);
        }

        if ($f_others && !$sfgoc) {
            WT_Backups_Helper::merge_arrays($all_files, $wp_contents_files);
            unset($wp_contents_files);
        }

        if ($f_core) {
            WT_Backups_Helper::merge_arrays($all_files, $wp_install_files);
            unset($wp_install_files);
        }

        return $all_files;
    }

    public function parseFilesForBackup(&$files, &$progress, $cron = false)
    {
        $backup_settings = json_decode(WT_Backups_Option::getOption('backup_settings'), true);

        $limitcrl = 96;
        $first_big = false;
        $sizemax = $backup_settings['max_file_size'] ?: 100;
        $usesize = true;

        $total_size = 0;
        $max = $sizemax * (1024 * 1024);
        $maxfor = sizeof($files);

        // Process due to rules
        for ($i = 0; $i < $maxfor; ++$i) {

            // Remove size from path and get the size
            $files[$i] = explode(',', $files[$i]);
            $last = sizeof($files[$i]) - 1;
            $size = intval($files[$i][$last]);
            unset($files[$i][$last]);
            $files[$i] = implode(',', $files[$i]);

            if ($usesize && WT_Backups_Scanner::fileTooLarge($size, $max)) {
                $progress->log(__("Removing file from backup (too large) ", 'wt-backups') . $files[$i] . ' (' . number_format(($size / 1024 / 1024), 2) . ' MB)', 'WARN');
                array_splice($files, $i, 1);
                $maxfor--;
                $i--;

                continue;
            }

            if ($size === 0) {
                array_splice($files, $i, 1);
                $maxfor--;
                $i--;

                continue;
            }

            if (strpos($files[$i], 'wtb-pclzip-') !== false) {
                array_splice($files, $i, 1);
                $maxfor--;
                $i--;

                continue;
            }

            if ($size > ($limitcrl * (1024 * 1024))) {
                if ($first_big === false) {
                    $first_big = $i;
                }
                $progress->log(__("This file is quite big consider to exclude it, if backup fails: ", 'wt-backups') . $files[$i] . ' (' . WT_Backups_Helper::humanFilesize($size) . ')', 'WARN');
            }

            $total_size += $size;
        }

        $this->total_size_for_backup = $total_size;
        $this->total_size_for_backup_in_mb = ($total_size / 1024 / 1024);

        return $files;
    }


    public function restore_checking()
    {

        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_restore_checking_' . sanitize_text_field($_POST['file']) );
        global $wp_version;

        $max_execution_time_initial_value = @ini_get('max_execution_time');
        @ini_set('max_execution_time', '1800');

        $restore = new WT_Backups_CheckRestoreProgress();

        // Checker
        $checker = new WT_Backups_Checker($restore);
        $zipper = new WT_Backups_Zipper();
        $file_name = sanitize_text_field($_POST['file']);

        if ($file_name) {
            $restore->log(__('Restore checks process responded', 'wt-backups'), 'SUCCESS');
        }


        // Initializing
        $restore->log(__('Initializing restore checks process', 'wt-backups'), 'STEP');
        $restore->log((__("WT backups version: ", 'wt-backups') . WT_BACKUPS_VERSION), 'info');

        // Error handler
        $restore->log(__("Initializing custom error handler", 'wt-backups'), 'info');

        // Error handler
        $this->restore_progress = &$restore;
        $this->restoreErrorHandler();
        $this->restoreExceptionHandler();


        $restore->log(__("Site which will be restored: ", 'wt-backups') . site_url(), 'info');
        $restore->log(__("PHP Version: ", 'wt-backups') . PHP_VERSION, 'info');
        $restore->log(__("WP Version: ", 'wt-backups') . $wp_version, 'info');
        $restore->log(__("MySQL Version: ", 'wt-backups') . $GLOBALS['wpdb']->db_version(), 'info');
        $restore->log(__("MySQL Max Length: ", 'wt-backups') . $GLOBALS['wpdb']->get_results("SHOW VARIABLES LIKE 'max_allowed_packet';")[0]->Value, 'info');
        $restore->log(__("Web server: ", 'wt-backups') . esc_html(sanitize_text_field($_SERVER['SERVER_SOFTWARE'])), 'info');
        $restore->log(__("Max execution time (in seconds): ", 'wt-backups') . @ini_get('max_execution_time'), 'info');
        $restore->log(__("Restore checks process initialized successfully.", 'wt-backups'), 'success');

        // Check file size
        $zippath = WT_Backups_Helper::fixSlashes(WT_BACKUPS_STORAGE) . DIRECTORY_SEPARATOR . $file_name;

        $manifest = $zipper->getZipFileContent($zippath, 'wtb_backup_manifest.json');
        $restore->log(__('Free space checking...', 'wt-backups'), 'STEP');
        $restore->log(__('Checking if there is enough amount of free space', 'wt-backups'), 'INFO');
        if ($manifest) {
            if (isset($manifest->bytes) && $manifest->bytes) {
                $bytes = intval($manifest->bytes * 1.2);
                if (!$checker->check_free_space($bytes)) {
                    $restore->log(__('Cannot start restore process', 'wt-backups'), 'ERROR');
                    $restore->log(sprintf(__('Error: There is not enough space on the server, checked: %s bytes.', 'wt-backups'), $bytes), 'ERROR');
                    $restore->log(__('Aborting...', 'wt-backups'), 'ERROR');
                    $restore->log(__('Unlocking restore', 'wt-backups'), 'INFO');

                    $restore->log('Fail', 'END-CODE');
                    $free_space = ['status' => 'error', 'message' => __('There is no space for that backup', 'wt-backups')];

                    WT_Backups_Option::setNotification('error', sprintf(__('Error: There is not enough space on the server, checked: %s bytes.', 'wt-backups'), $bytes));

                } else {
                    $restore->log(sprintf(__('Confirmed, there is enough space on the device, checked: %s bytes.', 'wt-backups'), $bytes), 'SUCCESS');
                }
            }
        } else {
            $restore->log(__('Cannot start restore process', 'wt-backups'), 'ERROR');
            $restore->log(__('Error: Could not find manifest in backup, file may be broken', 'wt-backups'), 'ERROR');
            $restore->log(__('Error: Btw. because of this I also cannot check free space', 'wt-backups'), 'ERROR');
            $restore->log(__('Aborting...', 'wt-backups'), 'ERROR');
            $restore->log(__('Unlocking restore', 'wt-backups'), 'INFO');

            $restore->log('Fail', 'END-CODE');

            WT_Backups_Option::setNotification('error', __('Could not find manifest in backup, file may be broken', 'wt-backups'));

        }

        $backup = WT_Backups_Helper::getBackupInfo($file_name);

        $backup_info = 'File name: ' . $file_name . '<br>';
        $backup_info .= 'Date ' . $backup['date'] . '<br>';
        $backup_info .= 'The backup contains: ' . $backup['list_of_elements'] . '<br>';
        $backup_info .= 'File size: ' . $backup['filesize'] . '<br>';
        $backup_info .= 'Files: ' . $backup['files'] . '<br>';

        $hosting_info = __("WT backups version: ", 'wt-backups') . WT_BACKUPS_VERSION . '<br>';
        $hosting_info .= __("Site which will be backed up: ", 'wt-backups') . site_url() . '<br>';
        $hosting_info .= __("PHP Version: ", 'wt-backups') . PHP_VERSION . '<br>';
        $hosting_info .= __("WP Version: ", 'wt-backups') . $wp_version . '<br>';

        if (isset($_SERVER['SERVER_SOFTWARE']) && !empty(sanitize_text_field($_SERVER['SERVER_SOFTWARE']))) {
            $server_software = sanitize_text_field($_SERVER['SERVER_SOFTWARE']);
            $restore->log(__("Web server: ", 'wt-backups') . wp_kses($server_software, 'post'), 'info');
            $hosting_info .= __("Web server: ", 'wt-backups') . wp_kses($server_software, 'post') . '<br>';
        } else {
            $restore->log(__("Web server: Not available", 'wt-backups'), 'info');
        }

        $restore->progress('100');
        $restore->log(sprintf(__('Restore checking %s (%s) process completed', 'wt-backups'), $file_name, $manifest->date), 'SUCCESS');

        $restore->log('Successful', 'END-CODE');

        WT_Backups_Option::setSessionOptions(['restoreChecks' => [
            'hosting_info' => $hosting_info,
            'hosting_status' => $hosting_info,
            'backup_info' => $backup_info,
            'free_space' => $free_space ?? ['status' => 'good'],
            'file_name' => $file_name,
        ]]);
        @ini_set('max_execution_time', $max_execution_time_initial_value);
    }

    public function restore()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_restore_' . sanitize_text_field($_POST['file']) );
        global $wp_version;

        $max_execution_time_initial_value = @ini_get('max_execution_time');
        @ini_set('max_execution_time', '1800');

        // Progress & lock file
        $lock = WT_BACKUPS_STORAGE . '/.restore_lock';

        $theme = wp_get_theme();
        WT_Backups_Option::setSessionOptions([
            'current_theme' => strtolower(str_replace([' ', '-'], '', $theme->get('Name')))
        ]);

        if (file_exists($lock) && (time() - filemtime($lock)) < 65) {
            WT_Backups_Option::setNotification('error', __('The restore process is currently running, please wait till it end or once the lock file expire.', 'wt-backups'));
        }

        $restore = new WT_Backups_RestoreProgress();
        $restore->start();

        // Checker
        $checker = new WT_Backups_Checker($restore);
        $zipper = new WT_Backups_Zipper();
        $file_name = sanitize_text_field($_POST['file']);

        if ($file_name) {
            $restore->log(__('Restore process responded', 'wt-backups'), 'SUCCESS');
        }

        // Make lock file
        $restore->log(__('Locking restore process', 'wt-backups'), 'SUCCESS');
        $this->wp_filesystem->touch($lock);

        // Initializing
        $restore->log(__('Initializing restore process', 'wt-backups'), 'STEP');
        $restore->log((__("WT backups version: ", 'wt-backups') . WT_BACKUPS_VERSION), 'info');

        // Error handler
        $restore->log(__("Initializing custom error handler", 'wt-backups'), 'info');

        // Error handler
        $this->restore_progress = &$restore;
        $this->restoreErrorHandler();
        $this->restoreExceptionHandler();

        $restore->log(__("Site which will be restored: ", 'wt-backups') . site_url(), 'info');
        $restore->log(__("PHP Version: ", 'wt-backups') . PHP_VERSION, 'info');
        $restore->log(__("WP Version: ", 'wt-backups') . $wp_version, 'info');
        $restore->log(__("MySQL Version: ", 'wt-backups') . $GLOBALS['wpdb']->db_version(), 'info');
        $restore->log(__("MySQL Max Length: ", 'wt-backups') . $GLOBALS['wpdb']->get_results("SHOW VARIABLES LIKE 'max_allowed_packet';")[0]->Value, 'info');
        $restore->log(__("Web server: ", 'wt-backups') . esc_html(sanitize_text_field($_SERVER['SERVER_SOFTWARE'])), 'info');
        $restore->log(__("Max execution time (in seconds): ", 'wt-backups') . @ini_get('max_execution_time'), 'info');
        $restore->log(__("Restore process initialized successfully.", 'wt-backups'), 'success');

        // Check file size
        $zippath = WT_Backups_Helper::fixSlashes(WT_BACKUPS_STORAGE) . DIRECTORY_SEPARATOR . $file_name;

        $manifest = $zipper->getZipFileContent($zippath, 'wtb_backup_manifest.json');
        $restore->log(__('Free space checking...', 'wt-backups'), 'STEP');
        $restore->log(__('Checking if there is enough amount of free space', 'wt-backups'), 'INFO');
        if ($manifest) {
            if (isset($manifest->bytes) && $manifest->bytes) {
                $bytes = intval($manifest->bytes * 1.2);
                if (!$checker->check_free_space($bytes)) {
                    $restore->log(__('Cannot start restore process', 'wt-backups'), 'ERROR');
                    $restore->log(sprintf(__('Error: There is not enough space on the server, checked: %s bytes.', 'wt-backups'), $bytes), 'ERROR');
                    $restore->log(__('Aborting...', 'wt-backups'), 'ERROR');
                    $restore->log(__('Unlocking restore', 'wt-backups'), 'INFO');

                    if (file_exists($lock)) wp_delete_file($lock);
                    $restore->log('Fail', 'END-CODE');

                    WT_Backups_Option::setNotification('error', sprintf(__('Error: There is not enough space on the server, checked: %s bytes.', 'wt-backups'), $bytes));

                } else {
                    $restore->log(sprintf(__('Confirmed, there is enough space on the device, checked: %s bytes.', 'wt-backups'), $bytes), 'SUCCESS');
                }
            }
        } else {
            $restore->log(__('Cannot start restore process', 'wt-backups'), 'ERROR');
            $restore->log(__('Error: Could not find manifest in backup, file may be broken', 'wt-backups'), 'ERROR');
            $restore->log(__('Error: Btw. because of this I also cannot check free space', 'wt-backups'), 'ERROR');
            $restore->log(__('Aborting...', 'wt-backups'), 'ERROR');
            $restore->log(__('Unlocking restore', 'wt-backups'), 'INFO');

            if (file_exists($lock)) wp_delete_file($lock);
            $restore->log('Fail', 'END-CODE');

            WT_Backups_Option::setNotification('error', __('Could not find manifest in backup, file may be broken', 'wt-backups'));

        }

        // New extracter
        $extracter = new WT_Backups_Unzip($file_name, $restore);

        // Extract
        $isFine = $extracter->extractTo();
        if (!$isFine) {
            $restore->log(__('Aborting...', 'wt-backups'), 'ERROR');
            $restore->log(__('Unlocking restore', 'wt-backups'), 'INFO');

            if (file_exists($lock)) wp_delete_file($lock);
            $restore->log('Fail', 'END-CODE');

            WT_Backups_Option::setNotification('error', __('Unlocking restore', 'wt-backups'));

        }

        $restore->progress('100');
        $restore->log(sprintf(__('Restore %s (%s) process completed', 'wt-backups'), $file_name, $manifest->date), 'SUCCESS');
        $restore->log(__('Finalizing restored files', 'wt-backups'), 'STEP');
        $restore->log(__('Unlocking restore', 'wt-backups'), 'INFO');
        if (file_exists($lock)) wp_delete_file($lock);

        $restore->log('Successful', 'END-CODE');

        @ini_set('max_execution_time', $max_execution_time_initial_value);
    }

    public function restoreErrorHandler()
    {
        set_exception_handler(function ($exception) {
            $this->restore_progress->log(__("Restore exception: ", 'wt-backups') . $exception->getMessage(), 'warn');
            WT_Backups_Logger::log(__("Restore exception: ", 'wt-backups') . $exception->getMessage());
        });
    }

    public function restoreExceptionHandler()
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {

            if (strpos($errstr, 'deprecated') !== false) return;
            if (strpos($errstr, 'php_uname') !== false) return;
            if ($errno == E_NOTICE) return;
            if ($errno != E_ERROR && $errno != E_CORE_ERROR && $errno != E_COMPILE_ERROR && $errno != E_USER_ERROR && $errno != E_RECOVERABLE_ERROR) {
                if (strpos($errfile, 'wt_backups') === false && strpos($errfile, 'wt_backups') === false) return;
                WT_Backups_Logger::error(__('There was an error before request shutdown (but it was not logged to restore log)', 'wt-backups'));
                WT_Backups_Logger::error(__('Error message: ', 'wt-backups') . $errstr);
                WT_Backups_Logger::error(__('Error file/line: ', 'wt-backups') . $errfile . '|' . $errline);
                return;
            }

            WT_Backups_Logger::error(__("There was an error/warning during restore process:", 'wt-backups'));
            WT_Backups_Logger::error(__("Message: ", 'wt-backups') . $errstr);
            WT_Backups_Logger::error(__("File/line: ", 'wt-backups') . $errfile . '|' . $errline);

            if (strpos($errfile, 'wt_backups') === false) {
                WT_Backups_Logger::error(__("Restore process was not aborted because this error is not related to WT backups.", 'wt-backups'));
                $this->restore_progress->log(__("There was an error not related to WT backups Plugin.", 'wt-backups'), 'warn');
                $this->restore_progress->log(__("Message: ", 'wt-backups') . $errstr, 'warn');
                $this->restore_progress->log(__("Backup will not be aborted because of this.", 'wt-backups'), 'warn');
                return;
            }
            if (strpos($errstr, 'unlink(') !== false) {
                WT_Backups_Logger::error(__("Restore process was not aborted due to this error.", 'wt-backups'));
                WT_Backups_Logger::error($errstr);
                return;
            }
            if (strpos($errfile, 'pclzip') !== false) {
                WT_Backups_Logger::error(__("Restore process was not aborted due to this error.", 'wt-backups'));
                WT_Backups_Logger::error($errstr);
                return;
            }
            if (strpos($errstr, 'rename(') !== false) {
                WT_Backups_Logger::error(__("Restore process was not aborted due to this error.", 'wt-backups'));
                WT_Backups_Logger::error($errstr);
                $this->restore_progress->log(__("Cannot move: ", 'wt-backups') . $errstr, 'warn');
                return;
            }

            $this->restore_progress->log(__("There was an error during restore process:", 'wt-backups'), 'error');
            $this->restore_progress->log(__("Message: ", 'wt-backups') . $errstr, 'error');
            $this->restore_progress->log(__("File/line: ", 'wt-backups') . $errfile . '|' . $errline, 'error');

            if (file_exists(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . '.restore_lock')) wp_delete_file(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . '.restore_lock');

            $this->restore_progress->log(__("Aborting restore process...", 'wt-backups'), 'step');

            if (isset($GLOBALS['wt_backups_current_tmp_restore']) && !empty($GLOBALS['wt_backups_current_tmp_restore'])) {

                $this->restore_progress->log(__("Cleaning up exported files...", 'wt-backups'), 'step');

                $tmp_unique = $GLOBALS['wt_backups_current_tmp_restore_unique'];
                $dir = $GLOBALS['wt_backups_current_tmp_restore'];
                $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

                $this->restore_progress->log(__('Removing ', 'wt-backups') . iterator_count($files) . __(' files', 'wt-backups'), 'INFO');
                foreach ($files as $file) {
                    if ($file->isDir()) {
                        $this->wp_filesystem->rmdir($file->getRealPath());
                    } else {
                        wp_delete_file($file->getRealPath());
                    }
                }

                $this->wp_filesystem->rmdir($dir);

                $config_file = untrailingslashit(ABSPATH) . DIRECTORY_SEPARATOR . 'wp-config.' . $tmp_unique . '.php';
                if (file_exists($config_file)) wp_delete_file($config_file);

            }

            $this->restore_progress->log(__("Fail", 'wt-backups'), 'end-code');

            exit;

        }, E_ALL);
    }


    /**
     * Delete Backup.
     *
     * @return void
     */
    public function delete_backup()
    {

        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_delete_backup_' . sanitize_text_field($_POST['value']) );

        $file_name = sanitize_text_field($_POST['value']);

        $zippath = WT_Backups_Helper::fixSlashes(WT_BACKUPS_STORAGE) . DIRECTORY_SEPARATOR . $file_name;

        wp_delete_file($zippath);

        $template = new WT_Backups_Template();
        $backups_list = WT_Backups_Helper::getAvailableBackups();

        $build[] = [
            'variables' => [
                'available_backups' => $backups_list,
            ],
            'template' => 'backups_list',
        ];


        WT_Backups_Option::setNotification('success', sprintf(__('File %s was deleted', 'wt-backups'), $file_name));

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'content' => $template->arrayRender($build),
            'backups_count' => count($backups_list),
            'success' => true,
        ], 200);

    }

    /**
     * Progress checker.
     *
     * @return void
     */
    public function progress_checker()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_progress_checker' );
        $process = sanitize_text_field($_POST['process']);
        $last_entry_logs = WT_Backups_Option::getSessionOption('last_entry_logs');
        $progress = 0;

        switch ($process) {
            case 'restore' :
                $log_file = 'latest_restore.log';
                $modification_time = filemtime(WT_BACKUPS_STORAGE . '/' . $log_file);
                $progress = $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . '/latest_restore_progress.log') ?: 0;

                break;

            case 'backup' :

                $log_file = 'latest.log';
                $modification_time = filemtime(WT_BACKUPS_STORAGE . '/' . $log_file);

                $latest_progress = $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . '/latest_progress.log');
                if ($latest_progress == '0/0') {
                    $progress = 0;
                } else {
                    $latest_progress = explode('/', $latest_progress);
                    $progress = floor($latest_progress[0] / ($latest_progress[1] / 100));
                }
                break;

            case 'backup_checking' :

                $log_file = 'latest_checks.log';
                $modification_time = filemtime(WT_BACKUPS_STORAGE . '/' . $log_file);
                $progress = $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . '/latest_checks_progress.log') ?: 0;
                break;

            case 'restore_checking' :

                $log_file = 'latest_restore_checks.log';
                $modification_time = filemtime(WT_BACKUPS_STORAGE . '/' . $log_file);
                $progress = $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . '/latest_restore_checks_progress.log') ?: 0;
                break;

        }

        if ($last_entry_logs[$process] != $modification_time) {
            $latest = WT_Backups_Helper::get_process_logs($log_file);
            $last_entry_logs[$process] = $modification_time;
            WT_Backups_Option::setSessionOptions(['last_entry_logs' => $last_entry_logs]);
        }

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'progress' => $progress,
            'logger' => $latest ?? false,
            'page_nonce' => $progress == 100 ? wp_create_nonce('wt_backups_next_page'): wp_create_nonce('wt_backups_progress_checker'),

        ], 200);

    }


    /**
     * Create backup page.
     *
     * @return void
     */
    public function next_page()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_next_page');
        $action = sanitize_text_field($_POST['backup_action']);
        $file = sanitize_text_field($_POST['file']);
        $page_nonce = '';

        switch ($action) {

            case 'backup_checking_page' :

                $storages = map_deep(wp_unslash($_POST['storages']), 'sanitize_text_field') ?: [];
                $db_only = sanitize_text_field($_POST['db_only']);
                $choose_folders = sanitize_text_field($_POST['choose_folders']);
                $folders_data = map_deep(wp_unslash($_POST['folders']), 'sanitize_text_field');
                $backup_name = sanitize_text_field($_POST['backup_name']);

                $folders = [
                    'plugins' => false,
                    'themes' => false,
                    'uploads' => false,
                    'others' => false,
                    'core' => false,
                ];

                if ($choose_folders && !$db_only) {
                    foreach ($folders_data as $folder => $value) {
                        $folders[$folder] = true;
                    }
                }

                WT_Backups_Option::setSessionOptions(['backup_settings' => [
                    'backup_name' => $backup_name,
                    'db_only' => $db_only ? 1 : 0,
                    'choose_folders' => $choose_folders ? 1 : 0,
                    'folders' => $folders,
                    'storages' => $storages,
                ]]);

                $latest = WT_Backups_Helper::get_process_logs('latest_checks.log');
                $progress = $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . '/latest_checks_progress.log') ?: 0;

                $build[] = [
                    'variables' => [
                        'process' => 'backup_checking',
                        'file' => '',
                        'progress' => 0,
                        'logger' => '',
                        'page_nonce' => wp_create_nonce('wt_backups_process'),
                    ],
                    'template' => 'process'
                ];

                $template = new WT_Backups_Template();
                $content = $template->arrayRender($build);
                $page_nonce = wp_create_nonce('wt_backups_backup_checking_nonce');
                break;

            case 'backup_build_next_page' :
                $process = sanitize_text_field($_POST['process']);

                if ($process == 'backup_checking') {

                    $backupChecks = WT_Backups_Option::getSessionOption('backupChecks');

                    if ($backupChecks['directory']['status'] == 'good' and
                        $backupChecks['backup_weights']['status'] == 'good' and
                        $backupChecks['free_space']['status'] == 'good') {
                        $hosting_status = ['text' => 'Good', 'class' => 'green'];
                    } else {
                        $hosting_status = ['text' => 'Failed', 'class' => 'red'];
                    }

                    if ($backupChecks['storages_status'] == 'good') {
                        $storages_status = ['text' => 'Good', 'class' => 'green'];
                    } elseif ($backupChecks['storages_status'] == 'warning') {
                        $storages_status = ['text' => 'Warning', 'class' => 'orange'];
                    } else {
                        $storages_status = ['text' => 'Failed', 'class' => 'red'];
                    }

                    $build[] = [
                        'variables' => [
                            'backupChecks' => $backupChecks,
                            'hosting_status' => $hosting_status,
                            'storages_status' => $storages_status,
                            'disk_free_space' => WT_Backups_Helper::humanFilesize(intval(disk_free_space(WT_BACKUPS_STORAGE))),
                            'page_nonce' => wp_create_nonce('wt_backups_next_page'),
                        ],
                        'template' => 'prebuild'
                    ];
                } elseif ($process == 'restore') {
                    $build[] = [
                        'variables' => [
                            'title' => __('The recovery has been completed successfully!', 'wt-backups'),
                            'text' => __('You have successfully restored from a backup', 'wt-backups'),
                            'go_home' => WT_Backups_Helper::adminURL('admin.php?page=wt_backups'),
                            'logger' => WT_Backups_Helper::get_process_logs('latest_restore.log'),
                            'process' => $process,
                        ],
                        'template' => 'result'
                    ];
                } elseif ($process == 'restore_checking') {

                    $restoreChecks = WT_Backups_Option::getSessionOption('restoreChecks');

                    if ($restoreChecks['free_space']['status'] == 'good') {
                        $hosting_status = ['text' => 'Good', 'class' => 'green'];
                    } else {
                        $hosting_status = ['text' => 'Failed', 'class' => 'red'];
                    }

                    $build[] = [
                        'variables' => [
                            'restoreChecks' => $restoreChecks,
                            'file_name' => $restoreChecks['file_name'],
                            'hosting_status' => $hosting_status,
                            'disk_free_space' => WT_Backups_Helper::humanFilesize(intval(disk_free_space(WT_BACKUPS_STORAGE))),
                            'page_nonce' => wp_create_nonce('wt_backups_restore_page_nonce_' . $file),
                        ],
                        'template' => 'prerestore'
                    ];
                } else {
                    $build[] = [
                        'variables' => [
                            'title' => __('Hooray! You have created a backup!', 'wt-backups'),
                            'text' => __('It may take some time to upload the backup to the storage(-s).', 'wt-backups'),
                            'go_home' => WT_Backups_Helper::adminURL('admin.php?page=wt_backups'),
                            'logger' => WT_Backups_Helper::get_process_logs('latest.log'),
                            'process' => $process,
                        ],
                        'template' => 'result'
                    ];
                }

                $template = new WT_Backups_Template();
                $content = $template->arrayRender($build);
                break;

            case 'backup_start_building_page' :

                $latest = WT_Backups_Helper::get_process_logs('latest_checks.log');
                $progress = $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . '/latest_checks_progress.log') ?: 0;


                $build[] = [
                    'variables' => [
                        'process' => 'backup',
                        'file' => '',
                        'progress' => 0,
                        'logger' => '',
                        'page_nonce' => wp_create_nonce('wt_backups_process'),
                    ],
                    'template' => 'process'
                ];

                $page_nonce = wp_create_nonce('wt_backups_create_backup_nonce');


                $template = new WT_Backups_Template();
                $content = $template->arrayRender($build);
                break;

        }

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'content' => $content ?? false,
            'progress' => $progress ?: 0,
            'logger' => $latest ?: 'logs empty',
            'page_nonce' => $page_nonce,

        ], 200);

    }

    /**
     * Restore page.
     * A process log is created for the pre-restore checking page and for the restore page/
     *
     * @return void
     */
    public function restore_page()
    {

        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_restore_page_nonce_' . sanitize_text_field($_POST['file']) );
        $action = sanitize_text_field($_POST['restore_action']);

        switch ($action) {

            case 'restore_page' :

                $file = sanitize_text_field($_POST['file']);


                $build[] = [
                    'variables' => [
                        'process' => 'restore',
                        'file' => $file,
                        'progress' => 0,
                        'logger' => '',
                        'page_nonce' => wp_create_nonce('wt_backups_process'),
                    ],
                    'template' => 'process'
                ];
                $page_nonce = wp_create_nonce('wt_backups_restore_'  . sanitize_text_field($_POST['file']));
                $template = new WT_Backups_Template();
                $content = $template->arrayRender($build);
                break;

            case 'restore_checking' :

                $file = sanitize_text_field($_POST['file']);

                $build[] = [
                    'variables' => [
                        'process' => 'restore_checking',
                        'file' => $file,
                        'progress' => 0,
                        'logger' => '',
                        'page_nonce' => wp_create_nonce('wt_backups_process'),
                    ],
                    'template' => 'process'
                ];

                $page_nonce = wp_create_nonce('wt_backups_restore_checking_'  . sanitize_text_field($_POST['file']));
                $template = new WT_Backups_Template();
                $content = $template->arrayRender($build);
                break;

        }

        $latest = WT_Backups_Helper::get_process_logs('latest_restore.log');
        $progress = $this->wp_filesystem->get_contents(WT_BACKUPS_STORAGE . '/latest_restore_progress.log') ?: 0;

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'content' => $content ?? false,
            'progress' => $progress ?: 0,
            'logger' => $latest ?: 'logs empty',
            'page_nonce' => $page_nonce,

        ], 200);

    }

    /**
     * Creating a modal window.
     *
     * @return void
     */
    public static function popup()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_open_popup_' . sanitize_text_field($_POST['file']) );

        $action = sanitize_text_field($_POST['popup_action']);
        $template = new WT_Backups_Template();

        if ($action) {
            $value = sanitize_text_field($_POST['file']);
            switch ($action) {
                case 'delete_backup':

                    $build[] = [
                        'variables' => [
                            'message' => __('The backup will be permanently deleted.', 'wt-backups'),
                            'action' => 'delete_backup',
                            'value' => $value,
                            'page_nonce' => wp_create_nonce('wt_backups_delete_backup_' . $value),
                        ],
                        'template' => 'popup',
                    ];
                    break;

                case 'restore_page':

                    $build[] = [
                        'variables' => [
                            'message' => sprintf(__('Make sure you have saved the latest data. The site will be restored from the archive: %s', 'wt-backups'), $value),
                            'action' => 'restore_page',
                            'value' => $value,
                            'page_nonce' => wp_create_nonce('wt_backups_restore_page_nonce_' . $value),
                        ],
                        'template' => 'popup',
                    ];
                    break;

            }

            wp_send_json([
                'success' => true,
                'content' => $template->arrayRender($build),
            ]);
        }

        wp_send_json([
            'success' => false,
        ]);

    }

    /**
     * Save backup settings.
     *
     * @return void
     */
    public function save_backup_settings()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_save_backup_settings_nonce' );

        $storages = map_deep(wp_unslash($_POST['storages']), 'sanitize_text_field') ?: [];
        $db_only = sanitize_text_field($_POST['db_only']);
        $choose_folders = sanitize_text_field($_POST['choose_folders']);
        $folders_data = map_deep(wp_unslash($_POST['folders']), 'sanitize_text_field');
        $time = sanitize_text_field($_POST['time']);
        $enable_scheduled_backup = sanitize_text_field($_POST['enable_scheduled_backup']);
        $limit_backups = sanitize_text_field($_POST['limit_backups']);
        $max_file_size = sanitize_text_field($_POST['max_file_size']);

        $folders = [
            'plugins' => false,
            'themes' => false,
            'uploads' => false,
            'others' => false,
            'core' => false,
        ];

        if ($choose_folders && !$db_only) {
            foreach ($folders_data as $folder => $value) {
                $folders[$folder] = true;
            }
        }

        $backup_settings = json_decode(WT_Backups_Option::getOption('backup_settings'), true);
        if ($time != $backup_settings['time']) {
            // Delete current cron event
            $timestamp = wp_next_scheduled('wt_backups_init_cron');
            while ($timestamp) {
                wp_unschedule_event($timestamp, 'wt_backups_init_cron');
                $timestamp = wp_next_scheduled('wt_backups_init_cron');
            }

            // Add new cron event
            $date = $time > $backup_settings['time'] ? gmdate('Y-m-d') : gmdate('Y-m-d', strtotime('+1 day', gmdate()));
            wp_schedule_event(strtotime($date . ' ' . $time), 'daily', 'wt_backups_init_cron');
        }

        WT_Backups_Option::setOptions(['backup_settings' => [
            'db_only' => $db_only ? 1 : 0,
            'choose_folders' => $choose_folders ? 1 : 0,
            'folders' => $folders,
            'time' => $time,
            'enable_scheduled_backup' => $enable_scheduled_backup,
            'limit_backups' => $limit_backups,
            'max_file_size' => $max_file_size,
            'storages' => $storages,
        ]]);

        WT_Backups_Option::setNotification('success', __('Settings saved successfully', 'wt-backups'));

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
        ]);

    }

    /**
     * Check backup settings.
     *
     * @return void
     */
    public function check_backup_settings()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_check_settings_nonce' );

        $storages = map_deep(wp_unslash($_POST['storages']), 'sanitize_text_field') ?: [];
        $db_only = sanitize_text_field($_POST['db_only']);
        $choose_folders = sanitize_text_field($_POST['choose_folders']);
        $folders_data = map_deep(wp_unslash($_POST['folders']), 'sanitize_text_field');

        $folders = [
            'plugins' => false,
            'themes' => false,
            'uploads' => false,
            'others' => false,
            'core' => false,
        ];

        if ($choose_folders && !$db_only) {
            foreach ($folders_data as $folder => $value) {
                $folders[$folder] = true;
            }
        }

        WT_Backups_Option::setSessionOptions(['backup_settings' => [
            'db_only' => $db_only ? 1 : 0,
            'choose_folders' => $choose_folders ? 1 : 0,
            'folders' => $folders,
            'storages' => $storages,
        ]]);

        $result = $this->backupChecks();

        WT_Backups_Option::setNotification($result['status'], $result['message']);


        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
        ]);

    }


    /**
     * Save storage.
     *
     * @return void
     */
    public function save_storage()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_save_storage_nonce' );

        $type = sanitize_text_field($_POST['backup_storage']);
        $template = new WT_Backups_Template();

        switch ($type) {
            case 'local' :
                $params = ['folder_path' => sanitize_text_field($_POST['folder_path'])];
                $dest = sanitize_text_field($_POST['folder_path']);
                break;

            case 'ftp/sftp' :
                $params = [
                    'ftp_type' => sanitize_text_field($_POST['ftp_type']),
                    'ftp_host' => sanitize_text_field($_POST['ftp_host']),
                    'ftp_path' => sanitize_text_field($_POST['ftp_path']),
                    'ftp_port' => sanitize_text_field($_POST['ftp_port']),
                    'ftp_user' => sanitize_text_field($_POST['ftp_user']),
                    'ftp_password' => sanitize_text_field($_POST['ftp_password']),
                ];
                $dest = sanitize_text_field($_POST['ftp_host']);
                break;

        }

        $storages = json_decode(WT_Backups_Option::getOption('storages'), true);

        if (isset($params)) {
            $key = $type . '_' . WT_Backups_Helper::generateRandomString(6) . '_' . time();
            $storages[$key] = [
                'type' => $type,
                'dest' => $dest,
                'params' => $params,
            ];

            WT_Backups_Option::setOptions(['storages' => $storages]);
            WT_Backups_Option::setNotification('success', __('Storage successfully added', 'wt-backups'));

        } else {
            WT_Backups_Option::setNotification('error', __('Storage could not be added', 'wt-backups'));
        }

        $build[] = [
            'variables' => [
                'storages' => json_decode(WT_Backups_Option::getOption('storages'), true),
                'page_nonce' => wp_create_nonce('wt_backups_page_nonce'),
            ],
            'template' => 'storages_list',
        ];

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'content' => $template->arrayRender($build),
        ]);

    }

    /**
     * Check FTP connection.
     *
     * @return void
     */
    public function check_ftp()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_check_ftp_nonce' );
        $params = [
            'ftp_type' => sanitize_text_field($_POST['ftp_type']),
            'ftp_host' => sanitize_text_field($_POST['ftp_host']),
            'ftp_port' => sanitize_text_field($_POST['ftp_port']),
            'ftp_user' => sanitize_text_field($_POST['ftp_user']),
            'ftp_password' => sanitize_text_field($_POST['ftp_password']),
        ];

        $result = self::check_ftp_connection($params);

        WT_Backups_Option::setNotification($result['status'], $result['message']);

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'message' => $result['message'],
            'status' => $result['status'],
            'success' => true,
        ]);

    }

    /**
     * Check FTP connection.
     *
     * @return array
     */
    public static function check_ftp_connection($params)
    {

        if ($params['ftp_type'] == 'ftp') {
            $conn_id = ftp_connect($params['ftp_host'], $params['ftp_port']);

            if ($conn_id) {
                $login_result = ftp_login($conn_id, $params['ftp_user'], $params['ftp_password']);

                if ($login_result) {
                    $result = ['status' => 'success', 'message' => 'The FTP connection has been successfully established and authorized.'];
                } else {
                    $result = ['status' => 'error', 'message' => 'Authorization error on the FTP server.'];
                }

                ftp_close($conn_id);
            } else {
                return ['status' => 'error', 'message' => 'Failed to establish a connection to the FTP server.'];
            }
        } elseif ($params['ftp_type'] == 'sftp') {
            if (!function_exists('ssh2_connect')) {
                return ['status' => 'error', 'message' => 'The Perl ssh2 library is missing.'];
            }

            $connection = ssh2_connect($params['ftp_host'], $params['ftp_port']);
            if (!$connection) {
                return ['status' => 'error', 'message' => "Could not connect to {$params['ftp_host']} on port {$params['ftp_port']}."];

            }
            if (!@ssh2_auth_password($connection, $params['ftp_user'], $params['ftp_password'])) {
                return ['status' => 'error', 'message' => 'Authorization error on the FTP server.'];
            }

            $sftp = ssh2_sftp($connection);
            if (!$sftp) {
                $result = ['status' => 'error', 'message' => 'Could not initialize SFTP subsystem.'];
            } else {
                $result = ['status' => 'success', 'message' => 'The FTP connection has been successfully established and authorized.'];
            }

        } else {
            $result = ['status' => 'error', 'message' => 'Не верно указан тип соединения.'];
        }

        return $result;
    }

    /**
     * Remove storage.
     *
     * @return void
     */
    public function remove_storage()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_remove_storage_nonce' );

        $template = new WT_Backups_Template();
        $key = sanitize_text_field($_POST['key']);


        $storages = json_decode(WT_Backups_Option::getOption('storages'), true);

        unset($storages[$key]);
        WT_Backups_Option::setOptions(['storages' => $storages]);

        $build[] = [
            'variables' => [
                'storages' => $storages,
                'page_nonce' => wp_create_nonce('wt_backups_page_nonce'),
            ],
            'template' => 'storages_list',
        ];

        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'content' => $template->arrayRender($build),
        ]);
    }


    /**
     * Remove storage.
     *
     * @return void
     */
    public function upload_backup()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_upload_backup_nonce' );

        $file = map_deep(wp_unslash($_FILES['backup_file']), 'sanitize_text_field');

        $allow = array('zip');
        $error = '';
        $max_file_upload_in_bytes = WT_Backups_Helper::max_file_upload_in_bytes();

        if ($file['size'] > $max_file_upload_in_bytes) {
            $error = 'The file is too large the maximum allowed size in bytes: ' . $max_file_upload_in_bytes;
        } // Проверим на ошибки загрузки.
        elseif (!empty($file['error']) || empty($file['tmp_name'])) {
            $error = 'Failed to upload file. ' . $file['error'];
        } elseif ($file['tmp_name'] == 'none' || !is_uploaded_file($file['tmp_name'])) {
            $error = 'Failed to upload file';
        } else {
            // Оставляем в имени файла только буквы, цифры и некоторые символы.
            $pattern = "[^a-zа-яё0-9,~!@#%^-_\$\?\(\)\{\}\[\]\.]";
            $name = mb_eregi_replace($pattern, '-', $file['name']);
            $name = mb_ereg_replace('[-]+', '-', $name);
            $parts = pathinfo($name);

            if (empty($name) || empty($parts['extension'])) {
                $error = 'Invalid file type';
            } elseif (!empty($allow) && !in_array(strtolower($parts['extension']), $allow)) {
                $error = 'Invalid file type';
            } else {
                // Перемещаем файл в директорию.

                require_once(ABSPATH . 'wp-admin/includes/file.php');

                $upload_overrides = array('test_form' => false);
                $movefile = wp_handle_upload($file, $upload_overrides);

                if ($movefile && empty($movefile['error'])) {
                    $uploaded_file_path = $movefile['file'];


                    if (!file_exists(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . $name)) {
                        $new_file_path = WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . basename($name);
                    } else {
                        $prefix = gmdate('d_m_Y_h_i_s_');
                        $new_file_path = WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . $prefix . basename($name);
                    }

                    $this->wp_filesystem->move($uploaded_file_path, $new_file_path, true);
                }

            }
        }

        if ($error) {
            WT_Backups_Option::setNotification('error', $error);
        } else {
            WT_Backups_Option::setNotification('success', sprintf(__('File %s was added', 'wt-backups'), $file['name']));
        }

        $backups_list = WT_Backups_Helper::getAvailableBackups();

        $build[] = [
            'variables' => [
                'available_backups' => $backups_list,
            ],
            'template' => 'backups_list',
        ];

        $template = new WT_Backups_Template();
        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'content' => $template->arrayRender($build),
            'backups_count' => count($backups_list),
        ]);
    }

    /**
     * Remove storage.
     *
     * @return void
     */
    public function add_cloud_storage()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_add_storage_' . sanitize_text_field($_POST['storage']) );

        $storage = sanitize_text_field($_POST['storage']);
        $prev_page = (strpos(sanitize_text_field($_POST['prev_url']), 'wt_backups_settings') !== false) ? 'wt_backups_settings' : 'wt_backups_create_backup';
        $return_redirect = admin_url('admin.php') . '?page=' . $prev_page;

        switch ($storage) {
            case 'google_drive' :

                $params = array(
                    'response_type' => 'code',
                    'client_id' => '890455749355-7cpqoam0m334a1n6nuv01iea2h6u6aqa.apps.googleusercontent.com',
                    'redirect_uri' => 'https://cloud.checktotem.com/google_drive_answer.php',
                    'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/userinfo.profile',
                    'state' => $return_redirect,
                    'access_type' => 'offline',
                    'approval_prompt' => 'force'
                );

                $redirect_link = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params, null, '&');
                break;

            case 'dropbox' :
                $dropbox = new WT_Backups_Dropbox();
                $redirect_link = $dropbox->GetAuthUrl('https://cloud.checktotem.com/dropbox_answer.php', $return_redirect);
                break;
        }


        $notifications = self::notifications();
        wp_send_json([
            'notifications' => $notifications['notifications'],
            'notifications_row' => $notifications['notifications_row'],
            'success' => true,
            'redirect_link' => $redirect_link,
        ]);
    }

    /**
     * Check zip exist.
     *
     * @return void
     */
    public function check_zip_exist()
    {
        wp_verify_nonce( sanitize_text_field($_REQUEST['ajax_nonce']), 'wt_backups_check_zip_exist_nonce' );

        $status = [
            'message' => 'The name is available.',
            'type' => 'success'
        ];
        $name = sanitize_text_field($_POST['backup_name']);
        if (empty($name)) {
            wp_send_json([
                'status' => [],
            ]);
        }

        if (WT_Backups_Helper::check_zip_exist($name)) {
            $status = [
                'message' => 'Such a file already exists.',
                'type' => 'warning'
            ];
        }

        wp_send_json([
            'status' => $status,
        ]);
    }

    /**
     * Notification output.
     *
     * @return array
     */
    public static function notifications()
    {

        $notifications = WT_Backups_Helper::getNotifications() ?: [];

        if ($notifications) {
            $build[] = [
                'variables' => [
                    'notifications' => $notifications,
                ],

                'template' => 'notifications',
            ];

            $template = new WT_Backups_Template();

            return ['notifications' => $template->arrayRender($build), 'notifications_row' => $notifications];
        }

        return [];

    }

}