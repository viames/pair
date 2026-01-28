<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Core\Router;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;
use Pair\Orm\Database;
use Pair\Models\Audit;
use Pair\Models\Locale;
use Pair\Models\Rule;
use Pair\Models\Session;
use Pair\Models\UserRemember;

/**
 * Base ActiveRecord model for application users. Handles authentication, access control (ACL),
 * sessions, time zone handling and "remember me" functionality. Can be extended to add custom
 * properties and behaviour.
 */
class User extends ActiveRecord {

	/**
	 * User unique identifier.
	 */
	protected int $id;

	/**
	 * ID of the group this user belongs to.
	 */
	protected int $groupId;

	/**
	 * ID of the locale associated with this user.
	 */
	protected int $localeId;

	/**
	 * Username for local authentication
	 */
	protected string $username;

	/**
	 * Password hash.
	 */
	protected string $hash;

	/**
	 * User name.
	 */
	protected string $name;

	/**
	 * User surname.
	 */
	protected string $surname;

	/**
	 * Property that binds db field email.
	 */
	protected ?string $email = null;

	/**
	 * If true, this user is super user.
	 */
	protected bool $super;

	/**
	 * Flag for user enabled.
	 */
	protected bool $enabled;

	/**
	 * Last login’s date, properly converted when inserted into db.
	 */
	protected ?\DateTime $lastLogin = null;

	/**
	 * Amount of wrong login.
	 */
	protected int $faults = 0;

	/**
	 * Token to start password reset.
	 */
	protected ?string $pwReset = null;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'users';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['groupId', 'localeId'];

	/**
	 * Magic getter for virtual and inherited properties.
	 *
	 * Handles a few virtual properties:
	 * - fullName   → "{$name} {$surname}"
	 * - groupName  → related Group name
	 *
	 * For any other property, delegates to the parent ActiveRecord::__get().
	 *
	 * @param  string $name Property name.
	 * @return mixed        Resolved value.
	 */
	public function __get(string $name): mixed {

		if (isset($this->id) and in_array($name, ['fullName', 'groupName'])) {

			switch ($name) {

				case 'fullName':
					return $this->name . ' ' . $this->surname;

				case 'groupName':
					return $this->getGroup()->name;

			}

		}

		return parent::__get($name);

	}

	/**
	 * Initializes field bindings for automatic type casting.
	 *
	 * Marks selected properties as boolean, integer or DateTime so they
	 * are converted correctly when loading from or saving to the database.
	 */
	protected function _init(): void {

		$this->bindAsBoolean('super', 'enabled');

		$this->bindAsDatetime('lastLogin');

		$this->bindAsInteger('id', 'groupId', 'localeId', 'faults');

	}

	/**
	 * Adds +1 to faults counter property.
	 */
	private function addFault() {

		$this->faults++;
		$this->update('faults');

	}

	/**
	 * Track the user creation in Audit table just after record saving.
	 */
	protected function afterCreate() {

		Audit::userCreated($this);

	}

	/**
	 * Hook called after login().
	 */
	protected function afterLogin() {}

	/**
	 * Hook called after logout().
	 */
	protected function afterLogout() {}

	/**
	 * Regenerates the session ID if the session is active and headers are not sent.
	 */
	private function regenerateSessionId(): void {

		if (session_status() === PHP_SESSION_ACTIVE and !headers_sent()) {
			session_regenerate_id(true);
		}

	}

	/**
	 * Hook called after remember-me token creation.
	 */
	protected function afterRememberMeCreate() {}

	/**
	 * Hook called after remember-me login.
	 */
	protected function afterRememberMeLogin() {}

	/**
	 * Hook called after remember-me token renewal.
	 */
	protected function afterRememberMeRenew() {}

	/**
	 * Hook called after remember-me token revocation.
	 */
	protected function afterRememberMeUnset() {}

	/**
	 * Hook called before remember-me token creation.
	 */
	protected function beforeRememberMeCreate() {}

	/**
	 * Hook called before remember-me login.
	 */
	protected function beforeRememberMeLogin() {}

	/**
	 * Hook called before remember-me token renewal.
	 */
	protected function beforeRememberMeRenew() {}

	/**
	 * Hook called before remember-me token revocation.
	 */
	protected function beforeRememberMeUnset() {}

	/**
	 * Returns the HTML code for the user avatar (initials with colored background).
	 *
	 * @return string HTML code for the user avatar.
	 */
	public function avatar(): string {

		// verify name and surname
		if (!isset($this->name) or !isset($this->surname) or $this->name === '' or $this->surname === '') {
			return '';
		}

		// initials of the name
		$initials = strtoupper(substr($this->name, 0, 1) . substr($this->surname, 0, 1));

		// color based on user id (to keep consistency across sessions)
		$colorInt = crc32((string)$this->id) & 0xFFFFFF;
		$bgColor = sprintf('#%06X', $colorInt);

		// white or black text based on background color brightness
		$textColor = Utilities::isDarkColor($bgColor) ? '#FFF' : '#000';

		return '<span class="avatar-circle" style="background-color:' . $bgColor . ';color:' . $textColor . '">' . $initials . '</span>';

	}

	/**
	 * Cleans up related records before deleting the user. Deletes all sessions and error logs
	 * associated with this user and, when the user is deletable, writes an audit log entry.
	 */
	protected function beforeDelete(): void {

		// deletes user sessions
		Database::run('DELETE FROM `sessions` WHERE `user_id` = ?', [$this->id]);

		// deletes error_logs of this user
		Database::run('DELETE FROM `error_logs` WHERE `user_id` = ?', [$this->id]);

		if ($this->isDeletable()) {
			Audit::userDeleted($this);
		}

	}

	/**
	 * Hook called before login().
	 */
	protected function beforeLogin() {}

	/**
	 * Hook called before logout().
	 */
	protected function beforeLogout() {}

	/**
	 * Checks whether this user has permission to access a module/action.
	 *
	 * Super users can access everything. The ACL is loaded once from the
	 * database and cached. The $module parameter can be either:
	 * - "module"           (with $action provided separately), or
	 * - "module/action"    (in which case $action can be omitted).
	 *
	 * @param string      $module Module name, or "module/action".
	 * @param string|null $action Optional action name.
	 * @return bool               True if access is allowed, false otherwise.
	 */
	public function canAccess(string $module, ?string $action = null): bool {

		// patch for public folder content
		if ('public' == $module) {
			return true;
		}

		// super users bypass ACL
		if ($this->__get('super')) {
			return true;
		}

		// reveal module/action type
		if ((is_null($action) or '' === $action) and false !== strpos($module, '/')) {
			list($module,$action) = explode('/', $module);
		}

		// check if it’s a custom route
		$router = Router::getInstance();
		$url = '/' . $module . ($action ? '/' . $action : '');
		$res = $router->getModuleActionFromCustomUrl($url);

		// in case, overwrite module and action
		if (is_a($res, '\stdClass')) {
			$module = $res->module;
			$action = $res->action;
		}

		// user module is for login and personal profile
		if ('user'==$module) {
			return true;
		}

		// acl is a cached Collection of Rule objects
		$acl = $this->getAcl();

		$actionIsEmpty = (is_null($action) or $action === '');

		// loop through rules in order to find a match
		foreach ($acl as $rule) {
			if ($rule->moduleName != $module) {
				continue;
			}

			if ($rule->superOnly) {
				continue;
			}

			$ruleAction = $rule->action;
			if (
				is_null($ruleAction) or
				$ruleAction === '' or
				$ruleAction === $action or
				($ruleAction == 'default' and $actionIsEmpty)
			) {
				return true;
			}

		}

		return false;

	}

	/**
	 * Checks if password matches hash for local auth.
	 *
	 * @param	string	Plain text password.
	 * @param	string	Crypted hash.
	 */
	public static function checkPassword(string $password, string $hash): bool {

		$info = password_get_info($hash);

		if (!empty($info['algo'])) {
			return password_verify($password, $hash);
		}

		return ($hash == crypt($password, $hash));

	}

	/**
	 * Create a remember-me object, store it into DB and set the browser’s cookie.
	 *
	 * @param	string	IANA time zone identifier.
	 */
	public function createRememberMe(string $timezone): bool {

		// hook for tasks to be executed before remember-me token creation
		$this->beforeRememberMeCreate();

		$dateTimeZone = User::getValidTimeZone($timezone);

		// set a random string
		$ur = new UserRemember();
		$ur->userId = $this->id;
		$ur->rememberMe = self::getSecureRandomString(32);
		$ur->createdAt = new \DateTime('now', $dateTimeZone);

		if (!$ur->store()) {
			return false;
		}

		// serialize an array with timezone and RememberMe string
		$content = serialize([$timezone, $ur->rememberMe]);

		// expire in 30 days 2592000
		$expires = time() + 2592000;

		// set cookie and return the result
		$result = setcookie(self::getRememberMeCookieName(), $content, Application::getCookieParams($expires));

		// hook for tasks to be executed after remember-me token creation
		$this->afterRememberMeCreate();

		return $result;

	}

	/**
	 * Starts a new session and persists it to the database.
	 *
	 * Creates a Session record, updates the user's last login info and,
	 * when impersonating, stores the former user ID as well.
	 *
	 * @param string   $timezone     IANA time zone identifier.
	 * @param int|null $formerUserId Optional ID of the user before impersonation.
	 */
	private function createSession(string $timezone, ?int $formerUserId = null): void {

		// prevent session fixation on auth flows
		$this->regenerateSessionId();

		// gets a valid DateTimeZone object
		$dateTimeZone = User::getValidTimeZone($timezone);

		// gets offset by timezone name
		$offset = (new \DateTime('now', $dateTimeZone))->getOffset() / 3600;

		$session = new Session();

		$session->id				= session_id();
		$session->userId			= $this->id;
		$session->startTime			= new \DateTime();
		$session->timezoneOffset	= $offset;
		$session->timezoneName		= $timezone;
		$session->formerUserId		= $formerUserId;

		$session->create();

		// deletes all other sessions for this user
		if (Env::get('PAIR_SINGLE_SESSION')) {
			Database::run('DELETE FROM `sessions` WHERE `user_id` = ? AND `id` != ?', [$this->id, session_id()]);
		}

		// updates last user login
		$this->lastLogin = new \DateTime();
		$this->update('lastLogin');

	}

	/**
	 * Return the current Application connected User object or its child, null otherwise.
	 */
	public static function current(): ?static {

		$app = Application::getInstance();
		return $app->currentUser;

	}

	/**
	 * Does the login action and returns an object with error, message, userId and sessionId
	 * properties.
	 *
	 * @param	string		$username	Username.
	 * @param	string		$password	Plain text password.
	 * @param	string		$timezone	IANA time zone identifier.
	 * @return	\stdClass				Object with error, message, userId and sessionId properties.
	 */
	public static function doLogin(string $username, string $password, string $timezone): \stdClass {

		$ret = new \stdClass();

		$ret->error		= false;
		$ret->message	= null;
		$ret->userId	= null;
		$ret->sessionId	= null;

		$genericMessage = Translator::do('AUTHENTICATION_FAILED');

		$query = 'SELECT * FROM `users` WHERE `' . (Env::get('PAIR_AUTH_BY_EMAIL') ? 'email' : 'username') . '` = ?';

		// load user row
		$row = Database::load($query, [$username], Database::OBJECT);

		// track ip address and user_agent for audit
		$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

		if (is_object($row)) {

			// load this user
			$user = new static($row);

			// over 9 faults
			if ($user->faults > 9) {

				$ret->error = true;
				$ret->message = $genericMessage;
				$user->addFault();

				Audit::loginFailed($username, $ipAddress, $userAgent);

			// user disabled
			} else if ('0' == $user->enabled) {

				$ret->error = true;
				$ret->message = $genericMessage;
				$user->addFault();

				Audit::loginFailed($username, $ipAddress, $userAgent);

			// user password doesn’t match
			} else if (!User::checkPassword($password, $user->hash)) {

				$ret->error = true;
				$ret->message = $genericMessage;
				$user->addFault();

				Audit::loginFailed($username, $ipAddress, $userAgent);

			// login ok
			} else {

				// hook for tasks to be executed before login
				$user->beforeLogin();

				// creates session for this user
				$user->createSession($timezone);
				$ret->userId = $user->id;
				$ret->sessionId = session_id();
				$user->resetFaults();

				// clear any password-reset
				if (!is_null($user->pwReset)) {
					$user->pwReset = null;
					$user->store();
				}

				// hook for tasks to be executed after login
				$user->afterLogin();

				$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

				Audit::loginSuccessful($user, $ipAddress, $userAgent);

			}

		// this username doesn’t exist into db
		} else {

			Audit::loginFailed($username, $ipAddress, $userAgent);

			$ret->error = true;
			$ret->message = $genericMessage;

		}

		return $ret;

	}

	/**
	 * Does the logout action and returns true if session is found and deleted.
	 *
	 * @param	string	Session ID to close.
	 */
	public static function doLogout(string $sid): bool {

		// get User object by Session
		$session = new Session($sid);
		$user = $session->getUser();

		if (is_null($user)) {
			return false;
		}

		// hook for tasks to be executed before logout
		$user->beforeLogout();

		// record the logout
		Audit::logout($user);

		// delete session
		$res = Database::run('DELETE FROM `sessions` WHERE `id` = ?', [$sid]);

		// unset all persistent states
		$app = Application::getInstance();
		$app->unsetAllPersistentStates();

		// unset RememberMe
		$user->unsetRememberMe();

		// reset the user in Application object
		$app->currentUser = null;

		// hook for tasks to be executed after logout
		$user->afterLogout();

		return (bool)$res;

	}

	/**
	 * Returns the user's full name as "name surname".
	 */
	public function fullName(): string {

		return $this->name . ' ' . $this->surname;

	}

	/**
	 * Loads the ACL rules for this user from the database.
	 *
	 * Returns a Collection of Rule entries and caches the result
	 * for subsequent calls.
	 *
	 * @return Collection
	 */
	private function getAcl(): Collection {

		if (!$this->issetCache('acl')) {

			$query =
				'SELECT r.*, m.`name` AS `module_name`
				FROM `rules` AS r
				INNER JOIN `acl` AS a ON a.`rule_id` = r.`id`
				INNER JOIN `modules` AS m ON r.`module_id` = m.`id`
				WHERE a.`group_id` = ?';

			$this->setCache('acl', Rule::getObjectsByQuery($query, [$this->__get('groupId')]));

		}

		return $this->getCache('acl');

	}

	/**
	 * Returns the mapping between object properties and database fields.
	 *
	 * @return array<string,string> [propertyName => db_field_name]
	 */
	protected static function getBinds(): array {

		return [
			'id'		=> 'id',
			'groupId'	=> 'group_id',
			'localeId'	=> 'locale_id',
			'username'	=> 'username',
			'hash'		=> 'hash',
			'name'		=> 'name',
			'surname'	=> 'surname',
			'email'		=> 'email',
			'super'		=> 'super',
			'enabled'	=> 'enabled',
			'lastLogin'	=> 'last_login',
			'faults'	=> 'faults',
			'pwReset'	=> 'pw_reset'
		];

	}

	/**
	 * Returns the user matching the given password reset token, if any.
	 *
	 * @param  string $pwReset Password reset token value.
	 * @return User|null       Matching user or null if not found.
	 */
	public static function getByPwReset(string $pwReset): ?User {

		$query = 'SELECT * FROM `users` WHERE `pw_reset` IS NOT NULL AND `pw_reset` = ?';

		return static::getObjectByQuery($query, [$pwReset]);

	}

	/**
	 * Returns a DateTimeZone instance for this user.
	 *
	 * If the user has no stored time zone, falls back to BASE_TIMEZONE.
	 */
	public function getDateTimeZone(): \DateTimeZone {

		$session = Session::current();

		return new \DateTimeZone($session ? $session->timezoneName : BASE_TIMEZONE);

	}

	/**
	 * Returns the Group this user belongs to.
	 */
	public function getGroup(): Group {

		return new Group($this->__get('groupId'));

	}

	/**
	 * Creates and returns a bcrypt password hash.
	 *
	 * @param	string	The user password.
	 * @return	string	Hashed password.
	 */
	public static function getHashedPasswordWithSalt(string $password): string {

		$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

		if ($hash === false) {
			try {
				$salt = substr(str_replace('+', '.', base64_encode(random_bytes(16))), 0, 22);
			} catch (\Throwable $e) {
				$salt = substr(str_replace('+', '.', base64_encode(sha1(microtime(true), true))), 0, 22);
			}

			$hash = crypt($password, '$2y$12$' . $salt);
		}

		return $hash;

	}

	/**
	 * Returns a cryptographically secure random string using the allowed charset.
	 *
	 * @param int $length Desired string length.
	 */
	private static function getSecureRandomString(int $length): string {

		$chars = Utilities::RANDOM_STRING_CHARS;
		$maxIndex = strlen($chars) - 1;
		$value = '';

		try {
			for ($i = 0; $i < $length; $i++) {
				$value .= $chars[random_int(0, $maxIndex)];
			}
		} catch (\Throwable $e) {
			return Utilities::getRandomString($length);
		}

		return $value;

	}

	/**
	 * Return the language code of this user. Cached.
	 */
	public function getLanguageCode(): ?string {

		if (!$this->issetCache('lang')) {

			$query =
				'SELECT l.`code`
				FROM `languages` AS l
				INNER JOIN `locales` AS lc ON l.`id` = lc.`language_id`
				INNER JOIN `users` AS u ON u.`locale_id` = lc.`id`
				WHERE u.`id` = ?';

			$this->setCache('lang', Database::load($query, [$this->id], Database::RESULT));

		}

		return $this->getCache('lang');

	}

	/**
	 * Returns the Locale object for this user, if set, otherwise the default Locale.
	 */
	public function getLocale(): Locale {

		if ($this->isLocaleSet()) {
			return new Locale($this->localeId);
		} else {
			return Locale::getDefault();
		}

	}

	/**
	 * Utility to unserialize and return the remember-me cookie content {timezone, rememberMe}.
	 */
	private static function getRememberMeCookie(): ?\stdClass {

		// try to unserialize the cookie content
		$app = Application::getInstance();
		$content = $app->getPersistentState('RememberMe');

		$regex = '#^[' . Utilities::RANDOM_STRING_CHARS . ']{32}$#';

		// check if content exists and RememberMe length
		if (is_array($content) and isset($content[0]) and isset($content[1]) and preg_match($regex, (string)$content[1])) {
			$obj = new \stdClass();
			$obj->timezone = ($content[0] and in_array($content[0], \DateTimeZone::listIdentifiers()))
				? $content[0] : 'UTC';
			$obj->rememberMe = (string)$content[1];
			return $obj;
		}

		return null;

	}

	/**
	 * Build and return the cookie name.
	 */
	private static function getRememberMeCookieName(): string {

		return Application::getCookiePrefix() . 'RememberMe';

	}

	/**
	 * Validates a time zone identifier and returns a DateTimeZone instance.
	 *
	 * If the given identifier is not in the list of valid time zones,
	 * falls back to the system default time zone.
	 *
	 * @param  string $timezone IANA time zone identifier.
	 * @return \DateTimeZone
	 */
	public static function getValidTimeZone(string $timezone): \DateTimeZone {

		// checks if time zone name is valid
		if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
			$timezone = date_default_timezone_get();
		}

		return new \DateTimeZone($timezone);

	}

	/**
	 * Starts impersonating another user.
	 *
	 * Creates a new session for $newUser, storing the current user as the "former" user so
	 * that impersonation can be stopped later.
	 *
	 * @param User $newUser User to impersonate.
	 */
	public function impersonate(User $newUser): void {

		$session = Session::current();
		$session->formerUserId = $this->id;
		$session->userId = $newUser->id;
		$session->store();

		Audit::impersonate($newUser);

	}

	/**
	 * Stops impersonation and restores the former user session.
	 *
	 * @throws \Exception If no active session or no former user is found.
	 */
	public function impersonateStop(): void {

		$session = Session::current();
		if (is_null($session)) {
			throw new \Exception('No active session found.');
		}

		$impersonatedUser = $session->getUser();
		$formerUser = $session->getFormerUser();

		if (!$formerUser) {
			throw new \Exception('No former user to stop impersonation.');
		}

		Database::run('UPDATE `sessions` SET `user_id` = `former_user_id`, `former_user_id` = NULL WHERE `id` = ?', [session_id()]);

		Audit::impersonateStop($impersonatedUser, $formerUser);

	}

	/**
	 * Returns true if this user, or the former user (when impersonating), is super user.
	 */
	public function isSuper(): bool {

		if ($this->__get('super')) {
			return true;
		}

		// check former user when impersonating
		$session = Session::current();
		if (is_null($session)) {
			return false;
		}

		// get former user
		$formerUser = $session->getFormerUser();
		if ($formerUser?->super) {
			return true;
		}

		return false;

	}

	/**
	 * Checks whether this user can be safely deleted.
	 *
	 * A user is deletable if:
	 * - no inverse foreign-key constraints prevent deletion, and
	 * - it is not the same user as the currently logged-in user.
	 */
	public function isDeletable(): bool {

		$app = Application::getInstance();

		if ($this->id == $app->currentUser->id) {
			return false;
		}

		return parent::isDeletable();

	}

	/**
	 * Check if the localeId parameter has been set and returns true if so.
	 */
	public function isLocaleSet(): bool {

		return isset($this->localeId) ? (bool)$this->localeId : false;

	}

	/**
	 * Returns the default landing module/action for this user.
	 *
	 * Reads the user's default Rule/Module association and returns an object
	 * with "module" and "action" properties, or null if none is defined.
	 * The result is cached.
	 */
	public function landing(): ?\stdClass {

		if (!$this->issetCache('landing')) {

			$query =
				'SELECT m.`name` AS `module`, r.`action`
				FROM `acl` AS a
				INNER JOIN `rules` AS r ON r.`id` = a.`rule_id`
				INNER JOIN `modules` AS m ON m.`id` = r.`module_id`
				WHERE a.`is_default` = 1
				AND a.`group_id` = ?';

			$this->setCache('landing', Database::load($query, [$this->__get('groupId')], Database::OBJECT));

		}

		return $this->getCache('landing');

	}

	/**
	 * Attempts to auto-login using the "remember me" cookie.
	 *
	 * If a valid remember-me token is found, loads the related user, creates
	 * a new session, renews the remember-me token and sets the Application
	 * current user.
	 *
	 * @return bool True on successful auto-login, false otherwise.
	 */
	public static function loginByRememberMe(): bool {

		// get the cookie content
		$cookieContent = self::getRememberMeCookie();

		// check if cookie exists
		if (is_null($cookieContent)) {
			return false;
		}

		// try to load user
		$user = UserRemember::getUserByRememberMe($cookieContent->rememberMe);

		// if user exists, return it
		if (is_a($user, 'Pair\Models\User')) {

			// prevent remember-me login for disabled/locked users
			if (!$user->enabled or $user->faults > 9) {
				$user->unsetRememberMe();
				return false;
			}

			// hook for tasks to be executed before remember-me login
			$user->beforeRememberMeLogin();

			$user->createSession($cookieContent->timezone);
			$user->renewRememberMe();

			$app = Application::getInstance();
			$app->setCurrentUser($user);

			Audit::rememberMeLogin();

			// hook for tasks to be executed after remember-me login
			$user->afterRememberMeLogin();

			return true;

		}

		// login unsucceded
		return false;

	}

	/**
	 * Sets to 0 faults counter property.
	 */
	public function resetFaults(): bool {

		$this->faults = 0;
		return $this->update('faults');

	}

	/**
	 * Redirect user’s browser to his default landing web-page.
	 *
	 * @throws	\Exception	if no landing page is defined for this user group.
	 */
	public function redirectToDefault() {

		$app	 = Application::getInstance();
		$landing = $this->landing();

		if (!is_a($landing, '\stdClass') or !isset($landing->module)) {
			throw new \Exception('No landing page defined for this user group.');
		}

		$app->redirect($landing->module . '/' . $landing->action);

	}

	/**
	 * Renews the remember-me token for this user.
	 *
	 * Updates the created_at timestamp in DB, deletes older tokens for the same
	 * user and refreshes the browser cookie with a new expiration date.
	 *
	 * @return bool True if the cookie was updated, false otherwise.
	 */
	public function renewRememberMe(): bool {

		// build the cookie name
		$cookieName = self::getRememberMeCookieName();

		// check if cookie exists
		if (!isset($_COOKIE[$cookieName])) {
			return false;
		}

		$cookieContent = self::getRememberMeCookie();

		// hook for tasks to be executed before remember-me token renewal
		$this->beforeRememberMeRenew();

		// update created_at date and delete older remember-me records
		Database::run('UPDATE `users_remembers` SET `created_at` = NOW() WHERE `user_id` = ? AND `remember_me` = ?', [$this->id, $cookieContent->rememberMe]);
		Database::run('DELETE FROM `users_remembers` WHERE `user_id` = ? AND `remember_me` != ?', [$this->id, $cookieContent->rememberMe]);

		// expires in 30 days
		$expires = time() + 2592000;

		// set cookie and return the result
		$result = setcookie($cookieName, $_COOKIE[$cookieName], Application::getCookieParams($expires));

		// hook for tasks to be executed after remember-me token renewal
		$this->afterRememberMeRenew();

		return $result;

	}

	/**
	 * Applies a password reset for this user. Updates the stored password hash, logs the change, creates
	 * a new session and resets the login fault counter.
	 *
	 * @param  string $newPassword New password to set.
	 * @param  string $timezone    IANA time zone identifier.
	 * @return bool                True on success, false otherwise.
	 */
	public function setNewPassword(string $newPassword, string $timezone): bool {

		$this->pwReset = null;
		$this->hash = static::getHashedPasswordWithSalt($newPassword);

		if (!$this->store()) {
			return false;
		}

		Audit::passwordChanged($this);

		// creates session for this user
		$this->createSession($timezone);
		$this->resetFaults();

		return true;

	}

	/**
	 * Removes the current remember-me token from DB and browser cookie. Used on logout to invalidate the
	 * remember-me association.
	 *
	 * @return bool True if the cookie was removed, false otherwise.
	 */
	public function unsetRememberMe(): bool {

		// build the cookie name
		$cookieContent = self::getRememberMeCookie();

		// check if cookie exists
		if (is_null($cookieContent)) {
			return false;
		}

		// hook for tasks to be executed before remember-me token revocation
		$this->beforeRememberMeUnset();

		// delete the current remember-me DB record
		Database::run('DELETE FROM `users_remembers` WHERE `user_id` = ? AND `remember_me` = ?', [$this->id, $cookieContent->rememberMe]);

		// delete the current remember-me Cookie
		$result = setcookie(self::getRememberMeCookieName(), '', Application::getCookieParams(-1));

		// hook for tasks to be executed after remember-me token revocation
		$this->afterRememberMeUnset();

		return $result;

	}

}
