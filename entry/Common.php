<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! defined( 'WT_BACKUPS_INIT' ) || WT_BACKUPS_INIT !== true ) {
	if ( ! headers_sent() ) {
		header( 'HTTP/1.1 403 Forbidden' );
	}
	die( "Protected By WebTotem! Not plugin init" );
}

/**
 * Directory for storing backups.
 */
if ( ! defined( 'WT_BACKUPS_STORAGE' ) ) {
    define( 'WT_BACKUPS_STORAGE', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'webtotem-backups' . DIRECTORY_SEPARATOR . 'backups' );
}

if ( ! defined( 'WT_BACKUPS_CONTENT_DIR' ) ) {
    define( 'WT_BACKUPS_CONTENT_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'webtotem-backups' );
}

if ( defined( 'WT_BACKUPS' ) ) {

	if ( ! defined( 'WT_BACKUPS_DB_MAX_ROWS_PER_QUERY' ) ) {

		$db_queries = WT_Backups_Option::getOption( 'db_queries' );
		if ( is_numeric( $db_queries ) ) {
			$db_queries = intval( $db_queries );

			if ( $db_queries > 15000 || $db_queries < 15 ) {
				$db_queries = 2000;
			}
		}

		if ( ! isset( $db_queries ) || is_null( $db_queries ) || ! is_numeric( $db_queries ) ) {
			$db_queries = 2000;
		}

		define( 'WT_BACKUPS_DB_MAX_ROWS_PER_QUERY', $db_queries );
	}


	if (!defined('WT_BACKUPS_MAX_SEARCH_REPLACE_PAGE')) {
		$db_sr_max_page = WT_Backups_Option::getOption( 'db_sr_max_page' );
		if (is_numeric($db_sr_max_page)) {
			$db_sr_max_page = intval($db_sr_max_page);

			if ($db_sr_max_page > 30000 || $db_sr_max_page < 10) {
				$db_sr_max_page = 2000;
			}
		}

		if (!isset($db_sr_max_page) || is_null($db_sr_max_page) || !is_numeric($db_sr_max_page)) {
			$db_sr_max_page = 2000;
		}

		define('WT_BACKUPS_MAX_SEARCH_REPLACE_PAGE', $db_sr_max_page);
	}


    add_filter( 'wp_kses_allowed_html', 'wt_backups_kses_allowed_html', 0, 2 );
    function wt_backups_kses_allowed_html( $tags, $context ) {
        $allowedposttags = array(
            'a'          => array(
                'href'     => true,
                'rel'      => true,
                'rev'      => true,
                'name'     => true,
                'target'   => true,
            ),
            'b'          => array(),
            'br'         => array(),
            'button'     => array(
                'disabled' => true,
                'name'     => true,
                'type'     => true,
                'value'    => true,
            ),
            'col'        => array(
                'align'   => true,
                'char'    => true,
                'charoff' => true,
                'span'    => true,
                'valign'  => true,
                'width'   => true,
            ),
            'div'        => array(
                'align' => true,
            ),
            'form' => array(),
            'figcaption' => array(
                'align' => true,
            ),
            'footer'     => array(
                'align' => true,
            ),
            'h1'         => array(
                'align' => true,
            ),
            'h2'         => array(
                'align' => true,
            ),
            'h3'         => array(
                'align' => true,
            ),
            'h4'         => array(
                'align' => true,
            ),
            'h5'         => array(
                'align' => true,
            ),
            'h6'         => array(
                'align' => true,
            ),
            'header'     => array(
                'align' => true,
            ),
            'hr'         => array(
                'align'   => true,
                'noshade' => true,
                'size'    => true,
                'width'   => true,
            ),
            'i'          => array(),
            'input'=> array(
                'type' => true,
                'name' => true,
                'value' => true,
                'placeholder' => true,
            ),
            'img'        => array(
                'alt'      => true,
                'align'    => true,
                'border'   => true,
                'height'   => true,
                'hspace'   => true,
                'loading'  => true,
                'longdesc' => true,
                'vspace'   => true,
                'src'      => true,
                'usemap'   => true,
                'width'    => true,
            ),
            'label'      => array(
                'for' => true,
            ),
            'li'         => array(
                'align' => true,
                'value' => true,
            ),
            'main'       => array(
                'align' => true,
            ),
            'nav'        => array(
                'align' => true,
            ),
            'p'          => array(
                'align' => true,
            ),
            'span'       => array(
                'align' => true,
            ),
            'section'    => array(
                'align' => true,
            ),
            'select'=> array(
                'value' => true,
            ),
            'strong'     => array(
            ),
            'table'      => array(
                'align'       => true,
                'bgcolor'     => true,
                'border'      => true,
                'cellpadding' => true,
                'cellspacing' => true,
                'rules'       => true,
                'summary'     => true,
                'width'       => true,
            ),
            'tbody'      => array(
                'align'   => true,
                'char'    => true,
                'charoff' => true,
                'valign'  => true,
            ),
            'td'         => array(
                'abbr'    => true,
                'align'   => true,
                'axis'    => true,
                'bgcolor' => true,
                'char'    => true,
                'charoff' => true,
                'colspan' => true,
                'headers' => true,
                'height'  => true,
                'nowrap'  => true,
                'rowspan' => true,
                'scope'   => true,
                'valign'  => true,
                'width'   => true,
            ),
            'textarea'   => array(
                'cols'     => true,
                'rows'     => true,
                'disabled' => true,
                'name'     => true,
                'readonly' => true,
            ),
            'tfoot'      => array(
                'align'   => true,
                'char'    => true,
                'charoff' => true,
                'valign'  => true,
            ),
            'th'         => array(
                'abbr'    => true,
                'align'   => true,
                'axis'    => true,
                'bgcolor' => true,
                'char'    => true,
                'charoff' => true,
                'colspan' => true,
                'headers' => true,
                'height'  => true,
                'nowrap'  => true,
                'rowspan' => true,
                'scope'   => true,
                'valign'  => true,
                'width'   => true,
            ),
            'thead'      => array(
                'align'   => true,
                'char'    => true,
                'charoff' => true,
                'valign'  => true,
            ),
            'title'      => array(
            ),
            'tr'         => array(
                'align'   => true,
                'bgcolor' => true,
                'char'    => true,
                'charoff' => true,
                'valign'  => true,
            ),
            'ul'         => array(
                'type' => true,
            ),
            'ol'         => array(
                'start'    => true,
                'type'     => true,
                'reversed' => true,
            ),
            'option'         => array(
                'selected'    => true,
                'type'     => true,
                'value' => true,
            ),
        );

        $allowedposttags = array_map( 'wt_backups_add_global_attributes', $allowedposttags );
        if ( $context === 'wt_backups' ) {
            return $allowedposttags;
        }
        return $tags;
    }

    if(isset($_GET['page'])){
        $_page = sanitize_text_field( $_GET['page'] );
        if(strpos($_page, 'wt_backups') === 0) {
            add_action('admin_enqueue_scripts', 'WT_Backups_Interface::enqueueScripts', 1);
        }
    }

    function wt_backups_add_global_attributes( $value ) {
        $global_attributes = array(
            'aria-describedby' => true,
            'aria-details'     => true,
            'aria-label'       => true,
            'aria-labelledby'  => true,
            'aria-hidden'      => true,
            'class'            => true,
            'data-*'           => true,
            'dir'              => true,
            'id'               => true,
            'lang'             => true,
            'style'            => true,
            'title'            => true,
            'role'             => true,
            'xml:lang'         => true,
        );

        if ( true === $value ) {
            $value = array();
        }

        if ( is_array( $value ) ) {
            return array_merge( $value, $global_attributes );
        }

        return $value;
    }

    /** Execute pre-checks before every page */
    add_action('init', 'WT_Backups_Interface::startupChecks');

	/** Attach HTTP request handlers for the AJAX requests */
    add_action( 'wp_ajax_wt_backups_open_popup', 'wt_backups_open_popup_ajax' );
    add_action( 'wp_ajax_wt_backups_restore_page', 'wt_backups_restore_page_ajax' );
    add_action( 'wp_ajax_wt_backups_delete_backup', 'wt_backups_delete_backup_ajax' );
    add_action( 'wp_ajax_wt_backups_progress_checker', 'wt_backups_progress_checker_ajax' );
    add_action( 'wp_ajax_wt_backups_next_page', 'wt_backups_next_page_ajax' );
    add_action( 'wp_ajax_wt_backups_backup_checking', 'wt_backups_backup_checking_ajax' );
    add_action( 'wp_ajax_wt_backups_backup', 'wt_backups_backup_ajax' );
    add_action( 'wp_ajax_wt_backups_restore_checking', 'wt_backups_restore_checking_ajax' );
    add_action( 'wp_ajax_wt_backups_restore', 'wt_backups_restore_ajax' );

    add_action( 'wp_ajax_wt_backups_activation', 'wt_backups_activation_ajax' );
    add_action( 'wp_ajax_wt_backups_check_folder_path', 'wt_backups_check_folder_path_ajax' );
    add_action( 'wp_ajax_wt_backups_save_backup_settings', 'wt_backups_save_backup_settings_ajax' );
    add_action( 'wp_ajax_wt_backups_check_backup_settings', 'wt_backups_check_backup_settings_ajax' );
    add_action( 'wp_ajax_wt_backups_save_storage', 'wt_backups_save_storage_ajax' );
    add_action( 'wp_ajax_wt_backups_check_ftp', 'wt_backups_check_ftp_ajax' );
    add_action( 'wp_ajax_wt_backups_remove_storage', 'wt_backups_remove_storage_ajax' );
    add_action( 'wp_ajax_wt_backups_upload_backup', 'wt_backups_upload_backup_ajax' );
    add_action( 'wp_ajax_wt_backups_add_cloud_storage', 'wt_backups_add_cloud_storage_ajax' );
    add_action( 'wp_ajax_wt_backups_check_zip_exist', 'wt_backups_check_zip_exist_ajax' );

	/** Cron */
	if ( defined( 'WT_BACKUPS' ) ) {
		$backup_settings = json_decode(WT_Backups_Option::getOption('backup_settings'), true) ?: [];

		if(array_key_exists('enable_scheduled_backup', $backup_settings) and $backup_settings['enable_scheduled_backup']){

			// Start creating a backup.
			function wt_backups_CreateBackup() {
				$ajax = new WT_Backups_Ajax();
				$ajax->backup(true);
			}

			// Registering an event.
			add_action( 'wp', 'wt_backups_step_cron' );
			function wt_backups_step_cron() {
				if( ! wp_next_scheduled( 'wt_backups_init_cron' ) ) {
					$backup_settings = json_decode(WT_Backups_Option::getOption('backup_settings'), true);
					$time = strtotime(gmdate('Y-m-d') . ' ' . $backup_settings['time']);
					wp_schedule_event( $time, 'daily', 'wt_backups_init_cron' );
				}
			}

				// Linking the function to the cron event/task.
			add_action( 'wt_backups_init_cron', 'wt_backups_CreateBackup' );
		}
	}

    /**
	 * List an associative array with the sub-pages of this plugin.
	 *
	 * @return array List of sub-pages of this plugin.
	 */
	function wt_backups_pages() {

		$slug = 'wt_backups';

		$pages['wt_backups_create_backup'] = [ 'title' => 'Create backup', 'slug' => $slug];
        $pages['wt_backups_add_storage'] = [ 'title' => 'Add storage', 'slug' => $slug];
        $pages['wt_backups_support'] = [ 'title' => 'Support', 'slug' => $slug];
        $pages['wt_backups_settings'] = [ 'title' => 'Settings', 'slug' => $slug];

		return $pages;
	}

	if ( function_exists( 'add_action' ) ) {
		/**
		 * Display extension menu and submenu items in the correct interface.
		 *
		 * @return void
		 */
		function wt_backups_add_menu() {

			add_menu_page(
				__( 'WT backups', 'wt-backups' ),
				__( 'WT backups', 'wt-backups' ),
				'manage_options',
				'wt_backups',
				'wt_backups_dashboard_page',
				WT_Backups_Helper::getImagePath( 'logo_17x17_w.png' )
			);

            if(WT_Backups_Option::isActivated()){
                $pages = wt_backups_pages();
                foreach ($pages as $sub_page_function => $sub_page) {
                    add_submenu_page(
                        $sub_page['slug'],
                        $sub_page['title'],
                        $sub_page['title'],
                        'manage_options',
                        $sub_page_function,
                        $sub_page_function . '_page'
                    );
                }

            } else {
                add_submenu_page(
                    'wt_backups',
                    __('Activation', 'wt-backups'),
                    __('Activation', 'wt-backups'),
                    'manage_options',
                    'wt_backups_activation',
                    'wt_backups_activation_page'
                );
            }

		}

		add_action( 'admin_menu', 'wt_backups_add_menu' );
	}

}
