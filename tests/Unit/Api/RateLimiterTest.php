<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\RateLimiter;
use Pair\Tests\Support\TestCase;

/**
 * Covers the file-backed rate limiter behavior without requiring Redis.
 */
class RateLimiterTest extends TestCase {

	/**
	 * Verify the limiter blocks requests after the configured quota and can be reset.
	 */
	public function testAttemptBlocksAfterQuotaAndClearResetsTheWindow(): void {

		$limiter = new RateLimiter(2, 60);

		$first = $limiter->attempt('user-1');
		$second = $limiter->attempt('user-1');
		$third = $limiter->attempt('user-1');

		$this->assertTrue($first->allowed);
		$this->assertSame(1, $first->remaining);
		$this->assertSame('file', $first->driver);

		$this->assertTrue($second->allowed);
		$this->assertSame(0, $second->remaining);

		$this->assertFalse($third->allowed);
		$this->assertSame(0, $third->remaining);
		$this->assertGreaterThan(0, $third->retryAfter);
		$this->assertTrue($limiter->tooManyAttempts('user-1'));

		$limiter->clear('user-1');

		$this->assertFalse($limiter->tooManyAttempts('user-1'));

	}

	/**
	 * Verify legacy fixed-window records are still interpreted as active limits.
	 */
	public function testTooManyAttemptsUnderstandsLegacyFixedWindowRecords(): void {

		$key = 'legacy-user';
		$this->writeLegacyRateLimitRecord($key, [
			'count' => 2,
			'expiresAt' => time() + 30,
		]);

		$limiter = new RateLimiter(2, 60);

		$this->assertTrue($limiter->tooManyAttempts($key));

	}

	/**
	 * Write a legacy fallback storage record in the same location used by the limiter.
	 *
	 * @param	string	$key		Logical rate-limit key.
	 * @param	array	$payload	Legacy payload to persist.
	 */
	private function writeLegacyRateLimitRecord(string $key, array $payload): void {

		$directory = TEMP_PATH . 'rate_limits/';

		if (!is_dir($directory)) {
			mkdir($directory, 0777, true);
		}

		file_put_contents(
			$directory . md5($key) . '.json',
			json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
		);

	}

}
