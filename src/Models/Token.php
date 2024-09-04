<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;

class Token extends ActiveRecord {

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
	 * This property maps “description” column.
	 * @var string
	 */
	protected $description;

	/**
	 * This property maps “value” column.
	 * @var string
	 */
	protected $value;

	/**
	 * This property maps “enabled” column.
	 * @var string
	 */
	protected $enabled;

	/**
	 * This property maps “created_by” column.
	 * @var int
	 */
	protected $createdBy;

	/**
	 * This property maps “creation_date” column.
	 * @var DateTime
	 */
	protected $creationDate;

	/**
	 * This property maps “last_use” column.
	 * @var DateTime|NULL
	 */
	protected $lastUse;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'tokens';

	/**
	 * Name of primary key db field.
	 * @var string|array
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsBoolean('enabled');

		$this->bindAsDatetime('creationDate', 'lastUse');

		$this->bindAsInteger('id', 'createdBy');

	}

	/**
	 * Generate a random token.
	 *
	 * @param	int		Token length, default 16.
	 *
	 * @return	string
	 */
	public static function generate($length=16) {

		// PHP 7
		if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
			$token = bin2hex(random_bytes($length));
		} else {
			$token = bin2hex(openssl_random_pseudo_bytes($length));
		}

		return $token;

	}

	/**
	 * Load a Token object from DB by its code and return it.
	 *
	 * @param	string	Token identifier code.
	 * @return	Token|NULL
	 */
	public static function getByValue(string $tokenValue): ?self {

		return self::getObjectByQuery('SELECT * FROM `tokens` WHERE `value` = ? AND `enabled` = 1', [$tokenValue]);

	}

	/**
	 * Set to now() the lastUse object property.
	 */
	public function updateLastUse(): void {

		$this->lastUse = new \DateTime();
		$this->update('lastUse');

	}

}