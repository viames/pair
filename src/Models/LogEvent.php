<?php

namespace Pair\Models;

use Pair\Core\Logger;
use Pair\Core\LogEventSanitizer;
use Pair\Helpers\Utilities;
use Pair\Orm\ActiveRecord;

class LogEvent extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * PSR-3 (PHP Standard Recommendation) log levels, default is DEBUG, least severe.
	 */
	protected int $level = Logger::DEBUG;

	/**
	 * Stable event name for grouping and external exports.
	 */
	protected ?string $eventName = null;

	/**
	 * This property maps "user_id" column.
	 */
	protected ?int $userId = null;

	/**
	 * This property maps "path" column.
	 */
	protected string $path;

	/**
	 * HTTP request method or CLI marker.
	 */
	protected ?string $requestMethod = null;

	/**
	 * Sanitized request payload data.
	 */
	protected array|string|null $requestData = null;

	/**
	 * This property maps "description" column.
	 */
	protected string $description;

	/**
	 * This property maps "referer" column.
	 */
	protected string $referer;

	/**
	 * Client IP address observed by the application.
	 */
	protected ?string $clientIp = null;

	/**
	 * Browser or client user agent.
	 */
	protected ?string $userAgent = null;

	/**
	 * Sanitized PSR-3 context data.
	 */
	protected array|string|null $contextData = null;

	/**
	 * Sanitized server/request metadata.
	 */
	protected array|string|null $serverData = null;

	/**
	 * Application version at the moment the event was logged.
	 */
	protected ?string $appVersion = null;

	/**
	 * Runtime environment at the moment the event was logged.
	 */
	protected ?string $environment = null;

	/**
	 * Request correlation identifier.
	 */
	protected ?string $correlationId = null;

	/**
	 * W3C/OpenTelemetry trace identifier.
	 */
	protected ?string $traceId = null;

	/**
	 * Stable fingerprint used to group equivalent errors.
	 */
	protected ?string $fingerprint = null;

	/**
	 * Throwable class, when available.
	 */
	protected ?string $exceptionClass = null;

	/**
	 * Throwable source file, when available.
	 */
	protected ?string $exceptionFile = null;

	/**
	 * Throwable source line, when available.
	 */
	protected ?int $exceptionLine = null;

	/**
	 * Throwable trace, when available.
	 */
	protected ?string $exceptionTrace = null;

	/**
	 * This property maps "created_at" column.
	 */
	protected \DateTime $createdAt;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'log_events';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['userId'];

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'			=> ['int unsigned', 'NO', 'PRI', NULL, 'auto_increment'],
		'level'			=> ['int unsigned', 'YES', '', '8', ''],
		'event_name'	=> ['varchar(128)', 'YES', 'MUL', NULL, ''],
		'user_id'		=> ['int unsigned', 'YES', 'MUL', NULL, ''],
		'path'			=> ['varchar(100)', 'NO', '', NULL, ''],
		'request_method' => ['varchar(16)', 'YES', '', NULL, ''],
		'request_data'	=> ['json', 'YES', '', NULL, ''],
		'description'	=> ['text', 'NO', '', NULL, ''],
		'referer'		=> ['varchar(255)', 'NO', '', NULL, ''],
		'client_ip'		=> ['varchar(45)', 'YES', '', NULL, ''],
		'user_agent'	=> ['varchar(512)', 'YES', '', NULL, ''],
		'context_data'	=> ['json', 'YES', '', NULL, ''],
		'server_data'	=> ['json', 'YES', '', NULL, ''],
		'app_version'	=> ['varchar(64)', 'YES', 'MUL', NULL, ''],
		'environment'	=> ['varchar(64)', 'YES', 'MUL', NULL, ''],
		'correlation_id'=> ['varchar(128)', 'YES', 'MUL', NULL, ''],
		'trace_id'		=> ['char(32)', 'YES', 'MUL', NULL, ''],
		'fingerprint'	=> ['char(64)', 'YES', 'MUL', NULL, ''],
		'exception_class' => ['varchar(255)', 'YES', 'MUL', NULL, ''],
		'exception_file' => ['varchar(512)', 'YES', '', NULL, ''],
		'exception_line' => ['int unsigned', 'YES', '', NULL, ''],
		'exception_trace' => ['mediumtext', 'YES', '', NULL, ''],
		'created_at'	=> ['timestamp', 'NO', 'MUL', NULL, '']
	];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsDatetime('createdAt');
		$this->bindAsInteger('id','level','userId','exceptionLine');

	}

	/**
	 * Unserialize some properties after populate() method execution.
	 */
	protected function afterPopulate() {

		$this->requestData	= self::decodePayload($this->__get('requestData'));
		$this->contextData	= self::decodePayload($this->__get('contextData'));
		$this->serverData	= self::decodePayload($this->__get('serverData'));

	}

	/**
	 * Serialize some properties before prepareData() method execution.
	 */
	protected function beforePrepareData() {

		$this->requestData	= self::encodePayload($this->__get('requestData'));
		$this->contextData	= self::encodePayload($this->__get('contextData'));
		$this->serverData	= self::encodePayload($this->__get('serverData'));

	}

	/**
	 * Decodes a payload saved as JSON or as legacy PHP serialized data.
	 */
	private static function decodePayload(mixed $payload): mixed {

		if (is_null($payload) or is_array($payload)) {
			return $payload;
		}

		if (!is_string($payload)) {
			return $payload;
		}

		$trimmedPayload = trim($payload);

		if ('' === $trimmedPayload) {
			return [];
		}

		$jsonPayload = json_decode($trimmedPayload, true);

		if (JSON_ERROR_NONE === json_last_error()) {
			return $jsonPayload;
		}

		if (Utilities::isSerialized($trimmedPayload, [\stdClass::class])) {
			return unserialize($trimmedPayload, ['allowed_classes' => [\stdClass::class]]);
		}

		return $payload;

	}

	/**
	 * Encodes structured payloads as JSON while leaving scalar values untouched.
	 */
	private static function encodePayload(mixed $payload): mixed {

		if (is_null($payload) or is_string($payload)) {
			return $payload;
		}

		$payload = LogEventSanitizer::sanitize($payload);
		$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		return false === $encodedPayload ? null : $encodedPayload;

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 */
	protected static function getBinds(): array {

		$varFields = [
			'id'			=> 'id',
			'level'			=> 'level',
			'eventName'		=> 'event_name',
			'userId'		=> 'user_id',
			'path'			=> 'path',
			'requestMethod'	=> 'request_method',
			'requestData'	=> 'request_data',
			'description'	=> 'description',
			'referer'		=> 'referer',
			'clientIp'		=> 'client_ip',
			'userAgent'		=> 'user_agent',
			'contextData'	=> 'context_data',
			'serverData'	=> 'server_data',
			'appVersion'	=> 'app_version',
			'environment'	=> 'environment',
			'correlationId'	=> 'correlation_id',
			'traceId'		=> 'trace_id',
			'fingerprint'	=> 'fingerprint',
			'exceptionClass' => 'exception_class',
			'exceptionFile'	=> 'exception_file',
			'exceptionLine'	=> 'exception_line',
			'exceptionTrace'=> 'exception_trace',
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

}
