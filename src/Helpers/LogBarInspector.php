<?php

namespace Pair\Helpers;

/**
 * Builds request-level metrics, query aggregation, and findings for LogBar.
 */
final readonly class LogBarInspector {

	/**
	 * Build the inspector with runtime thresholds.
	 */
	public function __construct(
		private int $slowRequestMs,
		private int $slowQueryMs,
		private int $queryBudget,
		private int $duplicateQueryBudget,
		private float $memoryNearLimitRatio,
	) {}

	/**
	 * Build structured request inspector data from collected entries.
	 *
	 * @param	LogBarEntry[]	$entries	Request entries collected by LogBar.
	 * @return	array<string, mixed>
	 */
	public function collect(array $entries, float $elapsedSeconds, int $memoryPeakBytes, int $memoryLimitBytes): array {

		$apiSeconds = 0.0;
		$querySeconds = 0.0;
		$queryCount = 0;
		$warningCount = 0;
		$errorCount = 0;
		$totalEntrySeconds = 0.0;
		$types = [];

		foreach ($entries as $entry) {

			$totalEntrySeconds += $entry->duration;
			$types[$entry->type] = $entry->type;

			if ('api' === $entry->type) {
				$apiSeconds += $entry->duration;
			} else if ($entry->isQuery()) {
				$querySeconds += $entry->duration;
				$queryCount++;
			} else if ('warning' === $entry->type) {
				$warningCount++;
			} else if ('error' === $entry->type) {
				$errorCount++;
			}

		}

		$totalSeconds = max($totalEntrySeconds, $elapsedSeconds);
		$queryGroups = $this->queryGroups($entries);
		$memoryLimitPercent = $memoryLimitBytes > 0 ? ($memoryPeakBytes / $memoryLimitBytes * 100) : 0.0;
		$queryPercent = $totalSeconds > 0 ? ($querySeconds / $totalSeconds * 100) : 0.0;

		$data = [
			'apiSeconds' => $apiSeconds,
			'events' => $entries,
			'errorCount' => $errorCount,
			'memoryLimitBytes' => $memoryLimitBytes,
			'memoryLimitPercent' => $memoryLimitPercent,
			'memoryNearLimitRatio' => $this->memoryNearLimitRatio,
			'memoryPeakBytes' => $memoryPeakBytes,
			'duplicateQueryBudget' => $this->duplicateQueryBudget,
			'queryBudget' => $this->queryBudget,
			'queryCount' => $queryCount,
			'queryGroups' => $queryGroups,
			'queryPercent' => $queryPercent,
			'querySeconds' => $querySeconds,
			'slowQueryMs' => $this->slowQueryMs,
			'slowRequestMs' => $this->slowRequestMs,
			'totalMs' => $totalSeconds * 1000,
			'totalSeconds' => $totalSeconds,
			'types' => array_values($types),
			'warningCount' => $warningCount,
		];
		$data['findings'] = $this->findings($data);

		return $data;

	}

	/**
	 * Return true when an entry should be visually marked as slow.
	 */
	public function isSlowEntry(LogBarEntry $entry): bool {

		if ($entry->isQuery() and $entry->durationMs() >= $this->slowQueryMs) {
			return true;
		}

		return $entry->durationMs() >= 100.0;

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

		if ($totalMs >= $this->slowRequestMs) {
			$findings[] = [
				'actionLabel' => 'Open timeline',
				'type' => 'warning',
				'targetTab' => 'timeline',
				'title' => 'Slow request',
				'detail' => 'Request time is ' . $this->formatMilliseconds($totalMs) . ' over the ' . $this->slowRequestMs . ' ms threshold.',
			];
		}

		if ($queryMs > 0 and $queryPercent > 50.0) {
			$findings[] = [
				'actionLabel' => 'Open queries',
				'type' => 'warning',
				'targetTab' => 'queries',
				'title' => 'DB-bound request',
				'detail' => 'DB time is ' . $this->formatMilliseconds($queryMs) . ' (' . round($queryPercent) . '% of request time).',
			];
		}

		if ($queryCount > $this->queryBudget) {
			$findings[] = [
				'actionLabel' => 'Open queries',
				'type' => 'warning',
				'targetTab' => 'queries',
				'title' => 'High query count',
				'detail' => $this->countLabel($queryCount, 'query') . ' ' . (1 === $queryCount ? 'exceeds' : 'exceed') . ' the budget of ' . $this->queryBudget . '.',
			];
		}

		$duplicateGroups = array_filter($data['queryGroups'], function (array $group): bool {
			return (int)$group['count'] > $this->duplicateQueryBudget;
		});

		if (count($duplicateGroups)) {
			$worst = reset($duplicateGroups);
			$count = count($duplicateGroups);
			$findings[] = [
				'actionLabel' => 'Show duplicates',
				'duplicatesOnly' => '1',
				'type' => 'warning',
				'targetTab' => 'queries',
				'title' => 'Duplicate query fingerprints',
				'detail' => $this->countLabel($count, 'fingerprint') . ' ' . (1 === $count ? 'exceeds' : 'exceed') . ' the duplicate budget; worst count is ' . (int)$worst['count'] . '.',
			];
		}

		$slowest = $this->slowestQueryGroup($data['queryGroups']);

		if ($slowest and (float)$slowest['maxMs'] >= $this->slowQueryMs) {
			$queryLabel = trim(((string)$slowest['operation'] ?: 'SQL') . ' ' . (string)$slowest['table']);
			$findings[] = [
				'actionLabel' => 'Open query',
				'openQuery' => '1',
				'search' => (string)$slowest['fingerprint'],
				'type' => 'warning',
				'targetTab' => 'queries',
				'title' => 'Slowest query',
				'detail' => $this->formatMilliseconds((float)$slowest['maxMs']) . ' in ' . ($queryLabel ?: 'SQL') . '.',
			];
		}

		if ((int)$data['warningCount'] or (int)$data['errorCount']) {
			$errorCount = (int)$data['errorCount'];
			$warningCount = (int)$data['warningCount'];
			$findings[] = [
				'actionLabel' => 'Open events',
				'type' => ($errorCount ? 'error' : 'warning'),
				'targetTab' => 'events',
				'warningsOnly' => '1',
				'title' => 'Warnings or errors',
				'detail' => $this->countLabel($warningCount, 'warning') . ' and ' . $this->countLabel($errorCount, 'error') . ' were logged.',
			];
		}

		if ((float)$data['memoryLimitPercent'] >= $this->memoryNearLimitRatio) {
			$findings[] = [
				'actionLabel' => 'Open overview',
				'type' => 'warning',
				'targetTab' => 'overview',
				'title' => 'Memory near limit',
				'detail' => 'Peak memory is ' . round((float)$data['memoryLimitPercent']) . '% of the configured limit.',
			];
		}

		return $findings;

	}

	/**
	 * Return a count label with an English singular or plural noun.
	 */
	private function countLabel(int $count, string $singular): string {

		return $count . ' ' . $singular . (1 === $count ? '' : 's');

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
	 * Return grouped query metrics sorted by total duration.
	 *
	 * @param	LogBarEntry[]	$entries	Structured LogBar entries.
	 * @return	array<int, array<string, mixed>>
	 */
	private function queryGroups(array $entries): array {

		$groups = [];

		foreach ($entries as $entry) {

			if (!$entry->isQuery()) {
				continue;
			}

			$attributes = $entry->attributes;
			$sql = (string)($attributes['normalizedSql'] ?? $entry->description);
			$fingerprint = (string)($attributes['fingerprint'] ?? LogBarSql::fingerprint($sql));
			$rows = is_numeric($attributes['rows'] ?? null) ? (int)$attributes['rows'] : (self::rowsFromSubtext($entry->subtext) ?? 0);

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
			$groups[$fingerprint]['totalMs'] += $entry->durationMs();
			$groups[$fingerprint]['maxMs'] = max((float)$groups[$fingerprint]['maxMs'], $entry->durationMs());
			$groups[$fingerprint]['rows'] += $rows;
			$groups[$fingerprint]['occurrences'][] = [
				'durationMs' => $entry->durationMs(),
				'rows' => $rows,
				'searchText' => $entry->searchText(),
				'sql' => $entry->description,
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
	 * Parse a row count from the legacy query subtext.
	 */
	public static function rowsFromSubtext(?string $subtext): ?int {

		if (is_string($subtext) and preg_match('/^\s*(\d+)/', $subtext, $matches)) {
			return (int)$matches[1];
		}

		return null;

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

}
