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
	private function queryEvent(string $sql, float $duration, int $rows, float $start = 0.0): LogBarEntry {

		return new LogBarEntry(
			'test-query-' . substr(hash('sha1', $sql . $duration . $start), 0, 8),
			'query',
			LogBarSql::normalize($sql),
			$rows . ' ' . (1 === $rows ? 'row' : 'rows'),
			$start,
			$duration,
			'ok',
			[
				'fingerprint' => LogBarSql::fingerprint($sql),
				'normalizedSql' => LogBarSql::normalize($sql),
				'operation' => LogBarSql::operation($sql),
				'rows' => $rows,
				'table' => LogBarSql::table($sql),
			]
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

		$inspector = new LogBarInspector(250, 20, 30, 3, 80.0);
		$renderer = new LogBarRenderer($inspector);
		$html = $renderer->render($data, 'request inspector', 'test-correlation-id', true, true);

		$this->assertStringContainsString('class="card mt-5 logbar logbar-shell logbar-shell-bootstrap"', $html);
		$this->assertStringContainsString('data-logbar-ui="bootstrap"', $html);
		$this->assertStringContainsString('class="card-header logbar-header"', $html);
		$this->assertStringContainsString('class="logbar-titlebar"', $html);
		$this->assertStringContainsString('<span class="logbar-metric-label">Queries</span>', $html);
		$this->assertStringContainsString('<strong class="logbar-metric-value">2</strong>', $html);
		$this->assertStringContainsString('<span class="logbar-metric-subtext">budget 30</span>', $html);
		$this->assertStringContainsString('class="logbar-diagnostic-banner logbar-severity-warning"', $html);
		$this->assertStringContainsString('class="card-body logbar-body logbar-show-queries"', $html);

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

		UiTheme::setCurrent('native');
		$nativeHtml = $renderer->render($data, 'sample/index', 'test-correlation-id', false, false);
		$this->assertStringContainsString('class="logbar logbar-shell logbar-shell-native"', $nativeHtml);
		$this->assertStringContainsString('class="logbar-header"', $nativeHtml);
		$this->assertStringContainsString('class="logbar-body hidden"', $nativeHtml);
		$this->assertStringContainsString('data-logbar-ui="native"', $nativeHtml);

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

}
