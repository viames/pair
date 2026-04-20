<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit plain-text response object used by non-JSON API endpoints.
 */
class TextResponseTest extends TestCase {

	/**
	 * Verify the response sends the configured text body and HTTP status code.
	 */
	public function testSendOutputsTextBodyAndStatusCode(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$response = new Pair\Http\TextResponse('challenge-token', 202);
$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(202, $this->extractReportedStatusCode($result['stderr']));
		$this->assertSame('challenge-token', $result['stdout']);

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
