<?php

declare(strict_types=1);

namespace Pair\Core;

use Pair\Api\RateLimitResult;
use Pair\Api\RateLimiter;

/**
 * Rate-limits authentication attempts across identifier and client-address buckets.
 */
final class AuthAttemptLimiter {

	/**
	 * Whether rate limiting is enabled for this instance.
	 */
	private bool $enabled;

	/**
	 * Maximum attempts allowed for each bucket.
	 */
	private int $maxAttempts;

	/**
	 * Window duration in seconds.
	 */
	private int $decaySeconds;

	/**
	 * Underlying storage-backed limiter.
	 */
	private RateLimiter $limiter;

	/**
	 * Build the default limiter for password login attempts.
	 */
	public static function login(): self {

		return new self(
			(bool)Env::get('PAIR_AUTH_RATE_LIMIT_ENABLED'),
			max(1, intval(Env::get('PAIR_AUTH_RATE_LIMIT_MAX_ATTEMPTS') ?? 10)),
			max(1, intval(Env::get('PAIR_AUTH_RATE_LIMIT_DECAY_SECONDS') ?? 900))
		);

	}

	/**
	 * Create an auth-attempt limiter with explicit settings.
	 */
	public function __construct(bool $enabled = true, int $maxAttempts = 10, int $decaySeconds = 900, ?RateLimiter $limiter = null) {

		$this->enabled = $enabled;
		$this->maxAttempts = max(1, $maxAttempts);
		$this->decaySeconds = max(1, $decaySeconds);
		$this->limiter = $limiter ?? new RateLimiter($this->maxAttempts, $this->decaySeconds);

	}

	/**
	 * Record an authentication attempt and return the stricter rate-limit decision.
	 */
	public function attempt(string $scope, string $identifier, ?string $ipAddress = null): RateLimitResult {

		if (!$this->enabled) {
			return $this->allowedResult('disabled');
		}

		$ipResult = $this->limiter->attempt($this->ipKey($scope, $ipAddress));

		if (!$ipResult->allowed) {
			return $ipResult;
		}

		// Track the identifier separately to slow distributed attacks against one account.
		$identifierResult = $this->limiter->attempt($this->identifierKey($scope, $identifier));

		return $identifierResult->allowed ? $this->stricterResult($ipResult, $identifierResult) : $identifierResult;

	}

	/**
	 * Clear successful authentication attempts for the matching identifier and address buckets.
	 */
	public function clear(string $scope, string $identifier, ?string $ipAddress = null): void {

		if (!$this->enabled) {
			return;
		}

		$this->limiter->clear($this->ipKey($scope, $ipAddress));
		$this->limiter->clear($this->identifierKey($scope, $identifier));

	}

	/**
	 * Build a storage key for the client-address bucket.
	 */
	private function ipKey(string $scope, ?string $ipAddress): string {

		$ipAddress = trim((string)$ipAddress);

		if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
			$ipAddress = '0.0.0.0';
		}

		return 'auth:' . $this->normalizeScope($scope) . ':ip:' . hash('sha256', $ipAddress);

	}

	/**
	 * Build a storage key for the normalized login identifier bucket.
	 */
	private function identifierKey(string $scope, string $identifier): string {

		$identifier = mb_strtolower(trim($identifier));

		if ('' === $identifier) {
			$identifier = '<empty>';
		}

		return 'auth:' . $this->normalizeScope($scope) . ':identifier:' . hash('sha256', $identifier);

	}

	/**
	 * Normalize the logical auth scope used in storage keys.
	 */
	private function normalizeScope(string $scope): string {

		$scope = preg_replace('/[^a-z0-9_:-]+/i', '_', trim($scope)) ?: 'auth';

		return strtolower($scope);

	}

	/**
	 * Return an allowed decision without touching storage when the limiter is disabled.
	 */
	private function allowedResult(string $driver): RateLimitResult {

		return new RateLimitResult(true, $this->maxAttempts, $this->maxAttempts, time() + $this->decaySeconds, 0, $driver);

	}

	/**
	 * Return the allowed result with the least remaining quota.
	 */
	private function stricterResult(RateLimitResult $left, RateLimitResult $right): RateLimitResult {

		return $left->remaining <= $right->remaining ? $left : $right;

	}

}
