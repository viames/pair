<?php

namespace Pair\Api;

/**
 * File-based rate limiter. Stores attempt counts in the application temp directory.
 * No database or external cache dependency required.
 */
class RateLimiter {

	/**
	 * Maximum number of attempts allowed within the decay window.
	 */
	private int $maxAttempts;

	/**
	 * Time window in seconds after which attempts reset.
	 */
	private int $decaySeconds;

	/**
	 * Directory path for storing rate limit data files.
	 */
	private string $storagePath;

	/**
	 * Create a new rate limiter instance.
	 *
	 * @param	int	$maxAttempts	Maximum attempts within the window (default 60).
	 * @param	int	$decaySeconds	Window duration in seconds (default 60).
	 */
	public function __construct(int $maxAttempts = 60, int $decaySeconds = 60) {

		$this->maxAttempts = $maxAttempts;
		$this->decaySeconds = $decaySeconds;

		// use TEMP_PATH if defined, otherwise fall back to system temp
		$basePath = defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir() . '/';
		$this->storagePath = $basePath . 'rate_limits/';

		if (!is_dir($this->storagePath)) {
			mkdir($this->storagePath, 0755, true);
		}

	}

	/**
	 * Check if the given key has exceeded the maximum number of attempts.
	 */
	public function tooManyAttempts(string $key): bool {

		$record = $this->getRecord($key);

		// window expired, not too many
		if ($record['expiresAt'] < time()) {
			return false;
		}

		return $record['count'] >= $this->maxAttempts;

	}

	/**
	 * Record a hit for the given key. Returns the number of remaining attempts.
	 * Also sends X-RateLimit-* headers.
	 */
	public function hit(string $key): int {

		$record = $this->getRecord($key);
		$now = time();

		// reset if window has expired
		if ($record['expiresAt'] < $now) {
			$record = [
				'count'		=> 0,
				'expiresAt'	=> $now + $this->decaySeconds,
			];
		}

		$record['count']++;
		$this->saveRecord($key, $record);

		$remaining = max(0, $this->maxAttempts - $record['count']);

		// send rate limit headers
		header('X-RateLimit-Limit: ' . $this->maxAttempts);
		header('X-RateLimit-Remaining: ' . $remaining);
		header('X-RateLimit-Reset: ' . $record['expiresAt']);

		return $remaining;

	}

	/**
	 * Clear the attempts for the given key.
	 */
	public function clear(string $key): void {

		$file = $this->getFilePath($key);

		if (file_exists($file)) {
			unlink($file);
		}

	}

	/**
	 * Get the current attempt record for a key.
	 */
	private function getRecord(string $key): array {

		$file = $this->getFilePath($key);

		if (file_exists($file)) {
			$data = json_decode(file_get_contents($file), true);
			if (is_array($data) and isset($data['count']) and isset($data['expiresAt'])) {
				return $data;
			}
		}

		return [
			'count'		=> 0,
			'expiresAt'	=> time() + $this->decaySeconds,
		];

	}

	/**
	 * Save the attempt record for a key.
	 */
	private function saveRecord(string $key, array $record): void {

		$file = $this->getFilePath($key);
		file_put_contents($file, json_encode($record), LOCK_EX);

	}

	/**
	 * Get the file path for a rate limit key.
	 */
	private function getFilePath(string $key): string {

		return $this->storagePath . md5($key) . '.json';

	}

}
