<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Sanitizes log payloads before they are persisted or exported.
 */
final class LogEventSanitizer {

	/**
	 * Maximum number of array items kept for one payload branch.
	 */
	private const MAX_ARRAY_ITEMS = 100;

	/**
	 * Maximum nested depth kept for structured payloads.
	 */
	private const MAX_DEPTH = 8;

	/**
	 * Maximum length for generic string values.
	 */
	private const MAX_STRING_LENGTH = 4096;

	/**
	 * Redaction marker for sensitive values.
	 */
	private const REDACTED = '[redacted]';

	/**
	 * Sanitizes an array recursively while preserving useful keys.
	 *
	 * @return array<mixed>
	 */
	public static function array(array $values, int $depth = 0): array {

		$sanitized = [];
		$count = 0;

		foreach ($values as $key => $value) {
			$count++;

			if ($count > self::MAX_ARRAY_ITEMS) {
				$sanitized['__truncated'] = true;
				break;
			}

			$sanitized[$key] = self::sanitize($value, is_string($key) ? $key : '', $depth);
		}

		return $sanitized;

	}

	/**
	 * Sanitizes a generic value recursively.
	 */
	public static function sanitize(mixed $value, string $key = '', int $depth = 0): mixed {

		if (self::isSensitiveKey($key)) {
			return self::REDACTED;
		}

		if ($depth >= self::MAX_DEPTH) {
			return '[max-depth]';
		}

		if (is_null($value) or is_bool($value) or is_int($value) or is_float($value)) {
			return $value;
		}

		if (is_string($value)) {
			return self::string($value);
		}

		if (is_array($value)) {
			return self::array($value, $depth + 1);
		}

		if ($value instanceof \Throwable) {
			return self::throwable($value);
		}

		if (is_object($value)) {
			return get_class($value);
		}

		return get_debug_type($value);

	}

	/**
	 * Sanitizes the subset of server data that is useful for diagnostics.
	 *
	 * @return array<string, mixed>
	 */
	public static function server(array $server): array {

		$allowedKeys = [
			'REMOTE_ADDR',
			'REQUEST_METHOD',
			'REQUEST_URI',
			'SERVER_NAME',
			'HTTP_HOST',
			'HTTP_REFERER',
			'HTTP_USER_AGENT',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_PAIR_CORRELATION_ID',
			'HTTP_X_CORRELATION_ID',
			'HTTP_TRACEPARENT',
		];

		$values = [];

		foreach ($allowedKeys as $key) {
			if (array_key_exists($key, $server)) {
				$values[$key] = self::sanitize($server[$key], $key);
			}
		}

		return $values;

	}

	/**
	 * Returns a sanitized string with a bounded length.
	 */
	public static function string(?string $value, int $maxLength = self::MAX_STRING_LENGTH): ?string {

		if (is_null($value)) {
			return null;
		}

		if ($maxLength < 1) {
			return '';
		}

		if (mb_strlen($value) <= $maxLength) {
			return $value;
		}

		return mb_substr($value, 0, $maxLength) . '... [truncated]';

	}

	/**
	 * Returns true when a payload key should never be persisted verbatim.
	 */
	private static function isSensitiveKey(string $key): bool {

		if ('' === $key) {
			return false;
		}

		return (bool)preg_match('/(authorization|cookie|password|passwd|secret|token|api[_-]?key|passphrase|csrf|session)/i', $key);

	}

	/**
	 * Extracts safe diagnostic data from a Throwable.
	 *
	 * @return array<string, mixed>
	 */
	private static function throwable(\Throwable $throwable): array {

		return [
			'class' => get_class($throwable),
			'code' => $throwable->getCode(),
			'message' => self::string($throwable->getMessage()),
			'file' => $throwable->getFile(),
			'line' => $throwable->getLine(),
		];

	}

}
