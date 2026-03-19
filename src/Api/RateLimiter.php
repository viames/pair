<?php

namespace Pair\Api;

use Pair\Core\Env;

/**
 * Rate limiter with Redis primary storage and file-based fallback. The file backend
 * preserves compatibility when Redis is unavailable, while Redis enables shared limits
 * across multiple PHP workers or application nodes.
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
	 * Optional Redis client for distributed rate limiting.
	 */
	private ?\Redis $redis = null;

	/**
	 * True after the first Redis connection attempt, to avoid retrying on every request.
	 */
	private bool $redisResolved = false;

	/**
	 * Prefix applied to Redis keys managed by the rate limiter.
	 */
	private string $redisPrefix;

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
		$this->redisPrefix = trim((string)(Env::get('PAIR_API_RATE_LIMIT_REDIS_PREFIX') ?? 'pair:rate_limit:'));

		if (!is_dir($this->storagePath)) {
			mkdir($this->storagePath, 0755, true);
		}

	}

	/**
	 * Check if the given key has exceeded the maximum number of attempts.
	 */
	public function tooManyAttempts(string $key): bool {

		return !$this->peek($key)->allowed;

	}

	/**
	 * Record a hit for the given key. Returns the number of remaining attempts.
	 */
	public function hit(string $key): int {

		$result = $this->recordHit($key);
		$result->applyHeaders();

		return $result->remaining;

	}

	/**
	 * Clear the attempts for the given key.
	 */
	public function clear(string $key): void {

		$redis = $this->redis();

		if ($redis) {
			try {
				$redis->del($this->redisKey($key));
			} catch (\Throwable $e) {
				// ignore Redis failures and keep the fallback cleanup below
			}
		}

		$file = $this->getFilePath($key);

		if (file_exists($file)) {
			unlink($file);
		}

	}

	/**
	 * Atomically decide whether the current request can proceed and record the hit when allowed.
	 */
	public function attempt(string $key): RateLimitResult {

		$result = $this->attemptWithRedis($key);

		if ($result) {
			return $result;
		}

		return $this->attemptWithFile($key);

	}

	/**
	 * Return the current state without consuming a new hit.
	 */
	private function peek(string $key): RateLimitResult {

		$result = $this->peekWithRedis($key);

		if ($result) {
			return $result;
		}

		return $this->peekWithFile($key);

	}

	/**
	 * Record a hit without performing the limit guard first.
	 */
	private function recordHit(string $key): RateLimitResult {

		$result = $this->recordHitWithRedis($key);

		if ($result) {
			return $result;
		}

		return $this->recordHitWithFile($key);

	}

	/**
	 * Atomically attempt to consume a hit using the file fallback backend.
	 */
	private function attemptWithFile(string $key): RateLimitResult {

		$now = time();
		$file = $this->getFilePath($key);
		$handle = $this->lockFile($file);
		$hits = $this->readHitsFromHandle($handle, $now);

		// keep file state compact by persisting only active hits.
		if (count($hits) >= $this->maxAttempts) {
			$this->writeHitsToHandle($handle, $hits);
			$this->unlockFile($handle);
			$this->maybeCleanupStorage();

			return $this->buildResult($hits, false, 'file', $now);
		}

		$hits[] = $now;
		sort($hits);
		$this->writeHitsToHandle($handle, $hits);
		$this->unlockFile($handle);
		$this->maybeCleanupStorage();

		return $this->buildResult($hits, true, 'file', $now);

	}

	/**
	 * Return the current file-backed state without consuming a new hit.
	 */
	private function peekWithFile(string $key): RateLimitResult {

		$now = time();
		$file = $this->getFilePath($key);
		$handle = $this->lockFile($file);
		$hits = $this->readHitsFromHandle($handle, $now);

		// rewrite the filtered list so expired entries do not accumulate forever.
		$this->writeHitsToHandle($handle, $hits);
		$this->unlockFile($handle);

		return $this->buildResult($hits, count($hits) < $this->maxAttempts, 'file', $now);

	}

	/**
	 * Record a hit on the file backend, preserving the current active sliding window.
	 */
	private function recordHitWithFile(string $key): RateLimitResult {

		$now = time();
		$file = $this->getFilePath($key);
		$handle = $this->lockFile($file);
		$hits = $this->readHitsFromHandle($handle, $now);
		$hits[] = $now;
		sort($hits);
		$this->writeHitsToHandle($handle, $hits);
		$this->unlockFile($handle);
		$this->maybeCleanupStorage();

		return $this->buildResult($hits, count($hits) <= $this->maxAttempts, 'file', $now);

	}

	/**
	 * Atomically attempt to consume a hit using Redis. Returns null when Redis is not
	 * configured or available, so the caller can fall back to the file backend.
	 */
	private function attemptWithRedis(string $key): ?RateLimitResult {

		$redis = $this->redis();

		if (!$redis) {
			return null;
		}

		$now = time();
		$member = $this->newRedisMember($now);
		$result = $this->runRedisScript($this->attemptRedisScript(), [$this->redisKey($key), $now, $this->decaySeconds, $this->maxAttempts, $member]);

		return is_array($result) ? $this->hydrateRedisResult($result, $now) : null;

	}

	/**
	 * Return the current Redis-backed state without consuming a hit.
	 */
	private function peekWithRedis(string $key): ?RateLimitResult {

		$redis = $this->redis();

		if (!$redis) {
			return null;
		}

		$now = time();
		$result = $this->runRedisScript($this->peekRedisScript(), [$this->redisKey($key), $now, $this->decaySeconds, $this->maxAttempts]);

		return is_array($result) ? $this->hydrateRedisResult($result, $now) : null;

	}

	/**
	 * Record a hit on Redis without checking whether the limit has already been reached.
	 */
	private function recordHitWithRedis(string $key): ?RateLimitResult {

		$redis = $this->redis();

		if (!$redis) {
			return null;
		}

		$now = time();
		$member = $this->newRedisMember($now);
		$result = $this->runRedisScript($this->recordRedisScript(), [$this->redisKey($key), $now, $this->decaySeconds, $this->maxAttempts, $member]);

		return is_array($result) ? $this->hydrateRedisResult($result, $now) : null;

	}

	/**
	 * Create a normalized rate-limit result from the active hit list.
	 */
	private function buildResult(array $hits, bool $allowed, string $driver, int $now): RateLimitResult {

		$activeHits = count($hits);
		$remaining = max(0, $this->maxAttempts - $activeHits);
		$resetAt = $activeHits ? (intval($hits[0]) + $this->decaySeconds) : ($now + $this->decaySeconds);

		if (!$allowed) {
			$remaining = 0;
		}

		return new RateLimitResult($allowed, $this->maxAttempts, $remaining, $resetAt, max(0, $resetAt - $now), $driver);

	}

	/**
	 * Return the Redis connection when available.
	 */
	private function redis(): ?\Redis {

		if ($this->redisResolved) {
			return $this->redis;
		}

		$this->redisResolved = true;

		if (!class_exists(\Redis::class)) {
			return null;
		}

		$host = trim((string)(Env::get('REDIS_HOST') ?? ''));

		if (!strlen($host)) {
			return null;
		}

		$client = new \Redis();
		$timeout = Env::get('REDIS_TIMEOUT');
		$timeout = is_numeric($timeout) && floatval($timeout) > 0 ? floatval($timeout) : 1.0;
		$port = intval(Env::get('REDIS_PORT') ?? 6379);

		try {

			if (str_starts_with($host, '/') or str_starts_with($host, 'unix://')) {
				$connected = $client->connect($host, 0, $timeout);
			} else {
				$connected = $client->connect($host, $port > 0 ? $port : 6379, $timeout);
			}

			if (!$connected) {
				return null;
			}

			$password = Env::get('REDIS_PASSWORD');

			if (is_string($password) and strlen(trim($password))) {
				$client->auth(trim($password));
			}

			$db = intval(Env::get('REDIS_DB') ?? 0);

			if ($db > 0) {
				$client->select($db);
			}

			$this->redis = $client;

		} catch (\Throwable $e) {
			$this->redis = null;
		}

		return $this->redis;

	}

	/**
	 * Run a Redis Lua script and return null on connection or script errors, so the
	 * caller can transparently fall back to the file backend.
	 */
	private function runRedisScript(string $script, array $args): ?array {

		$redis = $this->redis();

		if (!$redis) {
			return null;
		}

		try {
			$result = $redis->eval($script, $args, 1);
		} catch (\Throwable $e) {
			$this->redis = null;
			return null;
		}

		return is_array($result) ? $result : null;

	}

	/**
	 * Convert the raw Redis script response into a RateLimitResult.
	 */
	private function hydrateRedisResult(array $result, int $now): RateLimitResult {

		$allowed = intval($result[0] ?? 0) === 1;
		$limit = intval($result[1] ?? $this->maxAttempts);
		$remaining = max(0, intval($result[2] ?? 0));
		$resetAt = max($now, intval($result[3] ?? ($now + $this->decaySeconds)));

		return new RateLimitResult($allowed, $limit, $remaining, $resetAt, max(0, $resetAt - $now), 'redis');

	}

	/**
	 * Generate a unique member for Redis sorted-set inserts.
	 */
	private function newRedisMember(int $now): string {

		return $now . ':' . uniqid('', true);

	}

	/**
	 * Acquire an exclusive lock on the file that stores the current rate-limit window.
	 */
	private function lockFile(string $file) {

		$handle = fopen($file, 'c+');

		if (false === $handle) {
			throw new \RuntimeException('Unable to open rate-limit storage file.');
		}

		if (!flock($handle, LOCK_EX)) {
			fclose($handle);
			throw new \RuntimeException('Unable to lock rate-limit storage file.');
		}

		return $handle;

	}

	/**
	 * Release a previously locked file handle.
	 */
	private function unlockFile($handle): void {

		flock($handle, LOCK_UN);
		fclose($handle);

	}

	/**
	 * Read the list of active hit timestamps from the locked file handle.
	 */
	private function readHitsFromHandle($handle, int $now): array {

		rewind($handle);
		$json = stream_get_contents($handle);
		$data = is_string($json) && strlen($json) ? json_decode($json, true) : null;
		$hits = $this->normalizeHits(is_array($data) ? $data : [], $now);
		sort($hits);

		return $hits;

	}

	/**
	 * Convert the persisted record into the active hit list. Legacy `{count, expiresAt}`
	 * records are approximated so existing windows stay effective after the upgrade.
	 */
	private function normalizeHits(array $record, int $now): array {

		$threshold = $now - $this->decaySeconds + 1;

		if (isset($record['hits']) and is_array($record['hits'])) {
			$hits = [];

			foreach ($record['hits'] as $hit) {
				$hit = intval($hit);

				if ($hit >= $threshold) {
					$hits[] = $hit;
				}
			}

			return $hits;
		}

		// preserve old fixed-window files long enough to avoid silently resetting limits.
		if (isset($record['count']) and isset($record['expiresAt'])) {
			$expiresAt = intval($record['expiresAt']);
			$count = min($this->maxAttempts + 1, max(0, intval($record['count'])));

			if ($expiresAt < $now or !$count) {
				return [];
			}

			$firstHit = max($threshold, $expiresAt - $this->decaySeconds);

			return array_fill(0, $count, $firstHit);
		}

		return [];

	}

	/**
	 * Persist the active hit list back into the locked file handle.
	 */
	private function writeHitsToHandle($handle, array $hits): void {

		rewind($handle);
		ftruncate($handle, 0);

		$json = json_encode([
			'hits' => array_values($hits),
			'updatedAt' => time(),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (is_string($json) and strlen($json)) {
			fwrite($handle, $json);
		}

		fflush($handle);

	}

	/**
	 * Opportunistically remove expired file records so fallback storage does not grow forever.
	 */
	private function maybeCleanupStorage(): void {

		// run cleanup only occasionally to keep the hot path cheap.
		if (random_int(1, 100) !== 1) {
			return;
		}

		foreach (glob($this->storagePath . '*.json') ?: [] as $file) {

			$json = file_get_contents($file);
			$data = is_string($json) && strlen($json) ? json_decode($json, true) : null;
			$hits = $this->normalizeHits(is_array($data) ? $data : [], time());

			if (!count($hits)) {
				@unlink($file);
			}

		}

	}

	/**
	 * Return the Redis key for a logical limiter key.
	 */
	private function redisKey(string $key): string {

		return $this->redisPrefix . hash('sha256', $key);

	}

	/**
	 * Lua script that atomically checks the limit and consumes the hit only when allowed.
	 */
	private function attemptRedisScript(): string {

		return <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local decay = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
local member = ARGV[4]
local minScore = now - decay + 1

redis.call('ZREMRANGEBYSCORE', key, '-inf', minScore - 1)

local count = redis.call('ZCARD', key)
if count >= limit then
	local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
	local resetAt = now + decay
	if oldest[2] ~= nil then
		resetAt = tonumber(oldest[2]) + decay
	end
	redis.call('EXPIRE', key, decay + 1)
	return {0, limit, 0, resetAt}
end

redis.call('ZADD', key, now, member)
count = redis.call('ZCARD', key)
local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
local resetAt = now + decay
if oldest[2] ~= nil then
	resetAt = tonumber(oldest[2]) + decay
end
redis.call('EXPIRE', key, decay + 1)

return {1, limit, math.max(0, limit - count), resetAt}
LUA;

	}

	/**
	 * Lua script that returns the current state without consuming a hit.
	 */
	private function peekRedisScript(): string {

		return <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local decay = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
local minScore = now - decay + 1

redis.call('ZREMRANGEBYSCORE', key, '-inf', minScore - 1)

local count = redis.call('ZCARD', key)
local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
local resetAt = now + decay
if oldest[2] ~= nil then
	resetAt = tonumber(oldest[2]) + decay
end

if count == 0 then
	resetAt = now + decay
end

redis.call('EXPIRE', key, decay + 1)

if count >= limit then
	return {0, limit, 0, resetAt}
end

return {1, limit, math.max(0, limit - count), resetAt}
LUA;

	}

	/**
	 * Lua script that always records a hit and then returns the updated state.
	 */
	private function recordRedisScript(): string {

		return <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local decay = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
local member = ARGV[4]
local minScore = now - decay + 1

redis.call('ZREMRANGEBYSCORE', key, '-inf', minScore - 1)
redis.call('ZADD', key, now, member)

local count = redis.call('ZCARD', key)
local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
local resetAt = now + decay
if oldest[2] ~= nil then
	resetAt = tonumber(oldest[2]) + decay
end

redis.call('EXPIRE', key, decay + 1)

if count > limit then
	return {0, limit, 0, resetAt}
end

return {1, limit, math.max(0, limit - count), resetAt}
LUA;

	}

	/**
	 * Get the file path for a rate-limit key.
	 */
	private function getFilePath(string $key): string {

		return $this->storagePath . md5($key) . '.json';

	}

}
