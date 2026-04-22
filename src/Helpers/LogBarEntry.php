<?php

namespace Pair\Helpers;

/**
 * Immutable structured event stored by the Pair v4 LogBar.
 */
final readonly class LogBarEntry {

	/**
	 * Build a normalized LogBar entry.
	 *
	 * @param	array<string, mixed>	$attributes	Safe structured data used by inspector views.
	 */
	public function __construct(
		public string $id,
		public string $type,
		public string $description,
		public ?string $subtext,
		public float $start,
		public float $duration,
		public string $status,
		public array $attributes = [],
	) {}

	/**
	 * Return the duration in milliseconds.
	 */
	public function durationMs(): float {

		return $this->duration * 1000;

	}

	/**
	 * Return true when the entry represents a database query.
	 */
	public function isQuery(): bool {

		return 'query' === $this->type;

	}

	/**
	 * Return text used by client-side search and filters.
	 */
	public function searchText(): string {

		return implode(' ', array_filter([
			$this->type,
			$this->description,
			(string)$this->subtext,
			(string)($this->attributes['operation'] ?? ''),
			(string)($this->attributes['table'] ?? ''),
			(string)($this->attributes['normalizedSql'] ?? ''),
		]));

	}

}
