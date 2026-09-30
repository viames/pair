<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Exceptions;

use Pair\Core\Logger;
use Pair\Core\Observability;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Tests\Support\TestCase;

/**
 * Covers exception severity choices that affect external alerting.
 */
class PairExceptionTest extends TestCase {

	/**
	 * Reset the logger singleton so every test starts from isolated configuration.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->resetLogger();

	}

	/**
	 * Restore the logger singleton after each exception-severity assertion.
	 */
	protected function tearDown(): void {

		$this->resetLogger();

		parent::tearDown();

	}

	/**
	 * Verify routine CSRF session failures do not enter the error notification pipeline.
	 */
	public function testCsrfSessionFailuresAreNotLoggedAsErrors(): void {

		new PairException('CSRF token not found in session', ErrorCodes::CSRF_TOKEN_NOT_FOUND);
		new PairException('Invalid CSRF token', ErrorCodes::CSRF_TOKEN_INVALID);

		$this->assertSame([], Observability::events());

	}

	/**
	 * Clear the process-wide logger instance.
	 */
	private function resetLogger(): void {

		$property = new \ReflectionProperty(Logger::class, 'instance');
		$property->setValue(null, null);

	}

}
