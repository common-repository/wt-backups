<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

/**
 * WebTotem Database restore class.
 */
class WT_Backups_DBImporter {


    /**
     * __construct - Make connection
     *
     * @return @self
     */
    function __construct($storage, &$manifest, &$logger, $splitting) {

        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();
        $this->splitting = $splitting;
        $this->storage = $storage;
        $this->logger = &$logger;
        $this->manifest = &$manifest;
        $this->tablemap = WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'htaccess' . DIRECTORY_SEPARATOR . '.table_map';

        $this->map = $this->getTableMap();
        $this->seek = &$this->map['seek'];
    }

    public function start() {

        while ($nextFile = $this->getNextFile()) {
            $name = basename($nextFile, ".sql");

            if($this->manifest->db_data->$name->hash === hash_file('crc32b', $nextFile )
                and $this->manifest->db_data->$name->file_size === filesize($nextFile)){
                $this->processFile($nextFile);
            } else {
                $this->logger->log('The file is corrupted and cannot be restored ' . $name . ".sql", 'ERROR');

                $this->seek['last_seek'] = 0;
                $this->seek['last_file'] = '...';
                wp_delete_file($nextFile);
            }
        }

    }

    private function getTableMap() {

        if (file_exists($this->tablemap)) {

            $data = json_decode($this->wp_filesystem->get_contents($this->tablemap), true);
            $this->map = $data;

        } else {

            $data = [
                'tables' => [],
                'seek' => [
                    'last_seek' => 0,
                    'last_file' => '...',
                    'last_start' => 0,
                    'total_tables' => sizeof(array_diff(scandir($this->storage), ['..', '.'])),
                    'active_plugins' => 'a:1:{i:0;s:31:"wt-backups/wt-backups.php";}'
                ]
            ];

            $this->wp_filesystem->put_contents($this->tablemap, wp_json_encode($data));

        }

        return $data;

    }

    private function getTableProgress() {

        $total_tables = $this->seek['total_tables'];
        $tables_left = sizeof(array_diff(scandir($this->storage), ['..', '.']));

        $finished_tables = ($total_tables - $tables_left) + 1;
        $percentage = number_format(($finished_tables / $total_tables) * 100, 2);

        $this->logger->progress(50 + ((number_format($percentage, 0) / 2) - 10));

        return $finished_tables . '/' . $total_tables . ' (' . $percentage . '%)';

    }

    private function queryFile(&$objFile, $filePath, $tableName, $realTableName) {

        global $wpdb;

        $seek = &$this->seek['last_seek'];
        if ($seek == 0) {
            $seek = 19;
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i;", $tableName));

            $str = __("Started restoration of %table_name% %total_tables% table", 'wt-backups');
            $str = str_replace('%table_name%', $realTableName, $str);
            $str = str_replace('%total_tables%', $this->getTableProgress(), $str);
            $this->logger->log($str, 'STEP');

        }

        $qs = "/* QUERY START */\n";
        $qe = "/* QUERY END */\n";

        $vs = "/* VALUES START */\n";
        $ve = "/* VALUES END */\n";

        $wpdb->suppress_errors();

        $wpdb->query('SET autocommit = 0;');
        $wpdb->query('SET foreign_key_checks = 0;');
        $wpdb->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
        $wpdb->query('START TRANSACTION;');

        $sqlStarted = false;

        $sql = '';
        while (!$objFile->eof()) {
            $objFile->seek($seek); $seek++;

            if ($objFile->current() == $qs) { $sqlStarted = true; continue; }
            else if ($objFile->current() == $vs || $objFile->current() == $ve) {
                continue;
            } else if ($objFile->current() == $qe) {
                $sqlStarted = false;
                break;
            }

            if ($sqlStarted == true) $sql .= rtrim($objFile->current(), "\n");
        }

//        $wpdb->query($wpdb->prepare('%1$s', $sql)); unset($sql);
        $wpdb->query($sql); unset($sql);
        $wpdb->query('COMMIT;');
        $wpdb->query('SET autocommit = 1;');
        $wpdb->query('SET foreign_key_checks = 1;');

        $str = __("Progress of %table_name%: %progress%", 'wt-backups');
        $str = str_replace('%table_name%', $realTableName, $str);

        $objFile->seek($objFile->getSize());
        $total_size = $objFile->key();
        $objFile->seek($seek);

        $progress = ($seek - 1) . '/' . $total_size . " (" . number_format(($seek - 1) / $total_size * 100, 2) . "%)";
        $str = str_replace('%progress%', $progress, $str);
        $this->logger->log($str, 'INFO');

        $wpdb->show_errors();

        if ($objFile->eof()) {
            return true;
        } else {
            return false;
        }

    }

    private function addNewTableToMap($from, $to, $file) {

        if (!array_key_exists($from, $this->map['tables'])) {
            $this->map['tables'][$from] = $to;
        }

        $this->wp_filesystem->put_contents($this->tablemap, wp_json_encode($this->map));

    }

    private function processFile($file) {

        if ($this->seek['last_seek'] == 0) {
            $this->seek['last_start'] = microtime(true);
        }

        $objFile = new \SplFileObject($file);

        $objFile->seek(17);
        $realTableName = explode('`', $objFile->current())[1];

        $objFile->seek(18);
        $tmpTableName = explode('`', $objFile->current())[1];

        $finished = $this->queryFile($objFile, $file, $tmpTableName, $realTableName);

        if ($finished && file_exists($file)) {
            $this->seek['last_seek'] = 0;
            $this->seek['last_file'] = '...';
            wp_delete_file($file);

            $totalTime = microtime(true) - intval($this->seek['last_start']);
            $totalTime = number_format($totalTime, 5);

            $str = __("Table %table_name% restoration took %time% seconds", 'wt-backups');
            $str = str_replace('%table_name%', $realTableName, $str);
            $str = str_replace('%time%', $totalTime, $str);

            $this->logger->log($str, 'SUCCESS');
            $this->seek['last_start'] = 0;
        }

        $this->addNewTableToMap($tmpTableName, $realTableName, $file);

        return true;

    }

    private function parseDomain($domain, $removeWWW = true) {

        if (substr($domain, 0, 8) == 'https://') $domain = substr($domain, 8);
        if (substr($domain, 0, 7) == 'http://') $domain = substr($domain, 7);
        if ($removeWWW === true) {
            if (substr($domain, 0, 4) == 'www.') $domain = substr($domain, 4);
        }
        $domain = untrailingslashit($domain);

        return $domain;

    }

    private function replaceTableNames($tables) {

        global $wpdb;

        $this->logger->log(__('Performing table replacement', 'wt-backups'), 'STEP');

        $wpdb->suppress_errors();
        foreach ($tables as $oldTable => $newTable) {

            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i;", $newTable));
            $wpdb->query($wpdb->prepare("ALTER TABLE %i RENAME TO %i;", $oldTable, $newTable));

            $str = __('Table %old% renamed to %new%', 'wt-backups');
            $str = str_replace('%old%', $oldTable, $str);
            $str = str_replace('%new%', $newTable, $str);
            $this->logger->log($str, 'INFO');

        }

        $wpdb->show_errors();
        $this->logger->log(__('All tables replaced', 'wt-backups'), 'SUCCESS');

    }


    public function alter_tables() {

        $this->logger->progress(98);
        $this->prepareFinalDatabase();
        $this->replaceTableNames($this->map['tables']);

    }

    private function prepareFinalDatabase() {

        global $wpdb;

        $tables = array_keys($this->map['tables']);
        $unique_prefix = explode('_', $tables[0])[0];
        $backupPrefix = $this->manifest->config->table_prefix;

        $options_table = $unique_prefix . '_' . $backupPrefix . 'options';
        if (!in_array($options_table, $tables)) {
            $tablename = false;
            for ($i = 0; $i < sizeof($tables); ++$i) {
                $table = $tables[$i];
                if (substr($table, -7) == 'options') {
                    $tablename = $table;
                    break;
                }
            }

            $options_table = $tablename;
        }

        if ($options_table != false && in_array($options_table, $tables)) {

            $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE option_name LIKE %s", $options_table, '%\_transient\_%'));

            $ssl = is_ssl() == true ? 'https://' : 'http://';
            $currentDomain = $ssl . $this->parseDomain(get_option('siteurl'), false);

            $wpdb->query($wpdb->prepare('UPDATE %i SET option_value = %s WHERE option_name = "siteurl"', $options_table, $currentDomain));

            $wpdb->query($wpdb->prepare('UPDATE %i SET option_value = %s WHERE option_name = "home"', $options_table, $currentDomain));

            // saving current authorization sessions
            $usermeta_active_table = $wpdb->base_prefix . 'usermeta';
            $session_tokens = $wpdb->get_results($wpdb->prepare('SELECT meta_value FROM %i WHERE meta_key = "session_tokens"', $usermeta_active_table));

            if ($session_tokens && sizeof($session_tokens) > 0) {
                $session_tokens = $session_tokens[0]->meta_value;

                $usermeta_table = $unique_prefix . '_' . $backupPrefix . 'usermeta';
                $wpdb->query($wpdb->prepare('UPDATE %i SET meta_value = %s WHERE meta_key = "session_tokens"', $usermeta_table, $session_tokens));
            }

        }

    }

    private function getNextFile() {

        if ($this->seek['last_file'] == '...') {

            $nextFile = false;

            $sqlFiles = array_diff(scandir($this->storage), ['..', '.']);
            $sqlFiles = array_values($sqlFiles);

            if (sizeof($sqlFiles) > 0) {
                $nextFilePath = $this->storage . DIRECTORY_SEPARATOR . $sqlFiles[0];
                return $nextFilePath;
            }

            $this->seek['last_file'] = $nextFile;
            return $nextFile;

        } else {

            return $this->seek['last_file'];

        }

    }


}

