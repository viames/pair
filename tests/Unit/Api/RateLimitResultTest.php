<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\RateLimitResult;
use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit rate-limit result contract and the response headers it emits.
 */
class RateLimitResultTest extends TestCase {

	/**
	 * Verify negative retry-after values are normalized to zero at construction time.
	 */
	public function testConstructorClampsNegativeRetryAfter(): void {

		$result = new RateLimitResult(true, 60, 59, 1700000000, -15, 'file');

		$this->assertSame(0, $result->retryAfter);
		$this->assertSame('file', $result->driver);

	}

	/**
	 * Verify allowed decisions emit the standard rate-limit headers without Retry-After.
	 */
	public function testApplyHeadersForAllowedDecisionOmitsRetryAfter(): void {

		$result = $this->runRateLimitResultSnippet(implode("\n", [
			'$result = new \Pair\Api\RateLimitResult(true, 60, 41, 1700000123, 9, \'file\');',
			'$result->applyHeaders();',
		]));

		$this->assertSame(0, $result['exitCode']);

		$payload = $this->decodeRuntimePayload($result['stdout']);

		$this->assertSame('60', $payload['headers']['x-ratelimit-limit'] ?? null);
		$this->assertSame('41', $payload['headers']['x-ratelimit-remaining'] ?? null);
		$this->assertSame('1700000123', $payload['headers']['x-ratelimit-reset'] ?? null);
		$this->assertArrayNotHasKey('retry-after', $payload['headers']);

	}

	/**
	 * Verify blocked decisions also emit Retry-After along with the standard rate-limit headers.
	 */
	public function testApplyHeadersForBlockedDecisionIncludesRetryAfter(): void {

		$result = $this->runRateLimitResultSnippet(implode("\n", [
			'$result = new \Pair\Api\RateLimitResult(false, 60, 0, 1700000999, 27, \'redis\');',
			'$result->applyHeaders();',
		]));

		$this->assertSame(0, $result['exitCode']);

		$payload = $this->decodeRuntimePayload($result['stdout']);

		$this->assertSame('60', $payload['headers']['x-ratelimit-limit'] ?? null);
		$this->assertSame('0', $payload['headers']['x-ratelimit-remaining'] ?? null);
		$this->assertSame('1700000999', $payload['headers']['x-ratelimit-reset'] ?? null);
		$this->assertSame('27', $payload['headers']['retry-after'] ?? null);

	}

	/**
	 * Execute a PHP snippet with a namespace-level header stub so emitted rate-limit headers can be asserted.
	 *
	 * @param	string	$body	PHP statements that exercise the rate-limit result.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runRateLimitResultSnippet(string $body): array {

		$scriptPath = tempnam(sys_get_temp_dir(), 'pair-rate-limit-result-');

		if (false === $scriptPath) {
			$this->fail('Unable to allocate a temporary PHP script for the rate-limit result test.');
		}

		$script = implode("\n", [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'namespace Pair\Api {',
			'	/**',
			'	 * Runtime probe used to capture headers emitted by RateLimitResult during subprocess tests.',
			'	 */',
			'	final class RateLimitResultTestRuntime {',
			'',
			'		/**',
			'		 * Captured response headers keyed by lowercase header name.',
			'		 *',
			'		 * @var	array<string, string>',
			'		 */',
			'		public static array $headers = [];',
			'',
			'	}',
			'',
			'	/**',
			'	 * Intercept header() calls issued by RateLimitResult.',
			'	 */',
			'	function header(string $header, bool $replace = true, int $responseCode = 0): void {',
			'',
			'		$parts = explode(\':\', $header, 2);',
			'		$name = strtolower(trim($parts[0]));',
			'		$value = isset($parts[1]) ? trim($parts[1]) : \'\';',
			'',
			'		if (!$replace and array_key_exists($name, RateLimitResultTestRuntime::$headers)) {',
			'			RateLimitResultTestRuntime::$headers[$name] .= \', \' . $value;',
			'		} else {',
			'			RateLimitResultTestRuntime::$headers[$name] = $value;',
			'		}',
			'',
			'	}',
			'}',
			'',
			'namespace {',
			'	require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';',
			'',
			'	register_shutdown_function(function (): void {',
			'		print json_encode([',
			'			\'headers\' => \Pair\Api\RateLimitResultTestRuntime::$headers,',
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
			$this->fail('Unable to start the PHP subprocess for the rate-limit result test.');
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
	 * Decode the JSON payload emitted by the subprocess runtime probe.
	 *
	 * @param	string	$stdout	Standard output captured from the subprocess.
	 * @return	array{headers: array<string, string>}
	 */
	private function decodeRuntimePayload(string $stdout): array {

		$decoded = json_decode($stdout, true);

		if (!is_array($decoded)) {
			$this->fail('The rate-limit result subprocess did not emit valid JSON. STDOUT was: ' . $stdout);
		}

		return $decoded;

	}

}
