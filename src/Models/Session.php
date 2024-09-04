<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

class Session extends ActiveRecord {

	/**
	 * Property that binds db field id.
	 * @var string
	 */
	protected $id;

	/**
	 * Property that binds db field id_user.
	 * @var int|NULL
	 */
	protected $idUser;

	/**
	 * Property that binds db field start_time.
	 * @var DateTime
	 */
	protected $startTime;

	/**
	 * Property that binds db field timezone_offset.
	 * @var float|NULL
	 */
	protected $timezoneOffset;

	/**
	 * Property that binds db field timezone_name.
	 * @var string|NULL
	 */
	protected $timezoneName;

	/**
	 * Property that binds db field former_user_id.
	 *
	 * @var int|NULL
	 */
	protected $formerUserId;

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

		$this->bindAsInteger('idUser', 'formerUserId');

	}

	/**
	 * Returns an array with the object property names and corresponding columns in the db.
	 *
	 * @return	array
	 */
	protected static function getBinds(): array {

		$binds = [
			'id'				=> 'id',
			'idUser'			=> 'id_user',
			'startTime'			=> 'start_time',
			'timezoneOffset'	=> 'timezone_offset',
			'timezoneName'		=> 'timezone_name',
			'formerUserId'		=> 'former_user_id'
		];

		return $binds;

	}

	/**
	 * Extends timeout updating startTime of this session, based on user’s time zone.
	 */
	public function extendTimeout() {

		$this->startTime = new \DateTime();
		$this->store();

	}

	/**
	 * Deletes expired sessions from database, based on sessionTime param and user’s time zone.
	 *
	 * @param	int		Session time in minutes.
	 */
	public static function cleanOlderThan(int $sessionTime) {

		// converts to current time zone
		$dateTime  = new \DateTime();
		$startTime = $dateTime->format('Y-m-d H:i:s');

		Database::run('DELETE FROM `sessions` WHERE `start_time` < DATE_SUB(?, INTERVAL '. (int)$sessionTime .' MINUTE)', [$startTime]);

	}

	/**
	 * Checks if a Session object is expired after sessionTime passed as parameter.
	 *
	 * @param	int		Session time in minutes.
	 * @return	bool
	 */
	public function isExpired(int $sessionTime): bool {

		if (is_null($this->startTime)) {
			return TRUE;
		}

		// creates expiring date subtracting sessionTime interval
		$expiring = new \DateTime('now', new \DateTimeZone(BASE_TIMEZONE));
		$expiring->sub(new \DateInterval('PT' . (int)$sessionTime . 'M'));

		return ($this->startTime < $expiring);

	}

	/**
	 * Store the User or children object of this Session object into a cache.
	 *
	 * @param	User	User to set.
	 */
	public function setUser(User $user) {

		$this->setCache('user', $user);

	}

	/**
	 * Return the User object of this Session, if exists. Cached method.
	 *
	 * @return	User|NULL
	 */
	public function getUser(): ?User {

		if (!$this->idUser) {
			return NULL;
		}

		if (!$this->issetCache('user')) {
			$userClass = PAIR_USER_CLASS;
			$user = new $userClass($this->idUser);
			$this->setCache('user', $user->isLoaded() ? $user : NULL);
		}

		return $this->getCache('user');

	}

	/**
	 * Returns true if the session has a former user.
	 *
	 * @return boolean
	 */
	public function hasFormerUser(): bool {

		return !in_array($this->formerUserId,  [null, '']);

	}

	/**
	 * Store the former User or children object of this Session object into a cache.
	 *
	 * @param	User	User to set.
	 */
	public function setFormerUser(User $formerUser) {

		$this->setCache('formerUser', $formerUser);

	}

	/**
	 * Returns the former user associated to the session, if present.
	 *
	 * @return User|null
	 */
	public function getFormerUser(): ?User {

		if (!$this->formerUserId) {
			return NULL;
		}

		if (!$this->issetCache('formerUser')) {
			$userClass = PAIR_USER_CLASS;
			$formerUser = new $userClass($this->formerUserId);
			$this->setCache('formerUser', $formerUser->isLoaded() ? $formerUser : NULL);
		}

		return $this->getCache('formerUser');

	}

	/**
	 * Return the current Session object.
	 *
	 * @return	Session
	 */
	public static function current(): self {

		return new Session(session_id());

	}

}