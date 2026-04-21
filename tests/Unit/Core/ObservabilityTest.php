<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Observability;
use Pair\Core\ObservabilityAdapter;
use Pair\Core\ObservabilitySpan;
use Pair\Tests\Support\TestCase;

/**
 * Covers the lightweight Pair runtime observability facade.
 */
class ObservabilityTest extends TestCase {

	/**
	 * Verify instrumentation stays no-op when neither env nor adapter enables it.
	 */
	public function testTraceIsNoOpWhenDisabled(): void {

		$result = Observability::trace('unit.disabled', function (): string {
			return 'ok';
		});

		$this->assertSame('ok', $result);
		$this->assertSame([], Observability::spans());

	}

	/**
	 * Verify enabled tracing records spans and forwards them to the configured adapter.
	 */
	public function testTraceRecordsSpanAndForwardsToAdapter(): void {

		$adapter = new class implements ObservabilityAdapter {

			/**
			 * Spans received by this test adapter.
			 *
			 * @var	list<ObservabilitySpan>
			 */
			public array $spans = [];

			/**
			 * Store the completed span for assertions.
			 */
			public function record(ObservabilitySpan $span): void {

				$this->spans[] = $span;

			}

		};

		Observability::setAdapter($adapter);
		Observability::setCorrelationId('trace-test');

		$result = Observability::trace('unit.enabled', function (): string {
			return 'done';
		}, [
			'action' => 'test',
			'accessToken' => 'secret-token',
		]);

		$spans = Observability::spans();

		$this->assertSame('done', $result);
		$this->assertCount(1, $spans);
		$this->assertSame($spans, $adapter->spans);
		$this->assertSame('unit.enabled', $spans[0]->name());
		$this->assertSame('trace-test', $spans[0]->correlationId());
		$this->assertSame('ok', $spans[0]->status());
		$this->assertSame('test', $spans[0]->attributes()['action']);
		$this->assertSame('[redacted]', $spans[0]->attributes()['accessToken']);
		$this->assertGreaterThanOrEqual(0.0, $spans[0]->durationMs());

	}

	/**
	 * Verify exceptions are still propagated while the failing span is recorded.
	 */
	public function testTraceRecordsExceptionStatus(): void {

		Observability::enable();

		try {
			Observability::trace('unit.failure', function (): void {
				throw new \RuntimeException('Expected failure');
			});
			$this->fail('The traced exception was not propagated.');
		} catch (\RuntimeException $e) {
			$this->assertSame('Expected failure', $e->getMessage());
		}

		$spans = Observability::spans();

		$this->assertCount(1, $spans);
		$this->assertSame('error', $spans[0]->status());
		$this->assertSame(\RuntimeException::class, $spans[0]->attributes()['exception']);

	}

	/**
	 * Verify debug headers expose correlation and timing only when debug headers are allowed.
	 */
	public function testDebugHeadersExposeCorrelationAndTimingInDebugMode(): void {

		$_ENV['APP_DEBUG'] = true;
		$_ENV['PAIR_OBSERVABILITY_DEBUG_HEADERS'] = true;

		Observability::setCorrelationId('debug-correlation');
		Observability::trace('unit.debug', function (): void {});

		$headers = Observability::debugHeaders();

		$this->assertSame('debug-correlation', $headers['X-Pair-Correlation-Id']);
		$this->assertSame('1', $headers['X-Pair-Trace-Spans']);
		$this->assertArrayHasKey('X-Pair-Trace-Duration-Ms', $headers);
		$this->assertIsNumeric($headers['X-Pair-Trace-Duration-Ms']);

	}

	/**
	 * Verify debug headers stay hidden when APP_DEBUG is disabled.
	 */
	public function testDebugHeadersStayHiddenOutsideDebugMode(): void {

		$_ENV['APP_DEBUG'] = false;
		$_ENV['PAIR_OBSERVABILITY_DEBUG_HEADERS'] = true;

		Observability::setCorrelationId('hidden-correlation');

		$this->assertSame([], Observability::debugHeaders());

	}

}
