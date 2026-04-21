<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Lightweight OpenAI API client for Pair applications and optional AI extensions.
 */
class OpenAIClient {

	/**
	 * Default OpenAI REST API base URL.
	 */
	private const DEFAULT_API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default embedding model optimized for cost-sensitive search and indexing.
	 */
	private const DEFAULT_EMBEDDINGS_MODEL = 'text-embedding-3-small';

	/**
	 * Default Realtime model alias used by OpenAI's GA Realtime examples.
	 */
	private const DEFAULT_REALTIME_MODEL = 'gpt-realtime';

	/**
	 * Default Responses model chosen for low-latency application use.
	 */
	private const DEFAULT_RESPONSES_MODEL = 'gpt-5.4-mini';

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 30;

	/**
	 * OpenAI API key.
	 */
	private string $apiKey;

	/**
	 * OpenAI REST API base URL.
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
	 * Default Realtime model.
	 */
	private string $realtimeModel;

	/**
	 * Default Responses model.
	 */
	private string $responsesModel;

	/**
	 * Whether Responses API calls should be stored for later retrieval by default.
	 */
	private bool $storeResponses;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Build a client using explicit arguments or Env defaults.
	 *
	 * Optional Env keys:
	 * - OPENAI_API_KEY
	 * - OPENAI_API_BASE_URL
	 * - OPENAI_RESPONSES_MODEL
	 * - OPENAI_EMBEDDINGS_MODEL
	 * - OPENAI_REALTIME_MODEL
	 * - OPENAI_TIMEOUT
	 * - OPENAI_CONNECT_TIMEOUT
	 * - OPENAI_STORE_RESPONSES
	 */
	public function __construct(?string $apiKey = null, ?string $apiBaseUrl = null, ?string $responsesModel = null, ?string $embeddingsModel = null, ?string $realtimeModel = null, ?int $timeout = null, ?int $connectTimeout = null, ?bool $storeResponses = null) {

		$this->apiKey = trim((string)($apiKey ?? Env::get('OPENAI_API_KEY')));
		$this->apiBaseUrl = $this->sanitizeApiBaseUrl((string)($apiBaseUrl ?? Env::get('OPENAI_API_BASE_URL') ?? self::DEFAULT_API_BASE_URL));
		$this->responsesModel = $this->resolveModel($responsesModel, (string)(Env::get('OPENAI_RESPONSES_MODEL') ?: self::DEFAULT_RESPONSES_MODEL), 'OPENAI_RESPONSES_MODEL');
		$this->embeddingsModel = $this->resolveModel($embeddingsModel, (string)(Env::get('OPENAI_EMBEDDINGS_MODEL') ?: self::DEFAULT_EMBEDDINGS_MODEL), 'OPENAI_EMBEDDINGS_MODEL');
		$this->realtimeModel = $this->resolveModel($realtimeModel, (string)(Env::get('OPENAI_REALTIME_MODEL') ?: self::DEFAULT_REALTIME_MODEL), 'OPENAI_REALTIME_MODEL');
		$this->timeout = max(1, (int)($timeout ?? Env::get('OPENAI_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('OPENAI_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));
		$this->storeResponses = (bool)($storeResponses ?? Env::get('OPENAI_STORE_RESPONSES') ?? false);

	}

	/**
	 * Check whether an API key is configured for outbound requests.
	 */
	public function apiKeySet(): bool {

		return '' !== $this->apiKey;

	}

	/**
	 * Create a Responses API response.
	 *
	 * @param	string|array	$input		Text input or a Responses API input item list.
	 * @param	array		$options	Optional Responses API fields.
	 */
	public function createResponse(string|array $input, array $options = []): array {

		$this->assertInputPresent($input, 'Responses input');

		$payload = [
			'model' => $this->resolveModel($options['model'] ?? null, $this->responsesModel, 'model'),
			'input' => $input,
			'instructions' => $options['instructions'] ?? null,
			'include' => $options['include'] ?? null,
			'previous_response_id' => $options['previous_response_id'] ?? null,
			'conversation' => $options['conversation'] ?? null,
			'prompt' => $options['prompt'] ?? null,
			'metadata' => $options['metadata'] ?? null,
			'tools' => $options['tools'] ?? null,
			'tool_choice' => $options['tool_choice'] ?? null,
			'parallel_tool_calls' => $options['parallel_tool_calls'] ?? null,
			'max_output_tokens' => $options['max_output_tokens'] ?? null,
			'max_tool_calls' => $options['max_tool_calls'] ?? null,
			'temperature' => $options['temperature'] ?? null,
			'top_p' => $options['top_p'] ?? null,
			'reasoning' => $options['reasoning'] ?? null,
			'text' => $options['text'] ?? null,
			'background' => $options['background'] ?? null,
			'context_management' => $options['context_management'] ?? null,
			'service_tier' => $options['service_tier'] ?? null,
			'truncation' => $options['truncation'] ?? null,
			'user' => $options['user'] ?? null,
			'store' => array_key_exists('store', $options) ? (bool)$options['store'] : $this->storeResponses,
		];

		return $this->requestJson('POST', '/responses', $this->withoutNullValues($payload));

	}

	/**
	 * Create a Responses API response and return concatenated text output.
	 */
	public function createTextResponse(string|array $input, array $options = []): string {

		return self::extractOutputText($this->createResponse($input, $options));

	}

	/**
	 * Create a raw embeddings API response.
	 *
	 * @param	string|array	$input	Single input string or multiple inputs.
	 * @param	array		$options	Optional embedding fields: model, dimensions, encoding_format, user.
	 */
	public function createEmbeddingResponse(string|array $input, array $options = []): array {

		$this->assertInputPresent($input, 'Embeddings input');

		$payload = [
			'model' => $this->resolveModel($options['model'] ?? null, $this->embeddingsModel, 'model'),
			'input' => $input,
			'encoding_format' => $options['encoding_format'] ?? 'float',
			'dimensions' => $options['dimensions'] ?? null,
			'user' => $options['user'] ?? null,
		];

		return $this->requestJson('POST', '/embeddings', $this->withoutNullValues($payload));

	}

	/**
	 * Create one float embedding vector for semantic search or indexing.
	 *
	 * @return	array<int, float|int>
	 */
	public function embedText(string $input, array $options = []): array {

		$options['encoding_format'] = 'float';
		$response = $this->createEmbeddingResponse($input, $options);
		$vectors = $this->extractEmbeddingVectors($response);

		return $vectors[0] ?? [];

	}

	/**
	 * Create multiple float embedding vectors for semantic search or indexing.
	 *
	 * @param	string[]	$inputs	Input texts to embed.
	 * @return	array<int, array<int, float|int>>
	 */
	public function embedTexts(array $inputs, array $options = []): array {

		if (!count($inputs)) {
			throw new PairException('Embeddings input list cannot be empty.', ErrorCodes::OPENAI_ERROR);
		}

		foreach ($inputs as $input) {
			if (!is_string($input) or '' === trim($input)) {
				throw new PairException('Embeddings input list must contain non-empty strings.', ErrorCodes::OPENAI_ERROR);
			}
		}

		$options['encoding_format'] = 'float';

		return $this->extractEmbeddingVectors($this->createEmbeddingResponse(array_values($inputs), $options));

	}

	/**
	 * Create a short-lived Realtime client secret for browser or mobile clients.
	 */
	public function createRealtimeClientSecret(array $session = [], array $options = []): array {

		$session['type'] = $session['type'] ?? 'realtime';
		$session['model'] = $this->resolveModel($session['model'] ?? $options['model'] ?? null, $this->realtimeModel, 'model');

		return $this->requestJson('POST', '/realtime/client_secrets', [
			'session' => $this->withoutNullValues($session),
		]);

	}

	/**
	 * Extract concatenated text from a Responses API response.
	 */
	public static function extractOutputText(array $response): string {

		if (isset($response['output_text']) and is_string($response['output_text'])) {
			return $response['output_text'];
		}

		$outputText = [];
		$output = $response['output'] ?? [];

		if (!is_array($output)) {
			return '';
		}

		foreach ($output as $item) {

			if (!is_array($item)) {
				continue;
			}

			$content = $item['content'] ?? [];

			if (!is_array($content)) {
				continue;
			}

			foreach ($content as $contentItem) {

				if (!is_array($contentItem)) {
					continue;
				}

				if (($contentItem['type'] ?? null) === 'output_text' and isset($contentItem['text']) and is_string($contentItem['text'])) {
					$outputText[] = $contentItem['text'];
				}

			}

		}

		return implode('', $outputText);

	}

	/**
	 * Execute an authenticated JSON request against the OpenAI API.
	 *
	 * @throws PairException
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$this->assertApiKeyConfigured();

		$curl = curl_init($this->buildApiUrl($path));

		if (false === $curl) {
			throw new PairException('Unable to initialize OpenAI request.', ErrorCodes::OPENAI_ERROR);
		}

		$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === $encodedPayload) {
			curl_close($curl);
			throw new PairException('Unable to encode OpenAI request payload.', ErrorCodes::OPENAI_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper(trim($method)));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Authorization: Bearer ' . $this->apiKey,
			'Content-Type: application/json',
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('OpenAI request failed: ' . $error, ErrorCodes::OPENAI_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Require an API key before outbound requests.
	 */
	private function assertApiKeyConfigured(): void {

		if ('' === $this->apiKey) {
			throw new PairException('Missing OpenAI API key. Set OPENAI_API_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Ensure API inputs are not empty before making billable requests.
	 */
	private function assertInputPresent(string|array $input, string $label): void {

		if (is_string($input) and '' === trim($input)) {
			throw new PairException($label . ' cannot be empty.', ErrorCodes::OPENAI_ERROR);
		}

		if (is_array($input) and !count($input)) {
			throw new PairException($label . ' list cannot be empty.', ErrorCodes::OPENAI_ERROR);
		}

	}

	/**
	 * Build an absolute OpenAI API URL from a relative path.
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
				throw new PairException('OpenAI request failed with HTTP ' . $statusCode . '.', ErrorCodes::OPENAI_ERROR);
			}

			return [];

		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('OpenAI returned an invalid JSON response.', ErrorCodes::OPENAI_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->resolveErrorMessage($decodedResponse, $statusCode), ErrorCodes::OPENAI_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Extract float embedding vectors sorted by API result index.
	 *
	 * @return	array<int, array<int, float|int>>
	 */
	private function extractEmbeddingVectors(array $response): array {

		$data = $response['data'] ?? null;

		if (!is_array($data)) {
			throw new PairException('OpenAI embeddings response is missing data.', ErrorCodes::OPENAI_ERROR);
		}

		usort($data, function (array $left, array $right): int {
			return ((int)($left['index'] ?? 0)) <=> ((int)($right['index'] ?? 0));
		});

		$vectors = [];

		foreach ($data as $item) {

			$embedding = $item['embedding'] ?? null;

			if (!is_array($embedding)) {
				throw new PairException('OpenAI embeddings response is not a float vector response.', ErrorCodes::OPENAI_ERROR);
			}

			$vectors[] = $embedding;

		}

		return $vectors;

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
	 * Resolve the most useful error message from an OpenAI error response.
	 */
	private function resolveErrorMessage(array $response, int $statusCode): string {

		if (isset($response['error']) and is_array($response['error'])) {
			$message = trim((string)($response['error']['message'] ?? ''));
			$code = trim((string)($response['error']['code'] ?? ''));

			if ('' !== $message) {
				return 'OpenAI API error: ' . $message . ('' !== $code ? ' (' . $code . ')' : '');
			}
		}

		if (isset($response['message']) and is_string($response['message']) and '' !== trim($response['message'])) {
			return trim($response['message']);
		}

		return 'OpenAI request failed with HTTP ' . $statusCode . '.';

	}

	/**
	 * Resolve a model argument or default value.
	 *
	 * @throws PairException
	 */
	private function resolveModel(mixed $model, string $fallback, string $label): string {

		$model = trim((string)($model ?? $fallback));

		if ('' === $model) {
			throw new PairException('OpenAI model cannot be empty. Configure ' . $label . '.', ErrorCodes::MISSING_CONFIGURATION);
		}

		return $model;

	}

	/**
	 * Validate and normalize the API base URL.
	 */
	private function sanitizeApiBaseUrl(string $apiBaseUrl): string {

		$apiBaseUrl = rtrim(trim($apiBaseUrl), '/');

		if ('' === $apiBaseUrl or !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
			throw new PairException('OPENAI_API_BASE_URL is not valid.', ErrorCodes::OPENAI_ERROR);
		}

		return $apiBaseUrl;

	}

}
