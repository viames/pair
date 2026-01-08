<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Models\Acl;
use Pair\Models\Session;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;

class Audit extends ActiveRecord {

	/**
	 * The primary key “id” column.
	 */
	protected int $id;

	/**
	 * The user ID this audit is related to.
	 */
	protected ?int $userId = null;

	/**
	 * The event type.
	 */
	protected ?string $event = null;

	/**
	 * The created at timestamp.
	 */
	protected \DateTime $createdAt;

	/**
	 * The details of the audit event.
	 */
	protected ?\stdClass $details = null;

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
	protected function _init(): void {

		$this->bindAsDatetime('createdAt');

		$this->bindAsInteger('id', 'userId');

		$this->bindAsJson('details');

	}

	/**
	 * Add a new ACL into an existent Audit record or create a new one.
	 *
	 * @param  Acl	$acl	Object to set as added.
	 */
	public static function aclAdded(Acl $acl): void {

		// create a new detail item
		$detail = new \stdClass();
		$detail->groupId = $acl->groupId;
		$detail->ruleId  = $acl->ruleId;
		$detail->action  = 'added';

		// save it
		Audit::permissionsChanged($detail);

	}

	/**
	 * Remove an ACL from an existent Audit record or create a new one.
	 *
	 * @param  Acl	$acl	Object to set as removed.
	 */
	public static function aclRemoved(Acl $acl): void {

		// create a new detail item
		$detail = new \stdClass();
		$detail->groupId = $acl->groupId;
		$detail->ruleId  = $acl->ruleId;
		$detail->action  = 'removed';

		// save it
		Audit::permissionsChanged($detail);

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
	 * @return array	List of audit items.
	 */
	public static function getAuditList(): array {

		$events = [
			'impersonate',
			'impersonate_stop',
			'login_failed',
			'login_successful',
			'logout',
			'password_changed',
			'permissions_changed',
			'remember_me_login',
			'session_expired',
			'user_changed',
			'user_created',
			'user_deleted'
		];

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
	 * Get last audit item by the same user and date.
	 * 
	 * @return Audit|null	The last audit item or null if not found.
	 */
	private static function getMyLatestPermissionsChanged(): ?Audit {

		$app = Application::getInstance();

		$query = 'SELECT * FROM `audit` WHERE `event` = "permissions_changed" AND `user_id` = ? AND `created_at` = ?';

		$now = new \DateTime();
		$createdAt = $now->format('Y-m-d H:i:s');

		return Audit::getObjectByQuery($query, [$app->currentUser->id, $createdAt]);

	}

	/**
	 * Track the impersonation action.
	 *
	 * @param	User	$impersonated	The user that is being impersonated.
	 */
	public static function impersonate(User $impersonated): void {

		if (!Env::get('PAIR_AUDIT_IMPERSONATE') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$audit = new Audit();
		$audit->event = 'impersonate';
		$audit->details = (object)['impersonated' => $impersonated->id];

		$audit->store();

	}

	/**
	 * Track the end of an impersonation action.
	 *
	 * @param	User	$impersonatedBy	The user that has been impersonating.
	 */
	public static function impersonateStop(User $impersonatedBy): void {

		if (!Env::get('PAIR_AUDIT_IMPERSONATE_STOP') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$wantedProperties = ['id','username','name','surname'];

		$audit = new Audit();
		$audit->event = 'impersonate_stop';
		$audit->details = (object)['impersonatedBy' => $impersonatedBy->id];

		$audit->store();

	}

	/**
	 * Track the failed login action.
	 *
	 * @param	string		$username	The username used to attempt login.
	 * @param	string|null $ipAddress	The IP address of the user.
	 * @param	string|null $userAgent	The user agent of the user.
	 */
	public static function loginFailed(string $username, ?string $ipAddress, ?string $userAgent): void {

		if (!Env::get('PAIR_AUDIT_LOGIN_FAILED') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$obj = new \stdClass();
		$obj->username  = $username;
		$obj->ipAddress = $ipAddress;
		$obj->userAgent = $userAgent;

		$audit = new Audit();
		$audit->event = 'login_failed';
		$audit->details = $obj;

		$audit->store();

	}

	/**
	 * Track the successful login action.
	 *
	 * @param	User		$user 		The user that has logged in.
	 * @param	string|null $ipAddress	The IP address of the user.
	 * @param	string|null $userAgent	The user agent of the user.
	 */
	public static function loginSuccessful(User $user, ?string $ipAddress, ?string $userAgent): void {

		if (!Env::get('PAIR_AUDIT_LOGIN_SUCCESSFUL') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$obj = new \stdClass();
		$obj->ipAddress = $ipAddress;
		$obj->userAgent = $userAgent;

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'login_successful';
		$audit->details = $obj;

		$audit->store();

	}

	/**
	 * Tracks the logout action.
	 *
	 * @param	User	$user	The user that is logging out.
	 */
	public static function logout(User $user): void {

		if (!Env::get('PAIR_AUDIT_LOGOUT') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'logout';
		$audit->details = null;

		$audit->store();

	}

	/**
	 * Track the user’s password change.
	 * 
	 * @param	User	$subject	The user that changed the password.
	 */
	public static function passwordChanged(User $subject): void {

		if (!Env::get('PAIR_AUDIT_PASSWORD_CHANGED') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$wantedProperties = ['id','username','name','surname'];

		$audit = new Audit();
		$audit->event = 'password_changed';
		$audit->details = $subject->convertToStdclass($wantedProperties);

		$audit->store();

	}

	/**
	 * Add or update an audit record for changed permissions.
	 *
	 * @param	stdClass	$detail	Detail object with groupId, ruleId and action properties.
	 */
	private static function permissionsChanged(\stdClass $detail): void {

		if (!Env::get('PAIR_AUDIT_PERMISSIONS_CHANGED') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$audit = new Audit();
		$audit->event = 'permissions_changed';
		$audit->details = $detail;

		// get last audit item by the same user and date
		$lastAudit = Audit::getMyLatestPermissionsChanged();

		// if exists, merge the details with old ones, save and exit
		if ($lastAudit) {
			$lastAudit->details = (object)array_merge((array)$lastAudit->details, (array)$audit->details);
			$lastAudit->store();
		}

		// otherwise save a new one
		$audit->store();

	}

	/**
	 * Track the remember-me login action.
	 */
	public static function rememberMeLogin(): void {

		if (Env::get('PAIR_AUDIT_REMEMBER_ME_LOGIN') and Env::get('PAIR_AUDIT_ALL')) {

			$audit = new Audit();
			$audit->event = 'remember_me_login';
			$audit->store();
		}

	}

	/**
	 * Track the session expiration.
	 * 
	 * @param	Session	$session	The session that has expired.
	 */
	public static function sessionExpired(Session $session): void {

		if (!Env::get('PAIR_AUDIT_SESSION_EXPIRED') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$user = $session->getUser();

		$audit = new Audit();
		$audit->userId = $user->id;
		$audit->event = 'session_expired';
		$audit->details = null;

		$audit->store();

	}

	/**
	 * Keep track of changed property in user edit.
	 *
	 * @param	User	The old user object.
	 * @param	User	The new user object.
	 */
	public static function userChanged(User $oldUser, User $newUser): void {

		if (!Env::get('PAIR_AUDIT_USER_CHANGED') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
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
			return;
		}

		$audit = new Audit();
		$audit->event = 'user_changed';
		$audit->details = $details;

		$audit->store();

	}

	/**
	 * Track the user creation.
	 * 
	 * @param	User	$subject	The user that has been created.
	 */
	public static function userCreated(User $subject): void {

		if (!Env::get('PAIR_AUDIT_USER_CREATED') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$wantedProperties = ['id','groupId','localeId','username','name','surname','email','admin','enabled'];

		$audit = new Audit();
		$audit->event = 'user_created';
		$audit->details = $subject->convertToStdclass($wantedProperties);

		$audit->store();

	}

	/**
	 * Track the user deletion.
	 * 
	 * @param	User	$subject	The user that has been deleted.
	 */
	public static function userDeleted(User $subject): void {

		if (!Env::get('PAIR_AUDIT_USER_DELETED') and !Env::get('PAIR_AUDIT_ALL')) {
			return;
		}

		$wantedProperties = ['id','groupId','username','name','surname'];

		$audit = new Audit();
		$audit->event = 'user_deleted';
		$audit->details = $subject->convertToStdclass($wantedProperties);

		$audit->store();

	}

}