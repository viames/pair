<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit API error response object through subprocess execution because send() exits.
 */
class ApiErrorResponseTest extends TestCase {

	/**
	 * Verify the response emits the standard error payload and HTTP status code.
	 */
	public function testSendOutputsStandardizedErrorPayload(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$response = new Pair\Api\ApiErrorResponse('BAD_REQUEST', 'Invalid payload', 422, [
	'detail' => 'Missing field',
]);

$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(422, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'BAD_REQUEST',
				'error' => 'Invalid payload',
				'detail' => 'Missing field',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify non-string extra keys are dropped from the emitted payload.
	 */
	public function testSendDropsNonStringExtraKeys(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$response = new Pair\Api\ApiErrorResponse('CONFLICT', 'Already processed', 409, [
	0 => 'ignored',
	'detail' => 'Duplicate idempotency key',
]);

$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(409, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'CONFLICT',
				'error' => 'Already processed',
				'detail' => 'Duplicate idempotency key',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

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
