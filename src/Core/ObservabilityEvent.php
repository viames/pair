<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Observable runtime event for logs, errors, and non-span signals.
 */
final class ObservabilityEvent {

	/**
	 * Safe event attributes.
	 *
	 * @var	array<string, mixed>
	 */
	private array $attributes;

	/**
	 * Create an observable event with sanitized attributes.
	 *
	 * @param	array<string, mixed>	$attributes	Context values already sanitized by Observability.
	 */
	public function __construct(
		private string $name,
		private string $level,
		private string $message,
		array $attributes,
		private string $correlationId,
		private float $timestamp
	) {

		$this->attributes = $attributes;

	}

	/**
	 * Return the safe event attributes.
	 *
	 * @return	array<string, mixed>
	 */
	public function attributes(): array {

		return $this->attributes;

	}

	/**
	 * Return the request correlation ID.
	 */
	public function correlationId(): string {

		return $this->correlationId;

	}

	/**
	 * Return the event severity level.
	 */
	public function level(): string {

		return $this->level;

	}

	/**
	 * Return the event message.
	 */
	public function message(): string {

		return $this->message;

	}

	/**
	 * Return the event name.
	 */
	public function name(): string {

		return $this->name;

	}

	/**
	 * Return the event timestamp.
	 */
	public function timestamp(): float {

		return $this->timestamp;

	}

	/**
	 * Convert the event to a plain array suitable for adapters and tests.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'name' => $this->name,
			'level' => $this->level,
			'message' => $this->message,
			'correlationId' => $this->correlationId,
			'timestamp' => $this->timestamp,
			'attributes' => $this->attributes,
		];

	}

}
