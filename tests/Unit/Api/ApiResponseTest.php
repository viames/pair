<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Tests\Support\TestCase;

/**
 * Covers API response helpers through subprocess execution because the implementation exits.
 */
class ApiResponseTest extends TestCase {

	/**
	 * Verify built-in errors emit the expected payload and HTTP status.
	 */
	public function testErrorOutputsBuiltInPayloadAndStatusCode(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

Pair\Api\ApiResponse::error('NOT_FOUND', ['detail' => 'Missing resource']);
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(404, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'NOT_FOUND',
				'error' => 'Not found',
				'detail' => 'Missing resource',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify unknown error codes fall back to the generic internal-server-error contract.
	 */
	public function testErrorFallsBackToInternalServerErrorWhenCodeIsUnknown(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

Pair\Api\ApiResponse::error('SOMETHING_UNREGISTERED');
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(500, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'INTERNAL_SERVER_ERROR',
				'error' => 'Internal server error',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify runtime-registered custom errors override the built-in registry.
	 */
	public function testCustomErrorsTakePrecedenceOverBuiltInErrors(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

Pair\Api\ApiResponse::registerErrors([
	'NOT_FOUND' => [
		'httpCode' => 418,
		'message' => 'Teapot not found',
	],
]);

Pair\Api\ApiResponse::error('NOT_FOUND', ['context' => 'custom']);
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(418, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'NOT_FOUND',
				'error' => 'Teapot not found',
				'context' => 'custom',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify paginated responses preserve the expected data/meta envelope.
	 */
	public function testPaginatedOutputsDataAndMetaEnvelope(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

Pair\Api\ApiResponse::paginated([
	['id' => 1, 'name' => 'Alice'],
], 2, 15, 31);
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(200, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'data' => [
					['id' => 1, 'name' => 'Alice'],
				],
				'meta' => [
					'page' => 2,
					'perPage' => 15,
					'total' => 31,
					'lastPage' => 3,
				],
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify null responses keep the current framework behavior of promoting the status to 204.
	 */
	public function testRespondWithNullPromotesNoContentStatus(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

Pair\Api\ApiResponse::respond(null, 201);
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(204, $this->extractReportedStatusCode($result['stderr']));
		$this->assertSame('null', $result['stdout']);

	}

	/**
	 * Parse the HTTP status code emitted by the subprocess shutdown hook.
	 *
	 * @param	string	$stderr	Standard error captured from the subprocess.
	 */
	private function extractReportedStatusCode(string $stderr): int {

		if (!preg_match('/HTTP_CODE=(\d+)/', $stderr, $matches)) {
			$this->fail('The subprocess did not report an HTTP status code. STDERR was: ' . $stderr);
		}

		return (int)$matches[1];

	}

}
