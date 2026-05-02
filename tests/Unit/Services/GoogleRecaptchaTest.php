<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Services\GoogleRecaptcha;
use Pair\Tests\Support\TestCase;

/**
 * Covers GoogleRecaptcha request shaping and HTML helpers without calling Google.
 */
class GoogleRecaptchaTest extends TestCase {

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
	 * Verify Siteverify payloads include the token, secret, and remote IP.
	 */
	public function testVerifyTokenBuildsSiteverifyPayload(): void {

		$recaptcha = new FakeGoogleRecaptcha();

		$response = $recaptcha->verifyToken('token-test', '203.0.113.20');
		$request = $recaptcha->lastRequest();

		$this->assertTrue($response['success']);
		$this->assertSame('secret-test', $request['secret']);
		$this->assertSame('token-test', $request['response']);
		$this->assertSame('203.0.113.20', $request['remoteip']);

	}

	/**
	 * Verify assertToken converts failed provider responses into framework exceptions.
	 */
	public function testAssertTokenThrowsOnFailedVerification(): void {

		$recaptcha = new FakeGoogleRecaptcha();
		$recaptcha->setNextResponse([
			'success' => false,
			'error-codes' => ['timeout-or-duplicate'],
		]);

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		$this->expectExceptionMessage('timeout-or-duplicate');

		$recaptcha->assertToken('token-test');

	}

	/**
	 * Verify POST helpers read the configured reCAPTCHA response field.
	 */
	public function testVerifyPostReadsConfiguredResponseField(): void {

		$recaptcha = new FakeGoogleRecaptcha(responseField: 'captcha_token');

		$recaptcha->verifyPost([
			'captcha_token' => 'posted-token',
		]);

		$this->assertSame('posted-token', $recaptcha->lastRequest()['response']);

	}

	/**
	 * Verify widget HTML is escaped and maps options to reCAPTCHA data attributes.
	 */
	public function testWidgetHtmlRendersEscapedDataAttributes(): void {

		$recaptcha = new FakeGoogleRecaptcha();

		$html = $recaptcha->widgetHtml([
			'class' => 'form-captcha',
			'theme' => 'dark',
			'callback' => 'onCaptchaDone',
			'errorCallback' => 'onCaptchaError',
			'invalid attr' => 'ignored',
		]);

		$this->assertStringContainsString('<div', $html);
		$this->assertStringContainsString('class="g-recaptcha form-captcha"', $html);
		$this->assertStringContainsString('data-sitekey="site-test"', $html);
		$this->assertStringContainsString('data-theme="dark"', $html);
		$this->assertStringContainsString('data-callback="onCaptchaDone"', $html);
		$this->assertStringContainsString('data-error-callback="onCaptchaError"', $html);
		$this->assertStringNotContainsString('invalid attr', $html);

	}

	/**
	 * Verify scriptTag can render language-aware reCAPTCHA URLs.
	 */
	public function testScriptTagSupportsLanguageParameter(): void {

		$recaptcha = new FakeGoogleRecaptcha();

		$html = $recaptcha->scriptTag(['hl' => 'it'], [
			'id' => 'captcha-script',
		]);

		$this->assertStringContainsString('src="https://www.google.com/recaptcha/api.js?hl=it"', $html);
		$this->assertStringContainsString('async', $html);
		$this->assertStringContainsString('defer', $html);
		$this->assertStringContainsString('id="captcha-script"', $html);

	}

	/**
	 * Verify configured key helpers expose whether browser and server keys are present.
	 */
	public function testKeyPresenceHelpersReflectConfiguration(): void {

		$this->assertTrue((new FakeGoogleRecaptcha())->secretKeySet());
		$this->assertTrue((new FakeGoogleRecaptcha())->siteKeySet());
		$this->assertFalse((new GoogleRecaptcha('', ''))->secretKeySet());
		$this->assertFalse((new GoogleRecaptcha('', ''))->siteKeySet());

	}

	/**
	 * Verify empty tokens are rejected before any provider request is made.
	 */
	public function testEmptyTokenIsRejected(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::GOOGLE_RECAPTCHA_ERROR);

		(new FakeGoogleRecaptcha())->verifyToken('');

	}

	/**
	 * Verify tokens over the local guard limit are rejected before any provider request is made.
	 */
	public function testTokenLengthIsRejected(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::GOOGLE_RECAPTCHA_ERROR);

		(new FakeGoogleRecaptcha())->verifyToken(str_repeat('a', 4097));

	}

	/**
	 * Verify widget rendering requires a site key.
	 */
	public function testWidgetHtmlRequiresSiteKey(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::MISSING_CONFIGURATION);

		(new GoogleRecaptcha('secret-test', ''))->widgetHtml();

	}

}

/**
 * Test double that records reCAPTCHA requests and returns deterministic payloads.
 */
final class FakeGoogleRecaptcha extends GoogleRecaptcha {

	/**
	 * Next fake Siteverify response.
	 *
	 * @var	array<string, mixed>
	 */
	private array $nextResponse = [
		'success' => true,
		'challenge_ts' => '2026-05-02T00:00:00Z',
		'hostname' => 'example.test',
	];

	/**
	 * Captured Siteverify request payloads.
	 *
	 * @var	array<int, array<string, mixed>>
	 */
	private array $requests = [];

	/**
	 * Build the fake with deterministic reCAPTCHA keys.
	 */
	public function __construct(?string $secretKey = 'secret-test', ?string $siteKey = 'site-test', ?string $responseField = null) {

		parent::__construct($secretKey, $siteKey, 'https://recaptcha.example.test/siteverify', 10, 3, $responseField);

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
