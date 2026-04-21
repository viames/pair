<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiErrorResponse;
use Pair\Api\Request;
use Pair\Tests\Support\FakeCreateOrderRequest;
use Pair\Tests\Support\TestCase;

/**
 * Covers the request helpers that can be exercised without the full application bootstrap.
 */
class RequestTest extends TestCase {

	/**
	 * Verify standard and special-case header lookups along with content-type helpers.
	 */
	public function testHeaderLookupHandlesStandardAndContentHeaders(): void {

		$_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';
		$_SERVER['CONTENT_LENGTH'] = '37';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer top-secret';

		$request = new Request();

		$this->assertSame('application/json; charset=utf-8', $request->header('Content-Type'));
		$this->assertSame('37', $request->header('Content-Length'));
		$this->assertSame('Bearer top-secret', $request->header('Authorization'));
		$this->assertSame('top-secret', $request->bearerToken());
		$this->assertTrue($request->isJson());

	}

	/**
	 * Verify JSON parsing and merged accessors without depending on php://input in the test runner.
	 */
	public function testJsonBodyIsMergedOverQueryParameters(): void {

		$_GET = [
			'page' => '1',
			'name' => 'query-value',
		];

		$request = $this->requestWithJsonBody([
			'name' => 'json-value',
			'status' => 'active',
		]);

		$this->assertSame('json-value', $request->json('name'));
		$this->assertSame([
			'page' => '1',
			'name' => 'json-value',
			'status' => 'active',
		], $request->all());

	}

	/**
	 * Verify the built-in validator keeps valid fields and ignores missing optional ones.
	 */
	public function testValidateReturnsOnlyValidatedFields(): void {

		$request = $this->requestWithJsonBody([
			'name' => 'Alice',
			'age' => 31,
			'active' => true,
		]);

		$this->assertSame([
			'name' => 'Alice',
			'age' => 31,
			'active' => true,
		], $request->validate([
			'name' => 'required|string|min:3',
			'age' => 'required|int|min:18',
			'active' => 'bool',
			'email' => 'email',
		]));

	}

	/**
	 * Verify numeric min/max rules compare numeric values before falling back to string length checks.
	 */
	public function testValidateOrResponseSupportsDecimalNumericBounds(): void {

		$request = $this->requestWithJsonBody([
			'amount' => '0.005',
			'discount' => '10.5',
		]);

		$result = $request->validateOrResponse([
			'amount' => 'required|numeric|min:0.01',
			'discount' => 'numeric|max:9.99',
		]);

		$this->assertInstanceOf(ApiErrorResponse::class, $result);
		$this->assertSame([
			'errors' => [
				'amount' => 'The field amount must be at least 0.01',
				'discount' => 'The field discount must not exceed 9.99',
			],
		], $this->readApiErrorResponseProperty($result, 'extra'));

	}

	/**
	 * Verify the explicit validation helper returns an ApiErrorResponse instead of terminating immediately.
	 */
	public function testValidateOrResponseReturnsExplicitErrorResponse(): void {

		$request = $this->requestWithJsonBody([
			'email' => 'not-an-email',
		]);

		$result = $request->validateOrResponse([
			'email' => 'required|email',
			'name' => 'required|string',
		]);

		$this->assertInstanceOf(ApiErrorResponse::class, $result);
		$this->assertSame('INVALID_FIELDS', $this->readApiErrorResponseProperty($result, 'errorCode'));
		$this->assertSame(400, $this->readApiErrorResponseProperty($result, 'httpCode'));
		$this->assertSame([
			'errors' => [
				'email' => 'The field email must be a valid email address',
				'name' => 'The field name is required',
			],
		], $this->readApiErrorResponseProperty($result, 'extra'));

	}

	/**
	 * Verify validated request data can be mapped into an explicit request object.
	 */
	public function testValidateObjectOrResponseMapsValidatedDataToRequestObject(): void {

		$request = $this->requestWithJsonBody([
			'customerId' => '42',
			'amount' => '19.95',
			'currency' => 'eur',
			'ignored' => 'not-in-rules',
		]);

		$result = $request->validateObjectOrResponse(FakeCreateOrderRequest::class, [
			'customerId' => 'required|int',
			'amount' => 'required|numeric|min:0.01',
			'currency' => 'required|string|max:3',
		]);

		$this->assertInstanceOf(FakeCreateOrderRequest::class, $result);
		$this->assertSame(42, $result->customerId);
		$this->assertSame(19.95, $result->amount);
		$this->assertSame('EUR', $result->currency);

	}

	/**
	 * Verify request-object mapping preserves explicit validation errors without terminating.
	 */
	public function testValidateObjectOrResponseReturnsExplicitErrorResponse(): void {

		$request = $this->requestWithJsonBody([
			'customerId' => 'not-an-int',
		]);

		$result = $request->validateObjectOrResponse(FakeCreateOrderRequest::class, [
			'customerId' => 'required|int',
			'amount' => 'required|numeric',
		]);

		$this->assertInstanceOf(ApiErrorResponse::class, $result);
		$this->assertSame('INVALID_FIELDS', $this->readApiErrorResponseProperty($result, 'errorCode'));
		$this->assertSame([
			'errors' => [
				'customerId' => 'The field customerId must be an integer',
				'amount' => 'The field amount is required',
			],
		], $this->readApiErrorResponseProperty($result, 'extra'));

	}

	/**
	 * Verify mapping fails fast when the target class does not implement RequestData.
	 */
	public function testValidateObjectOrResponseRequiresRequestDataContract(): void {

		$request = $this->requestWithJsonBody([
			'name' => 'Alice',
		]);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must implement Pair\Api\RequestData');

		$request->validateObjectOrResponse(\stdClass::class, [
			'name' => 'required|string',
		]);

	}

	/**
	 * Verify proxy-aware IP resolution and replay/idempotency headers.
	 */
	public function testTrustedProxyReplayAndIdempotencyHelpers(): void {

		$_ENV['PAIR_TRUSTED_PROXIES'] = '10.0.0.1,192.168.0.0/24';
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.20, 10.0.0.1';
		$_SERVER['HTTP_X_PAIR_REPLAY'] = 'true';
		$_SERVER['HTTP_X_IDEMPOTENCY_KEY'] = ' replay-safe-key ';

		$request = new Request();

		$this->assertSame('203.0.113.20', $request->ip());
		$this->assertTrue($request->isReplayRequest());
		$this->assertSame('replay-safe-key', $request->idempotencyKey());

	}

	/**
	 * Build a Request instance backed by a preloaded JSON body.
	 *
	 * @param	array	$payload	JSON payload to expose through Request::json().
	 * @return	Request
	 */
	private function requestWithJsonBody(array $payload): Request {

		$request = new Request();

		// Preload the raw body so Request can exercise its native parser without touching php://input.
		$this->setInaccessibleProperty($request, 'rawBody', json_encode($payload));

		return $request;

	}

	/**
	 * Read one private ApiErrorResponse property for focused assertions on explicit validation errors.
	 */
	private function readApiErrorResponseProperty(ApiErrorResponse $response, string $name): mixed {

		$property = new \ReflectionProperty($response, $name);

		return $property->getValue($response);

	}

}
