<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Html\FormControls\HumanChallenge as HumanChallengeControl;
use Pair\Services\CloudflareTurnstile;
use Pair\Services\GoogleRecaptcha;
use Pair\Services\HumanChallenge;
use Pair\Tests\Support\TestCase;

/**
 * Covers provider selection, response checks, and form-control rendering for HumanChallenge.
 */
class HumanChallengeTest extends TestCase {

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
	 * Verify auto mode prefers Turnstile when both providers have keys.
	 */
	public function testAutoSelectsTurnstileFirst(): void {

		$challenge = new HumanChallenge(
			HumanChallenge::PROVIDER_AUTO,
			new FakeHumanChallengeTurnstile(),
			new FakeHumanChallengeRecaptcha()
		);

		$html = $challenge->widgetHtml(['action' => 'wait list']);

		$this->assertTrue($challenge->enabled());
		$this->assertSame(HumanChallenge::PROVIDER_TURNSTILE, $challenge->provider());
		$this->assertStringContainsString('class="cf-turnstile"', $html);
		$this->assertStringContainsString('data-action="wait_list"', $html);
		$this->assertStringContainsString('data-appearance="interaction-only"', $html);

	}

	/**
	 * Verify the CAPTCHA alias selects Google reCAPTCHA.
	 */
	public function testCaptchaAliasSelectsRecaptcha(): void {

		$challenge = new HumanChallenge(
			'captcha',
			new FakeHumanChallengeTurnstile('', ''),
			new FakeHumanChallengeRecaptcha()
		);

		$html = $challenge->widgetHtml(['theme' => 'dark']);

		$this->assertSame(HumanChallenge::PROVIDER_RECAPTCHA, $challenge->provider());
		$this->assertSame('https://www.google.com/recaptcha/api.js?hl=it', $challenge->scriptUrl(['language' => 'it']));
		$this->assertStringContainsString('class="g-recaptcha"', $html);
		$this->assertStringContainsString('data-theme="dark"', $html);

	}

	/**
	 * Verify disabled mode bypasses validation without requiring a provider token.
	 */
	public function testNoneProviderBypassesValidation(): void {

		$challenge = new HumanChallenge(
			HumanChallenge::PROVIDER_NONE,
			new FakeHumanChallengeTurnstile('', ''),
			new FakeHumanChallengeRecaptcha('', '')
		);

		$result = $challenge->verifyPost([]);

		$this->assertFalse($challenge->enabled());
		$this->assertSame('', $challenge->widgetHtml());
		$this->assertTrue($result['success']);
		$this->assertTrue($result['bypassed']);

	}

	/**
	 * Verify Turnstile action mismatches are normalized into failed provider responses.
	 */
	public function testExpectedActionMismatchMarksVerificationAsFailed(): void {

		$turnstile = new FakeHumanChallengeTurnstile();
		$turnstile->setNextResponse([
			'success' => true,
			'action' => 'other_action',
			'hostname' => 'example.test',
		]);
		$challenge = new HumanChallenge(
			HumanChallenge::PROVIDER_TURNSTILE,
			$turnstile,
			new FakeHumanChallengeRecaptcha('', '')
		);

		$result = $challenge->verifyPost([
			'cf-turnstile-response' => 'posted-token',
		], null, [
			'expectedAction' => 'proposal_create',
		]);

		$this->assertFalse($result['success']);
		$this->assertContains('action-mismatch', $result['error-codes']);

	}

	/**
	 * Verify the Pair form control renders the selected provider widget.
	 */
	public function testFormControlRendersSelectedProviderWidget(): void {

		$control = new HumanChallengeControl(
			'human_challenge',
			[],
			new HumanChallenge(
				HumanChallenge::PROVIDER_TURNSTILE,
				new FakeHumanChallengeTurnstile(),
				new FakeHumanChallengeRecaptcha('', '')
			)
		);

		$html = $control->class('anti-bot-widget')->action('proposal create')->render();

		$this->assertStringContainsString('class="cf-turnstile anti-bot-widget"', $html);
		$this->assertStringContainsString('data-action="proposal_create"', $html);

	}

}

/**
 * Test double that records Turnstile requests and returns deterministic payloads.
 */
final class FakeHumanChallengeTurnstile extends CloudflareTurnstile {

	/**
	 * Next fake Siteverify response.
	 *
	 * @var	array<string, mixed>
	 */
	private array $nextResponse = [
		'success' => true,
		'action' => 'proposal_create',
		'hostname' => 'example.test',
	];

	/**
	 * Build the fake with deterministic Turnstile keys.
	 */
	public function __construct(?string $secretKey = 'secret-test', ?string $siteKey = 'site-test') {

		parent::__construct($secretKey, $siteKey, 'https://turnstile.example.test/siteverify', 10, 3);

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
	 * Return the configured fake response without making an HTTP call.
	 */
	protected function requestSiteverify(array $payload): array {

		return $this->nextResponse;

	}

}

/**
 * Test double that records reCAPTCHA requests and returns deterministic payloads.
 */
final class FakeHumanChallengeRecaptcha extends GoogleRecaptcha {

	/**
	 * Next fake Siteverify response.
	 *
	 * @var	array<string, mixed>
	 */
	private array $nextResponse = [
		'success' => true,
		'hostname' => 'example.test',
	];

	/**
	 * Build the fake with deterministic reCAPTCHA keys.
	 */
	public function __construct(?string $secretKey = 'secret-test', ?string $siteKey = 'site-test') {

		parent::__construct($secretKey, $siteKey, 'https://recaptcha.example.test/siteverify', 10, 3);

	}

	/**
	 * Return the configured fake response without making an HTTP call.
	 */
	protected function requestSiteverify(array $payload): array {

		return $this->nextResponse;

	}

}
