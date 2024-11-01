<?php

/**
 * Load page and ajax handlers
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    die("Protected By WebTotem! Not plugin init");
}

function wt_backups_autoload()
{
    $composer_autoload = WT_BACKUPS_PLUGIN_PATH . '/vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        require_once $composer_autoload;
    }
}
function wt_backups_activation_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->activation();
}

function wt_backups_open_popup_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->popup();
}

function wt_backups_restore_page_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->restore_page();
}

function wt_backups_delete_backup_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->delete_backup();
}

function wt_backups_progress_checker_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->progress_checker();
}

function wt_backups_next_page_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->next_page();
}

function wt_backups_backup_checking_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->backup_checking();
}

function wt_backups_backup_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->backup();
}

function wt_backups_restore_checking_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->restore_checking();
}

function wt_backups_restore_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->restore();
}

function wt_backups_check_folder_path_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->check_folder_path();
}

function wt_backups_save_backup_settings_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->save_backup_settings();
}

function wt_backups_check_backup_settings_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->check_backup_settings();
}

function wt_backups_save_storage_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->save_storage();
}

function wt_backups_check_ftp_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->check_ftp();
}

function wt_backups_remove_storage_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->remove_storage();
}

function wt_backups_upload_backup_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->upload_backup();
}

function wt_backups_add_cloud_storage_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->add_cloud_storage();
}

function wt_backups_check_zip_exist_ajax()
{
    wt_backups_autoload();
    $ajax = new WT_Backups_Ajax;
    $ajax->check_zip_exist();
}


/**
 * Activation page.
 *
 * @return void
 */
function wt_backups_activation_page()
{
    $build[] = [
        'variables' => [
            'page_nonce' => wp_create_nonce('wt_backups_activation_nonce'),
            'max_file_upload_in_bytes' =>  WT_Backups_Helper::max_file_upload_in_bytes() ?: '33554432',
            'page' => 'activation',
            'notifications_raw' => json_encode(WT_Backups_Ajax::notifications()),
        ],
        'template' => 'activation'
    ];

    $template = new WT_Backups_Template();
    echo wp_kses($template->arrayRender($build), 'wt_backups');
}

/**
 * Dashboard page.
 *
 * @return void
 */
function wt_backups_dashboard_page()
{
    $available_backups = WT_Backups_Helper::getAvailableBackups();
    $build[] = [
        'variables' => [
            'available_backups' => $available_backups,
            'backups_count' => count($available_backups),
        ],
        'template' => 'dashboard'
    ];

    $template = new WT_Backups_Template();
    $page_content = $template->arrayRender($build);
    echo wp_kses($template->baseTemplate($page_content, true), 'wt_backups');
}

/**
 * Create backup page.
 *
 * @return void
 */
function wt_backups_create_backup_page()
{

    // Save the cloud if there is one.
    wt_backups_save_new_cloud_settings();

    $build[] = [
        'variables' => [
            'local_storage' => WT_BACKUPS_STORAGE,
            'storages' => json_decode(WT_Backups_Option::getOption('storages'), true),
            'settings' => WT_Backups_Option::getSessionOption('backup_settings') ?: [],
        ],
        'template' => 'create_backup'
    ];

    $template = new WT_Backups_Template();
    $page_content = $template->arrayRender($build);
    echo wp_kses($template->baseTemplate($page_content), 'wt_backups');
}

/**
 * Add storage page.
 *
 * @return void
 */
function wt_backups_add_storage_page()
{

    $build[] = [
        'variables' => [
            'local_storage' => WT_BACKUPS_STORAGE,
        ],
        'template' => 'add_storage'
    ];

    $template = new WT_Backups_Template();
    $page_content = $template->arrayRender($build);
    echo wp_kses($template->baseTemplate($page_content, true), 'wt_backups');
}


/**
 * Create backup page.
 *
 * @return void
 */
function wt_backups_support_page()
{

    $build[] = [
        'template' => 'support'
    ];

    $template = new WT_Backups_Template();
    $page_content = $template->arrayRender($build);
    echo wp_kses($template->baseTemplate($page_content, true), 'wt_backups');
}

/**
 * Create backup page.
 *
 * @return void
 */
function wt_backups_settings_page()
{
    $backup_settings = json_decode(WT_Backups_Option::getOption('backup_settings'), true);

    // Save the cloud if there is one.
    wt_backups_save_new_cloud_settings();

    // Google OAuth URL
    $build[] = [
        'variables' => [
            'time_list' => ['00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
            'backup_settings' => $backup_settings ?: [
                'db_only' => 0,
                'choose_folders' => 0,
                'folders' => [
                    'plugins' => false,
                    'themes' => false,
                    'uploads' => false,
                    'others' => false,
                    'core' => false,
                ],
                'time' => '00:00',
                'enable_scheduled_backup' => false,
                'storages' => [],
            ],
            'local_storage' => WT_BACKUPS_STORAGE,
            'storages' => json_decode(WT_Backups_Option::getOption('storages'), true),
        ],

        'template' => 'settings',
    ];

    $template = new WT_Backups_Template();
    $page_content = $template->arrayRender($build);
    echo wp_kses($template->baseTemplate($page_content, true), 'wt_backups');
}

/**
 * Create backup page.
 *
 * @return void
 */
function wt_backups_save_new_cloud_settings()
{
    if (isset($_GET['storage'])) {
        $storage = sanitize_text_field($_GET['storage']);

        $status = sanitize_text_field($_GET['status']);
        $storages = json_decode(WT_Backups_Option::getOption('storages'), true);

        switch ($storage) {
            case 'google_drive' :
                if ($status == 'success') {
                    $message = 'Authorization was successful';

                    $key = 'cloud_' . WT_Backups_Helper::generateRandomString(6) . '_' . time();

                    $access_token = sanitize_text_field($_GET['access_token']);
                    $GoogleDriveApi = new WT_Backups_GoogleDriveApi($access_token);

                    $folder_data = $GoogleDriveApi->CheckExistFolder();
                    $user_data = $GoogleDriveApi->GetUserData();

                    if (WT_Backups_Helper::is_storage_added('google_drive', $user_data['id'])) {
                        WT_Backups_Option::setNotification('success', 'Storage has already been added');
                        break;
                    }

                    $storages[$key] = [
                        'type' => 'cloud',
                        'dest' => 'Google Drive #' . WT_Backups_Helper::generateRandomString(6),
                        'params' => [
                            'storage' => 'google_drive',
                            'timestamp' => time(),
                            'access_token' => $access_token,
                            'folder_id' => $folder_data['folder_id'],
                            'account_id' => $user_data['id'],
                            'data' => [
                                'refresh_token' => sanitize_text_field($_GET['refresh_token']),
                                'expires_in' => sanitize_text_field($_GET['expires_in']),
                            ],
                        ],
                    ];

                    WT_Backups_Option::setOptions(['storages' => $storages]);
                    WT_Backups_Option::setNotification('success', 'Storage successfully added');


                } else {
                    $message = 'Authorization failed';
                }
                break;

            case 'dropbox' :

                if ($status == 'success') {
                    $message = 'Authorization was successful';
                    $code = sanitize_text_field($_GET['code']);

                    $dropbox = new WT_Backups_Dropbox();
                    $accessTokenObject = $dropbox->GetAccessToken($code, null, 'https://cloud.checktotem.com/dropbox_answer.php');

                    $account_id = $accessTokenObject->getAccountId();

                    if (WT_Backups_Helper::is_storage_added('dropbox', $account_id)) {
                        WT_Backups_Option::setNotification('success', 'Storage has already been added');
                        break;
                    }

                    $key = 'cloud_' . WT_Backups_Helper::generateRandomString(6) . '_' . time();
                    $storages[$key] = [
                        'type' => 'cloud',
                        'dest' => 'Dropbox #' . WT_Backups_Helper::generateRandomString(6),
                        'params' => [
                            'storage' => 'dropbox',
                            'timestamp' => time(),
                            'access_token' => $accessTokenObject->getToken(),
                            'account_id' => $account_id,
                            'data' => $accessTokenObject->getData(),
                        ],
                    ];

                    WT_Backups_Option::setOptions(['storages' => $storages]);
                    WT_Backups_Option::setNotification('success', 'Storage successfully added');

                } else {
                    $message = 'Authorization failed';
                }
                break;
        }

        if (isset($message)) {
            WT_Backups_Option::setNotification($status, $message);
        }

    }

}
