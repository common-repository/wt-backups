<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    die("Protected By WebTotem! Not plugin init");
}

/**
 * Plugin initializer.
 *
 */
class WT_Backups_Interface extends WT_Backups_Helper
{

    /**
     * Execute pre-checks before every page.
     *
     * @return void
     */
    public static function startupChecks()
    {
        $_page = '';
        if (array_key_exists('page', $_GET)) {
            $_page = sanitize_text_field($_GET['page']);
        }

        if (strpos($_page, 'wt_backups') === 0) {

            $composer_autoload = WT_BACKUPS_PLUGIN_PATH . '/vendor/autoload.php';
            if (file_exists($composer_autoload)) {
                require_once $composer_autoload;
            }

            if (!WT_Backups_Option::isActivated() and $_page !== 'wt_backups_activation') {
                // If the plugin is not activated by the API key, then redirect to the activation page.
                wp_safe_redirect(WT_Backups_Helper::adminURL('admin.php?page=wt_backups_activation'));
                exit;
            } elseif (WT_Backups_Option::isActivated() and ($_page === 'wt_backups_activation')) {
                // If the plugin is activated by the API key, then redirect to the main page.
                wp_safe_redirect(WT_Backups_Helper::adminURL('admin.php?page=wt_backups'));
                exit;
            }
        }


    }

    /**
     * Verify the nonce of the previous page after a form submission.
     *
     * @return bool True if the nonce is valid, false otherwise.
     */
    public static function checkNonce()
    {
        if (!empty($_POST)) {
            $name = 'wt_backups_page_nonce';
            $value = sanitize_text_field($_POST[$name]);

            if (!$value || !wp_verify_nonce($value, $name)) {
                WT_Backups_Option::setNotification('error', __('The WordPress CSRF check failed. The submitted form is missing an important unique code. Go back and try again. ', 'wt-backups'));

                return false;
            }
        }

        return true;
    }

    /**
     * A safe way to add JavaScript and css files to a WordPress-managed page
     *
     * @return void
     */
    public static function enqueueScripts()
    {

        // Adding CSS files.
        wp_register_style(
            'wt_backup_toastr_css',
            WT_BACKUPS_URL . '/includes/css/toastr.min.css',
            [],
            WT_Backups_Helper::fileVersion('includes/css/toastr.min.css')
        );
        wp_enqueue_style('wt_backup_toastr_css');

        wp_register_style(
            'wt_backup_main_css',
            WT_BACKUPS_URL . '/includes/css/main.css',
            [],
            WT_Backups_Helper::fileVersion('includes/css/main.css')
        );
        wp_enqueue_style('wt_backup_main_css');

        // Adding JS files.
        wp_register_script(
            'wt_backup_toastr',
            WT_BACKUPS_URL . '/includes/js/toastr.min.js',
            [],
            WT_Backups_Helper::fileVersion('includes/js/toastr.min.js'),
            true
        );
        wp_enqueue_script('wt_backup_toastr');

        wp_register_script(
            'wt_backup_ajax_js',
            WT_BACKUPS_URL . '/includes/js/ajax.js',
            ['jquery'],
            WT_Backups_Helper::fileVersion('includes/js/ajax.js'),
            true
        );
        wp_enqueue_script('wt_backup_ajax_js');

        wp_register_script(
            'wt_backup_main_js',
            WT_BACKUPS_URL . '/includes/js/main.js',
            ['jquery'],
            WT_Backups_Helper::fileVersion('includes/js/main.js'),
            true
        );
        wp_enqueue_script('wt_backup_main_js');
    }
}
