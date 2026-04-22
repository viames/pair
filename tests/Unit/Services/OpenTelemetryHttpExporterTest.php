<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Core\ObservabilitySpan;
use Pair\Services\OpenTelemetryHttpExporter;
use Pair\Tests\Support\TestCase;

/**
 * Covers OpenTelemetryHttpExporter payload shaping without calling an OTLP collector.
 */
class OpenTelemetryHttpExporterTest extends TestCase {

	/**
	 * Verify completed spans are exported as OTLP/HTTP JSON trace payloads.
	 */
	public function testRecordBuildsOtlpTracePayload(): void {

		$exporter = new FakeOpenTelemetryHttpExporter();
		$span = new ObservabilitySpan('unit.otel', [
			'cache.hit' => true,
			'db.rows' => 3,
		], 'otel-correlation', 1700000000.0);
		$span->finish([], 'ok');

		$exporter->record($span);
		$payload = $exporter->lastPayload();
		$otelSpan = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

		$this->assertSame('unit.otel', $otelSpan['name']);
		$this->assertSame(md5('otel-correlation'), $otelSpan['traceId']);
		$this->assertSame(1, $otelSpan['status']['code']);
		$this->assertSame('service.name', $payload['resourceSpans'][0]['resource']['attributes'][0]['key']);
		$this->assertSame('pair-test', $payload['resourceSpans'][0]['resource']['attributes'][0]['value']['stringValue']);

	}

	/**
	 * Verify an empty endpoint keeps the exporter as a safe no-op.
	 */
	public function testEmptyEndpointDoesNotExport(): void {

		$exporter = new FakeOpenTelemetryHttpExporter('');
		$span = new ObservabilitySpan('unit.noop', [], 'correlation', 1700000000.0);
		$span->finish();

		$exporter->record($span);

		$this->assertSame([], $exporter->lastPayload());

	}

}

/**
 * Fake OTLP exporter that captures payloads instead of sending HTTP requests.
 */
final class FakeOpenTelemetryHttpExporter extends OpenTelemetryHttpExporter {

	/**
	 * Captured OTLP payloads.
	 *
	 * @var	list<array<string, mixed>>
	 */
	private array $payloads = [];

	/**
	 * Build the fake exporter with deterministic configuration.
	 */
	public function __construct(?string $endpoint = 'https://otel.example.test/v1/traces') {

		parent::__construct($endpoint, [], 'pair-test', '1.2.3');

	}

	/**
	 * Return the most recent captured payload.
	 *
	 * @return	array<string, mixed>
	 */
	public function lastPayload(): array {

		return $this->payloads[count($this->payloads) - 1] ?? [];

	}

	/**
	 * Capture the payload and return a deterministic response.
	 *
	 * @return	array<string, mixed>
	 */
	protected function requestJson(array $payload): array {

		$this->payloads[] = $payload;

		return [];

	}

}
