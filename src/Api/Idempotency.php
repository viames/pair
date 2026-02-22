<?php

namespace Pair\Api;

use Pair\Helpers\Utilities;

/**
 * File-based idempotency helper for API endpoints.
 *
 * Usage:
 * 1) Call respondIfDuplicate() at the beginning of a mutating action.
 * 2) Call storeResponse() before sending ApiResponse::respond().
 * 3) Optionally call clearProcessing() in catch/failure paths.
 */
class Idempotency {

	/**
	 * Reads and responds with cached result for duplicate requests, or marks key as processing.
	 * Returns true only when no key is provided and execution should continue without idempotency.
	 */
	public static function respondIfDuplicate(Request $request, string $scope, int $ttlSeconds = 86400): bool {

		$key = $request->idempotencyKey();

		if (is_null($key) or !strlen($key)) {
			return true;
		}

		$hash = static::requestHash($request);
		$file = static::filePath($scope, $key);
		$row = static::readRow($file);

		if (is_array($row)) {

			if (isset($row['expiresAt']) and time() > intval($row['expiresAt'])) {
				static::deleteRow($file);
				$row = null;
			}

		}

		if (is_array($row)) {

			if (isset($row['requestHash']) and $row['requestHash'] !== $hash) {
				ApiResponse::error('CONFLICT', ['detail' => 'Idempotency key already used with different payload']);
			}

			if (($row['status'] ?? '') === 'done') {
				$httpCode = isset($row['httpCode']) ? intval($row['httpCode']) : 200;
				$data = $row['data'] ?? null;
				Utilities::jsonResponse($data, $httpCode);
			}

			if (($row['status'] ?? '') === 'processing') {
				ApiResponse::error('CONFLICT', ['detail' => 'A request with this idempotency key is already processing']);
			}

		}

		static::writeRow($file, [
			'key' => $key,
			'scope' => $scope,
			'status' => 'processing',
			'createdAt' => time(),
			'expiresAt' => time() + max(1, $ttlSeconds),
			'requestHash' => $hash,
		]);

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

		$file = static::filePath($scope, $key);
		$hash = static::requestHash($request);

		return static::writeRow($file, [
			'key' => $key,
			'scope' => $scope,
			'status' => 'done',
			'createdAt' => time(),
			'expiresAt' => time() + max(1, $ttlSeconds),
			'requestHash' => $hash,
			'httpCode' => $httpCode,
			'data' => $data,
		]);

	}

	/**
	 * Clears a processing lock for the current idempotency key.
	 */
	public static function clearProcessing(Request $request, string $scope): bool {

		$key = $request->idempotencyKey();

		if (is_null($key) or !strlen($key)) {
			return false;
		}

		$file = static::filePath($scope, $key);
		return static::deleteRow($file);

	}

	/**
	 * Deletes row file.
	 */
	private static function deleteRow(string $file): bool {

		if (!file_exists($file)) {
			return true;
		}

		return unlink($file);

	}

	/**
	 * Returns row file path for a scope/key pair.
	 */
	private static function filePath(string $scope, string $key): string {

		$folder = static::storageFolder();
		$hash = hash('sha256', trim($scope) . '|' . trim($key));

		return $folder . '/' . $hash . '.json';

	}

	/**
	 * Reads a row as associative array.
	 */
	private static function readRow(string $file): ?array {

		if (!file_exists($file)) {
			return null;
		}

		$json = file_get_contents($file);

		if (!is_string($json) or !strlen($json)) {
			return null;
		}

		$data = json_decode($json, true);

		return is_array($data) ? $data : null;

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
	 * Writes a row file as JSON.
	 */
	private static function writeRow(string $file, array $row): bool {

		$json = json_encode($row, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

		if (!is_string($json)) {
			return false;
		}

		return (false !== file_put_contents($file, $json, LOCK_EX));

	}

}
