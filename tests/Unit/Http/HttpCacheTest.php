<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Http\EmptyResponse;
use Pair\Http\HttpCache;
use Pair\Http\JsonResponse;
use Pair\Tests\Support\TestCase;

/**
 * Covers explicit HTTP cache helpers for read responses.
 */
class HttpCacheTest extends TestCase {

	/**
	 * Verify Cache-Control values are built from explicit directives.
	 */
	public function testCacheControlBuildsDirectiveString(): void {

		$this->assertSame('public, max-age=300', HttpCache::cacheControl(300));
		$this->assertSame('private, max-age=0, must-revalidate', HttpCache::cacheControl(-1, 'private', true));
		$this->assertSame('no-store', HttpCache::cacheControl(300, 'no-store'));

	}

	/**
	 * Verify ETags are deterministic and safely quoted.
	 */
	public function testEtagBuildsDeterministicValidators(): void {

		$etag = HttpCache::etag(['id' => 7, 'name' => 'Alice']);

		$this->assertSame($etag, HttpCache::etag(['id' => 7, 'name' => 'Alice']));
		$this->assertStringStartsWith('"', $etag);
		$this->assertStringEndsWith('"', $etag);
		$this->assertStringStartsWith('W/"', HttpCache::etag(['id' => 7], true));

	}

	/**
	 * Verify Last-Modified values are normalized to GMT HTTP-date strings.
	 */
	public function testLastModifiedFormatsHttpDate(): void {

		$this->assertSame('Tue, 21 Apr 2026 10:30:00 GMT', HttpCache::lastModified(1776767400));

	}

	/**
	 * Verify request validators match explicit ETags.
	 */
	public function testIsNotModifiedMatchesEtag(): void {

		$etag = '"abc123"';

		$this->assertTrue(HttpCache::isNotModified($etag, null, [
			'REQUEST_METHOD' => 'GET',
			'HTTP_IF_NONE_MATCH' => 'W/"abc123", "other"',
		]));
		$this->assertFalse(HttpCache::isNotModified($etag, null, [
			'REQUEST_METHOD' => 'GET',
			'HTTP_IF_NONE_MATCH' => '"other"',
		]));
		$this->assertFalse(HttpCache::isNotModified($etag, null, [
			'REQUEST_METHOD' => 'POST',
			'HTTP_IF_NONE_MATCH' => '"abc123"',
		]));

	}

	/**
	 * Verify Last-Modified fallback is used when no ETag validator is present.
	 */
	public function testIsNotModifiedMatchesLastModified(): void {

		$this->assertTrue(HttpCache::isNotModified(null, 1776767400, [
			'REQUEST_METHOD' => 'GET',
			'HTTP_IF_MODIFIED_SINCE' => 'Tue, 21 Apr 2026 10:31:00 GMT',
		]));
		$this->assertFalse(HttpCache::isNotModified(null, 1776767400, [
			'REQUEST_METHOD' => 'GET',
			'HTTP_IF_MODIFIED_SINCE' => 'Tue, 21 Apr 2026 10:29:00 GMT',
		]));

	}

	/**
	 * Verify conditional JSON responses return 304 when validators match.
	 */
	public function testJsonReturnsNotModifiedResponseWhenValidatorsMatch(): void {

		$response = HttpCache::json(
			['id' => 7],
			200,
			'"abc123"',
			1776767400,
			HttpCache::cacheControl(60),
			[
				'REQUEST_METHOD' => 'GET',
				'HTTP_IF_NONE_MATCH' => '"abc123"',
			]
		);

		$this->assertInstanceOf(EmptyResponse::class, $response);
		$this->assertSame(304, $this->readProperty($response, 'httpCode'));
		$this->assertSame([
			'ETag' => '"abc123"',
			'Last-Modified' => 'Tue, 21 Apr 2026 10:30:00 GMT',
			'Cache-Control' => 'public, max-age=60',
		], $this->readProperty($response, 'headers'));

	}

	/**
	 * Verify conditional JSON responses fall back to normal payload responses when validators differ.
	 */
	public function testJsonReturnsPayloadResponseWhenValidatorsDiffer(): void {

		$response = HttpCache::json(
			['id' => 7],
			200,
			'"abc123"',
			null,
			HttpCache::cacheControl(60),
			[
				'REQUEST_METHOD' => 'GET',
				'HTTP_IF_NONE_MATCH' => '"different"',
			]
		);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(['id' => 7], $this->readProperty($response, 'payload'));
		$this->assertSame([
			'ETag' => '"abc123"',
			'Cache-Control' => 'public, max-age=60',
		], $this->readProperty($response, 'headers'));

	}

	/**
	 * Verify EmptyResponse sends no response body.
	 */
	public function testEmptyResponseSendsStatusWithoutBody(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

$response = new Pair\Http\EmptyResponse(304, ['ETag' => '"abc123"']);
$response->send();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(304, $this->extractReportedStatusCode($result['stderr']));
		$this->assertSame('', $result['stdout']);

	}

	/**
	 * Read one private response property for focused assertions.
	 */
	private function readProperty(object $object, string $name): mixed {

		$property = new \ReflectionProperty($object, $name);

		return $property->getValue($object);

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
