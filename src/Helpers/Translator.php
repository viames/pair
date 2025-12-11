<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Core\Router;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\PairException;
use Pair\Models\Locale;
use Pair\Orm\Collection;

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

		return $currentLanguage ? $currentLanguage->code : null;

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

		$self->strings = null;
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
				$this->defaultStrings = null;

			// otherwise reload current strings
			} else {

				$this->strings = null;
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
	 * Return the translated string from expected lang file, if there, else
	 * from default, else return the key string.
	 *
	 * @param	string	The language key.
	 * @param	string|array|null	Parameter or list of parameters to bind on string (optional).
	 * @param	bool|null	Show a warning if string is not found (optional).
	 * @param	string|Callable	What to return if string is not found (optional).
	 */
	public static function do(string $key, string|array|null $vars = null, bool $warning = true, string|Callable|null $default = null): string {

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

				$logger = Logger::getInstance();
				$logger->warning('Language string ' . $key . ' is untranslated for current language [' . $self->currentLocale->code . ']');
				$string = $self->defaultStrings[$key];

			// return the string constant, as debug info
			} else {

				$logger = Logger::getInstance();
				$logger->warning('Language string ' . $key . ' is untranslated');
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
	 * Load language strings from ini file and merge them with current strings without overwriting existing ones.
	 */
	private function parseLanguageFile(string $filePath): void {

		if (file_exists($filePath) and is_readable($filePath)) {

			$strings = parse_ini_file($filePath);
			
			if (is_array($strings) and count($strings)) {

				// merge new strings without overwriting existing ones
				$this->strings += $strings;

			}

		}

	}

	/**
	 * Return true if passed language is available for translation.
	 *
	 * @param	string	Language key.
	 */
	public function stringExists($key): bool {

		// load translation strings
		$this->loadStrings();

		if (array_key_exists($key, $this->strings) or array_key_exists($key, $this->defaultStrings)) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Load translation strings from current and default (if different) language ini file.
	 */
	private function loadStrings(): void {

		// load strings just once
		if (isset($this->strings) and is_array($this->strings)) {
			return;
		}

		// initialize
		$this->strings = [];

		// useful for landing page
		if (!isset($this->module) or !$this->module) {
			$app = Application::getInstance();
			$router = Router::getInstance();
			if ($router->module) {
				$this->module = $router->module;
			} else if (is_a($app->currentUser, 'Pair\Models\User') and $app->currentUser->landing() and $app->currentUser->landing()->module) {
				$this->module = (string)$app->currentUser->landing()->module;
			}
		}

		// checks that languages are set
		$this->checkLocaleSet();

		$pairLanguageFolder = dirname(dirname(__DIR__)) . '/translations/';
		$commonLanguageFolder = APPLICATION_PATH . '/translations/';

		$languageFiles = [];

		// if module is set, loads its module strings
		if (isset($this->module) and $this->module) {
			$languageFiles[] = APPLICATION_PATH . '/modules/' . strtolower($this->module) . '/translations/' . $this->currentLocale->getRepresentation() . '.ini';
		}

		// pair strings and common translation strings in current language
		$languageFiles[] = $pairLanguageFolder . $this->currentLocale->getRepresentation() . '.ini';
		$languageFiles[] = $commonLanguageFolder . $this->currentLocale->getRepresentation() . '.ini';

		// if current language is different by default language, will load default strings
		if ($this->currentLocale->getRepresentation() != $this->defaultLocale->getRepresentation()) {

			// if module is not set, won’t find language file
			if (isset($this->module) and $this->module) {
				$languageFiles[] = APPLICATION_PATH . '/modules/' . strtolower($this->module) . '/translations/' . $this->defaultLocale->getRepresentation() . '.ini';
			}

			$languageFiles[] = $pairLanguageFolder . $this->defaultLocale->getRepresentation() . '.ini';
			$languageFiles[] = $commonLanguageFolder . $this->defaultLocale->getRepresentation() . '.ini';

		}

		foreach ($languageFiles as $langFile) {
			$this->parseLanguageFile($langFile);
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
	public function translateActiveRecordList(array|Collection $list, string $propertyName): array|Collection {

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

		return self::$instance->getDefaultLocale()->getRepresentation() . '.ini';

	}

}
