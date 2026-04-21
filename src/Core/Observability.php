<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Lightweight runtime observability facade with no required external dependency.
 */
final class Observability {

	/**
	 * Adapter receiving completed spans for the current PHP process.
	 */
	private static ?ObservabilityAdapter $adapter = null;

	/**
	 * Explicit process-local enable override.
	 */
	private static ?bool $enabled = null;

	/**
	 * Correlation ID shared by spans and optional debug response headers.
	 */
	private static ?string $correlationId = null;

	/**
	 * Completed spans retained for tests and debug headers during the current request.
	 *
	 * @var	list<ObservabilitySpan>
	 */
	private static array $spans = [];

	/**
	 * Clear process-local observability state.
	 */
	public static function clear(): void {

		self::$adapter = null;
		self::$enabled = null;
		self::$correlationId = null;
		self::$spans = [];

	}

	/**
	 * Return the current correlation ID, creating it from request headers or random bytes.
	 */
	public static function correlationId(): string {

		if (self::$correlationId) {
			return self::$correlationId;
		}

		$headerId = self::correlationHeaderValue();
		self::$correlationId = $headerId ?: bin2hex(random_bytes(16));

		return self::$correlationId;

	}

	/**
	 * Return response headers that are safe to expose in debug mode.
	 *
	 * @return	array<string, string>
	 */
	public static function debugHeaders(): array {

		if (!self::debugHeadersEnabled()) {
			return [];
		}

		$headers = [
			'X-Pair-Correlation-Id' => self::correlationId(),
		];

		if (count(self::$spans)) {
			$headers['X-Pair-Trace-Spans'] = (string)count(self::$spans);
			$headers['X-Pair-Trace-Duration-Ms'] = self::formatMilliseconds(self::totalDurationMs());
		}

		return $headers;

	}

	/**
	 * Enable or disable instrumentation explicitly for the current PHP process.
	 */
	public static function enable(bool $enabled = true): void {

		self::$enabled = $enabled;

	}

	/**
	 * Finish and record a span when instrumentation is enabled.
	 *
	 * @param	array<string, mixed>	$attributes	Additional attributes to merge into the completed span.
	 */
	public static function finish(ObservabilitySpan $span, array $attributes = [], string $status = 'ok'): void {

		if (!self::isEnabled()) {
			return;
		}

		$span->finish(self::sanitizeAttributes($attributes), $status);
		self::record($span);

	}

	/**
	 * Return whether runtime instrumentation is currently enabled.
	 */
	public static function isEnabled(): bool {

		if (!is_null(self::$enabled)) {
			return self::$enabled;
		}

		if (self::$adapter) {
			return true;
		}

		return (bool)Env::get('PAIR_OBSERVABILITY_ENABLED') || self::debugHeadersEnabled();

	}

	/**
	 * Record a completed span and forward it to the configured adapter.
	 */
	public static function record(ObservabilitySpan $span): void {

		if (!self::isEnabled()) {
			return;
		}

		self::$spans[] = $span;

		if (self::$adapter) {
			self::$adapter->record($span);
		}

	}

	/**
	 * Set the adapter that receives completed spans.
	 */
	public static function setAdapter(?ObservabilityAdapter $adapter): void {

		self::$adapter = $adapter;

	}

	/**
	 * Set a known correlation ID for tests, gateways, or custom bootstraps.
	 */
	public static function setCorrelationId(?string $correlationId): void {

		$correlationId = is_null($correlationId) ? null : trim($correlationId);
		self::$correlationId = $correlationId ?: null;

	}

	/**
	 * Start a span with safe attributes.
	 *
	 * @param	array<string, mixed>	$attributes	Context attributes safe for observability output.
	 */
	public static function start(string $name, array $attributes = []): ObservabilitySpan {

		if (!self::isEnabled()) {
			return new ObservabilitySpan($name, [], '', microtime(true));
		}

		return new ObservabilitySpan(
			$name,
			self::sanitizeAttributes($attributes),
			self::correlationId(),
			microtime(true)
		);

	}

	/**
	 * Return completed spans retained for the current request.
	 *
	 * @return	list<ObservabilitySpan>
	 */
	public static function spans(): array {

		return self::$spans;

	}

	/**
	 * Run a callback inside a named span.
	 *
	 * @param	array<string, mixed>	$attributes	Context attributes safe for observability output.
	 */
	public static function trace(string $name, callable $callback, array $attributes = []): mixed {

		if (!self::isEnabled()) {
			return $callback();
		}

		$span = self::start($name, $attributes);

		try {
			$result = $callback();
			self::finish($span);
			return $result;
		} catch (\Throwable $e) {
			self::finish($span, [
				'exception' => get_class($e),
			], 'error');
			throw $e;
		}

	}

	/**
	 * Return the first trusted correlation request header value.
	 */
	private static function correlationHeaderValue(): ?string {

		foreach (['HTTP_X_PAIR_CORRELATION_ID', 'HTTP_X_CORRELATION_ID', 'HTTP_TRACEPARENT'] as $header) {

			$value = $_SERVER[$header] ?? null;

			if (is_string($value) and strlen(trim($value))) {
				return substr(trim($value), 0, 128);
			}

		}

		return null;

	}

	/**
	 * Return whether observability debug headers may be sent to the client.
	 */
	private static function debugHeadersEnabled(): bool {

		return (bool)Env::get('APP_DEBUG') and (bool)Env::get('PAIR_OBSERVABILITY_DEBUG_HEADERS');

	}

	/**
	 * Format a floating-point millisecond duration for HTTP headers.
	 */
	private static function formatMilliseconds(float $milliseconds): string {

		return number_format($milliseconds, 3, '.', '');

	}

	/**
	 * Return true when an attribute name is likely to contain sensitive data.
	 */
	private static function isSensitiveAttribute(string $name): bool {

		return (bool)preg_match('/(authorization|cookie|password|secret|token|passphrase)/i', $name);

	}

	/**
	 * Sanitize attribute names and values before spans can leave the process.
	 *
	 * @param	array<string, mixed>	$attributes	Raw span attributes.
	 * @return	array<string, mixed>
	 */
	private static function sanitizeAttributes(array $attributes): array {

		$safe = [];

		foreach ($attributes as $name => $value) {

			if (!is_string($name)) {
				continue;
			}

			if (self::isSensitiveAttribute($name)) {
				$safe[$name] = '[redacted]';
				continue;
			}

			$safe[$name] = self::sanitizeAttributeValue($value);

		}

		return $safe;

	}

	/**
	 * Normalize one attribute value into a safe scalar representation.
	 */
	private static function sanitizeAttributeValue(mixed $value): mixed {

		if (is_null($value) or is_bool($value) or is_int($value) or is_float($value)) {
			return $value;
		}

		if (is_string($value)) {
			return mb_substr($value, 0, 256);
		}

		if (is_object($value)) {
			return get_class($value);
		}

		return gettype($value);

	}

	/**
	 * Return the total duration represented by the retained spans.
	 */
	private static function totalDurationMs(): float {

		$total = 0.0;

		foreach (self::$spans as $span) {
			$total += $span->durationMs();
		}

		return $total;

	}

}
