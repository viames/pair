<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit Pair v4 JSON response object through subprocess execution.
 */
class JsonResponseTest extends TestCase {

	/**
	 * Verify read-model payloads are normalized into plain arrays before output.
	 */
	public function testSendNormalizesReadModelPayload(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$response = new Pair\Http\JsonResponse(
	Pair\Data\Payload::fromArray([
		'id' => 7,
		'name' => 'Alice',
	]),
	202
);

$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(202, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'id' => 7,
				'name' => 'Alice',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify stdClass payloads are passed through unchanged.
	 */
	public function testSendPreservesStdClassPayload(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$payload = new stdClass();
$payload->status = 'ok';
$payload->count = 2;

$response = new Pair\Http\JsonResponse($payload, 200);
$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(200, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'status' => 'ok',
				'count' => 2,
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify null payloads keep the legacy no-content status promotion.
	 */
	public function testSendWithNullPayloadPromotesNoContentStatus(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$response = new Pair\Http\JsonResponse(null, 201);
$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(204, $this->extractReportedStatusCode($result['stderr']));
		$this->assertSame('null', $result['stdout']);

	}

	/**
	 * Verify scalar payloads can be emitted by explicit JSON responses.
	 */
	public function testSendSupportsScalarPayloads(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$response = new Pair\Http\JsonResponse('stored', 200);
$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(200, $this->extractReportedStatusCode($result['stderr']));
		$this->assertSame('"stored"', $result['stdout']);

	}

	/**
	 * Verify send() does not terminate the current process on the explicit v4 response path.
	 */
	public function testSendDoesNotTerminateExecution(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
$response = new Pair\Http\JsonResponse(['ok' => true], 200);
$response->send();
print "\nAFTER_SEND";
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertStringEndsWith("\nAFTER_SEND", $result['stdout']);

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
