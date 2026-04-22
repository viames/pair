<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Mailer;
use Pair\Http\JsonResponse;

/**
 * Resend-backed mailer for transactional email and webhook processing.
 */
class ResendMailer extends Mailer {

	/**
	 * Default Resend API base URL.
	 */
	private const DEFAULT_API_BASE_URL = 'https://api.resend.com';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default webhook replay tolerance in seconds.
	 */
	private const DEFAULT_WEBHOOK_TOLERANCE_SECONDS = 300;

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 20;

	/**
	 * Resend API key used for outbound requests.
	 */
	private string $apiKey = '';

	/**
	 * Resend REST API base URL.
	 */
	private string $apiBaseUrl = self::DEFAULT_API_BASE_URL;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;

	/**
	 * Default custom headers applied to transactional emails.
	 */
	private array $defaultHeaders = [];

	/**
	 * Default Resend tags applied to transactional emails.
	 */
	private array $defaultTags = [];

	/**
	 * Default reply-to address.
	 */
	private ?string $replyTo = null;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout = self::DEFAULT_TIMEOUT;

	/**
	 * Webhook signing secret used for Svix signature verification.
	 */
	private ?string $webhookSecret = null;

	/**
	 * Build a Resend mailer using explicit config or Env defaults.
	 *
	 * @param	array	$config	Mailer and Resend configuration.
	 */
	public function __construct(array $config = []) {

		$this->setConfig(array_merge($this->envConfig(), $config));
		$this->checkConfig();

	}

	/**
	 * Check whether a Resend API key is available.
	 */
	public function apiKeySet(): bool {

		return '' !== $this->apiKey;

	}

	/**
	 * Check if the required configuration is set. Throw an exception if not.
	 */
	public function checkConfig(): void {

		$this->checkBaseConfig();

		if ('' === $this->apiKey) {
			throw new PairException('Missing Resend API key. Set RESEND_API_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Decode a raw Resend webhook JSON payload.
	 *
	 * @throws PairException
	 */
	public function decodeWebhookPayload(string $payload): array {

		$payload = trim($payload);

		if ('' === $payload) {
			throw new PairException('Resend webhook payload cannot be empty.', ErrorCodes::RESEND_ERROR);
		}

		$decodedPayload = json_decode($payload, true);

		if (!is_array($decodedPayload)) {
			throw new PairException('Resend webhook payload is not valid JSON.', ErrorCodes::RESEND_ERROR);
		}

		return $decodedPayload;

	}

	/**
	 * Send a legacy Pair HTML email through Resend.
	 *
	 * @param	array		List of recipients as strings, arrays, or objects with email/name fields.
	 * @param	string		Email subject.
	 * @param	string		Content title.
	 * @param	string		Content text.
	 * @param	array		Optional attachment objects or arrays.
	 * @param	array		Optional carbon copy recipients.
	 *
	 * @throws PairException
	 */
	public function send(array $recipients, string $subject, string $title, string $text, array $attachments = [], array $ccs = []): void {

		$response = $this->sendTransactional([
			'to' => $this->formatRecipients($this->convertRecipients($recipients)),
			'cc' => $this->formatRecipients($this->convertCarbonCopy($ccs)),
			'subject' => $subject,
			'html' => $this->getBody($subject, $title, $text),
			'text' => trim(strip_tags($text)),
			'attachments' => $attachments,
		]);

		if (!isset($response['id'])) {
			throw new PairException('Resend response did not include an email id.', ErrorCodes::RESEND_ERROR);
		}

	}

	/**
	 * Send a transactional email through Resend and return the API response.
	 *
	 * @param	array	$message	Resend email payload.
	 * @param	array	$options	Optional request options such as idempotency_key.
	 *
	 * @throws PairException
	 */
	public function sendTransactional(array $message, array $options = []): array {

		$this->checkConfig();

		$payload = $this->normalizeEmailPayload($message);
		$headers = $this->requestHeaders($options);

		return $this->requestJson('POST', '/emails', $payload, $headers);

	}

	/**
	 * Set the configuration of the email sender.
	 *
	 * @param	array	$config	Associative mailer and Resend configuration.
	 */
	public function setConfig(array $config): void {

		$this->setBaseConfig($config);

		$this->apiKey = trim((string)($config['apiKey'] ?? $this->apiKey));
		$this->apiBaseUrl = $this->sanitizeApiBaseUrl((string)($config['apiBaseUrl'] ?? $this->apiBaseUrl));
		$this->timeout = max(1, (int)($config['timeout'] ?? $this->timeout));
		$this->connectTimeout = max(1, (int)($config['connectTimeout'] ?? $this->connectTimeout));
		$this->webhookSecret = $this->normalizeNullableString($config['webhookSecret'] ?? $this->webhookSecret);
		$this->replyTo = $this->normalizeNullableString($config['replyTo'] ?? $this->replyTo);

		if (isset($config['defaultTags']) and is_array($config['defaultTags'])) {
			$this->defaultTags = $config['defaultTags'];
		}

		if (isset($config['defaultHeaders']) and is_array($config['defaultHeaders'])) {
			$this->defaultHeaders = $config['defaultHeaders'];
		}

	}

	/**
	 * Verify a Resend webhook payload and return the decoded event.
	 *
	 * @throws PairException
	 */
	public function verifyWebhookPayload(string $payload, ?array $headers = null, ?int $toleranceSeconds = null): array {

		if (!$this->webhookSecret) {
			throw new PairException('Missing Resend webhook secret. Set RESEND_WEBHOOK_SECRET.', ErrorCodes::MISSING_CONFIGURATION);
		}

		$headers = $this->normalizeWebhookHeaders($headers ?? $this->serverWebhookHeaders());
		$id = $headers['svix-id'] ?? '';
		$timestamp = $headers['svix-timestamp'] ?? '';
		$signature = $headers['svix-signature'] ?? '';

		if ('' === $id or '' === $timestamp or '' === $signature) {
			throw new PairException('Missing Resend webhook signature headers.', ErrorCodes::RESEND_ERROR);
		}

		$this->assertWebhookTimestamp($timestamp, $toleranceSeconds ?? self::DEFAULT_WEBHOOK_TOLERANCE_SECONDS);
		$this->assertWebhookSignature($payload, $id, $timestamp, $signature);

		return $this->decodeWebhookPayload($payload);

	}

	/**
	 * Verify a Resend webhook and run a type-specific handler when provided.
	 *
	 * @param	array<string, callable>	$handlers	Handlers keyed by Resend event type or "*" fallback.
	 */
	public function webhookResponse(string $payload, ?array $headers = null, array $handlers = []): JsonResponse {

		$event = $this->verifyWebhookPayload($payload, $headers);

		return $this->webhookResponseFromEvent($event, $handlers);

	}

	/**
	 * Run a handler for an already verified Resend event.
	 *
	 * @param	array<string, callable>	$handlers	Handlers keyed by Resend event type or "*" fallback.
	 */
	public function webhookResponseFromEvent(array $event, array $handlers = []): JsonResponse {

		$type = $this->webhookEventType($event);
		$handler = $handlers[$type] ?? $handlers['*'] ?? null;

		if ($handler) {

			if (!is_callable($handler)) {
				throw new \InvalidArgumentException('Resend webhook handler for "' . $type . '" must be callable.');
			}

			$handler($event);

		}

		return new JsonResponse([
			'received' => true,
			'type' => $type,
		]);

	}

	/**
	 * Assert that a webhook signature matches the Svix signing format used by Resend.
	 *
	 * @throws PairException
	 */
	private function assertWebhookSignature(string $payload, string $id, string $timestamp, string $signatureHeader): void {

		$secret = $this->webhookSecretBytes((string)$this->webhookSecret);
		$signedContent = $id . '.' . $timestamp . '.' . $payload;
		$expectedSignature = base64_encode(hash_hmac('sha256', $signedContent, $secret, true));

		foreach (preg_split('/\s+/', trim($signatureHeader)) ?: [] as $signaturePart) {

			if (!str_starts_with($signaturePart, 'v1,')) {
				continue;
			}

			$providedSignature = substr($signaturePart, 3);

			if (hash_equals($expectedSignature, $providedSignature)) {
				return;
			}

		}

		throw new PairException('Invalid Resend webhook signature.', ErrorCodes::RESEND_ERROR);

	}

	/**
	 * Assert that the webhook timestamp is recent enough to reduce replay risk.
	 *
	 * @throws PairException
	 */
	private function assertWebhookTimestamp(string $timestamp, int $toleranceSeconds): void {

		if (!ctype_digit($timestamp)) {
			throw new PairException('Invalid Resend webhook timestamp.', ErrorCodes::RESEND_ERROR);
		}

		if ($toleranceSeconds > 0 and abs(time() - (int)$timestamp) > $toleranceSeconds) {
			throw new PairException('Expired Resend webhook timestamp.', ErrorCodes::RESEND_ERROR);
		}

	}

	/**
	 * Decode a JSON API response and normalize HTTP failures.
	 *
	 * @throws PairException
	 */
	private function decodeJsonResponse(int $statusCode, string $responseBody): array {

		$responseBody = trim($responseBody);

		if ('' === $responseBody) {

			if ($statusCode >= 400) {
				throw new PairException('Resend request failed with HTTP ' . $statusCode . '.', ErrorCodes::RESEND_ERROR);
			}

			return [];

		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('Resend returned an invalid JSON response.', ErrorCodes::RESEND_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->resolveErrorMessage($decodedResponse, $statusCode), ErrorCodes::RESEND_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Return default configuration from Env.
	 */
	private function envConfig(): array {

		return [
			'apiKey' => Env::get('RESEND_API_KEY'),
			'apiBaseUrl' => Env::get('RESEND_API_BASE_URL') ?: self::DEFAULT_API_BASE_URL,
			'fromAddress' => Env::get('RESEND_FROM_ADDRESS'),
			'fromName' => Env::get('RESEND_FROM_NAME') ?: Env::get('APP_NAME'),
			'timeout' => Env::get('RESEND_TIMEOUT') ?: self::DEFAULT_TIMEOUT,
			'connectTimeout' => Env::get('RESEND_CONNECT_TIMEOUT') ?: self::DEFAULT_CONNECT_TIMEOUT,
			'webhookSecret' => Env::get('RESEND_WEBHOOK_SECRET'),
		];

	}

	/**
	 * Format a sender or recipient object into the address string accepted by Resend.
	 */
	private function formatAddress(object $recipient): string {

		$email = trim((string)($recipient->email ?? ''));
		$name = trim((string)($recipient->name ?? ''));

		if ('' === $email) {
			throw new PairException('Resend recipient email cannot be empty.', ErrorCodes::RESEND_ERROR);
		}

		return '' === $name ? $email : $name . ' <' . $email . '>';

	}

	/**
	 * Format a recipient list for Resend.
	 */
	private function formatRecipients(array $recipients): array {

		return array_values(array_map(function (object $recipient): string {
			return $this->formatAddress($recipient);
		}, $recipients));

	}

	/**
	 * Normalize one attachment into the Resend API shape.
	 */
	private function normalizeAttachment(mixed $attachment): array {

		$attachment = is_object($attachment) ? (array)$attachment : $attachment;

		if (!is_array($attachment)) {
			throw new PairException('Resend attachment must be an array or object.', ErrorCodes::RESEND_ERROR);
		}

		$filename = trim((string)($attachment['filename'] ?? $attachment['name'] ?? ''));
		$path = trim((string)($attachment['path'] ?? ''));
		$filePath = trim((string)($attachment['filePath'] ?? ''));
		$content = $attachment['content'] ?? null;

		if ('' === $filename) {
			throw new PairException('Resend attachment filename cannot be empty.', ErrorCodes::RESEND_ERROR);
		}

		if (is_null($content) and '' !== $filePath) {

			if (!is_readable($filePath)) {
				throw new PairException('Resend attachment file is not readable: ' . $filePath, ErrorCodes::RESEND_ERROR);
			}

			$content = base64_encode((string)file_get_contents($filePath));

		}

		$normalizedAttachment = [
			'filename' => $filename,
			'content' => $content,
			'path' => '' !== $path ? $path : null,
			'content_id' => $attachment['content_id'] ?? $attachment['contentId'] ?? null,
		];

		if (is_null($normalizedAttachment['content']) and is_null($normalizedAttachment['path'])) {
			throw new PairException('Resend attachment must include content, path, or filePath.', ErrorCodes::RESEND_ERROR);
		}

		return $this->withoutNullValues($normalizedAttachment);

	}

	/**
	 * Normalize the Resend email payload.
	 */
	private function normalizeEmailPayload(array $message): array {

		$message['from'] = $message['from'] ?? $this->formatAddress((object)[
			'name' => $this->fromName,
			'email' => $this->fromAddress,
		]);
		$message['reply_to'] = $message['reply_to'] ?? $message['replyTo'] ?? $this->replyTo;
		unset($message['replyTo']);

		if (isset($message['attachments']) and is_array($message['attachments'])) {
			$message['attachments'] = array_values(array_map(function (mixed $attachment): array {
				return $this->normalizeAttachment($attachment);
			}, $message['attachments']));
		}

		$message['headers'] = array_merge($this->defaultHeaders, is_array($message['headers'] ?? null) ? $message['headers'] : []);
		$message['tags'] = $this->normalizeTags(array_merge($this->defaultTags, $message['tags'] ?? []));

		$this->assertEmailPayload($message);

		return $this->withoutNullValues($message);

	}

	/**
	 * Normalize Resend tag input into a list of name/value pairs.
	 */
	private function normalizeTags(array $tags): array {

		$normalizedTags = [];

		foreach ($tags as $key => $value) {

			if (is_array($value) and isset($value['name'], $value['value'])) {
				$name = (string)$value['name'];
				$tagValue = (string)$value['value'];
			} else {
				$name = (string)$key;
				$tagValue = (string)$value;
			}

			if ('' === trim($name) and '' === trim($tagValue)) {
				continue;
			}

			$this->assertTagPart($name, 'name');
			$this->assertTagPart($tagValue, 'value');

			$normalizedTags[] = [
				'name' => $name,
				'value' => $tagValue,
			];

		}

		return $normalizedTags;

	}

	/**
	 * Normalize nullable string configuration values.
	 */
	private function normalizeNullableString(mixed $value): ?string {

		$value = trim((string)$value);

		return '' === $value ? null : $value;

	}

	/**
	 * Normalize webhook header names to lowercase HTTP header keys.
	 */
	private function normalizeWebhookHeaders(array $headers): array {

		$normalizedHeaders = [];

		foreach ($headers as $name => $value) {
			$normalizedHeaders[strtolower(str_replace('_', '-', (string)$name))] = is_array($value) ? (string)reset($value) : (string)$value;
		}

		return $normalizedHeaders;

	}

	/**
	 * Execute an authenticated JSON request against the Resend API.
	 *
	 * @throws PairException
	 */
	protected function requestJson(string $method, string $path, array $payload = [], array $headers = []): array {

		$curl = curl_init($this->apiBaseUrl . '/' . ltrim($path, '/'));

		if (false === $curl) {
			throw new PairException('Unable to initialize Resend request.', ErrorCodes::RESEND_ERROR);
		}

		$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === $encodedPayload) {
			curl_close($curl);
			throw new PairException('Unable to encode Resend request payload.', ErrorCodes::RESEND_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper(trim($method)));
		curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge([
			'Accept: application/json',
			'Authorization: Bearer ' . $this->apiKey,
			'Content-Type: application/json',
		], $headers));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Resend request failed: ' . $error, ErrorCodes::RESEND_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Resolve the most useful API error message from a Resend response.
	 */
	private function resolveErrorMessage(array $response, int $statusCode): string {

		if (isset($response['message']) and is_string($response['message']) and '' !== trim($response['message'])) {
			return trim($response['message']);
		}

		if (isset($response['error']) and is_string($response['error']) and '' !== trim($response['error'])) {
			return trim($response['error']);
		}

		if (isset($response['error']) and is_array($response['error'])) {
			$message = trim((string)($response['error']['message'] ?? ''));

			if ('' !== $message) {
				return 'Resend API error: ' . $message;
			}
		}

		return 'Resend request failed with HTTP ' . $statusCode . '.';

	}

	/**
	 * Build optional Resend request headers.
	 */
	private function requestHeaders(array $options): array {

		$headers = [];
		$idempotencyKey = trim((string)($options['idempotency_key'] ?? $options['idempotencyKey'] ?? ''));

		if ('' !== $idempotencyKey) {
			$headers[] = 'Idempotency-Key: ' . $idempotencyKey;
		}

		return $headers;

	}

	/**
	 * Validate and normalize the API base URL.
	 */
	private function sanitizeApiBaseUrl(string $apiBaseUrl): string {

		$apiBaseUrl = rtrim(trim($apiBaseUrl), '/');

		if ('' === $apiBaseUrl or !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('RESEND_API_BASE_URL is not valid.', ErrorCodes::RESEND_ERROR);
		}

		return $apiBaseUrl;

	}

	/**
	 * Read Svix webhook headers from the current HTTP request.
	 */
	private function serverWebhookHeaders(): array {

		return [
			'svix-id' => $_SERVER['HTTP_SVIX_ID'] ?? '',
			'svix-timestamp' => $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '',
			'svix-signature' => $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '',
		];

	}

	/**
	 * Validate the required transactional email fields.
	 *
	 * @throws PairException
	 */
	private function assertEmailPayload(array $message): void {

		foreach (['from', 'to', 'subject'] as $field) {
			if (empty($message[$field])) {
				throw new PairException('Resend email payload is missing "' . $field . '".', ErrorCodes::RESEND_ERROR);
			}
		}

		if (empty($message['html']) and !array_key_exists('text', $message) and empty($message['template'])) {
			throw new PairException('Resend email payload requires html, text, or template.', ErrorCodes::RESEND_ERROR);
		}

	}

	/**
	 * Validate one Resend tag field.
	 *
	 * @throws PairException
	 */
	private function assertTagPart(string $value, string $label): void {

		if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/', $value)) {
			throw new PairException('Resend tag ' . $label . ' is not valid: ' . $value, ErrorCodes::RESEND_ERROR);
		}

	}

	/**
	 * Remove null values and empty arrays recursively from request payloads.
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

				if (!count($value)) {
					continue;
				}
			}

			$filteredValues[$key] = $value;

		}

		return $filteredValues;

	}

	/**
	 * Resolve the webhook event type from a Resend event.
	 */
	private function webhookEventType(array $event): string {

		return (string)($event['type'] ?? $event['event'] ?? '');

	}

	/**
	 * Decode the Svix signing secret used by Resend.
	 */
	private function webhookSecretBytes(string $secret): string {

		$secret = trim($secret);

		if (str_starts_with($secret, 'whsec_')) {
			$decodedSecret = base64_decode(substr($secret, 6), true);

			if (false !== $decodedSecret) {
				return $decodedSecret;
			}
		}

		return $secret;

	}

}
