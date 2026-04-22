<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Services\CloudflareTurnstile;
use Pair\Tests\Support\TestCase;

/**
 * Covers CloudflareTurnstile request shaping and HTML helpers without calling Cloudflare.
 */
class CloudflareTurnstileTest extends TestCase {

	/**
	 * Define the minimal routing constant needed by PairException logging in isolated tests.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

	}

	/**
	 * Verify Siteverify payloads include the token, secret, remote IP, and idempotency key.
	 */
	public function testVerifyTokenBuildsSiteverifyPayload(): void {

		$turnstile = new FakeCloudflareTurnstile();

		$response = $turnstile->verifyToken('token-test', '203.0.113.10', [
			'idempotency_key' => 'request-123',
		]);

		$request = $turnstile->lastRequest();

		$this->assertTrue($response['success']);
		$this->assertSame('secret-test', $request['secret']);
		$this->assertSame('token-test', $request['response']);
		$this->assertSame('203.0.113.10', $request['remoteip']);
		$this->assertSame('request-123', $request['idempotency_key']);

	}

	/**
	 * Verify assertToken converts failed provider responses into framework exceptions.
	 */
	public function testAssertTokenThrowsOnFailedVerification(): void {

		$turnstile = new FakeCloudflareTurnstile();
		$turnstile->setNextResponse([
			'success' => false,
			'error-codes' => ['timeout-or-duplicate'],
		]);

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		$this->expectExceptionMessage('timeout-or-duplicate');

		$turnstile->assertToken('token-test');

	}

	/**
	 * Verify POST helpers read the configured Turnstile response field.
	 */
	public function testVerifyPostReadsConfiguredResponseField(): void {

		$turnstile = new FakeCloudflareTurnstile(responseField: 'turnstile_token');

		$turnstile->verifyPost([
			'turnstile_token' => 'posted-token',
		]);

		$this->assertSame('posted-token', $turnstile->lastRequest()['response']);

	}

	/**
	 * Verify widget HTML is escaped and maps options to Turnstile data attributes.
	 */
	public function testWidgetHtmlRendersEscapedDataAttributes(): void {

		$turnstile = new FakeCloudflareTurnstile();

		$html = $turnstile->widgetHtml([
			'class' => 'form-turnstile',
			'theme' => 'auto',
			'action' => 'contact<form>',
			'retry' => 'auto',
			'errorCallback' => 'onTurnstileError',
			'invalid attr' => 'ignored',
		]);

		$this->assertStringContainsString('<div', $html);
		$this->assertStringContainsString('class="cf-turnstile form-turnstile"', $html);
		$this->assertStringContainsString('data-sitekey="site-test"', $html);
		$this->assertStringContainsString('data-theme="auto"', $html);
		$this->assertStringContainsString('data-action="contact&lt;form&gt;"', $html);
		$this->assertStringContainsString('data-retry="auto"', $html);
		$this->assertStringContainsString('data-error-callback="onTurnstileError"', $html);
		$this->assertStringNotContainsString('invalid attr', $html);

	}

	/**
	 * Verify scriptTag can render the explicit Turnstile script URL.
	 */
	public function testScriptTagSupportsExplicitRendering(): void {

		$turnstile = new FakeCloudflareTurnstile();

		$html = $turnstile->scriptTag(true, [
			'id' => 'turnstile-script',
		]);

		$this->assertStringContainsString('src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"', $html);
		$this->assertStringNotContainsString('async', $html);
		$this->assertStringContainsString('defer', $html);
		$this->assertStringContainsString('id="turnstile-script"', $html);

	}

	/**
	 * Verify configured key helpers expose whether browser and server keys are present.
	 */
	public function testKeyPresenceHelpersReflectConfiguration(): void {

		$this->assertTrue((new FakeCloudflareTurnstile())->secretKeySet());
		$this->assertTrue((new FakeCloudflareTurnstile())->siteKeySet());
		$this->assertFalse((new CloudflareTurnstile('', ''))->secretKeySet());
		$this->assertFalse((new CloudflareTurnstile('', ''))->siteKeySet());

	}

	/**
	 * Verify empty tokens are rejected before any provider request is made.
	 */
	public function testEmptyTokenIsRejected(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);

		(new FakeCloudflareTurnstile())->verifyToken('');

	}

	/**
	 * Verify tokens over the documented limit are rejected before any provider request is made.
	 */
	public function testTokenLengthIsRejected(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);

		(new FakeCloudflareTurnstile())->verifyToken(str_repeat('a', 2049));

	}

	/**
	 * Verify widget rendering requires a site key.
	 */
	public function testWidgetHtmlRequiresSiteKey(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::MISSING_CONFIGURATION);

		(new CloudflareTurnstile('secret-test', ''))->widgetHtml();

	}

}

/**
 * Test double that records Turnstile requests and returns deterministic payloads.
 */
final class FakeCloudflareTurnstile extends CloudflareTurnstile {

	/**
	 * Next fake Siteverify response.
	 *
	 * @var	array<string, mixed>
	 */
	private array $nextResponse = [
		'success' => true,
		'challenge_ts' => '2026-04-22T00:00:00Z',
		'hostname' => 'example.test',
	];

	/**
	 * Captured Siteverify request payloads.
	 *
	 * @var	array<int, array<string, mixed>>
	 */
	private array $requests = [];

	/**
	 * Build the fake with deterministic Turnstile keys.
	 */
	public function __construct(?string $secretKey = 'secret-test', ?string $siteKey = 'site-test', ?string $responseField = null) {

		parent::__construct($secretKey, $siteKey, 'https://turnstile.example.test/siteverify', 10, 3, $responseField);

	}

	/**
	 * Return the most recent captured Siteverify payload.
	 *
	 * @return	array<string, mixed>
	 */
	public function lastRequest(): array {

		return $this->requests[count($this->requests) - 1] ?? [];

	}

	/**
	 * Configure the next fake Siteverify response.
	 *
	 * @param	array<string, mixed>	$response	Provider-shaped response payload.
	 */
	public function setNextResponse(array $response): void {

		$this->nextResponse = $response;

	}

	/**
	 * Capture request details and return the configured fake response.
	 */
	protected function requestSiteverify(array $payload): array {

		$this->requests[] = $payload;

		return $this->nextResponse;

	}

}
