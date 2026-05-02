<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Provider-neutral anti-bot helper for public forms.
 */
class HumanChallenge {

	/**
	 * Automatically select the first configured provider.
	 */
	public const PROVIDER_AUTO = 'auto';

	/**
	 * Disable challenge rendering and validation.
	 */
	public const PROVIDER_NONE = 'none';

	/**
	 * Use Cloudflare Turnstile.
	 */
	public const PROVIDER_TURNSTILE = 'turnstile';

	/**
	 * Use Google reCAPTCHA.
	 */
	public const PROVIDER_RECAPTCHA = 'recaptcha';

	/**
	 * Google reCAPTCHA service implementation.
	 */
	private GoogleRecaptcha $recaptcha;

	/**
	 * Requested provider name before auto-resolution.
	 */
	private string $requestedProvider;

	/**
	 * Cloudflare Turnstile service implementation.
	 */
	private CloudflareTurnstile $turnstile;

	/**
	 * Build a human challenge helper using explicit services or Env defaults.
	 */
	public function __construct(string|bool|null $provider = null, ?CloudflareTurnstile $turnstile = null, ?GoogleRecaptcha $recaptcha = null) {

		$this->requestedProvider = $this->normalizeProvider($provider ?? Env::get('PAIR_HUMAN_CHALLENGE_PROVIDER') ?? self::PROVIDER_AUTO);
		$this->turnstile = $turnstile ?? new CloudflareTurnstile();
		$this->recaptcha = $recaptcha ?? new GoogleRecaptcha();

	}

	/**
	 * Assert that a POST payload contains a valid token for the selected provider.
	 */
	public function assertPost(array $post, ?string $remoteIp = null, array $options = []): array {

		try {
			$result = $this->verifyPost($post, $remoteIp, $options);
		} catch (PairException $exception) {
			throw new PairException($exception->getMessage(), ErrorCodes::HUMAN_CHALLENGE_ERROR, $exception);
		}

		if (!($result['success'] ?? false)) {
			throw new PairException($this->failureMessage($result), ErrorCodes::HUMAN_CHALLENGE_ERROR);
		}

		return $result;

	}

	/**
	 * Check whether a provider is active for the current configuration.
	 */
	public function enabled(): bool {

		return self::PROVIDER_NONE !== $this->provider();

	}

	/**
	 * Register the selected provider script in the Pair application.
	 */
	public function loadScript(?Application $app = null, array $options = []): void {

		if (!$this->enabled()) {
			return;
		}

		($app ?? Application::getInstance())->loadScript($this->scriptUrl($options), true, true);

	}

	/**
	 * Return the selected provider name after auto-resolution.
	 */
	public function provider(): string {

		if (self::PROVIDER_AUTO !== $this->requestedProvider) {
			return $this->requestedProvider;
		}

		if ($this->turnstile->siteKeySet() or $this->turnstile->secretKeySet()) {
			return self::PROVIDER_TURNSTILE;
		}

		if ($this->recaptcha->siteKeySet() or $this->recaptcha->secretKeySet()) {
			return self::PROVIDER_RECAPTCHA;
		}

		return self::PROVIDER_NONE;

	}

	/**
	 * Return the selected provider browser script URL.
	 */
	public function scriptUrl(array $options = []): string {

		return match ($this->provider()) {
			self::PROVIDER_TURNSTILE => $this->turnstile->scriptUrl(false),
			self::PROVIDER_RECAPTCHA => $this->recaptcha->scriptUrl($this->recaptchaScriptOptions($options)),
			default => '',
		};

	}

	/**
	 * Verify a POST payload by extracting the selected provider response field.
	 */
	public function verifyPost(array $post, ?string $remoteIp = null, array $options = []): array {

		$provider = $this->provider();

		if (self::PROVIDER_NONE === $provider) {
			return [
				'success' => true,
				'provider' => self::PROVIDER_NONE,
				'bypassed' => true,
			];
		}

		$result = match ($provider) {
			self::PROVIDER_TURNSTILE => $this->turnstile->verifyPost($post, $remoteIp, $options),
			self::PROVIDER_RECAPTCHA => $this->recaptcha->verifyPost($post, $remoteIp),
			default => ['success' => false],
		};

		$result['provider'] = $provider;

		return $this->validateExpectedResponse($provider, $result, $options);

	}

	/**
	 * Return the selected provider widget HTML.
	 */
	public function widgetHtml(array $options = []): string {

		return match ($this->provider()) {
			self::PROVIDER_TURNSTILE => $this->turnstile->widgetHtml($this->turnstileWidgetOptions($options)),
			self::PROVIDER_RECAPTCHA => $this->recaptcha->widgetHtml($this->recaptchaWidgetOptions($options)),
			default => '',
		};

	}

	/**
	 * Build a useful validation failure message.
	 */
	private function failureMessage(array $result): string {

		$errorCodes = $result['error-codes'] ?? $result['error_codes'] ?? [];
		$provider = (string)($result['provider'] ?? 'human challenge');

		if (is_array($errorCodes) and count($errorCodes)) {
			return 'Human challenge validation failed for ' . $provider . ': ' . implode(', ', array_map('strval', $errorCodes));
		}

		return 'Human challenge validation failed for ' . $provider . '.';

	}

	/**
	 * Normalize the widget action to Turnstile's documented character set and length.
	 */
	private function normalizeAction(string $action): string {

		$action = preg_replace('/[^A-Za-z0-9_-]/', '_', trim($action)) ?: 'form';

		return substr($action, 0, 32);

	}

	/**
	 * Normalize a hostname for case-insensitive provider response comparisons.
	 */
	private function normalizeHostname(string $hostname): string {

		$hostname = strtolower(trim($hostname));

		if (str_contains($hostname, ':')) {
			$hostname = preg_replace('/:\d+$/', '', $hostname) ?: $hostname;
		}

		return $hostname;

	}

	/**
	 * Normalize provider aliases accepted by applications.
	 */
	private function normalizeProvider(string|bool $provider): string {

		if (is_bool($provider)) {
			return $provider ? self::PROVIDER_AUTO : self::PROVIDER_NONE;
		}

		$provider = strtolower(trim(str_replace(['_', ' '], '-', $provider)));

		if ('' === $provider or self::PROVIDER_AUTO === $provider) {
			return self::PROVIDER_AUTO;
		}

		if (in_array($provider, ['0', 'false', 'off', 'disabled', 'none'], true)) {
			return self::PROVIDER_NONE;
		}

		if (in_array($provider, ['cloudflare', 'cloudflare-turnstile', self::PROVIDER_TURNSTILE], true)) {
			return self::PROVIDER_TURNSTILE;
		}

		if (in_array($provider, ['captcha', 'google', 'google-recaptcha', 'google-recaptcha-v2', self::PROVIDER_RECAPTCHA], true)) {
			return self::PROVIDER_RECAPTCHA;
		}

		throw new PairException('Unsupported human challenge provider "' . $provider . '".', ErrorCodes::MISSING_CONFIGURATION);

	}

	/**
	 * Return script parameters supported by Google reCAPTCHA.
	 */
	private function recaptchaScriptOptions(array $options): array {

		$language = trim((string)($options['hl'] ?? $options['language'] ?? ''));

		return '' === $language ? [] : ['hl' => $language];

	}

	/**
	 * Remove options that belong to other providers before rendering reCAPTCHA.
	 */
	private function recaptchaWidgetOptions(array $options): array {

		unset($options['action'], $options['appearance'], $options['expectedAction'], $options['expected_action'], $options['expectedHostname'], $options['expected_hostname'], $options['hl'], $options['language']);

		return array_merge(['theme' => 'light'], $options);

	}

	/**
	 * Add Turnstile defaults and normalize the action option when present.
	 */
	private function turnstileWidgetOptions(array $options): array {

		$options = array_merge([
			'appearance' => 'interaction-only',
			'theme' => 'auto',
		], $options);

		if (isset($options['action'])) {
			$options['action'] = $this->normalizeAction((string)$options['action']);
		}

		unset($options['expectedAction'], $options['expected_action'], $options['expectedHostname'], $options['expected_hostname'], $options['hl'], $options['language']);

		return $options;

	}

	/**
	 * Validate optional expected action and hostname values against provider responses.
	 */
	private function validateExpectedResponse(string $provider, array $result, array $options): array {

		if (!($result['success'] ?? false)) {
			return $result;
		}

		$expectedAction = trim((string)($options['expectedAction'] ?? $options['expected_action'] ?? $options['action'] ?? ''));

		if (self::PROVIDER_TURNSTILE === $provider and '' !== $expectedAction) {
			$actualAction = trim((string)($result['action'] ?? ''));

			if ('' !== $actualAction and !hash_equals($this->normalizeAction($expectedAction), $actualAction)) {
				return $this->withValidationFailure($result, 'action-mismatch');
			}
		}

		$expectedHostname = $this->normalizeHostname((string)($options['expectedHostname'] ?? $options['expected_hostname'] ?? ''));

		if ('' !== $expectedHostname) {
			$actualHostname = $this->normalizeHostname((string)($result['hostname'] ?? ''));

			if ('' !== $actualHostname and !hash_equals($expectedHostname, $actualHostname)) {
				return $this->withValidationFailure($result, 'hostname-mismatch');
			}
		}

		return $result;

	}

	/**
	 * Mark a provider response as failed with a normalized error code.
	 */
	private function withValidationFailure(array $result, string $code): array {

		$result['success'] = false;
		$errorCodes = $result['error-codes'] ?? [];

		if (!is_array($errorCodes)) {
			$errorCodes = [$errorCodes];
		}

		$errorCodes[] = $code;
		$result['error-codes'] = array_values(array_unique(array_map('strval', $errorCodes)));

		return $result;

	}

}
