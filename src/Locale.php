<?php

namespace Pair;

class Locale extends ActiveRecord {
	
	/**
	 * This property maps “id” column.
	 * @var int
	 */
	protected $id;

	/**
	 * This property maps “language_id” column.
	 * @var int
	 */
	protected $languageId;

	/**
	 * This property maps “country_id” column.
	 * @var int
	 */
	protected $countryId;
	
	/**
	 * This property maps “official_language” column.
	 * @var bool
	 */
	protected $officialLanguage;
	
	/**
	 * This property maps “default_country” column.
	 * @var bool
	 */
	protected $defaultCountry;
	
	/**
	 * This property maps “app_default” column.
	 * @var bool
	 */
	protected $appDefault;
	
	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'locales';
	
	/**
	 * Name of primary key db field.
	 * @var string|array
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 * @var	array
	 */
	const SHARED_CACHE_PROPERTIES = ['languageId', 'countryId'];

	/**
	 * Speed up the foreign-key load because for this class they are always used.
	 * @var array
	 */
	const FOREIGN_KEYS = [
		['CONSTRAINT_NAME'			=> 'fk_locales_countries',
		'COLUMN_NAME'				=> 'country_id',
		'REFERENCED_TABLE_NAME'		=> 'countries',
		'REFERENCED_COLUMN_NAME'	=> 'id',
		'UPDATE_RULE'				=> 'CASCADE',
		'DELETE_RULE'				=> 'RESTRICT'],
		['CONSTRAINT_NAME'			=> 'fk_locales_languages',
		'COLUMN_NAME'				=> 'language_id',
		'REFERENCED_TABLE_NAME'		=> 'languages',
		'REFERENCED_COLUMN_NAME'	=> 'id',
		'UPDATE_RULE'				=> 'CASCADE',
		'DELETE_RULE'				=> 'RESTRICT']
	];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsBoolean('officialLanguage', 'defaultCountry', 'appDefault');
		$this->bindAsInteger('id', 'languageId', 'countryId');

	}
	
	protected function beforeStore() {
		
		// only one row can be appDefault 
		if ($this->appDefault) {
			$this->db->exec('UPDATE `' . self::TABLE_NAME . '` SET `app_default` = 0');
		}
		
		// only one country can be default for a language
		if ($this->defaultCountry) {
			$this->db->exec('UPDATE `' . self::TABLE_NAME . '` SET `default_country` = 0 WHERE `language_id` = ?', [$this->languageId]);
		}
		
	}
	
	/**
	 * Returns the default Locale object.
	 *
	 * @return	Locale
	 */
	public static function getDefault() {
		
		return static::getObjectByQuery('SELECT * FROM `locales` WHERE `app_default` = 1');
		
	}

	/**
	 * Returns TRUE if language of this locale is the application default.
	 *
	 * @return	bool
	 */
	public function isDefault() {
		
		return $this->appDefault;
		
	}
	
	/**
	 * Returns TRUE if language of this locale is official one for its country.
	 *
	 * @return	bool
	 */
	public function isOfficialLanguage() {
		
		return $this->officialLanguage;
		
	}
	
	/**
	 * Returns TRUE if the country of this locale is the default for its language.
	 *
	 * @return	bool
	 */
	public function isDefaultCountry() {
		
		return $this->defaultCountry;
		
	}
	
	public function getRepresentation() {
		
		return $this->getLanguage()->code . '-' . $this->getCountry()->code;
		
	}

	/**
	 * Returns the Locale object by its representation.
	 *
	 * @param	string	Locale representation (eg. en-GB).
	 *
	 * @return	Locale|NULL
	 */
	public static function getByRepresentation($representation) {
		
		list ($languageCode, $countryCode) = explode('-', $representation);
		
		$query =
			'SELECT lc.*' .
			' FROM `locales` as lc' .
			' INNER JOIN `languages` AS l ON lc.language_id = l.id' .
			' INNER JOIN `countries` AS c ON lc.country_id = c.id' .
			' WHERE c.code = ?' .
			' AND l.code = ?';
		
		return static::getObjectByQuery($query, [$countryCode, $languageCode]);
		
	}
	
	/**
	 * Returns the Locale object by a Language code and its default Country.
	 * 
	 * @param	string	Language code as of ISO 639-1 standard list.
	 *
	 * @return	Locale|NULL
	 */
	public static function getDefaultByLanguage($languageCode) {

		$query =
			'SELECT lc.*' .
			' FROM `locales` as lc' .
			' INNER JOIN `languages` AS l ON lc.language_id = l.id' .
			' WHERE lc.default_country = 1' .
			' AND l.code = ?';
		
		return static::getObjectByQuery($query, [$languageCode]);
		
	}
	
	/**
	 * Returns translation absolute file path for this Locale and passed module.
	 *
	 * @param	string	Module name.
	 *
	 * @return	string
	 */
	public function getFilePath($module) {
		
		$folder = APPLICATION_PATH . ('common'!=$module ? '/modules/' . $module : '') . '/translations';
		return $folder . '/' . $this->getRepresentation() . '.ini';
		
	}
	
	/**
	 * Read all translation strings from a file located into a module.
	 * 
	 * @param	Module|NULL		Module object or NULL to read the common translation file.
	 * 
	 * @return	array:string
	 */
	public function readTranslation($module) {
		
		// get the right translation folder
		$file = APPLICATION_PATH . (is_a($module,'Pair\Module') ? '/modules/' . $module->name : '') . '/translations/' . $this->getRepresentation() . '.ini';
		
		// checks that folder exists
		if (file_exists($file)) {
			
			// scans file and gets translation strings
			$strings = parse_ini_file($file);
			return (is_array($strings) ? $strings : array());
			
		} else {
			
			return array();
			
		}
		
	}
	
	/**
	 * Returns TRUE if translation file of passed module is writable.
	 *
	 * @param	string	Module name.
	 *
	 * @return	bool
	 */
	public function isFileWritable($moduleName) {
		
		$folder = APPLICATION_PATH . ('common'!=$moduleName ? '/modules/' . $moduleName : '') . '/translations';
		$file = $folder . '/' . $this->getRepresentation() . '.ini';
		
		if ((file_exists($file) and is_writable($file)) or (!file_exists($file) and is_dir($folder) and is_writable($folder))) {
			return TRUE;
		} else {
			return FALSE;
		}
		
	}
	
	/**
	 * Write all translation strings into a file located into a module.
	 *
	 * @param	array:string	List of translation strings.
	 * @param	Module|NULL		Module object or NULL to read the common translation file.
	 *
	 * @return	bool
	 */
	public function writeTranslation($strings, $module) {
		
		$folder = APPLICATION_PATH . (is_a($module,'Pair\Module') ? '/modules/' . $module->name : '') . '/translations/';
		$file = $folder . $this->getRepresentation() . '.ini';
		
		// checks that folder exists
		if (!file_exists($file)) {
			
			if (is_dir($folder) and is_writable($folder)) {
				
				try {
					
					// creates new language file
					touch($file);
					chmod($file, 0777);
					
					// sets standard file head
					$head = "; \$Id\$\r\n";
					
				} catch (\Exception $e) {
					
					trigger_error($e->getMessage());
					return FALSE;
					
				}
				
			} else {
				
				trigger_error('Translation file ' . $file . ' cannot be read');
				return FALSE;
			}
			
		}
		
		$lines = array();
		
		// translated lines only if not empty
		foreach ($strings as $key=>$value) {
			if ($value) {
				$lines[] = $key . ' = "' . $value . '"';
			}
		}
		
		$content = implode("\n", $lines);
		
		try {
			
			$res = file_put_contents($file, $content);
			
		} catch (\Exception $e) {
			
			$app = Application::getInstance();
			$app->logError('Translation file ' . $file . ' cannot be written due its permission');
			$res = FALSE;
			
		}
		
		return $res;
		
	}
	
	/**
	 * Return the native language and country names if available, english name otherwise. 
	 * 
	 * @return string
	 */
	public function getNativeNames() {
		
		$language = $this->getLanguage();
		$country  = $this->getCountry();
		
		$languageName = $language->nativeName ? $language->nativeName : $language->englishName;
		$countryName  = $country->nativeName ? $country->nativeName : $country->englishName;
		
		return $languageName . ' (' . $countryName . ')';
		
	}
	
	/**
	 * Return the english language and country names if available, native name otherwise.
	 *
	 * @return string
	 */
	public function getEnglishNames() {
		
		$language = $this->getLanguage();
		$country  = $this->getCountry();
		
		$languageName = $language->englishName ? $language->englishName : $language->nativeName;
		$countryName  = $country->englishName ? $country->englishName : $country->nativeName;
		
		return $languageName . ' (' . $countryName . ')';
		
	}

	/**
	 * List all locales that have the common translation file in this application.
	 *
	 * @param	bool	Flag TRUE to get native language (country) names.
	 *
	 * @return	Locale[]
	 */
	public static function getExistentTranslations($nativeNames = TRUE) {
		
		$columnName = $nativeNames ? 'native_name' : 'english_name';
		
		$query =
		'SELECT lo.*, CONCAT(la.code, "-", co.code) AS representation,' .
		' CONCAT(la.' . $columnName . ', " (", co.' . $columnName . ', ")") AS language_country' .
		' FROM `locales` AS lo' .
		' INNER JOIN `languages` AS la ON lo.language_id = la.id' .
		' INNER JOIN `countries` AS co ON lo.country_id = co.id' .
		' ORDER BY la.' . $columnName;
		
		// all registered Locales with native or english language(country) name
		$locales = Locale::getObjectsByQuery($query);
		
		// list all common translation files
		$files = Utilities::getDirectoryFilenames('translations');
		
		$existents = array();
		
		foreach ($files as $file) {
			
			$fileRepresentation = substr($file, 0, strlen($file)-4);
			
			foreach ($locales as $locale) {
				if ($locale->representation == $fileRepresentation) {
					$existents[] = $locale;
					continue;
				}
			}
			
		}
		
		return $existents;
		
	}

}