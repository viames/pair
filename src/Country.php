<?php

namespace Pair;

class Country extends ActiveRecord {
	
	/**
	 * This property maps “id” column.
	 * @var int
	 */
	protected $id;

	/**
	 * This property maps “code” column.
	 * @var string
	 */
	protected $code;
	
	/**
	 * This property maps “native_name” column.
	 * @var string
	 */
	protected $nativeName;
	
	/**
	 * This property maps “english_name” column.
	 * @var string
	 */
	protected $englishName;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'countries';
	
	/**
	 * Name of primary key db field.
	 * @var string|array
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsInteger('id');

	}

}