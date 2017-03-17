<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

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
	 * Id of user language.
	 * @var int
	 */
	protected $languageId;
	
	/**
	 * Username for LDAP domain.
	 * @var string
	 */
	protected $ldapUser;
	
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
	 * If equals 1, this user is admin.
	 * @var int
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
	
		$this->bindAsDatetime('lastLogin');
		
		$this->bindAsInteger('id', 'groupId', 'languageId', 'faults');
		
		$this->bindAsBoolean('admin', 'enabled');
	
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
			'languageId'=> 'language_id',
			'ldapUser'	=> 'ldap_user',
			'username'	=> 'username',
			'name'		=> 'name',
			'surname'	=> 'surname',
			'email'		=> 'email',
			'admin'		=> 'admin',
			'hash'		=> 'hash',
			'enabled'	=> 'enabled',
			'lastLogin'	=> 'last_login',
			'faults'	=> 'faults');
		
		return $varFields;
		
	}
	
	/**
	 * Deletes sessions of an user before its deletion.
	 */
	protected function beforeDelete() {

		// deletes user sessions
		$this->db->exec('DELETE FROM sessions WHERE id_user = ?', $this->id);
	
		// deletes error_logs of this user
		$this->db->exec('DELETE FROM error_logs WHERE user_id = ?', $this->id);
		
	}

	/**
	 * Creates and returns an Hash for user password adding salt.
	 * 
	 * @param	string	The user password.
	 * @return	string	Hashed password
	 * 
	 * @see		http://php.net/crypt
	 */
	public static function getHashedPasswordWithSalt($password) {
		
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
	public static function checkPassword($password, $hash) {

		return ($hash == crypt($password, $hash) ? TRUE : FALSE);
		
	}
	
	/**
	 * Checks if username/password matches a record into database for local auth and returns a
	 * stdClass with error, message and userId parameters.
	 *
	 * @param	string	Username.
	 * @param	string	Plain text password.
	 * @param	string	IANA time zone identifier.
	 * 
	 * @return	stdClass
	 */
	public static function doLogin($username, $password, $timezone) {
	
		$app	= Application::getInstance();
		$db		= Database::getInstance();
		$tran	= Translator::getInstance();
		
		$ret = new \stdClass();

		$ret->error		= FALSE;
		$ret->message	= NULL;
		$ret->userId	= NULL;
		$ret->sessionId	= NULL;
		
		// loads user row
		$db->setQuery('SELECT * FROM users WHERE username = ?');
		$row = $db->loadObject($username);
	
		if (is_object($row)) {
				
			$user = new User($row);

			// over 9 faults
			if ($user->faults > 9) {
			
				$ret->error = TRUE;
				$ret->message = $tran->translate('TOO_MANY_LOGIN_ATTEMPTS');
				$user->addFault();
					
			// user disabled
			} else if ('0' == $user->enabled) {

				$ret->error = TRUE;
				$ret->message = $tran->translate('USER_IS_DISABLED');
				$user->addFault();
					
			// user password doesn’t match
			} else if (!User::checkPassword($password, $user->hash)) {

				$ret->error = TRUE;
				$ret->message = $tran->translate('PASSWORD_IS_NOT_VALID');
				$user->addFault();
				
			// login ok
			} else {
				
				// creates session for this user
				$user->createSession($user->fullName, $timezone);
				$ret->userId = $user->id;
				$ret->sessionId = session_id();
				$user->resetFaults();
	
			}
				
		// this username doesn’t exist into db
		} else {
				
			$ret->error = TRUE;
			$ret->message = $tran->translate('USERNAME_NOT_VALID');
				
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
	 */
	private function resetFaults() {
		
		$this->faults = 0;
		$this->update('faults');
		
	}
	
	/**
	 * Starts a new session, writes on db and updates users table for last login.
	 * Returns true if both db writing has been done succesfully. 
	 * 
	 * @param	string	User name for this session (useful in case of LDAP login).
	 * @param	string	IANA time zone identifier.
	 * 
	 * @return	bool
	 */
	private function createSession($name, $timezone) {

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

		$session->idSession			= session_id();
		$session->idUser			= $this->id;
		$session->startTime			= new \DateTime();
		$session->timezoneOffset	= $offset;
		$session->timezoneName		= $timezone;
		
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
	 * 
	 * @return	bool
	 */
	public static function doLogout($sid) {

		$app = Application::getInstance();
		$db  = Database::getInstance();

		// delete session
		$res = $db->exec('DELETE FROM sessions WHERE id_session = ?', $sid);

		// unset all persistent states
		$prefix = PRODUCT_NAME . '_';
		foreach ($_COOKIE as $name=>$content) {
			if (0 == strpos($name, $prefix)) {
				unset($_COOKIE[$name]);
				setcookie($name, '', -1, '/');
			}
		}
		
		// reset the user in Application object
		$app->currentUser = NULL;
		
		return (bool)$res;
	
	}
	
	/**
	 * It will returns DateTimeZone object for this User.
	 *
	 * @return DateTimeZone
	 */
	public function getDateTimeZone() {
	
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
			
			$this->db->setQuery('SELECT timezone_name, timezone_offset FROM sessions WHERE id_user = ?');
			$obj = $this->db->loadObject($this->id);
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
	 * 
	 * @return	bool	True if access is granted.
	 */
	public function canAccess($module, $action=NULL) {
		
		// user module is for login and personal profile
		if ($this->admin or 'user'==$module) {
			return TRUE;
		}
		
		$acl = $this->getAcl();

		foreach ($acl as $rule) {
			if ($rule->module_name == $module and (!$action or 'default'==$action or !$rule->action or ($rule->action and $rule->action == $action))) {
				return TRUE;
			}
		}
		
		return FALSE;
		
	}
	
	/**
	 * Load the rule list for this user. Cached.
	 */
	protected function getAcl() {

		if (!$this->issetCache('acl')) {
		
		$query =
			'SELECT r.*, m.name AS module_name' .
			' FROM rules AS r' .
			' INNER JOIN acl AS a ON a.rule_id = r.id'.
			' INNER JOIN modules AS m ON r.module_id = m.id'.
			' WHERE a.group_id = ?';
		
		$this->db->setQuery($query);
			$this->setCache('acl', $this->db->loadObjectList($this->groupId));

		}

		return $this->getCache('acl');
		
	}
	
	/**
	 * Get landing module and action as object properties where the user goes after login.
	 *
	 * @return stdClass
	 */
	public function getLanding() {
		
		$query =
			' SELECT m.`name` AS module, r.action' .
			' FROM acl AS a' .
			' INNER JOIN rules AS r ON r.id = a.rule_id' .
			' INNER JOIN modules AS m ON m.id = r.module_id' .
			' WHERE a.is_default = 1' . 
			' AND a.group_id = ?';

		$this->db->setQuery($query);
		$obj = $this->db->loadObject($this->groupId);

		return $obj;
		
	}
	
	/**
	 * Return the language code of this user. Cached.
	 *
	 * @return	string
	 */
	public function getLanguageCode() {

		if (!$this->issetCache('lang')) {
	
			$query =
				'SELECT l.code ' .
				' FROM languages AS l ' .
				' INNER JOIN users AS u ON u.language_id = l.id ' .
				' WHERE u.id = ?';
			$this->db->setQuery($query);
			$this->setCache('lang', $this->db->loadResult($this->id));

		}
	
		return $this->getCache('lang');

	}
	
	/**
	 * Get Group object for this user. Cached.
	 *
	 * @return Group
	 */
	public function getGroup() {
	
		if (!$this->issetCache('group')) {
			$this->setCache('group', new Group($this->groupId));
		}
	
		return $this->getCache('group');
	
	}
	
}