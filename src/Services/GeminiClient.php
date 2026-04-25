<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Lightweight Gemini API client for Pair applications and optional AI extensions.
 */
class GeminiClient {

	/**
	 * Default Gemini REST API base URL.
	 */
	private const DEFAULT_API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default embedding model for semantic search and indexing.
	 */
	private const DEFAULT_EMBEDDINGS_MODEL = 'gemini-embedding-2';

	/**
	 * Default text generation model for chat-style application use.
	 */
	private const DEFAULT_GENERATION_MODEL = 'gemini-2.5-flash';

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 30;

	/**
	 * Gemini API key.
	 */
	private string $apiKey;

	/**
	 * Gemini REST API base URL.
	 */
	private string $apiBaseUrl;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Default embeddings model.
	 */
	private string $embeddingsModel;

	/**
	 * Default generation model.
	 */
	private string $generationModel;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Build a client using explicit arguments or Env defaults.
	 *
	 * Optional Env keys:
	 * - GEMINI_API_KEY
	 * - GEMINI_API_BASE_URL
	 * - GEMINI_GENERATION_MODEL
	 * - GEMINI_EMBEDDINGS_MODEL
	 * - GEMINI_TIMEOUT
	 * - GEMINI_CONNECT_TIMEOUT
	 */
	public function __construct(?string $apiKey = null, ?string $apiBaseUrl = null, ?string $generationModel = null, ?string $embeddingsModel = null, ?int $timeout = null, ?int $connectTimeout = null) {

		$this->apiKey = trim((string)($apiKey ?? Env::get('GEMINI_API_KEY')));
		$this->apiBaseUrl = $this->sanitizeApiBaseUrl((string)($apiBaseUrl ?? Env::get('GEMINI_API_BASE_URL') ?? self::DEFAULT_API_BASE_URL));
		$this->generationModel = $this->resolveModel($generationModel, (string)(Env::get('GEMINI_GENERATION_MODEL') ?: self::DEFAULT_GENERATION_MODEL), 'GEMINI_GENERATION_MODEL');
		$this->embeddingsModel = $this->resolveModel($embeddingsModel, (string)(Env::get('GEMINI_EMBEDDINGS_MODEL') ?: self::DEFAULT_EMBEDDINGS_MODEL), 'GEMINI_EMBEDDINGS_MODEL');
		$this->timeout = max(1, (int)($timeout ?? Env::get('GEMINI_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('GEMINI_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));

	}

	/**
	 * Check whether an API key is configured for outbound requests.
	 */
	public function apiKeySet(): bool {

		return '' !== $this->apiKey;

	}

	/**
	 * Generate content with Gemini's native generateContent endpoint.
	 *
	 * @param	string|array	$contents	Text input or native Gemini contents.
	 * @param	array		$options	Generation options and native Gemini payload fields.
	 */
	public function generateContent(string|array $contents, array $options = []): array {

		$this->assertInputPresent($contents, 'Gemini contents');

		$model = $this->resolveModel($options['model'] ?? null, $this->generationModel, 'model');
		$payload = [
			'contents' => $this->normalizeContents($contents),
			'systemInstruction' => $this->normalizeSystemInstruction($options['system_instruction'] ?? $options['systemInstruction'] ?? null),
			'generationConfig' => $this->normalizeGenerationConfig($options),
			'safetySettings' => $options['safety_settings'] ?? $options['safetySettings'] ?? null,
			'tools' => $options['tools'] ?? null,
			'toolConfig' => $options['tool_config'] ?? $options['toolConfig'] ?? null,
			'cachedContent' => $options['cached_content'] ?? $options['cachedContent'] ?? null,
		];

		return $this->requestJson('POST', $this->buildModelPath($model, 'generateContent'), $this->withoutNullValues($payload));

	}

	/**
	 * Generate content and return concatenated text output.
	 */
	public function generateText(string|array $contents, array $options = []): string {

		return self::extractText($this->generateContent($contents, $options));

	}

	/**
	 * Send a stateless chat transcript using Gemini role conventions.
	 *
	 * @param	array<int, array<string, mixed>>	$messages	Messages using user, assistant/model, or system roles.
	 */
	public function chat(array $messages, array $options = []): array {

		if (!count($messages)) {
			throw new PairException('Gemini chat message list cannot be empty.', ErrorCodes::GEMINI_ERROR);
		}

		$contents = [];
		$systemInstructions = [];

		foreach ($messages as $message) {

			if (!is_array($message)) {
				throw new PairException('Gemini chat messages must be arrays.', ErrorCodes::GEMINI_ERROR);
			}

			$role = strtolower(trim((string)($message['role'] ?? 'user')));
			$content = $message['content'] ?? '';

			if ('system' === $role) {
				$systemInstructions[] = $this->messageContentToText($content, 'Gemini system message');
				continue;
			}

			$contents[] = [
				'role' => $this->normalizeChatRole($role),
				'parts' => $this->normalizeParts($content, 'Gemini chat message content'),
			];

		}

		if (!count($contents)) {
			throw new PairException('Gemini chat requires at least one user or assistant message.', ErrorCodes::GEMINI_ERROR);
		}

		// Native Gemini system instructions live outside the conversational contents array.
		if (!isset($options['system_instruction']) and !isset($options['systemInstruction']) and count($systemInstructions)) {
			$options['system_instruction'] = implode("\n\n", $systemInstructions);
		}

		return $this->generateContent($contents, $options);

	}

	/**
	 * Send a stateless chat transcript and return concatenated text output.
	 */
	public function chatText(array $messages, array $options = []): string {

		return self::extractText($this->chat($messages, $options));

	}

	/**
	 * Create a raw Gemini embedding response for one content item.
	 *
	 * @param	string|array	$content	Text input or native Gemini content object.
	 */
	public function createEmbeddingResponse(string|array $content, array $options = []): array {

		$this->assertInputPresent($content, 'Gemini embedding content');

		$model = $this->resolveModel($options['model'] ?? null, $this->embeddingsModel, 'model');
		$normalizedModel = $this->normalizeModelName($model);
		$payload = [
			'model' => 'models/' . $normalizedModel,
			'content' => $this->normalizeEmbeddingContent($content),
			'taskType' => $options['task_type'] ?? $options['taskType'] ?? null,
			'outputDimensionality' => $options['output_dimensionality'] ?? $options['outputDimensionality'] ?? null,
		];

		return $this->requestJson('POST', $this->buildModelPath($model, 'embedContent'), $this->withoutNullValues($payload));

	}

	/**
	 * Create one float embedding vector for semantic search or indexing.
	 *
	 * @return	array<int, float|int>
	 */
	public function embedText(string $input, array $options = []): array {

		return self::extractEmbeddingVector($this->createEmbeddingResponse($input, $options));

	}

	/**
	 * Extract concatenated text from a Gemini generateContent response.
	 */
	public static function extractText(array $response): string {

		$outputText = [];
		$candidates = $response['candidates'] ?? [];

		if (!is_array($candidates)) {
			return '';
		}

		foreach ($candidates as $candidate) {

			if (!is_array($candidate)) {
				continue;
			}

			$parts = $candidate['content']['parts'] ?? [];

			if (!is_array($parts)) {
				continue;
			}

			foreach ($parts as $part) {
				if (is_array($part) and isset($part['text']) and is_string($part['text'])) {
					$outputText[] = $part['text'];
				}
			}

		}

		return implode('', $outputText);

	}

	/**
	 * Extract the first embedding vector from a Gemini embedding response.
	 *
	 * @return	array<int, float|int>
	 */
	public static function extractEmbeddingVector(array $response): array {

		$embedding = $response['embedding']['values'] ?? $response['embeddings'][0]['values'] ?? null;

		if (!is_array($embedding)) {
			throw new PairException('Gemini embedding response is missing vector values.', ErrorCodes::GEMINI_ERROR);
		}

		return $embedding;

	}

	/**
	 * Execute an authenticated JSON request against the Gemini API.
	 *
	 * @throws PairException
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$this->assertApiKeyConfigured();

		$curl = curl_init($this->buildApiUrl($path));

		if (false === $curl) {
			throw new PairException('Unable to initialize Gemini request.', ErrorCodes::GEMINI_ERROR);
		}

		$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === $encodedPayload) {
			curl_close($curl);
			throw new PairException('Unable to encode Gemini request payload.', ErrorCodes::GEMINI_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper(trim($method)));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Goog-Api-Key: ' . $this->apiKey,
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Gemini request failed: ' . $error, ErrorCodes::GEMINI_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Require an API key before outbound requests.
	 */
	private function assertApiKeyConfigured(): void {

		if ('' === $this->apiKey) {
			throw new PairException('Missing Gemini API key. Set GEMINI_API_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Ensure API inputs are not empty before making billable requests.
	 */
	private function assertInputPresent(string|array $input, string $label): void {

		if (is_string($input) and '' === trim($input)) {
			throw new PairException($label . ' cannot be empty.', ErrorCodes::GEMINI_ERROR);
		}

		if (is_array($input) and !count($input)) {
			throw new PairException($label . ' list cannot be empty.', ErrorCodes::GEMINI_ERROR);
		}

	}

	/**
	 * Build an absolute Gemini API URL from a relative path.
	 */
	private function buildApiUrl(string $path): string {

		return $this->apiBaseUrl . '/' . ltrim($path, '/');

	}

	/**
	 * Build a model-scoped Gemini API path.
	 */
	private function buildModelPath(string $model, string $method): string {

		return '/models/' . rawurlencode($this->normalizeModelName($model)) . ':' . $method;

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
				throw new PairException('Gemini request failed with HTTP ' . $statusCode . '.', ErrorCodes::GEMINI_ERROR);
			}

			return [];

		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('Gemini returned an invalid JSON response.', ErrorCodes::GEMINI_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->resolveErrorMessage($decodedResponse, $statusCode), ErrorCodes::GEMINI_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Convert system instruction text into Gemini's native content shape.
	 */
	private function normalizeSystemInstruction(mixed $instruction): ?array {

		if (is_null($instruction)) {
			return null;
		}

		if (is_array($instruction)) {
			return $instruction;
		}

		$instruction = trim((string)$instruction);

		if ('' === $instruction) {
			return null;
		}

		return [
			'parts' => [
				['text' => $instruction],
			],
		];

	}

	/**
	 * Normalize generation options into Gemini's generationConfig object.
	 */
	private function normalizeGenerationConfig(array $options): ?array {

		$config = $options['generation_config'] ?? $options['generationConfig'] ?? [];

		if (!is_array($config)) {
			throw new PairException('Gemini generation configuration must be an array.', ErrorCodes::GEMINI_ERROR);
		}

		$aliases = [
			'temperature' => 'temperature',
			'top_p' => 'topP',
			'top_k' => 'topK',
			'candidate_count' => 'candidateCount',
			'max_output_tokens' => 'maxOutputTokens',
			'stop_sequences' => 'stopSequences',
			'response_mime_type' => 'responseMimeType',
			'response_schema' => 'responseSchema',
			'thinking_config' => 'thinkingConfig',
		];

		foreach ($aliases as $optionKey => $payloadKey) {
			if (array_key_exists($optionKey, $options)) {
				$config[$payloadKey] = $options[$optionKey];
			}
		}

		$config = $this->withoutNullValues($config);

		return count($config) ? $config : null;

	}

	/**
	 * Normalize raw generateContent input into a list of Gemini contents.
	 */
	private function normalizeContents(string|array $contents): array {

		if (is_string($contents)) {
			return [
				[
					'role' => 'user',
					'parts' => [
						['text' => $contents],
					],
				],
			];
		}

		if (isset($contents['parts']) and is_array($contents['parts'])) {
			return [$contents];
		}

		if (array_is_list($contents)) {
			$first = $contents[0] ?? null;

			if (is_array($first) and (isset($first['parts']) or isset($first['role']))) {
				return array_values($contents);
			}

			return [
				[
					'role' => 'user',
					'parts' => $this->normalizeParts($contents, 'Gemini content parts'),
				],
			];
		}

		return [$contents];

	}

	/**
	 * Normalize embedding input into a single Gemini content object.
	 */
	private function normalizeEmbeddingContent(string|array $content): array {

		if (is_string($content)) {
			return [
				'parts' => [
					['text' => $content],
				],
			];
		}

		if (isset($content['parts']) and is_array($content['parts'])) {
			return $content;
		}

		return [
			'parts' => $this->normalizeParts($content, 'Gemini embedding content'),
		];

	}

	/**
	 * Normalize chat content into Gemini part objects.
	 */
	private function normalizeParts(mixed $content, string $label): array {

		if (is_string($content)) {
			$content = trim($content);

			if ('' === $content) {
				throw new PairException($label . ' cannot be empty.', ErrorCodes::GEMINI_ERROR);
			}

			return [
				['text' => $content],
			];
		}

		if (!is_array($content) or !count($content)) {
			throw new PairException($label . ' must be a non-empty string or array.', ErrorCodes::GEMINI_ERROR);
		}

		if (isset($content['text']) and is_string($content['text'])) {
			return [
				['text' => $content['text']],
			];
		}

		$parts = [];

		foreach (array_is_list($content) ? $content : [$content] as $part) {

			if (is_string($part) and '' !== trim($part)) {
				$parts[] = ['text' => trim($part)];
				continue;
			}

			if (is_array($part) and ($part['type'] ?? null) === 'text' and isset($part['text'])) {
				$parts[] = ['text' => (string)$part['text']];
				continue;
			}

			if (is_array($part)) {
				$parts[] = $part;
			}

		}

		if (!count($parts)) {
			throw new PairException($label . ' does not contain valid parts.', ErrorCodes::GEMINI_ERROR);
		}

		return $parts;

	}

	/**
	 * Convert message content to plain text for Gemini system instructions.
	 */
	private function messageContentToText(mixed $content, string $label): string {

		$parts = $this->normalizeParts($content, $label);
		$text = [];

		foreach ($parts as $part) {
			if (isset($part['text']) and is_string($part['text'])) {
				$text[] = $part['text'];
			}
		}

		$text = trim(implode("\n", $text));

		if ('' === $text) {
			throw new PairException($label . ' must contain text.', ErrorCodes::GEMINI_ERROR);
		}

		return $text;

	}

	/**
	 * Convert common chat roles into Gemini roles.
	 */
	private function normalizeChatRole(string $role): string {

		if ('assistant' === $role) {
			return 'model';
		}

		if ('user' === $role or 'model' === $role) {
			return $role;
		}

		throw new PairException('Unsupported Gemini chat role: ' . $role, ErrorCodes::GEMINI_ERROR);

	}

	/**
	 * Strip optional API path prefixes from model names.
	 */
	private function normalizeModelName(string $model): string {

		return ltrim(preg_replace('#^models/#', '', trim($model)) ?? '', '/');

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
	 * Resolve the most useful error message from a Gemini error response.
	 */
	private function resolveErrorMessage(array $response, int $statusCode): string {

		if (isset($response['error']) and is_array($response['error'])) {
			$message = trim((string)($response['error']['message'] ?? ''));
			$status = trim((string)($response['error']['status'] ?? ''));

			if ('' !== $message) {
				return 'Gemini API error: ' . $message . ('' !== $status ? ' (' . $status . ')' : '');
			}
		}

		return 'Gemini request failed with HTTP ' . $statusCode . '.';

	}

	/**
	 * Resolve a model argument or default value.
	 *
	 * @throws PairException
	 */
	private function resolveModel(mixed $model, string $fallback, string $label): string {

		$model = trim((string)($model ?? $fallback));

		if ('' === $model) {
			throw new PairException('Gemini model cannot be empty. Configure ' . $label . '.', ErrorCodes::MISSING_CONFIGURATION);
		}

		return $model;

	}

	/**
	 * Validate and normalize the API base URL.
	 */
	private function sanitizeApiBaseUrl(string $apiBaseUrl): string {

		$apiBaseUrl = rtrim(trim($apiBaseUrl), '/');

		if ('' === $apiBaseUrl or !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('GEMINI_API_BASE_URL is not valid.', ErrorCodes::GEMINI_ERROR);
		}

		return $apiBaseUrl;

	}

}
