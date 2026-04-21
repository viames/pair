<?php

namespace Pair\Api;

use Pair\Cache\CacheStore;
use Pair\Cache\FileCacheStore;
use Pair\Http\ResponseInterface;

/**
 * Cache-backed idempotency helper for API endpoints.
 *
 * Usage:
 * 1) Call duplicateResponse() or respondIfDuplicate() at the beginning of a mutating action.
 * 2) Call storeResponse() before sending ApiResponse::respond() or returning ApiResponse::jsonResponse().
 * 3) Optionally call clearProcessing() in catch/failure paths.
 */
class Idempotency {

	/**
	 * Cache store used to persist idempotency rows for the current PHP process.
	 */
	private static ?CacheStore $store = null;

	/**
	 * Clear the configured cache store so the default file store is resolved again.
	 */
	public static function clearStore(): void {

		self::$store = null;

	}

	/**
	 * Returns an explicit response for duplicate requests, or marks the key as processing
	 * and returns null when the caller should continue normal execution.
	 */
	public static function duplicateResponse(Request $request, string $scope, int $ttlSeconds = 86400): ResponseInterface|null {

		$key = $request->idempotencyKey();

		if (is_null($key) or !strlen($key)) {
			return null;
		}

		$hash = static::requestHash($request);
		$cacheKey = static::cacheKey($scope, $key);
		$row = static::readRow($cacheKey);

		if (is_array($row)) {

			if (isset($row['expiresAt']) and time() > intval($row['expiresAt'])) {
				static::deleteRow($cacheKey);
				$row = null;
			}

		}

		if (is_array($row)) {

			if (isset($row['requestHash']) and $row['requestHash'] !== $hash) {
				return ApiResponse::errorResponse('CONFLICT', ['detail' => 'Idempotency key already used with different payload']);
			}

			if (($row['status'] ?? '') === 'done') {
				$httpCode = isset($row['httpCode']) ? intval($row['httpCode']) : 200;
				$data = $row['data'] ?? null;
				return static::replayResponse($data, $httpCode);
			}

			if (($row['status'] ?? '') === 'processing') {
				return ApiResponse::errorResponse('CONFLICT', ['detail' => 'A request with this idempotency key is already processing']);
			}

		}

		static::writeRow($cacheKey, [
			'key' => $key,
			'scope' => $scope,
			'status' => 'processing',
			'createdAt' => time(),
			'expiresAt' => time() + max(1, $ttlSeconds),
			'requestHash' => $hash,
		], $ttlSeconds);

		return null;

	}

	/**
	 * Reads and responds with cached result for duplicate requests, or marks key as processing.
	 * Returns false when an immediate replay/conflict response was sent.
	 */
	public static function respondIfDuplicate(Request $request, string $scope, int $ttlSeconds = 86400): bool {

		$response = static::duplicateResponse($request, $scope, $ttlSeconds);

		if ($response) {
			$response->send();
			return false;
		}

		return true;

	}

	/**
	 * Stores a completed response for the current idempotency key.
	 */
	public static function storeResponse(Request $request, string $scope, mixed $data, int $httpCode = 200, int $ttlSeconds = 86400): bool {

		$key = $request->idempotencyKey();

		if (is_null($key) or !strlen($key)) {
			return false;
		}

		$cacheKey = static::cacheKey($scope, $key);
		$hash = static::requestHash($request);

		return static::writeRow($cacheKey, [
			'key' => $key,
			'scope' => $scope,
			'status' => 'done',
			'createdAt' => time(),
			'expiresAt' => time() + max(1, $ttlSeconds),
			'requestHash' => $hash,
			'httpCode' => $httpCode,
			'data' => $data,
		], $ttlSeconds);

	}

	/**
	 * Clears a processing lock for the current idempotency key.
	 */
	public static function clearProcessing(Request $request, string $scope): bool {

		$key = $request->idempotencyKey();

		if (is_null($key) or !strlen($key)) {
			return false;
		}

		return static::deleteRow(static::cacheKey($scope, $key));

	}

	/**
	 * Set the cache store used by idempotency rows.
	 */
	public static function setStore(CacheStore $store): void {

		self::$store = $store;

	}

	/**
	 * Build a stable cache key for a scope/key pair without exposing raw client keys to shared stores.
	 */
	private static function cacheKey(string $scope, string $key): string {

		return 'idempotency:' . hash('sha256', trim($scope) . '|' . trim($key));

	}

	/**
	 * Deletes one idempotency row.
	 */
	private static function deleteRow(string $cacheKey): bool {

		return static::store()->delete($cacheKey);

	}

	/**
	 * Return the configured cache store, defaulting to file-backed idempotency storage.
	 */
	private static function store(): CacheStore {

		if (!self::$store) {
			self::$store = new FileCacheStore(static::storageFolder(), 'idempotency');
		}

		return self::$store;

	}

	/**
	 * Reads a row as associative array from the configured cache store.
	 */
	private static function readRow(string $cacheKey): ?array {

		$row = static::store()->get($cacheKey);

		if (!is_array($row)) {
			return null;
		}

		return $row;

	}

	/**
	 * Build an explicit replay response while preserving legacy support for scalar JSON payloads.
	 */
	private static function replayResponse(mixed $data, int $httpCode): ResponseInterface {

		if (is_array($data) or $data instanceof \stdClass or is_null($data)) {
			return ApiResponse::jsonResponse($data, $httpCode);
		}

		return ApiResponse::jsonResponse($data, $httpCode);

	}

	/**
	 * Creates a deterministic request hash for idempotency checks.
	 */
	private static function requestHash(Request $request): string {

		$method = $request->method();
		$uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
		$body = $request->rawBody();

		return hash('sha256', $method . "\n" . $uri . "\n" . $body);

	}

	/**
	 * Returns idempotency storage folder.
	 */
	private static function storageFolder(): string {

		$base = defined('TEMP_PATH') ? TEMP_PATH : (sys_get_temp_dir() . '/pair/');
		$folder = rtrim($base, '/\\') . '/idempotency';

		if (!is_dir($folder)) {
			mkdir($folder, 0755, true);
		}

		return $folder;

	}

	/**
	 * Writes an idempotency row through the configured cache store.
	 */
	private static function writeRow(string $cacheKey, array $row, int $ttlSeconds): bool {

		return static::store()->set($cacheKey, $row, max(1, $ttlSeconds));

	}

}
