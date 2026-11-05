<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers browser navigation and data request classification for expired web sessions.
 */
final class ApplicationSessionResponseTest extends TestCase {

	/**
	 * Provide navigation metadata and legacy HTML/JSON response preferences.
	 *
	 * @return array<string, array{array<string, string>, bool}>
	 */
	public static function requestProvider(): array {

		$navigation = ['HTTP_SEC_FETCH_DEST' => 'document', 'HTTP_SEC_FETCH_MODE' => 'navigate'];
		$fetch = ['HTTP_SEC_FETCH_DEST' => 'empty', 'HTTP_SEC_FETCH_MODE' => 'cors'];

		return [
			'HTML document' => [$navigation + ['HTTP_ACCEPT' => 'text/html'], false],
			'document with JSON Accept' => [$navigation + ['HTTP_ACCEPT' => 'application/json'], false],
			'document with mixed Accept' => [$navigation + ['HTTP_ACCEPT' => 'text/html,application/json'], false],
			'document with XHR header' => [$navigation + ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], false],
			'document with PairRouter header' => [$navigation + ['HTTP_X_REQUESTED_WITH' => 'PairRouter'], false],
			'document with JSON body' => [$navigation + ['CONTENT_TYPE' => 'application/json'], false],
			'navigation without destination' => [['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_ACCEPT' => 'application/json'], false],
			'document without mode' => [['HTTP_SEC_FETCH_DEST' => 'document', 'HTTP_ACCEPT' => 'application/json'], false],
			'iframe' => [['HTTP_SEC_FETCH_DEST' => 'iframe', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], false],
			'frame' => [['HTTP_SEC_FETCH_DEST' => 'frame', 'HTTP_ACCEPT' => 'application/json'], false],
			'fetch JSON' => [$fetch + ['HTTP_ACCEPT' => 'application/json'], true],
			'fetch HTML' => [$fetch + ['HTTP_ACCEPT' => 'text/html'], true],
			'fetch same-origin' => [['HTTP_SEC_FETCH_DEST' => 'empty', 'HTTP_SEC_FETCH_MODE' => 'same-origin'], true],
			'XHR legacy' => [['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], true],
			'PairRouter legacy' => [['HTTP_X_REQUESTED_WITH' => 'PairRouter'], true],
			'legacy JSON client' => [['HTTP_ACCEPT' => 'application/json'], true],
			'Problem Details' => [['HTTP_ACCEPT' => 'application/problem+json'], true],
			'legacy JSON body' => [['CONTENT_TYPE' => 'application/json; charset=utf-8'], true],
			'legacy mixed Accept' => [['HTTP_ACCEPT' => 'text/html,application/json'], false],
			'legacy HTML preferred' => [['HTTP_ACCEPT' => 'text/html,application/json;q=0.9'], false],
			'legacy XHTML preferred' => [['HTTP_ACCEPT' => 'application/xhtml+xml,application/problem+json;q=0.9'], false],
			'legacy JSON preferred' => [['HTTP_ACCEPT' => 'text/html;q=0.8,application/json'], true],
			'legacy HTML excluded' => [['HTTP_ACCEPT' => 'text/html;q=0,application/json'], true],
			'legacy JSON excluded' => [['HTTP_ACCEPT' => 'application/json;q=0,*/*'], false],
			'legacy XHR mixed Accept' => [['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'text/html,application/json'], true],
			'fetch mixed Accept' => [$fetch + ['HTTP_ACCEPT' => 'text/html,application/json'], true],
			'JSON body with HTML Accept' => [['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'text/html'], true],
			'legacy HTML page' => [['HTTP_ACCEPT' => 'text/html,application/xhtml+xml'], false],
			'missing metadata' => [[], false],
		];

	}

	/**
	 * Verify response selection before login redirection without starting sessions or a database.
	 *
	 * @param array<string, string> $headers Simulated request headers.
	 */
	#[DataProvider('requestProvider')]
	public function testExpiredSessionResponseMatchesRequest(array $headers, bool $expectsData): void {

		$originalServer = $_SERVER;

		try {
			$_SERVER = $headers;
			$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
			$method = new \ReflectionMethod(Application::class, 'requestExpectsDataResponse');

			$this->assertSame($expectsData, $method->invoke($app));
		} finally {
			$_SERVER = $originalServer;
		}

	}

}
