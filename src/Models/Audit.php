<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Models\Acl;
use Pair\Models\Session;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;

class Audit extends ActiveRecord {
	
	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * This property maps “user_id” column.
	 */
	protected ?int $userId = NULL;

	/**
	 * This property maps “event” column.
	 */
	protected ?string $event = NULL;

	/**
	 * This property maps “created_at” column.
	 */
	protected \DateTime $createdAt;

	/**
	 * This property maps “details” column.
	 */
	protected ?\stdClass $details = NULL;
	
	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'audit';
	
	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['userId'];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init(): void {

		$this->bindAsDatetime('createdAt');

		$this->bindAsInteger('id', 'userId');

		$this->bindAsJson('details');

	}

	/**
	 * Set the current logged-in user and the createdAt value before saving the record into db.
	 */
	protected function beforeCreate() {

		$app = Application::getInstance();

		if (!$this->userId and is_a($app->currentUser, 'Pair\Models\User')) {
			$this->userId = $app->currentUser->id;
		}

	}

	/**
	 * Return a current list and state of all audit items with readable “name”, coded “type” and “enabled”.
	 * 
	 * @return	array
	 */
	public static function getAuditList(): array {

		$events = ['login_failed','login_successful','logout','password_changed','permissions_changed',
				'remember_me_login','session_expired','user_changed','user_created','user_deleted'];

		$list = [];

		foreach($events as $e) {
			$list[] = (object)[
				'type'    => $e,
				'name'    => ucfirst(str_replace('_',' ', $e)),
				'enabled' => constant(strtoupper('PAIR_AUDIT_'.$e))
				];
		}

		return $list;

	}

	/**
	 * Track the user’s password change.
	 */
	public static function passwordChanged(User $subject): bool {

		if (!isset($_ENV['PAIR_AUDIT_PASSWORD_CHANGED']) or !$_ENV['PAIR_AUDIT_PASSWORD_CHANGED']) {
			return FALSE;
		}

		$wantedProperties = ['id','username','name','surname'];

		$audit = new Audit();
		$audit->event = 'password_changed';
		$audit->details = $subject->convertToStdclass($wantedProperties);

		return $audit->store();
		
	}
	
	public static function loginFailed(string $username, ?string $ipAddress, ?string $userAgent): bool {

		if (!isset($_ENV['PAIR_AUDIT_LOGIN_FAILED']) or !$_ENV['PAIR_AUDIT_LOGIN_FAILED']) {
			return FALSE;
		}

		$obj = new \stdClass();
		$obj->username  = $username;
		$obj->ipAddress = $ipAddress;
		$obj->userAgent = $userAgent;

		$audit = new Audit();
		$audit->event = 'login_failed';
		$audit->details = $obj;

		return $audit->store();

	}

	public static function loginSuccessful(User $user, ?string $ipAddress, ?string $userAgent): bool {

		if (!isset($_ENV['PAIR_AUDIT_LOGIN_SUCCESSFUL']) or !$_ENV['PAIR_AUDIT_LOGIN_SUCCESSFUL']) {
			return FALSE;
		}

		$obj = new \stdClass();
		$obj->ipAddress = $ipAddress;
		$obj->userAgent = $userAgent;

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'login_successful';
		$audit->details = $obj;

		return $audit->store();

	}

	public static function logout(User $user): bool {

		if (!isset($_ENV['PAIR_AUDIT_LOGOUT']) or !$_ENV['PAIR_AUDIT_LOGOUT']) {
			return FALSE;
		}

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'logout';
		$audit->details = NULL;

		return $audit->store();

	}

	public static function sessionExpired(Session $session): bool {

		if (!isset($_ENV['PAIR_AUDIT_SESSION_EXPIRED']) or !$_ENV['PAIR_AUDIT_SESSION_EXPIRED']) {
			return FALSE;
		}

		$user = $session->getUser();

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'session_expired';
		$audit->details = NULL;

		return $audit->store();

	}

	public static function rememberMeLogin(): void {

		if (isset($_ENV['PAIR_AUDIT_REMEMBER_ME_LOGIN']) and $_ENV['PAIR_AUDIT_REMEMBER_ME_LOGIN']) {
		
			$audit = new Audit();
			$audit->event = 'remember_me_login';
			$audit->store();
		}

	}

	public static function userCreated(User $subject): bool {

		if (!isset($_ENV['PAIR_AUDIT_USER_CREATED']) or !$_ENV['PAIR_AUDIT_USER_CREATED']) {
			return FALSE;
		}

		$wantedProperties = ['id','groupId','localeId','username','name','surname','email','admin','enabled'];

		$audit = new Audit();
		$audit->event = 'user_created';
		$audit->details = $subject->convertToStdclass($wantedProperties);

		return $audit->store();

	}

	public static function userDeleted(User $subject): bool {

		if (!isset($_ENV['PAIR_AUDIT_USER_DELETED']) or !$_ENV['PAIR_AUDIT_USER_DELETED']) {
			return FALSE;
		}

		$wantedProperties = ['id','groupId','username','name','surname'];

		$audit = new Audit();
		$audit->event = 'user_deleted';
		$audit->details = $subject->convertToStdclass($wantedProperties);

		return $audit->store();

	}

	/**
	 * Keep track of changed property in user edit.
	 * 
	 * @param	User	The old user object.
	 * @param	User	The new user object.
	 */
	public static function userChanged(User $oldUser, User $newUser): bool {

		if (!isset($_ENV['PAIR_AUDIT_USER_CHANGED']) or !$_ENV['PAIR_AUDIT_USER_CHANGED']) {
			return FALSE;
		}

		$details = new \stdClass();
		$details->subjectId = $newUser->id;
		$details->fullName = $newUser->fullName;
		$details->username = $newUser->username;
		$details->changes = [];
		
		$wantedProperties = ['id','groupId','localeId','username','name','surname','email','admin','enabled'];

		foreach ($wantedProperties as $wp) {
			if ($oldUser->$wp != $newUser->$wp) {
				$c = new \stdClass();
				$c->property = $wp;
				$c->oldValue = $oldUser->$wp;
				$c->newValue = $newUser->$wp;
				$details->changes[] = $c;
			}
		}

		// if nothing has changed, avoid storing the record
		if (!count($details->changes)) {
			return FALSE;
		}

		$audit = new Audit();
		$audit->event = 'user_changed';
		$audit->details = $details;

		return $audit->store();

	}

	/**
	 * Add a new ACL into an existent Audit record or create a new one.
	 *
	 * @param  Acl	Object to set as removed.
	 */
	public static function aclAdded(Acl $acl): bool {

		// create a new detail item
		$detail = new \stdClass();
		$detail->groupId = $acl->groupId;
		$detail->ruleId  = $acl->ruleId;
		$detail->action  = 'added';

		// save it
		return Audit::permissionsChanged($detail);

	}

	/**
	 * Remove an ACL from an existent Audit record or create a new one.
	 *
	 * @param  Acl	Object to set as removed.
	 */
	public static function aclRemoved(Acl $acl): bool {

		// create a new detail item
		$detail = new \stdClass();
		$detail->groupId = $acl->groupId;
		$detail->ruleId  = $acl->ruleId;
		$detail->action  = 'removed';

		// save it
		return Audit::permissionsChanged($detail);

	}

	private static function permissionsChanged(\stdClass $detail): bool {

		if (!isset($_ENV['PAIR_AUDIT_PERMISSIONS_CHANGED']) or !$_ENV['PAIR_AUDIT_PERMISSIONS_CHANGED']) {
			return FALSE;
		}

		$audit = new Audit();
		$audit->event = 'permissions_changed';
		$audit->details = $detail;
		
		// get last audit item by the same user and date
		$lastAudit = Audit::getMyLatestPermissionsChanged();
		
		// if exists, merge the details with old ones, save and exit
		if ($lastAudit) {
			$lastAudit->details = array_merge($lastAudit->details, $audit->details);
			return $lastAudit->store();
		}

		// otherwise save a new one
		return $audit->store();

	}

	/**
	 * Get last audit item by the same user and date.
	 */
	private static function getMyLatestPermissionsChanged(): ?Audit {

		$app = Application::getInstance();

		$query = 'SELECT * FROM `audit` WHERE `event` = "permissions_changed" AND `user_id` = ? AND `created_at` = ?';

		$now = new \DateTime();
		$createdAt = $now->format('Y-m-d H:i:s');

		return Audit::getObjectByQuery($query, [$app->currentUser->id, $createdAt]);

	}

}
