<?php

namespace Pair\Models;

use Pair\Core\Logger;
use Pair\Exceptions\PairException;
use Pair\Helpers\Utilities;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

class Locale extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * This property maps “language_id” column.
	 */
	protected int $languageId;

	/**
	 * This property maps “country_id” column.
	 */
	protected int $countryId;

	/**
	 * This property maps “official_language” column.
	 */
	protected bool $officialLanguage;

	/**
	 * This property maps “default_country” column.
	 */
	protected ?bool $defaultCountry = null;

	/**
	 * This property maps “app_default” column.
	 */
	protected bool $appDefault;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'locales';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['languageId', 'countryId'];

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id' => ['smallint unsigned', 'NO', 'PRI', NULL, 'auto_increment'],
		'language_id' => ['smallint unsigned', 'NO', 'MUL', NULL, ''],
		'country_id' => ['smallint unsigned', 'NO', 'MUL', NULL, ''],
		'official_language' => ['tinyint unsigned', 'NO', 'MUL', '0', ''],
		'default_country' => ['tinyint unsigned', 'YES', 'MUL', NULL, ''],
		'app_default' => ['tinyint unsigned', 'NO', 'MUL', '0', '']
	];

	/**
	 * Speed up the foreign-key load because for this class they are always used.
	 */
	const FOREIGN_KEYS = [
		[
			'CONSTRAINT_NAME'			=> 'fk_locales_countries',
			'COLUMN_NAME'				=> 'country_id',
			'REFERENCED_TABLE_NAME'		=> 'countries',
			'REFERENCED_COLUMN_NAME'	=> 'id',
			'UPDATE_RULE'				=> 'CASCADE',
			'DELETE_RULE'				=> 'RESTRICT'
		],[
			'CONSTRAINT_NAME'			=> 'fk_locales_languages',
			'COLUMN_NAME'				=> 'language_id',
			'REFERENCED_TABLE_NAME'		=> 'languages',
			'REFERENCED_COLUMN_NAME'	=> 'id',
			'UPDATE_RULE'				=> 'CASCADE',
			'DELETE_RULE'				=> 'RESTRICT'
		]
	];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsBoolean('officialLanguage', 'defaultCountry', 'appDefault');
		$this->bindAsInteger('id', 'languageId', 'countryId');

	}

	/**
	 * Returns an array with the object property names and corresponding columns in the db.
	 */
	protected static function getBinds(): array {

		$binds = [
			'id'				=> 'id',
			'languageId'		=> 'language_id',
			'countryId'			=> 'country_id',
			'officialLanguage'	=> 'official_language',
			'defaultCountry'	=> 'default_country',
			'appDefault'		=> 'app_default'
		];

		return $binds;

	}

	protected function beforeStore(): void {

		// only one row can be appDefault
		if ($this->appDefault) {
			Database::run('UPDATE `' . self::TABLE_NAME . '` SET `app_default` = 0');
		}

		// only one country can be default for a language
		if ($this->defaultCountry) {
			Database::run('UPDATE `' . self::TABLE_NAME . '` SET `default_country` = 0 WHERE `language_id` = ?', [$this->languageId]);
		}

	}

	/**
	 * Returns the default Locale object.
	 */
	public static function getDefault(): ?Locale {

		return static::getObjectByQuery('SELECT * FROM `locales` WHERE `app_default` = 1');

	}

	/**
	 * Returns true if language of this locale is the application default.
	 */
	public function isDefault(): bool {

		return $this->appDefault;

	}

	/**
	 * Returns true if language of this locale is official one for its country.
	 */
	public function isOfficialLanguage(): bool {

		return $this->officialLanguage;

	}

	/**
	 * Returns true if the country of this locale is the default for its language.
	 */
	public function isDefaultCountry(): bool {

		return $this->defaultCountry;

	}

	/**
	 * Returns the Locale representation as a string.
	 *
	 * @param	string	Separator character, default is '-'.
	 */
	public function getRepresentation(string $separator = '-'): string {

		if (!in_array($separator, ['-', '_'])) {
			$logger = Logger::getInstance();
			$logger->error('Invalid separator for Locale representation', [
				'separator' => $separator
			]);
		}

		$country = $this->getCountry() ?? new Country($this->countryId);

		$language = $this->getLanguage() ?? new Language($this->languageId);

		return $language->code . $separator . $country->code;

	}

	/**
	 * Returns the Locale object by its representation.
	 * @param	string	Locale representation (eg. en-GB).
	 */
	public static function getByRepresentation(string $representation): ?Locale {

		list ($languageCode, $countryCode) = explode('-', $representation);

		$query =
			'SELECT lc.*
			FROM `locales` as lc
			INNER JOIN `languages` AS l ON lc.`language_id` = l.`id`
			INNER JOIN `countries` AS c ON lc.`country_id` = c.`id`
			WHERE c.`code` = ?
			AND l.`code` = ?';

		return static::getObjectByQuery($query, [$countryCode, $languageCode]);

	}

	/**
	 * Returns the Locale object by a Language code and its default Country.
	 * @param	string	Language code as of ISO 639-1 standard list.
	 */
	public static function getDefaultByLanguage(string $languageCode): ?Locale {

		$query =
			'SELECT lc.*
			FROM `locales` as lc
			INNER JOIN `languages` AS l ON lc.`language_id` = l.`id`
			WHERE lc.`default_country` = 1
			AND l.`code` = ?';

		return static::getObjectByQuery($query, [$languageCode]);

	}

	/**
	 * Returns translation absolute file path for this Locale and passed module.
	 * @param	string	Module name.
	 */
	public function getFilePath(string $module): string {

		$folder = APPLICATION_PATH . ('common'!=$module ? '/modules/' . $module : '') . '/translations';
		return $folder . '/' . $this->getRepresentation() . '.ini';

	}

	/**
	 * Read all translation strings from a file located into a module.
	 * @param	Module|null	Module object or null to read the common translation file.
	 * @return	string[]
	 */
	public function readTranslation(?Module $module): array {

		// get the right translation folder
		$file = APPLICATION_PATH . (is_a($module,'Pair\Models\Module') ? '/modules/' . $module->name : '') . '/translations/' . $this->getRepresentation() . '.ini';

		// checks that folder exists
		if (file_exists($file)) {

			// scans file and gets translation strings
			$strings = parse_ini_file($file);
			return (is_array($strings) ? $strings : []);

		} else {

			return [];

		}

	}

	/**
	 * Returns true if translation file of passed module is writable.
	 * @param	string	Module name.
	 */
	public function isFileWritable(string $moduleName): bool {

		$folder = APPLICATION_PATH . ('common'!=$moduleName ? '/modules/' . $moduleName : '') . '/translations';
		$file = $folder . '/' . $this->getRepresentation() . '.ini';

		if ((file_exists($file) and is_writable($file)) or (!file_exists($file) and is_dir($folder) and is_writable($folder))) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Write all translation strings into a file located into a module.
	 * @param	string[]	List of translation strings.
	 * @param	Module|null		Module object or null to read the common translation file.
	 */
	public function writeTranslation(array $strings, ?Module $module): bool {

		$folder = APPLICATION_PATH . (is_a($module,'Pair\Models\Module') ? '/modules/' . $module->name : '') . '/translations/';
		$file = $folder . $this->getRepresentation() . '.ini';

		// checks that folder exists
		if (!file_exists($file)) {

			if (is_dir($folder) and is_writable($folder)) {

				// creates new language file
				touch($file);
				chmod($file, 0777);

			} else {

				throw new PairException('Translation folder ' . $folder . ' does not exist or is not writable');

			}

		}

		$lines = [];

		// translated lines only if not empty
		foreach ($strings as $key=>$value) {
			if ($value) {
				$lines[] = $key . ' = "' . $value . '"';
			}
		}

		$content = implode("\n", $lines);

		return file_put_contents($file, $content);

	}

	/**
	 * Return the native language and country names if available, english name otherwise.
	 */
	public function getNativeNames(): string {

		$language = $this->getLanguage();
		$country  = $this->getCountry();

		$languageName = $language->nativeName ? $language->nativeName : $language->englishName;
		$countryName  = $country->nativeName ? $country->nativeName : $country->englishName;

		return $languageName . ' (' . $countryName . ')';

	}

	/**
	 * Return the english language and country names if available, native name otherwise.
	 */
	public function getEnglishNames(): string {

		$language = $this->getLanguage();
		$country  = $this->getCountry();

		$languageName = $language->englishName ? $language->englishName : $language->nativeName;
		$countryName  = $country->englishName ? $country->englishName : $country->nativeName;

		return $languageName . ' (' . $countryName . ')';

	}

	/**
	 * List all locales that have the common translation file in this application.
	 *
	 * @param	bool	Flag true to get native language (country) names.
	 *
	 * @return	Locale[]
	 */
	public static function getExistentTranslations(?bool $nativeNames = true): array {

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
		$files = Utilities::getDirectoryFilenames(APPLICATION_PATH . '/translations');

		$existents = [];

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