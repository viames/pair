<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Core\ObservabilityAdapter;
use Pair\Core\ObservabilitySpan;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Dependency-free OTLP/HTTP JSON trace exporter.
 */
class OpenTelemetryHttpExporter implements ObservabilityAdapter {

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * OTLP trace endpoint.
	 */
	private string $endpoint;

	/**
	 * Custom request headers.
	 *
	 * @var	array<string, string>
	 */
	private array $headers;

	/**
	 * Service name reported in resource attributes.
	 */
	private string $serviceName;

	/**
	 * Service version reported in resource attributes.
	 */
	private string $serviceVersion;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Build an OTLP exporter from explicit arguments or Env defaults.
	 */
	public function __construct(?string $endpoint = null, array|string|null $headers = null, ?string $serviceName = null, ?string $serviceVersion = null, ?int $timeout = null, ?int $connectTimeout = null) {

		$this->endpoint = $this->sanitizeEndpoint((string)($endpoint ?? Env::get('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT')));
		$this->headers = is_array($headers) ? $headers : $this->parseHeaders((string)($headers ?? Env::get('OTEL_EXPORTER_OTLP_HEADERS')));
		$this->serviceName = trim((string)($serviceName ?? Env::get('OTEL_SERVICE_NAME') ?: Env::get('APP_NAME') ?: 'pair-app'));
		$this->serviceVersion = trim((string)($serviceVersion ?? Env::get('OTEL_SERVICE_VERSION') ?: Env::get('APP_VERSION') ?: ''));
		$this->timeout = max(1, (int)($timeout ?? Env::get('OTEL_TIMEOUT') ?? 10));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('OTEL_CONNECT_TIMEOUT') ?? 3));

	}

	/**
	 * Check whether an OTLP trace endpoint is configured.
	 */
	public function endpointSet(): bool {

		return '' !== $this->endpoint;

	}

	/**
	 * Export one completed span to the OTLP/HTTP endpoint.
	 */
	public function record(ObservabilitySpan $span): void {

		if (!$this->endpointSet()) {
			return;
		}

		$this->requestJson($this->tracePayload($span));

	}

	/**
	 * Convert attributes into OTLP key-value entries.
	 *
	 * @return	list<array<string, mixed>>
	 */
	private function attributes(array $attributes): array {

		$items = [];

		foreach ($attributes as $key => $value) {
			$items[] = [
				'key' => (string)$key,
				'value' => $this->otlpValue($value),
			];
		}

		return $items;

	}

	/**
	 * Return headers used for the OTLP request.
	 *
	 * @return	list<string>
	 */
	private function httpHeaders(): array {

		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
		];

		foreach ($this->headers as $name => $value) {
			$headers[] = $name . ': ' . $value;
		}

		return $headers;

	}

	/**
	 * Return a valid OTLP AnyValue structure.
	 *
	 * @return	array<string, mixed>
	 */
	private function otlpValue(mixed $value): array {

		if (is_bool($value)) {
			return ['boolValue' => $value];
		}

		if (is_int($value)) {
			return ['intValue' => (string)$value];
		}

		if (is_float($value)) {
			return ['doubleValue' => $value];
		}

		return ['stringValue' => (string)$value];

	}

	/**
	 * Parse comma-separated OTLP headers.
	 *
	 * @return	array<string, string>
	 */
	private function parseHeaders(string $headers): array {

		$parsed = [];

		foreach (explode(',', $headers) as $header) {
			if (!str_contains($header, '=')) {
				continue;
			}

			[$name, $value] = explode('=', $header, 2);
			$name = trim($name);

			if ('' !== $name) {
				$parsed[$name] = trim($value);
			}
		}

		return $parsed;

	}

	/**
	 * Execute the OTLP/HTTP JSON request.
	 *
	 * @return	array<string, mixed>
	 */
	protected function requestJson(array $payload): array {

		$curl = curl_init($this->endpoint);

		if (false === $curl) {
			throw new PairException('Unable to initialize OpenTelemetry request.', ErrorCodes::OPENTELEMETRY_ERROR);
		}

		$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === $encodedPayload) {
			curl_close($curl);
			throw new PairException('Unable to encode OpenTelemetry request payload.', ErrorCodes::OPENTELEMETRY_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->httpHeaders());
		curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('OpenTelemetry request failed: ' . $error, ErrorCodes::OPENTELEMETRY_ERROR);
		}

		curl_close($curl);

		if ($statusCode >= 400) {
			throw new PairException('OpenTelemetry request failed with HTTP ' . $statusCode . '.', ErrorCodes::OPENTELEMETRY_ERROR);
		}

		$decoded = json_decode(trim((string)$responseBody), true);

		return is_array($decoded) ? $decoded : [];

	}

	/**
	 * Validate and normalize the OTLP trace endpoint.
	 */
	private function sanitizeEndpoint(string $endpoint): string {

		$endpoint = trim($endpoint);

		if ('' === $endpoint) {
			return '';
		}

		if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
			throw new PairException('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT is not valid.', ErrorCodes::OPENTELEMETRY_ERROR);
		}

		return $endpoint;

	}

	/**
	 * Return a stable 16-character span id.
	 */
	private function spanId(ObservabilitySpan $span): string {

		return substr(hash('sha256', $span->name() . '|' . $span->startedAt()), 0, 16);

	}

	/**
	 * Return an OTLP status structure.
	 *
	 * @return	array<string, mixed>
	 */
	private function status(ObservabilitySpan $span): array {

		return [
			'code' => 'error' === $span->status() ? 2 : 1,
		];

	}

	/**
	 * Return an epoch timestamp in nanoseconds as a string.
	 */
	private function timestampNano(?float $timestamp): string {

		return (string)((int)(($timestamp ?? microtime(true)) * 1000000000));

	}

	/**
	 * Build an OTLP JSON trace payload for one span.
	 *
	 * @return	array<string, mixed>
	 */
	private function tracePayload(ObservabilitySpan $span): array {

		$resourceAttributes = [
			'service.name' => $this->serviceName,
		];

		if ('' !== $this->serviceVersion) {
			$resourceAttributes['service.version'] = $this->serviceVersion;
		}

		return [
			'resourceSpans' => [
				[
					'resource' => [
						'attributes' => $this->attributes($resourceAttributes),
					],
					'scopeSpans' => [
						[
							'scope' => [
								'name' => 'pair',
								'version' => (string)Env::get('APP_VERSION'),
							],
							'spans' => [
								[
									'traceId' => $this->traceId($span->correlationId()),
									'spanId' => $this->spanId($span),
									'name' => $span->name(),
									'kind' => 1,
									'startTimeUnixNano' => $this->timestampNano($span->startedAt()),
									'endTimeUnixNano' => $this->timestampNano($span->endedAt()),
									'attributes' => $this->attributes($span->attributes()),
									'status' => $this->status($span),
								],
							],
						],
					],
				],
			],
		];

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

}
