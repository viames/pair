<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Application;
use Pair\Language;
use Pair\Model;
use Pair\Module;
use Pair\Options;
use Pair\Translator;

class SelftestModel extends Model {

	public function testApache() {

		$res = TRUE;

		// apache section
		$aModules = \apache_get_modules();
		if (!in_array('mod_rewrite', $aModules)) {
			$res = FALSE;
			$this->logError('Apache mod_rewrite is not loaded');
		}

		return $res;

	}

	public function testPhp() {

		$res = TRUE;

		// php section
		$requiredPhpExt = array('curl','fileinfo','gd','json','mcrypt', 'openssl','pcre','PDO','Reflection','soap','sockets');

		$hiddenExt = array();
		
		if ('ldap' == AUTH_SOURCE) {
			$requiredPhpExt[] = 'ldap';
		}
		
		switch (DBMS) {
			case 'mysql':
				$requiredPhpExt[] = 'pdo_mysql';
				break;
			case 'mssql':
				$requiredPhpExt[] = 'pdo_dblib';
				break;
		}
		
		$this->app->logEvent('Checking for PHP extensions ' . implode(', ', $requiredPhpExt));

		// check each library
		foreach ($requiredPhpExt as $ext) {

			if (!extension_loaded($ext)) {

				// patch for hidden extensions that reveals themselves only by command line
				if (in_array($ext, $hiddenExt)) {

					$lines = explode("\n", shell_exec('php -i|grep ' . $ext));
					foreach ($lines as $line) {
						if ($ext . ' support => enabled' == $line) continue 2;
					}
					
				}

				// enqueue failure and show error message
				$res = FALSE;
				$this->logError('Missing PHP extension ' . $ext);

			}

		}

		if (version_compare(phpversion(), "5.6.0", "<")) {
			$res = FALSE;
			$this->logError('PHP version required is 5.6 or greater. You are using PHP ' . phpversion());
		}

		return $res;

	}

	/**
	 * Check that MySQL version is greater than 5.5 and search for charset settings.
	 * Return TRUE if DBMS is ok.
	 * 
	 * @return	bool
	 */
	public function testMysql() {
		
		$ret = TRUE;
		
		$version = $this->db->getMysqlVersion();
		
		if (version_compare($version, '5.6') < 0) {
			$this->logError('MySQL version required is 5.6 or greater. You are using MySQL ' . $version);
			$ret = FALSE;
		}

		// the right settings list
		$settings = array(
			'character_set_client'		=> 'utf8',
			'character_set_connection'	=> 'utf8',
			'character_set_database'	=> 'utf8mb4',
			'character_set_filesystem'	=> 'binary',
			'character_set_results'		=> 'utf8',
			'character_set_server'		=> 'utf8mb4',
			'character_set_system'		=> 'utf8',
			'collation_connection'		=> 'utf8_general_ci',
			'collation_database'		=> 'utf8mb4_unicode_ci',
			'collation_server'			=> 'utf8mb4_unicode_ci');
		
		// ask to dbms the current settings
		$this->db->setQuery('SHOW VARIABLES WHERE Variable_name LIKE \'character\_set\_%\' OR Variable_name LIKE \'collation%\'');
		$list = $this->db->loadObjectList();

		// compare settings
		foreach ($list as $row) {
			
			if (array_key_exists($row->Variable_name, $settings)) {
				
				if ($settings[$row->Variable_name] != $row->Value) {
					$this->logError('DBMS setting parameter ' . $row->Variable_name . ' value is ' . $row->Value . ' should be ' . $settings[$row->Variable_name]);
					$ret = FALSE;
				}
				
			}
			
		}
		
		return $ret;
		
	}
	
	/**
	 * Will tests config.ini file for missing lines or bad entries and returns TRUE if it's ok.
	 *
	 * @return boolean
	 */
	public function testConfigFile() {

		$ret = TRUE;

		$options = Options::getInstance();

		if (!defined('UTC_DATE')) {
			$ret = FALSE;
			$this->logError('In config.ini file UTC_DATE constant is missing');
		}

		if (!defined('AUTH_SOURCE') or ('ldap'!=AUTH_SOURCE and 'internal'!=AUTH_SOURCE and 'none'!=AUTH_SOURCE)) {
			$ret = FALSE;
			$this->logError('In config.ini file AUTH_SOURCE constant (ldap|internal|none) is missing.');
		} else if ('ldap' == AUTH_SOURCE) {
			if (!defined('LDAP_HOST') or !defined('LDAP_PORT') or !defined('LDAP_BASEDN') or
					!defined('LDAP_AUTHREALM') or !defined('LDAP_USERBIND') or !defined('LDAP_BINDPW')) {
						$ret = FALSE;
						$this->logError('In config.ini file LDAP_HOST, LDAP_PORT, LDAP_BASEDN, LDAP_AUTHREALM, LDAP_USERBIND or LDAP_BINDPW constant is missing.');
					}
		}
		
		return $ret;

	}

	/**
	 * Tests needed folders in both read and write.
	 */
	public function testFolders() {

		$ret = TRUE;

		$folders = array('files', 'languages', 'modules', 'templates');
		
		$modules = Module::getAllObjects();
		
		foreach ($modules as $module) {
			$folders[] = 'modules/' . strtolower($module->name) . '/languages';
		}

		foreach ($folders as $f) {

			$folder = APPLICATION_PATH . '/' . $f;

			if (is_dir($folder)) {
				if (!is_readable($folder)) {
					$ret = FALSE;
					$this->logError(PRODUCT_NAME . ' application hasn’t read permission on folder ' . $folder);
				} else if (!is_writable($folder)) {
					$ret = FALSE;
					$this->logError(PRODUCT_NAME . ' application hasn’t write permission on folder ' . $folder);
				}
			}

		}

		return $ret;

	}

	/**
	 * Test for unfound language files under languages folder for all modules.
	 *
	 * @return array
	 */
	public function testLanguages() {

		// instance of current language translator
		$translator = Translator::getInstance();
		
		// all registered languages
		$languages = Language::getAllObjects(NULL, array('code'));

		// paths
		$defaultLang = $translator->default . '.ini';

		// count of fails
		$files		= 0;
		$folders	= 0;
		$lines		= 0;
		$notNeeded	= 0;
		
		// common language folder
		$langFolders = array(APPLICATION_PATH . '/languages');
		
		$modules = array_diff(scandir('modules'), array('..', '.', '.DS_Store'));
		
		// assembles the other language folders
		foreach ($modules as $module) {
			$langFolders[] = APPLICATION_PATH . '/modules/' . $module . '/languages';
		}
		
		// scan on each language folder
		foreach ($langFolders as $langFolder) {

			// checks that folder exists
			if (is_dir($langFolder)) {

				// now checks for default language file
				if (file_exists($langFolder . '/' . $defaultLang)) {

					// gets all default language’s keys
					$langData = @parse_ini_file($langFolder . '/' . $defaultLang);
					$defaultKeys = array_keys($langData);

					// compares each other language file
					foreach ($languages as $language) {

						// else if is not default, pass keys to another array
						if ($language->code != $translator->default) {

							if (!file_exists($langFolder . '/' . $language->code . '.ini')) {
	
								$files++;
								//$this->logWarning('Unfound ' . $language->languageName . ' language file at this path: ' . $langFolder);
	
							} else {

								// scans file and gets all translation keys
								$langData = @parse_ini_file($langFolder . '/' . $language->code . '.ini');
								$otherKeys = array_keys($langData);

								// untranslated lines
								$lines += $this->countUntranslated($defaultKeys, $otherKeys, $language->code, $langFolder);

								// not needed lines
								$notNeeded += $this->countNotNeeded($defaultKeys, $otherKeys, $language->code, $langFolder);
			
							}
							
						}
						
					}
			
				} else {
			
					$files++;
					//$this->logWarning('Unfound ' . $defaultLang . ' language file at this path: ' . $langFolder);
			
				}
			
			} else {
			
				$folders++;
				$this->logWarning('Folder language was not found at this path: ' . $langFolder);
			
			}
			
		}

		$retVar = array(
			'folders'	=> $folders,
			'files'		=> $files,
			'lines'		=> $lines,
			'notNeeded'	=> $notNeeded);

		return $retVar;

	}

	/**
	 * Counts for untranslated language lines and logs a warning with line and language-file path.
	 * 
	 * @param	array	List of translation key names.
	 * @param	array	List of comparing language key names.
	 * @param	string	Two chars language code.
	 * @param	string	Path to comparing language file.
	 *
	 * @return	int
	 */
	private function countUntranslated($defaultKeys, $otherKeys, $langCode, $langPath) {

		$differences = array_diff($defaultKeys, $otherKeys);

		foreach ($differences as $diff) {
			//$this->logWarning('Untranslated “' . $diff . '” key for “' . $langCode . '.ini” at this path: ' . $langPath);
		}

		return count($differences);
		
	}

	/**
	 * Counts not needed language lines and logs a warning with line and language-file path.
	 * 
	 * @param	array	List of translation key names.
	 * @param	array	List of comparing language key names.
	 * @param	string	Two chars language code.
	 * @param	string	Path to comparing language file.
	 * 
	 * @return	int
	 */
	private function countNotNeeded($defaultKeys, $otherKeys, $langCode, $langPath) {

		$differences = array_diff($otherKeys, $defaultKeys);
		
		foreach ($differences as $diff) {
			$this->logWarning('Key  “' . $diff . '” is not needed for language “' . $langCode . '.ini” at this path: ' . $langPath);
		}

		return count($differences);
		
	}

	/**
	 * Returns list of class names that inherite from ActiveRecord.
	 *
	 * @return array
	 */
	public function getActiveRecordClasses() {

		$classes = array();

		// list of class files
		$scan1 = array_diff(scandir('classes'), array('..', '.', '.DS_Store'));
		$scan2 = array();
		//$scan2 = array_diff(scandir('framework'), array('..', '.', '.DS_Store'));

		$classFiles = array_merge($scan1, $scan2);

		foreach ($classFiles as $file) {

			// cut .php from file name
			$class = substr($file, 0, -4);

			// will adds just requested children
			if (is_subclass_of($class, 'Pair\ActiveRecord')) {
				$reflection = new ReflectionClass($class);
				if (!$reflection->isAbstract()) {
					$classes[] = $class;
				}
			}

		}

		// list of class files in modules
		$modules = array_diff(scandir('modules'), array('..', '.', '.DS_Store'));

		foreach ($modules as $module) {

			$classesFolder = 'modules/' . $module . '/classes';

			if (is_dir($classesFolder)) {

				$classFiles = array_diff(scandir($classesFolder), array('..', '.', '.DS_Store'));

				foreach ($classFiles as $file) {

					// only .php files are included
					if ('.php' == substr($file,-4)) {

						// cut .php from file name
						$class = substr($file, 0, -4);

						if (!class_exists($class)) {
							include_once ($classesFolder . '/' . $file);
						}
						
						// will adds just requested children
						if (is_subclass_of($class, 'Pair\ActiveRecord')) {
							$reflection = new ReflectionClass($class);
							if (!$reflection->isAbstract()) {
								$classes[] = $class;
							}
						}

					}

				}

			}

		}
		
		sort($classes);

		return $classes;

	}

	/**
	 * Compare version of each installed plugin with application version and return FALSE
	 * if at least one of them is made for older version.
	 * 
	 * @return	bool
	 */
	public function checkPlugins() {
		
		$app = Application::getInstance();
		
		$ret = TRUE;
		
		// list of plugin types with namespace (true if Pair framework)
		$pluginTypes = array(
				'module'	=> TRUE,
				'template'	=> TRUE);
		
		foreach ($pluginTypes as $type => $aFramework) {
			
			// compute names
			$class	= ($aFramework ? 'Pair\\' : '') . ucfirst($type);

			// load db records and create objects
			$plugins = $class::getAllObjects();

			// for each plugin compare version 
			foreach ($plugins as $plugin) {
				
				if (version_compare(PRODUCT_VERSION, $plugin->appVersion) > 0) {
					$app->logWarning(ucfirst($type) . ' plugin ' . ucfirst($plugin->name) .
							' is compatible with ' . PRODUCT_NAME . ' v' . $plugin->appVersion);
					$ret = FALSE;
				}
				
			}
			
		
		}
		
		return $ret;

	}

}
