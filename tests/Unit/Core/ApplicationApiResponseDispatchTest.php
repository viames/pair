<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit API response dispatch path handled by the Pair application runtime.
 */
class ApplicationApiResponseDispatchTest extends TestCase {

	/**
	 * Verify the API runtime enters headless mode before controller construction and sends the explicit response.
	 */
	public function testApiJsonResponseBypassesLegacyViewBootstrap(): void {

		$applicationPath = $this->createFixtureApplication();

		try {
			$result = $this->runFixtureApplication($applicationPath);
		} finally {
			$this->removeDirectory($applicationPath);
		}

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(202, $this->extractReportedStatusCode($result['stderr']));
		$this->assertSame([45], $this->extractSessionCleanupArguments($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'status' => 'ok',
				'channel' => 'passkey',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify the API runtime preserves a response returned through ApiController::runMiddleware().
	 */
	public function testApiJsonResponseReturnedFromRunMiddlewareIsDispatched(): void {

		$applicationPath = $this->createFixtureApplication(<<<'PHP'
<?php

use Pair\Api\Middleware;
use Pair\Api\Request;
use Pair\Http\JsonResponse;

/**
 * Fixture API controller used to verify middleware-returned responses in the runtime dispatch path.
 */
final class apiController extends \Pair\Api\ApiController {

	/**
	 * Register one pass-through middleware so the runtime crosses the controller pipeline.
	 */
	protected function _init(): void {

		parent::_init();
		$this->middleware(new class implements Middleware {

			/**
			 * Pass the request to the next pipeline stage without altering it.
			 *
			 * @param	Request		$request	Current API request.
			 * @param	callable	$next		Next middleware or destination.
			 */
			public function handle(Request $request, callable $next): void {

				$next($request);

			}

		});

	}

	/**
	 * Return the response object from inside runMiddleware() instead of sending it immediately.
	 */
	public function passkeyAction(): JsonResponse {

		return $this->runMiddleware(function (): JsonResponse {

			return new JsonResponse([
				'status' => 'ok',
				'channel' => 'middleware',
			], 206);

		});

	}

}
PHP);

		try {
			$result = $this->runFixtureApplication($applicationPath);
		} finally {
			$this->removeDirectory($applicationPath);
		}

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(206, $this->extractReportedStatusCode($result['stderr']));
		$this->assertSame([45], $this->extractSessionCleanupArguments($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'status' => 'ok',
				'channel' => 'middleware',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Create a minimal application tree for the API runtime fixture.
	 *
	 * @param	string|null	$controllerSource	Optional custom API controller source for one focused runtime scenario.
	 */
	private function createFixtureApplication(?string $controllerSource = null): string {

		$applicationPath = sys_get_temp_dir() . '/pair-api-runtime-' . bin2hex(random_bytes(6));

		mkdir($applicationPath . '/modules/api', 0777, true);
		mkdir($applicationPath . '/tmp', 0777, true);

		file_put_contents($applicationPath . '/.env', implode("\n", [
			'APP_NAME="Pair Test Application"',
			'APP_ENV=production',
			'DB_UTF8=false',
			'PAIR_API_RATE_LIMIT_ENABLED=false',
		]));

		if (is_null($controllerSource)) {
			$controllerSource = <<<'PHP'
<?php

use Pair\Http\JsonResponse;

/**
 * Fixture API controller used to verify explicit runtime dispatch.
 */
final class apiController extends \Pair\Api\ApiController {

	/**
	 * Return a raw JSON response for the passkey route without depending on legacy views.
	 */
	public function passkeyAction(): JsonResponse {

		return new JsonResponse([
			'status' => 'ok',
			'channel' => 'passkey',
		], 202);

	}

}
PHP;
		}

		file_put_contents(
			$applicationPath . '/modules/api/controller.php',
			$controllerSource
		);

		file_put_contents(
			$applicationPath . '/modules/api/model.php',
			<<<'PHP'
<?php

use Pair\Core\Model;

/**
 * Minimal API model used to satisfy the legacy controller bridge during tests.
 */
final class apiModel extends Model {}
PHP
		);

		return $applicationPath;

	}

	/**
	 * Execute the fixture application in a subprocess with API-specific stubs loaded before Composer autoloading.
	 *
	 * @param	string	$applicationPath	Absolute path to the temporary fixture application.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runFixtureApplication(string $applicationPath): array {

		$scriptPath = tempnam(sys_get_temp_dir(), 'pair-api-test-');

		if (false === $scriptPath) {
			$this->fail('Unable to allocate a temporary PHP script for the API runtime test.');
		}

		$script = implode("\n", [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'namespace Pair\Helpers {',
			'	/**',
			'	 * Minimal options stub used to keep the API runtime test deterministic.',
			'	 */',
			'	final class Options {',
			'',
			'		/**',
			'		 * Return the configured option value expected by the API runtime.',
			'		 */',
			'		public static function get(string $name): mixed {',
			'',
			"			return 'session_time' === \$name ? 45 : null;",
			'',
			'		}',
			'',
			'	}',
			'',
			'	/**',
			'	 * Minimal translator stub used by the legacy controller bridge during API controller construction.',
			'	 */',
			'	final class Translator {',
			'',
			'		/**',
			'		 * Singleton instance for the lightweight translator stub.',
			'		 */',
			'		private static ?self $instance = null;',
			'',
			'		/**',
			'		 * Return the lightweight translator singleton.',
			'		 */',
			'		public static function getInstance(): self {',
			'',
			'			if (is_null(self::$instance)) {',
			'				self::$instance = new self();',
			'			}',
			'',
			'			return self::$instance;',
			'',
			'		}',
			'',
			'		/**',
			'		 * Keep the module setter available for the legacy controller constructor.',
			'		 */',
			'		public function setModuleName(string $moduleName): void {}',
			'',
			'		/**',
			'		 * Return the untranslated key for any fallback path that asks for a label.',
			'		 */',
			'		public static function do(string $key, string|array|null $vars = null): string {',
			'',
			'			return $key;',
			'',
			'		}',
			'',
			'	}',
			'}',
			'',
			'namespace Pair\Models {',
			'	/**',
			'	 * Minimal session stub used to observe stale-session cleanup without touching the database.',
			'	 */',
			'	final class Session {',
			'',
			'		/**',
			'		 * Arguments received by cleanOlderThan().',
			'		 *',
			'		 * @var	list<int>',
			'		 */',
			'		public static array $cleanArguments = [];',
			'',
			'		/**',
			'		 * Record the cleanup request instead of talking to the database.',
			'		 */',
			'		public static function cleanOlderThan(int $sessionTime): void {',
			'',
			'			self::$cleanArguments[] = $sessionTime;',
			'',
			'		}',
			'',
			'	}',
			'}',
			'',
			'namespace Pair\Orm {',
			'	/**',
			'	 * Minimal database stub used by the legacy model bridge during controller construction.',
			'	 */',
			'	final class Database {',
			'',
			'		/**',
			'		 * Singleton instance for the lightweight database stub.',
			'		 */',
			'		private static ?self $instance = null;',
			'',
			'		/**',
			'		 * Return the lightweight database singleton.',
			'		 */',
			'		public static function getInstance(): self {',
			'',
			'			if (is_null(self::$instance)) {',
			'				self::$instance = new self();',
			'			}',
			'',
			'			return self::$instance;',
			'',
			'		}',
			'',
			'		/**',
			'		 * Keep the UTF-8 configurator available even if this fixture does not use it.',
			'		 */',
			'		public function setUtf8unicode(): void {}',
			'',
			'	}',
			'}',
			'',
			'namespace {',
			'	if (!defined(\'APPLICATION_PATH\')) {',
			"		define('APPLICATION_PATH', " . var_export($applicationPath, true) . ');',
			'	}',
			'',
			'	if (!defined(\'TEMP_PATH\')) {',
			"		define('TEMP_PATH', " . var_export($applicationPath . '/tmp/', true) . ');',
			'	}',
			'',
			'	if (!defined(\'PAIR_FOLDER\')) {',
			"		define('PAIR_FOLDER', 'pair');",
			'	}',
			'',
			'	if (!defined(\'BASE_TIMEZONE\')) {',
			"		define('BASE_TIMEZONE', 'UTC');",
			'	}',
			'',
			'	if (!defined(\'URL_PATH\')) {',
			"		define('URL_PATH', null);",
			'	}',
			'',
			'	if (!defined(\'BASE_HREF\')) {',
			"		define('BASE_HREF', null);",
			'	}',
			'',
			'	require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';',
			'	\Pair\Core\Env::load();',
			'',
			'	$_SERVER[\'REQUEST_METHOD\'] = \'POST\';',
			'	$_SERVER[\'SCRIPT_NAME\'] = \'/public/index.php\';',
			'	$_SERVER[\'HTTP_HOST\'] = \'pair.test\';',
			'',
			'	register_shutdown_function(function (): void {',
			'		fwrite(STDERR, \'HTTP_CODE=\' . http_response_code() . PHP_EOL);',
			'		fwrite(STDERR, \'SESSION_CLEAN_ARGUMENTS=\' . json_encode(\Pair\Models\Session::$cleanArguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);',
			'	});',
			'',
			'	$app = \Pair\Core\Application::getInstance();',
			'	$router = \Pair\Core\Router::getInstance();',
			'',
			'	// CLI tests do not parse HTTP URLs, so set the runtime route explicitly.',
			'	$router->setModule(\'api\');',
			'	$router->action = \'passkey\';',
			'	$router->vars = [];',
			'	$router->ajax = false;',
			'	$router->raw = false;',
			'',
			'	$app->run();',
			'}',
			'',
		]);

		file_put_contents($scriptPath, $script);

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open([PHP_BINARY, $scriptPath], $descriptors, $pipes, dirname(__DIR__, 3));

		if (!is_resource($process)) {
			unlink($scriptPath);
			$this->fail('Unable to start the PHP subprocess for the API runtime test.');
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);

		unlink($scriptPath);

		return [
			'stdout' => is_string($stdout) ? $stdout : '',
			'stderr' => is_string($stderr) ? $stderr : '',
			'exitCode' => $exitCode,
		];

	}

	/**
	 * Parse the reported HTTP status code emitted by the subprocess shutdown hook.
	 *
	 * @param	string	$stderr	Standard error captured from the subprocess.
	 */
	private function extractReportedStatusCode(string $stderr): int {

		if (!preg_match('/HTTP_CODE=(\d+)/', $stderr, $matches)) {
			$this->fail('The subprocess did not report an HTTP status code. STDERR was: ' . $stderr);
		}

		return (int)$matches[1];

	}

	/**
	 * Parse the session-cleanup arguments emitted by the subprocess shutdown hook.
	 *
	 * @param	string	$stderr	Standard error captured from the subprocess.
	 * @return	list<int>
	 */
	private function extractSessionCleanupArguments(string $stderr): array {

		if (!preg_match('/SESSION_CLEAN_ARGUMENTS=(.+)/', $stderr, $matches)) {
			$this->fail('The subprocess did not report session cleanup arguments. STDERR was: ' . $stderr);
		}

		$decoded = json_decode($matches[1], true);

		if (!is_array($decoded)) {
			$this->fail('The subprocess reported invalid session cleanup arguments. STDERR was: ' . $stderr);
		}

		return array_map('intval', $decoded);

	}

}
