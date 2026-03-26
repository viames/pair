<?php

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Lightweight client for Meta WhatsApp Business Platform / Cloud API.
 * It supports outbound messages, media management, and webhook helpers.
 */
class WhatsAppCloudClient {

	/**
	 * Default Graph API base URL.
	 */
	private const DEFAULT_API_BASE_URL = 'https://graph.facebook.com';

	/**
	 * Default Graph API version used for requests.
	 */
	private const DEFAULT_API_VERSION = 'v23.0';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 20;

	/**
	 * Media-capable WhatsApp message types.
	 */
	private const MEDIA_MESSAGE_TYPES = ['audio', 'document', 'image', 'sticker', 'video'];

	/**
	 * Base URL for Graph API requests.
	 */
	private string $apiBaseUrl;

	/**
	 * Graph API version.
	 */
	private string $apiVersion;

	/**
	 * Long-lived or system user access token.
	 */
	private string $accessToken;

	/**
	 * Meta app secret used to verify webhook signatures.
	 */
	private ?string $appSecret;

	/**
	 * Connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Business phone number ID used for messaging and media upload endpoints.
	 */
	private ?string $phoneNumberId;

	/**
	 * Request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Shared verify token used during webhook challenge validation.
	 */
	private ?string $webhookVerifyToken;

	/**
	 * Build a new client using explicit arguments or Env values.
	 *
	 * Required for outbound API calls:
	 * - WHATSAPP_CLOUD_ACCESS_TOKEN
	 * - WHATSAPP_CLOUD_PHONE_NUMBER_ID
	 *
	 * Optional Env keys:
	 * - WHATSAPP_CLOUD_API_VERSION
	 * - WHATSAPP_CLOUD_API_BASE_URL
	 * - WHATSAPP_CLOUD_TIMEOUT
	 * - WHATSAPP_CLOUD_CONNECT_TIMEOUT
	 * - WHATSAPP_CLOUD_WEBHOOK_VERIFY_TOKEN
	 * - WHATSAPP_CLOUD_APP_SECRET
	 *
	 * Webhook-only use cases can instantiate the client without access token and phone number ID,
	 * as long as the outbound methods are not called.
	 */
	public function __construct(?string $accessToken = null, ?string $phoneNumberId = null, ?string $apiVersion = null, ?string $apiBaseUrl = null, ?int $timeout = null, ?int $connectTimeout = null, ?string $webhookVerifyToken = null, ?string $appSecret = null) {

		$this->accessToken = trim((string)($accessToken ?? Env::get('WHATSAPP_CLOUD_ACCESS_TOKEN')));
		$this->phoneNumberId = $this->normalizeNullableString($phoneNumberId ?? Env::get('WHATSAPP_CLOUD_PHONE_NUMBER_ID'));
		$this->apiVersion = $this->sanitizeApiVersion((string)($apiVersion ?? Env::get('WHATSAPP_CLOUD_API_VERSION') ?? self::DEFAULT_API_VERSION));
		$this->apiBaseUrl = $this->sanitizeApiBaseUrl((string)($apiBaseUrl ?? Env::get('WHATSAPP_CLOUD_API_BASE_URL') ?? self::DEFAULT_API_BASE_URL));
		$this->timeout = max(1, (int)($timeout ?? Env::get('WHATSAPP_CLOUD_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('WHATSAPP_CLOUD_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));
		$this->webhookVerifyToken = $this->normalizeNullableString($webhookVerifyToken ?? Env::get('WHATSAPP_CLOUD_WEBHOOK_VERIFY_TOKEN'));
		$this->appSecret = $this->normalizeNullableString($appSecret ?? Env::get('WHATSAPP_CLOUD_APP_SECRET'));

	}

	/**
	 * Check whether an access token is available for outbound requests.
	 */
	public function accessTokenSet(): bool {

		return '' !== $this->accessToken;

	}

	/**
	 * Assert that the webhook signature is valid for the given payload.
	 *
	 * @throws PairException
	 */
	public function assertWebhookSignature(string $payload, ?string $signatureHeader = null): void {

		if (!$this->verifyWebhookSignature($payload, $signatureHeader)) {
			throw new PairException('Invalid WhatsApp webhook signature.', ErrorCodes::WHATSAPP_ERROR);
		}

	}

	/**
	 * Decode a raw webhook JSON payload.
	 *
	 * @throws PairException
	 */
	public function decodeWebhookPayload(string $payload): array {

		$payload = trim($payload);

		if ('' === $payload) {
			throw new PairException('WhatsApp webhook payload cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		$decodedPayload = json_decode($payload, true);

		if (!is_array($decodedPayload)) {
			throw new PairException('WhatsApp webhook payload is not valid JSON.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $decodedPayload;

	}

	/**
	 * Delete a previously uploaded media asset from Meta storage.
	 */
	public function deleteMedia(string $mediaId): bool {

		$mediaId = $this->sanitizeGraphId($mediaId, 'media');
		$response = $this->requestJson('DELETE', '/' . $this->encodeSegment($mediaId));

		return (bool)($response['success'] ?? false);

	}

	/**
	 * Download media content referenced by its media ID and save it to disk.
	 *
	 * @return	array	Metadata plus the destination path.
	 */
	public function downloadMediaToPath(string $mediaId, string $destinationPath): array {

		$media = $this->getMediaMetadata($mediaId);
		$this->downloadBinaryToPath($this->extractMediaDownloadUrl($media), $destinationPath);
		$media['path'] = $destinationPath;

		return $media;

	}

	/**
	 * Download media content referenced by its media ID and return the raw bytes.
	 */
	public function downloadMediaContents(string $mediaId): string {

		$media = $this->getMediaMetadata($mediaId);

		return $this->downloadBinary($this->extractMediaDownloadUrl($media));

	}

	/**
	 * Extract a normalized list of webhook events from the nested Meta payload.
	 *
	 * Each event is returned as an associative array with:
	 * - event: message, status, or raw
	 * - metadata: phone number metadata
	 * - raw: the original change payload
	 */
	public function extractWebhookEvents(array $payload): array {

		$events = [];
		$entries = $payload['entry'] ?? [];
		$object = is_string($payload['object'] ?? null) ? $payload['object'] : null;

		if (!is_array($entries)) {
			return $events;
		}

		foreach ($entries as $entryIndex => $entry) {

			if (!is_array($entry)) {
				continue;
			}

			$changes = $entry['changes'] ?? [];

			if (!is_array($changes)) {
				continue;
			}

			foreach ($changes as $changeIndex => $change) {

				if (!is_array($change)) {
					continue;
				}

				$value = is_array($change['value'] ?? null) ? $change['value'] : [];
				$metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
				$contacts = is_array($value['contacts'] ?? null) ? $value['contacts'] : [];
				$messages = is_array($value['messages'] ?? null) ? $value['messages'] : [];
				$statuses = is_array($value['statuses'] ?? null) ? $value['statuses'] : [];
				$baseEvent = [
					'object' => $object,
					'entry_id' => $entry['id'] ?? null,
					'entry_index' => $entryIndex,
					'change_field' => $change['field'] ?? null,
					'change_index' => $changeIndex,
					'metadata' => $metadata,
					'contacts' => $contacts,
					'raw' => $change,
				];

				foreach ($messages as $message) {

					if (!is_array($message)) {
						continue;
					}

					$events[] = array_merge($baseEvent, [
						'event' => 'message',
						'message_id' => $message['id'] ?? null,
						'from' => $message['from'] ?? null,
						'timestamp' => $message['timestamp'] ?? null,
						'message_type' => $message['type'] ?? null,
						'message' => $message,
					]);

				}

				foreach ($statuses as $status) {

					if (!is_array($status)) {
						continue;
					}

					$events[] = array_merge($baseEvent, [
						'event' => 'status',
						'message_id' => $status['id'] ?? null,
						'recipient_id' => $status['recipient_id'] ?? null,
						'timestamp' => $status['timestamp'] ?? null,
						'delivery_status' => $status['status'] ?? null,
						'status' => $status,
					]);

				}

				// Keep non-message changes visible so callers can still inspect unknown event types.
				if (!count($messages) and !count($statuses)) {
					$events[] = array_merge($baseEvent, [
						'event' => 'raw',
						'value' => $value,
					]);
				}

			}

		}

		return $events;

	}

	/**
	 * Fetch media metadata, including the temporary download URL.
	 */
	public function getMediaMetadata(string $mediaId): array {

		$mediaId = $this->sanitizeGraphId($mediaId, 'media');

		return $this->requestJson('GET', '/' . $this->encodeSegment($mediaId));

	}

	/**
	 * Check whether a phone number ID is available for messaging endpoints.
	 */
	public function phoneNumberIdSet(): bool {

		return !is_null($this->phoneNumberId) and '' !== $this->phoneNumberId;

	}

	/**
	 * Send a raw message payload to the WhatsApp messages endpoint.
	 */
	public function sendMessage(array $payload): array {

		$this->assertMessagingConfiguration();

		$payload['messaging_product'] = 'whatsapp';

		return $this->requestJson('POST', '/' . $this->encodeSegment((string)$this->phoneNumberId) . '/messages', [], $payload);

	}

	/**
	 * Send a media message.
	 *
	 * The $media array must contain either:
	 * - id => uploaded media ID
	 * - link => externally hosted file URL
	 *
	 * Optional keys:
	 * - caption
	 * - filename (document only)
	 * - provider
	 */
	public function sendMedia(string $to, string $mediaType, array $media, array $options = []): array {

		$to = $this->sanitizeRecipient($to);
		$mediaType = $this->sanitizeMediaType($mediaType);
		$mediaPayload = $this->normalizeMediaPayload($mediaType, $media);
		$payload = [
			'to' => $to,
			'type' => $mediaType,
			$mediaType => $mediaPayload,
		];

		$payload = $this->appendCommonMessageOptions($payload, $options);

		return $this->sendMessage($payload);

	}

	/**
	 * Send a template message.
	 *
	 * Components should follow Meta template component format.
	 */
	public function sendTemplate(string $to, string $templateName, string $languageCode, array $components = [], array $options = []): array {

		$to = $this->sanitizeRecipient($to);
		$templateName = trim($templateName);
		$languageCode = trim($languageCode);

		if ('' === $templateName) {
			throw new PairException('WhatsApp template name cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		if ('' === $languageCode) {
			throw new PairException('WhatsApp template language code cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		$template = [
			'name' => $templateName,
			'language' => [
				'code' => $languageCode,
			],
		];

		if (count($components)) {
			$template['components'] = array_values($components);
		}

		$payload = [
			'to' => $to,
			'type' => 'template',
			'template' => $template,
		];

		$payload = $this->appendCommonMessageOptions($payload, $options);

		return $this->sendMessage($payload);

	}

	/**
	 * Send a plain text message.
	 */
	public function sendText(string $to, string $body, array $options = []): array {

		$to = $this->sanitizeRecipient($to);
		$body = trim($body);

		if ('' === $body) {
			throw new PairException('WhatsApp message body cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		$text = [
			'body' => $body,
		];

		if (array_key_exists('preview_url', $options)) {
			$text['preview_url'] = (bool)$options['preview_url'];
		}

		$payload = [
			'to' => $to,
			'type' => 'text',
			'text' => $text,
		];

		$payload = $this->appendCommonMessageOptions($payload, $options);

		return $this->sendMessage($payload);

	}

	/**
	 * Upload a local media file to Meta and return the API response.
	 */
	public function uploadMedia(string $filePath, ?string $mimeType = null, ?string $fileName = null): array {

		$this->assertMessagingConfiguration();

		$filePath = trim($filePath);

		if ('' === $filePath) {
			throw new PairException('WhatsApp media file path cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		if (!file_exists($filePath) or !is_readable($filePath)) {
			throw new PairException('WhatsApp media file not found or not readable: ' . $filePath, ErrorCodes::WHATSAPP_ERROR);
		}

		$resolvedMimeType = $this->resolveMimeType($filePath, $mimeType);
		$resolvedFileName = trim((string)($fileName ?? basename($filePath)));

		$fields = [
			'messaging_product' => 'whatsapp',
			'file' => new \CURLFile($filePath, $resolvedMimeType, $resolvedFileName),
		];

		return $this->requestMultipart('POST', '/' . $this->encodeSegment((string)$this->phoneNumberId) . '/media', [], $fields);

	}

	/**
	 * Check whether a webhook app secret is configured.
	 */
	public function webhookAppSecretSet(): bool {

		return !is_null($this->appSecret) and '' !== $this->appSecret;

	}

	/**
	 * Check whether a webhook verify token is configured.
	 */
	public function webhookVerifyTokenSet(): bool {

		return !is_null($this->webhookVerifyToken) and '' !== $this->webhookVerifyToken;

	}

	/**
	 * Validate the verification challenge sent by Meta during webhook setup.
	 *
	 * If arguments are not provided, values are resolved from the current query string.
	 *
	 * @throws PairException
	 */
	public function verifyWebhookChallenge(?string $mode = null, ?string $verifyToken = null, ?string $challenge = null): string {

		$this->assertWebhookVerifyTokenConfigured();

		$mode = trim((string)($mode ?? self::webhookQueryValue('hub.mode')));
		$verifyToken = trim((string)($verifyToken ?? self::webhookQueryValue('hub.verify_token')));
		$challenge = trim((string)($challenge ?? self::webhookQueryValue('hub.challenge')));

		if ('subscribe' !== $mode) {
			throw new PairException('Unsupported WhatsApp webhook mode.', ErrorCodes::WHATSAPP_ERROR);
		}

		if ($verifyToken !== $this->webhookVerifyToken) {
			throw new PairException('Invalid WhatsApp webhook verify token.', ErrorCodes::WHATSAPP_ERROR);
		}

		if ('' === $challenge) {
			throw new PairException('Missing WhatsApp webhook challenge value.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $challenge;

	}

	/**
	 * Verify the Meta webhook signature header against the raw payload.
	 *
	 * If the header is not explicitly provided, HTTP_X_HUB_SIGNATURE_256 is used.
	 *
	 * @throws PairException
	 */
	public function verifyWebhookSignature(string $payload, ?string $signatureHeader = null): bool {

		$this->assertWebhookAppSecretConfigured();

		$signatureHeader = trim((string)($signatureHeader ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''));

		if ('' === $signatureHeader or !str_starts_with($signatureHeader, 'sha256=')) {
			return false;
		}

		$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, (string)$this->appSecret);

		return hash_equals($expectedSignature, $signatureHeader);

	}

	/**
	 * Append the common top-level WhatsApp message options supported by Pair.
	 */
	private function appendCommonMessageOptions(array $payload, array $options): array {

		$payload['recipient_type'] = $payload['recipient_type'] ?? 'individual';

		if (isset($options['recipient_type']) and is_string($options['recipient_type']) and '' !== trim($options['recipient_type'])) {
			$payload['recipient_type'] = trim($options['recipient_type']);
		}

		if (isset($options['context']) and is_array($options['context'])) {
			$payload['context'] = $options['context'];
		}

		if (isset($options['reply_to_message_id']) and is_string($options['reply_to_message_id']) and '' !== trim($options['reply_to_message_id'])) {
			$payload['context'] = $payload['context'] ?? [];
			$payload['context']['message_id'] = trim($options['reply_to_message_id']);
		}

		if (isset($options['biz_opaque_callback_data']) and is_scalar($options['biz_opaque_callback_data'])) {
			$payload['biz_opaque_callback_data'] = (string)$options['biz_opaque_callback_data'];
		}

		return $payload;

	}

	/**
	 * Assert that outbound messaging configuration is present.
	 *
	 * @throws PairException
	 */
	private function assertMessagingConfiguration(): void {

		$this->assertAccessTokenConfigured();

		if (!$this->phoneNumberIdSet()) {
			throw new PairException('Missing WhatsApp phone number ID. Set WHATSAPP_CLOUD_PHONE_NUMBER_ID.', ErrorCodes::WHATSAPP_ERROR);
		}

	}

	/**
	 * Assert that an access token is configured.
	 *
	 * @throws PairException
	 */
	private function assertAccessTokenConfigured(): void {

		if (!$this->accessTokenSet()) {
			throw new PairException('Missing WhatsApp access token. Set WHATSAPP_CLOUD_ACCESS_TOKEN.', ErrorCodes::WHATSAPP_ERROR);
		}

	}

	/**
	 * Assert that a webhook app secret is configured.
	 *
	 * @throws PairException
	 */
	private function assertWebhookAppSecretConfigured(): void {

		if (!$this->webhookAppSecretSet()) {
			throw new PairException('Missing WhatsApp webhook app secret. Set WHATSAPP_CLOUD_APP_SECRET.', ErrorCodes::WHATSAPP_ERROR);
		}

	}

	/**
	 * Assert that a webhook verify token is configured.
	 *
	 * @throws PairException
	 */
	private function assertWebhookVerifyTokenConfigured(): void {

		if (!$this->webhookVerifyTokenSet()) {
			throw new PairException('Missing WhatsApp webhook verify token. Set WHATSAPP_CLOUD_WEBHOOK_VERIFY_TOKEN.', ErrorCodes::WHATSAPP_ERROR);
		}

	}

	/**
	 * Build an absolute Graph API URL.
	 */
	private function buildApiUrl(string $path, array $query = []): string {

		$url = rtrim($this->apiBaseUrl, '/') . '/' . $this->apiVersion . '/' . ltrim($path, '/');

		if (!count($query)) {
			return $url;
		}

		return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);

	}

	/**
	 * Download an authenticated binary resource and return the raw body.
	 *
	 * @throws PairException
	 */
	private function downloadBinary(string $url): string {

		$this->assertAccessTokenConfigured();

		$curl = curl_init($url);

		if (false === $curl) {
			throw new PairException('Unable to initialize WhatsApp media download request.', ErrorCodes::WHATSAPP_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $this->accessToken,
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('WhatsApp media download failed: ' . $error, ErrorCodes::WHATSAPP_ERROR);
		}

		curl_close($curl);

		if ($statusCode >= 400) {
			$this->throwHttpException($statusCode, $responseBody);
		}

		return $responseBody;

	}

	/**
	 * Download an authenticated binary resource and save it to disk.
	 *
	 * @throws PairException
	 */
	private function downloadBinaryToPath(string $url, string $destinationPath): void {

		$destinationPath = trim($destinationPath);

		if ('' === $destinationPath) {
			throw new PairException('WhatsApp media destination path cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		$directory = dirname($destinationPath);

		if (!is_dir($directory) or !is_writable($directory)) {
			throw new PairException('WhatsApp media destination directory is not writable: ' . $directory, ErrorCodes::WHATSAPP_ERROR);
		}

		$binary = $this->downloadBinary($url);

		if (false === file_put_contents($destinationPath, $binary)) {
			throw new PairException('Unable to write WhatsApp media file to ' . $destinationPath . '.', ErrorCodes::WHATSAPP_ERROR);
		}

	}

	/**
	 * Extract the temporary download URL from media metadata.
	 *
	 * @throws PairException
	 */
	private function extractMediaDownloadUrl(array $media): string {

		$downloadUrl = trim((string)($media['url'] ?? ''));

		if ('' === $downloadUrl) {
			throw new PairException('WhatsApp media metadata does not include a download URL.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $downloadUrl;

	}

	/**
	 * Encode a Graph path segment.
	 */
	private function encodeSegment(string $segment): string {

		return rawurlencode($segment);

	}

	/**
	 * Normalize a nullable string, returning null when it is empty after trim.
	 */
	private function normalizeNullableString(mixed $value): ?string {

		$value = trim((string)$value);

		return '' === $value ? null : $value;

	}

	/**
	 * Normalize a media payload according to Meta media message rules.
	 *
	 * @throws PairException
	 */
	private function normalizeMediaPayload(string $mediaType, array $media): array {

		$mediaId = trim((string)($media['id'] ?? ''));
		$link = trim((string)($media['link'] ?? ''));

		if (($mediaId === '') and ($link === '')) {
			throw new PairException('WhatsApp media payload must include either id or link.', ErrorCodes::WHATSAPP_ERROR);
		}

		if (($mediaId !== '') and ($link !== '')) {
			throw new PairException('WhatsApp media payload cannot include both id and link.', ErrorCodes::WHATSAPP_ERROR);
		}

		$payload = [];

		if ($mediaId !== '') {
			$payload['id'] = $mediaId;
		} else {
			$payload['link'] = $link;
		}

		// Captions are allowed only on document, image, and video messages.
		if (isset($media['caption']) and in_array($mediaType, ['document', 'image', 'video'], true)) {
			$caption = trim((string)$media['caption']);

			if ('' !== $caption) {
				$payload['caption'] = $caption;
			}
		}

		if (isset($media['provider']) and is_string($media['provider']) and '' !== trim($media['provider'])) {
			$payload['provider'] = trim($media['provider']);
		}

		if ('document' === $mediaType and isset($media['filename'])) {
			$filename = trim((string)$media['filename']);

			if ('' !== $filename) {
				$payload['filename'] = $filename;
			}
		}

		return $payload;

	}

	/**
	 * Perform a multipart/form-data request.
	 *
	 * @throws PairException
	 */
	private function requestMultipart(string $method, string $path, array $query = [], array $fields = []): array {

		$this->assertAccessTokenConfigured();

		$url = $this->buildApiUrl($path, $query);
		$curl = curl_init($url);

		if (false === $curl) {
			throw new PairException('Unable to initialize WhatsApp multipart request.', ErrorCodes::WHATSAPP_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper(trim($method)));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Authorization: Bearer ' . $this->accessToken,
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('WhatsApp multipart request failed: ' . $error, ErrorCodes::WHATSAPP_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Perform a JSON request against the Graph API.
	 *
	 * @throws PairException
	 */
	private function requestJson(string $method, string $path, array $query = [], ?array $payload = null): array {

		$this->assertAccessTokenConfigured();

		$url = $this->buildApiUrl($path, $query);
		$curl = curl_init($url);

		if (false === $curl) {
			throw new PairException('Unable to initialize WhatsApp request.', ErrorCodes::WHATSAPP_ERROR);
		}

		$headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $this->accessToken,
		];

		$encodedPayload = null;

		if (!is_null($payload)) {
			$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if (false === $encodedPayload) {
				curl_close($curl);
				throw new PairException('Unable to encode WhatsApp request payload.', ErrorCodes::WHATSAPP_ERROR);
			}

			$headers[] = 'Content-Type: application/json';
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper(trim($method)));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		if (!is_null($encodedPayload)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		}

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('WhatsApp request failed: ' . $error, ErrorCodes::WHATSAPP_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Decode a JSON response and normalize HTTP failures.
	 *
	 * @throws PairException
	 */
	private function decodeJsonResponse(int $statusCode, string $responseBody): array {

		$responseBody = trim($responseBody);

		if ('' === $responseBody) {
			if ($statusCode >= 400) {
				throw new PairException('WhatsApp request failed with HTTP ' . $statusCode . '.', ErrorCodes::WHATSAPP_ERROR);
			}

			return [];
		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			if ($statusCode >= 400) {
				$this->throwHttpException($statusCode, $responseBody);
			}

			throw new PairException('WhatsApp returned an invalid JSON response.', ErrorCodes::WHATSAPP_ERROR);
		}

		if ($statusCode >= 400) {
			$message = $this->resolveErrorMessage($decodedResponse, $statusCode);
			throw new PairException($message, ErrorCodes::WHATSAPP_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Resolve the most useful Graph API error message from a decoded response.
	 */
	private function resolveErrorMessage(array $response, int $statusCode): string {

		if (isset($response['error']) and is_array($response['error'])) {
			$error = $response['error'];
			$message = trim((string)($error['message'] ?? ''));
			$details = trim((string)($error['error_data']['details'] ?? ''));
			$code = isset($error['code']) ? (string)$error['code'] : '';
			$subcode = isset($error['error_subcode']) ? (string)$error['error_subcode'] : '';

			if ('' !== $details and $details !== $message) {
				$message = ($message !== '' ? $message . ' - ' : '') . $details;
			}

			if ('' !== $code) {
				$message .= ($message !== '' ? ' ' : '') . '(code ' . $code . ($subcode !== '' ? '/' . $subcode : '') . ')';
			}

			if ('' !== $message) {
				return 'WhatsApp Cloud API error: ' . $message;
			}
		}

		if (isset($response['message']) and is_string($response['message']) and '' !== trim($response['message'])) {
			return trim($response['message']);
		}

		return 'WhatsApp request failed with HTTP ' . $statusCode . '.';

	}

	/**
	 * Resolve the MIME type used during media uploads.
	 */
	private function resolveMimeType(string $filePath, ?string $mimeType = null): string {

		$mimeType = trim((string)$mimeType);

		if ('' !== $mimeType) {
			return $mimeType;
		}

		$detectedMimeType = function_exists('mime_content_type') ? mime_content_type($filePath) : false;

		return is_string($detectedMimeType) && '' !== trim($detectedMimeType)
			? trim($detectedMimeType)
			: 'application/octet-stream';

	}

	/**
	 * Sanitize the configured Graph API base URL.
	 *
	 * @throws PairException
	 */
	private function sanitizeApiBaseUrl(string $apiBaseUrl): string {

		$apiBaseUrl = rtrim(trim($apiBaseUrl), '/');

		if ('' === $apiBaseUrl or !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('WHATSAPP_CLOUD_API_BASE_URL is not valid.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $apiBaseUrl;

	}

	/**
	 * Sanitize the configured Graph API version.
	 *
	 * @throws PairException
	 */
	private function sanitizeApiVersion(string $apiVersion): string {

		$apiVersion = trim($apiVersion);

		if (!preg_match('/^v\d+\.\d+$/', $apiVersion)) {
			throw new PairException('WHATSAPP_CLOUD_API_VERSION is not valid.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $apiVersion;

	}

	/**
	 * Sanitize a Graph resource ID.
	 *
	 * @throws PairException
	 */
	private function sanitizeGraphId(string $value, string $label): string {

		$value = trim($value);

		if ('' === $value) {
			throw new PairException('WhatsApp ' . $label . ' ID cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $value;

	}

	/**
	 * Sanitize a media message type.
	 *
	 * @throws PairException
	 */
	private function sanitizeMediaType(string $mediaType): string {

		$mediaType = strtolower(trim($mediaType));

		if (!in_array($mediaType, self::MEDIA_MESSAGE_TYPES, true)) {
			throw new PairException('Unsupported WhatsApp media type: ' . $mediaType . '.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $mediaType;

	}

	/**
	 * Sanitize the destination phone number or WhatsApp user identifier.
	 *
	 * @throws PairException
	 */
	private function sanitizeRecipient(string $to): string {

		$to = trim($to);

		if ('' === $to) {
			throw new PairException('WhatsApp recipient cannot be empty.', ErrorCodes::WHATSAPP_ERROR);
		}

		return $to;

	}

	/**
	 * Convert an HTTP failure response into a PairException.
	 *
	 * @throws PairException
	 */
	private function throwHttpException(int $statusCode, string $responseBody): never {

		$decodedResponse = json_decode($responseBody, true);

		if (is_array($decodedResponse)) {
			throw new PairException($this->resolveErrorMessage($decodedResponse, $statusCode), ErrorCodes::WHATSAPP_ERROR);
		}

		throw new PairException('WhatsApp request failed with HTTP ' . $statusCode . '.', ErrorCodes::WHATSAPP_ERROR);

	}

	/**
	 * Read a webhook query parameter while preserving dots in parameter names.
	 */
	private static function webhookQueryValue(string $name): ?string {

		if (isset($_GET[$name])) {
			return trim((string)$_GET[$name]);
		}

		$underscoreName = str_replace('.', '_', $name);

		if (isset($_GET[$underscoreName])) {
			return trim((string)$_GET[$underscoreName]);
		}

		$queryString = trim((string)($_SERVER['QUERY_STRING'] ?? ''));

		if ('' === $queryString) {
			return null;
		}

		foreach (explode('&', $queryString) as $pair) {

			if ('' === $pair) {
				continue;
			}

			$parts = explode('=', $pair, 2);
			$key = rawurldecode($parts[0]);

			if ($key !== $name) {
				continue;
			}

			return isset($parts[1]) ? trim(rawurldecode($parts[1])) : '';

		}

		return null;

	}

}
