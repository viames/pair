<?php
		
namespace Pair;

class Session extends ActiveRecord {

	/**
	 * Property that binds db field id.
	 * @var string
	 */
	protected $id;

	/**
	 * Property that binds db field id_user.
	 * @var int
	 */
	protected $idUser;

	/**
	 * Property that binds db field start_time.
	 * @var DateTime
	 */
	protected $startTime;

	/**
	 * Property that binds db field timezone_offset.
	 * @var float
	 */
	protected $timezoneOffset;

	/**
	 * Property that binds db field timezone_name.
	 * @var string
	 */
	protected $timezoneName;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'sessions';
		
	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';
	
	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {
			
		$this->bindAsDatetime('startTime');
		
		$this->bindAsFloat('timezoneOffset');

		$this->bindAsInteger('idUser');
			
	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return	array
	 */
	protected static function getBinds() {
		
		$varFields = array (
			'id'				=> 'id',
			'idUser'			=> 'id_user',
			'startTime'			=> 'start_time',
			'timezoneOffset'	=> 'timezone_offset',
			'timezoneName'		=> 'timezone_name');
		
		return $varFields;
		
	}

	/**
	 * Only one session is allowed, deletes previous for this user.
	 * 
	 * @see ActiveRecord::beforeCreate()
	 */
	public function beforeCreate() {

		$this->db->exec('DELETE FROM `sessions` WHERE `id_user` = ?', [$this->idUser]);

	}
	
	/**
	 * Extends timeout updating startTime of this session, based on user’s time zone.
	 */
	public function extendTimeout() {

		// converts to current time zone
		$dateTime  = new \DateTime();
		$startTime = Utilities::convertToDbDatetime($dateTime);
		
		$this->db->exec('UPDATE `sessions` SET `start_time` = ? WHERE `id` = ?', array($startTime, $this->id));
		
	}
	
	/**
	 * Deletes expired sessions from database, based on sessionTime param and user’s time zone.
	 *
	 * @param	int		Session time in minutes.
	 */
	public static function cleanOlderThan($sessionTime) {

		$db = Database::getInstance();

		// converts to current time zone
		$dateTime  = new \DateTime();
		$startTime = $dateTime->format('Y-m-d H:i:s');

		$query = 'DELETE FROM `sessions` WHERE `start_time` < DATE_SUB(?, INTERVAL '. (int)$sessionTime .' MINUTE)';
		$db->exec($query, [$startTime]);

	}

	/**
	 * Checks if a Session object is expired after sessionTime passed as parameter.
	 * 
	 * @param	int		Session time in minutes.
	 * 
	 * @return	bool
	 */
	public function isExpired($sessionTime) {
		
		if (is_null($this->startTime)) {
			return TRUE;
		}

		// creates expiring date subtracting sessionTime interval
		$expiring = new \DateTime(NULL, new \DateTimeZone(BASE_TIMEZONE));
		$expiring->sub(new \DateInterval('PT' . (int)$sessionTime . 'M'));
		
		return ($this->startTime < $expiring);

	}
	
	/**
	 * Return the User object of this Session, if exists.
	 * 
	 * @return	User|NULL 
	 */
	public function getUser(): ?User {

		if (!$this->idUser) return NULL;

		$user = new User($this->idUser);

		return ($user->isLoaded() ? $user : NULL);

	}
	
}