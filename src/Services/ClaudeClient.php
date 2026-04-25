<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Lightweight Claude Messages API client for Pair applications and optional AI extensions.
 */
class ClaudeClient {

	/**
	 * Default Claude REST API base URL.
	 */
	private const DEFAULT_API_BASE_URL = 'https://api.anthropic.com/v1';

	/**
	 * Default Anthropic API version header.
	 */
	private const DEFAULT_API_VERSION = '2023-06-01';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default maximum generated tokens.
	 */
	private const DEFAULT_MAX_TOKENS = 1024;

	/**
	 * Default Messages API model for chat-style application use.
	 */
	private const DEFAULT_MESSAGES_MODEL = 'claude-sonnet-4-5';

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 30;

	/**
	 * Claude API key.
	 */
	private string $apiKey;

	/**
	 * Claude REST API base URL.
	 */
	private string $apiBaseUrl;

	/**
	 * Anthropic API version header.
	 */
	private string $apiVersion;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Default maximum generated tokens.
	 */
	private int $maxTokens;

	/**
	 * Default Messages API model.
	 */
	private string $messagesModel;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Build a client using explicit arguments or Env defaults.
	 *
	 * Optional Env keys:
	 * - CLAUDE_API_KEY
	 * - CLAUDE_API_BASE_URL
	 * - CLAUDE_MESSAGES_MODEL
	 * - CLAUDE_API_VERSION
	 * - CLAUDE_MAX_TOKENS
	 * - CLAUDE_TIMEOUT
	 * - CLAUDE_CONNECT_TIMEOUT
	 */
	public function __construct(?string $apiKey = null, ?string $apiBaseUrl = null, ?string $messagesModel = null, ?string $apiVersion = null, ?int $maxTokens = null, ?int $timeout = null, ?int $connectTimeout = null) {

		$this->apiKey = trim((string)($apiKey ?? Env::get('CLAUDE_API_KEY')));
		$this->apiBaseUrl = $this->sanitizeApiBaseUrl((string)($apiBaseUrl ?? Env::get('CLAUDE_API_BASE_URL') ?? self::DEFAULT_API_BASE_URL));
		$this->messagesModel = $this->resolveModel($messagesModel, (string)(Env::get('CLAUDE_MESSAGES_MODEL') ?: self::DEFAULT_MESSAGES_MODEL), 'CLAUDE_MESSAGES_MODEL');
		$this->apiVersion = $this->resolveApiVersion($apiVersion ?? Env::get('CLAUDE_API_VERSION') ?? self::DEFAULT_API_VERSION);
		$this->maxTokens = max(1, (int)($maxTokens ?? Env::get('CLAUDE_MAX_TOKENS') ?? self::DEFAULT_MAX_TOKENS));
		$this->timeout = max(1, (int)($timeout ?? Env::get('CLAUDE_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('CLAUDE_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));

	}

	/**
	 * Check whether an API key is configured for outbound requests.
	 */
	public function apiKeySet(): bool {

		return '' !== $this->apiKey;

	}

	/**
	 * Create a Claude Messages API response.
	 *
	 * @param	array<int, array<string, mixed>>	$messages	Messages using user, assistant, or system roles.
	 * @param	array							$options	Optional Messages API fields.
	 */
	public function createMessage(array $messages, array $options = []): array {

		if (!count($messages)) {
			throw new PairException('Claude message list cannot be empty.', ErrorCodes::CLAUDE_ERROR);
		}

		$systemInstructions = [];
		$normalizedMessages = $this->normalizeMessages($messages, $systemInstructions);
		$system = $options['system'] ?? null;

		// Anthropic keeps system prompts outside the stateless message transcript.
		if (is_null($system) and count($systemInstructions)) {
			$system = implode("\n\n", $systemInstructions);
		}

		$payload = [
			'model' => $this->resolveModel($options['model'] ?? null, $this->messagesModel, 'model'),
			'max_tokens' => max(1, (int)($options['max_tokens'] ?? $this->maxTokens)),
			'messages' => $normalizedMessages,
			'system' => $system,
			'metadata' => $options['metadata'] ?? null,
			'stop_sequences' => $options['stop_sequences'] ?? null,
			'stream' => $options['stream'] ?? null,
			'temperature' => $options['temperature'] ?? null,
			'thinking' => $options['thinking'] ?? null,
			'tool_choice' => $options['tool_choice'] ?? null,
			'tools' => $options['tools'] ?? null,
			'top_k' => $options['top_k'] ?? null,
			'top_p' => $options['top_p'] ?? null,
		];

		return $this->requestJson('POST', '/messages', $this->withoutNullValues($payload));

	}

	/**
	 * Create a Claude Messages API response and return concatenated text output.
	 */
	public function createTextMessage(array $messages, array $options = []): string {

		return self::extractText($this->createMessage($messages, $options));

	}

	/**
	 * Convenience helper for a single user text prompt.
	 */
	public function createTextResponse(string $input, array $options = []): string {

		if ('' === trim($input)) {
			throw new PairException('Claude input cannot be empty.', ErrorCodes::CLAUDE_ERROR);
		}

		return $this->createTextMessage([
			[
				'role' => 'user',
				'content' => $input,
			],
		], $options);

	}

	/**
	 * Extract concatenated text from a Claude Messages API response.
	 */
	public static function extractText(array $response): string {

		$outputText = [];
		$content = $response['content'] ?? [];

		if (!is_array($content)) {
			return '';
		}

		foreach ($content as $contentItem) {
			if (is_array($contentItem) and ($contentItem['type'] ?? null) === 'text' and isset($contentItem['text']) and is_string($contentItem['text'])) {
				$outputText[] = $contentItem['text'];
			}
		}

		return implode('', $outputText);

	}

	/**
	 * Execute an authenticated JSON request against the Claude API.
	 *
	 * @throws PairException
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$this->assertApiKeyConfigured();

		$curl = curl_init($this->buildApiUrl($path));

		if (false === $curl) {
			throw new PairException('Unable to initialize Claude request.', ErrorCodes::CLAUDE_ERROR);
		}

		$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === $encodedPayload) {
			curl_close($curl);
			throw new PairException('Unable to encode Claude request payload.', ErrorCodes::CLAUDE_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper(trim($method)));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Anthropic-Version: ' . $this->apiVersion,
			'Content-Type: application/json',
			'X-Api-Key: ' . $this->apiKey,
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Claude request failed: ' . $error, ErrorCodes::CLAUDE_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Require an API key before outbound requests.
	 */
	private function assertApiKeyConfigured(): void {

		if ('' === $this->apiKey) {
			throw new PairException('Missing Claude API key. Set CLAUDE_API_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Build an absolute Claude API URL from a relative path.
	 */
	private function buildApiUrl(string $path): string {

		return $this->apiBaseUrl . '/' . ltrim($path, '/');

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
				throw new PairException('Claude request failed with HTTP ' . $statusCode . '.', ErrorCodes::CLAUDE_ERROR);
			}

			return [];

		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('Claude returned an invalid JSON response.', ErrorCodes::CLAUDE_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->resolveErrorMessage($decodedResponse, $statusCode), ErrorCodes::CLAUDE_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Normalize a Messages API transcript and lift system messages out.
	 *
	 * @param	array<int, array<string, mixed>>	$messages				Input messages.
	 * @param	array<int, string>				$systemInstructions		Collected system instruction text.
	 * @return	array<int, array<string, mixed>>
	 */
	private function normalizeMessages(array $messages, array &$systemInstructions): array {

		$normalizedMessages = [];

		foreach ($messages as $message) {

			if (!is_array($message)) {
				throw new PairException('Claude messages must be arrays.', ErrorCodes::CLAUDE_ERROR);
			}

			$role = strtolower(trim((string)($message['role'] ?? 'user')));
			$content = $message['content'] ?? '';

			if ('system' === $role) {
				$systemInstructions[] = $this->messageContentToText($content, 'Claude system message');
				continue;
			}

			if ('user' !== $role and 'assistant' !== $role) {
				throw new PairException('Unsupported Claude message role: ' . $role, ErrorCodes::CLAUDE_ERROR);
			}

			$normalizedMessages[] = [
				'role' => $role,
				'content' => $this->normalizeMessageContent($content, 'Claude message content'),
			];

		}

		if (!count($normalizedMessages)) {
			throw new PairException('Claude messages require at least one user or assistant message.', ErrorCodes::CLAUDE_ERROR);
		}

		return $normalizedMessages;

	}

	/**
	 * Normalize text or content block arrays for Claude messages.
	 */
	private function normalizeMessageContent(mixed $content, string $label): string|array {

		if (is_string($content)) {
			$content = trim($content);

			if ('' === $content) {
				throw new PairException($label . ' cannot be empty.', ErrorCodes::CLAUDE_ERROR);
			}

			return $content;
		}

		if (!is_array($content) or !count($content)) {
			throw new PairException($label . ' must be a non-empty string or array.', ErrorCodes::CLAUDE_ERROR);
		}

		$blocks = [];

		foreach (array_is_list($content) ? $content : [$content] as $block) {

			if (is_string($block) and '' !== trim($block)) {
				$blocks[] = [
					'type' => 'text',
					'text' => trim($block),
				];
				continue;
			}

			if (is_array($block) and ($block['type'] ?? null) === 'text' and isset($block['text'])) {
				$blocks[] = [
					'type' => 'text',
					'text' => (string)$block['text'],
				];
				continue;
			}

			if (is_array($block)) {
				$blocks[] = $block;
			}

		}

		if (!count($blocks)) {
			throw new PairException($label . ' does not contain valid content blocks.', ErrorCodes::CLAUDE_ERROR);
		}

		return $blocks;

	}

	/**
	 * Convert message content to plain text for Claude system instructions.
	 */
	private function messageContentToText(mixed $content, string $label): string {

		$content = $this->normalizeMessageContent($content, $label);

		if (is_string($content)) {
			return $content;
		}

		$text = [];

		foreach ($content as $block) {
			if (is_array($block) and ($block['type'] ?? null) === 'text' and isset($block['text'])) {
				$text[] = (string)$block['text'];
			}
		}

		$text = trim(implode("\n", $text));

		if ('' === $text) {
			throw new PairException($label . ' must contain text.', ErrorCodes::CLAUDE_ERROR);
		}

		return $text;

	}

	/**
	 * Remove null values recursively from API request payloads.
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
	 * Resolve the most useful error message from a Claude error response.
	 */
	private function resolveErrorMessage(array $response, int $statusCode): string {

		if (isset($response['error']) and is_array($response['error'])) {
			$message = trim((string)($response['error']['message'] ?? ''));
			$type = trim((string)($response['error']['type'] ?? ''));

			if ('' !== $message) {
				return 'Claude API error: ' . $message . ('' !== $type ? ' (' . $type . ')' : '');
			}
		}

		return 'Claude request failed with HTTP ' . $statusCode . '.';

	}

	/**
	 * Resolve and validate an Anthropic API version.
	 */
	private function resolveApiVersion(mixed $apiVersion): string {

		$apiVersion = trim((string)$apiVersion);

		if ('' === $apiVersion) {
			throw new PairException('Claude API version cannot be empty. Configure CLAUDE_API_VERSION.', ErrorCodes::MISSING_CONFIGURATION);
		}

		return $apiVersion;

	}

	/**
	 * Resolve a model argument or default value.
	 *
	 * @throws PairException
	 */
	private function resolveModel(mixed $model, string $fallback, string $label): string {

		$model = trim((string)($model ?? $fallback));

		if ('' === $model) {
			throw new PairException('Claude model cannot be empty. Configure ' . $label . '.', ErrorCodes::MISSING_CONFIGURATION);
		}

		return $model;

	}

	/**
	 * Validate and normalize the API base URL.
	 */
	private function sanitizeApiBaseUrl(string $apiBaseUrl): string {

		$apiBaseUrl = rtrim(trim($apiBaseUrl), '/');

		if ('' === $apiBaseUrl or !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('CLAUDE_API_BASE_URL is not valid.', ErrorCodes::CLAUDE_ERROR);
		}

		return $apiBaseUrl;

	}

}
