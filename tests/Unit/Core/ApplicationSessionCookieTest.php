<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Covers app-scoped native PHP session cookie configuration.
 */
class ApplicationSessionCookieTest extends TestCase {

	/**
	 * Prepare a minimal web request fixture for session cookie assertions.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->writeEnvFile(implode("\n", [
			'APP_NAME="Pair Test Application"',
			'APP_ENV=development',
			'DB_UTF8=false',
		]));
		Env::load();

	}

	/**
	 * Verify native session cookies use an app-specific name and the current app URL path.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testSessionCookieUsesAppScopedNameAndPath(): void {

		$this->defineUrlPath('/pair-test');

		$this->assertSame('PairTestApplicationSession', Application::getSessionCookieName());
		$this->assertSame([
			'expires' => 123,
			'path' => '/pair-test',
			'samesite' => 'Lax',
			'secure' => false,
			'httponly' => true,
		], Application::getSessionCookieParams(123));

	}

	/**
	 * Verify numeric application names are normalized to valid session cookie names.
	 */
	public function testSessionCookieNameDoesNotStartWithNumber(): void {

		$this->writeEnvFile(implode("\n", [
			'APP_NAME="2026 Pair"',
			'APP_ENV=development',
			'DB_UTF8=false',
		]));
		Env::clearCache();
		Env::load();

		$this->assertSame('Pair2026PairSession', Application::getSessionCookieName());

	}

	/**
	 * Define URL_PATH for a focused session-cookie unit test.
	 */
	private function defineUrlPath(string $urlPath): void {

		if (!defined('URL_PATH')) {
			define('URL_PATH', $urlPath);
		}

	}

}
