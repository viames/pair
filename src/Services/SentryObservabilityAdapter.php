<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Core\ObservabilityAdapter;
use Pair\Core\ObservabilityEvent;
use Pair\Core\ObservabilityEventAdapter;
use Pair\Core\ObservabilitySpan;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Dependency-free Sentry adapter using the envelope ingestion endpoint.
 */
class SentryObservabilityAdapter implements ObservabilityAdapter, ObservabilityEventAdapter {

	/**
	 * Default Sentry client identifier.
	 */
	private const CLIENT_NAME = 'pair-php/4';

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Parsed Sentry DSN data.
	 *
	 * @var	array{endpoint: string, publicKey: string}|null
	 */
	private ?array $dsn = null;

	/**
	 * Sentry environment.
	 */
	private string $environment;

	/**
	 * Event sample rate.
	 */
	private float $errorSampleRate;

	/**
	 * Release identifier.
	 */
	private string $release;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Transaction sample rate.
	 */
	private float $traceSampleRate;

	/**
	 * Build a Sentry adapter from explicit arguments or Env defaults.
	 */
	public function __construct(?string $dsn = null, ?string $environment = null, ?string $release = null, ?float $traceSampleRate = null, ?float $errorSampleRate = null, ?int $timeout = null, ?int $connectTimeout = null) {

		$dsn = trim((string)($dsn ?? Env::get('SENTRY_DSN')));
		$this->dsn = '' === $dsn ? null : $this->parseDsn($dsn);
		$this->environment = trim((string)($environment ?? Env::get('SENTRY_ENVIRONMENT') ?: Env::get('APP_ENV') ?: 'production'));
		$this->release = trim((string)($release ?? Env::get('SENTRY_RELEASE') ?: Env::get('APP_VERSION') ?: ''));
		$this->traceSampleRate = $this->sampleRate($traceSampleRate ?? Env::get('SENTRY_TRACES_SAMPLE_RATE') ?? 1.0);
		$this->errorSampleRate = $this->sampleRate($errorSampleRate ?? Env::get('SENTRY_ERROR_SAMPLE_RATE') ?? 1.0);
		$this->timeout = max(1, (int)($timeout ?? Env::get('SENTRY_TIMEOUT') ?? 10));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('SENTRY_CONNECT_TIMEOUT') ?? 3));

	}

	/**
	 * Check whether a DSN is configured.
	 */
	public function dsnSet(): bool {

		return !is_null($this->dsn);

	}

	/**
	 * Record one completed span as a Sentry transaction.
	 */
	public function record(ObservabilitySpan $span): void {

		if (!$this->dsnSet() or !$this->sample($this->traceSampleRate)) {
			return;
		}

		$this->sendEnvelope('transaction', $this->transactionPayload($span));

	}

	/**
	 * Record one log or error event as a Sentry event.
	 */
	public function recordEvent(ObservabilityEvent $event): void {

		if (!$this->dsnSet() or !$this->sample($this->errorSampleRate)) {
			return;
		}

		$this->sendEnvelope('event', $this->eventPayload($event));

	}

	/**
	 * Build one envelope body and send it to Sentry.
	 *
	 * @return	array<string, mixed>
	 */
	protected function sendEnvelope(string $type, array $payload): array {

		if (!$this->dsn) {
			return [];
		}

		$body = implode("\n", [
			json_encode(['event_id' => $payload['event_id'], 'sent_at' => gmdate('c')], JSON_UNESCAPED_SLASHES),
			json_encode(['type' => $type], JSON_UNESCAPED_SLASHES),
			json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
		]) . "\n";

		return $this->requestEnvelope($body);

	}

	/**
	 * Return an event ID accepted by Sentry.
	 */
	private function eventId(): string {

		return bin2hex(random_bytes(16));

	}

	/**
	 * Build a Sentry event payload from an observable event.
	 *
	 * @return	array<string, mixed>
	 */
	private function eventPayload(ObservabilityEvent $event): array {

		return $this->withoutNullValues([
			'event_id' => $this->eventId(),
			'timestamp' => gmdate('c', (int)$event->timestamp()),
			'platform' => 'php',
			'logger' => 'pair',
			'level' => $event->level(),
			'message' => $event->message(),
			'environment' => $this->environment,
			'release' => $this->release ?: null,
			'tags' => [
				'correlation_id' => $event->correlationId(),
				'event' => $event->name(),
			],
			'extra' => $event->attributes(),
		]);

	}

	/**
	 * Parse a Sentry DSN into envelope endpoint metadata.
	 *
	 * @return	array{endpoint: string, publicKey: string}
	 */
	private function parseDsn(string $dsn): array {

		$parts = parse_url($dsn);

		if (!is_array($parts) or empty($parts['scheme']) or empty($parts['host']) or empty($parts['user']) or empty($parts['path'])) {
			throw new PairException('SENTRY_DSN is not valid.', ErrorCodes::SENTRY_ERROR);
		}

		$path = trim($parts['path'], '/');
		$segments = '' === $path ? [] : explode('/', $path);
		$projectId = array_pop($segments);

		if (!$projectId) {
			throw new PairException('SENTRY_DSN project id is missing.', ErrorCodes::SENTRY_ERROR);
		}

		$port = isset($parts['port']) ? ':' . $parts['port'] : '';
		$basePath = count($segments) ? '/' . implode('/', $segments) : '';
		$endpoint = $parts['scheme'] . '://' . $parts['host'] . $port . $basePath . '/api/' . rawurlencode($projectId) . '/envelope/';
		$query = http_build_query([
			'sentry_key' => $parts['user'],
			'sentry_version' => '7',
			'sentry_client' => self::CLIENT_NAME,
		]);

		return [
			'endpoint' => $endpoint . '?' . $query,
			'publicKey' => $parts['user'],
		];

	}

	/**
	 * Execute the Sentry envelope HTTP request.
	 *
	 * @return	array<string, mixed>
	 */
	protected function requestEnvelope(string $body): array {

		if (!$this->dsn) {
			return [];
		}

		$curl = curl_init($this->dsn['endpoint']);

		if (false === $curl) {
			throw new PairException('Unable to initialize Sentry request.', ErrorCodes::SENTRY_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/x-sentry-envelope',
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Sentry request failed: ' . $error, ErrorCodes::SENTRY_ERROR);
		}

		curl_close($curl);

		if ($statusCode >= 400) {
			throw new PairException('Sentry request failed with HTTP ' . $statusCode . '.', ErrorCodes::SENTRY_ERROR);
		}

		$decoded = json_decode(trim((string)$responseBody), true);

		return is_array($decoded) ? $decoded : [];

	}

	/**
	 * Return true when a sampled request should be sent.
	 */
	private function sample(float $rate): bool {

		if ($rate <= 0.0) {
			return false;
		}

		if ($rate >= 1.0) {
			return true;
		}

		return (random_int(0, 1000000) / 1000000) <= $rate;

	}

	/**
	 * Normalize one sample rate.
	 */
	private function sampleRate(mixed $rate): float {

		return min(1.0, max(0.0, is_numeric($rate) ? (float)$rate : 1.0));

	}

	/**
	 * Return a stable 16-character span id.
	 */
	private function spanId(ObservabilitySpan $span): string {

		return substr(hash('sha256', $span->name() . '|' . $span->startedAt()), 0, 16);

	}

	/**
	 * Return a stable 32-character trace id from the Pair correlation id.
	 */
	private function traceId(string $correlationId): string {

		if (preg_match('/^[a-f0-9]{32}$/i', $correlationId)) {
			return strtolower($correlationId);
		}

		return md5($correlationId);

	}

	/**
	 * Build a Sentry transaction payload from a completed span.
	 *
	 * @return	array<string, mixed>
	 */
	private function transactionPayload(ObservabilitySpan $span): array {

		return $this->withoutNullValues([
			'event_id' => $this->eventId(),
			'type' => 'transaction',
			'transaction' => $span->name(),
			'start_timestamp' => $span->startedAt(),
			'timestamp' => $span->endedAt() ?? microtime(true),
			'platform' => 'php',
			'environment' => $this->environment,
			'release' => $this->release ?: null,
			'contexts' => [
				'trace' => [
					'trace_id' => $this->traceId($span->correlationId()),
					'span_id' => $this->spanId($span),
					'op' => $span->name(),
					'status' => $span->status() ?: 'ok',
				],
			],
			'tags' => [
				'correlation_id' => $span->correlationId(),
			],
			'extra' => $span->attributes(),
		]);

	}

	/**
	 * Remove null values recursively from payloads.
	 *
	 * @return	array<string, mixed>
	 */
	private function withoutNullValues(array $values): array {

		$filteredValues = [];

		foreach ($values as $key => $value) {
			if (is_null($value)) {
				continue;
			}

			if (is_array($value)) {
				$value = $this->withoutNullValues($value);
			}

			$filteredValues[$key] = $value;
		}

		return $filteredValues;

	}

}
