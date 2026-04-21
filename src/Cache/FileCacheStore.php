<?php

namespace Pair\Cache;

/**
 * File-backed cache store with no external dependencies.
 */
class FileCacheStore implements CacheStore {

	/**
	 * Directory used for cache files.
	 */
	private string $directory;

	/**
	 * Filename prefix used to isolate entries owned by this store.
	 */
	private string $filenamePrefix;

	/**
	 * Create a file-backed cache store.
	 *
	 * @param	string|null	$directory	Optional cache directory.
	 * @param	string		$prefix		Logical namespace for cache keys.
	 */
	public function __construct(?string $directory = null, string $prefix = 'default') {

		$base = defined('TEMP_PATH') ? TEMP_PATH : (sys_get_temp_dir() . '/pair/');
		$this->directory = rtrim($directory ?? ($base . 'cache'), '/\\') . DIRECTORY_SEPARATOR;
		$this->filenamePrefix = $this->normalizePrefix($prefix);
		$this->ensureDirectory();

	}

	/**
	 * Remove all cache files owned by this store.
	 */
	public function clear(): bool {

		$success = true;

		foreach (glob($this->directory . $this->filenamePrefix . '*.cache') ?: [] as $file) {
			if (is_file($file) and !@unlink($file)) {
				$success = false;
			}
		}

		return $success;

	}

	/**
	 * Delete one cache entry.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function delete(string $key): bool {

		$file = $this->pathForKey($key);

		return !file_exists($file) or @unlink($file);

	}

	/**
	 * Return a cached value or the provided default when missing or expired.
	 *
	 * @param	string	$key		Cache key.
	 * @param	mixed	$default	Value returned when the key is missing.
	 */
	public function get(string $key, mixed $default = null): mixed {

		$row = $this->readRow($key);

		if (!is_array($row)) {
			return $default;
		}

		return $row['value'] ?? null;

	}

	/**
	 * Return true when the cache key exists and has not expired.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function has(string $key): bool {

		return is_array($this->readRow($key));

	}

	/**
	 * Store a value, optionally with a TTL in seconds.
	 *
	 * @param	string		$key		Cache key.
	 * @param	mixed		$value		Serializable value.
	 * @param	int|null	$ttlSeconds	Optional TTL; null means no expiration.
	 */
	public function set(string $key, mixed $value, ?int $ttlSeconds = null): bool {

		if (!is_null($ttlSeconds) and $ttlSeconds <= 0) {
			return $this->delete($key);
		}

		$row = [
			'expiresAt' => is_null($ttlSeconds) ? null : time() + $ttlSeconds,
			'value' => $value,
		];

		$payload = serialize($row);

		return false !== file_put_contents($this->pathForKey($key), $payload, LOCK_EX);

	}

	/**
	 * Create the cache directory when it does not exist yet.
	 */
	private function ensureDirectory(): void {

		if (!is_dir($this->directory)) {
			mkdir($this->directory, 0755, true);
		}

	}

	/**
	 * Return true when a cache row has expired.
	 *
	 * @param	array<string, mixed>	$row	Cache row.
	 */
	private function isExpired(array $row): bool {

		return isset($row['expiresAt']) and !is_null($row['expiresAt']) and time() >= (int)$row['expiresAt'];

	}

	/**
	 * Normalize a logical namespace into a filesystem-safe filename prefix.
	 *
	 * @param	string	$prefix	Logical cache namespace.
	 */
	private function normalizePrefix(string $prefix): string {

		$prefix = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($prefix));

		if (!is_string($prefix) or !strlen($prefix)) {
			$prefix = 'default';
		}

		return $prefix . '-';

	}

	/**
	 * Return the storage path for one cache key.
	 *
	 * @param	string	$key	Cache key.
	 */
	private function pathForKey(string $key): string {

		return $this->directory . $this->filenamePrefix . hash('sha256', $key) . '.cache';

	}

	/**
	 * Read and validate one cache row.
	 *
	 * @param	string	$key	Cache key.
	 * @return	array<string, mixed>|null
	 */
	private function readRow(string $key): ?array {

		$file = $this->pathForKey($key);

		if (!file_exists($file)) {
			return null;
		}

		$payload = file_get_contents($file);

		if (!is_string($payload) or !strlen($payload)) {
			return null;
		}

		$row = @unserialize($payload, ['allowed_classes' => true]);

		if (!is_array($row)) {
			return null;
		}

		if ($this->isExpired($row)) {
			$this->delete($key);
			return null;
		}

		return $row;

	}

}
