<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}

/**
 * WebTotem Scanner class.
 */
class WT_Backups_Scanner {

	public static function equalEnd($name, $nsiz, $rsiz, $rule) {
		return substr($name, -$rsiz) == $rule;
	}

	public static function equalStart($name, $nsiz, $rsiz, $rule) {
		return substr($name, 0, $rsiz) == $rule;
	}

	public static function equalAnywhere($name, $rule) {
		return strpos($name, $rule) !== false;
	}

	public static function equalFolder($name, $ignores) {

		$s = strlen($name);
		for ($i = 0; $i < sizeof($ignores); ++$i) {

			if ($ignores[$i]['z'] > $s) continue;
			if ($ignores[$i]['w'] == '1') {

				if (self::equalAnywhere($name, $ignores[$i]['s'])) return true;
				else continue;

			} elseif ($ignores[$i]['w'] == '2') {

				if (self::equalStart($name, $s, $ignores[$i]['z'], $ignores[$i]['s'])) return true;
				else continue;

			} elseif ($ignores[$i]['w'] == '3') {

				if (self::equalEnd($name, $s, $ignores[$i]['z'], $ignores[$i]['s'])) return true;
				else continue;

			}

		}

		return false;
	}

	public static function equalFolderByPath($abs, $path, $ignores) {

		$alen = strlen($abs);
		$path = substr($path, $alen + 1);
		$paths = explode(DIRECTORY_SEPARATOR, $path);

		for ($i=0; $i < sizeof($paths); ++$i) {

			$c = $paths[$i];
			if (self::equalFolder($c, $ignores)) {

				return true;

			}

		}

		return false;
	}

	public static function scanDirectory($path) {

		$files = [];
		if (!is_readable($path) || is_link($path)) return $files;
		foreach (new \DirectoryIterator($path) as $fileInfo) {

			if ($fileInfo->isLink()) continue;
			if ($fileInfo->isDot()) continue;
			if (!$fileInfo->isDir()) {

				try {
					$files[] = $fileInfo->getFilename() . DIRECTORY_SEPARATOR . $fileInfo->getSize();
				} catch (\Exception $e) {
					WT_Backups_Logger::debug('Failed to check file: ' . $fileInfo->getFilename());
				}

			} else {

				$files[] = [];
				$index = sizeof($files) - 1;
				$dirName = $fileInfo->getFilename();
				$newBase = $path . DIRECTORY_SEPARATOR . $dirName;
				$files[$index] = self::scanDirectory($newBase);
				array_unshift($files[$index], $dirName);

			}

		}

		return $files;

	}

	public static function scanDirectorySizeOnly($path, $bm) {

		$files = [];
		if (!is_readable($path) || is_link($path)) return $files;
		foreach (new \DirectoryIterator($path) as $fileInfo) {

			if ($fileInfo->isLink()) continue;
			if ($fileInfo->isDot()) continue;
			if (!$fileInfo->isDir()) {

				try {
					$files[] = $fileInfo->getSize();
				} catch (\Exception $e) {
					WT_Backups_Logger::debug('Failed to check file: ' . $fileInfo->getFilename());
				}

			} else {

				$files[] = [];
				$index = sizeof($files) - 1;
				$dirName = $fileInfo->getFilename();
				$newBase = $path . DIRECTORY_SEPARATOR . $dirName;
				if ($newBase == $bm) continue;
				$files[$index] = self::scanDirectorySizeOnly($newBase, $bm);
				array_unshift($files[$index], $dirName);

			}

		}

		return $files;

	}

	public static function scanDirectorySizeOnlyAndIgnore($path, $ignored = [], $bm = '') {

		$files = [];
		if (!is_readable($path) || is_link($path)) return $files;
		foreach (new \DirectoryIterator($path) as $fileInfo) {

			if ($fileInfo->isLink()) continue;
			if ($fileInfo->isDot()) continue;
			if (!$fileInfo->isDir()) {

				try {
					$files[] = $fileInfo->getSize();
				} catch (\Exception $e) {
					WT_Backups_Logger::debug('Failed to check file: ' . $fileInfo->getFilename());
				}

			} else {

				$dirPath = $fileInfo->getPath();
				$dirName = $fileInfo->getFilename();
				if (in_array($dirName, $ignored) || in_array($dirPath, $ignored)) {
					WT_Backups_Logger::debug('Dodging ' . $dirName);
					continue;
				}

				$files[] = [];
				$index = sizeof($files) - 1;
				$newBase = $path . DIRECTORY_SEPARATOR . $dirName;
				if ($newBase == $bm) continue;
				$files[$index] = self::scanDirectorySizeOnlyAndIgnoreDirOnly($newBase, $ignored, $bm);
				array_unshift($files[$index], $dirName);

			}

		}

		return $files;

	}

	public static function scanDirectorySizeOnlyAndIgnoreDirOnly($path, $ignored = [], $bm = '') {

		$files = [];
		try {

			if (!is_readable($path) || is_link($path)) return $files;
			foreach (new \DirectoryIterator($path) as $fileInfo) {

				if ($fileInfo->isLink()) continue;
				if ($fileInfo->isDot()) continue;
				if (!$fileInfo->isDir()) {

					try {
						$files[] = $fileInfo->getSize();
					} catch (\Exception $e) {
						WT_Backups_Logger::debug('Failed to check file: ' . $fileInfo->getFilename());
					}

				} else {

					$dirName = $fileInfo->getFilename();
					$dirPath = $fileInfo->getPath();
					if (in_array($dirPath, $ignored)) {
						WT_Backups_Logger::debug('Dodging ' . $dirName);
						continue;
					}

					$files[] = [];
					$index = sizeof($files) - 1;
					$newBase = $path . DIRECTORY_SEPARATOR . $dirName;
					if ($newBase == $bm) continue;
					$files[$index] = self::scanDirectorySizeOnlyAndIgnoreDirOnly($newBase, $ignored, $bm);
					array_unshift($files[$index], $dirName);

				}

			}

		} catch (\Exception $e) {
			WT_Backups_Logger::log($e->getMessage());
			return $files;
		} catch (\Throwable $e) {
			WT_Backups_Logger::log($e->getMessage());
			return $files;
		}

		return $files;

	}

	public static function scanDirectoryNameOnly($path) {

		$files = [];

		if (!is_readable($path) || is_link($path)) return $files;
		foreach (new \DirectoryIterator($path) as $fileInfo) {

			if ($fileInfo->isLink()) continue;
			if ($fileInfo->isDot()) continue;
			if (!$fileInfo->isDir()) {

				if (!$fileInfo->isLink()) {
					$files[] = $fileInfo->getFilename();
				}

			} else {

				$files[] = [];
				$index = sizeof($files) - 1;
				$dirName = $fileInfo->getFilename();
				$newBase = $path . DIRECTORY_SEPARATOR . $dirName;
				$files[$index] = self::scanDirectoryNameOnly($newBase);
				array_unshift($files[$index], $dirName);

			}

		}

		return $files;

	}

	public static function scanDirectoryNameOnlyAndIgnore($path, $ignored = []) {

		$files = [];

		if (!is_readable($path) || is_link($path)) return $files;
		foreach (new \DirectoryIterator($path) as $fileInfo) {

			if ($fileInfo->isLink()) continue;
			if ($fileInfo->isDot()) continue;
			if (!$fileInfo->isDir()) {

				if (!$fileInfo->isLink()) {
					$files[] = $fileInfo->getFilename();
				}

			} else {

				$dirName = $fileInfo->getFilename();
				if (in_array($dirName, $ignored)) {
					WT_Backups_Logger::debug('Dodging ' . $dirName);
					continue;
				}

				$files[] = [];
				$index = sizeof($files) - 1;
				$newBase = $path . DIRECTORY_SEPARATOR . $dirName;
				$files[$index] = self::scanDirectoryNameOnlyAndIgnore($newBase, $ignored);
				array_unshift($files[$index], $dirName);

			}

		}

		return $files;

	}

	public static function scanDirectoryNameOnlyAndIgnoreFBC($path, $ignored_folders = [], $ignored_paths = []) {

		$files = [];

		if (!is_readable($path) || is_link($path)) return $files;
		foreach (new \DirectoryIterator($path) as $fileInfo) {

			if ($fileInfo->isLink()) continue;
			if ($fileInfo->isDot()) continue;
			if (!$fileInfo->isDir()) {

				if (!$fileInfo->isLink()) {
					$files[] = $fileInfo->getFilename() . ',' . $fileInfo->getSize();
				}

			} else {

				$dirName = $fileInfo->getFilename();
				if (self::equalFolder($dirName, $ignored_folders)) {
					WT_Backups_Logger::debug('Dodging folder ' . $dirName);
					continue;
				}

				$files[] = [];
				$index = sizeof($files) - 1;
				$newBase = $path . DIRECTORY_SEPARATOR . $dirName;
				if (in_array($newBase, $ignored_paths)) {
					WT_Backups_Logger::debug('Dodging path ' . $newBase);
					continue;
				}

				$files[$index] = self::scanDirectoryNameOnlyAndIgnoreFBC($newBase, $ignored_folders, $ignored_paths);
				array_unshift($files[$index], $dirName);

			}

		}

		return $files;

	}

	public static function getSizeOfFileList($fileList) {

		$size = 0;
		for ($i = 0; $i < sizeOf($fileList); $i++) {

			if ($i == 0) continue;
			if (!is_array($fileList[$i])) {

				$size += intval($fileList[$i]);

			} else {

				$size += self::getSizeOfFileList($fileList[$i]);

			}

		}
		return $size;

	}

	public static function getFileFullPaths($base, $fileList) {

		$paths = []; $merge = [];
		for ($i = 0; $i < sizeOf($fileList); $i++) {

			if ($i == 0) {

				$base = $base . DIRECTORY_SEPARATOR . $fileList[$i];
				continue;

			}

			if (!is_array($fileList[$i])) {

				$paths[] = $base . DIRECTORY_SEPARATOR . $fileList[$i];

			} else {

				$merge[] = self::getFileFullPaths($base, $fileList[$i]);

			}

		}

		$paths = array_merge($paths, ...$merge);
		return $paths;

	}

	public static function scanFiles($path, $bm) {

		// Get TOP Dir name
		$z = explode(DIRECTORY_SEPARATOR, $path);
		$z = $z[sizeof($z) - 1];

		// Scan Directory
		$x = self::scanDirectorySizeOnly($path, $bm);

		// Push TOP Lever (root) Directory
		array_unshift($x, $z);

		// Calculate size
		$y = self::getSizeOfFileList($x);

		// Return size in Bytes
		return $y;

	}

	public static function scanFilesGetNamesWithIgnore($path, $ignored = []) {

		// Get TOP Dir name
		$z = explode(DIRECTORY_SEPARATOR, $path);
		$z = $z[sizeof($z) - 1];

		// Scan Directory
		$x = self::scanDirectoryNameOnlyAndIgnore($path, $ignored);

		// Push TOP Lever (root) Directory
		array_unshift($x, $z);

		// Parse Output to Array of Full Paths
		$path = substr($path, 0, -(strlen($z) + 1));
		$y = self::getFileFullPaths($path, $x);

		// Return array of Full Paths
		return $y;

	}

	public static function scanFilesGetNamesWithIgnoreFBC($path, $ignored = [], $ignored_paths = []) {

		// Exclude paths which won't exist in current $path
		$max = sizeof($ignored_paths);
		for ($i = 0; $i < $max; ++$i) {

			if (strpos($ignored_paths[$i], $path) === false) {
				array_splice($ignored_paths, $i, 1);
				$max--; $i--;
			}

		}

		// Get TOP Dir name
		$z = explode(DIRECTORY_SEPARATOR, $path);
		$z = $z[sizeof($z) - 1];

		// Scan Directory
		$x = self::scanDirectoryNameOnlyAndIgnoreFBC($path, $ignored, $ignored_paths);

		// Push TOP Lever (root) Directory
		array_unshift($x, $z);

		// Parse Output to Array of Full Paths
		$path = substr($path, 0, -(strlen($z) + 1));
		$y = self::getFileFullPaths($path, $x);

		// Return array of Full Paths
		return $y;

	}

	public static function scanFilesWithIgnore($path, $ignored, $bm) {

		// Get TOP Dir name
		$z = explode(DIRECTORY_SEPARATOR, $path);
		$z = $z[sizeof($z) - 1];

		// Scan Directory
		$x = self::scanDirectorySizeOnlyAndIgnore($path, $ignored, $bm);

		// Push TOP Lever (root) Directory
		array_unshift($x, $z);

		// Calculate size
		$y = self::getSizeOfFileList($x);

		// Return size in Bytes
		return $y;

	}

	public static function fileTooLarge($size, $max) {

		if ($size > $max) return true;
		else return false;

	}

}
