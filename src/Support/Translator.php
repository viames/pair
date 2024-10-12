<?php

namespace Pair\Support;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Models\Locale;
use Pair\Orm\Collection;
use Pair\Support\Logger;

class Translator {

	/**
	 * Singleton object.
	 */
	protected static Translator $instance;

	/**
	 * The default Locale object.
	 */
	private Locale $defaultLocale;

	/**
	 * The current user’s Locale object.
	 */
	private Locale $currentLocale;

	/**
	 * Current module in where to look for language files.
	 */
	private string $module;

	/**
	 * Translation strings, as loaded from ini language file.
	 */
	private ?array $strings;

	/**
	 * Default language strings, loaded if needed and stored for next use.
	 */
	private ?array $defaultStrings;

	/**
	 * Set current language reading the favorite browser language variable.
	 */
	private function __construct(){

		// config module for locale
		$this->defaultLocale = Locale::getDefault();

	}

	/**
	 * Return the singleton object.
	 */
	public static function getInstance(): Translator {

		if (!isset(static::$instance) or is_null(static::$instance)) {
			static::$instance = new static();
		}

		return static::$instance;

	}

	/**
	 * Return the current language code.
	 */
	public static function getCurrentLanguageCode(): ?string {

		$self = static::getInstance();
		$currentLocale = $self->getCurrentLocale();
		$currentLanguage = $currentLocale->getLanguage();

		return $currentLanguage ? $currentLanguage->code : NULL;

	}

	/**
	 * Return the current Locale object.
	 */
	public function getCurrentLocale(): Locale {

		$this->checkLocaleSet();

		return $this->currentLocale;

	}

	/**
	 * Return the default Locale object, cached.
	 *
	 * @return	Locale
	 */
	public function getDefaultLocale(): Locale {

		$this->checkLocaleSet();

		return $this->defaultLocale;

	}

	/**
	 * Set the default locale forcing read of language strings.
	 */
	public static function resetLocale(): void {

		$self = static::getInstance();

		$defaultLocale = Locale::getDefault();
		$self->currentLocale = $defaultLocale;

		$self->strings = NULL;
		$self->loadStrings();

		// set default locale to all categories
		setlocale(LC_ALL, str_replace('-', '_', $defaultLocale->getRepresentation()) . '.UTF-8');

	}

	/**
	 * Set a new current locale by preparing its language strings.
	 *
	 * @param	Locale	Locale object to set.
	 */
	public function setLocale(Locale $newLocale): void {

		// apply some changes only if new Locale really differs
		if (!isset($this->currentLocale) or !$this->currentLocale or ($this->currentLocale and $newLocale->id != $this->currentLocale->id)) {

			$this->currentLocale = $newLocale;

			// if new language code equals the default one, move lang-strings
			if ($this->defaultLocale and $newLocale->id == $this->defaultLocale->id and isset($this->defaultStrings)) {

				$this->strings = $this->defaultStrings;
				$this->defaultStrings = NULL;

			// otherwise reload current strings
			} else {

				$this->strings = NULL;
				$this->loadStrings();

			}

		}

		// set current locale to all categories
		setlocale(LC_ALL, str_replace('-', '_', $newLocale->getRepresentation()) . '.UTF-8');

	}

	/**
	 * Set the current module name for this object.
	 *
	 * @param	string	Module name.
	 */
	public function setModuleName($moduleName): void {

		$this->module = $moduleName;

	}

	/**
	 * Check that both default and current locales are set.
	 */
	private function checkLocaleSet(): void {

		if (!$this->defaultLocale) {

			$locale = Locale::getDefault();
			$this->defaultLocale = $locale;

			// server variable
			setlocale(LC_ALL, $locale->getRepresentation());

		}

		if (!isset($this->currentLocale) or !$this->currentLocale) {

			// temporary sets default locale as current
			$this->currentLocale = $this->defaultLocale;

			// gets favorite language from browser settings
			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {

				preg_match_all('/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?' .
						'(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i',
						$_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches, PREG_SET_ORDER);

				// if browser’s lang matches and it’s different by current, will set as current
				if (!isset($matches[0][1]) or $this->currentLocale->getLanguage()->code == $matches[0][1]) {
					return;
				}

				$locale = Locale::getDefaultByLanguage($matches[0][1]);
				if (!$locale) {
					return;
				}

				$this->setLocale($locale);

			}

		}

	}

	/**
	 * Return the translated string from expected lang file, if there, else
	 * from default, else return the key string.
	 *
	 * @param	string	The language key.
	 * @param	string|array|NULL	Parameter or list of parameters to bind on string (optional).
	 * @param	bool|NULL	Show a warning if string is not found (optional).
	 */
	public static function do(string $key, string|array|NULL $vars=NULL, bool $warning=TRUE, string|Callable $default=NULL): string {

		$self = static::getInstance();

		// load translation strings
		$self->loadStrings();

		// search into strings
		if (array_key_exists($key, $self->strings) and $self->strings[$key]) {

			$string = $self->strings[$key];

		} else if (is_string($default)) {

			$string = $default;

		} else if (is_callable($default)) {

			$string = $default($key);

		} else if ($warning) {

			// search into strings of default language
			if (isset($self->defaultStrings) and is_array($self->defaultStrings) and array_key_exists($key, $self->defaultStrings) and $self->defaultStrings[$key]) {

				Logger::warning('Language string ' . $key . ' is untranslated for current language [' . $self->currentLocale->code . ']');
				$string = $self->defaultStrings[$key];

			// return the string constant, as debug info
			} else {

				Logger::warning('Language string ' . $key . ' is untranslated');
				$string = '[' . $key . ']';

			}

		} else {

			// return without warning and square brackets
			$string = $key;

		}

		// vars is optional
		if (!is_null($vars)) {

			// force a single string to be the expected array
			if (!is_array($vars)) {
				$vars = [(string)$vars];
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
	 */
	public function stringExists($key): bool {

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
	private function loadStrings(): void {

		// load strings just once
		if (is_array($this->strings)) {
			return;
		}

		// avoid failures
		$this->strings = [];

		// useful for landing page
		if (!isset($this->module) or !$this->module) {
			$app = Application::getInstance();
			$router = Router::getInstance();
			if ($router->module) {
				$this->module = $router->module;
			} else if (is_a($app->currentUser, 'Pair\Models\User') and $app->currentUser->getLanding() and $app->currentUser->getLanding()->module) {
				$this->module = (string)$app->currentUser->getLanding()->module;
			}
		}

		// checks that languages are set
		$this->checkLocaleSet();

		// common strings in current language
		$common = APPLICATION_PATH . '/translations/' . $this->currentLocale->getRepresentation() . '.ini';
		if (file_exists($common) and is_readable($common)) {
			try {
				$commonFileContent = parse_ini_file($common);
				if (FALSE == $commonFileContent) {
					throw new \Exception('File parsing failed: ' . $common);
				}
				$this->strings = $commonFileContent;
			} catch (\Exception $e) {
				$this->strings = [];
			}
		}

		// if module is not set, won’t find language file
		if (isset($this->module) and $this->module) {

			// module strings in current language
			$file1 = APPLICATION_PATH . '/modules/' . strtolower($this->module) . '/translations/' . $this->currentLocale->getRepresentation() . '.ini';
			if (file_exists($file1)) {
				try {
					$moduleStrings = parse_ini_file($file1);
					if (FALSE == $moduleStrings) {
						throw new \Exception('File parsing failed: ' . $file1);
					}
				} catch (\Exception $e) {
					$moduleStrings = [];
				}
				$this->strings = array_merge($this->strings, $moduleStrings);
			}

		}

		// if current language is different by default, will load also
		if ($this->currentLocale->getRepresentation() != $this->defaultLocale->getRepresentation()) {

			// common strings in default language
			$common = APPLICATION_PATH . '/translations/' . $this->defaultLocale->getRepresentation() . '.ini';
			if (file_exists($common)) {
				try {
					$this->defaultStrings = @parse_ini_file($common);
				} catch (\Exception $e) {
					$this->defaultStrings = [];
				}
			}

			// if module is not set, won’t find language file
			if ($this->module) {

				// module strings in default language
				$file2 = 'modules/' . strtolower($this->module) . '/translations/' . $this->defaultLocale->getRepresentation() . '.ini';
				if (file_exists($file2)) {
					try {
						$moduleStrings = @parse_ini_file($file2);
					} catch (\Exception $e) {
						$moduleStrings = [];
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
	 */
	public function translateSelectOptions(array $optSelect): array {

		// load translation strings
		$this->loadStrings();

		foreach ($optSelect as $value=>$text) {

			// tricks to leave untranslated english-only options
			if (strtoupper($text) == $text and strlen($text) > 3) {
				$optSelect[$value] = self::do($text);
			}

		}

		return $optSelect;

	}

	/**
	 * Translate a list of ActiveRecord objects by specifing a property name.
	 *
	 * @param	array	List of ActiveRecord objects.
	 * @param	string	Parameter name.
	 */
	public function translateActiveRecordList(array|Collection $list, $propertyName): array|Collection {

		if (!isset($list[0]) or !property_exists($list[0], $propertyName)) {
			return $list;
		}

		$translatedVar = 'translated' . ucfirst($propertyName);

		foreach ($list as $item) {
			$item->$translatedVar = self::do($item->$propertyName);
		}

		return $list;

	}

	public static function getDefaultFileName(): string {

		try {
			return self::$instance->getDefaultLocale()->getRepresentation() . '.ini';
		} catch(\Exception $e) {
			die('Translator instance has not been created yet');
		}

	}

}
