<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

class UserRemember extends ActiveRecord {

	/**
	 * This property maps “user_id” column.
	 */
	protected int $userId;

	/**
	 * This property maps “remember_me column.
	 */
	protected string $rememberMe;

	/**
	 * This property maps “created_at” column.
	 */
	protected \DateTime $createdAt;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'users_remembers';

	/**
	 * Name of primary key db field.
	 * @var array
	 */
	const TABLE_KEY = ['user_id', 'remember_me'];

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['userId'];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsDatetime('createdAt');

		$this->bindAsInteger('userId');

	}

	/**
	 * Return an user that matches remember_me string if created less than 1 month ago. Null if not found.
	 *
	 * @param	string	RememberMe value.
	 */
	public static function getUserByRememberMe(string $rememberMe): ?User {

		// delete older remember-me DB records
		Database::run('DELETE FROM `users_remembers` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 1 MONTH)');

		$query =
			'SELECT u.*
			FROM `users` AS u
			INNER JOIN `users_remembers` AS ur ON u.`id` = ur.`user_id`
			WHERE ur.`remember_me` = ?';

		$userClass = Application::getInstance()->userClass;

		return $userClass::getObjectByQuery($query, [$rememberMe]);

	}

}