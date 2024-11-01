<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
/**
 * WebTotem Unzip class.
 */

class WT_Backups_Unzip {

  public function __construct($backup, &$restore_progress, $tmptime = false) {

	  // Globals
	  global $table_prefix;

	  // Backup name
	  $this->backup_name = $backup;

	  $this->splitting = true;

	  // Logger
	  $this->restore_progress = $restore_progress;

	  // Temp name
	  $this->tmptime = time();

	  // Use specified name if it is in batching mode
	  if (is_numeric($tmptime)) $this->tmptime = $tmptime;

	  $this->cleanupbefore = false;

	  // Restore start time
	  $this->start = intval(microtime(true));

	  // File amount by default 0 later we replace it with scan
	  $this->fileAmount = 0;
	  $this->recent_export_seek = 0;
	  $this->processData = [];
	  $this->conversionStats = [];


	  // Name
	  $this->tmp = untrailingslashit(ABSPATH) . DIRECTORY_SEPARATOR . 'wt-backups_' . $this->tmptime;
	  $GLOBALS['wt_backups_current_tmp_restore'] = $this->tmp;
	  $GLOBALS['wt_backups_current_tmp_restore_unique'] = $this->tmptime;

	  // Scan file
	  $this->scanFile = untrailingslashit(WT_BACKUPS_PLUGIN_PATH) . DIRECTORY_SEPARATOR . 'htaccess' . DIRECTORY_SEPARATOR . '.restore_scan_' . $this->tmptime;

	  // Save current wp-config to replace (only those required)
	  $this->DB_NAME = DB_NAME;
	  $this->DB_USER = DB_USER;
	  $this->DB_PASSWORD = DB_PASSWORD;
	  $this->DB_HOST = DB_HOST;
	  $this->DB_CHARSET = DB_CHARSET;
	  $this->DB_COLLATE = DB_COLLATE;

	  $this->AUTH_KEY = AUTH_KEY;
	  $this->SECURE_AUTH_KEY = SECURE_AUTH_KEY;
	  $this->LOGGED_IN_KEY = LOGGED_IN_KEY;
	  $this->NONCE_KEY = NONCE_KEY;
	  $this->AUTH_SALT = AUTH_SALT;
	  $this->SECURE_AUTH_SALT = SECURE_AUTH_SALT;
	  $this->LOGGED_IN_SALT = LOGGED_IN_SALT;
	  $this->NONCE_SALT = NONCE_SALT;

	  $this->ABSPATH = ABSPATH;
	  $this->WP_CONTENT_DIR = trailingslashit(WP_CONTENT_DIR);

	  $this->WP_DEBUG_LOG = WP_DEBUG_LOG;
	  $this->table_prefix = $table_prefix;

	  $this->siteurl = get_option('siteurl');
	  $this->home = get_option('home');

	  $this->src = WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . $this->backup_name;

      $this->wp_filesystem = WT_Backups_Helper::wpFileSystem();

  }

  public function replacePath($path, $sub, $content) {
	  $path .= DIRECTORY_SEPARATOR . 'wordpress' . $sub;

	  // Handle only database backup
	  if (!file_exists($path)) return;

	  $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

	  $clent = strlen($content);
	  $sublen = strlen($path);
	  $files = [];
	  $dirs = [];

	  foreach ($rii as $file) {
		  if (!$file->isDir()) {
			  $files[] = substr($file->getPathname(), $sublen);
		  } else {
			  $dirs[] = substr($file->getPathname(), $sublen);
		  }
	  }

	  for ($i = 0; $i < sizeof($dirs); ++$i) {
		  $src = $path . $dirs[$i];
		  if (strpos($src, $content) !== false) {
			  $dest = $this->WP_CONTENT_DIR . $sub . substr($dirs[$i], $clent);
		  } else {
			  $dest = $this->ABSPATH . $sub . $dirs[$i];
		  }

		  $dest = untrailingslashit($dest);
		  if (!file_exists($dest) && !is_dir($dest)) {
              $this->wp_filesystem->mkdir($dest, 0755);
		  }
	  }

	  for ($i = 0; $i < sizeof($files); ++$i) {
		  if (strpos($files[$i], 'debug.log') !== false) {
			  array_splice($files, $i, 1);

			  break;
		  }
		  if (strpos($files[$i], 'wp-config.php') !== false && $this->same_domain != true) {
			  array_splice($files, $i, 1);

			  break;
		  }
	  }

	  $max = sizeof($files);
	  for ($i = 0; $i < $max; ++$i) {
		  $src = $path . $files[$i];
		  if (strpos($src, $content) !== false) {
			  $dest = $this->WP_CONTENT_DIR . $sub . substr($files[$i], $clent);
		  } else {
			  $dest = $this->ABSPATH . $sub . $files[$i];
		  }

		  if (file_exists($src)) {
			  $fileDest = WT_Backups_Helper::fixSlashes($dest);
			  $dirDest = pathinfo($fileDest);
			  if ($dirDest['dirname']) {
				  $dirDest = $dirDest['dirname'];
				  if (!(is_dir($dirDest) && file_exists($dirDest))) {
                      $this->wp_filesystem->mkdir($dirDest, 0755);
				  }
			  }
              $this->wp_filesystem->move($src, $fileDest, true);
		  }

		  if ($i % 100 === 0) {
			  $this->restore_progress->progress(25 + intval((($i / $max) * 100) / 4));
		  }
	  }
  }

  public function replaceAll($content) {

	  $themedir = get_theme_root();
	  $tempTheme = $themedir . DIRECTORY_SEPARATOR . 'wt_backups_restoration_in_progress';
	  if (!(file_exists($tempTheme) && is_dir($tempTheme))) {
          $this->wp_filesystem->mkdir($tempTheme, 0755);
	  }

	  $visitLaterText = __('Site restoration in progress, please visit that website a bit later, thank you! :)', 'wt-backups');
	  $this->wp_filesystem->put_contents($tempTheme . DIRECTORY_SEPARATOR . 'header.php', '<?php wp_head(); show_admin_bar(true);');
	  $this->wp_filesystem->put_contents($tempTheme . DIRECTORY_SEPARATOR . 'footer.php', '<?php wp_footer(); get_footer();');
	  $this->wp_filesystem->put_contents($tempTheme . DIRECTORY_SEPARATOR . 'index.php', '<?php get_header(); wp_body_open(); ?>' . $visitLaterText);

      try {
          switch_theme( 'wt_backups_restoration_in_progress' );
      } catch (\Exception $e) {
          $this->restore_progress->log(__('Failed to change the theme of the site.', 'wt-backups'), 'info');
      }

	  $this->replacePath($this->tmp, DIRECTORY_SEPARATOR, $content);

  }

  public function cleanup() {

	  $filesToBeRemoved = [];
	  $dir = $this->tmp;

	  $themedir = get_theme_root();
	  $tempTheme = $themedir . DIRECTORY_SEPARATOR . 'wt_backups_restoration_in_progress';
	  $filesToBeRemoved[] = $tempTheme;

	  if (is_dir($dir) && file_exists($dir)) {

		  $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
		  $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

		  $this->restore_progress->log(sprintf(__('Removing %s files', 'wt-backups'), iterator_count($files)), 'INFO');
		  foreach ($files as $file) {
			  if ($file->isDir()) {
                  $this->wp_filesystem->rmdir($file->getRealPath(), true);
			  } else {
				  gc_collect_cycles();
                  wp_delete_file($file->getRealPath());
			  }
		  }

          $this->wp_filesystem->rmdir($dir, true);

	  }

	  if (file_exists($this->scanFile)) {
          wp_delete_file($this->scanFile);
	  }

	  $tblmap = WT_BACKUPS_STORAGE . DIRECTORY_SEPARATOR . 'htaccess' . DIRECTORY_SEPARATOR . '.table_map';
	  if (file_exists($tblmap)) {
          wp_delete_file($tblmap);
	  }

	  $allowedFiles = ['wp-config.php', '.htaccess'];
	  foreach (glob(untrailingslashit(ABSPATH) . DIRECTORY_SEPARATOR . 'wt-backups_??????????') as $filename) {

		  $basename = basename($filename);

		  if (is_dir($filename) && !in_array($basename, ['.', '..'])) {
			  $filesToBeRemoved[] = $filename;
		  }

	  }

	  foreach (glob(untrailingslashit(ABSPATH) . DIRECTORY_SEPARATOR . 'wp-config.??????????.php') as $filename) {

		  $basename = basename($filename);

		  if (in_array($basename, ['.', '..'])) continue;
		  if (is_file($filename) && !in_array($filename, $allowedFiles)) {
			  $filesToBeRemoved[] = $filename;
		  }

	  }

	  if (is_array($filesToBeRemoved) || is_object($filesToBeRemoved)) {
		  foreach ($filesToBeRemoved as $file) {
			  $this->rrmdir($file);
		  }
	  }

      $current_theme = WT_Backups_Option::getSessionOption('current_theme');

      if($current_theme){
          try {
              switch_theme( $current_theme );
          } catch (\Exception $e) {
              $this->restore_progress->log(__('Failed to change the theme of the site.', 'wt-backups'), 'info');
          }
      }

  }

	private function rrmdir( $dir ) {
		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( $object != "." && $object != ".." ) {
					if ( is_dir( $dir . DIRECTORY_SEPARATOR . $object ) && ! is_link( $dir . DIRECTORY_SEPARATOR . $object ) ) {
						$this->rrmdir( $dir . DIRECTORY_SEPARATOR . $object );
					} else {
                        wp_delete_file( $dir . DIRECTORY_SEPARATOR . $object );
					}
				}
			}
            $this->wp_filesystem->rmdir($dir, true);

		} else {
			if ( file_exists( $dir ) && is_file( $dir ) ) {
                wp_delete_file( $dir );
			}
		}
	}

  public function makeUnZIP() {

	  // Source
	  $src = $this->src;

	  // Extract
	  $this->zip = new WT_Backups_Zip();

	  $isOk = $this->zip->unzip_file($src, $this->tmp, $this->restore_progress);

	  if (!$isOk) {

		  // Verbose
		  $this->restore_progress->log(__('Failed to extract the files...', 'wt-backups'), 'WARN');
		  $this->cleanup();

		  return false;

	  }

	  $this->restore_progress->log(__('Files extracted...', 'wt-backups'), 'SUCCESS');
	  return true;

  }

  public function fixWPLogin(&$manifest) {

	  try {

		  global $wpdb;

		  $loginslug = false;

          $option_name = 'bwpl_slug';
          $table_name = $manifest->config->table_prefix . 'options';

		  $results = $wpdb->get_results($wpdb->prepare("SELECT option_value FROM %i WHERE option_name = %s", $table_name, $option_name));

		  if (sizeof($results) > 0) $loginslug = $results[0]->option_value;

		  if ($loginslug != false && is_string($loginslug) && strlen($loginslug) >= 1) {

			  $wploginfile = trailingslashit(ABSPATH) . 'wp-login.php';
			  $blockedloginfile = trailingslashit(ABSPATH) . $loginslug . '-wp-login.php';

			  if (file_exists($wploginfile) && !file_exists($blockedloginfile)) {
				  @copy($wploginfile, $blockedloginfile);
			  }

		  }

	  }
	  catch (\Exception $e) {}
	  catch (\Throwable $e) {}

  }

  public function makeWPConfigCopy() {

	  $this->restore_progress->log(__('Saving wp-config file...', 'wt-backups'), 'STEP');
	  $configData = $this->wp_filesystem->get_contents(ABSPATH . 'wp-config.php');
	  if ($configData && strlen($configData) > 0) {
          $this->wp_filesystem->put_contents(ABSPATH . 'wp-config.' . $this->tmptime . '.php', $configData);
		  $this->restore_progress->log(__('File wp-config saved', 'wt-backups'), 'SUCCESS');
	  } else {
		  $this->restore_progress->log(__('Could not backup/read wp-config file.', 'wt-backups'), 'WARN');
	  }

  }

  public function getCurrentManifest($first = false) {

	  if ($first == true) {
		  $this->restore_progress->log(__('Getting backup manifest...', 'wt-backups'), 'STEP');
	  }

	  $manifest = json_decode($this->wp_filesystem->get_contents($this->tmp . DIRECTORY_SEPARATOR . 'wtb_backup_manifest.json'));

	  if ($first == true) {
		  $this->restore_progress->log(__('Manifest loaded', 'wt-backups'), 'SUCCESS');
	  }

	  return $manifest;

  }

  public function restoreBackupFromFiles($manifest) {

	  $this->same_domain = untrailingslashit($manifest->dbdomain) == untrailingslashit($this->siteurl) ? true : false;
	  $this->restore_progress->log(__('Restoring files (this process may take a while)...', 'wt-backups'), 'STEP');
	  $pathtowp = DIRECTORY_SEPARATOR . 'wp-content';
	  if (isset($manifest->config->WP_CONTENT_DIR) && isset($manifest->config->ABSPATH)) {
		  $absi = $manifest->config->ABSPATH;
		  $cotsi = $manifest->config->WP_CONTENT_DIR;
		  if (strlen($absi) <= strlen($cotsi) && substr($cotsi, 0, strlen($absi)) == $absi) {
			  $pathtowp = substr($cotsi, strlen($absi));
		  } else {
			  $pathtowp = $cotsi;
		  }
	  }

	  $this->replaceAll($pathtowp);
	  $this->restore_progress->log(__('All files restored successfully.', 'wt-backups'), 'SUCCESS');

  }

  public function alter_tables(&$manifest) {

	  if ($this->dbImporter == null) {
		  $storage = $this->tmp . DIRECTORY_SEPARATOR . 'db_tables';
		  $importer = new WT_Backups_DBImporter($storage, $manifest, $this->restore_progress, $this->splitting);
		  $importer->alter_tables();
	  } else {
		  $this->dbImporter->alter_tables();
	  }

	  $this->restore_progress->log(__('Database restored', 'wt-backups'), 'SUCCESS');

  }

  public function restoreDatabase(&$manifest) {

	  $storage = $this->tmp . DIRECTORY_SEPARATOR . 'db_tables';
	  $this->dbImporter = new WT_Backups_DBImporter($storage, $manifest, $this->restore_progress, $this->splitting);
	  $this->dbImporter->start();

	  return true;

  }

  public function cleanupCurrentThemesAndPlugins() {

	  if ($this->cleanupbefore == true) {

		  $this->restore_progress->log(__('Moving current themes and plugins.', 'wt-backups'), 'STEP');

		  $plugins_path = WT_Backups_Helper::fixSlashes(WP_PLUGIN_DIR);
		  $themes_path = WT_Backups_Helper::fixSlashes(dirname(get_template_directory()));

		  $plugins = [];
		  if (file_exists($plugins_path)) {
			  $plugins = array_values(array_diff(scandir($plugins_path), ['..', '.', WT_BACKUPS_PLUGIN_FOLDER]));
		  }

		  $themes = [];
		  if (file_exists($themes_path)) {
			  $themes = array_values(array_diff(scandir($themes_path), ['..', '.', WT_BACKUPS_PLUGIN_FOLDER]));
		  }

		  $destination = WT_BACKUPS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'clean-ups';
		  $destination_unique = $destination . DIRECTORY_SEPARATOR . 'restoration_' . intval($this->start);

		  $destination_plugins = $destination_unique . DIRECTORY_SEPARATOR . 'plugins';
		  $destination_themes = $destination_unique . DIRECTORY_SEPARATOR . 'themes';

		  if (!file_exists($destination)) $this->wp_filesystem->mkdir($destination, 0775);
		  if (!file_exists($destination_unique)) $this->wp_filesystem->mkdir($destination_unique, 0775);
		  if (!file_exists($destination_plugins)) $this->wp_filesystem->mkdir($destination_plugins, 0775);
		  if (!file_exists($destination_themes)) $this->wp_filesystem->mkdir($destination_themes, 0775);

		  for ($i = 0; $i < sizeof($plugins); ++$i) {
			  $pluginPath = trailingslashit($plugins_path) . $plugins[$i];
			  $destPath = trailingslashit($destination_plugins) . $plugins[$i];
              $this->wp_filesystem->move($pluginPath, $destPath, true);
		  }

		  for ($i = 0; $i < sizeof($themes); ++$i) {
			  $themePath = trailingslashit($themes_path) . $themes[$i];
			  $destPath = trailingslashit($destination_themes) . $themes[$i];
              $this->wp_filesystem->move($themePath, $destPath, true);
		  }

		  $this->restore_progress->log(__('Themes and plugins moved to safe directory.', 'wt-backups'), 'SUCCESS');

	  }

	  return true;

  }

  public function rescueCleanedThemesAndPlugins() {

	  if ($this->cleanupbefore == true) {

		  $this->restore_progress->log(__('Restoring moved themes and plugins.', 'wt-backups'), 'INFO');

		  $plugins_path = WT_Backups_Helper::fixSlashes(WP_PLUGIN_DIR);
		  $themes_path = WT_Backups_Helper::fixSlashes(dirname(get_template_directory()));

		  $destination = WT_BACKUPS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'clean-ups';
		  $destination_unique = $destination . DIRECTORY_SEPARATOR . 'restoration_' . intval($this->start);

		  $destination_plugins = $destination_unique . DIRECTORY_SEPARATOR . 'plugins';
		  $destination_themes = $destination_unique . DIRECTORY_SEPARATOR . 'themes';

		  $plugins = [];
		  if (file_exists($destination_plugins)) {
			  $plugins = array_values(array_diff(scandir($destination_plugins), ['..', '.']));
		  }

		  $themes = [];
		  if (file_exists($destination_themes)) {
			  $themes = array_values(array_diff(scandir($destination_themes), ['..', '.']));
		  }

		  if (!file_exists($plugins_path)) $this->wp_filesystem->mkdir($plugins_path, 0775);
		  if (!file_exists($themes_path)) $this->wp_filesystem->mkdir($themes_path, 0775);

		  for ($i = 0; $i < sizeof($plugins); ++$i) {
			  $pluginPath = trailingslashit($destination_plugins) . $plugins[$i];
			  $destPath = trailingslashit($plugins_path) . $plugins[$i];
              $this->wp_filesystem->move($pluginPath, $destPath, true);
		  }

		  for ($i = 0; $i < sizeof($themes); ++$i) {
			  $themePath = trailingslashit($destination_themes) . $themes[$i];
			  $destPath = trailingslashit($themes_path) . $themes[$i];
              $this->wp_filesystem->move($themePath, $destPath, true);
		  }

	  }

	  return true;

  }

    public function removeCleanedThemesAndPlugins(){

        if ($this->cleanupbefore == true) {

            $this->restore_progress->log(__('Removing old plugins and themes moved before restoration.', 'wt-backups'), 'INFO');

            $destination = WT_BACKUPS_CONTENT_DIR . DIRECTORY_SEPARATOR . 'clean-ups';
            $destination_unique = $destination . DIRECTORY_SEPARATOR . 'restoration_' . $this->start;
            $this->rrmdir($destination_unique);

        }
    }

  public function replaceDbPrefixInWPConfig(&$manifest) {

	  $abs = untrailingslashit(ABSPATH);
	  $curr_prefix = $this->table_prefix;
	  $new_prefix = $manifest->config->table_prefix;

		if($curr_prefix === $new_prefix) return;

	  $this->restore_progress->log(__('Restoring wp-config file...', 'wt-backups'), 'STEP');
	  $wpconfigDir = $abs . DIRECTORY_SEPARATOR . 'wp-config.' . $this->tmptime . '.php';
	  if ($this->wp_filesystem->exists($wpconfigDir) && $this->wp_filesystem->is_readable($wpconfigDir) && $this->wp_filesystem->is_writable($wpconfigDir)) {

		  $wpconfig = $this->wp_filesystem->get_contents($abs . DIRECTORY_SEPARATOR . 'wp-config.php');
		  if (strpos($wpconfig, '"' . $curr_prefix . '";') !== false) {
			  $wpconfig = str_replace('"' . $curr_prefix . '";', '"' . $new_prefix . '";', $wpconfig);
		  } elseif (strpos($wpconfig, "'" . $curr_prefix . "';") !== false) {
			  $wpconfig = str_replace("'" . $curr_prefix . "';", "'" . $new_prefix . "';", $wpconfig);
		  }

          $this->wp_filesystem->put_contents($abs . DIRECTORY_SEPARATOR . 'wp-config.php', $wpconfig);

		  $this->restore_progress->log(__('WP-Config restored', 'wt-backups'), 'SUCCESS');

	  } else {

		  $this->restore_progress->log(__('Cannot write to WP-Config, if you need to change database prefix, please do it manually.', 'wt-backups'), 'WARN');

	  }

  }

  public function makeNewLoginSession(&$manifest) {

	  wp_load_alloptions(true);

	  $this->restore_progress->log(__('Making new login session', 'wt-backups'), 'STEP');

	  if ($manifest->cron === true || $manifest->cron === 'true' || $manifest->uid === 0 || $manifest->uid === '0') {
		  $manifest->uid = 1;
	  }

	  if (is_numeric($manifest->uid)) {
		  $existant = (bool) get_users(['include' => $manifest->uid, 'fields' => 'ID']);
		  if ($existant) {
			  $user = get_user_by('id', $manifest->uid);
		  } else {
			  $existant = (bool) get_users(['include' => 1, 'fields' => 'ID']);
			  if ($existant) {
				  $user = get_user_by('id', 1);
			  }
		  }
	  }

	  if (isset($user) && is_object($user) && property_exists($user, 'ID')) {
		  remove_all_actions('wp_login', -1000);
		  clean_user_cache(get_current_user_id());
		  clean_user_cache($user->ID);
		  wp_clear_auth_cookie();
		  wp_set_current_user($user->ID, $user->user_login);
		  wp_set_auth_cookie($user->ID, 1, is_ssl());
		  do_action('wp_login', $user->user_login, $user);
		  update_user_caches($user);
			$this->restore_progress->log(__('User authorized again', 'wt-backups'), 'INFO');
	  }

	  $this->restore_progress->log(__('User should be logged in', 'wt-backups'), 'SUCCESS');

  }

  public function clearElementorCache() {

	  $file = trailingslashit(wp_upload_dir()['basedir']) . 'elementor';
	  if (file_exists($file) && is_dir($file)) {
		  $this->restore_progress->log(__('Clearing elementor template cache...', 'wt-backups'), 'STEP');
		  $path = $file . DIRECTORY_SEPARATOR . '*';
		  foreach (glob($path) as $file_path) if (!is_dir($file_path)) wp_delete_file($file_path);
		  $this->restore_progress->log(__('Elementor cache cleared!', 'wt-backups'), 'SUCCESS');
	  }

  }

  public function finalCleanUP() {

	  $this->restore_progress->log(__('Cleaning temporary files...', 'wt-backups'), 'STEP');
	  $this->cleanup();
	  $this->removeCleanedThemesAndPlugins();
	  $this->restore_progress->log(__('Temporary files cleaned', 'wt-backups'), 'SUCCESS');

  }

  public function handleError($e) {

	  // Restore moved themes and plugins
	  $this->rescueCleanedThemesAndPlugins();

	  // On this tragedy at least remove tmp files
	  $this->restore_progress->log(__('Something bad happened...', 'wt-backups'), 'ERROR');
	  $this->restore_progress->log($e->getMessage(), 'ERROR');
	  $this->restore_progress->log($e->getLine() . ' @ ' . $e->getFile(), 'ERROR');
	  $this->cleanup();

  }

  public function makeTMPDirectory() {

	  // Make temp dir
	  $this->restore_progress->log(__('Making temporary directory', 'wt-backups'), 'INFO');
	  if (!(is_dir($this->tmp) || file_exists($this->tmp))) {
          $this->wp_filesystem->mkdir($this->tmp, 0755);
	  }

	  // Deny read of this folder
	  copy(WT_BACKUPS_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'htaccess' . DIRECTORY_SEPARATOR . '.htaccess', $this->tmp . DIRECTORY_SEPARATOR . '.htaccess');
      $this->wp_filesystem->touch($this->tmp . DIRECTORY_SEPARATOR . 'index.html');
      $this->wp_filesystem->touch($this->tmp . DIRECTORY_SEPARATOR . 'index.php');

  }


  public function listBackupContents() {

	  $manager = new WT_Backups_Zipper();

	  $save = $this->scanFile;
	  $amount = $manager->getZipContentList($this->src, $save);

	  $this->restore_progress->log(__('Scan found ', 'wt-backups') . $amount . __(' files inside the backup.', 'wt-backups'), 'INFO');

	  return $amount;

  }

  public function extractTo() {

	  try {

		  // STEP: 1
		  $this->restore_progress->log(__('Secret key detected successfully (pong)!', 'wt-backups'), 'INFO');

		  // Make temporary directory
		  $this->makeTMPDirectory();

		  // Time start
		  $this->restore_progress->log(__('Scanning archive...', 'wt-backups'), 'STEP');

		  // STEP: 2
		  // Get ZIP contents for batch unzipping
		  $this->fileAmount = $this->listBackupContents();
		  $this->cleanupCurrentThemesAndPlugins();

		  // STEP: 3
		  // UnZIP the backup
		  $unzipped = $this->makeUnZIP();
		  if ($unzipped === false) {
			  $this->handleError(__('File extraction process failed.', 'wt-backups'));

			  WT_Backups_Option::setNotification('error', __('File extraction process failed.', 'wt-backups'));

			  wp_send_json( [
				  'notifications' => WT_Backups_Ajax::notifications(),
				  'success'       => false,
			  ], 200 );
				
		  }

		  // STEP: 4
		  // WP Config backup
		  $this->makeWPConfigCopy();

		  // STEP: 5

		  // Get manifest
		  $manifest = $this->getCurrentManifest(true);

		  try {

			  if (isset($manifest->version)) {
				  $this->restore_progress->log(__('WT backups version used for that backup: ', 'wt-backups') . $manifest->version, 'INFO');
			  } else {
				  $this->restore_progress->log(__('Backup was made with unknown version of WT backups plugin.', 'wt-backups'), 'INFO');
			  }

		  } catch (\Exception $e) {
			  $this->restore_progress->log(__('Backup was made with unknown version of WT backups plugin.', 'wt-backups'), 'INFO');
		  } catch (\Throwable $e) {
			  $this->restore_progress->log(__('Backup was made with unknown version of WT backups plugin.', 'wt-backups'), 'INFO');
		  }

		  // Restore files
		  $this->restoreBackupFromFiles($manifest);


		  // STEP: 6
		  $this->restore_progress->log(__('Checking the database structure...', 'wt-backups'), 'STEP');

		  if (is_dir($this->tmp . DIRECTORY_SEPARATOR . 'db_tables')) {
			  $database_exist = $this->restoreDatabase($manifest);
		  } else {
			  $database_exist = false;
			  $this->restore_progress->log(__('This backup does not contain database copy, omitting...', 'wt-backups'), 'INFO');
		  }

		  // STEP: 7
		  // Rename database from temporary to destination
		  // Restore WP Config ** It allows to recover session after restore no matter what
		  if ( $database_exist ) {

			  // Alter all tables
			  $this->alter_tables($manifest);

			  // Modify the WP Config and replace
			  $this->replaceDbPrefixInWPConfig($manifest);

			  // User is logged off at this point, try to log in
			  $this->makeNewLoginSession($manifest);

		  }

				// Fix elementor templates
		  $this->clearElementorCache();

		  // Make final cleanup
		  $this->finalCleanUP();

		  // Final flush of rewrite rules
		  flush_rewrite_rules();

		  // Dedicated fix for block-wp-login plugin
		  $this->fixWPLogin($manifest);


		  // Final verbose
		  if ((intval(microtime(true)) - $this->start) > 0) {
			  $this->restore_progress->log(sprintf(__('Restore process took: %s seconds', 'wt-backups'), (intval(microtime(true)) - intval($this->start))), 'INFO');
		  } else {
			  $this->restore_progress->log(__('Restore process fully finished.', 'wt-backups'), 'INFO');
		  }
		  WT_Backups_Logger::log('Site restored...');

		  // Return success
		  return true;

	  } catch (\Exception $e) {

		  // On this tragedy at least remove tmp files
		  $this->handleError($e);
		  return false;

	  } catch (\Throwable $e) {

		  // On this tragedy at least remove tmp files
		  $this->handleError($e);
		  return false;

	  }

  }

}
