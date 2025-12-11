<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;

class Country extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * This property maps “code” column.
	 */
	protected string $code;

	/**
	 * This property maps “native_name” column.
	 */
	protected ?string $nativeName = null;

	/**
	 * This property maps “english_name” column.
	 */
	protected string $englishName;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'countries';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'			=> ['smallint unsigned', 'NO', 'PRI', 'NULL', 'auto_increment'],
		'code'			=> ['varchar(3)', 'NO', 'UNI', '', ''],
		'native_name'	=> ['varchar(100)', 'YES', '', '', ''],
		'english_name'	=> ['varchar(100)', 'NO', '', '', '']
	];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id');

	}

	/**
	 * Returns an array with the object property names and corresponding columns in the db.
	 *
	 * @return array
	 */
	protected static function getBinds(): array {

		$binds = [
			'id'			=> 'id',
			'code'			=> 'code',
			'nativeName'	=> 'native_name',
			'englishName'	=> 'english_name'
		];

		return $binds;

	}

}