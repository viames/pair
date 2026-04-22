<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Core\Observability;
use Pair\Core\Router;
use Pair\Models\Session;
use Pair\Models\User;

/**
 * Singleton helper class to collect and render request debug information.
 */
class LogBar {

	/**
	 * Memory usage ratio percent above which only warnings and errors are logged.
	 */
	private const MEMORY_ALERT_RATIO = 60.0;

	/**
	 * Memory usage ratio percent above which the overview reports memory pressure.
	 */
	private const MEMORY_NEAR_LIMIT_RATIO = 80.0;

	/**
	 * Cookie names for log visibility.
	 */
	private const COOKIE_SHOW_QUERIES = 'LogBarShowQueries';

	/**
	 * Legacy cookie name for log visibility.
	 */
	private const COOKIE_SHOW_EVENTS = 'LogBarShowEvents';

	/**
	 * Legacy cookie name for show queries.
	 */
	private const LEGACY_COOKIE_SHOW_QUERIES = 'LogShowQueries';

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Start time.
	 */
	private float $timeStart;

	/**
	 * Time chrono of last event.
	 */
	private float $lastChrono;

	/**
	 * Logged events.
	 *
	 * @var	\stdClass[]
	 */
	private array $events = [];

	/**
	 * Disabled flag.
	 */
	private bool $disabled = false;

	/**
	 * Tracks whether the retention warning has already been emitted.
	 */
	private bool $eventLimitWarningAdded = false;

	/**
	 * Incremental event identifier.
	 */
	private int $nextEventId = 1;

	/**
	 * Disabled constructor.
	 */
	private function __construct() {}

	/**
	 * Render the LogBar when the object is used as a string.
	 */
	public function __toString(): string {

		return $this->render();

	}

	/**
	 * Add a normalized event to the current request log.
	 *
	 * @param	array<string, mixed>	$attributes	Structured attributes used by the inspector renderer.
	 */
	private function addEvent(
		string $description,
		string $type = 'notice',
		?string $subtext = null,
		array $attributes = [],
		?string $status = null
	): void {

		if (!$this->isEnabled()) {
			return;
		}

		$type = $this->normalizeType($type);

		if ($this->shouldDropEvent($type)) {
			return;
		}

		$now = $this->getMicrotime();
		$duration = abs($now - $this->lastChrono);
		$start = max(0.0, $now - $this->timeStart - $duration);

		$event = new \stdClass();
		$event->id = 'logbar-event-' . $this->nextEventId++;
		$event->type = $type;
		$event->description = LogBarSql::redactText($description);
		$event->subtext = is_null($subtext) ? null : LogBarSql::redactText($subtext);
		$event->start = $start;
		$event->duration = $duration;
		$event->chrono = $duration;
		$event->status = $status ?: $this->statusForType($type);
		$event->attributes = $this->sanitizeAttributes($attributes);

		$this->events[] = $event;
		$this->lastChrono = $now;

	}

	/**
	 * Build one compact event row for the chronological event list or AJAX response.
	 */
	private function buildEventHtml(\stdClass $event, string $eventDomId = '', bool $withData = true): string {

		$type = $this->normalizeType((string)$event->type);
		$duration = (float)($event->duration ?? $event->chrono ?? 0.0);
		$isSlow = $this->isSlowEvent($event);
		$data = $withData
			? ' data-logbar-row="1" data-logbar-type="' . $this->escapeAttribute($type) . '" data-logbar-text="' . $this->escapeAttribute($this->eventSearchText($event)) . '"'
			: '';
		$subtext = $event->subtext ?? null;
		$domId = strlen($eventDomId) ? ' id="' . $this->escapeAttribute($eventDomId) . '"' : '';

		return
			'<div' . $domId . $data . ' class="logbar-event ' . $this->escapeAttribute($type) . ($isSlow ? ' logbar-slow' : '') . '">' .
				'<span class="time' . ($isSlow ? ' slow' : '') . '">' . $this->escape($this->formatChrono($duration)) . '</span>' .
				'<span class="logbar-event-type">' . $this->escape($type) . '</span>' .
				'<span class="logbar-event-message">' . $this->escape((string)$event->description) . '</span>' .
				($subtext ? ' <span class="logbar-event-subtext">| ' . $this->escape((string)$subtext) . '</span>' : '') .
			'</div>';

	}

	/**
	 * Build the client-side filtering and tab script for the rendered LogBar.
	 */
	private function buildFilterScriptHtml(): string {

		$script = <<<'JS'
<script>
(function (global) {
	"use strict";

	if (global.PairLogBar && typeof global.PairLogBar.initAll === "function") {
		global.PairLogBar.initAll();
		return;
	}

	/**
	 * Read a plain cookie value by name.
	 */
	function readCookie(name) {
		const prefix = encodeURIComponent(name) + "=";
		const parts = document.cookie ? document.cookie.split("; ") : [];
		for (const part of parts) {
			if (part.indexOf(prefix) === 0) return decodeURIComponent(part.slice(prefix.length));
		}
		return "";
	}

	/**
	 * Store a small LogBar preference cookie.
	 */
	function writeCookie(name, value) {
		document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + "; path=/; SameSite=Lax";
	}

	/**
	 * Apply the selected tab to one LogBar instance.
	 */
	function setActiveTab(logbar, tabName) {
		logbar.querySelectorAll("[data-logbar-tab]").forEach(function (pane) {
			pane.hidden = pane.getAttribute("data-logbar-tab") !== tabName;
		});
		logbar.querySelectorAll("[data-logbar-tab-button]").forEach(function (button) {
			button.classList.toggle("active", button.getAttribute("data-logbar-tab-button") === tabName);
		});
	}

	/**
	 * Apply text, type, and query filters to visible rows.
	 */
	function applyFilters(logbar) {
		const searchControl = logbar.querySelector("[data-logbar-search]");
		const typeControl = logbar.querySelector("[data-logbar-type-filter]");
		const queriesOnlyControl = logbar.querySelector("[data-logbar-queries-only]");
		const warningsOnlyControl = logbar.querySelector("[data-logbar-warnings-only]");
		const duplicatesOnlyControl = logbar.querySelector("[data-logbar-duplicates-only]");
		const body = logbar.querySelector(".logbar-body");
		const search = ((searchControl && searchControl.value) || "").toLowerCase();
		const type = (typeControl && typeControl.value) || "";
		const queriesOnly = !!(queriesOnlyControl && queriesOnlyControl.checked);
		const warningsOnly = !!(warningsOnlyControl && warningsOnlyControl.checked);
		const duplicatesOnly = !!(duplicatesOnlyControl && duplicatesOnlyControl.checked);
		const queryRowsVisible = !body || body.classList.contains("show-queries");

		logbar.querySelectorAll("[data-logbar-row]").forEach(function (row) {
			const rowType = row.getAttribute("data-logbar-type") || "";
			const text = (row.getAttribute("data-logbar-text") || "").toLowerCase();
			let hidden = false;
			if (search && text.indexOf(search) === -1) hidden = true;
			if (type && rowType !== type) hidden = true;
			if (queriesOnly && rowType !== "query") hidden = true;
			if (warningsOnly && rowType !== "warning" && rowType !== "error") hidden = true;
			if (!queryRowsVisible && rowType === "query") hidden = true;
			row.hidden = hidden;
		});

		logbar.querySelectorAll("[data-logbar-query-group]").forEach(function (group) {
			const text = (group.getAttribute("data-logbar-text") || "").toLowerCase();
			let hidden = false;
			if (search && text.indexOf(search) === -1) hidden = true;
			if (duplicatesOnly && group.getAttribute("data-logbar-duplicate") !== "1") hidden = true;
			group.hidden = hidden;
		});
	}

	/**
	 * Attach controls to one rendered LogBar.
	 */
	function initLogBar(logbar) {
		if (logbar.getAttribute("data-logbar-ready") === "1") return;
		logbar.setAttribute("data-logbar-ready", "1");

		const body = logbar.querySelector(".logbar-body");
		const toggle = logbar.querySelector("#toggle-events");
		const queryToggle = logbar.querySelector("[data-logbar-query-toggle]");

		logbar.querySelectorAll("[data-logbar-tab-button]").forEach(function (button) {
			button.addEventListener("click", function () {
				setActiveTab(logbar, button.getAttribute("data-logbar-tab-button") || "overview");
			});
		});

		logbar.querySelectorAll("[data-logbar-search], [data-logbar-type-filter], [data-logbar-queries-only], [data-logbar-warnings-only], [data-logbar-duplicates-only]").forEach(function (control) {
			control.addEventListener("input", function () {
				applyFilters(logbar);
			});
			control.addEventListener("change", function () {
				applyFilters(logbar);
			});
		});

		if (toggle && body) {
			toggle.addEventListener("click", function () {
				const isHidden = body.classList.toggle("hidden");
				toggle.classList.toggle("expanded", !isHidden);
				toggle.textContent = isHidden ? "Show" : "Hide";
				writeCookie("LogBarShowEvents", isHidden ? "0" : "1");
			});
		}

		if (queryToggle && body) {
			queryToggle.addEventListener("click", function () {
				const showQueries = body.classList.toggle("show-queries");
				queryToggle.classList.toggle("active", showQueries);
				writeCookie("LogBarShowQueries", showQueries ? "1" : "0");
				applyFilters(logbar);
			});
		}

		if (!readCookie("LogBarShowEvents") && toggle && body) {
			toggle.textContent = body.classList.contains("hidden") ? "Show" : "Hide";
		}

		setActiveTab(logbar, "overview");
		applyFilters(logbar);
	}

	/**
	 * Initialize every LogBar currently available in the document.
	 */
	function initAll() {
		document.querySelectorAll("#logbar[data-logbar-root]").forEach(initLogBar);
	}

	global.PairLogBar = {
		initAll: initAll
	};

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initAll);
	} else {
		initAll();
	}
})(window);
</script>
JS;

		return $script;

	}

	/**
	 * Build the full LogBar card.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildLogHtml(array $data, bool $showQueries, bool $showEvents): string {

		$bodyClasses = 'card-body logbar-body events' . ($showQueries ? ' show-queries' : '') . ($showEvents ? '' : ' hidden');
		$route = $this->routeLabel();
		$correlationId = Observability::correlationId();
		$memory = $this->memoryLabel((int)$data['memoryPeakBytes'], (int)$data['memoryLimitBytes']);

		$ret = '<div class="card mt-5" id="logbar" data-logbar-root>';
		$ret .= '<div class="card-header logbar-header">';
		$ret .= '<div class="float-end"><button type="button" id="toggle-events" class="item' . ($showEvents ? ' expanded' : '') . '">' . ($showEvents ? 'Hide' : 'Show') . '</button></div>';
		$ret .= '<h4>Pair LogBar</h4>';
		$ret .= '<div class="head">';
		$ret .= $this->buildMetricHtml('Route', $route ?: '-', '', 'route');
		$ret .= $this->buildMetricHtml('Total', $this->formatChrono((float)$data['totalSeconds']), '', ((float)$data['totalMs'] >= $this->slowRequestMs() ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('DB', $this->formatChrono((float)$data['querySeconds']), round((float)$data['queryPercent']) . '%', 'database', ' data-logbar-query-toggle="1"');
		$ret .= $this->buildMetricHtml('Queries', (string)$data['queryCount'], '', ((int)$data['queryCount'] > $this->queryBudget() ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('Memory', $memory['value'], $memory['subtext'], ((float)$data['memoryLimitPercent'] >= self::MEMORY_NEAR_LIMIT_RATIO ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('Warnings', (string)$data['warningCount'], '', ((int)$data['warningCount'] ? 'warning' : ''));
		$ret .= $this->buildMetricHtml('Errors', (string)$data['errorCount'], '', ((int)$data['errorCount'] ? 'error' : ''));
		$ret .= $this->buildMetricHtml('Correlation', substr($correlationId, 0, 12), '', 'correlation');
		$ret .= '</div></div>';

		$ret .= '<div class="' . $bodyClasses . '">';
		$ret .= $this->buildTabsHtml();
		$ret .= $this->buildFiltersHtml($data['types']);
		$ret .= '<div class="logbar-panes">';
		$ret .= '<section data-logbar-tab="overview">' . $this->buildOverviewHtml($data) . '</section>';
		$ret .= '<section data-logbar-tab="timeline" hidden>' . $this->buildTimelineHtml($data) . '</section>';
		$ret .= '<section data-logbar-tab="queries" hidden>' . $this->buildQueriesHtml($data) . '</section>';
		$ret .= '<section data-logbar-tab="events" hidden>' . $this->buildEventsHtml($data) . '</section>';
		$ret .= '</div></div></div>';
		$ret .= $this->buildFilterScriptHtml();

		return $ret;

	}

	/**
	 * Build filter controls for the LogBar body.
	 *
	 * @param	string[]	$types	Observed event types.
	 */
	private function buildFiltersHtml(array $types): string {

		sort($types);

		$ret = '<div class="logbar-filters">';
		$ret .= '<input type="search" data-logbar-search placeholder="Search">';
		$ret .= '<select data-logbar-type-filter><option value="">All types</option>';

		foreach ($types as $type) {
			$ret .= '<option value="' . $this->escapeAttribute($type) . '">' . $this->escape($type) . '</option>';
		}

		$ret .= '</select>';
		$ret .= '<label><input type="checkbox" data-logbar-queries-only> Queries only</label>';
		$ret .= '<label><input type="checkbox" data-logbar-warnings-only> Warnings/errors</label>';
		$ret .= '<label><input type="checkbox" data-logbar-duplicates-only> Duplicates</label>';
		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Build a compact metric for the sticky header.
	 */
	private function buildMetricHtml(string $label, string $value, string $subtext = '', string $class = '', string $attributes = ''): string {

		return
			'<div class="item ' . $this->escapeAttribute(trim($class)) . '"' . $attributes . '>' .
				'<span class="label">' . $this->escape($label) . '</span>' .
				'<span class="emph">' . $this->escape($value) . '</span>' .
				(strlen($subtext) ? '<span class="sub">' . $this->escape($subtext) . '</span>' : '') .
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

		foreach ($data['events'] as $event) {

			$domId = '';

			if ('warning' === $event->type and !$firstWarning) {
				$domId = 'logFirstWarning';
				$firstWarning = true;
			} else if ('error' === $event->type and !$firstError) {
				$domId = 'logFirstError';
				$firstError = true;
			}

			$ret .= $this->buildEventHtml($event, $domId);

		}

		return $ret;

	}

	/**
	 * Build one automatic diagnostic finding.
	 *
	 * @param	array<string, string>	$finding	Finding title, detail, and type.
	 */
	private function buildFindingHtml(array $finding): string {

		return
			'<li class="' . $this->escapeAttribute($finding['type'] ?? 'notice') . '">' .
				'<strong>' . $this->escape($finding['title'] ?? 'Finding') . '</strong>' .
				'<span>' . $this->escape($finding['detail'] ?? '') . '</span>' .
			'</li>';

	}

	/**
	 * Build the overview pane with diagnostics and top-level request metrics.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 */
	private function buildOverviewHtml(array $data): string {

		$ret = '<div class="logbar-overview">';
		$ret .= '<div class="logbar-overview-grid">';
		$ret .= $this->buildOverviewStat('Request', $this->formatChrono((float)$data['totalSeconds']), 'total');
		$ret .= $this->buildOverviewStat('Database', $this->formatChrono((float)$data['querySeconds']), round((float)$data['queryPercent']) . '% of request');
		$ret .= $this->buildOverviewStat('Queries', (string)$data['queryCount'], 'budget ' . $this->queryBudget());
		$ret .= $this->buildOverviewStat('Warnings', (string)$data['warningCount'], '');
		$ret .= $this->buildOverviewStat('Errors', (string)$data['errorCount'], '');
		$ret .= $this->buildOverviewStat('Peak memory', $this->formatBytes((int)$data['memoryPeakBytes']), $this->memoryLimitSubtext((int)$data['memoryLimitBytes'], (float)$data['memoryLimitPercent']));
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
	private function buildOverviewStat(string $label, string $value, string $subtext): string {

		return
			'<div class="logbar-stat">' .
				'<span>' . $this->escape($label) . '</span>' .
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
			$ret .= '<details class="logbar-query-group' . ($isDuplicate ? ' duplicate' : '') . '" data-logbar-query-group="1" data-logbar-duplicate="' . ($isDuplicate ? '1' : '0') . '" data-logbar-text="' . $this->escapeAttribute($group['searchText']) . '">';
			$ret .= '<summary class="logbar-query-summary">';
			$ret .= '<span>' . $this->escape((string)$group['count']) . '</span>';
			$ret .= '<span>' . $this->escape($this->formatMilliseconds((float)$group['totalMs'])) . '</span>';
			$ret .= '<span>' . $this->escape($this->formatMilliseconds((float)$group['avgMs'])) . '</span>';
			$ret .= '<span>' . $this->escape($this->formatMilliseconds((float)$group['maxMs'])) . '</span>';
			$ret .= '<span>' . $this->escape((string)$group['rows']) . '</span>';
			$ret .= '<span>' . $this->escape((string)$group['operation']) . '</span>';
			$ret .= '<span>' . $this->escape((string)$group['table']) . '</span>';
			$ret .= '<code>' . $this->escape((string)$group['sql']) . '</code>';
			$ret .= '</summary>';
			$ret .= '<div class="logbar-query-occurrences">';

			foreach ($group['occurrences'] as $occurrence) {
				$ret .= '<div data-logbar-row="1" data-logbar-type="query" data-logbar-text="' . $this->escapeAttribute($occurrence['searchText']) . '" class="logbar-query-occurrence query' . ((float)$occurrence['durationMs'] >= $this->slowQueryMs() ? ' logbar-slow' : '') . '">';
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
	 * Build the tab controls.
	 */
	private function buildTabsHtml(): string {

		return
			'<div class="logbar-tabs" role="tablist">' .
				'<button type="button" data-logbar-tab-button="overview" class="active">Overview</button>' .
				'<button type="button" data-logbar-tab-button="timeline">Timeline</button>' .
				'<button type="button" data-logbar-tab-button="queries">Queries</button>' .
				'<button type="button" data-logbar-tab-button="events">Events</button>' .
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

		foreach ($data['events'] as $event) {
			$left = min(100.0, max(0.0, ((float)$event->start / $totalSeconds) * 100));
			$width = min(100.0 - $left, max(0.4, ((float)$event->duration / $totalSeconds) * 100));
			$type = $this->normalizeType((string)$event->type);
			$ret .= '<div class="logbar-timeline-row ' . $this->escapeAttribute($type) . ($this->isSlowEvent($event) ? ' logbar-slow' : '') . '" data-logbar-row="1" data-logbar-type="' . $this->escapeAttribute($type) . '" data-logbar-text="' . $this->escapeAttribute($this->eventSearchText($event)) . '">';
			$ret .= '<div class="logbar-timeline-label"><span>' . $this->escape($this->formatChrono((float)$event->duration)) . '</span><strong>' . $this->escape($type) . '</strong></div>';
			$ret .= '<div class="logbar-timeline-track"><span style="left:' . $this->formatPercent($left) . '%;width:' . $this->formatPercent($width) . '%"></span></div>';
			$ret .= '<div class="logbar-timeline-text">' . $this->escape((string)$event->description) . '</div>';
			$ret .= '</div>';
		}

		$ret .= '</div>';

		return $ret;

	}

	/**
	 * Check if the log can appear in the current session.
	 *
	 * @return	bool	True if can be shown.
	 */
	public function canBeShown(): bool {

		// user is defined, could be super
		if (User::current() and Options::get('show_log')) {

			// get current session
			$session = Session::current();

			// if impersonating, use the former user attribs
			if ($session and $session->hasFormerUser()) {
				$formerUser = $session->getFormerUser();
				return ($formerUser ? $formerUser->super : false);
			}

			return (bool)User::current()->super;

		} else {

			return false;

		}

	}

	/**
	 * Build structured inspector data for the rendered LogBar.
	 *
	 * @return	array<string, mixed>
	 */
	private function collectInspectorData(): array {

		$apiSeconds = 0.0;
		$querySeconds = 0.0;
		$queryCount = 0;
		$warningCount = 0;
		$errorCount = 0;
		$totalSeconds = 0.0;
		$types = [];

		foreach ($this->events as $event) {

			$type = $this->normalizeType((string)$event->type);
			$duration = (float)($event->duration ?? $event->chrono ?? 0.0);
			$totalSeconds += $duration;
			$types[$type] = $type;

			if ('api' === $type) {
				$apiSeconds += $duration;
			} else if ('query' === $type) {
				$querySeconds += $duration;
				$queryCount++;
			} else if ('warning' === $type) {
				$warningCount++;
			} else if ('error' === $type) {
				$errorCount++;
			}

		}

		$totalSeconds = max($totalSeconds, $this->getMicrotime() - $this->timeStart);
		$queryGroups = $this->queryGroups($this->events);
		$memoryLimit = $this->getMemoryLimitBytes();
		$memoryPeak = memory_get_peak_usage();
		$memoryLimitPercent = $memoryLimit > 0 ? ($memoryPeak / $memoryLimit * 100) : 0.0;
		$queryPercent = $totalSeconds > 0 ? ($querySeconds / $totalSeconds * 100) : 0.0;

		$data = [
			'apiSeconds' => $apiSeconds,
			'events' => $this->events,
			'errorCount' => $errorCount,
			'memoryLimitBytes' => $memoryLimit,
			'memoryLimitPercent' => $memoryLimitPercent,
			'memoryPeakBytes' => $memoryPeak,
			'queryCount' => $queryCount,
			'queryGroups' => $queryGroups,
			'queryPercent' => $queryPercent,
			'querySeconds' => $querySeconds,
			'totalMs' => $totalSeconds * 1000,
			'totalSeconds' => $totalSeconds,
			'types' => array_values($types),
			'warningCount' => $warningCount,
		];
		$data['findings'] = $this->findings($data);

		return $data;

	}

	/**
	 * Shutdown the log.
	 */
	final public function disable(): void {

		$this->disabled = true;

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
	 * Adds an event, storing its chrono time.
	 *
	 * @param	string		$description	Event description.
	 * @param	string		$type			Event type notice, query, api, warning or error (default is notice).
	 * @param	null|string	$subtext		Optional additional text.
	 */
	final public static function event(string $description, string $type = 'notice', ?string $subtext = null): void {

		$self = self::getInstance();

		if (!$self->isEnabled()) {
			return;
		}

		$attributes = [];

		if ('query' === $self->normalizeType($type)) {
			$attributes = self::queryAttributes($description, self::rowsFromSubtext($subtext));
			$description = LogBarSql::render($description, [], self::showSqlValues());
		}

		$self->addEvent($description, $type, $subtext, $attributes);

	}

	/**
	 * Return search text for a structured event row.
	 */
	private function eventSearchText(\stdClass $event): string {

		$attributes = (array)($event->attributes ?? []);

		return implode(' ', array_filter([
			(string)($event->type ?? ''),
			(string)($event->description ?? ''),
			(string)($event->subtext ?? ''),
			(string)($attributes['operation'] ?? ''),
			(string)($attributes['table'] ?? ''),
			(string)($attributes['normalizedSql'] ?? ''),
		]));

	}

	/**
	 * Build automatic findings for the overview pane.
	 *
	 * @param	array<string, mixed>	$data	Inspector summary data.
	 * @return	array<int, array<string, string>>
	 */
	private function findings(array $data): array {

		$findings = [];
		$totalMs = (float)$data['totalMs'];
		$queryMs = (float)$data['querySeconds'] * 1000;
		$queryPercent = (float)$data['queryPercent'];
		$queryCount = (int)$data['queryCount'];

		if ($totalMs >= $this->slowRequestMs()) {
			$findings[] = [
				'type' => 'warning',
				'title' => 'Slow request',
				'detail' => 'Request time is ' . $this->formatMilliseconds($totalMs) . ' over the ' . $this->slowRequestMs() . ' ms threshold.',
			];
		}

		if ($queryMs > 0 and $queryPercent > 50.0) {
			$findings[] = [
				'type' => 'warning',
				'title' => 'DB-bound request',
				'detail' => 'DB time is ' . $this->formatMilliseconds($queryMs) . ' (' . round($queryPercent) . '% of request time).',
			];
		}

		if ($queryCount > $this->queryBudget()) {
			$findings[] = [
				'type' => 'warning',
				'title' => 'High query count',
				'detail' => $queryCount . ' queries exceed the budget of ' . $this->queryBudget() . '.',
			];
		}

		$duplicateGroups = array_filter($data['queryGroups'], function (array $group): bool {
			return (int)$group['count'] > $this->duplicateQueryBudget();
		});

		if (count($duplicateGroups)) {
			$worst = reset($duplicateGroups);
			$findings[] = [
				'type' => 'warning',
				'title' => 'Duplicate query fingerprints',
				'detail' => count($duplicateGroups) . ' fingerprints exceed the duplicate budget; worst count is ' . (int)$worst['count'] . '.',
			];
		}

		$slowest = $this->slowestQueryGroup($data['queryGroups']);

		if ($slowest and (float)$slowest['maxMs'] >= $this->slowQueryMs()) {
			$findings[] = [
				'type' => 'warning',
				'title' => 'Slowest query',
				'detail' => $this->formatMilliseconds((float)$slowest['maxMs']) . ' in ' . ((string)$slowest['operation'] ?: 'SQL') . ' ' . ((string)$slowest['table'] ?: ''),
			];
		}

		if ((int)$data['warningCount'] or (int)$data['errorCount']) {
			$findings[] = [
				'type' => ((int)$data['errorCount'] ? 'error' : 'warning'),
				'title' => 'Warnings or errors',
				'detail' => (int)$data['warningCount'] . ' warnings and ' . (int)$data['errorCount'] . ' errors were logged.',
			];
		}

		if ((float)$data['memoryLimitPercent'] >= self::MEMORY_NEAR_LIMIT_RATIO) {
			$findings[] = [
				'type' => 'warning',
				'title' => 'Memory near limit',
				'detail' => 'Peak memory is ' . round((float)$data['memoryLimitPercent']) . '% of the configured limit.',
			];
		}

		return $findings;

	}

	/**
	 * Choose if use sec or millisec based on amount of time to show.
	 *
	 * @param	float	$chrono	Time in seconds.
	 * @return	string	Formatted time string.
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
	 * Returns a cookie value as bool.
	 *
	 * @param	string	$name	Cookie name.
	 * @return	bool	Cookie value or false.
	 */
	private function getCookieBool(string $name): bool {

		if (!isset($_COOKIE[$name])) {
			return false;
		}

		$value = $_COOKIE[$name];

		if (is_bool($value)) {
			return $value;
		}

		return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on', 'b:1;'], true);

	}

	/**
	 * Returns count of registered error.
	 *
	 * @return	int	Number of errors.
	 */
	final public function getErrorCount(): int {

		return $this->countEventsByType('error');

	}

	/**
	 * Singleton instance method.
	 *
	 * @return	self	Instance of LogBar.
	 */
	final public static function getInstance(): self {

		if (null == self::$instance) {
			self::$instance = new self();
			self::$instance->startChrono();
		}

		return self::$instance;

	}

	/**
	 * Returns [showQueries, showEvents] cookie settings.
	 *
	 * @return	array{0: bool, 1: bool}
	 */
	private function getLogVisibility(): array {

		$showQueries = $this->getCookieBool(self::COOKIE_SHOW_QUERIES);
		$showEvents = $this->getCookieBool(self::COOKIE_SHOW_EVENTS);

		return [$showQueries, $showEvents];

	}

	/**
	 * Returns memory limit in bytes.
	 *
	 * @return	int	Memory limit in bytes.
	 */
	private function getMemoryLimitBytes(): int {

		$limit = ini_get('memory_limit');
		if ('-1' === $limit) {
			return 0;
		}

		// Convert shorthand memory limits such as 128M into bytes.
		switch (substr($limit, -1)) {
			case 'G': case 'g': $multiplier = 1073741824;	break;
			case 'M': case 'm': $multiplier = 1048576;		break;
			case 'K': case 'k': $multiplier = 1024;			break;
			default:			$multiplier = 1;			break;
		}

		return (int)$limit * $multiplier;

	}

	/**
	 * Returns current time as float value.
	 *
	 * @return	float	Current time in seconds with microseconds.
	 */
	private function getMicrotime(): float {

		return microtime(true);

	}

	/**
	 * Returns the query count.
	 */
	final public function getQueryCount(): int {

		return $this->countEventsByType('query');

	}

	/**
	 * Returns showQueries cookie, with legacy fallback.
	 *
	 * @return	bool	True if queries should be shown.
	 */
	private function getShowQueriesForAjax(): bool {

		if (isset($_COOKIE[self::COOKIE_SHOW_QUERIES])) {
			return $this->getCookieBool(self::COOKIE_SHOW_QUERIES);
		}

		return isset($_COOKIE[self::LEGACY_COOKIE_SHOW_QUERIES]) ? $this->getCookieBool(self::LEGACY_COOKIE_SHOW_QUERIES) : false;

	}

	/**
	 * Returns the warning count.
	 */
	final public function getWarningCount(): int {

		return $this->countEventsByType('warning');

	}

	/**
	 * Check that LogBar can be collected by checking "disabled" flag, CLI, API, router module and Options.
	 *
	 * @return	bool	True if enabled.
	 */
	final public function isEnabled(): bool {

		if ($this->disabled or 'cli' == php_sapi_name()) {
			return false;
		}

		$router = Router::getInstance();

		if ('api' == $router->module or ('user' == $router->module and 'login' == $router->action)) {
			return false;
		}

		return true;

	}

	/**
	 * Return true when an event should be visually marked as slow.
	 */
	private function isSlowEvent(\stdClass $event): bool {

		$durationMs = (float)($event->duration ?? $event->chrono ?? 0.0) * 1000;

		if ('query' === ($event->type ?? '') and $durationMs >= $this->slowQueryMs()) {
			return true;
		}

		return $durationMs >= 100.0;

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

	/**
	 * Normalize an event type for CSS classes and filters.
	 */
	private function normalizeType(string $type): string {

		$type = strtolower(trim($type));
		$type = preg_replace('/[^a-z0-9_-]+/', '-', $type) ?? 'notice';

		return trim($type, '-') ?: 'notice';

	}

	/**
	 * Adds a query event with safe SQL rendering and structured query attributes.
	 *
	 * @param	array<int|string, mixed>	$params	Bound query parameters.
	 */
	final public static function query(string $query, int $rows, array $params = []): void {

		$self = self::getInstance();

		if (!$self->isEnabled()) {
			return;
		}

		$description = LogBarSql::render($query, $params, self::showSqlValues());
		$subtext = $rows . ' ' . (1 === $rows ? 'row' : 'rows');

		$self->addEvent($description, 'query', $subtext, self::queryAttributes($query, $rows));

	}

	/**
	 * Return structured query attributes for LogBar aggregation.
	 *
	 * @return	array<string, mixed>
	 */
	private static function queryAttributes(string $query, ?int $rows = null): array {

		return [
			'fingerprint' => LogBarSql::fingerprint($query),
			'normalizedSql' => LogBarSql::normalize($query),
			'operation' => LogBarSql::operation($query),
			'rows' => $rows,
			'table' => LogBarSql::table($query),
		];

	}

	/**
	 * Return grouped query metrics sorted by total duration.
	 *
	 * @param	\stdClass[]	$events	Structured LogBar events.
	 * @return	array<int, array<string, mixed>>
	 */
	private function queryGroups(array $events): array {

		$groups = [];

		foreach ($events as $event) {

			if ('query' !== ($event->type ?? null)) {
				continue;
			}

			$attributes = (array)($event->attributes ?? []);
			$sql = (string)($attributes['normalizedSql'] ?? $event->description ?? '');
			$fingerprint = (string)($attributes['fingerprint'] ?? LogBarSql::fingerprint($sql));
			$durationMs = (float)($event->duration ?? $event->chrono ?? 0.0) * 1000;
			$rows = is_numeric($attributes['rows'] ?? null) ? (int)$attributes['rows'] : (self::rowsFromSubtext($event->subtext ?? null) ?? 0);

			if (!isset($groups[$fingerprint])) {
				$groups[$fingerprint] = [
					'avgMs' => 0.0,
					'count' => 0,
					'fingerprint' => $fingerprint,
					'maxMs' => 0.0,
					'occurrences' => [],
					'operation' => (string)($attributes['operation'] ?? LogBarSql::operation($sql)),
					'rows' => 0,
					'searchText' => '',
					'sql' => $sql,
					'table' => (string)($attributes['table'] ?? LogBarSql::table($sql)),
					'totalMs' => 0.0,
				];
			}

			$groups[$fingerprint]['count']++;
			$groups[$fingerprint]['totalMs'] += $durationMs;
			$groups[$fingerprint]['maxMs'] = max((float)$groups[$fingerprint]['maxMs'], $durationMs);
			$groups[$fingerprint]['rows'] += $rows;
			$groups[$fingerprint]['occurrences'][] = [
				'durationMs' => $durationMs,
				'rows' => $rows,
				'searchText' => $this->eventSearchText($event),
				'sql' => (string)($event->description ?? $sql),
			];

		}

		foreach ($groups as &$group) {
			$group['avgMs'] = $group['count'] ? $group['totalMs'] / $group['count'] : 0.0;
			$group['searchText'] = implode(' ', [
				$group['operation'],
				$group['table'],
				$group['sql'],
				$group['fingerprint'],
			]);
		}
		unset($group);

		usort($groups, function (array $left, array $right): int {
			return $right['totalMs'] <=> $left['totalMs'];
		});

		return array_values($groups);

	}

	/**
	 * Returns a formatted event list of all chrono steps.
	 *
	 * @return	string	HTML code of log list.
	 */
	final public function render(): string {

		if (!$this->isEnabled() or !$this->canBeShown()) {
			return '';
		}

		[$showQueries, $showEvents] = $this->getLogVisibility();
		$limit = $this->getMemoryLimitBytes();

		// Alert about risk of out-of-memory before building the inspector summary.
		if ($limit > 0 and memory_get_usage() / $limit * 100 > self::MEMORY_ALERT_RATIO) {
			self::event('Memory usage is ' . round(memory_get_usage() / $limit * 100, 0) . '% of limit, will reduce logs');
		}

		return $this->buildLogHtml($this->collectInspectorData(), $showQueries, $showEvents);

	}

	/**
	 * Returns a formatted event list with no header, useful for AJAX purpose.
	 *
	 * @return	string	HTML code of log list.
	 */
	final public function renderForAjax(): string {

		$app = Application::getInstance();
		$router = Router::getInstance();

		if (!$this->isEnabled() or !$this->canBeShown() or !$router->sendLog()) {
			return '';
		}

		$log = '';

		// Keep the legacy AJAX payload as event rows only.
		if (Options::get('show_log') and isset($app->currentUser) and $app->currentUser->super) {

			$showQueries = $this->getShowQueriesForAjax();

			foreach ($this->events as $event) {
				$class = $this->normalizeType((string)$event->type) . (('query' == $event->type and !$showQueries) ? ' hidden' : '');
				$subtext = $event->subtext ?? null;
				$duration = (float)($event->duration ?? $event->chrono ?? 0.0);
				$log .=
					'<div class="logbar-event ' . $this->escapeAttribute($class) . '">' .
						'<span class="time">' . $this->escape($this->formatChrono($duration)) . '</span> ' .
						$this->escape((string)$event->description) .
						($subtext ? ' <span>| ' . $this->escape((string)$subtext) . '</span>' : '') .
					'</div>';
			}

		}

		return $log;

	}

	/**
	 * Reset events and start chrono again.
	 */
	final public function reset(): void {

		$this->events = [];
		$this->eventLimitWarningAdded = false;
		$this->nextEventId = 1;
		$this->startChrono();

	}

	/**
	 * Return a safe route label for the header.
	 */
	private function routeLabel(): string {

		$router = Router::getInstance();
		$module = isset($router->module) ? (string)$router->module : '';
		$action = isset($router->action) ? (string)$router->action : '';

		return trim($module . ($action ? '/' . $action : ''), '/');

	}

	/**
	 * Parse a row count from the legacy query subtext.
	 */
	private static function rowsFromSubtext(?string $subtext): ?int {

		if (is_string($subtext) and preg_match('/^\s*(\d+)/', $subtext, $matches)) {
			return (int)$matches[1];
		}

		return null;

	}

	/**
	 * Sanitize structured attributes before they are kept for rendering.
	 *
	 * @param	array<string, mixed>	$attributes	Raw event attributes.
	 * @return	array<string, mixed>
	 */
	private function sanitizeAttributes(array $attributes): array {

		$safe = [];

		foreach ($attributes as $name => $value) {

			if (!is_string($name)) {
				continue;
			}

			if (is_string($value)) {
				$safe[$name] = LogBarSql::redactText($value);
			} else if (is_null($value) or is_bool($value) or is_int($value) or is_float($value)) {
				$safe[$name] = $value;
			}

		}

		return $safe;

	}

	/**
	 * Return whether SQL values are explicitly allowed in LogBar previews.
	 */
	private static function showSqlValues(): bool {

		$value = Env::get('PAIR_LOGBAR_SHOW_SQL_VALUES');

		if (is_bool($value)) {
			return $value;
		}

		return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);

	}

	/**
	 * Return the slowest query group, if any.
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
	 * Return the duplicate query count budget.
	 */
	private function duplicateQueryBudget(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_DUPLICATE_QUERY_BUDGET') ?? 3));

	}

	/**
	 * Return the event retention cap.
	 */
	private function maxEvents(): int {

		return max(0, (int)(Env::get('PAIR_LOGBAR_MAX_EVENTS') ?? 500));

	}

	/**
	 * Return the query count budget.
	 */
	private function queryBudget(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_QUERY_BUDGET') ?? 30));

	}

	/**
	 * Return whether a non-critical event should be dropped because LogBar reached its cap.
	 */
	private function shouldDropEvent(string $type): bool {

		$maxEvents = $this->maxEvents();

		if ($maxEvents <= 0 or count($this->events) < $maxEvents) {
			return false;
		}

		if (in_array($type, ['warning', 'error'], true)) {
			return false;
		}

		if (!$this->eventLimitWarningAdded) {
			$this->eventLimitWarningAdded = true;
			$this->addEvent('LogBar event limit reached; non-critical events are being skipped.', 'warning');
		}

		return true;

	}

	/**
	 * Return the slow query threshold in milliseconds.
	 */
	private function slowQueryMs(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_SLOW_QUERY_MS') ?? 20));

	}

	/**
	 * Return the slow request threshold in milliseconds.
	 */
	private function slowRequestMs(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_SLOW_REQUEST_MS') ?? 250));

	}

	/**
	 * Starts the time chrono.
	 */
	private function startChrono(): void {

		$this->timeStart = $this->lastChrono = $this->getMicrotime();

	}

	/**
	 * Return the default status for a LogBar event type.
	 */
	private function statusForType(string $type): string {

		return match ($type) {
			'error' => 'error',
			'warning' => 'warning',
			default => 'ok',
		};

	}

	/**
	 * Count stored events by normalized type.
	 */
	private function countEventsByType(string $type): int {

		$count = 0;

		foreach ($this->events as $event) {
			if ($type === ($event->type ?? null)) {
				$count++;
			}
		}

		return $count;

	}

}
