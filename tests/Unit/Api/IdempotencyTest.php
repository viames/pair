<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiErrorResponse;
use Pair\Api\Idempotency;
use Pair\Api\Request;
use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;
use Pair\Tests\Support\TestCase;

/**
 * Covers file-backed idempotency behavior for API endpoints.
 */
class IdempotencyTest extends TestCase {

	/**
	 * Clean the idempotency storage before each test.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->removeDirectory(TEMP_PATH . 'idempotency');

	}

	/**
	 * Clean the idempotency storage after each test.
	 */
	protected function tearDown(): void {

		$this->removeDirectory(TEMP_PATH . 'idempotency');

		parent::tearDown();

	}

	/**
	 * Verify duplicateResponse() returns null for a new key and creates the processing row.
	 */
	public function testDuplicateResponseCreatesProcessingRowAndReturnsNullForNewKey(): void {

		$request = $this->newRequest(
			method: 'POST',
			uri: '/api/orders',
			rawBody: '{"amount":10}',
			idempotencyKey: 'order-create-explicit-1'
		);

		$response = Idempotency::duplicateResponse($request, 'orders', 90);
		$rows = $this->readIdempotencyRows();

		$this->assertNull($response);
		$this->assertCount(1, $rows);
		$this->assertSame('processing', $rows[0]['status'] ?? null);

	}

	/**
	 * Verify duplicateResponse() returns an explicit replay response with the stored payload and HTTP status.
	 */
	public function testDuplicateResponseReturnsExplicitReplayResponse(): void {

		$request = $this->newRequest(
			method: 'POST',
			uri: '/api/orders',
			rawBody: '{"amount":15}',
			idempotencyKey: 'order-create-explicit-2'
		);

		Idempotency::storeResponse($request, 'orders', [
			'id' => 10,
			'status' => 'stored',
		], 201, 300);

		$response = Idempotency::duplicateResponse($request, 'orders');

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame([
			'id' => 10,
			'status' => 'stored',
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(201, $this->readJsonResponseProperty($response, 'httpCode'));

	}

	/**
	 * Verify duplicateResponse() still returns a replay response object when the cached payload is scalar JSON.
	 */
	public function testDuplicateResponseSupportsScalarReplayPayloads(): void {

		$request = $this->newRequest(
			method: 'POST',
			uri: '/api/orders',
			rawBody: '{"amount":16}',
			idempotencyKey: 'order-create-explicit-4'
		);

		Idempotency::storeResponse($request, 'orders', 'stored', 200, 300);

		$response = Idempotency::duplicateResponse($request, 'orders');

		$this->assertInstanceOf(ResponseInterface::class, $response);
		$this->assertNotInstanceOf(JsonResponse::class, $response);
		$this->assertNotInstanceOf(ApiErrorResponse::class, $response);

	}

	/**
	 * Verify duplicateResponse() returns an explicit conflict response when the same key is reused with a different payload.
	 */
	public function testDuplicateResponseReturnsExplicitConflictResponseForDifferentPayload(): void {

		$initial = $this->newRequest(
			method: 'POST',
			uri: '/api/orders',
			rawBody: '{"amount":15}',
			idempotencyKey: 'order-create-explicit-3'
		);

		Idempotency::storeResponse($initial, 'orders', [
			'id' => 11,
		], 201, 300);

		$duplicate = $this->newRequest(
			method: 'POST',
			uri: '/api/orders',
			rawBody: '{"amount":99}',
			idempotencyKey: 'order-create-explicit-3'
		);

		$response = Idempotency::duplicateResponse($duplicate, 'orders');

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('CONFLICT', $this->readPrivateProperty($response, ApiErrorResponse::class, 'errorCode'));
		$this->assertSame(409, $this->readPrivateProperty($response, ApiErrorResponse::class, 'httpCode'));

	}

	/**
	 * Verify requests without an idempotency key continue normally and do not create storage rows.
	 */
	public function testRespondIfDuplicateWithoutKeySkipsStorage(): void {

		$request = $this->newRequest(
			method: 'POST',
			uri: '/api/orders',
			rawBody: '{"amount":10}',
			idempotencyKey: null
		);

		$this->assertTrue(Idempotency::respondIfDuplicate($request, 'orders'));
		$this->assertSame([], $this->readIdempotencyRows());

	}

	/**
	 * Verify a processing row can be created and then removed explicitly.
	 */
	public function testRespondIfDuplicateCreatesProcessingRowAndClearProcessingRemovesIt(): void {

		$request = $this->newRequest(
			method: 'POST',
			uri: '/api/orders',
			rawBody: '{"amount":10}',
			idempotencyKey: 'order-create-1'
		);

		$this->assertTrue(Idempotency::respondIfDuplicate($request, 'orders', 90));

		$rows = $this->readIdempotencyRows();

		$this->assertCount(1, $rows);
		$this->assertSame('processing', $rows[0]['status'] ?? null);
		$this->assertSame('orders', $rows[0]['scope'] ?? null);
		$this->assertSame('order-create-1', $rows[0]['key'] ?? null);

		$this->assertTrue(Idempotency::clearProcessing($request, 'orders'));
		$this->assertSame([], $this->readIdempotencyRows());

	}

	/**
	 * Verify duplicate requests replay the stored response payload and HTTP status.
	 */
	public function testRespondIfDuplicateReplaysStoredResponse(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/orders';
$_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'order-create-2';

$request = new Pair\Api\Request();
$reflection = new ReflectionProperty(Pair\Api\Request::class, 'rawBody');
$reflection->setValue($request, '{"amount":15}');

Pair\Api\Idempotency::storeResponse($request, 'orders', [
	'id' => 10,
	'status' => 'stored',
], 201, 300);

register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

Pair\Api\Idempotency::respondIfDuplicate($request, 'orders');
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(201, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'id' => 10,
				'status' => 'stored',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify reusing the same key with a different payload returns a conflict response.
	 */
	public function testRespondIfDuplicateRejectsDifferentPayloadForSameKey(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/orders';
$_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'order-create-3';

$request = new Pair\Api\Request();
$reflection = new ReflectionProperty(Pair\Api\Request::class, 'rawBody');
$reflection->setValue($request, '{"amount":15}');

Pair\Api\Idempotency::storeResponse($request, 'orders', [
	'id' => 11,
], 201, 300);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/orders';
$_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'order-create-3';

$duplicate = new Pair\Api\Request();
$reflection = new ReflectionProperty(Pair\Api\Request::class, 'rawBody');
$reflection->setValue($duplicate, '{"amount":99}');

register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

Pair\Api\Idempotency::respondIfDuplicate($duplicate, 'orders');
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(409, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'CONFLICT',
				'error' => 'Conflict',
				'detail' => 'Idempotency key already used with different payload',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Build a Request instance backed by a controlled raw body and optional idempotency key.
	 *
	 * @param	string		$method			HTTP method exposed through the request.
	 * @param	string		$uri			Request URI included in the request hash.
	 * @param	string		$rawBody		Raw request body used for hashing.
	 * @param	string|null	$idempotencyKey	Idempotency key header value.
	 */
	private function newRequest(string $method, string $uri, string $rawBody, ?string $idempotencyKey): Request {

		$_SERVER['REQUEST_METHOD'] = $method;
		$_SERVER['REQUEST_URI'] = $uri;

		if (is_null($idempotencyKey)) {
			unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
		} else {
			$_SERVER['HTTP_IDEMPOTENCY_KEY'] = $idempotencyKey;
		}

		$request = new Request();
		$this->setInaccessibleProperty($request, 'rawBody', $rawBody);

		return $request;

	}

	/**
	 * Read every stored idempotency row currently present in the temporary storage folder.
	 *
	 * @return	list<array<string, mixed>>
	 */
	private function readIdempotencyRows(): array {

		$folder = TEMP_PATH . 'idempotency';

		if (!is_dir($folder)) {
			return [];
		}

		$rows = [];

		foreach (glob($folder . '/*.json') ?: [] as $file) {
			$content = file_get_contents($file);
			$decoded = is_string($content) ? json_decode($content, true) : null;

			if (is_array($decoded)) {
				$rows[] = $decoded;
			}
		}

		return $rows;

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

	/**
	 * Read one private JsonResponse property for focused assertions on explicit idempotency responses.
	 */
	private function readJsonResponseProperty(JsonResponse $response, string $name): mixed {

		$property = new \ReflectionProperty($response, $name);

		return $property->getValue($response);

	}

	/**
	 * Read one private property from an arbitrary object for focused assertions.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$class	Declaring class of the property.
	 * @param	string	$name	Property name.
	 */
	private function readPrivateProperty(object $object, string $class, string $name): mixed {

		$property = new \ReflectionProperty($class, $name);

		return $property->getValue($object);

	}

}
