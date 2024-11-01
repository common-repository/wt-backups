<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! defined( 'WT_BACKUPS_INIT' ) || WT_BACKUPS_INIT !== true ) {
	if ( ! headers_sent() ) {
		header( 'HTTP/1.1 403 Forbidden' );
	}
	exit( 1 );
}

/**
 * WebTotem BackUp FTP class for WordPress.
 */
class WT_Backups_FTP {

    private $conn_id;

    private $sftp;

    private $ftp;

    private $host;

    private $ftp_path;

    private $type;

	private $username;

	private $password;

	private $port;

	public $timeout = 60;

    public $progress;

    public $wp_filesystem;


	public function __construct($progress, $type, $host, $username, $password, $ftp_path, $port = 21) {

        $this->type     = $type;
        $this->host     = $host;
        $this->ftp_path = $ftp_path;
		$this->username = $username;
		$this->password = $password;
		$this->port     = $port;
		$this->progress = $progress;

        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
	}

	public function connect() {

		if($this->type == 'ftp'){

            if ($this->wp_filesystem) {
                require_once($this->wp_filesystem->abspath() . '/wp-admin/includes/class-wp-filesystem-ftpext.php');
            } else {
                return false;
            }

            $credentials = [
                'hostname' => $this->host,
                'port' => $this->port,
                'username' => $this->username,
                'password' => $this->password,
            ];

            $this->ftp = new WP_Filesystem_FTPext($credentials);

            if (!$this->ftp && $this->ftp->errors->get_error_code()) {
                $this->progress->log( __('Error connecting to the FTP server', 'wt-backups'), 'error' );
                return false;
            }

            if($this->ftp->connect()){
                return true;
            }

            $this->progress->log( sprintf(__('The connection to %s has timed out. If you entered the server address correctly, this issue is likely caused by a firewall blocking the connection. Please consider reaching out to your web hosting company for further assistance.', 'wt-backups'), 'FTP'), 'error' );

            return false;
        } elseif($this->type == 'sftp') {

            if (!function_exists('ssh2_connect')) {
                $this->progress->log( sprintf(__('The Perl ssh2 library is missing', 'wt-backups'), 'SFTP'), 'error' );
                return false;
            }

            $this->conn_id = ssh2_connect($this->host, $this->port);
            if ($this->conn_id){
                $result = ssh2_auth_password($this->conn_id, $this->username, $this->password);
            }

            if (!empty($result)) {
                $this->sftp = ssh2_sftp($this->conn_id);
                if ( $this->sftp){
                    return true;
                }
            }

            $this->progress->log( sprintf(__('The connection to %s has timed out. If you entered the server address correctly, this issue is likely caused by a firewall blocking the connection. Please consider reaching out to your web hosting company for further assistance.', 'wt-backups'), 'SFTP'), 'error' );
            return false;
        }

        return false;
	}

	public function put($local_file_path, $remote_file_path, $mode = FTP_BINARY, $resume = false) {

        $remote_file_path = trailingslashit($this->ftp_path) . $remote_file_path;

	    if($this->type == 'ftp'){

            if ($this->wp_filesystem) {
                $file_content = $this->wp_filesystem->get_contents($local_file_path);
                if (false == $file_content) {
                    $this->progress->log(__('The file could not be opened', 'wt-backups'), 'error');
                    return false;
                }

            } else {
                $this->progress->log(__('WP FileSystem path error', 'wt-backups'), 'error');
                return false;
            }

            $result = $this->ftp->put_contents($remote_file_path, $file_content, FS_CHMOD_FILE );

            if (!$result) {
                $this->progress->log( "FTP upload: error", 'info' );
                return false;
            }

            return true;
        } elseif($this->type == 'sftp') {

            return ssh2_scp_send($this->conn_id, $local_file_path, $remote_file_path, 0644);

        }
        return false;

	}

}