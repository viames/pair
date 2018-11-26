<?php

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
	 * Language native name.
	 * @var string
	 */
	protected $nativeName;
	
	/**
	 * Language name in english.
	 * @var string
	 */
	protected $englishName;
	
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
			'englishName'	=> 'english_name',
			'nativeName'	=> 'native_name');
		
		return $varFields;

	}
	
	/**
	 * Get the default Country object for this language based on locales table.
	 * 
	 * @return	Country|NULL
	 */
	public function getDefaultCountry() {
		
		$query =
			'SELECT c.*' .
			' FROM `countries` AS c' .
			' INNER JOIN `locales` AS l ON c.id = l.country_id' .
			' WHERE l.language_id = ?' .
			' AND l.default_country = 1';
					
		return Country::getObjectByQuery($query, $this->id);
		
	}
	
}
