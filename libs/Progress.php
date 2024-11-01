<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}

/**
 * WebTotem Progress class.
 */

class WT_Backups_Progress {

	public function __construct($name, $files = 0, $bytes = 0, $cron = false, $reset = true) {

        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
		if (!file_exists(WT_BACKUPS_STORAGE)) {
            wp_mkdir_p(WT_BACKUPS_STORAGE);
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.html');
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.php');
		}

		$this->name = $name;
		$this->date = gmdate('Y-m-d H:i:s');
		$this->millis = microtime(true);
		//$this->cron = $cron;
		$this->logfilename = substr($name, 0, -4) . '.log';
		$this->latest = WT_BACKUPS_STORAGE . '/latest.log';
		$this->latest_progress = WT_BACKUPS_STORAGE . '/latest_progress.log';
		$this->files = $files;
		$this->bytes = $bytes;
		$this->total_queries = 1;

		if ($reset == true) {
			if (file_exists($this->latest))  wp_delete_file($this->latest);
			if (file_exists($this->latest_progress))  wp_delete_file($this->latest_progress);
            $this->wp_filesystem->put_contents($this->latest_progress, '0/100');
		}

	}

	public function createManifest($cron) {

		global $table_prefix;

		$backupSettings = $cron ? json_decode( WT_Backups_Option::getOption( 'backup_settings' ), true ) :  WT_Backups_Option::getSessionOption( 'backup_settings' );
        $folders = $backupSettings['folders'];

        $db_data = json_decode(WT_Backups_Option::getOption( 'db_data' ), true);


		$manifest = [
			'name'          => $this->name,
			'date'          => $this->date,
			'files'         => $this->files,
			'bytes'         => $this->bytes,
			'total_queries' => $this->total_queries,
			'manifest'      => gmdate('Y-m-d H:i:s' ),
			'millis_start'  => $this->millis,
			'millis_end'    => microtime( true ),
			'version'       => WT_BACKUPS_VERSION,
			'domain'        => wp_parse_url( home_url() )['host'],
			'dbdomain'      => get_option( 'siteurl' ),
            'uid'           => get_current_user_id(),
            'db_data'       => $db_data,

			'list_of_elements' => [
				'database' => WT_Backups_Option::getOption( 'backup_database' ),
				'plugins'  => $folders['plugins'],
				'themes'   => $folders['themes'],
				'uploads'  => $folders['uploads'],
				'others'   => $folders['others'],
				'core'     => $folders['core'],
			],

			'config' => [
//				'ABSPATH'          => ABSPATH,
//				'DB_NAME'          => DB_NAME,
//				'DB_USER'          => DB_USER,
//				'DB_PASSWORD'      => DB_PASSWORD,
//				'DB_HOST'          => DB_HOST,
//				'DB_CHARSET'       => DB_CHARSET,
//				'DB_COLLATE'       => DB_COLLATE,
//				'AUTH_KEY'         => AUTH_KEY,
//				'SECURE_AUTH_KEY'  => SECURE_AUTH_KEY,
//				'LOGGED_IN_KEY'    => LOGGED_IN_KEY,
//				'NONCE_KEY'        => NONCE_KEY,
//				'AUTH_SALT'        => AUTH_SALT,
//				'SECURE_AUTH_SALT' => SECURE_AUTH_SALT,
//				'LOGGED_IN_SALT'   => LOGGED_IN_SALT,
//				'NONCE_SALT'       => NONCE_SALT,
//				'WP_DEBUG_LOG'     => WP_DEBUG_LOG,
//				'WP_CONTENT_URL'   => WP_CONTENT_URL,
//				'WP_CONTENT_DIR'   => trailingslashit( WP_CONTENT_DIR ),
				'table_prefix'     => $table_prefix
			]
		];

		return wp_json_encode( $manifest );

	}

	public function start($muted = false) {

		$this->muted = $muted;

	}

	public function log($log = '', $level = 'INFO') {

		if (!$this->muted) {
            $content = $this->wp_filesystem->get_contents($this->latest);
            $log_string = '[' . strtoupper($level) . '] [' . gmdate('Y-m-d H:i:s') . '] ' . $log . "\n";
            $this->wp_filesystem->put_contents($this->latest, $content . $log_string);
		}

	}

	public function progress($progress = '0') {

        $this->wp_filesystem->put_contents($this->latest_progress, $progress);

	}

	public function end() { }

}

/**
 * Main File Scanner Logic
 */
class WT_Backups_RestoreProgress {

	public function __construct($reset = true) {

        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
        if (!file_exists(WT_BACKUPS_STORAGE)) {
            wp_mkdir_p(WT_BACKUPS_STORAGE);
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.html');
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.php');
        }

		$this->latest = WT_BACKUPS_STORAGE . '/latest_restore.log';
		$this->progress = WT_BACKUPS_STORAGE . '/latest_restore_progress.log';

		if ($reset == true) {
			if (file_exists($this->latest))  wp_delete_file($this->latest);
			if (file_exists($this->progress))  wp_delete_file($this->progress);
            $this->wp_filesystem->put_contents($this->progress, '0');
		}

	}

	public function start() { }


	public function progress($progress = '0') {
        $this->wp_filesystem->put_contents($this->progress, $progress);
	}

	public function log($log = '', $level = 'INFO') {
        $content = $this->wp_filesystem->get_contents($this->latest);
        $log_string = '[' . strtoupper($level) . '] [' . gmdate('Y-m-d H:i:s') . '] ' . $log . "\n";
        $this->wp_filesystem->put_contents($this->latest, $content . $log_string);
	}

	public function end() {

		return true;

	}

}


/**
 * Main File Scanner Logic
 */
class WT_Backups_CheckProgress {

	public function __construct($reset = true) {

        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
        if (!file_exists(WT_BACKUPS_STORAGE)) {
            wp_mkdir_p(WT_BACKUPS_STORAGE);
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.html');
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.php');
        }

		$this->latest = WT_BACKUPS_STORAGE . '/latest_checks.log';
		$this->progress = WT_BACKUPS_STORAGE . '/latest_checks_progress.log';

		if ($reset == true) {
			if (file_exists($this->latest))  wp_delete_file($this->latest);
			if (file_exists($this->progress))  wp_delete_file($this->progress);
            $this->wp_filesystem->put_contents($this->progress, '0');
		}

	}

	public function progress($progress = '0') {
        $this->wp_filesystem->put_contents($this->progress, $progress);
	}

	public function log($log = '', $level = 'INFO') {
        $content = $this->wp_filesystem->get_contents($this->latest);
        $log_string = '[' . strtoupper($level) . '] [' . gmdate('Y-m-d H:i:s') . '] ' . $log . "\n";
        $this->wp_filesystem->put_contents($this->latest, $content . $log_string);
	}


}



/**
 * Main File Scanner Logic
 */
class WT_Backups_CheckRestoreProgress {

    public function __construct($reset = true) {

        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
        if (!file_exists(WT_BACKUPS_STORAGE)) {
            wp_mkdir_p(WT_BACKUPS_STORAGE);
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.html');
            $this->wp_filesystem->touch(WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'index.php');
        }

        $this->latest = WT_BACKUPS_STORAGE . '/latest_restore_checks.log';
        $this->progress = WT_BACKUPS_STORAGE . '/latest_restore_checks_progress.log';

        if ($reset == true) {
            if (file_exists($this->latest))  wp_delete_file($this->latest);
            if (file_exists($this->progress))  wp_delete_file($this->progress);
            $this->wp_filesystem->put_contents($this->progress, '0');
        }

    }

    public function progress($progress = '0') {
        $this->wp_filesystem->put_contents($this->progress, $progress);
    }

    public function log($log = '', $level = 'INFO') {
        $content = $this->wp_filesystem->get_contents($this->latest);
        $log_string = '[' . strtoupper($level) . '] [' . gmdate('Y-m-d H:i:s') . '] ' . $log . "\n";
        $this->wp_filesystem->put_contents($this->latest, $content . $log_string);
    }


}
