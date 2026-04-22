<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Data\ReadModel;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Search\SearchIndexableReadModel;

/**
 * Lightweight Meilisearch HTTP client for optional search extensions.
 */
class MeilisearchClient {

	/**
	 * Default Meilisearch host for local development.
	 */
	private const DEFAULT_HOST = 'http://127.0.0.1:7700';

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 3;

	/**
	 * Default search result limit.
	 */
	private const DEFAULT_SEARCH_LIMIT = 20;

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 10;

	/**
	 * Meilisearch API key.
	 */
	private string $apiKey;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Default index UID used when callers omit the index.
	 */
	private string $defaultIndexUid;

	/**
	 * Meilisearch server base URL.
	 */
	private string $host;

	/**
	 * Default search result limit.
	 */
	private int $searchLimit;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Build a client using explicit arguments or Env defaults.
	 */
	public function __construct(?string $host = null, ?string $apiKey = null, ?string $defaultIndexUid = null, ?int $timeout = null, ?int $connectTimeout = null, ?int $searchLimit = null) {

		$this->host = $this->sanitizeHost((string)($host ?? Env::get('MEILISEARCH_HOST') ?? self::DEFAULT_HOST));
		$this->apiKey = trim((string)($apiKey ?? Env::get('MEILISEARCH_API_KEY')));
		$this->defaultIndexUid = trim((string)($defaultIndexUid ?? Env::get('MEILISEARCH_DEFAULT_INDEX')));
		$this->timeout = max(1, (int)($timeout ?? Env::get('MEILISEARCH_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('MEILISEARCH_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));
		$this->searchLimit = max(1, (int)($searchLimit ?? Env::get('MEILISEARCH_SEARCH_LIMIT') ?? self::DEFAULT_SEARCH_LIMIT));

	}

	/**
	 * Add or replace full documents in an index.
	 *
	 * @param	array<int, array<string, mixed>>	$documents	Documents to index.
	 */
	public function addOrReplaceDocuments(string $indexUid, array $documents, ?string $primaryKey = null): array {

		return $this->requestJson('POST', $this->documentsPath($indexUid, $primaryKey), $this->assertDocuments($documents));

	}

	/**
	 * Add or update documents in an index without replacing omitted fields.
	 *
	 * @param	array<int, array<string, mixed>>	$documents	Documents to index.
	 */
	public function addOrUpdateDocuments(string $indexUid, array $documents, ?string $primaryKey = null): array {

		return $this->requestJson('PUT', $this->documentsPath($indexUid, $primaryKey), $this->assertDocuments($documents));

	}

	/**
	 * Check whether an API key is configured for authenticated requests.
	 */
	public function apiKeySet(): bool {

		return '' !== $this->apiKey;

	}

	/**
	 * Create an index and optionally define its primary key.
	 */
	public function createIndex(string $indexUid, ?string $primaryKey = null): array {

		$payload = [
			'uid' => $this->sanitizeIndexUid($indexUid),
			'primaryKey' => $this->normalizeNullableString($primaryKey),
		];

		return $this->requestJson('POST', '/indexes', $this->withoutNullValues($payload));

	}

	/**
	 * Delete one document from an index.
	 */
	public function deleteDocument(string $indexUid, string|int $documentId): array {

		$documentId = $this->sanitizeDocumentId($documentId);

		return $this->requestJson('DELETE', '/indexes/' . rawurlencode($this->sanitizeIndexUid($indexUid)) . '/documents/' . rawurlencode($documentId));

	}

	/**
	 * Delete several documents from an index.
	 *
	 * @param	array<int, string|int>	$documentIds	Document identifiers to delete.
	 */
	public function deleteDocuments(string $indexUid, array $documentIds): array {

		if (!count($documentIds)) {
			throw new PairException('Meilisearch document id list cannot be empty.', ErrorCodes::MEILISEARCH_ERROR);
		}

		return $this->requestJson('POST', '/indexes/' . rawurlencode($this->sanitizeIndexUid($indexUid)) . '/documents/delete-batch', array_map([$this, 'sanitizeDocumentId'], $documentIds));

	}

	/**
	 * Search facet values within an index.
	 */
	public function facetSearch(string $indexUid, string $facetName, ?string $facetQuery = null, array $options = []): array {

		$payload = $this->normalizeOptions(array_merge($options, [
			'facetName' => $facetName,
			'facetQuery' => $facetQuery,
		]));

		return $this->requestJson('POST', '/indexes/' . rawurlencode($this->sanitizeIndexUid($indexUid)) . '/facet-search', $this->withoutNullValues($payload));

	}

	/**
	 * Return the task status for a Meilisearch asynchronous operation.
	 */
	public function getTask(string|int $taskUid): array {

		$taskUid = $this->sanitizeDocumentId($taskUid);

		return $this->requestJson('GET', '/tasks/' . rawurlencode($taskUid));

	}

	/**
	 * Return Meilisearch health information.
	 */
	public function health(): array {

		return $this->requestJson('GET', '/health');

	}

	/**
	 * Index read models and return the Meilisearch task response.
	 *
	 * @param	iterable<int, ReadModel>	$readModels	Read models to publish.
	 */
	public function indexReadModels(iterable $readModels, ?string $indexUid = null, ?string $primaryKey = null, bool $replace = false): array {

		$documents = [];

		foreach ($readModels as $readModel) {

			if (!$readModel instanceof ReadModel) {
				throw new PairException('Meilisearch read model sync requires Pair\Data\ReadModel instances.', ErrorCodes::MEILISEARCH_ERROR);
			}

			if ($readModel instanceof SearchIndexableReadModel) {
				$modelIndexUid = $this->sanitizeIndexUid($readModel::searchIndexUid());
				$modelPrimaryKey = $this->normalizeNullableString($readModel::searchPrimaryKey());

				if (!is_null($indexUid) and $modelIndexUid !== $indexUid) {
					throw new PairException('Meilisearch read model batch cannot target multiple index UIDs.', ErrorCodes::MEILISEARCH_ERROR);
				}

				if (!is_null($primaryKey) and $modelPrimaryKey !== $primaryKey) {
					throw new PairException('Meilisearch read model batch cannot target multiple primary keys.', ErrorCodes::MEILISEARCH_ERROR);
				}

				$indexUid = $modelIndexUid;
				$primaryKey = $modelPrimaryKey;
				$documents[] = $readModel->searchDocument();
				continue;
			}

			if (is_null($indexUid)) {
				throw new PairException('Plain read model indexing requires an explicit Meilisearch index UID.', ErrorCodes::MEILISEARCH_ERROR);
			}

			$documents[] = $readModel->toArray();

		}

		$indexUid = $this->resolveIndexUid($indexUid);

		return $replace
			? $this->addOrReplaceDocuments($indexUid, $documents, $primaryKey)
			: $this->addOrUpdateDocuments($indexUid, $documents, $primaryKey);

	}

	/**
	 * Search documents in an index using Meilisearch's POST search endpoint.
	 */
	public function search(string $indexUid, string $query = '', array $options = []): array {

		$options['q'] = $query;
		if (!array_key_exists('limit', $options) and !array_key_exists('hitsPerPage', $options) and !array_key_exists('hits_per_page', $options)) {
			$options['limit'] = $this->searchLimit;
		}

		return $this->requestJson('POST', '/indexes/' . rawurlencode($this->sanitizeIndexUid($indexUid)) . '/search', $this->withoutNullValues($this->normalizeOptions($options)));

	}

	/**
	 * Update index settings such as filterable, sortable, faceting, or embedders.
	 */
	public function updateSettings(string $indexUid, array $settings): array {

		if (!count($settings)) {
			throw new PairException('Meilisearch settings cannot be empty.', ErrorCodes::MEILISEARCH_ERROR);
		}

		return $this->requestJson('PATCH', '/indexes/' . rawurlencode($this->sanitizeIndexUid($indexUid)) . '/settings', $this->normalizeOptions($settings));

	}

	/**
	 * Return Meilisearch version information.
	 */
	public function version(): array {

		return $this->requestJson('GET', '/version');

	}

	/**
	 * Assert documents are safe to send to Meilisearch.
	 *
	 * @param	array<int, array<string, mixed>>	$documents	Documents to validate.
	 * @return	array<int, array<string, mixed>>
	 */
	private function assertDocuments(array $documents): array {

		if (!count($documents)) {
			throw new PairException('Meilisearch document list cannot be empty.', ErrorCodes::MEILISEARCH_ERROR);
		}

		foreach ($documents as $document) {
			if (!is_array($document) or !count($document)) {
				throw new PairException('Meilisearch documents must be non-empty arrays.', ErrorCodes::MEILISEARCH_ERROR);
			}
		}

		return array_values($documents);

	}

	/**
	 * Build an index documents path with an optional primary key query string.
	 */
	private function documentsPath(string $indexUid, ?string $primaryKey = null): string {

		$path = '/indexes/' . rawurlencode($this->sanitizeIndexUid($indexUid)) . '/documents';
		$primaryKey = $this->normalizeNullableString($primaryKey);

		return $primaryKey ? $path . '?primaryKey=' . rawurlencode($primaryKey) : $path;

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
				throw new PairException('Meilisearch request failed with HTTP ' . $statusCode . '.', ErrorCodes::MEILISEARCH_ERROR);
			}

			return [];
		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('Meilisearch returned an invalid JSON response.', ErrorCodes::MEILISEARCH_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->resolveErrorMessage($decodedResponse, $statusCode), ErrorCodes::MEILISEARCH_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Normalize nullable string values.
	 */
	private function normalizeNullableString(mixed $value): ?string {

		$value = trim((string)$value);

		return '' === $value ? null : $value;

	}

	/**
	 * Normalize common snake_case options into Meilisearch camelCase parameters.
	 */
	private function normalizeOptions(array $options): array {

		$aliases = [
			'attributes_to_crop' => 'attributesToCrop',
			'attributes_to_highlight' => 'attributesToHighlight',
			'attributes_to_retrieve' => 'attributesToRetrieve',
			'attributes_to_search_on' => 'attributesToSearchOn',
			'crop_marker' => 'cropMarker',
			'crop_length' => 'cropLength',
			'displayed_attributes' => 'displayedAttributes',
			'distinct_attribute' => 'distinctAttribute',
			'exhaustive_facet_count' => 'exhaustiveFacetCount',
			'facet_name' => 'facetName',
			'facet_query' => 'facetQuery',
			'filterable_attributes' => 'filterableAttributes',
			'hits_per_page' => 'hitsPerPage',
			'localized_attributes' => 'localizedAttributes',
			'matching_strategy' => 'matchingStrategy',
			'non_separator_tokens' => 'nonSeparatorTokens',
			'pagination_max_total_hits' => 'paginationMaxTotalHits',
			'proximity_precision' => 'proximityPrecision',
			'ranking_score_threshold' => 'rankingScoreThreshold',
			'ranking_rules' => 'rankingRules',
			'retrieve_vectors' => 'retrieveVectors',
			'searchable_attributes' => 'searchableAttributes',
			'separator_tokens' => 'separatorTokens',
			'show_matches_position' => 'showMatchesPosition',
			'show_ranking_score' => 'showRankingScore',
			'show_ranking_score_details' => 'showRankingScoreDetails',
			'sort_facet_values_by' => 'sortFacetValuesBy',
			'sortable_attributes' => 'sortableAttributes',
			'stop_words' => 'stopWords',
			'typo_tolerance' => 'typoTolerance',
		];

		foreach ($aliases as $alias => $canonical) {
			if (array_key_exists($alias, $options) and !array_key_exists($canonical, $options)) {
				$options[$canonical] = $options[$alias];
			}

			unset($options[$alias]);
		}

		return $this->withoutNullValues($options);

	}

	/**
	 * Execute an authenticated JSON request against Meilisearch.
	 *
	 * @throws PairException
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$method = strtoupper(trim($method));
		$curl = curl_init($this->host . '/' . ltrim($path, '/'));

		if (false === $curl) {
			throw new PairException('Unable to initialize Meilisearch request.', ErrorCodes::MEILISEARCH_ERROR);
		}

		$headers = [
			'Accept: application/json',
		];

		if ('' !== $this->apiKey) {
			$headers[] = 'Authorization: Bearer ' . $this->apiKey;
		}

		if (!in_array($method, ['GET', 'DELETE'], true) or count($payload)) {
			$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if (false === $encodedPayload) {
				curl_close($curl);
				throw new PairException('Unable to encode Meilisearch request payload.', ErrorCodes::MEILISEARCH_ERROR);
			}

			$headers[] = 'Content-Type: application/json';
			curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Meilisearch request failed: ' . $error, ErrorCodes::MEILISEARCH_ERROR);
		}

		curl_close($curl);

		return $this->decodeJsonResponse($statusCode, $responseBody);

	}

	/**
	 * Resolve the index UID from an explicit argument or the configured default.
	 */
	private function resolveIndexUid(?string $indexUid): string {

		return $this->sanitizeIndexUid($indexUid ?? $this->defaultIndexUid);

	}

	/**
	 * Resolve the most useful API error message from a Meilisearch response.
	 */
	private function resolveErrorMessage(array $response, int $statusCode): string {

		$message = trim((string)($response['message'] ?? $response['error'] ?? ''));
		$code = trim((string)($response['code'] ?? $response['errorCode'] ?? ''));

		if ('' !== $message) {
			return 'Meilisearch API error: ' . $message . ('' !== $code ? ' (' . $code . ')' : '');
		}

		return 'Meilisearch request failed with HTTP ' . $statusCode . '.';

	}

	/**
	 * Validate and normalize one document identifier.
	 */
	private function sanitizeDocumentId(string|int $documentId): string {

		$documentId = trim((string)$documentId);

		if ('' === $documentId) {
			throw new PairException('Meilisearch document id cannot be empty.', ErrorCodes::MEILISEARCH_ERROR);
		}

		return $documentId;

	}

	/**
	 * Validate and normalize the Meilisearch host URL.
	 */
	private function sanitizeHost(string $host): string {

		$host = rtrim(trim($host), '/');

		if ('' === $host or !filter_var($host, FILTER_VALIDATE_URL)) {
			throw new PairException('MEILISEARCH_HOST is not valid.', ErrorCodes::MEILISEARCH_ERROR);
		}

		return $host;

	}

	/**
	 * Validate and normalize a Meilisearch index UID.
	 */
	private function sanitizeIndexUid(string $indexUid): string {

		$indexUid = trim($indexUid);

		if ('' === $indexUid) {
			throw new PairException('Meilisearch index UID cannot be empty.', ErrorCodes::MEILISEARCH_ERROR);
		}

		return $indexUid;

	}

	/**
	 * Remove null values recursively from request payloads.
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

}
