<?php

namespace Pair;

class UserRemember extends ActiveRecord {

	/**
	 * This property maps “user_id” column.
	 * @var int
	 */
	protected $userId;

	/**
	 * This property maps “remember_me column.
	 * @var string
	 */
	protected $rememberMe;

	/**
	 * This property maps “created_at” column.
	 * @var DateTime|NULL
	 */
	protected $createdAt;

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
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsDatetime('createdAt');

		$this->bindAsInteger('user_id');

	}

	/**
	 * Return an user that matches remember_me string if created less than 1 month ago. NULL if not found.
	 *
	 * @param	string		RememberMe value.
	 */
	public static function getUserByRememberMe(string $rememberMe): ?User {

		// delete older remember-me DB records
		Database::run('DELETE FROM `users_remembers` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 1 MONTH)');

		$query =
			'SELECT u.*' .
			' FROM `users` AS u' .
			' INNER JOIN `users_remembers` AS ur ON u.`id` = ur.`user_id`' .
			' WHERE ur.`remember_me` = ?';

		$userClass = PAIR_USER_CLASS;

		return $userClass::getObjectByQuery($query, [$rememberMe]);

	}

	/**
	 * Utility to unserialize and return the remember-me cookie content {timezone, rememberMe}.
	 */
	public static function getCookieContent(): ?string {

		// build the cookie name
		$cookieName = static::getCookieName();

		// check if cookie exists
		if (!isset($_COOKIE[$cookieName])) {
			return NULL;
		}

		// try to unserialize the cookie content
		$content = unserialize($_COOKIE[$cookieName], ['allowed_classes' => FALSE]);

		// cookie content is not unserializable
		if (FALSE === $content) {
			return  NULL;
		}

		$regex = '#^[' . Utilities::RANDOM_STRING_CHARS . ']{32}$#';
		$contentCheck = preg_match($regex, (string)$content);

		// check content format and return it
		return $contentCheck ? (string)$content : NULL;

	}

	/**
	 * Build and return the cookie name.
	 */
	public static function getCookieName(): string {

		return Application::getCookiePrefix() . 'RememberMe';

	}

}