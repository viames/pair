<?php

declare(strict_types=1);

namespace Pair\Core;

use Pair\Models\LogEvent;

/**
 * Persists structured log events to the local database.
 */
final class DatabaseLogWriter {

	/**
	 * Stores one log event with request, release, trace, and exception metadata.
	 */
	public function write(int $level, string $description, array $context = []): void {

		$app = Application::getInstance();
		$router = Router::getInstance();
		$throwable = $this->throwableFromContext($context);
		$path = ltrim((string)$router->url, '/');
		$correlationId = Observability::correlationId();

		$logEvent = new LogEvent();

		$logEvent->level = $level;
		$logEvent->eventName = $this->eventName($context, $throwable);
		$logEvent->userId = $app->currentUser->id ?? null;
		$logEvent->path = $path;
		$logEvent->requestMethod = $_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : '');
		$logEvent->requestData = $this->requestData();
		$logEvent->description = LogEventSanitizer::string($description, 20000) ?? '';
		$logEvent->referer = $this->referer();
		$logEvent->clientIp = $_SERVER['REMOTE_ADDR'] ?? null;
		$logEvent->userAgent = LogEventSanitizer::string($_SERVER['HTTP_USER_AGENT'] ?? null, 512);
		$logEvent->contextData = $this->contextData($context, LogEventSanitizer::array($app->getAllNotificationsMessages()));
		$logEvent->serverData = LogEventSanitizer::server($_SERVER);
		$logEvent->appVersion = LogEventSanitizer::string((string)Env::get('APP_VERSION'), 64);
		$logEvent->environment = LogEventSanitizer::string((string)Application::getEnvironment(), 64);
		$logEvent->correlationId = $correlationId;
		$logEvent->traceId = $this->traceId($correlationId);
		$logEvent->exceptionClass = $throwable ? get_class($throwable) : $this->stringContext($context, 'exceptionClass');
		$logEvent->exceptionFile = $throwable ? $throwable->getFile() : $this->stringContext($context, 'file');
		$logEvent->exceptionLine = $throwable ? $throwable->getLine() : $this->intContext($context, 'line');
		$logEvent->exceptionTrace = $throwable ? LogEventSanitizer::string($throwable->getTraceAsString(), 20000) : null;
		$logEvent->fingerprint = LogEventFingerprint::build(
			$level,
			$description,
			$logEvent->exceptionClass,
			$logEvent->exceptionFile,
			$logEvent->exceptionLine,
			$path
		);

		$logEvent->create();

	}

	/**
	 * Returns sanitized contextual data, including visible user messages when present.
	 */
	private function contextData(array $context, array $userMessages): ?array {

		$contextData = LogEventSanitizer::array($context);

		if ([] !== $userMessages) {
			$contextData['userMessages'] = $userMessages;
		}

		return [] === $contextData ? null : $contextData;

	}

	/**
	 * Returns a normalized event name for grouping and external exports.
	 */
	private function eventName(array $context, ?\Throwable $throwable): string {

		$eventName = $this->stringContext($context, 'eventName');

		if ($eventName) {
			return mb_substr($eventName, 0, 128);
		}

		if ($throwable) {
			return mb_substr(get_class($throwable), 0, 128);
		}

		return 'log';

	}

	/**
	 * Reads an integer context value safely.
	 */
	private function intContext(array $context, string $key): ?int {

		if (!array_key_exists($key, $context)) {
			return null;
		}

		if (is_int($context[$key])) {
			return $context[$key];
		}

		if (is_string($context[$key]) and preg_match('/^-?\d+$/', $context[$key]) === 1) {
			return (int)$context[$key];
		}

		return null;

	}

	/**
	 * Returns the request referer without the application base URL when possible.
	 */
	private function referer(): string {

		if (!isset($_SERVER['HTTP_REFERER'])) {
			return '';
		}

		return (0 === strpos($_SERVER['HTTP_REFERER'], BASE_HREF))
			? substr($_SERVER['HTTP_REFERER'], strlen(BASE_HREF))
			: (string)$_SERVER['HTTP_REFERER'];

	}

	/**
	 * Returns sanitized request payload data only when the request carries payloads.
	 */
	private function requestData(): ?array {

		$requestData = [];

		if ([] !== $_GET) {
			$requestData['query'] = LogEventSanitizer::array($_GET);
		}

		if ([] !== $_POST) {
			$requestData['body'] = LogEventSanitizer::array($_POST);
		}

		if ([] !== $_FILES) {
			$requestData['files'] = LogEventSanitizer::array($_FILES);
		}

		return [] === $requestData ? null : $requestData;

	}

	/**
	 * Reads a scalar context value as string.
	 */
	private function stringContext(array $context, string $key): ?string {

		if (!array_key_exists($key, $context)) {
			return null;
		}

		$value = $context[$key];

		if (is_scalar($value) or (is_object($value) and method_exists($value, '__toString'))) {
			return (string)$value;
		}

		return null;

	}

	/**
	 * Extracts the PSR-3 Throwable from context, if present.
	 */
	private function throwableFromContext(array $context): ?\Throwable {

		return ($context['exception'] ?? null) instanceof \Throwable ? $context['exception'] : null;

	}

	/**
	 * Returns a W3C trace ID from traceparent or the Pair correlation ID.
	 */
	private function traceId(string $correlationId): string {

		$traceparent = trim((string)($_SERVER['HTTP_TRACEPARENT'] ?? ''));

		if (preg_match('/^[\da-f]{2}-([\da-f]{32})-([\da-f]{16})-([\da-f]{2})$/i', $traceparent, $matches) === 1) {
			return strtolower($matches[1]);
		}

		return preg_match('/^[a-f0-9]{32}$/i', $correlationId) ? strtolower($correlationId) : md5($correlationId);

	}

}
