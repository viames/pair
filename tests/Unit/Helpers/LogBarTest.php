<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Helpers;

use Pair\Helpers\LogBar;
use Pair\Helpers\LogBarEntry;
use Pair\Helpers\LogBarInspector;
use Pair\Helpers\LogBarRenderer;
use Pair\Helpers\LogBarSql;
use Pair\Html\UiTheme;
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
		$_ENV['APP_ENV'] = 'development';
		unset($_ENV['PAIR_LOGBAR_ENABLED']);
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
	private function queryEvent(string $sql, float $duration, int $rows, float $start = 0.0, string $parameterFingerprint = '', ?string $description = null, string $status = 'ok', ?string $error = null): LogBarEntry {

		$attributes = [
			'fingerprint' => LogBarSql::fingerprint($sql),
			'normalizedSql' => LogBarSql::normalize($sql),
			'operation' => LogBarSql::operation($sql),
			'rows' => $rows,
			'table' => LogBarSql::table($sql),
		];

		if ('' !== $parameterFingerprint) {
			$attributes['parameterFingerprint'] = $parameterFingerprint;
		}

		if (is_string($error)) {
			$attributes['error'] = $error;
		}

		return new LogBarEntry(
			'test-query-' . substr(hash('sha1', $sql . $duration . $start), 0, 8),
			'query',
			$description ?? LogBarSql::normalize($sql),
			$rows . ' ' . (1 === $rows ? 'row' : 'rows'),
			$start,
			$duration,
			$status,
			$attributes
		);

	}

	/**
	 * Build a structured non-query event for inspector-only tests.
	 */
	private function noticeEvent(float $duration, float $start = 0.0): LogBarEntry {

		return new LogBarEntry(
			'test-notice-' . substr(hash('sha1', (string)($duration . $start)), 0, 8),
			'notice',
			'Controller work',
			null,
			$start,
			$duration,
			'ok',
		);

	}

	/**
	 * Build a structured warning event for renderer filter tests.
	 */
	private function warningEvent(float $duration, float $start = 0.0): LogBarEntry {

		return new LogBarEntry(
			'test-warning-' . substr(hash('sha1', (string)($duration . $start)), 0, 8),
			'warning',
			'Warning event',
			null,
			$start,
			$duration,
			'warning',
		);

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
		$this->assertSame($left, LogBarSql::fingerprintFromNormalized('SELECT * FROM users WHERE id = ? AND status = ?'));

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
		$this->assertStringContainsString(LogBarSql::fingerprint('SELECT * FROM users WHERE id = ?'), $groups[0]['searchText']);

	}

	/**
	 * Verify query groups show rendered SQL while keeping normalized metadata for grouping.
	 */
	public function testQueryAggregationDisplaysRenderedSql(): void {

		$logBar = LogBar::getInstance();
		$events = [
			$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.010, 1, 0.000, 'user-7', 'SELECT * FROM `users` WHERE `id` = 7'),
		];

		$this->setInaccessibleProperty($logBar, 'events', $events);
		$data = $this->invokeInaccessibleMethod($logBar, 'collectInspectorData');
		$groups = $data['queryGroups'];

		$this->assertSame('SELECT * FROM `users` WHERE `id` = 7', $groups[0]['sql']);
		$this->assertSame('SELECT', $groups[0]['operation']);
		$this->assertSame('users', $groups[0]['table']);

	}

	/**
	 * Verify failed queries stay visible in the query pane and warning/error filters.
	 */
	public function testQueryAggregationKeepsFailedQueryStatus(): void {

		$logBar = LogBar::getInstance();
		$events = [
			$this->queryEvent(
				'SELECT test FROM users WHERE id = ?',
				0.001,
				0,
				0.000,
				'user-1',
				'SELECT `test` FROM `users` WHERE `id` = 1',
				'error',
				"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'test' in 'field list'"
			),
		];

		$this->setInaccessibleProperty($logBar, 'events', $events);
		$data = $this->invokeInaccessibleMethod($logBar, 'collectInspectorData');
		$groups = $data['queryGroups'];

		$this->assertSame('error', $groups[0]['status']);
		$this->assertStringContainsString('Unknown column', $groups[0]['error']);
		$this->assertStringContainsString('error', $groups[0]['searchText']);

		UiTheme::setCurrent('bootstrap');
		$renderer = new LogBarRenderer(new LogBarInspector(250, 20, 30, 3, 80.0));
		$html = $renderer->render($data, 'sample/index', 'test-correlation-id', true, true);

		$this->assertStringContainsString('logbar-query-error', $html);
		$this->assertStringContainsString('data-logbar-status="error"', $html);
		$this->assertStringContainsString('logbar-query-badge-error', $html);
		$this->assertStringContainsString('btn-danger', $html);

	}

	/**
	 * Verify equal SQL with different bound parameters does not look like one duplicate group.
	 */
	public function testQueryAggregationSeparatesDifferentBoundParameterSets(): void {

		$logBar = LogBar::getInstance();
		$events = [
			$this->queryEvent('SELECT * FROM locales WHERE id = ?', 0.010, 1, 0.000, 'locale-1'),
			$this->queryEvent('SELECT * FROM locales WHERE id = ?', 0.020, 1, 0.010, 'locale-2'),
			$this->queryEvent('SELECT * FROM locales WHERE id = ?', 0.015, 1, 0.030, 'locale-1'),
		];

		$this->setInaccessibleProperty($logBar, 'events', $events);
		$data = $this->invokeInaccessibleMethod($logBar, 'collectInspectorData');
		$groups = $data['queryGroups'];

		$this->assertCount(2, $groups);
		$this->assertSame(2, $groups[0]['count']);
		$this->assertSame(1, $groups[1]['count']);
		$this->assertSame($groups[0]['fingerprint'], $groups[1]['fingerprint']);
		$this->assertNotSame($groups[0]['groupKey'], $groups[1]['groupKey']);

	}

	/**
	 * Verify SQL values are visible by default and can still be disabled from configuration.
	 */
	public function testSqlValueRenderingIsEnabledByDefault(): void {

		$method = new \ReflectionMethod(LogBar::class, 'showSqlValues');

		unset($_ENV['PAIR_LOGBAR_SHOW_SQL_VALUES']);
		$this->assertTrue($method->invoke(null));

		$_ENV['PAIR_LOGBAR_SHOW_SQL_VALUES'] = '0';
		$this->assertFalse($method->invoke(null));

	}

	/**
	 * Verify the query API can receive measured database timing without breaking legacy calls.
	 */
	public function testQueryPublicApiAcceptsMeasuredTiming(): void {

		$method = new \ReflectionMethod(LogBar::class, 'query');
		$parameters = $method->getParameters();

		$this->assertSame(7, $method->getNumberOfParameters());
		$this->assertSame('durationMs', $parameters[3]->getName());
		$this->assertTrue($parameters[3]->allowsNull());
		$this->assertTrue($parameters[3]->isDefaultValueAvailable());
		$this->assertSame('startedAt', $parameters[4]->getName());
		$this->assertTrue($parameters[4]->allowsNull());
		$this->assertTrue($parameters[4]->isDefaultValueAvailable());
		$this->assertSame('status', $parameters[5]->getName());
		$this->assertTrue($parameters[5]->allowsNull());
		$this->assertTrue($parameters[5]->isDefaultValueAvailable());
		$this->assertSame('error', $parameters[6]->getName());
		$this->assertTrue($parameters[6]->allowsNull());
		$this->assertTrue($parameters[6]->isDefaultValueAvailable());

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
		$findings = array_column($data['findings'], null, 'title');

		$this->assertTrue($data['dbBoundRequest']);
		$this->assertContains('DB-bound request', $titles);
		$this->assertContains('High query count', $titles);
		$this->assertContains('Duplicate query fingerprints', $titles);
		$this->assertSame('queries', $findings['DB-bound request']['targetTab']);
		$this->assertSame('queries', $findings['High query count']['targetTab']);
		$this->assertSame('queries', $findings['Duplicate query fingerprints']['targetTab']);
		$this->assertSame('1 fingerprint exceeds the duplicate budget; worst count is 79.', $findings['Duplicate query fingerprints']['detail']);
		$this->assertSame('Show duplicates', $findings['Duplicate query fingerprints']['actionLabel']);
		$this->assertSame('1', $findings['Duplicate query fingerprints']['duplicatesOnly']);

	}

	/**
	 * Verify DB-bound warnings require enough absolute DB time to be useful.
	 */
	public function testDbBoundFindingIgnoresTinyFastRequests(): void {

		$events = [
			$this->queryEvent('SELECT * FROM sessions WHERE user_id = ?', 0.017, 1, 0.000),
			$this->noticeEvent(0.006, 0.017),
		];
		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);
		$data = $inspector->collect($events, 0.023, 1048576, 134217728);
		$titles = array_column($data['findings'], 'title');

		$this->assertFalse($data['dbBoundRequest']);
		$this->assertNotContains('DB-bound request', $titles);

		UiTheme::setCurrent('bootstrap');
		$html = $renderer->render($data, 'sample/index', 'test-correlation-id', true, true);

		$this->assertStringContainsString('class="logbar-metric database" data-logbar-query-toggle="1"', $html);
		$this->assertStringNotContainsString('class="logbar-metric warning database" data-logbar-query-toggle="1"', $html);
		$this->assertStringNotContainsString('No automatic findings', $html);
		$this->assertStringNotContainsString('Request timings are within configured LogBar thresholds.', $html);

	}

	/**
	 * Verify the request inspector renders separated metrics and namespaced panes.
	 */
	public function testInspectorMarkupUsesSeparatedMetricsAndNamespacedBody(): void {

		$logBar = LogBar::getInstance();
		$events = [
			$this->queryEvent('SELECT * FROM sessions WHERE user_id = ?', 0.060, 1, 0.000),
			$this->queryEvent('SELECT * FROM sessions WHERE user_id = ?', 0.026, 1, 0.060),
			$this->noticeEvent(0.032, 0.086),
		];

		$this->setInaccessibleProperty($logBar, 'events', $events);
		$data = $this->invokeInaccessibleMethod($logBar, 'collectInspectorData');

		// The renderer reads the Router singleton for a safe route label.
		if (!defined('Pair\Core\URL_PATH')) {
			define('Pair\Core\URL_PATH', '');
		}

		UiTheme::setCurrent('bootstrap');
		$_ENV['APP_ENV'] = 'staging';

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);
		$html = $renderer->render($data, 'request inspector', 'test-correlation-id', true, true);

		$this->assertStringContainsString('class="card mt-5 logbar logbar-shell logbar-shell-bootstrap"', $html);
		$this->assertStringContainsString('data-logbar-ui="bootstrap"', $html);
		$this->assertStringContainsString('class="card-header logbar-header"', $html);
		$this->assertStringContainsString('class="logbar-titlebar"', $html);
		$this->assertStringNotContainsString('request inspector', $html);
		$this->assertStringContainsString('class="logbar-context" aria-label="LogBar runtime context"', $html);
		$this->assertStringContainsString('<span class="logbar-context-label">Env</span><strong class="logbar-context-value">staging</strong>', $html);
		$this->assertStringContainsString('<span class="logbar-context-label">UI</span><strong class="logbar-context-value">Bootstrap</strong>', $html);
		$this->assertStringContainsString('data-logbar-breakpoint="1" aria-live="polite"', $html);
		$this->assertStringContainsString('<span class="logbar-context-label">Breakpoint</span><strong class="logbar-context-value">-</strong>', $html);
		$this->assertStringContainsString('data-logbar-copy-value="test-correlation-id"', $html);
		$this->assertStringContainsString('<span class="logbar-metric-label">Queries</span>', $html);
		$this->assertStringContainsString('<strong class="logbar-metric-value">2</strong>', $html);
		$this->assertStringContainsString('<span class="logbar-metric-subtext">budget 30</span>', $html);
		$this->assertStringNotContainsString('<span class="logbar-metric-label">Environment</span>', $html);
		$this->assertStringNotContainsString('<span class="logbar-metric-label">Request ID</span>', $html);
		$this->assertStringContainsString('class="logbar-diagnostic-banner logbar-severity-warning"', $html);
		$this->assertStringContainsString('<span class="logbar-severity-badge logbar-severity-warning">Warning</span>', $html);
		$this->assertStringContainsString('Open Overview to inspect 1 more finding.', $html);
		$this->assertStringContainsString('data-logbar-finding-action="1"', $html);
		$this->assertStringContainsString('data-logbar-finding-tab="queries"', $html);
		$this->assertStringContainsString('<span class="logbar-finding-action">Open queries</span>', $html);
		$this->assertStringNotContainsString('Query events', $html);
		$this->assertStringContainsString('Warnings/errors', $html);
		$this->assertStringContainsString('Duplicates', $html);
		$this->assertStringContainsString('class="logbar-query-issues"', $html);
		$this->assertStringContainsString('<span class="logbar-severity-badge logbar-severity-warning">Slowest</span>', $html);
		$this->assertStringContainsString('data-logbar-finding-open-query="1"', $html);
		$this->assertStringNotContainsString('class="logbar-overview-grid"', $html);
		$this->assertStringContainsString('class="card-body logbar-body logbar-show-queries"', $html);
		$this->assertStringNotContainsString('data-logbar-type-filter', $html);

	}

	/**
	 * Verify query groups expose shortcut cards and row badges for duplicate budgets.
	 */
	public function testQueryPaneHighlightsDuplicateBudgetOffenders(): void {

		$events = [];
		$start = 0.0;

		for ($i = 0; $i < 5; $i++) {
			$events[] = $this->queryEvent('SELECT * FROM categories WHERE target_category_id = ?', 0.001, 1, $start);
			$start += 0.001;
		}

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);
		$data = $inspector->collect($events, 0.010, 1048576, 134217728);

		UiTheme::setCurrent('bootstrap');
		$html = $renderer->render($data, 'sample/index', 'test-correlation-id', true, true);
		$fingerprint = LogBarSql::fingerprint('SELECT * FROM categories WHERE target_category_id = ?');

		$this->assertStringContainsString('<span class="logbar-severity-badge logbar-severity-warning">Duplicate x5</span>', $html);
		$this->assertStringContainsString('SELECT categories repeated 5 times', $html);
		$this->assertStringContainsString('data-logbar-finding-duplicates-only="1"', $html);
		$this->assertStringContainsString('data-logbar-duplicate="1"', $html);
		$this->assertStringContainsString($fingerprint, $html);
		$this->assertStringNotContainsString('start 0 ms', $html);
		$this->assertStringContainsString('logbar-query-duplicate-over-budget', $html);
		$this->assertStringNotContainsString('<span>Avg</span>', $html);
		$this->assertStringNotContainsString('<span>Max</span>', $html);
		$this->assertStringNotContainsString('<small>Avg</small>', $html);
		$this->assertStringNotContainsString('<small>Max</small>', $html);
		$this->assertStringContainsString('<span class="logbar-query-badge logbar-query-badge-duplicate">Duplicate x5</span>', $html);
		$this->assertStringContainsString('class="btn btn-sm btn-warning"', $html);
		$this->assertStringNotContainsString('class="logbar-query-detail-toggle"', $html);
		$this->assertStringContainsString('data-logbar-query-detail-toggle="1"', $html);
		$this->assertStringContainsString('data-logbar-query-detail-active-class="active"', $html);
		$this->assertStringContainsString('title="Show full query">...</button>', $html);
		$this->assertStringNotContainsString('hidden>...</button>', $html);
		$this->assertStringContainsString('data-logbar-query-detail="1"', $html);
		$this->assertStringNotContainsString('class="logbar-query-copy"', $html);

	}

	/**
	 * Verify query detail toggles use the active UI framework button classes.
	 */
	public function testQueryDetailToggleUsesFrameworkButtonClasses(): void {

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);
		$data = $inspector->collect([$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.005, 1)], 0.010, 1048576, 134217728);

		UiTheme::setCurrent('bootstrap');
		$bootstrapHtml = $renderer->render($data, 'sample/index', 'test-correlation-id', true, true);
		$this->assertStringContainsString('class="btn btn-sm btn-outline-secondary"', $bootstrapHtml);
		$this->assertStringContainsString('data-logbar-query-detail-active-class="active"', $bootstrapHtml);

		UiTheme::setCurrent('bulma');
		$bulmaHtml = $renderer->render($data, 'sample/index', 'test-correlation-id', true, true);
		$this->assertStringContainsString('class="button is-small is-light"', $bulmaHtml);
		$this->assertStringContainsString('data-logbar-query-detail-active-class="is-active"', $bulmaHtml);

		UiTheme::setCurrent('native');
		$nativeHtml = $renderer->render($data, 'sample/index', 'test-correlation-id', true, true);
		$this->assertStringContainsString('class="logbar-query-detail-toggle"', $nativeHtml);
		$this->assertStringContainsString('data-logbar-query-detail-active-class="active"', $nativeHtml);

	}

	/**
	 * Verify the type dropdown is omitted from the simplified filter bar.
	 */
	public function testTypeFilterIsAlwaysOmitted(): void {

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);

		UiTheme::setCurrent('bootstrap');
		$basicHtml = $renderer->render(
			$inspector->collect([
				$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.010, 1),
				$this->noticeEvent(0.005, 0.010),
			], 0.015, 1048576, 134217728),
			'sample/index',
			'test-correlation-id',
			true,
			true
		);
		$warningHtml = $renderer->render(
			$inspector->collect([
				$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.010, 1),
				$this->warningEvent(0.005, 0.010),
			], 0.015, 1048576, 134217728),
			'sample/index',
			'test-correlation-id',
			true,
			true
		);

		$this->assertStringNotContainsString('data-logbar-type-filter', $basicHtml);
		$this->assertStringNotContainsString('data-logbar-type-filter', $warningHtml);

	}

	/**
	 * Verify empty event panes are omitted when query rows are hidden.
	 */
	public function testInspectorOmitsBlankEventPanesWhenOnlyHiddenQueriesExist(): void {

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);
		$data = $inspector->collect([$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.010, 1)], 0.010, 1048576, 134217728);

		UiTheme::setCurrent('bootstrap');
		$html = $renderer->render($data, 'sample/index', 'test-correlation-id', false, true);

		$this->assertStringContainsString('data-logbar-tab-button="overview"', $html);
		$this->assertStringContainsString('data-logbar-tab-button="queries"', $html);
		$this->assertStringNotContainsString('data-logbar-tab-button="timeline"', $html);
		$this->assertStringNotContainsString('data-logbar-tab-button="events"', $html);
		$this->assertStringNotContainsString('data-logbar-tab="timeline"', $html);
		$this->assertStringNotContainsString('data-logbar-tab="events"', $html);

	}

	/**
	 * Verify event panes only render non-query rows because queries have a dedicated pane.
	 */
	public function testInspectorOnlyKeepsEventPanesForNonQueryRows(): void {

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);

		UiTheme::setCurrent('bootstrap');

		$queryHtml = $renderer->render(
			$inspector->collect([$this->queryEvent('SELECT * FROM users WHERE id = ?', 0.010, 1)], 0.010, 1048576, 134217728),
			'sample/index',
			'test-correlation-id',
			true,
			true
		);
		$noticeHtml = $renderer->render(
			$inspector->collect([$this->noticeEvent(0.010)], 0.010, 1048576, 134217728),
			'sample/index',
			'test-correlation-id',
			false,
			true
		);

		$this->assertStringNotContainsString('data-logbar-tab-button="timeline"', $queryHtml);
		$this->assertStringNotContainsString('data-logbar-tab-button="events"', $queryHtml);
		$this->assertStringContainsString('data-logbar-tab-button="timeline"', $noticeHtml);
		$this->assertStringContainsString('data-logbar-tab-button="events"', $noticeHtml);

	}

	/**
	 * Verify non-Bootstrap UI frameworks get matching outer LogBar chrome.
	 */
	public function testRendererUsesActiveUiFrameworkChrome(): void {

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);
		$data = $inspector->collect([$this->noticeEvent(0.010)], 0.010, 1048576, 134217728);

		UiTheme::setCurrent('bulma');
		$bulmaHtml = $renderer->render($data, 'sample/index', 'test-correlation-id', false, false);
		$this->assertStringContainsString('class="card logbar logbar-shell logbar-shell-bulma"', $bulmaHtml);
		$this->assertStringContainsString('class="card-header logbar-header"', $bulmaHtml);
		$this->assertStringContainsString('class="card-content logbar-body hidden"', $bulmaHtml);
		$this->assertStringContainsString('data-logbar-ui="bulma"', $bulmaHtml);
		$this->assertStringContainsString('data-logbar-breakpoint="1" aria-live="polite"', $bulmaHtml);
		$this->assertStringContainsString('<span class="logbar-context-label">UI</span><strong class="logbar-context-value">Bulma</strong>', $bulmaHtml);

		UiTheme::setCurrent('native');
		$nativeHtml = $renderer->render($data, 'sample/index', 'test-correlation-id', false, false);
		$this->assertStringContainsString('class="logbar logbar-shell logbar-shell-native"', $nativeHtml);
		$this->assertStringContainsString('class="logbar-header"', $nativeHtml);
		$this->assertStringContainsString('class="logbar-body hidden"', $nativeHtml);
		$this->assertStringContainsString('data-logbar-ui="native"', $nativeHtml);
		$this->assertStringContainsString('<span class="logbar-context-label">UI</span><strong class="logbar-context-value">Native</strong>', $nativeHtml);
		$this->assertStringNotContainsString('data-logbar-breakpoint="1"', $nativeHtml);

	}

	/**
	 * Verify default asset paths follow applications mounted below the domain root when no public copy exists.
	 */
	public function testDefaultAssetPathFallsBackToApplicationUrlPath(): void {

		$logBar = LogBar::getInstance();

		$this->assertSame(
			['/sample-app/assets/pair.css', '/sample-app/assets/PairLogBar.js'],
			$this->invokeInaccessibleMethod($logBar, 'resolveAssetPaths', ['', '/sample-app', TEMP_PATH . 'missing-public'])
		);
		$this->assertSame(
			['/assets/pair.css', '/assets/PairLogBar.js'],
			$this->invokeInaccessibleMethod($logBar, 'resolveAssetPaths', ['', '', TEMP_PATH . 'missing-public'])
		);
		$this->assertSame(
			['/custom/assets/pair.css', '/custom/assets/PairLogBar.js'],
			$this->invokeInaccessibleMethod($logBar, 'resolveAssetPaths', ['/custom/assets'])
		);

	}

	/**
	 * Verify default asset paths prefer the Pair 3 public/css and public/js layout when available.
	 */
	public function testDefaultAssetPathPrefersLegacyPublicCssAndJs(): void {

		$logBar = LogBar::getInstance();
		$publicPath = TEMP_PATH . 'logbar-public';

		mkdir($publicPath . '/css', 0777, true);
		mkdir($publicPath . '/js', 0777, true);
		file_put_contents($publicPath . '/css/pair.css', '');
		file_put_contents($publicPath . '/js/PairLogBar.js', '');

		$this->assertSame(
			['css/pair.css', 'js/PairLogBar.js'],
			$this->invokeInaccessibleMethod($logBar, 'resolveAssetPaths', ['', '/sample-app', $publicPath])
		);

		$this->removeDirectory($publicPath);

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

	/**
	 * Verify production requests skip LogBar collection unless explicitly enabled.
	 */
	public function testRuntimeCollectionIsDisabledInProductionByDefault(): void {

		$method = new \ReflectionMethod(LogBar::class, 'runtimeEnabled');

		$_ENV['APP_ENV'] = 'production';
		unset($_ENV['PAIR_LOGBAR_ENABLED']);
		$this->assertFalse($method->invoke(null));

		$_ENV['PAIR_LOGBAR_ENABLED'] = true;
		$this->assertTrue($method->invoke(null));

		$_ENV['PAIR_LOGBAR_ENABLED'] = 1;
		$this->assertTrue($method->invoke(null));

		$_ENV['PAIR_LOGBAR_ENABLED'] = false;
		$this->assertFalse($method->invoke(null));

		$_ENV['PAIR_LOGBAR_ENABLED'] = 0;
		$this->assertFalse($method->invoke(null));

		$_ENV['APP_ENV'] = 'development';
		unset($_ENV['PAIR_LOGBAR_ENABLED']);
		$this->assertTrue($method->invoke(null));

	}

	/**
	 * Verify disabled runtimes skip singleton startup for static event entry points.
	 */
	public function testDisabledRuntimeSkipsLogBarSingletonStartup(): void {

		$property = new \ReflectionProperty(LogBar::class, 'instance');
		$property->setValue(null, null);

		$_ENV['APP_ENV'] = 'production';
		unset($_ENV['PAIR_LOGBAR_ENABLED']);

		LogBar::query('SELECT * FROM sessions WHERE id = ?', 1, ['sid']);
		LogBar::event('Runtime disabled notice');

		$this->assertNull($property->getValue());

	}

	/**
	 * Verify request visibility resolution can run without starting the singleton.
	 */
	public function testRequestVisibilityResolutionSkipsLogBarSingletonStartup(): void {

		$property = new \ReflectionProperty(LogBar::class, 'instance');
		$property->setValue(null, null);

		$_ENV['APP_ENV'] = 'production';
		unset($_ENV['PAIR_LOGBAR_ENABLED']);

		LogBar::resolveRequestVisibility();

		$this->assertNull($property->getValue());
		$this->assertFalse(LogBar::isRuntimeAvailable());

	}

	/**
	 * Verify canBeShown reuses the request visibility decision without resolving it again.
	 */
	public function testCanBeShownUsesCachedRequestVisibility(): void {

		$property = new \ReflectionProperty(LogBar::class, 'requestCanBeShown');
		$logBar = LogBar::getInstance();

		$property->setValue(null, true);
		$this->assertTrue($logBar->canBeShown());

		$property->setValue(null, false);
		$this->assertFalse($logBar->canBeShown());

	}

}
