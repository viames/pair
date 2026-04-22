<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Helpers;

use Pair\Helpers\LogBar;
use Pair\Helpers\LogBarSql;
use Pair\Tests\Support\TestCase;

/**
 * Covers safe LogBar SQL rendering and request inspector aggregation.
 */
class LogBarTest extends TestCase {

	/**
	 * Reset LogBar state and thresholds before each test.
	 */
	protected function setUp(): void {

		parent::setUp();

		LogBar::getInstance()->reset();
		$_ENV['PAIR_LOGBAR_SLOW_REQUEST_MS'] = 250;
		$_ENV['PAIR_LOGBAR_SLOW_QUERY_MS'] = 20;
		$_ENV['PAIR_LOGBAR_QUERY_BUDGET'] = 30;
		$_ENV['PAIR_LOGBAR_DUPLICATE_QUERY_BUDGET'] = 3;
		$_ENV['PAIR_LOGBAR_MAX_EVENTS'] = 500;
		$_ENV['PAIR_LOGBAR_SHOW_SQL_VALUES'] = false;

	}

	/**
	 * Build a structured query event for inspector-only tests.
	 */
	private function queryEvent(string $sql, float $duration, int $rows, float $start = 0.0): \stdClass {

		$event = new \stdClass();
		$event->id = 'test-query-' . spl_object_id($event);
		$event->type = 'query';
		$event->description = LogBarSql::normalize($sql);
		$event->subtext = $rows . ' ' . (1 === $rows ? 'row' : 'rows');
		$event->start = $start;
		$event->duration = $duration;
		$event->chrono = $duration;
		$event->status = 'ok';
		$event->attributes = [
			'fingerprint' => LogBarSql::fingerprint($sql),
			'normalizedSql' => LogBarSql::normalize($sql),
			'operation' => LogBarSql::operation($sql),
			'rows' => $rows,
			'table' => LogBarSql::table($sql),
		];

		return $event;

	}

	/**
	 * Build a structured non-query event for inspector-only tests.
	 */
	private function noticeEvent(float $duration, float $start = 0.0): \stdClass {

		$event = new \stdClass();
		$event->id = 'test-notice-' . spl_object_id($event);
		$event->type = 'notice';
		$event->description = 'Controller work';
		$event->subtext = null;
		$event->start = $start;
		$event->duration = $duration;
		$event->chrono = $duration;
		$event->status = 'ok';
		$event->attributes = [];

		return $event;

	}

	/**
	 * Verify equivalent SQL renders with placeholders instead of values.
	 */
	public function testSqlNormalizationRedactsLiteralsAndParameters(): void {

		$sql = 'SELECT * FROM sessions WHERE id = :sid AND email = ? LIMIT 10';

		$this->assertSame(
			'SELECT * FROM sessions WHERE id = ? AND email = ? LIMIT ?',
			LogBarSql::normalize($sql)
		);
		$this->assertSame(
			'SELECT * FROM sessions WHERE id = ? AND email = ? LIMIT ?',
			LogBarSql::redactText(LogBarSql::normalize($sql))
		);

	}

	/**
	 * Verify default LogBar query previews never interpolate bound values.
	 */
	public function testDefaultQueryPreviewHidesBoundValues(): void {

		$sql = 'SELECT * FROM sessions WHERE id = :sid LIMIT :limit';
		$preview = LogBarSql::render($sql, [
			'sid' => 'raw-session-id',
			'limit' => 1,
		]);

		$this->assertSame('SELECT * FROM sessions WHERE id = ? LIMIT ?', $preview);
		$this->assertStringNotContainsString('raw-session-id', $preview);

	}

	/**
	 * Verify opt-in SQL values still mask sensitive names and email-like values.
	 */
	public function testSqlValueRenderingMasksSensitiveValuesWhenEnabled(): void {

		$sql = 'SELECT * FROM users WHERE email = :email AND name = :name AND api_key = :apiKey';
		$preview = LogBarSql::render($sql, [
			'email' => 'person@example.com',
			'name' => 'Jane',
			'apiKey' => 'secret-key',
		], true);

		$this->assertSame(
			"SELECT * FROM users WHERE email = '[redacted]' AND name = 'Jane' AND api_key = '[redacted]'",
			$preview
		);

	}

	/**
	 * Verify fingerprints ignore literal value differences.
	 */
	public function testQueryFingerprintIgnoresParameterValues(): void {

		$left = LogBarSql::fingerprint('SELECT * FROM users WHERE id = 10 AND status = "active"');
		$right = LogBarSql::fingerprint('SELECT * FROM users WHERE id = 20 AND status = "disabled"');

		$this->assertSame($left, $right);

	}

	/**
	 * Verify query groups aggregate count, timing, rows, operation, and table.
	 */
	public function testQueryAggregationBuildsStats(): void {

		$logBar = LogBar::getInstance();
		$events = [
			$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.010, 2, 0.000),
			$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.020, 3, 0.010),
			$this->queryEvent('UPDATE users SET name = ? WHERE id = ?', 0.005, 1, 0.030),
		];

		$this->setInaccessibleProperty($logBar, 'events', $events);
		$data = $this->invokeInaccessibleMethod($logBar, 'collectInspectorData');
		$groups = $data['queryGroups'];

		$this->assertCount(2, $groups);
		$this->assertSame(2, $groups[0]['count']);
		$this->assertSame(30.0, $groups[0]['totalMs']);
		$this->assertSame(15.0, $groups[0]['avgMs']);
		$this->assertSame(20.0, $groups[0]['maxMs']);
		$this->assertSame(5, $groups[0]['rows']);
		$this->assertSame('SELECT', $groups[0]['operation']);
		$this->assertSame('users', $groups[0]['table']);

	}

	/**
	 * Verify DB-bound, high-count, and duplicate query findings are generated.
	 */
	public function testOverviewFindingsReportDbBoundHighQueryRequests(): void {

		$logBar = LogBar::getInstance();
		$events = [];
		$start = 0.0;
		$queryDuration = 0.133 / 79;

		for ($i = 0; $i < 79; $i++) {
			$events[] = $this->queryEvent('SELECT * FROM sessions WHERE user_id = ?', $queryDuration, 1, $start);
			$start += $queryDuration;
		}

		$events[] = $this->noticeEvent(0.036, $start);

		$this->setInaccessibleProperty($logBar, 'events', $events);
		$data = $this->invokeInaccessibleMethod($logBar, 'collectInspectorData');
		$titles = array_column($data['findings'], 'title');

		$this->assertContains('DB-bound request', $titles);
		$this->assertContains('High query count', $titles);
		$this->assertContains('Duplicate query fingerprints', $titles);

	}

	/**
	 * Verify the legacy public event API remains callable with the same signature.
	 */
	public function testEventPublicApiRemainsBackwardCompatible(): void {

		$method = new \ReflectionMethod(LogBar::class, 'event');
		$parameters = $method->getParameters();

		$this->assertTrue($method->isPublic());
		$this->assertTrue($method->isStatic());
		$this->assertSame(3, $method->getNumberOfParameters());
		$this->assertSame('description', $parameters[0]->getName());
		$this->assertSame('type', $parameters[1]->getName());
		$this->assertSame('notice', $parameters[1]->getDefaultValue());
		$this->assertSame('subtext', $parameters[2]->getName());
		$this->assertTrue($parameters[2]->allowsNull());

		LogBar::event('Legacy notice', 'notice', 'subtext');
		$this->assertSame(0, LogBar::getInstance()->getErrorCount());

	}

}
