<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Exceptions\PairException;
use Pair\Orm\ActiveRecord;

class ErrorLog extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * PSR-3 (PHP Standard Recommendation) log levels, default is DEBUG, least severe.
	 */
	protected int $level = self::DEBUG;

	/**
	 * This property maps “created_time” column.
	 */
	protected \DateTime $createdTime;

	/**
	 * This property maps “user_id” column.
	 */
	protected ?int $userId = NULL;

	/**
	 * This property maps “path” column.
	 */
	protected string $path;

	/**
	 * Converted from array to string automatically.
	 */
	protected array|string $getData;

	/**
	 * Converted from array to string automatically.
	 */
	protected array|string $postData;

	/**
	 * Converted from array to string automatically.
	 */
	protected array|string|NULL $filesData = NULL;

	/**
	 * Converted from array to string automatically.
	 */
	protected array|string $cookieData;

	/**
	 * This property maps “description” column.
	 */
	protected string $description;

	/**
	 * This property maps “user_messages” column.
	 */
	protected array|string $userMessages;

	/**
	 * This property maps “referer” column.
	 */
	protected string $referer;

	/**
	 * Description: The most critical level. Indicates that the system is completely unusable.
	 * Example: The entire platform is down or a critical dependency has failed.
	 * Typical Message: "System is unusable."
	 */
	const EMERGENCY = 1;

	/**
	 * Description: Requires immediate action.
	 * Example: Data loss or a critical security issue that needs attention right away.
	 * Typical Message: "Database connection lost."
	 */
	const ALERT = 2;

	/**
	 * Description: Critical conditions that may prevent key application features from working.
	 * Example: A critical service is down.
	 * Typical Message: "Payment system unavailable."
	 */
	const CRITICAL = 3;

	/**
	 * Description: Runtime errors that do not halt the application but require fixing.
	 * Example: An exception that was caught or a missing file.
	 * Typical Message: "File not found."
	 */
	const ERROR = 4;

	/**
	 * Description: Exceptional conditions that are not errors but should be looked into.
	 * Example: High memory usage or deprecated features being used.
	 * Typical Message: "Memory usage is high."
	 */
	const WARNING = 5;

	/**
	 * Description: Normal but noteworthy events that may be of interest for monitoring.
	 * Example: A configuration setting is suboptimal.
	 * Typical Message: "Using default configuration."
	 */
	const NOTICE = 6;

	/**
	 * Description: Informational messages about the system’s normal operations.
	 * Example: A user logs in or a connection is successfully established.
	 * Typical Message: "User logged in."
	 */
	const INFO = 7;

	/**
	 * Description: Detailed debugging information for development purposes.
	 * Example: API responses during testing or SQL query execution.
	 * Typical Message: "Query executed: SELECT * FROM users."
	 */
	const DEBUG = 8;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'error_logs';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init(): void {

		$this->bindAsDatetime('createdTime');
		$this->bindAsInteger('id','level','userId');

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 */
	protected static function getBinds(): array {

		$varFields = [
			'id'			=> 'id',
			'level'			=> 'level',
			'createdTime'	=> 'created_time',
			'userId'		=> 'user_id',
			'path'			=> 'path',
			'getData'		=> 'get_data',
			'postData'		=> 'post_data',
			'filesData'		=> 'files_data',
			'cookieData'	=> 'cookie_data',
			'description'	=> 'description',
			'userMessages'	=> 'user_messages',
			'referer'		=> 'referer'
		];

		return $varFields;

	}

	/**
	 * Serialize some properties before prepareData() method execution.
	 */
	protected function beforePrepareData() {

		$this->getData		= serialize($this->__get('getData'));
		$this->postData		= serialize($this->__get('postData'));
		$this->filesData	= serialize($this->__get('filesData'));
		$this->cookieData	= serialize($this->__get('cookieData'));
		$this->userMessages	= serialize($this->__get('userMessages'));

	}

	/**
	 * Unserialize some properties after populate() method execution.
	 */
	protected function afterPopulate() {

		$this->getData		= unserialize($this->__get('getData'));
		$this->postData		= unserialize($this->__get('postData'));
		$this->filesData	= unserialize($this->__get('filesData'));
		$this->cookieData	= unserialize($this->__get('cookieData'));
		$this->userMessages	= unserialize($this->__get('userMessages'));

	}

	/**
	 * Allows to keep the current Application and browser state.
	 *
	 * @param	string	Description of the snapshot moment.
	 * @param	int		Optional PSR-3 log level number equivalent, default is 8 (DEBUG).
	 */
	public static function snapshot(string $description, ?int $level=NULL): void {

		$app = Application::getInstance();
		$router = Router::getInstance();

		if (!is_null($level) and ($level > 8 or $level < 1)) {
            throw new PairException('Invalid log level: ' . $level);
        }

		$snap = new self();

		$snap->level 		= $level ?: self::DEBUG;
		$snap->createdTime	= new \DateTime();
		$snap->userId		= $app->currentUser->id ?? NULL;
		$snap->path			= substr($router->url,1);
		$snap->getData		= $_GET;
		$snap->postData		= $_POST;
		$snap->filesData	= $_FILES;
		$snap->cookieData	= $_COOKIE;
		$snap->description	= substr($description, 0, 255);
		$snap->userMessages	= $app->getAllNotificationsMessages();

		if (isset($_SERVER['HTTP_REFERER'])) {

			// removes application base url from referer
			if (0 === strpos($_SERVER['HTTP_REFERER'], BASE_HREF)) {
				$snap->referer = substr($_SERVER['HTTP_REFERER'], strlen(BASE_HREF));
			} else {
				$snap->referer = (string)$_SERVER['HTTP_REFERER'];
			}

		} else {
			$snap->referer = '';
		}

		$snap->create();

	}

}