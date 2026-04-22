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
	 * Event sampling decision for the current PHP process.
	 */
	private static ?bool $eventSampled = null;

	/**
	 * Events retained for tests during the current request.
	 *
	 * @var	list<ObservabilityEvent>
	 */
	private static array $events = [];

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
	 * Trace sampling decision for the current PHP process.
	 */
	private static ?bool $traceSampled = null;

	/**
	 * Clear process-local observability state.
	 */
	public static function clear(): void {

		self::$adapter = null;
		self::$enabled = null;
		self::$eventSampled = null;
		self::$events = [];
		self::$correlationId = null;
		self::$spans = [];
		self::$traceSampled = null;

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
		self::$eventSampled = null;
		self::$traceSampled = null;

	}

	/**
	 * Return completed events retained for the current request.
	 *
	 * @return	list<ObservabilityEvent>
	 */
	public static function events(): array {

		return self::$events;

	}

	/**
	 * Finish and record a span when instrumentation is enabled.
	 *
	 * @param	array<string, mixed>	$attributes	Additional attributes to merge into the completed span.
	 */
	public static function finish(ObservabilitySpan $span, array $attributes = [], string $status = 'ok'): void {

		if (!self::shouldRecordTrace()) {
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

		if (!self::shouldRecordTrace()) {
			return;
		}

		if (count(self::$spans) < self::maxSpans()) {
			self::$spans[] = $span;
		}

		if (self::$adapter) {
			try {
				self::$adapter->record($span);
			} catch (\Throwable) {
				// Observability adapters must never break application flow.
			}
		}

	}

	/**
	 * Record a non-span event and forward it to adapters that support events.
	 */
	public static function recordEvent(ObservabilityEvent $event): void {

		if (!self::shouldRecordEvent()) {
			return;
		}

		if (count(self::$events) < self::maxEvents()) {
			self::$events[] = $event;
		}

		if (self::$adapter instanceof ObservabilityEventAdapter) {
			try {
				self::$adapter->recordEvent($event);
			} catch (\Throwable) {
				// Observability adapters must never break application flow.
			}
		}

	}

	/**
	 * Record a sanitized log event for warning and error pipelines.
	 *
	 * @param	array<string, mixed>	$attributes	Context attributes safe for observability output.
	 */
	public static function recordLogEvent(string $level, string $message, array $attributes = []): void {

		if (!self::shouldRecordEvent()) {
			return;
		}

		self::recordEvent(new ObservabilityEvent(
			'log',
			mb_substr($level, 0, 32),
			mb_substr($message, 0, 1024),
			self::sanitizeAttributes($attributes),
			self::correlationId(),
			microtime(true)
		));

	}

	/**
	 * Set the adapter that receives completed spans.
	 */
	public static function setAdapter(?ObservabilityAdapter $adapter): void {

		self::$adapter = $adapter;
		self::$eventSampled = null;
		self::$traceSampled = null;

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

		if (!self::shouldRecordTrace()) {
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

		if (!self::shouldRecordTrace()) {
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
	 * Return the event sampling decision for the current request.
	 */
	private static function eventSampled(): bool {

		if (!is_null(self::$eventSampled)) {
			return self::$eventSampled;
		}

		self::$eventSampled = self::sample(self::sampleRate('PAIR_OBSERVABILITY_ERROR_SAMPLE_RATE', 1.0));

		return self::$eventSampled;

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
	 * Return the maximum number of events retained in memory.
	 */
	private static function maxEvents(): int {

		return max(0, (int)(Env::get('PAIR_OBSERVABILITY_MAX_EVENTS') ?? 50));

	}

	/**
	 * Return the maximum number of spans retained in memory.
	 */
	private static function maxSpans(): int {

		return max(0, (int)(Env::get('PAIR_OBSERVABILITY_MAX_SPANS') ?? 100));

	}

	/**
	 * Return true when the current request should be sampled.
	 */
	private static function sample(float $rate): bool {

		if ($rate <= 0.0) {
			return false;
		}

		if ($rate >= 1.0) {
			return true;
		}

		return (random_int(0, 1000000) / 1000000) <= $rate;

	}

	/**
	 * Return one sanitized sample rate from Env.
	 */
	private static function sampleRate(string $key, float $fallback): float {

		$value = Env::get($key);

		if (!is_numeric($value)) {
			return $fallback;
		}

		return min(1.0, max(0.0, (float)$value));

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
	 * Return whether event adapters should receive non-span events.
	 */
	private static function shouldRecordEvent(): bool {

		return self::isEnabled() and self::eventSampled();

	}

	/**
	 * Return whether trace spans should be recorded for this request.
	 */
	private static function shouldRecordTrace(): bool {

		return self::isEnabled() and self::traceSampled();

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

	/**
	 * Return the trace sampling decision for the current request.
	 */
	private static function traceSampled(): bool {

		if (!is_null(self::$traceSampled)) {
			return self::$traceSampled;
		}

		self::$traceSampled = self::sample(self::sampleRate('PAIR_OBSERVABILITY_TRACE_SAMPLE_RATE', 1.0));

		return self::$traceSampled;

	}

}
