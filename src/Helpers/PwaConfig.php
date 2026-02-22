<?php

namespace Pair\Helpers;

/**
 * Helper class for building and normalizing PWA runtime configuration.
 */
class PwaConfig {

	/**
	 * Builds the service worker URL with encoded PWA config.
	 */
	public static function buildServiceWorkerUrl(string $swUrl = '/assets/PairSW.js', array $options = []): string {

		$config = static::normalize($options);
		$encoded = static::encodeForServiceWorker($config);

		$parts = parse_url($swUrl);

		$path = isset($parts['path']) ? $parts['path'] : $swUrl;
		$queryList = [];

		if (isset($parts['query'])) {
			parse_str($parts['query'], $queryList);
		}

		if (isset($config['offlineFallback']) and strlen(trim((string)$config['offlineFallback']))) {
			$queryList['offline'] = (string)$config['offlineFallback'];
		}

		if (strlen($encoded)) {
			$queryList['pwa'] = $encoded;
		}

		$query = http_build_query($queryList);

		if (strlen($query)) {
			$path .= '?' . $query;
		}

		if (isset($parts['fragment']) and strlen($parts['fragment'])) {
			$path .= '#' . $parts['fragment'];
		}

		return $path;

	}

	/**
	 * Returns default PWA runtime options.
	 */
	public static function defaults(): array {

		return [
			'offlineFallback' => '/offline.html',
			'precache' => [],
			'cache' => [
				'pageStrategy' => 'network-first',
				'apiStrategy' => 'network-first',
				'assetStrategy' => 'stale-while-revalidate',
				'maxRuntimeEntries' => 300,
				'maxRuntimeAgeSeconds' => 604800,
				'rules' => [],
			],
			'sync' => [
				'enabled' => true,
				'maxQueueEntries' => 250,
				'maxBodyBytes' => 262144,
				'maxAgeSeconds' => 86400,
				'maxAttempts' => 5,
				'retryDelaysSeconds' => [30, 120, 600, 1800, 3600],
			],
		];

	}

	/**
	 * Encodes options for use in the service worker query string.
	 */
	public static function encodeForServiceWorker(array $options = []): string {

		$json = json_encode(static::normalize($options), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

		if (!is_string($json)) {
			return '';
		}

		$base64 = base64_encode($json);

		// URL-safe base64 without padding.
		return rtrim(strtr($base64, '+/', '-_'), '=');

	}

	/**
	 * Normalizes and merges options with defaults.
	 */
	public static function normalize(array $options = []): array {

		$ret = static::defaults();

		if (isset($options['offlineFallback']) and is_string($options['offlineFallback']) and strlen(trim($options['offlineFallback']))) {
			$ret['offlineFallback'] = trim($options['offlineFallback']);
		}

		if (isset($options['precache']) and is_array($options['precache'])) {
			$ret['precache'] = static::normalizePrecacheList($options['precache']);
		}

		if (isset($options['cache']) and is_array($options['cache'])) {
			$ret['cache'] = static::normalizeCache($options['cache'], $ret['cache']);
		}

		if (isset($options['sync']) and is_array($options['sync'])) {
			$ret['sync'] = static::normalizeSync($options['sync'], $ret['sync']);
		}

		return $ret;

	}

	/**
	 * Normalizes cache-related options.
	 */
	private static function normalizeCache(array $cache, array $defaults): array {

		$ret = $defaults;

		$allowedStrategies = ['network-first', 'cache-first', 'stale-while-revalidate'];

		foreach (['pageStrategy', 'apiStrategy', 'assetStrategy'] as $field) {
			if (isset($cache[$field]) and in_array($cache[$field], $allowedStrategies)) {
				$ret[$field] = $cache[$field];
			}
		}

		$ret['maxRuntimeEntries'] = static::normalizeInt($cache['maxRuntimeEntries'] ?? $ret['maxRuntimeEntries'], 10, 5000);
		$ret['maxRuntimeAgeSeconds'] = static::normalizeInt($cache['maxRuntimeAgeSeconds'] ?? $ret['maxRuntimeAgeSeconds'], 0, 31536000);

		if (isset($cache['rules']) and is_array($cache['rules'])) {
			$ret['rules'] = array_values($cache['rules']);
		}

		return $ret;

	}

	/**
	 * Normalizes integer values within range.
	 */
	private static function normalizeInt(mixed $value, int $min, int $max): int {

		$value = intval($value);

		if ($value < $min) {
			return $min;
		}

		if ($value > $max) {
			return $max;
		}

		return $value;

	}

	/**
	 * Normalizes precache list values.
	 */
	private static function normalizePrecacheList(array $list): array {

		$ret = [];

		foreach ($list as $item) {
			if (!is_string($item)) {
				continue;
			}

			$item = trim($item);

			if (strlen($item)) {
				$ret[] = $item;
			}
		}

		return array_values(array_unique($ret));

	}

	/**
	 * Normalizes sync-related options.
	 */
	private static function normalizeSync(array $sync, array $defaults): array {

		$ret = $defaults;

		if (array_key_exists('enabled', $sync)) {
			$ret['enabled'] = (bool)$sync['enabled'];
		}

		$ret['maxQueueEntries'] = static::normalizeInt($sync['maxQueueEntries'] ?? $ret['maxQueueEntries'], 10, 5000);
		$ret['maxBodyBytes'] = static::normalizeInt($sync['maxBodyBytes'] ?? $ret['maxBodyBytes'], 1024, 2097152);
		$ret['maxAgeSeconds'] = static::normalizeInt($sync['maxAgeSeconds'] ?? $ret['maxAgeSeconds'], 60, 604800);
		$ret['maxAttempts'] = static::normalizeInt($sync['maxAttempts'] ?? $ret['maxAttempts'], 1, 20);

		if (isset($sync['retryDelaysSeconds']) and is_array($sync['retryDelaysSeconds']) and count($sync['retryDelaysSeconds'])) {
			$delays = [];

			foreach ($sync['retryDelaysSeconds'] as $delay) {
				$delay = intval($delay);
				if ($delay > 0) {
					$delays[] = $delay;
				}
			}

			if (count($delays)) {
				$ret['retryDelaysSeconds'] = array_values($delays);
			}
		}

		return $ret;

	}

}
