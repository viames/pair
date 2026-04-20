<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\LegacyMvc;
use Pair\Tests\Support\TestCase;

/**
 * Covers the Pair v4 legacy MVC bridge notices.
 */
class LegacyMvcTest extends TestCase {

	/**
	 * Restore the environment after each test.
	 */
	protected function tearDown(): void {

		unset($_ENV['APP_ENV']);

		parent::tearDown();

	}

	/**
	 * Verify controller notices are emitted only once outside production.
	 */
	public function testControllerNoticeIsEmittedOnlyOnceInDevelopment(): void {

		$_ENV['APP_ENV'] = 'development';
		$messages = [];

		set_error_handler(
			static function(int $level, string $message) use (&$messages): bool {

				if ($level !== E_USER_DEPRECATED) {
					return false;
				}

				$messages[] = $message;
				return true;

			}
		);

		try {
			LegacyMvc::emitControllerDeprecation('Fixtures\\LegacyController');
			LegacyMvc::emitControllerDeprecation('Fixtures\\LegacyController');
		} finally {
			restore_error_handler();
		}

		$this->assertCount(1, $messages);
		$this->assertStringContainsString('Pair\Core\Controller', $messages[0]);
		$this->assertStringContainsString('Pair\Web\Controller', $messages[0]);

	}

	/**
	 * Verify view notices stay silent in production.
	 */
	public function testViewNoticeIsSilentInProduction(): void {

		$_ENV['APP_ENV'] = 'production';
		$messages = [];

		set_error_handler(
			static function(int $level, string $message) use (&$messages): bool {

				if ($level !== E_USER_DEPRECATED) {
					return false;
				}

				$messages[] = $message;
				return true;

			}
		);

		try {
			LegacyMvc::emitViewDeprecation('Fixtures\\LegacyView');
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $messages);

	}

}
