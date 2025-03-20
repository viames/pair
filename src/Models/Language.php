<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;

class Language extends ActiveRecord {

	/**
	 * Unique identifier.
	 */
	protected int $id;

	/**
	 * ISO 639-1 language code (ex. “en”).
	 */
	protected string $code;

	/**
	 * Language native name.
	 */
	protected ?string $nativeName = NULL;

	/**
	 * Language name in english.
	 */
	protected string $englishName;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'languages';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'			=> ['smallint unsigned', 'NO', 'PRI', 'NULL', 'auto_increment'],
		'code'			=> ['varchar(7)', 'NO', 'UNI', '', ''],
		'native_name'	=> ['varchar(30)', 'YES', '', 'NULL', ''],
		'english_name'	=> ['varchar(30)', 'NO', '', '', '']
	];

	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id');

		$this->bindAsBoolean('default');

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds(): array {

		$varFields = [
			'id'			=> 'id',
			'code'			=> 'code',
			'englishName'	=> 'english_name',
			'nativeName'	=> 'native_name'
		];

		return $varFields;

	}

	/**
	 * Get the default Country object for this language based on locales table.
	 */
	public function getDefaultCountry(): ?Country {

		$query =
			'SELECT c.*
			FROM `countries` AS c
			INNER JOIN `locales` AS l ON c.`id` = l.`country_id`
			WHERE l.`language_id` = ?
			AND l.`default_country` = 1';

		return Country::getObjectByQuery($query, [$this->id]);

	}

}