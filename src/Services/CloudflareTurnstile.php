<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Cloudflare Turnstile helper for widget rendering and server-side token validation.
 */
class CloudflareTurnstile {

	/**
	 * Official Turnstile browser script URL.
	 */
	private const API_SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 3;

	/**
	 * Default response field submitted by Turnstile widgets.
	 */
	private const DEFAULT_RESPONSE_FIELD = 'cf-turnstile-response';

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 10;

	/**
	 * Default server-side verification endpoint.
	 */
	private const DEFAULT_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Maximum documented Turnstile token length.
	 */
	private const MAX_TOKEN_LENGTH = 2048;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * POST field used to read Turnstile response tokens.
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
	 * Build a Turnstile helper using explicit values or Env defaults.
	 */
	public function __construct(?string $secretKey = null, ?string $siteKey = null, ?string $verifyUrl = null, ?int $timeout = null, ?int $connectTimeout = null, ?string $responseField = null) {

		$this->secretKey = trim((string)($secretKey ?? Env::get('CLOUDFLARE_TURNSTILE_SECRET_KEY')));
		$this->siteKey = trim((string)($siteKey ?? Env::get('CLOUDFLARE_TURNSTILE_SITE_KEY')));
		$this->verifyUrl = $this->sanitizeVerifyUrl((string)($verifyUrl ?? Env::get('CLOUDFLARE_TURNSTILE_VERIFY_URL') ?? self::DEFAULT_VERIFY_URL));
		$this->timeout = max(1, (int)($timeout ?? Env::get('CLOUDFLARE_TURNSTILE_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('CLOUDFLARE_TURNSTILE_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));
		$this->responseField = $this->sanitizeResponseField((string)($responseField ?? Env::get('CLOUDFLARE_TURNSTILE_RESPONSE_FIELD') ?? self::DEFAULT_RESPONSE_FIELD));

	}

	/**
	 * Assert that a POST payload contains a valid Turnstile token.
	 */
	public function assertPost(array $post, ?string $remoteIp = null, array $options = []): array {

		return $this->assertToken($this->tokenFromPost($post), $remoteIp, $options);

	}

	/**
	 * Assert that a Turnstile token is valid and return the verification response.
	 *
	 * @throws PairException
	 */
	public function assertToken(string $token, ?string $remoteIp = null, array $options = []): array {

		$result = $this->verifyToken($token, $remoteIp, $options);

		if (!($result['success'] ?? false)) {
			throw new PairException($this->failureMessage($result), ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		}

		return $result;

	}

	/**
	 * Return the official Turnstile script tag.
	 */
	public function scriptTag(bool $explicit = false, array $attributes = []): string {

		$attributes = array_merge([
			'src' => self::API_SCRIPT_URL . ($explicit ? '?render=explicit' : ''),
			'async' => !$explicit,
			'defer' => true,
		], $attributes);

		return '<script' . $this->htmlAttributes($attributes) . '></script>';

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
	 * Extract the configured Turnstile response token from a POST-like payload.
	 */
	public function tokenFromPost(array $post): string {

		return trim((string)($post[$this->responseField] ?? ''));

	}

	/**
	 * Verify a POST payload by extracting the configured Turnstile response field.
	 */
	public function verifyPost(array $post, ?string $remoteIp = null, array $options = []): array {

		return $this->verifyToken($this->tokenFromPost($post), $remoteIp, $options);

	}

	/**
	 * Verify a Turnstile token through Cloudflare Siteverify.
	 */
	public function verifyToken(string $token, ?string $remoteIp = null, array $options = []): array {

		$this->assertSecretKeyConfigured();

		$token = trim($token);
		$this->assertTokenFormat($token);

		$payload = [
			'secret' => $this->secretKey,
			'response' => $token,
			'remoteip' => $this->normalizeNullableString($remoteIp),
			'idempotency_key' => $this->normalizeNullableString($options['idempotency_key'] ?? $options['idempotencyKey'] ?? null),
		];

		return $this->normalizeVerificationResponse(
			$this->requestSiteverify($this->withoutNullValues($payload))
		);

	}

	/**
	 * Return the implicit-rendering Turnstile widget HTML.
	 */
	public function widgetHtml(array $options = []): string {

		$siteKey = trim((string)($options['sitekey'] ?? $options['siteKey'] ?? $this->siteKey));

		if ('' === $siteKey) {
			throw new PairException('Missing Cloudflare Turnstile site key. Set CLOUDFLARE_TURNSTILE_SITE_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

		unset($options['sitekey'], $options['siteKey']);

		$attributes = [
			'class' => trim('cf-turnstile ' . (string)($options['class'] ?? '')),
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
			throw new PairException('Missing Cloudflare Turnstile secret key. Set CLOUDFLARE_TURNSTILE_SECRET_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Assert that a Turnstile token is present and within the documented size limit.
	 */
	private function assertTokenFormat(string $token): void {

		if ('' === $token) {
			throw new PairException('Cloudflare Turnstile token cannot be empty.', ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		}

		if (strlen($token) > self::MAX_TOKEN_LENGTH) {
			throw new PairException('Cloudflare Turnstile token exceeds the maximum length.', ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		}

	}

	/**
	 * Decode a JSON Siteverify response and normalize HTTP failures.
	 */
	private function decodeJsonResponse(int $statusCode, string $responseBody): array {

		$responseBody = trim($responseBody);

		if ('' === $responseBody) {
			throw new PairException('Cloudflare Turnstile returned an empty response.', ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('Cloudflare Turnstile returned an invalid JSON response.', ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->failureMessage($decodedResponse, $statusCode), ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Build a useful validation failure message.
	 */
	private function failureMessage(array $result, ?int $statusCode = null): string {

		$errorCodes = $result['error-codes'] ?? $result['error_codes'] ?? [];

		if (is_array($errorCodes) and count($errorCodes)) {
			return 'Cloudflare Turnstile validation failed: ' . implode(', ', array_map('strval', $errorCodes));
		}

		if ($statusCode) {
			return 'Cloudflare Turnstile request failed with HTTP ' . $statusCode . '.';
		}

		return 'Cloudflare Turnstile validation failed.';

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
			throw new PairException('Unable to initialize Cloudflare Turnstile request.', ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
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
			throw new PairException('Cloudflare Turnstile request failed: ' . $error, ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
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
			throw new PairException('CLOUDFLARE_TURNSTILE_RESPONSE_FIELD cannot be empty.', ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
		}

		return $responseField;

	}

	/**
	 * Validate and normalize the Siteverify URL.
	 */
	private function sanitizeVerifyUrl(string $verifyUrl): string {

		$verifyUrl = trim($verifyUrl);

		if ('' === $verifyUrl or !filter_var($verifyUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('CLOUDFLARE_TURNSTILE_VERIFY_URL is not valid.', ErrorCodes::CLOUDFLARE_TURNSTILE_ERROR);
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
