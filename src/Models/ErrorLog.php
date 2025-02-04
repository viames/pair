<?php

namespace Pair\Models;

use Pair\Core\Logger;
use Pair\Orm\ActiveRecord;

class ErrorLog extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * PSR-3 (PHP Standard Recommendation) log levels, default is DEBUG, least severe.
	 */
	protected int $level = Logger::DEBUG;

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
	 * This property maps “created_at” column.
	 */
	protected \DateTime $createdAt;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'error_logs';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['userId'];

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
	 * Returns array with matching object property name on related db fields.
	 */
	protected static function getBinds(): array {

		$varFields = [
			'id'			=> 'id',
			'level'			=> 'level',
			'userId'		=> 'user_id',
			'path'			=> 'path',
			'getData'		=> 'get_data',
			'postData'		=> 'post_data',
			'filesData'		=> 'files_data',
			'cookieData'	=> 'cookie_data',
			'description'	=> 'description',
			'userMessages'	=> 'user_messages',
			'referer'		=> 'referer',
			'createdAt'		=> 'created_at'
		];

		return $varFields;

	}

	/**
	 * Return the description of error level.
	 */
	public function getLevelDescription(): string {

		$levels = [
			Logger::EMERGENCY	=> 'Emergency',
			Logger::ALERT		=> 'Alert',
			Logger::CRITICAL	=> 'Critical',
			Logger::ERROR		=> 'Error',
			Logger::WARNING		=> 'Warning',
			Logger::NOTICE		=> 'Notice',
			Logger::INFO		=> 'Info',
			Logger::DEBUG		=> 'Debug'
		];

		return $levels[$this->level];

	}

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init(): void {

		$this->bindAsDatetime('createdAt');
		$this->bindAsInteger('id','level','userId');

	}

}