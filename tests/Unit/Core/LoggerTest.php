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
		unset($_ENV['PAIR_LOGGER_TELEGRAM_BOT_TOKEN']);
		unset($_ENV['TELEGRAM_BOT_TOKEN']);
		Env::clearCache();
		$this->resetLoggerInstance();

	}

	/**
	 * Reset environment state after debug logging tests.
	 */
	protected function tearDown(): void {

		unset($_ENV['PAIR_LOGGER_DEBUG_ENABLED']);
		unset($_ENV['PAIR_LOGGER_TELEGRAM_BOT_TOKEN']);
		unset($_ENV['TELEGRAM_BOT_TOKEN']);
		Env::clearCache();
		$this->resetLoggerInstance();

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
	 * Verify logger Telegram notifications prefer the logger-scoped bot token.
	 */
	public function testLoggerUsesScopedTelegramBotTokenWhenConfigured(): void {

		$_ENV['PAIR_LOGGER_TELEGRAM_BOT_TOKEN'] = 'scoped-token';
		$_ENV['TELEGRAM_BOT_TOKEN'] = 'legacy-token';
		Env::clearCache();

		$this->assertSame('scoped-token', $this->telegramBotToken(Logger::getInstance()));

	}

	/**
	 * Verify existing applications can keep using the legacy generic Telegram bot token.
	 */
	public function testLoggerFallsBackToLegacyTelegramBotToken(): void {

		$_ENV['TELEGRAM_BOT_TOKEN'] = 'legacy-token';
		Env::clearCache();

		$this->assertSame('legacy-token', $this->telegramBotToken(Logger::getInstance()));

	}

	/**
	 * Verify an empty logger-scoped bot token does not block the legacy fallback.
	 */
	public function testLoggerFallsBackWhenScopedTelegramBotTokenIsEmpty(): void {

		$_ENV['PAIR_LOGGER_TELEGRAM_BOT_TOKEN'] = '   ';
		$_ENV['TELEGRAM_BOT_TOKEN'] = 'legacy-token';
		Env::clearCache();

		$this->assertSame('legacy-token', $this->telegramBotToken(Logger::getInstance()));

	}

	/**
	 * Return the private debug logging flag through reflection.
	 */
	private function debugLoggingEnabled(): bool {

		$method = new \ReflectionMethod(Logger::class, 'debugLoggingEnabled');

		return $method->invoke(null);

	}

	/**
	 * Reset the logger singleton so constructor configuration can be tested deterministically.
	 */
	private function resetLoggerInstance(): void {

		$property = new \ReflectionProperty(Logger::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Return the private Telegram bot token value configured on the logger.
	 */
	private function telegramBotToken(Logger $logger): ?string {

		$property = new \ReflectionProperty($logger, 'telegramBotToken');

		return $property->getValue($logger);

	}

}
