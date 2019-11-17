<?php

namespace Pair;

/**
 * Base class for Pair framework users. Can be extended to have more user’s properties.
 */
class User extends ActiveRecord {

	/**
	 * User unique identifier.
	 * @var int
	 */
	protected $id;

	/**
	 * Id group belongs to this user.
	 * @var int
	 */
	protected $groupId;
	
	/**
	 * Id of user locale.
	 * @var int
	 */
	protected $localeId;
	
	/**
	 * Username for local authentication
	 * @var string
	 */
	protected $username;
	
	/**
	 * Password hash.
	 * @var string
	 */
	protected $hash;
	
	/**
	 * User name.
	 * @var string
	 */
	protected $name;
	
	/**
	 * User surname.
	 * @var string
	 */
	protected $surname;
	
	/**
	 * Property that binds db field email.
	 * @var string
	 */
	protected $email;
	
	/**
	 * If TRUE, this user is admin.
	 * @var bool
	 */
	protected $admin;
	
	/**
	 * Flag for user enabled.
	 * @var bool
	 */
	protected $enabled;
	
	/**
	 * Last login’s date, properly converted when inserted into db.
	 * @var DateTime
	 */
	protected $lastLogin;

	/**
	 * Amount of wrong login.
	 * @var int
	 */
	protected $faults;
	
	/**
	 * Token to start password reset.
	 * @var string
	 */
	protected $pwReset;
	
	/**
	 * Time zone offset in hours. Cached.
	 * @var float
	 */
	protected $tzOffset;

	/**
	 * Time zone name. Cached.
	 * @var string
	 */
	protected $tzName;
	
	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'users';
	
	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';
	
	/**
	 * Will returns property’s value if set. Throw an exception and returns NULL if not set.
	 * Name will returns firstName + secondName.
	 *
	 * @param	string	Property’s name.
	 * @throws	Exception
	 * @return	mixed|NULL
	 */
	public function __get($name) {

		switch ($name) {

			case 'fullName':
				return $this->name . ' ' . $this->surname;
				break;

			case 'groupName':
				return $this->getGroup()->name;
				break;
				
			case 'tzName':
				$this->loadTimezone();
				return $this->tzName;
				break;
				
			case 'tzOffset':
				$this->loadTimezone();
				return $this->tzOffset;
				
			default:
				return parent::__get($name);
				break;

		}
	
	}
	
	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function init() {
		
		$this->bindAsBoolean('admin', 'enabled');
		
		$this->bindAsDatetime('lastLogin');
		
		$this->bindAsInteger('id', 'groupId', 'languageId', 'faults');
		
	}
	
	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds() {
		
		$varFields = array (
			'id'		=> 'id',
			'groupId'	=> 'group_id',
			'localeId'	=> 'locale_id',
			'username'	=> 'username',
			'hash'		=> 'hash',
			'name'		=> 'name',
			'surname'	=> 'surname',
			'email'		=> 'email',
			'admin'		=> 'admin',
			'enabled'	=> 'enabled',
			'lastLogin'	=> 'last_login',
			'faults'	=> 'faults',
			'pwReset'	=> 'pw_reset'
		);
		
		return $varFields;
		
	}

	/**
	 * Track the user creation in Audit table just after record saving.
	 */
	protected function afterCreate() {
	
		Audit::userCreated($this);

	}
	
	/**
	 * Deletes sessions of an user before its deletion.
	 */
	protected function beforeDelete() {

		// deletes user sessions
		Database::run('DELETE FROM `sessions` WHERE `id_user` = ?', [$this->id]);
	
		// deletes error_logs of this user
		Database::run('DELETE FROM `error_logs` WHERE `user_id` = ?', [$this->id]);
		
		if ($this->isDeletable()) {
			Audit::userDeleted($this);
		}

	}

	/**
	 * Creates and returns an Hash for user password adding salt.
	 * 
	 * @param	string	The user password.
	 * @return	string	Hashed password
	 * 
	 * @see		http://php.net/crypt
	 */
	public static function getHashedPasswordWithSalt(string $password): string {
		
		// salt for bcrypt needs to be 22 base64 characters (only [./0-9A-Za-z])
		$salt = substr(str_replace('+', '.', base64_encode(sha1(microtime(true), true))), 0, 22);
		
		// 2a = bcrypt algorithm selector, 12 = the workload factor
		$hash = crypt($password, '$2a$12$' . $salt);
		
		return $hash;

	}
	
	/**
	 * Checks if password matches hash for local auth.
	 * 
	 * @param	string	Plain text password.
	 * @param	string	Crypted hash.
	 * @return	boolean
	 */
	public static function checkPassword(string $password, string $hash): bool {

		return ($hash == crypt($password, $hash) ? TRUE : FALSE);
		
	}
	
	/**
	 * Checks if username/password matches a record into database for local auth and returns a
	 * \stdClass with error, message and userId parameters.
	 *
	 * @param	string	Username.
	 * @param	string	Plain text password.
	 * @param	string	IANA time zone identifier.
	 * @return	\stdClass
	 */
	public static function doLogin(string $username, string $password, string $timezone): \stdClass {
	
		$ret = new \stdClass();

		$ret->error		= FALSE;
		$ret->message	= NULL;
		$ret->userId	= NULL;
		$ret->sessionId	= NULL;
		
		$query = 'SELECT * FROM `users` WHERE `' . (PAIR_AUTH_BY_EMAIL ? 'email' : 'username') . '` = ?';
		
		// load user row
		$row = Database::load($query, [$username], PAIR_DB_OBJECT);
	
		// track ip address for audit
		$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL;
	
		if (is_object($row)) {
				
			// load this user
			$user = new static($row);

			// over 9 faults
			if ($user->faults > 9) {
			
				$ret->error = TRUE;
				$ret->message = Translator::do('TOO_MANY_LOGIN_ATTEMPTS');
				$user->addFault();
					
				Audit::loginFailed($username, $ipAddress);
					
			// user disabled
			} else if ('0' == $user->enabled) {

				$ret->error = TRUE;
				$ret->message = Translator::do('USER_IS_DISABLED');
				$user->addFault();
					
				Audit::loginFailed($username, $ipAddress);
					
			// user password doesn’t match
			} else if (!User::checkPassword($password, $user->hash)) {

				$ret->error = TRUE;
				$ret->message = Translator::do('PASSWORD_IS_NOT_VALID');
				$user->addFault();
				
				Audit::loginFailed($username, $ipAddress);
				
			// login ok
			} else {
				
				// creates session for this user
				$user->createSession($timezone);
				$ret->userId = $user->id;
				$ret->sessionId = session_id();
				$user->resetFaults();

				// clear any password-reset
				if (!is_null($user->pwReset)) {
					$user->pwReset = NULL;
					$user->store();
				}
	
				Audit::loginSuccessful($user);

			}
				
		// this username doesn’t exist into db
		} else {
				
			Audit::loginFailed($username, $ipAddress);
				
			$ret->error = TRUE;
			$ret->message = Translator::do('USERNAME_NOT_VALID');
				
		}

		return $ret;
		
	}

	/**
	 * Performs a login for the user passed in parameters. It returns an
	 * stdClass with error, message and userId parameters.
	 *
	 * @param	\Pair\User 	$user
	 * @param	string		$timezone	IANA time zone identifier.
	 * @param	int|NULL	Former user ID.
	 * @return	\stdClass
	 */
	public static function loginAs(User $user, string $timezone, ?int $formerUserId = null): \stdClass {

		$ret = new \stdClass();

		$ret->error		= FALSE;
		$ret->message	= NULL;
		$ret->userId	= NULL;
		$ret->sessionId	= NULL;

		// track ip address for audit
		$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL;

		if ('0' == $user->enabled) {

			$ret->error = TRUE;
			$ret->message = Translator::do('USER_IS_DISABLED');
			$user->addFault();

			Audit::loginFailed($user->email, $ipAddress);

		// user password doesn’t match
		} else {

			// creates session for this user
			$user->createSession($timezone, $formerUserId);
			$ret->userId = $user->id;
			$ret->sessionId = session_id();

			Audit::loginSuccessful($user);
		}

		return $ret;
		
	}
	
	/**
	 * Adds +1 to faults counter property.
	 */
	private function addFault() {
		
		$this->faults++;
		$this->update('faults');
		
	}
	
	/**
	 * Sets to 0 faults counter property.
	 * 
	 * @return	bool
	 */
	public function resetFaults(): bool {
		
		$this->faults = 0;
		return $this->update('faults');
		
	}
	
	/**
	 * Starts a new session, writes on db and updates users table for last login.
	 * Returns true if both db writing has been done succesfully. 
	 * 
	 * @param	string	IANA time zone identifier.
	 * @param	int		Possible ID of the user before impersonation.
	 * @return	bool
	 */
	private function createSession(string $timezone, ?int $formerUserId = null): bool {

		// checks if time zone name is valid
		if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
			$timezone = date_default_timezone_get();
		}
		
		// gets offset by timezone name
		$dt = new \DateTime('now', new \DateTimeZone($timezone));
		$diff = $dt->format('P');
		list ($hours, $mins) = explode(':', $diff);
		$offset = (float)$hours + ($hours > 0 ? ($mins / 60) : -($mins / 60));

		// creates session
		$session = new Session();

		$session->id				= session_id();
		$session->idUser			= $this->id;
		$session->startTime			= new \DateTime();
		$session->timezoneOffset	= $offset;
		$session->timezoneName		= $timezone;
		$session->formerUserId		= $formerUserId;
		
		$res1 = $session->create();
		
		// updates last user login
		$this->lastLogin	= new \DateTime();
		$this->tzOffset		= $offset;
		$this->tzName		= $timezone;
		
		$res2 = $this->update('lastLogin');

		return ($res1 and $res2);
		
	}
	
	/**
	 * Does the logout action and returns TRUE if session is found and deleted. 
	 * 
	 * @param	string	Session ID to close.
	 * @return	bool
	 */
	public static function doLogout(string $sid): bool {

		$app = Application::getInstance();
		$db  = Database::getInstance();

		// get User object by Session
		$session = new Session($sid);
		$user = $session->getUser();

		if (is_null($user)) {
			return FALSE;
		}

		// record the logout
		Audit::logout($user);

		// delete session
		$res = Database::run('DELETE FROM `sessions` WHERE id = ?', [$sid]);

		// unset all persistent states
		$app->unsetAllPersistentStates();

		// unset RememberMe
		$app->currentUser->unsetRememberMe();
		
		// reset the user in Application object
		$app->currentUser = NULL;
		
		return (bool)$res;
	
	}
	
	/**
	 * It will returns DateTimeZone object for this User.
	 *
	 * @return DateTimeZone
	 */
	public function getDateTimeZone(): \DateTimeZone {
	
		$this->loadTimezone();
	
		// tzName is still NULL for guest users
		return new \DateTimeZone($this->tzName ? $this->tzName : BASE_TIMEZONE);
	
	}
	
	/**
	 * If time zone name or offset is null, will loads from session table their values and
	 * populates this object cache properties.
	 */
	private function loadTimezone() {
		
		if (!is_null($this->id) and (is_null($this->tzName) or is_null($this->tzOffset))) {
			
			$this->db->setQuery('SELECT timezone_name, timezone_offset FROM `sessions` WHERE id_user = ?');
			$obj = $this->db->loadObject([$this->id]);
			$this->tzOffset	= $obj->timezone_offset;
			$this->tzName	= $obj->timezone_name;
			
		}
		
	}
	
	/**
	 * Check if this user has access permission to a module and optionally to a specific action.
	 * Admin can access everything. This method use cache variable to load once from db.
	 * 
	 * @param	string	Module name.
	 * @param	string	Optional action name.
	 * @return	bool	True if access is granted.
	 */
	public function canAccess(string $module, string $action=NULL): bool {

		// reveal module/action type
		if (is_null($action) and FALSE !== strpos($module, '/')) {
			list($module,$action) = explode('/', $module);
		}

		// check if it’s a custom route
		$router = Router::getInstance();
		$url = '/' . $module . ($action ? '/' . $action : '');
		$res = $router->getModuleActionFromCustomUrl($url);
		
		// in case, overwrite module and action
		if (is_a($res, 'stdClass')) {
			$module = $res->module;
			$action = $res->action;
		}

		// user module is for login and personal profile
		if ('user'==$module) {
			return TRUE;
		}
		
		// acl is cached
		$acl = $this->getAcl();

		foreach ($acl as $rule) {
			if ($rule->moduleName == $module and (($rule->adminOnly and $this->admin) or !$rule->action or ($rule->action and $rule->action == $action))) {
				return TRUE;
			}
		}
		
		return FALSE;
		
	}
	
	/**
	 * Load the rule list for this user. Cached.
	 * 
	 * @return	Rule[]|NULL
	 */
	private function getAcl(): ?array {

		if (!$this->issetCache('acl')) {
		
			$query =
				'SELECT r.*, m.name AS module_name' .
				' FROM `rules` AS r' .
				' INNER JOIN `acl` AS a ON a.rule_id = r.id'.
				' INNER JOIN `modules` AS m ON r.module_id = m.id'.
				' WHERE a.group_id = ?';
			
			$this->setCache('acl', Rule::getObjectsByQuery($query, [$this->groupId]));

		}

		return $this->getCache('acl');
		
	}
	
	/**
	 * Get landing module and action as object properties where the user goes after login.
	 *
	 * @return \stdClass|NULL
	 */
	public function getLanding(): ?\stdClass {
		
		$query =
			' SELECT m.`name` AS module, r.action' .
			' FROM `acl` AS a' .
			' INNER JOIN `rules` AS r ON r.id = a.rule_id' .
			' INNER JOIN `modules` AS m ON m.id = r.module_id' .
			' WHERE a.is_default = 1' . 
			' AND a.group_id = ?';

		return Database::load($query, [$this->groupId], PAIR_DB_OBJECT);
		
	}
	
	/**
	 * Redirect user’s browser to his default landing web-page.
	 */
	public function redirectToDefault() {
		
		$app	 = Application::getInstance();
		$landing = $this->getLanding();
	
		$app->redirect($landing->module . '/' . $landing->action);
		
	}
	
	/**
	 * Return the language code of this user. Cached.
	 *
	 * @return	string|NULL
	 */
	public function getLanguageCode(): ?string {

		if (!$this->issetCache('lang')) {
	
			$query =
				'SELECT l.code' .
				' FROM `languages` AS l' .
				' INNER JOIN `locales` AS lc ON l.id = lc.language_id' .
				' INNER JOIN `users` AS u ON u.locale_id = lc.id' .
				' WHERE u.id = ?';
			
			$this->setCache('lang', Database::load($query, [$this->id], PAIR_DB_RESULT));

		}
	
		return $this->getCache('lang');

	}
	
	/**
	 * Get Group object for this user. Cached.
	 *
	 * @return Group
	 */
	public function getGroup(): Group {
	
		if (!$this->issetCache('group')) {
			$this->setCache('group', new Group($this->groupId));
		}
	
		return $this->getCache('group');
	
	}
	
	/**
	 * Join the user’s name and surname and return it
	 * 
	 * @return	string
	 */
	public function getFullName(): string {
		
		return $this->name . ' ' . $this->surname;
		
	}
	
	/**
	 * Check if the localeId parameter has been set and returns TRUE if so.
	 * 
	 * @return boolean
	 */
	public function isLocaleSet(): bool {
		
		return (bool)$this->localeId;
		
	}
	
	/**
	 * Returns the Locale object for this user, if set, otherwise the default Locale.
	 * 
	 * @return Locale
	 */
	public function getLocale(): Locale {
		
		if ($this->isLocaleSet()) {
			return new Locale($this->localeId);
		} else {
			return Locale::getDefault();
		}
		
	}
	
	/**
	 * Check whether record of this object is deletable based on inverse foreign-key list
	 * and the user is not the same connected.
	 *
	 * @return	bool
	 */
	public function isDeletable(): bool {
		
		$app = Application::getInstance();
		
		if ($this->id == $app->currentUser->id) {
			return FALSE;
		}
		
		return parent::isDeletable();
		
	}
	
	/**
	 * Return an user that matches pw_reset string. NULL if not found.
	 * 
	 * @param	string		PwReset value.
	 * @return	User|NULL
	 */
	public static function getByPwReset(string $pwReset): ?User {
		
		$query =
			'SELECT *' .
			' FROM `users`' .
			' WHERE `pw_reset` IS NOT NULL' . 
			' AND `pw_reset` = ?'; 
		
		return static::getObjectByQuery($query, [$pwReset]);
		
	}
	
	/**
	 * Apply a password reset for this User.
	 * 
	 * @param	string	New password to set.
	 * @param	string	IANA time zone identifier.
	 * @return	bool
	 */
	public function setNewPassword(string $newPassword, string $timezone): bool {
		
		$this->pwReset = NULL;
		$this->hash = static::getHashedPasswordWithSalt($newPassword);
		
		if (!$this->store()) {
			return FALSE;
		}
		
		// creates session for this user
		$this->createSession($timezone);
		$this->resetFaults();
		
		return TRUE;
		
	}
	
	/**
	 * Create a remember-me object, store it into DB and set the browser’s cookie.
	 * 
	 * @param	string	IANA time zone identifier.
	 * @return	bool
	 */
	public function createRememberMe(string $timezone): bool {
		
		// set a random string
		$ur = new UserRemember();
		$ur->userId = $this->id;
		$ur->rememberMe = Utilities::getRandomString(32);
		$ur->createdAt = new \DateTime('now', new \DateTimeZone($timezone));
		
		if (!$ur->store()) {
			return FALSE;
		}

		// serialize an array with timezone and RememberMe string
		$content = serialize([$timezone, $ur->rememberMe]);
		
		// expire in 30 days
		$expire = time() + 60*60*24*30;
		
		// set cookie and return the result
		return setcookie(static::getRememberMeCookieName(), $content, $expire, '/');
		
	}

	/**
	 * Update the expire date of RememberMe cookie.
	 * 
	 * @return boolean
	 */
	public function renewRememberMe() {
		
		// build the cookie name
		$cookieName = static::getRememberMeCookieName();
		
		// check if cookie exists
		if (!isset($_COOKIE[$cookieName])) {
			return FALSE;
		}

		$cookieContent = static::getRememberMeCookieContent();
		
		// delete any DB record with the current user and remember-me
		Database::run('UPDATE `users_remembers` SET `created_at` = NOW() WHERE `user_id` = ? AND `remember_me` = ?', [$this->id, $cookieContent->rememberMe]);
		
		// expire in 30 days
		$expire = time() + 60*60*24*30;

		// set cookie and return the result
		return setcookie($cookieName, $_COOKIE[$cookieName], $expire, '/');
		
	}

	/**
	 * Return an user that matches remember_me string if created less than 1 month ago. NULL if not found.
	 *
	 * @param	string		RememberMe value.
	 * @return	User|NULL
	 */
	private static function getByRememberMe(string $rememberMe): ?User {

		// delete older remember-me DB records
		Database::run('DELETE FROM `users_remembers` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 1 MONTH');

		$query =
			'SELECT u.*' .
			' FROM `users` AS u' .
			' INNER JOIN `users_remembers` AS ur ON u.`id` = ur.`user_id`' .
			' WHERE ur.`remember_me` = ?';
		
		$userClass = PAIR_USER_CLASS;
		
		return $userClass::getObjectByQuery($query, [$rememberMe]);
		
	}
	
	/**
	 * Check if the browser’s cookie contains a RememberMe. In case, do an auto-login.
	 * 
	 * @return bool
	 */
	public static function loginByRememberMe(): bool {
		
		// get the cookie content
		$cookieContent = static::getRememberMeCookieContent();
	
		// check if cookie exists
		if (is_null($cookieContent)) {
			return FALSE;
		}

		// try to load user
		$user = static::getByRememberMe($cookieContent->rememberMe);
		
		// if user exists, return it
		if (is_a($user, 'Pair\User')) {
			$user->createSession($cookieContent->timezone);
			$user->renewRememberMe();
			$app = Application::getInstance();
			$app->setCurrentUser($user);
			return TRUE;
		}

		// login unsucceded
		return FALSE;
	
	}
	
	/**
	 * Delete DB record and browser’s cookie because when logout is invoked.
	 * 
	 * @return bool
	 */
	private function unsetRememberMe(): bool {
		
		// build the cookie name
		$cookieContent = $this->getRememberMeCookieContent();
		
		// check if cookie exists
		if (is_null($cookieContent)) {
			return FALSE;
		}

		// delete the current remember-me DB record
		Database::run('DELETE FROM `users_remembers` WHERE `user_id` = ? AND `remember_me` = ?', [$this->id, $cookieContent->rememberMe]);
		
		// delete the current remember-me Cookie
		return setcookie(static::getRememberMeCookieName(), '', -1, '/');
		
	}
	
	/**
	 * Build and return the cookie name.
	 * 
	 * @return string
	 */
	private static function getRememberMeCookieName(): string {
		
		return Application::getCookiePrefix() . 'RememberMe';
		
	}
	
	/**
	 * Return TRUE if this user or the the former User object if impersonating, is admin.
	 * 
	 * @return	bool
	 */
	public function isAdmin(): bool {

		if ($this->admin) {
			return TRUE;
		} else {
			$query =
				'SELECT COUNT(1) FROM `users`' .
				' INNER JOIN `sessions` AS s ON u.id = s.id_user' .
				' WHERE s.id = ? AND u.id = ? AND admin = 1';
			return (bool)Database::load($query, [Session::current(), $this->id], PAIR_DB_COUNT);
		}

	}
	
	/**
	 * Return the current Application connected User object or its child.
	 * 
	 * @return	User
	 */
	public static function current(): self {

		$app = Application::getInstance();
		return $app->currentUser;

	}

	/**
	 * Utility to unserialize and return the remember-me cookie content {timezone, rememberMe}.
	 * 
	 * @return	\stdClass|NULL
	 */
	private static function getRememberMeCookieContent(): ?\stdClass {

		// build the cookie name
		$cookieName = static::getRememberMeCookieName();

		// check if cookie exists
		if (!isset($_COOKIE[$cookieName])) {
			return NULL;
		}
			
		// try to unserialize the cookie content
		$content = unserialize($_COOKIE[$cookieName]);
		
		// cookie content is not unserializable
		if (FALSE === $content) {
			return  NULL;
		}

		// check if content exists and RememberMe length
		if (isset($content[0]) and isset($content[1]) and 32==strlen($content[1])) {
			$obj = new \stdClass();
			$obj->timezone = $content[0];
			$obj->rememberMe = $content[1];
			return $obj;
		}
		
		return NULL;

	}
	
}