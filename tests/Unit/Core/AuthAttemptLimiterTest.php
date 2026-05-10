<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\AuthAttemptLimiter;
use Pair\Tests\Support\TestCase;

/**
 * Covers authentication attempt throttling across IP and identifier buckets.
 */
class AuthAttemptLimiterTest extends TestCase {

	/**
	 * Verify one client address cannot bypass throttling by rotating identifiers.
	 */
	public function testAttemptBlocksRepeatedIpAcrossDifferentIdentifiers(): void {

		$limiter = new AuthAttemptLimiter(true, 2, 60);

		$this->assertTrue($limiter->attempt('login', 'first@example.test', '203.0.113.10')->allowed);
		$this->assertTrue($limiter->attempt('login', 'second@example.test', '203.0.113.10')->allowed);
		$this->assertFalse($limiter->attempt('login', 'third@example.test', '203.0.113.10')->allowed);

	}

	/**
	 * Verify one identifier cannot be attacked from many client addresses without throttling.
	 */
	public function testAttemptBlocksRepeatedIdentifierAcrossDifferentIps(): void {

		$limiter = new AuthAttemptLimiter(true, 2, 60);

		$this->assertTrue($limiter->attempt('login', 'TARGET@Example.Test', '203.0.113.11')->allowed);
		$this->assertTrue($limiter->attempt('login', 'target@example.test', '203.0.113.12')->allowed);
		$this->assertFalse($limiter->attempt('login', 'target@example.test', '203.0.113.13')->allowed);

	}

	/**
	 * Verify successful authentication can clear both buckets for the matching identifier and address.
	 */
	public function testClearRemovesMatchingIdentifierAndIpBuckets(): void {

		$limiter = new AuthAttemptLimiter(true, 2, 60);

		$this->assertTrue($limiter->attempt('login', 'clear@example.test', '203.0.113.14')->allowed);
		$this->assertTrue($limiter->attempt('login', 'clear@example.test', '203.0.113.14')->allowed);
		$this->assertFalse($limiter->attempt('login', 'clear@example.test', '203.0.113.14')->allowed);

		$limiter->clear('login', 'clear@example.test', '203.0.113.14');

		$this->assertTrue($limiter->attempt('login', 'clear@example.test', '203.0.113.14')->allowed);

	}

	/**
	 * Verify disabled limiters allow attempts without touching storage.
	 */
	public function testDisabledLimiterAlwaysAllowsAttempts(): void {

		$limiter = new AuthAttemptLimiter(false, 1, 60);

		$this->assertTrue($limiter->attempt('login', 'disabled@example.test', '203.0.113.15')->allowed);
		$this->assertTrue($limiter->attempt('login', 'disabled@example.test', '203.0.113.15')->allowed);

	}

}
