<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Logger;
use Pair\Core\Env;
use Pair\Tests\Support\TestCase;

/**
 * Covers logger behavior that affects request-time instrumentation overhead.
 */
class LoggerTest extends TestCase {

	/**
	 * Prepare runtime state required by LogBar event collection.
	 */
	protected function setUp(): void {

		parent::setUp();

		unset($_ENV['PAIR_LOGGER_DEBUG_ENABLED']);
		Env::clearCache();

	}

	/**
	 * Reset environment state after debug logging tests.
	 */
	protected function tearDown(): void {

		unset($_ENV['PAIR_LOGGER_DEBUG_ENABLED']);
		Env::clearCache();

		parent::tearDown();

	}

	/**
	 * Verify debug logs are disabled by default to avoid high-volume request noise.
	 */
	public function testDebugLoggingIsDisabledByDefault(): void {

		$this->assertFalse($this->debugLoggingEnabled());

	}

	/**
	 * Verify debug logs can still be enabled explicitly when deep instrumentation is needed.
	 */
	public function testDebugLogsCanBeEnabledExplicitly(): void {

		$_ENV['PAIR_LOGGER_DEBUG_ENABLED'] = true;
		Env::clearCache();

		$this->assertTrue($this->debugLoggingEnabled());

	}

	/**
	 * Return the private debug logging flag through reflection.
	 */
	private function debugLoggingEnabled(): bool {

		$method = new \ReflectionMethod(Logger::class, 'debugLoggingEnabled');

		return $method->invoke(null);

	}

}
