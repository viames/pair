<?php

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Lightweight Telegram Bot API client with helpers for common messaging and webhook flows.
 */
class TelegramBotClient {

	/**
	 * Default Telegram Bot API base URL.
	 */
	private const DEFAULT_API_BASE_URL = 'https://api.telegram.org';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 20;

	/**
	 * Base URL for Telegram API requests.
	 */
	private string $apiBaseUrl;

	/**
	 * Telegram bot token used for authenticated API requests.
	 */
	private ?string $botToken;

	/**
	 * Connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Shared secret expected in Telegram webhook requests.
	 */
	private ?string $webhookSecretToken;

	/**
	 * Build a Telegram client using explicit arguments or `.env` defaults.
	 *
	 * Optional Env keys:
	 * - TELEGRAM_BOT_TOKEN
	 * - TELEGRAM_API_BASE_URL
	 * - TELEGRAM_TIMEOUT
	 * - TELEGRAM_CONNECT_TIMEOUT
	 * - TELEGRAM_WEBHOOK_SECRET_TOKEN
	 *
	 * Webhook-only use cases can instantiate the client without a bot token,
	 * as long as outbound API methods are not called.
	 *
	 * @throws PairException
	 */
	public function __construct(?string $botToken = null, ?string $apiBaseUrl = null, ?int $timeout = null, ?int $connectTimeout = null, ?string $webhookSecretToken = null) {

		$this->botToken = $this->normalizeNullableString($botToken ?? Env::get('TELEGRAM_BOT_TOKEN'));
		$this->apiBaseUrl = $this->sanitizeApiBaseUrl((string)($apiBaseUrl ?? Env::get('TELEGRAM_API_BASE_URL') ?? self::DEFAULT_API_BASE_URL));
		$this->timeout = max(1, (int)($timeout ?? Env::get('TELEGRAM_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('TELEGRAM_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));
		$this->webhookSecretToken = $this->sanitizeWebhookSecretToken($webhookSecretToken ?? Env::get('TELEGRAM_WEBHOOK_SECRET_TOKEN'));

	}

	/**
	 * Execute an arbitrary Telegram Bot API method and return the decoded `result` payload.
	 *
	 * @return	mixed
	 *
	 * @throws PairException
	 */
	public function call(string $method, array $params = [], string $httpMethod = 'POST'): mixed {

		$this->assertBotTokenConfigured();

		$method = $this->sanitizeMethodName($method);
		$httpMethod = $this->sanitizeHttpMethod($httpMethod);
		$params = $this->filterNullValues($params);

		return $this->request($method, $httpMethod, $params);

	}

	/**
	 * Check whether a bot token is available for authenticated requests.
	 */
	public function botTokenSet(): bool {

		return !is_null($this->botToken) and '' !== $this->botToken;

	}

	/**
	 * Legacy convenience helper that resolves a numeric chat ID by username through recent updates.
	 *
	 * Returns null when the username was not seen in the retained update backlog.
	 */
	public function chatId(string $username): ?int {

		$username = ltrim(trim($username), '@');

		if ('' === $username) {
			return null;
		}

		try {
			$updates = $this->getUpdates();
		} catch (PairException) {
			return null;
		}

		foreach ($updates as $update) {
			$chat = $this->extractChatFromUpdate(is_array($update) ? $update : []);

			if (is_array($chat) and ($chat['username'] ?? null) === $username and array_key_exists('id', $chat)) {
				return (int)$chat['id'];
			}
		}

		return null;

	}

	/**
	 * Decode a raw webhook JSON payload.
	 *
	 * @throws PairException
	 */
	public function decodeWebhookPayload(string $payload): array {

		$payload = trim($payload);

		if ('' === $payload) {
			throw new PairException('Telegram webhook payload cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		$decodedPayload = json_decode($payload, true);

		if (!is_array($decodedPayload)) {
			throw new PairException('Telegram webhook payload is not valid JSON.', ErrorCodes::TELEGRAM_FAILURE);
		}

		return $decodedPayload;

	}

	/**
	 * Download file contents from Telegram storage using a previously returned `file_path`.
	 *
	 * @throws PairException
	 */
	public function downloadFileContents(string $filePath): string {

		$this->assertBotTokenConfigured();

		$filePath = ltrim(trim($filePath), '/');

		if ('' === $filePath) {
			throw new PairException('Telegram file path cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		$curl = curl_init($this->buildFileUrl($filePath));
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$response = curl_exec($curl);

		if (false === $response) {
			$error = curl_error($curl);

			throw new PairException('Telegram file download error: ' . $error, ErrorCodes::TELEGRAM_FAILURE);
		}

		$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (200 !== $httpCode) {
			throw new PairException('Telegram file download failed: HTTP ' . $httpCode, ErrorCodes::TELEGRAM_FAILURE);
		}

		return $response;

	}

	/**
	 * Download a Telegram file to disk using a previously returned `file_path`.
	 *
	 * @throws PairException
	 */
	public function downloadFileToPath(string $filePath, string $destinationPath): string {

		$destinationPath = trim($destinationPath);

		if ('' === $destinationPath) {
			throw new PairException('Telegram destination path cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		$directory = dirname($destinationPath);

		if (!is_dir($directory)) {
			throw new PairException('Telegram destination directory not found: ' . $directory, ErrorCodes::TELEGRAM_FAILURE);
		}

		if (file_exists($destinationPath) and !is_writable($destinationPath)) {
			throw new PairException('Telegram destination file is not writable: ' . $destinationPath, ErrorCodes::TELEGRAM_FAILURE);
		}

		if (!is_writable($directory)) {
			throw new PairException('Telegram destination directory is not writable: ' . $directory, ErrorCodes::TELEGRAM_FAILURE);
		}

		$contents = $this->downloadFileContents($filePath);

		if (false === file_put_contents($destinationPath, $contents)) {
			throw new PairException('Telegram file could not be written to: ' . $destinationPath, ErrorCodes::TELEGRAM_FAILURE);
		}

		return $destinationPath;

	}

	/**
	 * Remove the currently configured webhook.
	 */
	public function deleteWebhook(bool $dropPendingUpdates = false): bool {

		$response = $this->call('deleteWebhook', [
			'drop_pending_updates' => $dropPendingUpdates,
		]);

		return $this->assertBoolResult($response, 'deleteWebhook');

	}

	/**
	 * Fetch basic metadata for a Telegram file.
	 */
	public function getFile(string $fileId): array {

		$fileId = trim($fileId);

		if ('' === $fileId) {
			throw new PairException('Telegram file ID cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		$response = $this->call('getFile', [
			'file_id' => $fileId,
		], 'GET');

		return $this->assertArrayResult($response, 'getFile');

	}

	/**
	 * Fetch up-to-date metadata for a chat.
	 */
	public function getChat(int|string $chatId): array {

		$response = $this->call('getChat', [
			'chat_id' => $this->sanitizeChatId($chatId),
		], 'GET');

		return $this->assertArrayResult($response, 'getChat');

	}

	/**
	 * Fetch the current webhook configuration.
	 */
	public function getWebhookInfo(): array {

		$response = $this->call('getWebhookInfo', [], 'GET');

		return $this->assertArrayResult($response, 'getWebhookInfo');

	}

	/**
	 * Legacy convenience helper that returns a public `t.me` link for a chat username.
	 */
	public function link(int|string $chatId): string {

		$username = $this->username($chatId);

		return $username ? 'https://t.me/' . ltrim($username, '@') : '';

	}

	/**
	 * Legacy convenience alias for sendMessage().
	 *
	 * @throws PairException
	 */
	public function message(int|string $chatId, string $message, ?string $parseMode = null): void {

		$options = [];

		if (in_array($parseMode, ['HTML', 'Markdown', 'MarkdownV2'], true)) {
			$options['parse_mode'] = $parseMode;
		}

		$this->sendMessage($chatId, $message, $options);

	}

	/**
	 * Legacy convenience alias for sendPhoto().
	 *
	 * @throws PairException
	 */
	public function photo(int|string $chatId, string $imagePath, ?string $caption = null): void {

		$this->sendPhoto($chatId, $imagePath, $caption);

	}

	/**
	 * Poll Telegram updates for bots using `getUpdates`.
	 */
	public function getUpdates(array $options = []): array {

		$response = $this->call('getUpdates', $options, 'GET');

		return $this->assertArrayResult($response, 'getUpdates');

	}

	/**
	 * Send a chat action such as `typing` or `upload_photo`.
	 */
	public function sendChatAction(int|string $chatId, string $action, array $options = []): bool {

		$action = trim($action);

		if ('' === $action) {
			throw new PairException('Telegram chat action cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		$payload = array_merge($options, [
			'chat_id' => $this->sanitizeChatId($chatId),
			'action' => $action,
		]);

		$response = $this->call('sendChatAction', $payload);

		return $this->assertBoolResult($response, 'sendChatAction');

	}

	/**
	 * Send a text message.
	 */
	public function sendMessage(int|string $chatId, string $text, array $options = []): array {

		if ('' === trim($text)) {
			throw new PairException('Telegram message text cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		$payload = array_merge($options, [
			'chat_id' => $this->sanitizeChatId($chatId),
			'text' => $text,
		]);

		$response = $this->call('sendMessage', $payload);

		return $this->assertArrayResult($response, 'sendMessage');

	}

	/**
	 * Send a photo using a file ID, URL, or local file path.
	 */
	public function sendPhoto(int|string $chatId, string $photo, ?string $caption = null, array $options = []): array {

		$payload = array_merge($options, [
			'chat_id' => $this->sanitizeChatId($chatId),
			'photo' => $this->normalizePhotoInput($photo),
		]);

		$caption = trim((string)$caption);
		if ('' !== $caption) {
			$payload['caption'] = $caption;
		}

		$response = $this->call('sendPhoto', $payload);

		return $this->assertArrayResult($response, 'sendPhoto');

	}

	/**
	 * Configure an outgoing webhook endpoint for the bot.
	 */
	public function setWebhook(string $url, array $options = []): bool {

		$url = trim($url);

		if ('' === $url) {
			throw new PairException('Telegram webhook URL cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		$payload = array_merge($options, [
			'url' => $url,
		]);

		if (!array_key_exists('secret_token', $payload) and $this->webhookSecretTokenSet()) {
			$payload['secret_token'] = $this->webhookSecretToken;
		}

		if (array_key_exists('certificate', $payload)) {
			$payload['certificate'] = $this->normalizeCertificateInput($payload['certificate']);
		}

		if (array_key_exists('secret_token', $payload)) {
			$payload['secret_token'] = $this->sanitizeWebhookSecretToken((string)$payload['secret_token']) ?? null;
		}

		$response = $this->call('setWebhook', $payload);

		return $this->assertBoolResult($response, 'setWebhook');

	}

	/**
	 * Check whether a webhook secret token is configured.
	 */
	public function webhookSecretTokenSet(): bool {

		return !is_null($this->webhookSecretToken) and '' !== $this->webhookSecretToken;

	}

	/**
	 * Throw if the inbound webhook secret token does not match the configured one.
	 *
	 * @throws PairException
	 */
	public function assertWebhookSecretToken(?string $providedToken = null): void {

		if (!$this->verifyWebhookSecretToken($providedToken)) {
			throw new PairException('Invalid Telegram webhook secret token.', ErrorCodes::TELEGRAM_FAILURE);
		}

	}

	/**
	 * Check whether the inbound webhook secret token matches the configured one.
	 *
	 * @throws PairException
	 */
	public function verifyWebhookSecretToken(?string $providedToken = null): bool {

		$this->assertWebhookSecretTokenConfigured();

		$providedToken = trim((string)($providedToken ?? ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '')));

		return hash_equals((string)$this->webhookSecretToken, $providedToken);

	}

	/**
	 * Legacy convenience helper that returns the Telegram username of a chat, if any.
	 */
	public function username(int|string $chatId): ?string {

		if ((is_int($chatId) and 0 === $chatId) or (is_string($chatId) and '' === trim($chatId))) {
			return null;
		}

		try {
			$chat = $this->getChat($chatId);
		} catch (PairException) {
			return null;
		}

		if (isset($chat['username']) and '' !== trim((string)$chat['username'])) {
			return '@' . $chat['username'];
		}

		return null;

	}

	/**
	 * Throw if the client has no bot token configured.
	 *
	 * @throws PairException
	 */
	private function assertBotTokenConfigured(): void {

		if (!$this->botTokenSet()) {
			throw new PairException('Telegram bot token not set in configuration nor in class constructor', ErrorCodes::TELEGRAM_FAILURE);
		}

	}

	/**
	 * Throw if the client has no webhook secret token configured.
	 *
	 * @throws PairException
	 */
	private function assertWebhookSecretTokenConfigured(): void {

		if (!$this->webhookSecretTokenSet()) {
			throw new PairException('Telegram webhook secret token not configured.', ErrorCodes::TELEGRAM_FAILURE);
		}

	}

	/**
	 * Assert that a Telegram API result is an array payload.
	 *
	 * @throws PairException
	 */
	private function assertArrayResult(mixed $response, string $method): array {

		if (!is_array($response)) {
			throw new PairException('Telegram API method ' . $method . ' returned an unexpected result.', ErrorCodes::TELEGRAM_FAILURE);
		}

		return $response;

	}

	/**
	 * Assert that a Telegram API result is a boolean payload.
	 *
	 * @throws PairException
	 */
	private function assertBoolResult(mixed $response, string $method): bool {

		if (!is_bool($response)) {
			throw new PairException('Telegram API method ' . $method . ' returned an unexpected result.', ErrorCodes::TELEGRAM_FAILURE);
		}

		return $response;

	}

	/**
	 * Build the absolute URL for file downloads.
	 *
	 * @throws PairException
	 */
	private function buildFileUrl(string $filePath): string {

		$this->assertBotTokenConfigured();

		return $this->apiBaseUrl . '/file/bot' . $this->botToken . '/' . ltrim($filePath, '/');

	}

	/**
	 * Build the absolute URL for a Bot API method.
	 *
	 * @throws PairException
	 */
	private function buildMethodUrl(string $method): string {

		$this->assertBotTokenConfigured();

		return $this->apiBaseUrl . '/bot' . $this->botToken . '/' . $method;

	}

	/**
	 * Recursively remove null values from request payloads while preserving booleans and zeroes.
	 */
	private function filterNullValues(array $payload): array {

		$filteredPayload = [];

		foreach ($payload as $key => $value) {

			if (is_array($value)) {
				$value = $this->filterNullValues($value);
			}

			if (is_null($value)) {
				continue;
			}

			$filteredPayload[$key] = $value;

		}

		return $filteredPayload;

	}

	/**
	 * Extract the most relevant chat payload from a Telegram update.
	 */
	private function extractChatFromUpdate(array $update): ?array {

		$candidateChats = [
			$update['message']['chat'] ?? null,
			$update['edited_message']['chat'] ?? null,
			$update['channel_post']['chat'] ?? null,
			$update['edited_channel_post']['chat'] ?? null,
			$update['callback_query']['message']['chat'] ?? null,
			$update['my_chat_member']['chat'] ?? null,
			$update['chat_member']['chat'] ?? null,
			$update['chat_join_request']['chat'] ?? null,
			$update['business_message']['chat'] ?? null,
			$update['edited_business_message']['chat'] ?? null,
		];

		foreach ($candidateChats as $chat) {
			if (is_array($chat)) {
				return $chat;
			}
		}

		return null;

	}

	/**
	 * Check whether a payload requires multipart/form-data.
	 */
	private function hasMultipartFields(array $payload): bool {

		foreach ($payload as $value) {
			if ($value instanceof \CURLFile) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Determine whether a string should be treated as a local file path.
	 */
	private function looksLikeLocalFilePath(string $value): bool {

		return str_contains($value, '/') or str_contains($value, '\\') or str_starts_with($value, './') or str_starts_with($value, '../');

	}

	/**
	 * Normalize a local file path or pass through a Telegram file ID / URL unchanged.
	 *
	 * @return	string|\CURLFile
	 *
	 * @throws PairException
	 */
	private function normalizeCertificateInput(mixed $certificate): mixed {

		if ($certificate instanceof \CURLFile) {
			return $certificate;
		}

		$certificatePath = trim((string)$certificate);

		if ('' === $certificatePath) {
			throw new PairException('Telegram webhook certificate path cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		if (!file_exists($certificatePath) or !is_readable($certificatePath)) {
			throw new PairException('Telegram webhook certificate not found or not readable: ' . $certificatePath, ErrorCodes::TELEGRAM_FAILURE);
		}

		return new \CURLFile(
			realpath($certificatePath),
			mime_content_type($certificatePath) ?: 'application/octet-stream',
			basename($certificatePath)
		);

	}

	/**
	 * Normalize a local file path or pass through a Telegram file ID / URL unchanged.
	 *
	 * @return	string|\CURLFile
	 *
	 * @throws PairException
	 */
	private function normalizePhotoInput(string $photo): string|\CURLFile {

		$photo = trim($photo);

		if ('' === $photo) {
			throw new PairException('Telegram photo value cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		if (file_exists($photo) and is_readable($photo)) {
			return new \CURLFile(
				realpath($photo),
				mime_content_type($photo) ?: 'application/octet-stream',
				basename($photo)
			);
		}

		// Paths that look local but are missing should fail fast instead of being sent as file IDs.
		if ($this->looksLikeLocalFilePath($photo)) {
			throw new PairException('Telegram photo file not found or not readable: ' . $photo, ErrorCodes::TELEGRAM_FAILURE);
		}

		return $photo;

	}

	/**
	 * Normalize empty strings to null for optional constructor values.
	 */
	private function normalizeNullableString(mixed $value): ?string {

		$value = trim((string)$value);

		return '' === $value ? null : $value;

	}

	/**
	 * Serialize payload values for multipart/form-data requests.
	 */
	private function prepareMultipartPayload(array $payload): array {

		$preparedPayload = [];

		foreach ($payload as $key => $value) {

			if ($value instanceof \CURLFile) {
				$preparedPayload[$key] = $value;
				continue;
			}

			if (is_array($value) or is_object($value)) {
				$preparedPayload[$key] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				continue;
			}

			if (is_bool($value)) {
				$preparedPayload[$key] = $value ? 'true' : 'false';
				continue;
			}

			$preparedPayload[$key] = $value;

		}

		return $preparedPayload;

	}

	/**
	 * Execute an HTTP request against Telegram API and return the decoded result payload.
	 *
	 * @return	mixed
	 *
	 * @throws PairException
	 */
	private function request(string $method, string $httpMethod, array $params): mixed {

		$url = $this->buildMethodUrl($method);

		if ('GET' === $httpMethod and count($params)) {
			$url .= '?' . http_build_query($this->prepareMultipartPayload($params));
		}

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		if ('POST' === $httpMethod) {
			curl_setopt($curl, CURLOPT_POST, true);

			if ($this->hasMultipartFields($params)) {
				curl_setopt($curl, CURLOPT_POSTFIELDS, $this->prepareMultipartPayload($params));
			} else {
				curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
			}
		}

		$response = curl_exec($curl);

		if (false === $response) {
			$error = curl_error($curl);

			throw new PairException('Telegram API request failed: ' . $error, ErrorCodes::TELEGRAM_FAILURE);
		}

		$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		$decodedResponse = json_decode($response, true);

		if (!is_array($decodedResponse) or !array_key_exists('ok', $decodedResponse)) {
			throw new PairException('Telegram API response error: ' . $response, ErrorCodes::TELEGRAM_FAILURE);
		}

		if (true !== $decodedResponse['ok']) {
			$message = $this->buildApiErrorMessage($decodedResponse);
			throw new PairException($message, ErrorCodes::TELEGRAM_FAILURE);
		}

		if (200 !== $httpCode) {
			throw new PairException('Telegram API not reachable: HTTP ' . $httpCode, ErrorCodes::TELEGRAM_FAILURE);
		}

		return $decodedResponse['result'] ?? true;

	}

	/**
	 * Build a descriptive error message from a Telegram API error response.
	 */
	private function buildApiErrorMessage(array $response): string {

		$errorCode = (int)($response['error_code'] ?? 0);
		$description = trim((string)($response['description'] ?? 'Unknown Telegram API error.'));
		$message = 400 === $errorCode
			? 'Telegram API error: ' . $description
			: 'Telegram API error: ' . $errorCode . ' - ' . $description;
		$parameters = is_array($response['parameters'] ?? null) ? $response['parameters'] : [];

		// Include throttling and chat migration hints when Telegram returns them.
		if (array_key_exists('retry_after', $parameters)) {
			$message .= ' (retry after ' . $parameters['retry_after'] . ' seconds)';
		}

		if (array_key_exists('migrate_to_chat_id', $parameters)) {
			$message .= ' (migrate to chat ID ' . $parameters['migrate_to_chat_id'] . ')';
		}

		return $message;

	}

	/**
	 * Normalize and validate a chat identifier.
	 *
	 * @return	int|string
	 *
	 * @throws PairException
	 */
	private function sanitizeChatId(int|string $chatId): int|string {

		if (is_int($chatId)) {
			if (0 === $chatId) {
				throw new PairException('Telegram chat ID value not valid (0)', ErrorCodes::TELEGRAM_FAILURE);
			}

			return $chatId;
		}

		$chatId = trim($chatId);

		if ('' === $chatId) {
			throw new PairException('Telegram chat ID value cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		if (preg_match('/^-?\d+$/', $chatId)) {
			return (int)$chatId;
		}

		return $chatId;

	}

	/**
	 * Normalize and validate the configured API base URL.
	 *
	 * @throws PairException
	 */
	private function sanitizeApiBaseUrl(string $apiBaseUrl): string {

		$apiBaseUrl = rtrim(trim($apiBaseUrl), '/');

		if ('' === $apiBaseUrl) {
			throw new PairException('Telegram API base URL cannot be empty.', ErrorCodes::TELEGRAM_FAILURE);
		}

		if (!filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('Telegram API base URL is not valid: ' . $apiBaseUrl, ErrorCodes::TELEGRAM_FAILURE);
		}

		return $apiBaseUrl;

	}

	/**
	 * Normalize and validate the requested HTTP method.
	 *
	 * @throws PairException
	 */
	private function sanitizeHttpMethod(string $httpMethod): string {

		$httpMethod = strtoupper(trim($httpMethod));

		if (!in_array($httpMethod, ['GET', 'POST'], true)) {
			throw new PairException('Telegram HTTP method not supported: ' . $httpMethod, ErrorCodes::TELEGRAM_FAILURE);
		}

		return $httpMethod;

	}

	/**
	 * Normalize and validate a Bot API method name.
	 *
	 * @throws PairException
	 */
	private function sanitizeMethodName(string $method): string {

		$method = trim($method);

		if ('' === $method or !preg_match('/^[A-Za-z0-9_]+$/', $method)) {
			throw new PairException('Telegram API method not valid: ' . $method, ErrorCodes::TELEGRAM_FAILURE);
		}

		return $method;

	}

	/**
	 * Normalize and validate the webhook secret token format expected by Telegram.
	 *
	 * @throws PairException
	 */
	private function sanitizeWebhookSecretToken(mixed $value): ?string {

		$value = $this->normalizeNullableString($value);

		if (is_null($value)) {
			return null;
		}

		if (strlen($value) > 256 or !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
			throw new PairException('Telegram webhook secret token is not valid.', ErrorCodes::TELEGRAM_FAILURE);
		}

		return $value;

	}

}
