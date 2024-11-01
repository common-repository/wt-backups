<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! defined( 'WT_BACKUPS_INIT' ) || WT_BACKUPS_INIT !== true ) {
    if ( ! headers_sent() ) {
        header( 'HTTP/1.1 403 Forbidden' );
    }
    exit( 1 );
}

/**
 * WebTotem Base class for Wordpress.
 */
class WT_Backups_Helper {

    /**
     * Returns an URL from the admin dashboard.
     *
     * @param string $url
     *    Optional trailing of the URL.
     *
     * @return string
     *    Full valid URL from the admin dashboard.
     */
    public static function adminURL( $url = '' ) {
//		if ( self::isMultiSite() and is_super_admin() ) {
//			return network_admin_url( $url );
//		}

        return admin_url( $url );
    }

    /**
     * Check whether the current site is working as a multi-site instance.
     *
     * @return bool
     *    Either TRUE or FALSE in case WordPress is being used as a multi-site instance.
     */
    public static function isMultiSite() {
        return (bool) ( function_exists( 'is_multisite' ) && is_multisite() );
    }

    /**
     * Returns the md5 hash representing the content of a file.
     *
     * @param string $file
     *    Relative path to the file.
     *
     * @return string
     *    Seven first characters in the hash of the file.
     */
    public static function fileVersion( $file = '' ) {
        return substr( md5_file( WT_BACKUPS_PLUGIN_PATH . '/' . $file ), 0, 7 );
    }

    /**
     * Returns full path to image.
     *
     * @param string $image
     *    Relative path to the file.
     *
     * @return string
     *    Full path to image.
     */
    public static function getImagePath( $image ) {
        return WT_BACKUPS_URL . '/includes/img/' . $image;
    }

    /**
     * Convert object to array.
     *
     * @param array $data
     *    Array.
     *
     * @return array
     *    Returns array.
     */
    public static function convertObjectToArray( $data ) {

        if ( ! is_array( $data ) ) {
            $data = (array) $data;
        }
        array_walk_recursive( $data, function ( &$item ) {
            if ( is_object( $item ) ) {
                $item = (array) $item;
            }
        } );

        return $data;
    }

    /**
     * Convert the file size to а human-readable format.
     *
     * @param string $bytes
     *    File size in bytes.
     * @param string $decimals
     *    The number of characters after the decimal point.
     *
     * @return string
     *   Returns the file size in a human-readable format.
     */
    public static function humanFilesize( $bytes, $decimals = 2 ) {
        $factor              = floor( ( strlen( $bytes ) - 1 ) / 3 );
        $unit_of_measurement = ( $factor > 0 ) ? substr( "KMGT", $factor - 1, 1 ) : '';

        return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . $unit_of_measurement . 'B';
    }

    /**
     * Converting the response to a readable form.
     *
     * @param string $message
     *   Message response from the API server to the request.
     *
     * @return string|bool
     *   Returns a message.
     */
    public static function messageForHuman($message) {

        $definition = $message;

        switch ($message) {
            case 'HOSTS_LIMIT_EXCEEDED':
                $definition = __('Limit of adding sites exceeded.', 'wt-backups');
                break;

            case 'USER_ALREADY_REGISTERED':
                $definition = __('A user with this email already exists.', 'wt-backups');
                break;

            case 'DUPLICATE_HOST':
                $definition = __('Duplicate host', 'wt-backups');
                break;

            case 'INVALID_DOMAIN_NAME':
                $definition = __('Invalid Domain Name', 'wt-backups');
                break;
            default:
                $definition = str_replace("_", " ", $definition);
                $definition = ucfirst(strtolower($definition));

        }
        return $definition;
    }

    /**
     * Converting a date to the appropriate format.
     *
     * @param string $date
     *   Date in any format.
     * @param string $format
     *   The format to which you want to convert the date.
     *
     * @return string
     *   Returns converted Date.
     */
    public static function dateFormatter( $date, $format = 'M j, Y \/ H:i' ) {
        if ( ! $date ) {
            return __( 'Unknown', 'wt-backups' );
        }

        $time_zone = WT_Backups_Option::getOption( 'time_zone_offset' );
        $user_time = ( $time_zone ) ? strtotime( $time_zone . 'hours', strtotime( $date ) ) : strtotime( $date );

        return date_i18n( $format, $user_time );
    }

    /**
     * Get current user language.
     *
     * @return string
     *   Returns current language in 2-letter abbreviations
     */
    public static function getLanguage() {
        $current_language = substr( get_bloginfo( 'language' ), 0, 2 );

        return ( in_array( $current_language, [ 'ru', 'en', 'pl' ] ) ) ? $current_language : 'en';
    }

    /**
     * Generate random string.
     *
     * @param int $length
     *   The required length of the string.
     *
     * @return string
     *   Returns random string.
     */
    public static function generateRandomString( $length = 10 ) {
        $characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
        $charactersLength = strlen( $characters );
        $randomString     = '';
        for ( $i = 0; $i < $length; $i ++ ) {
            $randomString .= $characters[ wp_rand( 0, $charactersLength - 1 ) ];
        }

        return $randomString;
    }

    /**
     * Encodes the less than, greater than, ampersand,double quote
     * and single quote characters. Will never double encode entities.
     *
     * @see https://developer.wordpress.org/reference/functions/esc_attr/
     *
     * @param string $text
     *     The text which is to be encoded.
     *
     * @return string
     *    The encoded text with HTML entities.
     */
    public static function escape( $text = '' ) {
        return esc_attr( $text );
    }


    /**
     * Returns a version of the given path with modified slashes.
     *
     * @param string $str
     *     The text which is to be encoded.
     *
     * @return string
     *    Returns a version of the given path with modified slashes..
     */
    public static function fixSlashes( $str ) {
        $str = str_replace( '\\\\', DIRECTORY_SEPARATOR, $str );
        $str = str_replace( '\\', DIRECTORY_SEPARATOR, $str );
        $str = str_replace( '\/', DIRECTORY_SEPARATOR, $str );
        $str = str_replace( '/', DIRECTORY_SEPARATOR, $str );

        if ( $str[ strlen( $str ) - 1 ] == DIRECTORY_SEPARATOR ) {
            $str = substr( $str, 0, - 1 );
        }

        return $str;
    }

    public static function merge_arrays( &$array1, &$array2 ) {
        for ( $i = 0; $i < sizeof( $array2 ); ++ $i ) {
            $array1[] = $array2[ $i ];
        }
    }

    /**
     * Get notifications array.
     *
     * @return array
     *   Returns notifications array.
     */
    public static function getNotifications() {

        $notifications_data = WT_Backups_Option::getNotificationsData();
        $notifications = [];

        foreach ($notifications_data as $notification) {
            switch ($notification['type']) {
                case 'error':
                    $image = 'alert-error.svg';
                    $class = 'wt_backups_alert__title_red';
                    break;

                case 'warning':
                    $image = 'alert-warning.svg';
                    $class = 'wt_backups_alert__title_yellow';
                    break;

                case 'success':
                    $image = 'alert-success.svg';
                    $class = 'wt_backups_alert__title_green';
                    break;

                case 'info':
                    $image = 'info-blue.svg';
                    $class = 'wt_backups_alert__title_blue';
                    break;
            }

            $notifications[] = [
                "text" => $notification['notice'],
                "id" => self::generateRandomString(8),
                "type" => self::getStatusText($notification['type']),
                "type_raw" => $notification['type'],
                "image" => $image,
                "class" => $class,
            ];
        }

        return $notifications;
    }

    /**
     * Get a readable status text.
     *
     * @param string $status
     *   Module or agent status.
     *
     * @return string
     *   Returns the status text in the current language.
     */
    public static function getStatusText($status) {
        $statuses = [
            'warning' => __('Warning', 'wt-backups'),
            'error' => __('Error', 'wt-backups'),
            'success' => __('Success', 'wt-backups'),
            'info' => __('Info', 'wt-backups')
        ];

        return (array_key_exists($status, $statuses)) ? $statuses[$status] : $status;
    }


    private static function scanBackupDir($path) {

        $files = [];
        foreach (new \DirectoryIterator($path) as $fileInfo) {

            if ($fileInfo->isDot()) continue;
            if ($fileInfo->isFile()) {
                if (in_array($fileInfo->getExtension(), ['zip', 'tar', 'tar.gz', 'gz', 'rar', '7zip', '7z'])) {

                    $files[] = array(
                        'filename' => $fileInfo->getFilename(),
                        'path' => $path,
                        'size' => $fileInfo->getSize()
                    );

                }
            }

        }

        return $files;

    }

    private static function getManifestFromZip($zip_path, $zip_name, &$zipper) {

        // Get manifest data
        $res = [];
        $manifest = $zipper->getZipFileContent($zip_path, 'wtb_backup_manifest.json');
        if ($manifest) {
            $list_of_elements = [];

            if(isset($manifest->list_of_elements)){
                $elements = $manifest->list_of_elements;

                if ( $elements->plugins ) {
                    $list_of_elements[] = 'plugins';
                }
                if ( $elements->themes ) {
                    $list_of_elements[] = 'themes';
                }
                if ( $elements->uploads ) {
                    $list_of_elements[] = 'uploads';
                }
                if ( $elements->others ) {
                    $list_of_elements[] = 'others';
                }
                if ( $elements->core ) {
                    $list_of_elements[] = 'core';
                }
                if($list_of_elements){
                    $list_of_elements = 'Database and ' . implode(', ', $list_of_elements);
                } elseif($manifest->files) {
                    $list_of_elements = "Database and all files and folders";
                } else {
                    $list_of_elements = "Only database";
                }
            }

            $res['name'] = $manifest->name;
            $res['db_data'] = $manifest->db_data;
            $res['zip_name'] = $zip_name;
            $res['url'] = WP_CONTENT_URL . '/webtotem-backups/backups/' . $zip_name;
            $res['date'] = $manifest->date;
            $res['files'] = $manifest->files;
            $res['list_of_elements'] = $list_of_elements;
            $res['manifest'] = $manifest->manifest;
            $res['filesize'] = self::humanFilesize(@filesize($zip_path));

            return $res;

        } else {
            return false;
        }

    }

    /**
     * Get process logs.
     *
     * @return string
     */
    public static function get_process_logs($file_name) {
        $content_as_array = file(WT_BACKUPS_STORAGE . '/' . $file_name);

        $result = '';
        foreach ($content_as_array as $string){
            if (preg_match('/\[(.*?)\]/', $string, $matches)) {
                $status = $matches[1];

                switch ($status) {
                    case 'INFO':
                        $string = '<span class="logger_item_info">' . $string . '</span>';
                        break;
                    case 'SUCCESS':
                        $string = '<span class="logger_item_success">' . $string . '</span>';
                        break;
                    case 'STEP':
                        $string = '<span class="logger_item_step">' . $string . '</span>';
                        break;
                    case 'ERROR':
                        $string = '<span class="logger_item_error">' . $string . '</span>';
                        break;
                    case 'END-CODE':
                    case 'END':
                        $string = '<span class="logger_item_end"><strong>' . $string . '</strong></span>';
                        break;
                }
            }
            $result .= $string;
        }
        return $result;
    }

    /**
     * Check zip exist.
     *
     * @return boolean
     */
    public static function check_zip_exist($name) {
        $filepath = trailingslashit( WP_CONTENT_DIR ) . '/webtotem-backups/backups/' . str_replace('.zip', '', $name)  . '.zip';
        return file_exists($filepath);
    }

    public static function return_bytes($val) {

        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last)
        {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    public static function max_file_upload_in_bytes() {
        if(function_exists('ini_get')){
            //select maximum upload size
            $max_upload = self::return_bytes(ini_get('upload_max_filesize'));
            //select post limit
            $max_post = self::return_bytes(ini_get('post_max_size'));
            //select memory limit
            $memory_limit = self::return_bytes(ini_get('memory_limit'));
            // return the smallest of them, this defines the real limit
            return min($max_upload, $max_post, $memory_limit);
        }
        return false;
    }

    public static function getAvailableBackups() {

        $zipper = new WT_Backups_Zipper();

        // Scan for manifests
        $manifests = [];
        $backups = [];

        if (file_exists(WT_BACKUPS_STORAGE)) {
            $backups = self::scanBackupDir(WT_BACKUPS_STORAGE);
        }

        for ($i = 0; $i < sizeof($backups); ++$i) {

            $backup = $backups[$i];
            $manifest = self::getManifestFromZip($backup['path'] . '/' . $backup['filename'], $backup['filename'], $zipper);
            if ($manifest) {
                $manifest['zip_name'] = $backup['filename'];
                $manifests[] = $manifest;
            }

        }

        $helper = new WT_Backups_Helper();
        $backups = $helper->sort($manifests);
        $backup_settings = json_decode(WT_Backups_Option::getOption('backup_settings'), true) ?: [];

        if(array_key_exists('limit_backups', $backup_settings) and $backup_settings['limit_backups']){
            $backups = self::delete_outdated($backups, $backup_settings);
        }

        return $backups;
    }
    public static function getBackupInfo($filename) {
        $zipper = new WT_Backups_Zipper();
        return self::getManifestFromZip(WT_BACKUPS_STORAGE . '/' . $filename, $filename, $zipper);
    }


    public static function get_mime($file) {
        if (function_exists("finfo_file")) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE); // Return MIME type a la the 'mimetype' extension
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            return $mime;
        } else if (function_exists("mime_content_type")) {
            return mime_content_type($file);
        } else if (!stristr(ini_get("disable_functions"), "shell_exec")) {
            $file = escapeshellarg($file);
            $mime = shell_exec("file -bi " . $file);
            return $mime;
        } else {
            return false;
        }
    }

  /**
   * Base WordPress Filesystem class which Filesystem implementations extend.
   *
   * @return object|bool
   *   Instance of Filesystem class.
   */
  public static function wpFileSystem() {
    global $wp_filesystem;

    if ( empty( $wp_filesystem ) ) {
      require_once( ABSPATH . '/wp-admin/includes/file.php' );
      WP_Filesystem();
    }

    if ( empty( $wp_filesystem ) ) {
      WebTotemOption::setNotification('error', _('WP FileSystem path error'));
      return FALSE;
    }

    return $wp_filesystem;
  }

    /**
     * Deleting old backups..
     *
     * @param array $backups
     *   An array of backups.
     * @param array $backup_settings
     *   Settings.
     *
     * @return array
     *   Returns the modified backup array.
     */
    public static function delete_outdated($backups, $backup_settings) {

        $i = 1;
        foreach ($backups as $key => $backup){
            if($i > $backup_settings['limit_backups']){
                $zippath = self::fixSlashes(WT_BACKUPS_STORAGE) . DIRECTORY_SEPARATOR . $backup['zip_name'];
                wp_delete_file($zippath);
                unset($backups[$key]);
            }
            $i++;
        }
        return $backups;
    }

    public function sort($array) {
        usort($array, array($this, 'compare'));
        return $array;
    }

    protected function compare($a, $b) {
        $valueA = $a['date'];
        $valueB = $b['date'];
        return $valueB <=> $valueA;
    }

    public static function file_name_exist($file_name) {
        $file_path = self::fixSlashes(WT_BACKUPS_STORAGE) . DIRECTORY_SEPARATOR . $file_name;
        return file_exists($file_path);
    }

    public static function is_storage_added ($storage_name, $account_id) {
        $backup_storages = json_decode(WT_Backups_Option::getOption( 'storages' ), true) ?: [];
        foreach ($backup_storages as $storage){
            if($storage['type'] == 'cloud'){
                if($storage['params']['storage'] == $storage_name){
                    if($storage['params']['account_id'] == $account_id){
                       return true;
                    }
                }
            }
        }

        return false;
    }
}
