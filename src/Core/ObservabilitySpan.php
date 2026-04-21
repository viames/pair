<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Timing record for one instrumented runtime operation.
 */
final class ObservabilitySpan {

	/**
	 * Attribute values safe for logs and external observability adapters.
	 *
	 * @var	array<string, mixed>
	 */
	private array $attributes;

	/**
	 * Timestamp captured when the span finishes.
	 */
	private ?float $endedAt = null;

	/**
	 * Final span status.
	 */
	private ?string $status = null;

	/**
	 * Create a new span starting at the provided monotonic timestamp.
	 *
	 * @param	array<string, mixed>	$attributes	Context values already sanitized by Observability.
	 */
	public function __construct(
		private string $name,
		array $attributes,
		private string $correlationId,
		private float $startedAt
	) {

		$this->attributes = $attributes;

	}

	/**
	 * Return the safe span attributes.
	 *
	 * @return	array<string, mixed>
	 */
	public function attributes(): array {

		return $this->attributes;

	}

	/**
	 * Return the correlation ID shared by all spans in the current request.
	 */
	public function correlationId(): string {

		return $this->correlationId;

	}

	/**
	 * Return the duration in milliseconds, using the current timestamp while the span is open.
	 */
	public function durationMs(): float {

		$endedAt = $this->endedAt ?? microtime(true);

		return max(0.0, ($endedAt - $this->startedAt) * 1000);

	}

	/**
	 * Return the finished timestamp, or null while the span is open.
	 */
	public function endedAt(): ?float {

		return $this->endedAt;

	}

	/**
	 * Finish the span once and merge final safe attributes.
	 *
	 * @param	array<string, mixed>	$attributes	Additional safe attributes for the completed span.
	 */
	public function finish(array $attributes = [], string $status = 'ok'): self {

		if (!$this->endedAt) {
			$this->endedAt = microtime(true);
		}

		$this->status = $status;
		$this->attributes = array_merge($this->attributes, $attributes);

		return $this;

	}

	/**
	 * Return whether the span has already finished.
	 */
	public function isFinished(): bool {

		return !is_null($this->endedAt);

	}

	/**
	 * Return the span name.
	 */
	public function name(): string {

		return $this->name;

	}

	/**
	 * Return the start timestamp.
	 */
	public function startedAt(): float {

		return $this->startedAt;

	}

	/**
	 * Return the final span status.
	 */
	public function status(): ?string {

		return $this->status;

	}

	/**
	 * Convert the span to a plain array suitable for adapters and tests.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'name' => $this->name,
			'correlationId' => $this->correlationId,
			'status' => $this->status,
			'startedAt' => $this->startedAt,
			'endedAt' => $this->endedAt,
			'durationMs' => $this->durationMs(),
			'attributes' => $this->attributes,
		];

	}

}
