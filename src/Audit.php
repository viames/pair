<?php

namespace Pair;

class Audit extends ActiveRecord {
	
	/**
	 * This property maps “id” column.
	 * @var int
	 */
	protected $id;

	/**
	 * This property maps “user_id” column.
	 * @var int
	 */
	protected $userId;

	/**
	 * This property maps “event” column.
	 * @var string
	 */
	protected $event;

	/**
	 * This property maps “created_at” column.
	 * @var DateTime
	 */
	protected $createdAt;

	/**
	 * This property maps “details” column.
	 * @var stdClass
	 */
	protected $details;
	
	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'audit';
	
	/**
	 * Name of primary key db field.
	 * @var string|array
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsDatetime('createdAt');

		$this->bindAsInteger('id', 'userId');

		$this->bindAsJson('details');

	}

	/**
	 * Set the current logged-in user and the createdAt value before saving the record into db.
	 */
	protected function beforeCreate() {

		$app = Application::getInstance();

		if (!$this->userId and is_a($app->currentUser, 'Pair\User')) {
			$this->userId = $app->currentUser->id;
		}

		$this->createdAt = new \DateTime();

	}

	/**
	 * Convert an object of any type into stdClass with just properties specified in second
	 * array parameter.
	 * 
	 * @param	mixed		The object to convert.
	 * @param	array		List of properties to copy into the new object.
	 * @return	\stdClass	The resulting object.
	 */
	private static function convertToStdclass($source, array $wantedProperties): \stdClass {

		$newObj = new \stdClass();

		foreach ($wantedProperties as $p) {
			$newObj->$p = $source->$p;
		}

		return $newObj;

	}

	/**
	 * Track the user’s password change.
	 */
	public static function passwordChanged(User $subject): bool {

		if (!defined('PAIR_AUDIT_PASSWORD_CHANGED') or !PAIR_AUDIT_PASSWORD_CHANGED) {
			return FALSE;
		}

		$audit = new Audit();
		$audit->event = 'password_changed';
		$audit->details = static::convertToStdclass($subject, ['id','username','name','surname']);

		return $audit->store();
		
	}
	
	public static function loginFailed(string $username, ?string $ipAddress): bool {

		if (!defined('PAIR_AUDIT_LOGIN_FAILED') or !PAIR_AUDIT_LOGIN_FAILED) {
			return FALSE;
		}

		$obj = new \stdClass();
		$obj->username  = $username;
		$obj->ipAddress = $ipAddress;

		$audit = new Audit();
		$audit->event = 'login_failed';
		$audit->details = $obj;

		return $audit->store();

	}

	public static function loginSuccessful(User $user) {

		if (!defined('PAIR_AUDIT_LOGIN_SUCCESSFUL') or !PAIR_AUDIT_LOGIN_SUCCESSFUL) {
			return FALSE;
		}

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'login_successful';
		$audit->details = NULL;

		return $audit->store();

	}

	public static function logout(User $user) {

		if (!defined('PAIR_AUDIT_LOGOUT') or !PAIR_AUDIT_LOGOUT) {
			return FALSE;
		}

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'logout';
		$audit->details = NULL;

		return $audit->store();

	}

	public static function sessionExpired() {

		if (!defined('PAIR_AUDIT_SESSION_EXPIRED') or !PAIR_AUDIT_SESSION_EXPIRED) {
			return FALSE;
		}

		// TODO

	}

	public static function rememberMeLogin() {

		if (!defined('PAIR_AUDIT_REMEMBER_ME_LOGIN') or !PAIR_AUDIT_REMEMBER_ME_LOGIN) {
			return FALSE;
		}

		// TODO

	}

	public static function userBlocked(User $subject) {

		if (!defined('PAIR_AUDIT_USER_BLOCKED') or !PAIR_AUDIT_USER_BLOCKED) {
			return FALSE;
		}

		// TODO

	}

	public static function userCreated(User $subject) {

		if (!defined('PAIR_AUDIT_USER_CREATED') or !PAIR_AUDIT_USER_CREATED) {
			return FALSE;
		}

		// TODO

	}

	public static function userDeleted(User $subject) {

		if (!defined('PAIR_AUDIT_USER_DELETED') or !PAIR_AUDIT_USER_DELETED) {
			return FALSE;
		}

		// TODO

	}

	public static function userChanged(User $subject) {

		if (!defined('PAIR_AUDIT_USER_CHANGED') or !PAIR_AUDIT_USER_CHANGED) {
			return FALSE;
		}

		$wantedProperties = ['id','groupId','localeId','username','name','surname','email','admin','enabled'];

		$audit = new Audit();
		$audit->event = 'user_changed';
		$audit->details = static::convertToStdclass($subject, $wantedProperties);

		return $audit->store();

	}

	public static function permissionsChanged(Group $subject) {

		if (!defined('PAIR_AUDIT_PERMISSIONS_CHANGED') or !PAIR_AUDIT_PERMISSIONS_CHANGED) {
			return FALSE;
		}

		// TODO

	}

}