<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Lightweight Supabase HTTP client for optional server-side bridge workflows.
 */
class SupabaseClient {

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 20;

	/**
	 * Public anon or publishable key used for user-scoped requests.
	 */
	private string $anonKey;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Private service role key used only by trusted server-side calls.
	 */
	private string $serviceRoleKey;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Supabase project URL.
	 */
	private string $url;

	/**
	 * Build a Supabase client from explicit arguments or Env defaults.
	 */
	public function __construct(?string $url = null, ?string $anonKey = null, ?string $serviceRoleKey = null, ?int $timeout = null, ?int $connectTimeout = null) {

		$this->url = $this->sanitizeUrl((string)($url ?? Env::get('SUPABASE_URL')));
		$this->anonKey = trim((string)($anonKey ?? Env::get('SUPABASE_ANON_KEY')));
		$this->serviceRoleKey = trim((string)($serviceRoleKey ?? Env::get('SUPABASE_SERVICE_ROLE_KEY')));
		$this->timeout = max(1, (int)($timeout ?? Env::get('SUPABASE_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('SUPABASE_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));

	}

	/**
	 * Check whether a public anon or publishable key is configured.
	 */
	public function anonKeySet(): bool {

		return '' !== $this->anonKey;

	}

	/**
	 * Retrieve the currently authenticated Supabase Auth user with a user JWT.
	 */
	public function authUser(string $accessToken): array {

		$accessToken = $this->assertToken($accessToken, 'Supabase access token cannot be empty.');

		return $this->requestJson('GET', '/auth/v1/user', null, [], [
			'bearerToken' => $accessToken,
			'serviceRole' => false,
		]);

	}

	/**
	 * Retrieve a Supabase Auth user by id using the server-side service role key.
	 */
	public function authAdminGetUser(string $userId): array {

		return $this->requestJson('GET', '/auth/v1/admin/users/' . rawurlencode($this->assertPathSegment($userId, 'Supabase user id')), null, [], [
			'serviceRole' => true,
		]);

	}

	/**
	 * Check whether a service role key is configured.
	 */
	public function serviceRoleKeySet(): bool {

		return '' !== $this->serviceRoleKey;

	}

	/**
	 * Download one object from Supabase Storage.
	 */
	public function storageDownload(string $bucket, string $path, array $options = []): string {

		return $this->requestBinary('GET', $this->storageObjectPath($bucket, $path), [], $this->requestOptions($options, true));

	}

	/**
	 * Delete one or more objects from Supabase Storage.
	 *
	 * @param	list<string>	$paths	Object paths to delete.
	 */
	public function storageDelete(string $bucket, array $paths, array $options = []): array {

		if (!count($paths)) {
			throw new PairException('Supabase storage delete requires at least one object path.', ErrorCodes::SUPABASE_ERROR);
		}

		$prefixes = [];
		foreach ($paths as $path) {
			$prefixes[] = $this->sanitizeStorageObjectPath($path);
		}

		return $this->requestJson('DELETE', '/storage/v1/object/' . rawurlencode($this->assertIdentifier($bucket, 'Supabase storage bucket')), [
			'prefixes' => $prefixes,
		], [], $this->requestOptions($options, true));

	}

	/**
	 * List objects in a Supabase Storage bucket.
	 */
	public function storageList(string $bucket, string $prefix = '', array $options = []): array {

		$payload = $this->withoutNullValues([
			'prefix' => $prefix,
			'limit' => $options['limit'] ?? null,
			'offset' => $options['offset'] ?? null,
			'sortBy' => $options['sortBy'] ?? $options['sort_by'] ?? null,
			'search' => $options['search'] ?? null,
		]);

		return $this->requestJson('POST', '/storage/v1/object/list/' . rawurlencode($this->assertIdentifier($bucket, 'Supabase storage bucket')), $payload, [], $this->requestOptions($options, true));

	}

	/**
	 * Return the public URL for an object in a public Supabase Storage bucket.
	 */
	public function storagePublicUrl(string $bucket, string $path, array $query = []): string {

		$this->assertUrlConfigured();

		$url = $this->url . '/storage/v1/object/public/' . rawurlencode($this->assertIdentifier($bucket, 'Supabase storage bucket')) . '/' . $this->encodedStoragePath($path);

		return $this->appendQuery($url, $query);

	}

	/**
	 * Create a signed download URL for one Supabase Storage object.
	 */
	public function storageSignedUrl(string $bucket, string $path, int $expiresIn, array $options = []): array {

		$payload = $this->withoutNullValues([
			'expiresIn' => max(1, $expiresIn),
			'download' => $options['download'] ?? null,
			'transform' => $options['transform'] ?? null,
		]);

		return $this->requestJson('POST', '/storage/v1/object/sign/' . rawurlencode($this->assertIdentifier($bucket, 'Supabase storage bucket')) . '/' . $this->encodedStoragePath($path), $payload, [], $this->requestOptions($options, true));

	}

	/**
	 * Upload one object to Supabase Storage.
	 */
	public function storageUpload(string $bucket, string $path, string $contents, ?string $contentType = null, array $options = []): array {

		$headers = $this->storageUploadHeaders($contentType, $options);

		return $this->requestJson('POST', $this->storageObjectPath($bucket, $path), $contents, $headers, $this->requestOptions($options, true));

	}

	/**
	 * Return a Supabase Realtime WebSocket URL for external clients.
	 */
	public function realtimeWebSocketUrl(array $params = []): string {

		$this->assertUrlConfigured();

		$parts = parse_url($this->url);
		if (!is_array($parts) or empty($parts['scheme']) or empty($parts['host'])) {
			throw new PairException('SUPABASE_URL is not valid.', ErrorCodes::SUPABASE_ERROR);
		}

		$scheme = 'https' === strtolower((string)$parts['scheme']) ? 'wss' : 'ws';
		$port = isset($parts['port']) ? ':' . $parts['port'] : '';
		$query = array_merge($params, [
			'apikey' => $this->publicKey(),
			'vsn' => $params['vsn'] ?? '1.0.0',
		]);

		return $scheme . '://' . $parts['host'] . $port . '/realtime/v1/websocket?' . http_build_query($query);

	}

	/**
	 * Call a Postgres function through Supabase PostgREST RPC.
	 */
	public function rpc(string $function, array $arguments = [], array $options = []): array {

		return $this->requestJson('POST', '/rest/v1/rpc/' . rawurlencode($this->assertIdentifier($function, 'Supabase RPC function')), $arguments, $this->postgrestHeaders($options), $this->requestOptions($options));

	}

	/**
	 * Select rows from a Supabase PostgREST table or view.
	 */
	public function restSelect(string $table, array $query = [], array $options = []): array {

		if (isset($options['select']) and !isset($query['select'])) {
			$query['select'] = $options['select'];
		}

		$options['query'] = array_merge($query, $options['query'] ?? []);

		return $this->requestJson('GET', '/rest/v1/' . rawurlencode($this->assertIdentifier($table, 'Supabase REST table')), null, $this->postgrestHeaders($options), $this->requestOptions($options));

	}

	/**
	 * Check whether a Supabase project URL is configured.
	 */
	public function urlSet(): bool {

		return '' !== $this->url;

	}

	/**
	 * Append query parameters to a URL.
	 */
	private function appendQuery(string $url, array $query): string {

		$query = $this->withoutNullValues($query);

		if (!count($query)) {
			return $url;
		}

		return $url . '?' . http_build_query($query);

	}

	/**
	 * Assert that a Supabase key or bearer token is present.
	 */
	private function assertToken(string $token, string $message): string {

		$token = trim($token);

		if ('' === $token) {
			throw new PairException($message, ErrorCodes::SUPABASE_ERROR);
		}

		return $token;

	}

	/**
	 * Assert that a Supabase path identifier contains only safe characters.
	 */
	private function assertIdentifier(string $identifier, string $label): string {

		$identifier = trim($identifier);

		if ('' === $identifier or !preg_match('/^[A-Za-z0-9_.-]+$/', $identifier)) {
			throw new PairException($label . ' is not valid.', ErrorCodes::SUPABASE_ERROR);
		}

		return $identifier;

	}

	/**
	 * Assert that one URL path segment is present.
	 */
	private function assertPathSegment(string $segment, string $label): string {

		$segment = trim($segment);

		if ('' === $segment or str_contains($segment, '/')) {
			throw new PairException($label . ' is not valid.', ErrorCodes::SUPABASE_ERROR);
		}

		return $segment;

	}

	/**
	 * Assert that SUPABASE_URL is available before URL construction.
	 */
	private function assertUrlConfigured(): void {

		if ('' === $this->url) {
			throw new PairException('Missing Supabase URL. Set SUPABASE_URL.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Decode a JSON response body into an array.
	 *
	 * @return	array<string|int, mixed>
	 */
	private function decodeJsonResponse(string $body): array {

		$body = trim($body);

		if ('' === $body) {
			return [];
		}

		$decoded = json_decode($body, true);

		if (!is_array($decoded)) {
			throw new PairException('Supabase response is not valid JSON.', ErrorCodes::SUPABASE_ERROR);
		}

		return $decoded;

	}

	/**
	 * Return the URL-encoded object path while preserving path separators.
	 */
	private function encodedStoragePath(string $path): string {

		return implode('/', array_map('rawurlencode', explode('/', $this->sanitizeStorageObjectPath($path))));

	}

	/**
	 * Convert associative headers to cURL header lines.
	 *
	 * @return	list<string>
	 */
	private function headerLines(array $headers): array {

		$lines = [];

		foreach ($headers as $name => $value) {
			if (is_int($name)) {
				$lines[] = (string)$value;
				continue;
			}

			$lines[] = $name . ': ' . $value;
		}

		return $lines;

	}

	/**
	 * Return true when a header already exists.
	 */
	private function headerSet(array $headers, string $name): bool {

		foreach ($headers as $headerName => $value) {
			if (!is_int($headerName) and 0 === strcasecmp((string)$headerName, $name)) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Return the public key used for anon, authenticated-user, and Realtime flows.
	 */
	private function publicKey(): string {

		if ('' !== $this->anonKey) {
			return $this->anonKey;
		}

		if ('' !== $this->serviceRoleKey) {
			return $this->serviceRoleKey;
		}

		throw new PairException('Missing Supabase API key. Set SUPABASE_ANON_KEY or SUPABASE_SERVICE_ROLE_KEY.', ErrorCodes::MISSING_CONFIGURATION);

	}

	/**
	 * Execute an authenticated Supabase binary request and return the raw response body.
	 */
	protected function requestBinary(string $method, string $path, array $headers = [], array $options = []): string {

		$requestHeaders = $this->requestHeaders($headers, $options);
		$curl = curl_init($this->requestUrl($path, $options['query'] ?? []));

		if (false === $curl) {
			throw new PairException('Unable to initialize Supabase request.', ErrorCodes::SUPABASE_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headerLines($requestHeaders));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Supabase request failed: ' . $error, ErrorCodes::SUPABASE_ERROR);
		}

		curl_close($curl);
		$this->validateStatus($statusCode);

		return (string)$responseBody;

	}

	/**
	 * Execute an authenticated Supabase JSON request.
	 *
	 * @param	array<string, mixed>|string|null	$payload	Request payload.
	 * @return	array<string|int, mixed>
	 */
	protected function requestJson(string $method, string $path, array|string|null $payload = null, array $headers = [], array $options = []): array {

		$requestBody = null;
		if (is_array($payload)) {
			$requestBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if (false === $requestBody) {
				throw new PairException('Unable to encode Supabase request payload.', ErrorCodes::SUPABASE_ERROR);
			}

			if (!$this->headerSet($headers, 'Content-Type')) {
				$headers['Content-Type'] = 'application/json';
			}
		} else if (is_string($payload)) {
			$requestBody = $payload;
		}

		$requestHeaders = $this->requestHeaders($headers, $options);
		$curl = curl_init($this->requestUrl($path, $options['query'] ?? []));

		if (false === $curl) {
			throw new PairException('Unable to initialize Supabase request.', ErrorCodes::SUPABASE_ERROR);
		}

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headerLines($requestHeaders));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		if (!is_null($requestBody)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
		}

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Supabase request failed: ' . $error, ErrorCodes::SUPABASE_ERROR);
		}

		curl_close($curl);
		$this->validateStatus($statusCode);

		return $this->decodeJsonResponse((string)$responseBody);

	}

	/**
	 * Return normalized request headers for Supabase HTTP calls.
	 */
	private function requestHeaders(array $headers, array $options = []): array {

		$headers = array_merge([
			'Accept' => 'application/json',
		], $headers);

		$key = ($options['serviceRole'] ?? false) ? $this->serverKey() : $this->publicKey();
		$headers['apikey'] = $headers['apikey'] ?? $key;

		if (!$this->headerSet($headers, 'Authorization')) {
			$bearerToken = $this->normalizeNullableString($options['bearerToken'] ?? null) ?? $key;
			$headers['Authorization'] = 'Bearer ' . $bearerToken;
		}

		if (!empty($options['schema'])) {
			$schema = $this->assertIdentifier((string)$options['schema'], 'Supabase schema');
			$headers['Accept-Profile'] = $headers['Accept-Profile'] ?? $schema;
			$headers['Content-Profile'] = $headers['Content-Profile'] ?? $schema;
		}

		return $headers;

	}

	/**
	 * Return a fully qualified request URL.
	 */
	private function requestUrl(string $path, array $query = []): string {

		$this->assertUrlConfigured();
		$path = '/' . ltrim($path, '/');

		return $this->appendQuery($this->url . $path, $query);

	}

	/**
	 * Normalize request options shared by Supabase helpers.
	 */
	private function requestOptions(array $options, bool $defaultServiceRole = false): array {

		return [
			'bearerToken' => $this->normalizeNullableString($options['bearerToken'] ?? $options['bearer_token'] ?? null),
			'query' => is_array($options['query'] ?? null) ? $options['query'] : [],
			'schema' => $this->normalizeNullableString($options['schema'] ?? null),
			'serviceRole' => (bool)($options['serviceRole'] ?? $options['service_role'] ?? $defaultServiceRole),
		];

	}

	/**
	 * Return the server-side service role key.
	 */
	private function serverKey(): string {

		if ('' === $this->serviceRoleKey) {
			throw new PairException('Missing Supabase service role key. Set SUPABASE_SERVICE_ROLE_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

		return $this->serviceRoleKey;

	}

	/**
	 * Normalize optional strings to a trimmed value or null.
	 */
	private function normalizeNullableString(mixed $value): ?string {

		if (is_null($value)) {
			return null;
		}

		$value = trim((string)$value);

		return '' === $value ? null : $value;

	}

	/**
	 * Return headers for PostgREST requests.
	 */
	private function postgrestHeaders(array $options): array {

		$headers = [];

		if (!empty($options['prefer'])) {
			$headers['Prefer'] = trim((string)$options['prefer']);
		}

		return $headers;

	}

	/**
	 * Validate and normalize the Supabase project URL.
	 */
	private function sanitizeUrl(string $url): string {

		$url = rtrim(trim($url), '/');

		if ('' === $url) {
			return '';
		}

		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			throw new PairException('SUPABASE_URL is not valid.', ErrorCodes::SUPABASE_ERROR);
		}

		return $url;

	}

	/**
	 * Validate and normalize a storage object path.
	 */
	private function sanitizeStorageObjectPath(string $path): string {

		$path = trim($path, '/');

		if ('' === $path) {
			throw new PairException('Supabase storage object path cannot be empty.', ErrorCodes::SUPABASE_ERROR);
		}

		foreach (explode('/', $path) as $segment) {
			if ('' === $segment or '.' === $segment or '..' === $segment) {
				throw new PairException('Supabase storage object path is not valid.', ErrorCodes::SUPABASE_ERROR);
			}
		}

		return $path;

	}

	/**
	 * Return the Supabase Storage object API path.
	 */
	private function storageObjectPath(string $bucket, string $path): string {

		return '/storage/v1/object/' . rawurlencode($this->assertIdentifier($bucket, 'Supabase storage bucket')) . '/' . $this->encodedStoragePath($path);

	}

	/**
	 * Return headers used for Storage upload requests.
	 */
	private function storageUploadHeaders(?string $contentType, array $options): array {

		$headers = [];

		if (!is_null($contentType) and '' !== trim($contentType)) {
			$headers['Content-Type'] = trim($contentType);
		}

		if (isset($options['cacheControl']) or isset($options['cache_control'])) {
			$headers['Cache-Control'] = (string)($options['cacheControl'] ?? $options['cache_control']);
		}

		if (array_key_exists('upsert', $options)) {
			$headers['x-upsert'] = $options['upsert'] ? 'true' : 'false';
		}

		return $headers;

	}

	/**
	 * Validate one Supabase HTTP status code.
	 */
	private function validateStatus(int $statusCode): void {

		if ($statusCode >= 400) {
			throw new PairException('Supabase request failed with HTTP ' . $statusCode . '.', ErrorCodes::SUPABASE_ERROR);
		}

	}

	/**
	 * Remove null values recursively from payloads and query strings.
	 *
	 * @return	array<string|int, mixed>
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
