<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Html\UiTheme;

/**
 * Renders Pair v4 LogBar inspector HTML from already prepared data.
 */
final readonly class LogBarRenderer {

	/**
	 * Build the renderer with the inspector used for slow-row classification.
	 */
	public function __construct(
		private LogBarInspector $inspector,
	) {}

	/**
	 * Render the full LogBar inspector.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	public function render(array $data, string $route, string $correlationId, bool $showQueries, bool $showEvents): string {

		$bodyClasses = 'logbar-body' . ($showQueries ? ' logbar-show-queries' : '') . ($showEvents ? '' : ' hidden');
		$memory = $this->memoryLabel((int)$data['memoryPeakBytes'], (int)$data['memoryLimitBytes']);
		$chrome = $this->chromeClasses();

		$ret = '<div class="' . $this->escapeAttribute($chrome['root']) . '" id="logbar" data-logbar-root data-logbar-ui="' . $this->escapeAttribute(UiTheme::current()) . '">';
		$ret .= '<div class="' . $this->escapeAttribute($chrome['header']) . '">';
		$ret .= '<div class="logbar-titlebar">';
		$ret .= '<div class="logbar-heading"><h4>Pair LogBar</h4><div class="logbar-route">' . $this->escape($route ?: 'request inspector') . '</div>' . $this->buildRuntimeContextHtml($correlationId) . '</div>';
		$ret .= '<button type="button" id="toggle-events" class="logbar-toggle' . ($showEvents ? ' expanded' : '') . '" aria-expanded="' . ($showEvents ? 'true' : 'false') . '">' . ($showEvents ? 'Hide details' : 'Show details') . '</button>';
		$ret .= '</div>';
		$ret .= '<div class="logbar-metrics">';
		$ret .= $this->buildMetricHtml('Total', $this->formatChrono((float)$data['totalSeconds']), ((float)$data['totalMs'] >= (int)$data['slowRequestMs'] ? 'slow request' : 'request time'), ((float)$data['totalMs'] >= (int)$data['slowRequestMs'] ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('DB', $this->formatChrono((float)$data['querySeconds']), round((float)$data['queryPercent']) . '% of request', ((float)$data['queryPercent'] > 50.0 ? 'warning database' : 'database'), ' data-logbar-query-toggle="1"');
		$ret .= $this->buildMetricHtml('Queries', (string)$data['queryCount'], 'budget ' . (int)$data['queryBudget'], ((int)$data['queryCount'] > (int)$data['queryBudget'] ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('Memory', $memory['value'], $memory['subtext'], ((float)$data['memoryLimitPercent'] >= (float)$data['memoryNearLimitRatio'] ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('Warnings', (string)$data['warningCount'], ((int)$data['warningCount'] ? 'needs review' : 'none'), ((int)$data['warningCount'] ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('Errors', (string)$data['errorCount'], ((int)$data['errorCount'] ? 'needs review' : 'none'), ((int)$data['errorCount'] ? 'error' : ''));
		$ret .= '</div></div>';

		$ret .= '<div class="' . $this->escapeAttribute(trim($chrome['body'] . ' ' . $bodyClasses)) . '">';
		$ret .= $this->buildDiagnosticBannerHtml($data);
		$ret .= $this->buildTabsHtml();
		$ret .= $this->buildFiltersHtml($data['types']);
		$ret .= '<div class="logbar-panes">';
		$ret .= '<section data-logbar-tab="overview">' . $this->buildOverviewHtml($data) . '</section>';
		$ret .= '<section data-logbar-tab="timeline" hidden>' . $this->buildTimelineHtml($data) . '</section>';
		$ret .= '<section data-logbar-tab="queries" hidden>' . $this->buildQueriesHtml($data) . '</section>';
		$ret .= '<section data-logbar-tab="events" hidden>' . $this->buildEventsHtml($data) . '</section>';
		$ret .= '</div></div></div>';

		return $ret;

	}

	/**
	 * Return outer container classes that match the active UI framework.
	 *
	 * @return	array{root: string, header: string, body: string}
	 */
	private function chromeClasses(): array {

		if (UiTheme::isBootstrap()) {
			return [
				'root' => 'card mt-5 logbar logbar-shell logbar-shell-bootstrap',
				'header' => 'card-header logbar-header',
				'body' => 'card-body',
			];
		}

		if (UiTheme::isBulma()) {
			return [
				'root' => 'card logbar logbar-shell logbar-shell-bulma',
				'header' => 'card-header logbar-header',
				'body' => 'card-content',
			];
		}

		return [
			'root' => 'logbar logbar-shell logbar-shell-native',
			'header' => 'logbar-header',
			'body' => '',
		];

	}

	/**
	 * Render the compact AJAX event payload.
	 *
	 * @param	LogBarEntry[]	$entries	Entries to render for AJAX consumers.
	 */
	public function renderForAjax(array $entries, bool $showQueries): string {

		$log = '';

		foreach ($entries as $entry) {
			$class = 'logbar-type-' . $entry->type . ' ' . $entry->type . (($entry->isQuery() and !$showQueries) ? ' hidden' : '') . ($this->inspector->isSlowEntry($entry) ? ' logbar-slow' : '');
			$log .= $this->buildEventHtml($entry, '', false, $class);
		}

		return $log;

	}

	/**
	 * Build the runtime metadata shown near the title instead of in the metric grid.
	 */
	private function buildRuntimeContextHtml(string $correlationId): string {

		$ret = '<div class="logbar-context" aria-label="LogBar runtime context">';
		$ret .= $this->buildRuntimeContextItemHtml('Env', $this->appEnvironment(), 'environment');
		$ret .= $this->buildRuntimeContextItemHtml('UI', $this->uiFrameworkLabel(), 'ui');
		$ret .= $this->buildBreakpointContextHtml();
		$ret .= $this->buildRequestContextHtml($correlationId);
		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Build one compact runtime context item.
	 */
	private function buildRuntimeContextItemHtml(string $label, string $value, string $class = '', string $attributes = ''): string {

		$classAttribute = trim('logbar-context-item ' . $class);

		return
			'<span class="' . $this->escapeAttribute($classAttribute) . '"' . $attributes . '>' .
				'<span class="logbar-context-label">' . $this->escape($label) . '</span>' .
				'<strong class="logbar-context-value">' . $this->escape($value) . '</strong>' .
			'</span>';

	}

	/**
	 * Return the normalized application environment shown in the LogBar header.
	 */
	private function appEnvironment(): string {

		return Application::getEnvironment();

	}

	/**
	 * Build the viewport breakpoint context item for UI frameworks that expose named breakpoints.
	 */
	private function buildBreakpointContextHtml(): string {

		if (!UiTheme::isBootstrap() and !UiTheme::isBulma()) {
			return '';
		}

		return $this->buildRuntimeContextItemHtml('Breakpoint', '-', 'breakpoint', ' data-logbar-breakpoint="1" aria-live="polite"');

	}

	/**
	 * Build the request context item with a copy affordance for the correlation ID.
	 */
	private function buildRequestContextHtml(string $correlationId): string {

		$value = substr($correlationId, 0, 12);

		return
			'<span class="logbar-context-item request">' .
				'<span class="logbar-context-label">Request</span>' .
				'<button type="button" class="logbar-context-copy" data-logbar-copy-value="' . $this->escapeAttribute($correlationId) . '" title="Copy request ID" aria-label="Copy request ID ' . $this->escapeAttribute($value) . '">' .
					'<strong class="logbar-context-value">' . $this->escape($value) . '</strong>' .
				'</button>' .
			'</span>';

	}

	/**
	 * Return a readable label for the active UI framework.
	 */
	private function uiFrameworkLabel(): string {

		return ucfirst(UiTheme::current());

	}

	/**
	 * Build the prominent summary banner for automatic findings.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildDiagnosticBannerHtml(array $data): string {

		if (!count($data['findings'])) {
			return '<div class="logbar-diagnostic-banner logbar-diagnostic-ok"><strong>No automatic findings</strong><span>Request timings are within configured LogBar thresholds.</span></div>';
		}

		$primary = $data['findings'][0];
		$details = [];

		foreach (array_slice($data['findings'], 0, 3) as $finding) {
			$details[] = (string)($finding['detail'] ?? '');
		}

		return
			'<div class="logbar-diagnostic-banner logbar-severity-' . $this->escapeAttribute($primary['type'] ?? 'warning') . '">' .
				'<strong>' . $this->escape((string)($primary['title'] ?? 'Finding')) . '</strong>' .
				'<span>' . $this->escape(implode(' ', array_filter($details))) . '</span>' .
			'</div>';

	}

	/**
	 * Build one compact event row for chronological and AJAX output.
	 */
	private function buildEventHtml(LogBarEntry $entry, string $eventDomId = '', bool $withData = true, string $classOverride = ''): string {

		$data = $withData
			? ' data-logbar-row="1" data-logbar-type="' . $this->escapeAttribute($entry->type) . '" data-logbar-text="' . $this->escapeAttribute($entry->searchText()) . '"'
			: '';
		$domId = strlen($eventDomId) ? ' id="' . $this->escapeAttribute($eventDomId) . '"' : '';
		$class = $classOverride ?: 'logbar-type-' . $entry->type . ($this->inspector->isSlowEntry($entry) ? ' logbar-slow' : '');

		return
			'<div' . $domId . $data . ' class="logbar-event ' . $this->escapeAttribute($class) . '">' .
				'<span class="logbar-duration time">' . $this->escape($this->formatChrono($entry->duration)) . '</span>' .
				'<span class="logbar-event-type">' . $this->escape($entry->type) . '</span>' .
				'<span class="logbar-event-message">' . $this->escape($entry->description) . '</span>' .
				($entry->subtext ? ' <span class="logbar-event-subtext">| ' . $this->escape($entry->subtext) . '</span>' : '') .
			'</div>';

	}

	/**
	 * Build the chronological event list.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildEventsHtml(array $data): string {

		if (!count($data['events'])) {
			return '<div class="logbar-empty">No events recorded.</div>';
		}

		$ret = '<div class="logbar-section-title">Events</div>';
		$firstWarning = false;
		$firstError = false;

		foreach ($data['events'] as $entry) {

			$domId = '';

			if ('warning' === $entry->type and !$firstWarning) {
				$domId = 'logFirstWarning';
				$firstWarning = true;
			} else if ('error' === $entry->type and !$firstError) {
				$domId = 'logFirstError';
				$firstError = true;
			}

			$ret .= $this->buildEventHtml($entry, $domId);

		}

		return $ret;

	}

	/**
	 * Build filter controls for the LogBar body.
	 *
	 * @param	string[]	$types	Observed event types.
	 */
	private function buildFiltersHtml(array $types): string {

		sort($types);

		$ret = '<div class="logbar-filters" aria-label="LogBar filters">';
		$ret .= '<label class="logbar-search"><span>Search</span><input type="search" data-logbar-search placeholder="SQL, event, type"></label>';
		$ret .= '<label><span>Type</span><select data-logbar-type-filter><option value="">All types</option>';

		foreach ($types as $type) {
			$ret .= '<option value="' . $this->escapeAttribute($type) . '">' . $this->escape($type) . '</option>';
		}

		$ret .= '</select></label>';
		$ret .= '<label class="logbar-filter-toggle"><input type="checkbox" data-logbar-queries-only><span>Queries only</span></label>';
		$ret .= '<label class="logbar-filter-toggle"><input type="checkbox" data-logbar-warnings-only><span>Warnings/errors</span></label>';
		$ret .= '<label class="logbar-filter-toggle"><input type="checkbox" data-logbar-duplicates-only><span>Duplicates</span></label>';
		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Build one automatic diagnostic finding.
	 *
	 * @param	array<string, string>	$finding	Finding title, detail, and type.
	 */
	private function buildFindingHtml(array $finding): string {

		return
			'<li class="logbar-finding-card logbar-severity-' . $this->escapeAttribute($finding['type'] ?? 'notice') . '">' .
				'<strong>' . $this->escape($finding['title'] ?? 'Finding') . '</strong>' .
				'<span>' . $this->escape($finding['detail'] ?? '') . '</span>' .
			'</li>';

	}

	/**
	 * Build one compact metric for the sticky header.
	 */
	private function buildMetricHtml(string $label, string $value, string $subtext = '', string $class = '', string $attributes = ''): string {

		return
			'<div class="logbar-metric ' . $this->escapeAttribute(trim($class)) . '"' . $attributes . '>' .
				'<span class="logbar-metric-label">' . $this->escape($label) . '</span>' .
				'<strong class="logbar-metric-value">' . $this->escape($value) . '</strong>' .
				(strlen($subtext) ? '<span class="logbar-metric-subtext">' . $this->escape($subtext) . '</span>' : '') .
			'</div>';

	}

	/**
	 * Build the overview pane with diagnostics and top-level request metrics.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildOverviewHtml(array $data): string {

		$ret = '<div class="logbar-overview">';
		$ret .= '<div class="logbar-overview-grid">';
		$ret .= $this->buildOverviewStat('Request', $this->formatChrono((float)$data['totalSeconds']), 'total', ((float)$data['totalMs'] >= (int)$data['slowRequestMs'] ? 'warning' : ''));
		$ret .= $this->buildOverviewStat('Database', $this->formatChrono((float)$data['querySeconds']), round((float)$data['queryPercent']) . '% of request', ((float)$data['queryPercent'] > 50.0 ? 'warning' : ''));
		$ret .= $this->buildOverviewStat('Queries', (string)$data['queryCount'], 'budget ' . (int)$data['queryBudget'], ((int)$data['queryCount'] > (int)$data['queryBudget'] ? 'warning' : ''));
		$ret .= $this->buildOverviewStat('Warnings', (string)$data['warningCount'], ((int)$data['warningCount'] ? 'needs review' : 'none'), ((int)$data['warningCount'] ? 'warning' : ''));
		$ret .= $this->buildOverviewStat('Errors', (string)$data['errorCount'], ((int)$data['errorCount'] ? 'needs review' : 'none'), ((int)$data['errorCount'] ? 'error' : ''));
		$ret .= $this->buildOverviewStat('Peak memory', $this->formatBytes((int)$data['memoryPeakBytes']), $this->memoryLimitSubtext((int)$data['memoryLimitBytes'], (float)$data['memoryLimitPercent']), ((float)$data['memoryLimitPercent'] >= (float)$data['memoryNearLimitRatio'] ? 'warning' : ''));
		$ret .= '</div>';
		$ret .= '<div class="logbar-section-title">Findings</div>';

		if (count($data['findings'])) {
			$ret .= '<ul class="logbar-findings">';
			foreach ($data['findings'] as $finding) {
				$ret .= $this->buildFindingHtml($finding);
			}
			$ret .= '</ul>';
		} else {
			$ret .= '<div class="logbar-empty">No automatic findings.</div>';
		}

		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Build one overview metric.
	 */
	private function buildOverviewStat(string $label, string $value, string $subtext, string $severity = ''): string {

		return
			'<div class="logbar-stat ' . $this->escapeAttribute($severity ? 'logbar-severity-' . $severity : '') . '">' .
				'<span class="logbar-stat-label">' . $this->escape($label) . '</span>' .
				'<strong>' . $this->escape($value) . '</strong>' .
				(strlen($subtext) ? '<small>' . $this->escape($subtext) . '</small>' : '') .
			'</div>';

	}

	/**
	 * Build the aggregated query pane.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildQueriesHtml(array $data): string {

		$groups = $data['queryGroups'];

		if (!count($groups)) {
			return '<div class="logbar-empty">No query events recorded.</div>';
		}

		$ret = '<div class="logbar-section-title">Queries</div>';
		$ret .= '<div class="logbar-query-table">';
		$ret .= '<div class="logbar-query-head"><span>Count</span><span>Total</span><span>Avg</span><span>Max</span><span>Rows</span><span>Op</span><span>Table</span><span>SQL</span></div>';

		foreach ($groups as $group) {
			$isDuplicate = ((int)$group['count'] > 1);
			$isExpensive = ((float)$group['totalMs'] >= (int)$data['slowQueryMs'] or (float)$group['maxMs'] >= (int)$data['slowQueryMs']);
			$ret .= '<details class="logbar-query-group' . ($isDuplicate ? ' logbar-query-duplicate' : '') . ($isExpensive ? ' logbar-query-expensive' : '') . '" data-logbar-query-group="1" data-logbar-duplicate="' . ($isDuplicate ? '1' : '0') . '" data-logbar-text="' . $this->escapeAttribute($group['searchText']) . '">';
			$ret .= '<summary class="logbar-query-summary">';
			$ret .= $this->buildQueryMetricHtml('Count', (string)$group['count']);
			$ret .= $this->buildQueryMetricHtml('Total', $this->formatMilliseconds((float)$group['totalMs']));
			$ret .= $this->buildQueryMetricHtml('Avg', $this->formatMilliseconds((float)$group['avgMs']));
			$ret .= $this->buildQueryMetricHtml('Max', $this->formatMilliseconds((float)$group['maxMs']));
			$ret .= $this->buildQueryMetricHtml('Rows', (string)$group['rows']);
			$ret .= $this->buildQueryMetricHtml('Op', (string)$group['operation']);
			$ret .= $this->buildQueryMetricHtml('Table', (string)$group['table']);
			$ret .= '<code class="logbar-sql-preview">' . $this->escape((string)$group['sql']) . '</code>';
			$ret .= '</summary>';
			$ret .= '<div class="logbar-query-occurrences">';
			$ret .= '<pre class="logbar-query-fullsql"><code>' . $this->escape((string)$group['sql']) . '</code></pre>';

			foreach ($group['occurrences'] as $occurrence) {
				$ret .= '<div data-logbar-row="1" data-logbar-type="query" data-logbar-text="' . $this->escapeAttribute($occurrence['searchText']) . '" class="logbar-query-occurrence logbar-type-query' . ((float)$occurrence['durationMs'] >= (int)$data['slowQueryMs'] ? ' logbar-slow' : '') . '">';
				$ret .= '<span>' . $this->escape($this->formatMilliseconds((float)$occurrence['durationMs'])) . '</span>';
				$ret .= '<span>' . $this->escape((string)$occurrence['rows']) . ' rows</span>';
				$ret .= '<code>' . $this->escape((string)$occurrence['sql']) . '</code>';
				$ret .= '</div>';
			}

			$ret .= '</div></details>';
		}

		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Build one labeled metric inside an aggregated query summary.
	 */
	private function buildQueryMetricHtml(string $label, string $value): string {

		return '<span class="logbar-query-cell"><small>' . $this->escape($label) . '</small><strong>' . $this->escape($value ?: '-') . '</strong></span>';

	}

	/**
	 * Build the tab controls.
	 */
	private function buildTabsHtml(): string {

		return
			'<div class="logbar-tabs" role="tablist">' .
				'<button type="button" role="tab" aria-selected="true" data-logbar-tab-button="overview" class="active">Overview</button>' .
				'<button type="button" role="tab" aria-selected="false" data-logbar-tab-button="timeline">Timeline</button>' .
				'<button type="button" role="tab" aria-selected="false" data-logbar-tab-button="queries">Queries</button>' .
				'<button type="button" role="tab" aria-selected="false" data-logbar-tab-button="events">Events</button>' .
			'</div>';

	}

	/**
	 * Build a CSS-only timeline pane.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildTimelineHtml(array $data): string {

		if (!count($data['events'])) {
			return '<div class="logbar-empty">No events recorded.</div>';
		}

		$totalSeconds = max(0.001, (float)$data['totalSeconds']);
		$ret = '<div class="logbar-section-title">Timeline</div>';
		$ret .= '<div class="logbar-timeline">';

		foreach ($data['events'] as $entry) {
			$left = min(100.0, max(0.0, ($entry->start / $totalSeconds) * 100));
			$width = min(100.0 - $left, max(0.4, ($entry->duration / $totalSeconds) * 100));
			$ret .= '<div class="logbar-timeline-row logbar-type-' . $this->escapeAttribute($entry->type) . ($this->inspector->isSlowEntry($entry) ? ' logbar-slow' : '') . '" data-logbar-row="1" data-logbar-type="' . $this->escapeAttribute($entry->type) . '" data-logbar-text="' . $this->escapeAttribute($entry->searchText()) . '">';
			$ret .= '<div class="logbar-timeline-label"><strong>' . $this->escape($entry->type) . '</strong><span>' . $this->escape($this->formatChrono($entry->duration)) . '</span></div>';
			$ret .= '<div class="logbar-timeline-track"><span style="left:' . $this->formatPercent($left) . '%;width:' . $this->formatPercent($width) . '%"></span></div>';
			$ret .= '<div class="logbar-timeline-text">' . $this->escape($entry->description) . '</div>';
			$ret .= '</div>';
		}

		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Escape HTML text using Pair's LogBar output convention.
	 */
	private function escape(string $value): string {

		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

	}

	/**
	 * Escape a value for an HTML attribute.
	 */
	private function escapeAttribute(string $value): string {

		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

	}

	/**
	 * Choose seconds or milliseconds based on the amount of time shown.
	 */
	private function formatChrono(float $chrono): string {

		return ($chrono >= 1) ? round($chrono, 2) . ' s' : round($chrono * 1000) . ' ms';

	}

	/**
	 * Format bytes as a compact memory value.
	 */
	private function formatBytes(int $bytes): string {

		return floor($bytes / 1024 / 1024) . ' MB';

	}

	/**
	 * Format a millisecond value for diagnostics.
	 */
	private function formatMilliseconds(float $milliseconds): string {

		return ($milliseconds >= 1000)
			? round($milliseconds / 1000, 2) . ' s'
			: round($milliseconds, 1) . ' ms';

	}

	/**
	 * Format a CSS percentage value without locale-specific separators.
	 */
	private function formatPercent(float $percent): string {

		return number_format($percent, 3, '.', '');

	}

	/**
	 * Return the header memory value and subtext.
	 *
	 * @return	array{value: string, subtext: string}
	 */
	private function memoryLabel(int $peakBytes, int $limitBytes): array {

		return [
			'value' => $this->formatBytes($peakBytes),
			'subtext' => $this->memoryLimitSubtext($limitBytes, $limitBytes > 0 ? ($peakBytes / $limitBytes * 100) : 0.0),
		];

	}

	/**
	 * Return memory limit percentage text when a limit exists.
	 */
	private function memoryLimitSubtext(int $limitBytes, float $percent): string {

		if ($limitBytes <= 0) {
			return 'no limit';
		}

		return round($percent) . '% of ' . $this->formatBytes($limitBytes);

	}

}
