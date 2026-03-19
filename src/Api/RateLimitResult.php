<?php

namespace Pair\Api;

/**
 * Value object describing the outcome of a rate-limit check.
 */
class RateLimitResult {

	/**
	 * Whether the request is allowed to continue.
	 */
	public bool $allowed;

	/**
	 * Maximum requests allowed within the current time window.
	 */
	public int $limit;

	/**
	 * Remaining requests available in the current window.
	 */
	public int $remaining;

	/**
	 * Unix timestamp when the current window fully resets.
	 */
	public int $resetAt;

	/**
	 * Seconds the client should wait before retrying when blocked.
	 */
	public int $retryAfter;

	/**
	 * Storage backend that produced the decision.
	 */
	public string $driver;

	/**
	 * Create a new rate-limit result instance.
	 */
	public function __construct(bool $allowed, int $limit, int $remaining, int $resetAt, int $retryAfter, string $driver) {

		$this->allowed = $allowed;
		$this->limit = $limit;
		$this->remaining = $remaining;
		$this->resetAt = $resetAt;
		$this->retryAfter = max(0, $retryAfter);
		$this->driver = $driver;

	}

	/**
	 * Send the standard rate-limit headers for the current decision.
	 */
	public function applyHeaders(): void {

		header('X-RateLimit-Limit: ' . $this->limit);
		header('X-RateLimit-Remaining: ' . $this->remaining);
		header('X-RateLimit-Reset: ' . $this->resetAt);

		if (!$this->allowed) {
			header('Retry-After: ' . $this->retryAfter);
		}

	}

}
