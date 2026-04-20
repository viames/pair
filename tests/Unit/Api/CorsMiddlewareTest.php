<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Tests\Support\TestCase;

/**
 * Covers the CORS middleware through subprocess execution so header calls can be intercepted safely.
 */
class CorsMiddlewareTest extends TestCase {

	/**
	 * Verify normal requests keep the middleware chain running and emit the configured CORS headers.
	 */
	public function testHandleForStandardRequestSetsConfiguredHeadersAndContinues(): void {

		$result = $this->runCorsMiddlewareSnippet(implode("\n", [
			'$_SERVER[\'REQUEST_METHOD\'] = \'GET\';',
			'$_SERVER[\'HTTP_ORIGIN\'] = \'https://app.example.test\';',
			'',
			'$middleware = new \Pair\Api\CorsMiddleware([',
			"\t'allowedOrigins' => ['https://app.example.test'],",
			"\t'allowedMethods' => ['GET', 'POST'],",
			"\t'allowedHeaders' => ['Content-Type', 'Authorization'],",
			"\t'maxAge' => 600,",
			']);',
			'$request = new \Pair\Api\Request();',
			'$middleware->handle($request, function (\Pair\Api\Request $request): void {',
			"\t\\Pair\\Api\\CorsMiddlewareTestRuntime::markNextCalled();",
			'});',
		]));

		$this->assertSame(0, $result['exitCode']);

		$payload = $this->decodeRuntimePayload($result['stdout']);

		$this->assertSame(200, $payload['status']);
		$this->assertTrue($payload['nextCalled']);
		$this->assertSame('https://app.example.test', $payload['headers']['access-control-allow-origin'] ?? null);
		$this->assertSame('Origin', $payload['headers']['vary'] ?? null);
		$this->assertSame('GET, POST', $payload['headers']['access-control-allow-methods'] ?? null);
		$this->assertSame('Content-Type, Authorization', $payload['headers']['access-control-allow-headers'] ?? null);
		$this->assertSame('600', $payload['headers']['access-control-max-age'] ?? null);

	}

	/**
	 * Verify OPTIONS preflight requests short-circuit with 204 while still emitting the configured headers.
	 */
	public function testHandleForOptionsRequestShortCircuitsWithNoContent(): void {

		$result = $this->runCorsMiddlewareSnippet(implode("\n", [
			'$_SERVER[\'REQUEST_METHOD\'] = \'OPTIONS\';',
			'$_SERVER[\'HTTP_ORIGIN\'] = \'https://admin.example.test\';',
			'',
			'$middleware = new \Pair\Api\CorsMiddleware([',
			"\t'allowedOrigins' => ['*'],",
			"\t'allowedMethods' => ['GET', 'OPTIONS'],",
			"\t'allowedHeaders' => ['Content-Type', 'X-Requested-With'],",
			"\t'maxAge' => 1200,",
			']);',
			'$request = new \Pair\Api\Request();',
			'$middleware->handle($request, function (\Pair\Api\Request $request): void {',
			"\t\\Pair\\Api\\CorsMiddlewareTestRuntime::markNextCalled();",
			'});',
		]));

		$this->assertSame(0, $result['exitCode']);

		$payload = $this->decodeRuntimePayload($result['stdout']);

		$this->assertSame(204, $payload['status']);
		$this->assertFalse($payload['nextCalled']);
		$this->assertSame('*', $payload['headers']['access-control-allow-origin'] ?? null);
		$this->assertArrayNotHasKey('vary', $payload['headers']);
		$this->assertSame('GET, OPTIONS', $payload['headers']['access-control-allow-methods'] ?? null);
		$this->assertSame('Content-Type, X-Requested-With', $payload['headers']['access-control-allow-headers'] ?? null);
		$this->assertSame('1200', $payload['headers']['access-control-max-age'] ?? null);

	}

	/**
	 * Execute a PHP snippet with namespace-level header stubs so the middleware output can be asserted.
	 *
	 * @param	string	$body	PHP statements that exercise the middleware.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runCorsMiddlewareSnippet(string $body): array {

		$scriptPath = tempnam(sys_get_temp_dir(), 'pair-cors-test-');

		if (false === $scriptPath) {
			$this->fail('Unable to allocate a temporary PHP script for the CORS middleware test.');
		}

		$script = implode("\n", [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'namespace Pair\Api {',
			'	/**',
			'	 * Runtime probe used to capture middleware headers and control flow during subprocess tests.',
			'	 */',
			'	final class CorsMiddlewareTestRuntime {',
			'',
			'		/**',
			'		 * Captured response headers keyed by lowercase header name.',
			'		 *',
			'		 * @var	array<string, string>',
			'		 */',
			'		public static array $headers = [];',
			'',
			'		/**',
			'		 * Captured status code.',
			'		 */',
			'		public static int $status = 200;',
			'',
			'		/**',
			'		 * Whether the destination callable was reached.',
			'		 */',
			'		public static bool $nextCalled = false;',
			'',
			'		/**',
			'		 * Mark the destination callable as reached.',
			'		 */',
			'		public static function markNextCalled(): void {',
			'',
			'			self::$nextCalled = true;',
			'',
			'		}',
			'',
			'	}',
			'',
			'	/**',
			'	 * Intercept header() calls issued by the middleware under test.',
			'	 */',
			'	function header(string $header, bool $replace = true, int $responseCode = 0): void {',
			'',
			'		$parts = explode(\':\', $header, 2);',
			'		$name = strtolower(trim($parts[0]));',
			'		$value = isset($parts[1]) ? trim($parts[1]) : \'\';',
			'',
			'		if (!$replace and array_key_exists($name, CorsMiddlewareTestRuntime::$headers)) {',
			'			CorsMiddlewareTestRuntime::$headers[$name] .= \', \' . $value;',
			'		} else {',
			'			CorsMiddlewareTestRuntime::$headers[$name] = $value;',
			'		}',
			'',
			'		if ($responseCode > 0) {',
			'			CorsMiddlewareTestRuntime::$status = $responseCode;',
			'		}',
			'',
			'	}',
			'',
			'	/**',
			'	 * Intercept http_response_code() calls issued by the middleware under test.',
			'	 */',
			'	function http_response_code(?int $responseCode = null): int {',
			'',
			'		if (!is_null($responseCode)) {',
			'			CorsMiddlewareTestRuntime::$status = $responseCode;',
			'		}',
			'',
			'		return CorsMiddlewareTestRuntime::$status;',
			'',
			'	}',
			'}',
			'',
			'namespace {',
			'	require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';',
			'',
			'	register_shutdown_function(function (): void {',
			'		print json_encode([',
			'			\'headers\' => \Pair\Api\CorsMiddlewareTestRuntime::$headers,',
			'			\'status\' => \Pair\Api\CorsMiddlewareTestRuntime::$status,',
			'			\'nextCalled\' => \Pair\Api\CorsMiddlewareTestRuntime::$nextCalled,',
			'		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);',
			'	});',
			'',
			$body,
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
			$this->fail('Unable to start the PHP subprocess for the CORS middleware test.');
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
	 * Decode the JSON payload emitted by the middleware subprocess.
	 *
	 * @param	string	$stdout	Standard output captured from the subprocess.
	 * @return	array{headers: array<string, string>, status: int, nextCalled: bool}
	 */
	private function decodeRuntimePayload(string $stdout): array {

		$decoded = json_decode($stdout, true);

		if (!is_array($decoded)) {
			$this->fail('The CORS middleware subprocess did not emit valid JSON. STDOUT was: ' . $stdout);
		}

		return $decoded;

	}

}
