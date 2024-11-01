<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

/**
 * WebTotem Database backup class.
 */
class WT_Backups_Database {

    /**
     * Private local variables
     */
    private $total_tables = 0;
    private $recipes = [];
    private $tables_by_size = [];
    public $total_queries = 0;
    public $total_rows = 0;
    public $total_size = 0;
    public $files = [];
    public $wp_filesystem;

    /**
     * __construct - Initialization and logger resolver
     *
     * @return self
     */
    function __construct($storage, &$logger) {

        global $wpdb;

        /**
         * Logger
         */
        $this->logger = &$logger;

        /**
         * Storage directory
         */
        // $this->storage = trailingslashit(__DIR__) . 'data';
        $this->storage = $storage;

        /**
         * Percentage escape to replace
         * This way we know what the randomized string is
         */
        $this->percentage = trim($wpdb->prepare('%s', '%'), "'");

        /**
         * Max rows to pass each query
         */
        $this->max_rows = WT_BACKUPS_DB_MAX_ROWS_PER_QUERY;

        $this->table_prefix = time();
        $this->init_start = microtime(true);
        $this->logger->log("Memory usage after initialization: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB", 'INFO');

        $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();

    }

    /**
     * export - Export initializer
     *
     * @return filename/filenames
     */
    public function export($progress_data) {

        // Table names
        $this->get_table_names_and_sizes();
        $this->logger->log("Scan found $this->total_tables tables ($this->total_rows rows), estimated total size: $this->total_size MB.", 'INFO');
        $this->logger->log("Memory usage after getting table names: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB ", 'INFO');

        // Recipes
        $this->logger->log("Getting table recipes...", 'INFO');
        $this->table_recipes();
        $this->logger->log("Table recipes have been exported.", 'INFO');
        $this->logger->log("Memory usage after loading recipes: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB ", 'INFO');

        // Save Recipes
        $this->logger->log("Saving recipes...", 'INFO');
        $this->save_recipes();
        $this->logger->log("Recipes saved.", 'INFO');
        $this->logger->log("Memory usage after recipe off-load: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB", 'INFO');

        // Tables data
        $this->logger->log("Exporting table data...", 'INFO');
        $this->get_tables_data($progress_data);
        $this->logger->log("Table data exported.", 'INFO');
        $this->logger->log("Memory usage after data export: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB", 'INFO');

        $end = number_format(microtime(true) - $this->init_start, 4);
        $this->logger->log("Entire process took: $end s", 'INFO');

    }

    /**
     * get_table_names_and_sizes - Gets table names and sizes
     *
     * @return {array} associative array table_name => [size => its size in MB, rows => rows count]
     */
    private function get_table_names_and_sizes() {

        global $wpdb;
        $tables = $wpdb->get_results('SHOW TABLES');


        foreach ($tables as $table_index => $table_object) {
            foreach ($table_object as $database_name => $table_name) {

                $results = $wpdb->get_results($wpdb->prepare("SELECT table_name AS `table`, round(((data_length + index_length) / 1024 / 1024), 2) AS `size`, (SELECT COUNT(*) FROM %i) AS `rows` FROM information_schema.TABLES WHERE table_schema = %s AND table_name = %s", $table_name, DB_NAME, $table_name));

                if (!is_object($results[0])) {
                    $this->logger->log("Could not get info about: $table_name (#01)", 'INFO');
                    continue;
                }

                $table_name_returned = trim($results[0]->table);
                if ($table_name != $table_name_returned || strlen(trim($table_name)) <= 0) {
                    $this->logger->log("Could not get info about: $table_name (#02)", 'INFO');
                    continue;
                }

                $this->tables_by_size[$table_name_returned] =[
                    'size' => floatval($results[0]->size),
                    'rows' => intval($results[0]->rows)
                ];

                $this->total_size += floatval($results[0]->size);
                $this->total_rows += intval($results[0]->rows);
                $this->total_tables++;

            }
        }

        return $this->tables_by_size;

    }

    /**
     * table_recipes - Gets CREATION recipe of each table
     *
     * @return {array} - Creation recipes for each table_name => recipe
     */
    private function table_recipes() {

        global $wpdb;
        foreach ($this->tables_by_size as $table_name => $table_object) {

            $result = $wpdb->get_results($wpdb->prepare("SHOW CREATE TABLE %i", $table_name));
            foreach ($result as $index => $result_object) {
                foreach ($result_object as $column_name => $column_value) {

                    if ($column_value == $table_name) continue;
                    else {

                        $column_value = str_replace("`" . $table_name . "`", "`" . $this->table_prefix . '_' . $table_name . "`", $column_value);

                        $recipe = 'CREATE TABLE IF NOT EXISTS ';
                        $recipe .= substr($column_value, 13);

                        $this->recipes[$table_name] = $recipe;

                    }

                }
            }

        }

        return $this->recipes;

    }

    /**
     * save_recipes - Save recipes and off-load the memory
     *
     * @return {void}
     */
    private function save_recipes() {

        $time_prefix = $this->table_prefix;
        foreach ($this->recipes as $table_name => $table_recipe) {

            if(strpos($table_name,'wt_backups_settings')){
                wp_delete_file($this->file_name($table_name));
                continue;
            }

            $this->total_queries += 4 + 3;
            $recipe = "/* QUERY START */\n";
            $recipe .= "SET foreign_key_checks = 0;\n";
            $recipe .= "/* QUERY END */\n\n";

            $recipe .= "/* QUERY START */\n";
            $recipe .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
            $recipe .= "/* QUERY END */\n\n";

            $recipe .= "/* QUERY START */\n";
            $recipe .= "SET time_zone = '+00:00';\n";
            $recipe .= "/* QUERY END */\n\n";

            $recipe .= "/* QUERY START */\n";
            $recipe .= "SET NAMES 'utf8';\n";
            $recipe .= "/* QUERY END */\n\n";

            $recipe .= "/* CUSTOM VARS START */\n";
            $recipe .= "/* REAL_TABLE_NAME: `$table_name`; */\n";
            $recipe .= "/* PRE_TABLE_NAME: `$time_prefix" . "_" . "$table_name`; */\n";
            $recipe .= "/* CUSTOM VARS END */\n\n";

            $recipe .= "/* QUERY START */\n";
            $recipe .= $table_recipe . ";\n";
            $recipe .= "/* QUERY END */\n\n";

            $this->total_rows++;
            $location = $this->file_name($table_name);

            $this->wp_filesystem->put_contents( $location, $recipe, FS_CHMOD_FILE );

            $this->files[] = $location;
            unset($location);

        }

        unset($this->recipes);

    }

    /**
     * get_tables_data - Table data getter
     *
     * @return {int} Total rows count
     */
    private function get_tables_data($progress_data) {

        global $wpdb;
        $cur_rows = 0;
        WT_Backups_Option::setOptions(['db_data' => []]);
        foreach ($this->tables_by_size as $table_name => $table_object) {

            $start_time = microtime(true);
            $this->logger->log("Getting data of table: " . $table_name . " (" . number_format ($table_object['size'], 2) . " MB)", 'STEP');
            $rows = intval($table_object['rows']);

            $wpdb->query("SET foreign_key_checks = 0;");

            for ($i = 0; $i < $rows; $i += $this->max_rows) {

                $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i LIMIT %d, %d", $table_name, $i, $this->max_rows));

                $this->save_data($result, $table_name);
                unset($result);

            }

            $wpdb->query("SET foreign_key_checks = 1;");
            $cur_rows += $rows;
            $this->logger->progress(ceil($cur_rows * ($progress_data['db_progress'] / $this->total_rows)) . '/' . $progress_data['total_progress']);

            $this->logger->log("Table: " . $table_name . " cloned, operation took: " . number_format((microtime(true) - $start_time), 5) . " ms", 'INFO');

            $file = $this->file_name($table_name);
            $backup_settings = json_decode(WT_Backups_Option::getOption( 'db_data' ), true);
            $backup_settings[$table_name]['hash'] = hash_file('crc32b', $file );
            $backup_settings[$table_name]['file_size'] = filesize($file);
            WT_Backups_Option::setOptions(['db_data' => $backup_settings]);

            unset($start_time);

        }

    }

    /**
     * save_data - Saves table data/row as query
     *
     * @param  {wpdb object} &$result  Database query result
     * @param  {string} &$table_name   Table name
     */
    private function save_data(&$result, &$table_name) {

        global $wpdb;
        $columns_schema_added = false;

        $this->total_queries++;
        $query = "/* QUERY START */\n";
        $query .= "INSERT INTO `" . $this->table_prefix . "_" . $table_name . "` ";

        $file = $this->file_name($table_name);
        $content = $this->wp_filesystem->get_contents($file);

        foreach ($result as $index => $result_object) {

            $data_in_order = array();
            $format_in_order = array();
            $columns_in_order = array();

            foreach ($result_object as $column_name => $value) {

                $data_in_order[] = $value;
                $columns_in_order[] = "`$column_name`";

                if (is_numeric($value)) {

                    if (is_float($value)) $format_in_order[] = '%f';
                    else $format_in_order[] = '%d';

                } else $format_in_order[] = '%s';

            }

            if ($columns_schema_added === false) {

                $query .= "(" . implode(', ', $columns_in_order) . ") VALUES ( \n";
                $columns_schema_added = true;

            } else {

                $query = "), (\n";

            }

            $columns = sizeof($columns_in_order);
            unset($columns_in_order);

            $query .= "/* VALUES START */\n";
            for ($i = 0; $i < $columns; ++$i) {

                if ($format_in_order[$i] == '%f') {

                    $query .= floatval($data_in_order[$i]);

                } elseif ($format_in_order[$i] == '%d') {

                    $query .= intval($data_in_order[$i]);

                } else {
                    $query .= $wpdb->prepare("%s", $data_in_order[$i]);
                    $query = str_replace($this->percentage, '%', $query);
                }


                if ($i < ($columns - 1)) $query .= ",\n";
                else $query .= "\n/* VALUES END */\n";

            }

            unset($data_in_order);
            unset($format_in_order);
            unset($columns_in_order);

            $content .= $query;

        }

        $content .= ");\n/* QUERY END */\n\n";
        $this->wp_filesystem->put_contents($file, $content);

        unset($file);
    }

    /**
     * file_name - Replaces table name to file name friendly format
     *
     * @param  {string} $table_name Table name
     * @return {string}             Friendly format for file
     */
    private function file_name($table_name) {

        $friendly_name = preg_replace("/[^A-Za-z0-9_-]/", '', $table_name);
        $friendly_name = trailingslashit($this->storage) . $friendly_name . '.sql';

        return $friendly_name;

    }

}

