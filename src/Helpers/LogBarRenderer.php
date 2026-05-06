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
		$showEventPanes = $this->hasRenderableEventRows($data['events'], $showQueries);

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
		$ret .= $this->buildTabsHtml($showEventPanes);
		$ret .= $this->buildFiltersHtml($data['types']);
		$ret .= '<div class="logbar-panes">';
		$ret .= '<section data-logbar-tab="overview">' . $this->buildOverviewHtml($data) . '</section>';
		if ($showEventPanes) {
			$ret .= '<section data-logbar-tab="timeline" hidden>' . $this->buildTimelineHtml($data) . '</section>';
		}
		$ret .= '<section data-logbar-tab="queries" hidden>' . $this->buildQueriesHtml($data) . '</section>';
		if ($showEventPanes) {
			$ret .= '<section data-logbar-tab="events" hidden>' . $this->buildEventsHtml($data) . '</section>';
		}
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
			return
				'<div class="logbar-diagnostic-banner logbar-diagnostic-ok">' .
					'<div class="logbar-diagnostic-title">' .
						'<span class="logbar-severity-badge logbar-severity-ok">OK</span>' .
						'<strong>No automatic findings</strong>' .
					'</div>' .
					'<span>Request timings are within configured LogBar thresholds.</span>' .
				'</div>';
		}

		$primary = $data['findings'][0];
		$severity = (string)($primary['type'] ?? 'warning');
		$detail = (string)($primary['detail'] ?? '');
		$remaining = count($data['findings']) - 1;

		if (1 === $remaining) {
			$detail .= ' Open Overview to inspect 1 more finding.';
		} else if ($remaining > 1) {
			$detail .= ' Open Overview to inspect ' . $remaining . ' more findings.';
		}

		return
			'<div class="logbar-diagnostic-banner logbar-severity-' . $this->escapeAttribute($severity) . '">' .
				'<div class="logbar-diagnostic-title">' .
					'<span class="logbar-severity-badge logbar-severity-' . $this->escapeAttribute($severity) . '">' . $this->escape($this->severityLabel($severity)) . '</span>' .
					'<strong>' . $this->escape((string)($primary['title'] ?? 'Finding')) . '</strong>' .
				'</div>' .
				'<span>' . $this->escape($detail) . '</span>' .
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
		$ret .= '<label class="logbar-filter-toggle logbar-filter-query-events"><input type="checkbox" data-logbar-queries-only><span>Query events</span></label>';
		$ret .= '<label class="logbar-filter-toggle"><input type="checkbox" data-logbar-warnings-only><span>Warnings/errors</span></label>';
		$ret .= '<label class="logbar-filter-toggle"><input type="checkbox" data-logbar-duplicates-only><span>Duplicates</span></label>';
		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Build one automatic diagnostic finding.
	 *
	 * @param	array<string, string>	$finding	Finding title, detail, action target, and type.
	 */
	private function buildFindingHtml(array $finding): string {

		$severity = (string)($finding['type'] ?? 'notice');

		return
			'<li>' .
				'<button type="button" class="logbar-finding-card logbar-severity-' . $this->escapeAttribute($severity) . '"' . $this->buildActionAttributes($finding) . '>' .
					'<span class="logbar-finding-meta">' .
						'<span class="logbar-severity-badge logbar-severity-' . $this->escapeAttribute($severity) . '">' . $this->escape($this->severityLabel($severity)) . '</span>' .
						'<span class="logbar-finding-action">' . $this->escape((string)($finding['actionLabel'] ?? 'Inspect')) . '</span>' .
					'</span>' .
					'<strong>' . $this->escape((string)($finding['title'] ?? 'Finding')) . '</strong>' .
					'<span>' . $this->escape((string)($finding['detail'] ?? '')) . '</span>' .
				'</button>' .
			'</li>';

	}

	/**
	 * Build data attributes used by the client to focus a finding.
	 *
	 * @param	array<string, string>	$finding	Finding action options.
	 */
	private function buildActionAttributes(array $finding): string {

		$attributes = [
			'data-logbar-finding-action' => '1',
			'data-logbar-finding-tab' => (string)($finding['targetTab'] ?? 'overview'),
		];

		foreach ([
			'duplicatesOnly' => 'data-logbar-finding-duplicates-only',
			'openQuery' => 'data-logbar-finding-open-query',
			'queriesOnly' => 'data-logbar-finding-queries-only',
			'search' => 'data-logbar-finding-search',
			'typeFilter' => 'data-logbar-finding-type',
			'warningsOnly' => 'data-logbar-finding-warnings-only',
		] as $key => $attribute) {
			if (isset($finding[$key]) and '' !== (string)$finding[$key]) {
				$attributes[$attribute] = (string)$finding[$key];
			}
		}

		$ret = '';

		foreach ($attributes as $name => $value) {
			$ret .= ' ' . $name . '="' . $this->escapeAttribute($value) . '"';
		}

		return $ret;

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
		$ret .= '<div class="logbar-section-title">Findings</div>';

		if (count($data['findings'])) {
			$ret .= '<ul class="logbar-findings">';
			foreach ($data['findings'] as $finding) {
				$ret .= $this->buildFindingHtml($finding);
			}
			$ret .= '</ul>';
		} else {
			$ret .= '<div class="logbar-empty">No findings to inspect.</div>';
		}

		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Return the compact visible label for a diagnostic severity.
	 */
	private function severityLabel(string $severity): string {

		if ('error' === $severity) {
			return 'Error';
		}

		if ('ok' === $severity) {
			return 'OK';
		}

		if ('notice' === $severity) {
			return 'Info';
		}

		return 'Warning';

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
		$ret .= $this->buildQueryIssuesHtml($data);
		$ret .= '<div class="logbar-query-table">';
		$ret .= '<div class="logbar-query-head"><span>Count</span><span>Total</span><span>Avg</span><span>Max</span><span>Rows</span><span>Op</span><span>Table</span><span>SQL</span></div>';

		foreach ($groups as $group) {
			$isDuplicate = ((int)$group['count'] > 1);
			$isOverDuplicateBudget = ((int)$group['count'] > (int)($data['duplicateQueryBudget'] ?? 1));
			$isExpensive = ((float)$group['totalMs'] >= (int)$data['slowQueryMs'] or (float)$group['maxMs'] >= (int)$data['slowQueryMs']);
			$ret .= '<details class="logbar-query-group' . ($isDuplicate ? ' logbar-query-duplicate' : '') . ($isOverDuplicateBudget ? ' logbar-query-duplicate-over-budget' : '') . ($isExpensive ? ' logbar-query-expensive' : '') . '" data-logbar-query-group="1" data-logbar-duplicate="' . ($isDuplicate ? '1' : '0') . '" data-logbar-text="' . $this->escapeAttribute($group['searchText']) . '">';
			$ret .= '<summary class="logbar-query-summary">';
			$ret .= $this->buildQueryMetricHtml('Count', (string)$group['count']);
			$ret .= $this->buildQueryMetricHtml('Total', $this->formatMilliseconds((float)$group['totalMs']));
			$ret .= $this->buildQueryMetricHtml('Avg', $this->formatMilliseconds((float)$group['avgMs']));
			$ret .= $this->buildQueryMetricHtml('Max', $this->formatMilliseconds((float)$group['maxMs']));
			$ret .= $this->buildQueryMetricHtml('Rows', (string)$group['rows']);
			$ret .= $this->buildQueryMetricHtml('Op', (string)$group['operation']);
			$ret .= $this->buildQueryMetricHtml('Table', (string)$group['table']);
			$ret .= '<span class="logbar-query-sql">' . $this->buildQueryBadgesHtml($group, $data) . '<code class="logbar-sql-preview">' . $this->escape((string)$group['sql']) . '</code></span>';
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
	 * Build actionable shortcuts for the most relevant query groups.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildQueryIssuesHtml(array $data): string {

		$issues = $this->queryIssues($data);

		if (!count($issues)) {
			return '';
		}

		$ret = '<div class="logbar-query-issues" aria-label="Top query issues">';

		foreach ($issues as $issue) {
			$ret .=
				'<button type="button" class="logbar-query-issue logbar-severity-warning"' . $this->buildActionAttributes($issue) . '>' .
					'<span class="logbar-severity-badge logbar-severity-warning">' . $this->escape((string)$issue['badge']) . '</span>' .
					'<strong>' . $this->escape((string)$issue['title']) . '</strong>' .
					'<span>' . $this->escape((string)$issue['detail']) . '</span>' .
				'</button>';
		}

		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Return the top query groups that deserve a direct shortcut.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 * @return	array<int, array<string, string>>
	 */
	private function queryIssues(array $data): array {

		$issues = [];
		$usedFingerprints = [];
		$duplicateBudget = (int)($data['duplicateQueryBudget'] ?? 1);
		$duplicateGroups = array_values(array_filter($data['queryGroups'], function (array $group) use ($duplicateBudget): bool {
			return (int)$group['count'] > $duplicateBudget;
		}));

		usort($duplicateGroups, function (array $left, array $right): int {
			return (int)$right['count'] <=> (int)$left['count'];
		});

		foreach (array_slice($duplicateGroups, 0, 3) as $group) {
			$issues[] = $this->queryIssue($group, 'Duplicate x' . (int)$group['count'], $this->queryLabel($group) . ' repeated ' . (int)$group['count'] . ' times', $this->formatMilliseconds((float)$group['totalMs']) . ' total, ' . $this->countLabel((int)$group['rows'], 'row') . '.', true);
			$usedFingerprints[(string)$group['fingerprint']] = true;
		}

		$slowest = $this->slowestQueryGroup($data['queryGroups']);

		if ($slowest and (float)$slowest['maxMs'] >= (int)$data['slowQueryMs'] and !isset($usedFingerprints[(string)$slowest['fingerprint']])) {
			$issues[] = $this->queryIssue($slowest, 'Slowest', $this->queryLabel($slowest), $this->formatMilliseconds((float)$slowest['maxMs']) . ' max, ' . $this->formatMilliseconds((float)$slowest['totalMs']) . ' total.');
			$usedFingerprints[(string)$slowest['fingerprint']] = true;
		}

		foreach ($data['queryGroups'] as $group) {
			if (count($issues) >= 4) {
				break;
			}

			if ((float)$group['totalMs'] < (int)$data['slowQueryMs'] or isset($usedFingerprints[(string)$group['fingerprint']])) {
				continue;
			}

			$issues[] = $this->queryIssue($group, 'Heaviest', $this->queryLabel($group), $this->formatMilliseconds((float)$group['totalMs']) . ' total across ' . $this->countLabel((int)$group['count'], 'call') . '.');
			$usedFingerprints[(string)$group['fingerprint']] = true;
		}

		return $issues;

	}

	/**
	 * Build one query shortcut payload.
	 *
	 * @param	array<string, mixed>	$group	Aggregated query group.
	 * @return	array<string, string>
	 */
	private function queryIssue(array $group, string $badge, string $title, string $detail, bool $duplicatesOnly = false): array {

		$ret = [
			'badge' => $badge,
			'detail' => $detail,
			'openQuery' => '1',
			'search' => (string)$group['fingerprint'],
			'targetTab' => 'queries',
			'title' => $title,
		];

		if ($duplicatesOnly) {
			$ret['duplicatesOnly'] = '1';
		}

		return $ret;

	}

	/**
	 * Build visual badges shown inside a query group row.
	 *
	 * @param	array<string, mixed>	$group	Aggregated query group.
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildQueryBadgesHtml(array $group, array $data): string {

		$badges = [];

		if ((int)$group['count'] > (int)($data['duplicateQueryBudget'] ?? 1)) {
			$badges[] = ['duplicate', 'Duplicate x' . (int)$group['count']];
		}

		if ((float)$group['maxMs'] >= (int)$data['slowQueryMs']) {
			$badges[] = ['slow', 'Slow ' . $this->formatMilliseconds((float)$group['maxMs'])];
		} else if ((float)$group['totalMs'] >= (int)$data['slowQueryMs']) {
			$badges[] = ['total', 'Total ' . $this->formatMilliseconds((float)$group['totalMs'])];
		}

		if (!count($badges)) {
			return '';
		}

		$ret = '<span class="logbar-query-badges">';

		foreach ($badges as $badge) {
			$ret .= '<span class="logbar-query-badge logbar-query-badge-' . $this->escapeAttribute($badge[0]) . '">' . $this->escape($badge[1]) . '</span>';
		}

		$ret .= '</span>';

		return $ret;

	}

	/**
	 * Return a compact query group label from operation and table metadata.
	 *
	 * @param	array<string, mixed>	$group	Aggregated query group.
	 */
	private function queryLabel(array $group): string {

		$label = trim(((string)$group['operation'] ?: 'SQL') . ' ' . (string)$group['table']);

		return $label ?: 'SQL query';

	}

	/**
	 * Return a count label with an English singular or plural noun.
	 */
	private function countLabel(int $count, string $singular): string {

		return $count . ' ' . $singular . (1 === $count ? '' : 's');

	}

	/**
	 * Return the query group with the highest single occurrence duration.
	 *
	 * @param	array<int, array<string, mixed>>	$queryGroups	Aggregated query groups.
	 */
	private function slowestQueryGroup(array $queryGroups): ?array {

		$slowest = null;

		foreach ($queryGroups as $group) {
			if (!$slowest or (float)$group['maxMs'] > (float)$slowest['maxMs']) {
				$slowest = $group;
			}
		}

		return $slowest;

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
	private function buildTabsHtml(bool $showEventPanes): string {

		$ret = '<div class="logbar-tabs" role="tablist">';
		$ret .= '<button type="button" role="tab" aria-selected="true" data-logbar-tab-button="overview" class="active">Overview</button>';
		if ($showEventPanes) {
			$ret .= '<button type="button" role="tab" aria-selected="false" data-logbar-tab-button="timeline">Timeline</button>';
		}
		$ret .= '<button type="button" role="tab" aria-selected="false" data-logbar-tab-button="queries">Queries</button>';
		if ($showEventPanes) {
			$ret .= '<button type="button" role="tab" aria-selected="false" data-logbar-tab-button="events">Events</button>';
		}
		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Return whether Timeline and Events panes would have visible rows.
	 *
	 * @param	LogBarEntry[]	$events	Request events already collected by LogBar.
	 */
	private function hasRenderableEventRows(array $events, bool $showQueries): bool {

		foreach ($events as $entry) {
			if ($showQueries or !$entry->isQuery()) {
				return true;
			}
		}

		return false;

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
