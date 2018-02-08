<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Translator {
	
	/**
	 * Singleton object.
	 * @var object|NULL
	 */
	protected static $instance;
	
	/**
	 * The default Language object.
	 * @var	Language
	 */
	private $default;
	
	/**
	 * The current Language object.
	 * @var Language
	 */
	private $current;
	
	/**
	 * Current module in where to look for language files.
	 * @var	string
	 */
	private $module;
	
	/**
	 * Translation strings, as loaded from ini language file.
	 * @var NULL|array
	 */
	private $strings;

	/**
	 * Default language strings, loaded if needed and stored for next use.
	 * @var NULL|array
	 */
	private $defaultStrings;

	/**
	 * Set current language reading the favorite browser language variable.
	 */
	private function __construct() {

		// config module for language
		$this->default = Language::getDefault();
		
	}
	
	/**
	 * Return the singleton object.
	 *
	 * @return object
	 */
	public static function getInstance() {
	
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
	
		return static::$instance;
	
	}

	/**
	 * Return the current Language object.
	 * 
	 * @return	Language
	 */
	public function getCurrentLanguage() {
		
		$this->checkLanguageSet();
		
		return $this->current;
		
	}
	
	/**
	 * Return the default Language object, cached.
	 *
	 * @return	Language
	 */
	public function getDefaultLanguage() {
		
		$this->checkLanguageSet();
		
		return $this->default;
		
	}
	
	/**
	 * Set a new current language by preparing language strings and locale.
	 * 
	 * @param	Language	Language object to set.
	 */
	public function setLanguage(Language $newLang) {
		
		// apply some changes only if new Language really differs
		if (!$this->current or ($this->current and $newLang->code != $this->current->code)) {
			
			$this->current = $newLang;
				
			// if new language code equals the default one, move lang-strings
			if ($this->default and $newLang->code == $this->default->code) {

				$this->strings = $this->defaultStrings;
				$this->defaultStrings = NULL;
				
			// otherwise reload current strings
			} else {
			
				$this->strings = NULL;
				$this->loadStrings();
				
			}
			
		}
		
		setlocale(LC_ALL, $newLang->representation);
		
	}
	
	/**
	 * Set a module name.
	 * 
	 * @param	string	Module name.
	 */
	public function setModule($moduleName) {
		
		$this->module = $moduleName;
		
	}
	
	/**
	 * Check that both default and current languages are set.
	 */
	private function checkLanguageSet() {
		
		if (!$this->default) {
			
			$lang = Language::getDefault();
			$this->default = $lang;
			
			// server variable
			setlocale(LC_ALL, $lang->representation);
			
		}
		
		if (!$this->current) {
			
			// temporary sets default language as current
			$this->current = $this->default;
			
			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			
				// gets favorite language from browser settings
				preg_match_all('/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?' .
						'(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i',
						$_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches, PREG_SET_ORDER);
			
				// if browser’s lang matches and it’s different by current, will set as current
				if (isset($matches[0][1]) and $this->current->code != $matches[0][1]) {
			
					$lang = Language::getLanguageByCode($matches[0][1]);
			
					if ($lang) {
						
						$this->setLanguage($lang);
							
					}
			
				}
					
			}
			
		}
		
	}
	
	/**
	 * Return the translated string from expected lang file, if there, else
	 * from default, else return the key string.
	 * 
	 * @param	string		The language key.
	 * @param	array|NULL	List of parameters to bind on string (optional).
	 * 
	 * @return	string
	 */
	public function translate($key, $vars=NULL) {

		$app = Application::getInstance();
		
		// load translation strings
		$this->loadStrings();
		
		// searches into strings
		if (array_key_exists($key, $this->strings) and $this->strings[$key]) {

			$string = $this->strings[$key];
			
		// searches into strings of default language
		} else if (is_array($this->defaultStrings) and array_key_exists($key, $this->defaultStrings) and $this->defaultStrings[$key]) {
			
			$app->logWarning('Language string ' . $key . ' is untranslated for current language [' . $this->current->code . ']');
			$string = $this->defaultStrings[$key];

		// will returns the string constant, as debug info
		} else {

			$app->logWarning('Language string ' . $key . ' is untranslated');
			$string = '[' . $key . ']';
		
		}
		
		if (!is_null($vars)) {

			// force a single string to be the expected array
			if (!is_array($vars)) {
				$vars = array((string)$vars);
			}
				
			// binds of parameters on %s placeholders
			$string = vsprintf($string, $vars);
		
		}
		
		return $string;
		
	}
	
	/**
	 * Return TRUE if passed language is available for translation.
	 * 
	 * @param	string	Language key.
	 * 
	 * @return	boolean
	 */
	public function stringExists($key) {
		
		// load translation strings
		$this->loadStrings();
		
		if (array_key_exists($key, $this->strings) or array_key_exists($key, $this->defaultStrings)) {
			return TRUE;
		} else {
			return FALSE;
		}
		
	}
	
	/**
	 * Load translation strings from current and default (if different) language ini file.
	 */
	private function loadStrings() {

		// load strings just once
		if (is_array($this->strings)) {
			return;
		}
		
		// avoid failures
		$this->strings = array();

		// useful for landing page
		if (!$this->module) {
			$app = Application::getInstance();
			$route = Router::getInstance();
			if ($route->module) {
				$this->module = $route->module;
			} else if (is_a($app->currentUser, 'Pair\User')) {
				$this->module = $app->currentUser->getLanding()->module;
			}
		}

		// checks that languages are set
		$this->checkLanguageSet();

		// common strings in current language
		$common = 'languages/' . $this->current->code . '.ini';
		if (file_exists($common)) {
			try {
				$this->strings = @parse_ini_file($common);
				if (FALSE == $this->strings) {
					throw new \Exception('File parsing failed: ' . $common);
				}
			} catch (\Exception $e) {
				$this->strings = array();
			}
		}
		
		// if module is not set, won’t find language file
		if ($this->module) {
			
			// module strings in current language
			$file1 = 'modules/' . strtolower($this->module) . '/languages/' . $this->current->code . '.ini';
			if (file_exists($file1)) {
				try {
					$moduleStrings = @parse_ini_file($file1);
					if (FALSE == $moduleStrings) {
						throw new \Exception('File parsing failed: ' . $file1);
					}
				} catch (\Exception $e) {
					$moduleStrings = array();
				}
				$this->strings = array_merge($this->strings, $moduleStrings);
			}
		
		}
		
		// if current language is different by default, will load also
		if ($this->current->code != $this->default->code) {
			
			// common strings in default language
			$common = 'languages/' . $this->default->code . '.ini';
			if (file_exists($common)) {
				try {
					$this->defaultStrings = @parse_ini_file($common);
				} catch (\Exception $e) {
					$this->defaultStrings = array();
				}
			}

			// if module is not set, won’t find language file
			if ($this->module) {
		
				// module strings in default language
				$file2 = 'modules/' . strtolower($this->module) . '/languages/' . $this->default->code . '.ini';
				if (file_exists($file2)) {
					try {
						$moduleStrings = @parse_ini_file($file2);
					} catch (\Exception $e) {
						$moduleStrings = array();
					}
					$this->defaultStrings = array_merge($this->defaultStrings, $moduleStrings);
				}
				
			}
				
		}

	}

	/**
	 * Translate the text in an array of select-options strings if uppercase.
	 * 
	 * @param	array	List of (value=>text)s to translate.
	 * @return	array
	 */
	public function translateSelectOptions($optSelect) {
	
		// load translation strings
		$this->loadStrings();
		
		foreach ($optSelect as $value=>$text) {
			
			// tricks to leave untranslated english-only options
			if (strtoupper($text) == $text and strlen($text) > 3) {
				$optSelect[$value] = $this->translate($text);
			}
			
		}
	
		return $optSelect;
	
	}

}
