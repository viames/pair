<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Language extends ActiveRecord {

	/**
	 * Unique identifier.
	 * @var int
	 */
	protected $id;

	/**
	 * ISO 639-1 language code (ex. “en”).
	 * @var string
	 */
	protected $code;
	
	/**
	 * Language tag as ISO 639-1 _ country code (ex. “en_UK”).
	 * @var string
	 */
	protected $representation;

	/**
	 * Language name in english.
	 * @var string
	 */
	protected $languageName;
	
	/**
	 * Flag to set this language as default.
	 * @var bool
	 */
	protected $default;
	
	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'languages';

	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';

	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function init() {
	
		$this->bindAsInteger('id');
	
		$this->bindAsBoolean('default');
	
	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds() {

		$varFields = array(
			'id'			=> 'id',
			'code'			=> 'code',
			'representation'=> 'representation',
			'languageName'	=> 'language_name',
			'default'		=> 'is_default');
		
		return $varFields;

	}
	
	/**
	 * Returns Language object by its code, if found, NULL otherwise.
	 * 
	 * @param	string	Two chars language code.
	 * 
	 * @return	Language|NULL
	 */
	public static function getLanguageByCode($code) {
		
		$db = Database::getInstance();
		$db->setQuery('SELECT * FROM languages WHERE code = ?');
		$res = $db->loadObject($code);
		
		if (is_a($res, 'stdClass')) {
			return new Language($res);
		} else {
			return NULL;
		}
		
	}
	
	/**
	 * Returns the default Language object.
	 *
	 * @return	Language
	 */
	public static function getDefault() {

		$db = Database::getInstance();
		$db->setQuery('SELECT * FROM languages WHERE is_default = 1');
		$language = new Language($db->loadObject());
		return $language;

	}

	/**
	 * Returns TRUE if this language is default.
	 *
	 * @return	bool
	 */
	public function isDefault() {
	
		return $this->default;

	}
	
	public function getStrings($module) {
		
		$folder = APPLICATION_PATH . ('common'!=$module ? '/modules/' . $module : '') . '/languages';
		$file = $folder . '/' . $this->code . '.ini';
		
		// checks that folder exists
		if (is_dir($folder) and file_exists($file)) {

			// scans file and gets translation strings
			$strings = parse_ini_file($file);
			return (is_array($strings) ? $strings : array()); 
			
		} else {
			
			return array();
			
		}
		
	}
	
	public function setStrings($strings, $module) {
		
		$folder = APPLICATION_PATH . ('common'!=$module ? '/modules/' . $module : '') . '/languages';
		$file = $folder . '/' . $this->code . '.ini';
		
		// checks that folder exists
		if (!file_exists($file) and is_dir($folder) and is_writable($folder)) {
		
			try {

				// creates new language file
				touch($file);
				chmod ($file, 0777);
				
				// sets standard file head
				$head = "; \$Id\$\r\n";
					
			} catch (\Exception $e) {
				
				trigger_error($e->getMessage());
				return FALSE;
				
			}

		} else if (file_exists($file)) {

			// reads first line from current file
			$current = file($file);
			
			// parsing of current file head
			if (isset($current[0])) {

				// searching for a real SVN Id
				$regex = '^; \$Id: .+ \d+ \d{4}-\d{2}-\d{1,2} \d{2}:\d{2}:\d{2}Z \w+ \$$';
				if (0 == strpos($current[0], '; $Id' . '$') or preg_match($regex, $current[0])) {
					$head = $current[0];
				} else {
					$head = "; \$Id\$\n";
				}

			// sets standard file head
			} else {
				
				$head = "; \$Id\$\n";
				
			}

		} else {
			
			trigger_error('Language file ' . $file . ' cannot be read');
			return FALSE;
			
		}
		
		// second file line
		$head .= "; " . $this->languageName . " language\n\n";
		
		$lines = array();
		
		// translated lines only if not empty
		foreach ($strings as $key=>$value) {
			if ($value) {
				$lines[] = $key . ' = "' . $value . '"';
			}
		}

		$content = $head . implode("\n", $lines);
		
		try {
			
			$res = file_put_contents($file, $content);
			
		} catch (\Exception $e) {
			
			$app = Application::getInstance();
			$app->logError('Language file ' . $file . ' cannot be written due its permission');
			$res = FALSE;
			
		}
		
		return $res;
				
	}

	/**
	 * Returns language absolute file path of passed module.
	 *
	 * @param	string	Module name.
	 *
	 * @return	boolean
	 */
	public function getFilePath($module) {
	
		$folder = APPLICATION_PATH . ('common'!=$module ? '/modules/' . $module : '') . '/languages';
		$file = $folder . '/' . $this->code . '.ini';
	
		return $file;
	
	}
	
	/**
	 * Returns TRUE if language file of passed module is writable.
	 * 
	 * @param	string	Module name.
	 * 
	 * @return	boolean
	 */
	public function isWritable($module) {
		
		$folder = APPLICATION_PATH . ('common'!=$module ? '/modules/' . $module : '') . '/languages';
		$file = $folder . '/' . $this->code . '.ini';
		
		if ((file_exists($file) and is_writable($file)) or (!file_exists($file) and is_dir($folder) and is_writable($folder))) {
			return TRUE;
		} else {
			return FALSE;
		}
		
	}
	
	/**
	 * Returns array with all abs path to language folders, included common language path.
	 *
	 * @return array:string
	 */
	public static function getLanguageFolders() {
	
		// common language folder
		$folders = array('common' => APPLICATION_PATH . '/languages');
	
		$modules = array_diff(scandir('modules'), array('..', '.', '.DS_Store'));
	
		// assembles the other language folders
		foreach ($modules as $module) {
			$folders[$module] = APPLICATION_PATH . '/modules/' . $module . '/languages';
		}
	
		return $folders;
	
	}
	
}
