<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Core\ObservabilityEvent;
use Pair\Core\ObservabilitySpan;
use Pair\Services\SentryObservabilityAdapter;
use Pair\Tests\Support\TestCase;

/**
 * Covers SentryObservabilityAdapter envelope shaping without calling Sentry.
 */
class SentryObservabilityAdapterTest extends TestCase {

	/**
	 * Verify completed spans are sent as Sentry transaction envelopes.
	 */
	public function testRecordBuildsTransactionEnvelope(): void {

		$adapter = new FakeSentryObservabilityAdapter();
		$span = new ObservabilitySpan('unit.transaction', ['route' => '/unit'], 'trace-correlation', 1700000000.0);
		$span->finish([], 'ok');

		$adapter->record($span);
		$envelope = $adapter->lastEnvelope();

		$this->assertSame('transaction', $envelope['item']['type']);
		$this->assertSame('transaction', $envelope['payload']['type']);
		$this->assertSame('unit.transaction', $envelope['payload']['transaction']);
		$this->assertSame('/unit', $envelope['payload']['extra']['route']);
		$this->assertSame('trace-correlation', $envelope['payload']['tags']['correlation_id']);

	}

	/**
	 * Verify log events are sent as Sentry event envelopes.
	 */
	public function testRecordEventBuildsEventEnvelope(): void {

		$adapter = new FakeSentryObservabilityAdapter();

		$adapter->recordEvent(new ObservabilityEvent('log', 'error', 'Expected failure', [
			'component' => 'unit',
		], 'event-correlation', 1700000000.0));

		$envelope = $adapter->lastEnvelope();

		$this->assertSame('event', $envelope['item']['type']);
		$this->assertSame('error', $envelope['payload']['level']);
		$this->assertSame('Expected failure', $envelope['payload']['message']);
		$this->assertSame('event-correlation', $envelope['payload']['tags']['correlation_id']);
		$this->assertSame('unit', $envelope['payload']['extra']['component']);

	}

	/**
	 * Verify an empty DSN keeps the adapter as a safe no-op.
	 */
	public function testEmptyDsnDoesNotSendEnvelope(): void {

		$adapter = new FakeSentryObservabilityAdapter('');
		$span = new ObservabilitySpan('unit.noop', [], 'correlation', 1700000000.0);
		$span->finish();

		$adapter->record($span);

		$this->assertSame([], $adapter->lastEnvelope());

	}

}

/**
 * Fake Sentry adapter that captures envelopes instead of sending HTTP requests.
 */
final class FakeSentryObservabilityAdapter extends SentryObservabilityAdapter {

	/**
	 * Captured decoded envelopes.
	 *
	 * @var	list<array<string, mixed>>
	 */
	private array $envelopes = [];

	/**
	 * Build the fake adapter with deterministic configuration.
	 */
	public function __construct(?string $dsn = 'https://public@example.test/123') {

		parent::__construct($dsn, 'testing', '1.2.3', 1.0, 1.0);

	}

	/**
	 * Return the most recent decoded envelope.
	 *
	 * @return	array<string, mixed>
	 */
	public function lastEnvelope(): array {

		return $this->envelopes[count($this->envelopes) - 1] ?? [];

	}

	/**
	 * Decode the envelope body and capture it.
	 *
	 * @return	array<string, mixed>
	 */
	protected function requestEnvelope(string $body): array {

		$lines = array_values(array_filter(explode("\n", trim($body)), static fn (string $line): bool => '' !== $line));

		$this->envelopes[] = [
			'header' => json_decode($lines[0] ?? '{}', true),
			'item' => json_decode($lines[1] ?? '{}', true),
			'payload' => json_decode($lines[2] ?? '{}', true),
		];

		return ['id' => 'event-id'];

	}

}
