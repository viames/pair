<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Google reCAPTCHA helper for widget rendering and server-side token validation.
 */
class GoogleRecaptcha {

	/**
	 * Official reCAPTCHA browser script URL.
	 */
	private const API_SCRIPT_URL = 'https://www.google.com/recaptcha/api.js';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 3;

	/**
	 * Default response field submitted by reCAPTCHA widgets.
	 */
	private const DEFAULT_RESPONSE_FIELD = 'g-recaptcha-response';

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 10;

	/**
	 * Default server-side verification endpoint.
	 */
	private const DEFAULT_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Generous token length guard to avoid oversized verification payloads.
	 */
	private const MAX_TOKEN_LENGTH = 4096;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * POST field used to read reCAPTCHA response tokens.
	 */
	private string $responseField;

	/**
	 * Private key used by Siteverify.
	 */
	private string $secretKey;

	/**
	 * Public key used by browser widgets.
	 */
	private string $siteKey;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Siteverify endpoint URL.
	 */
	private string $verifyUrl;

	/**
	 * Build a reCAPTCHA helper using explicit values or Env defaults.
	 */
	public function __construct(?string $secretKey = null, ?string $siteKey = null, ?string $verifyUrl = null, ?int $timeout = null, ?int $connectTimeout = null, ?string $responseField = null) {

		$this->secretKey = trim((string)($secretKey ?? Env::get('GOOGLE_RECAPTCHA_SECRET_KEY')));
		$this->siteKey = trim((string)($siteKey ?? Env::get('GOOGLE_RECAPTCHA_SITE_KEY')));
		$this->verifyUrl = $this->sanitizeVerifyUrl((string)($verifyUrl ?? Env::get('GOOGLE_RECAPTCHA_VERIFY_URL') ?? self::DEFAULT_VERIFY_URL));
		$this->timeout = max(1, (int)($timeout ?? Env::get('GOOGLE_RECAPTCHA_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('GOOGLE_RECAPTCHA_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));
		$this->responseField = $this->sanitizeResponseField((string)($responseField ?? Env::get('GOOGLE_RECAPTCHA_RESPONSE_FIELD') ?? self::DEFAULT_RESPONSE_FIELD));

	}

	/**
	 * Assert that a POST payload contains a valid reCAPTCHA token.
	 */
	public function assertPost(array $post, ?string $remoteIp = null): array {

		return $this->assertToken($this->tokenFromPost($post), $remoteIp);

	}

	/**
	 * Assert that a reCAPTCHA token is valid and return the verification response.
	 *
	 * @throws PairException
	 */
	public function assertToken(string $token, ?string $remoteIp = null): array {

		$result = $this->verifyToken($token, $remoteIp);

		if (!($result['success'] ?? false)) {
			throw new PairException($this->failureMessage($result), ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		return $result;

	}

	/**
	 * Return the official reCAPTCHA script tag.
	 */
	public function scriptTag(array $parameters = [], array $attributes = []): string {

		$attributes = array_merge([
			'src' => $this->scriptUrl($parameters),
			'async' => true,
			'defer' => true,
		], $attributes);

		return '<script' . $this->htmlAttributes($attributes) . '></script>';

	}

	/**
	 * Return the official reCAPTCHA script URL.
	 */
	public function scriptUrl(array $parameters = []): string {

		$allowedParameters = [];

		foreach (['onload', 'render', 'hl'] as $name) {
			$value = $this->normalizeNullableString($parameters[$name] ?? null);
			if (!is_null($value)) {
				$allowedParameters[$name] = $value;
			}
		}

		if (!$allowedParameters) {
			return self::API_SCRIPT_URL;
		}

		return self::API_SCRIPT_URL . '?' . http_build_query($allowedParameters, '', '&', PHP_QUERY_RFC3986);

	}

	/**
	 * Check whether a secret key is configured.
	 */
	public function secretKeySet(): bool {

		return '' !== $this->secretKey;

	}

	/**
	 * Check whether a site key is configured.
	 */
	public function siteKeySet(): bool {

		return '' !== $this->siteKey;

	}

	/**
	 * Extract the configured reCAPTCHA response token from a POST-like payload.
	 */
	public function tokenFromPost(array $post): string {

		return trim((string)($post[$this->responseField] ?? ''));

	}

	/**
	 * Verify a POST payload by extracting the configured reCAPTCHA response field.
	 */
	public function verifyPost(array $post, ?string $remoteIp = null): array {

		return $this->verifyToken($this->tokenFromPost($post), $remoteIp);

	}

	/**
	 * Verify a reCAPTCHA token through Google Siteverify.
	 */
	public function verifyToken(string $token, ?string $remoteIp = null): array {

		$this->assertSecretKeyConfigured();

		$token = trim($token);
		$this->assertTokenFormat($token);

		$payload = [
			'secret' => $this->secretKey,
			'response' => $token,
			'remoteip' => $this->normalizeNullableString($remoteIp),
		];

		return $this->normalizeVerificationResponse(
			$this->requestSiteverify($this->withoutNullValues($payload))
		);

	}

	/**
	 * Return the implicit-rendering reCAPTCHA widget HTML.
	 */
	public function widgetHtml(array $options = []): string {

		$siteKey = trim((string)($options['sitekey'] ?? $options['siteKey'] ?? $this->siteKey));

		if ('' === $siteKey) {
			throw new PairException('Missing Google reCAPTCHA site key. Set GOOGLE_RECAPTCHA_SITE_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

		unset($options['sitekey'], $options['siteKey']);

		$attributes = [
			'class' => trim('g-recaptcha ' . (string)($options['class'] ?? '')),
			'data-sitekey' => $siteKey,
		];

		if (isset($options['id'])) {
			$attributes['id'] = $options['id'];
		}

		foreach ($this->widgetDataAttributes($options) as $name => $value) {
			$attributes[$name] = $value;
		}

		return '<div' . $this->htmlAttributes($attributes) . '></div>';

	}

	/**
	 * Assert that the secret key is configured before server-side validation.
	 */
	private function assertSecretKeyConfigured(): void {

		if ('' === $this->secretKey) {
			throw new PairException('Missing Google reCAPTCHA secret key. Set GOOGLE_RECAPTCHA_SECRET_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Assert that a reCAPTCHA token is present and within the local size guard.
	 */
	private function assertTokenFormat(string $token): void {

		if ('' === $token) {
			throw new PairException('Google reCAPTCHA token cannot be empty.', ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		if (strlen($token) > self::MAX_TOKEN_LENGTH) {
			throw new PairException('Google reCAPTCHA token exceeds the maximum length.', ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

	}

	/**
	 * Decode a JSON Siteverify response and normalize HTTP failures.
	 */
	private function decodeJsonResponse(int $statusCode, string $responseBody): array {

		$responseBody = trim($responseBody);

		if ('' === $responseBody) {
			throw new PairException('Google reCAPTCHA returned an empty response.', ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('Google reCAPTCHA returned an invalid JSON response.', ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->failureMessage($decodedResponse, $statusCode), ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Build a useful validation failure message.
	 */
	private function failureMessage(array $result, ?int $statusCode = null): string {

		$errorCodes = $result['error-codes'] ?? $result['error_codes'] ?? [];

		if (is_array($errorCodes) and count($errorCodes)) {
			return 'Google reCAPTCHA validation failed: ' . implode(', ', array_map('strval', $errorCodes));
		}

		if ($statusCode) {
			return 'Google reCAPTCHA request failed with HTTP ' . $statusCode . '.';
		}

		return 'Google reCAPTCHA validation failed.';

	}

	/**
	 * Render escaped HTML attributes, including boolean attributes.
	 */
	private function htmlAttributes(array $attributes): string {

		$html = '';

		foreach ($attributes as $name => $value) {

			if (is_null($value) or false === $value) {
				continue;
			}

			$name = trim((string)$name);
			if (!preg_match('/^[A-Za-z_:][A-Za-z0-9:._-]*$/', $name)) {
				continue;
			}

			$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

			if (true === $value) {
				$html .= ' ' . $name;
				continue;
			}

			$html .= ' ' . $name . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';

		}

		return $html;

	}

	/**
	 * Normalize nullable string values.
	 */
	private function normalizeNullableString(mixed $value): ?string {

		$value = trim((string)$value);

		return '' === $value ? null : $value;

	}

	/**
	 * Normalize Siteverify response keys while keeping provider fields intact.
	 */
	private function normalizeVerificationResponse(array $response): array {

		if (isset($response['error_codes']) and !isset($response['error-codes'])) {
			$response['error-codes'] = $response['error_codes'];
		}

		$response['success'] = (bool)($response['success'] ?? false);

		return $response;

	}

	/**
	 * Execute the Siteverify HTTP request.
	 */
	protected function requestSiteverify(array $payload): array {

		$curl = curl_init($this->verifyUrl);

		if (false === $curl) {
			throw new PairException('Unable to initialize Google reCAPTCHA request.', ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/x-www-form-urlencoded',
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Google reCAPTCHA request failed: ' . $error, ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Validate and normalize the response field name.
	 */
	private function sanitizeResponseField(string $responseField): string {

		$responseField = trim($responseField);

		if ('' === $responseField) {
			throw new PairException('GOOGLE_RECAPTCHA_RESPONSE_FIELD cannot be empty.', ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		return $responseField;

	}

	/**
	 * Validate and normalize the Siteverify URL.
	 */
	private function sanitizeVerifyUrl(string $verifyUrl): string {

		$verifyUrl = trim($verifyUrl);

		if ('' === $verifyUrl or !filter_var($verifyUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('GOOGLE_RECAPTCHA_VERIFY_URL is not valid.', ErrorCodes::GOOGLE_RECAPTCHA_ERROR);
		}

		return $verifyUrl;

	}

	/**
	 * Remove null values recursively from Siteverify payloads.
	 *
	 * @return	array<string, mixed>
	 */
	private function withoutNullValues(array $values): array {

		$filteredValues = [];

		foreach ($values as $key => $value) {

			if (is_null($value)) {
				continue;
			}

			if (is_array($value)) {
				$value = $this->withoutNullValues($value);
			}

			$filteredValues[$key] = $value;

		}

		return $filteredValues;

	}

	/**
	 * Convert widget options into data attributes.
	 */
	private function widgetDataAttributes(array $options): array {

		$attributes = [];

		foreach ($options as $name => $value) {

			if (in_array($name, ['class', 'id'], true)) {
				continue;
			}

			if (is_null($value) or false === $value) {
				continue;
			}

			$optionName = preg_replace('/(?<!^)[A-Z]/', '-$0', (string)$name) ?? (string)$name;
			$attributeName = 'data-' . strtolower(str_replace('_', '-', $optionName));
			if (!preg_match('/^data-[a-z0-9_.:-]+$/', $attributeName)) {
				continue;
			}

			$attributes[$attributeName] = true === $value ? 'true' : (string)$value;

		}

		return $attributes;

	}

}
