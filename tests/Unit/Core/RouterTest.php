<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Router;
use Pair\Tests\Support\TestCase;

/**
 * Covers focused Router behavior that is easy to regress while preserving legacy routing contracts.
 */
class RouterTest extends TestCase {

	/**
	 * Ensure the static parameter helpers are safe before the singleton is initialized.
	 */
	public function testStaticHelpersAreSafeBeforeRouterInitialization(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
$value = \Pair\Core\Router::get(0);
\Pair\Core\Router::exceedingPaginationFallback();
print json_encode(['value' => $value, 'done' => true]);
PHP);

		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertSame(['value' => null, 'done' => true], $this->decodeJson($result['stdout']));

	}

	/**
	 * Verify parsed CGI parameters are decoded once and generated URLs encode path and query values safely.
	 */
	public function testQueryParsingAndGeneratedUrlEncodingAreSafe(): void {

		$result = $this->runRouterSnippet(<<<'PHP'
$router = \Pair\Core\Router::getInstance();
$url = '/catalog/list/red%20shoes/%22%20onmouseover%3D%22alert(1)?token=abc%3D123&term=one+two&filter%5Bcolor%5D=dark+blue';
$property = new \ReflectionProperty($router, 'url');
$property->setValue($router, $url);
$router->parseRoutes();

print json_encode([
	'module' => $router->module,
	'action' => $router->action,
	'firstParam' => $router->getParam(0),
	'unsafeParam' => $router->getParam(1),
	'token' => $router->getParam('token'),
	'term' => $router->getParam('term'),
	'filter' => $router->getParam('filter'),
	'url' => $router->getUrl(),
]);
PHP);

		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertSame([
			'module' => 'catalog',
			'action' => 'list',
			'firstParam' => 'red shoes',
			'unsafeParam' => '" onmouseover="alert(1)',
			'token' => 'abc=123',
			'term' => 'one two',
			'filter' => ['color' => 'dark blue'],
			'url' => 'catalog/list/red%20shoes/%22%20onmouseover%3D%22alert%281%29?token=abc%3D123&term=one%20two&filter%5Bcolor%5D=dark%20blue',
		], $this->decodeJson($result['stdout']));

	}

	/**
	 * Verify legacy raw and ajax URL prefixes set flags and are not treated as module names.
	 */
	public function testRouteModePrefixesAreParsedBeforeStandardRoutes(): void {

		$result = $this->runRouterSnippet(<<<'PHP'
$router = \Pair\Core\Router::getInstance();
$property = new \ReflectionProperty($router, 'url');
$property->setValue($router, '/raw/ajax/report/download/noLog');
$router->parseRoutes();

print json_encode([
	'module' => $router->module,
	'action' => $router->action,
	'raw' => $router->raw,
	'ajax' => $router->ajax,
	'sendLog' => $router->sendLog(),
	'url' => $router->getUrl(),
]);
PHP);

		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertSame([
			'module' => 'report',
			'action' => 'download',
			'raw' => true,
			'ajax' => true,
			'sendLog' => false,
			'url' => 'report/download',
		], $this->decodeJson($result['stdout']));

	}

	/**
	 * Verify literal custom-route segments are escaped while placeholders still support regex constraints.
	 */
	public function testCustomRouteMatchingEscapesLiteralSegments(): void {

		$result = $this->runRouterSnippet(<<<'PHP'
$router = \Pair\Core\Router::getInstance();

print json_encode([
	'literalMatch' => $router->routePathMatchesUrl('/feed.xml', '/feed.xml'),
	'literalMismatch' => $router->routePathMatchesUrl('/feed.xml', '/feedXxml'),
	'prefixedRegexMatch' => $router->routePathMatchesUrl('/posts/:id(\d+)', '/ajax/posts/42'),
	'alternationRegexMatch' => $router->routePathMatchesUrl('/posts/:state(draft|published)', '/posts/draft'),
	'regexMismatch' => $router->routePathMatchesUrl('/posts/:id(\d+)', '/posts/abc'),
]);
PHP);

		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertSame([
			'literalMatch' => true,
			'literalMismatch' => false,
			'prefixedRegexMatch' => true,
			'alternationRegexMatch' => true,
			'regexMismatch' => false,
		], $this->decodeJson($result['stdout']));

	}

	/**
	 * Run a Router-focused PHP snippet with the minimal constants required by the singleton constructor.
	 *
	 * @param	string	$body	PHP statements to execute after Router constants are prepared.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runRouterSnippet(string $body): array {

		return $this->runPhpSnippet(<<<PHP
if (!defined('URL_PATH')) {
	define('URL_PATH', null);
}

\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REQUEST_URI'] = '/';

$body
PHP);

	}

	/**
	 * Decode a JSON string into an associative array for assertions.
	 *
	 * @return	array<string, mixed>
	 */
	private function decodeJson(string $json): array {

		$decoded = json_decode($json, true);

		$this->assertIsArray($decoded, $json);

		return $decoded;

	}

}
