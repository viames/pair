<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Exceptions;

use Pair\Core\Logger;
use Pair\Core\Observability;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
	 * Cover lifecycle failures and nearby validation errors that must still alert.
	 */
	public static function passkeyFailures(): array {

		return [
			'missing' => ['Passkey challenge not found', ErrorCodes::INVALID_REQUEST, null, 'notice'],
			'expired' => ['Passkey challenge expired', ErrorCodes::INVALID_REQUEST, null, 'notice'],
			'user mismatch' => ['Passkey challenge user mismatch', ErrorCodes::VALIDATION_FAILED, null, 'error'],
			'other request error' => ['Invalid request', ErrorCodes::INVALID_REQUEST, null, 'error'],
			'different code' => ['Passkey challenge not found', ErrorCodes::VALIDATION_FAILED, null, 'error'],
			'underlying error' => ['Passkey challenge not found', ErrorCodes::INVALID_REQUEST, 'Session storage failed', 'error'],
		];

	}

	/**
	 * Verify only routine passkey lifecycle failures bypass external error alerts.
	 */
	#[DataProvider('passkeyFailures')]
	public function testPasskeySeverity(string $message, int $code, ?string $previousMessage, string $level): void {

		$logger = $this->createMock(Logger::class);
		$logger->expects($this->once())->method($level)->with($previousMessage ?? $message, ['errorCode' => $code]);
		$logger->expects($this->never())->method('notice' === $level ? 'error' : 'notice');
		(new \ReflectionProperty(Logger::class, 'instance'))->setValue(null, $logger);

		$previous = null === $previousMessage ? null : new \RuntimeException($previousMessage);
		$exception = new PairException($message, $code, $previous);

		$this->assertSame($message, $exception->getMessage());
		$this->assertSame($code, $exception->getCode());
		$this->assertSame($previous, $exception->getPrevious());

	}

	/**
	 * Clear the process-wide logger instance.
	 */
	private function resetLogger(): void {

		$property = new \ReflectionProperty(Logger::class, 'instance');
		$property->setValue(null, null);

	}

}
