<?php

namespace Pair\Core;

use Pair\Models\OAuth2Token;

/**
 * Manages environment variables from .env file and default values.
 */
class Env {

	/**
	 * Env file path.
	 */
	const FILE = APPLICATION_PATH . '/.env';

	/**
	 * Default values for environment variables.
	 */
	const DEFAULTS = [
		'APP_NAME' => 'Pair Application',
		'APP_VERSION' => '1.0',
		'APP_ENV' => 'production',
		'APP_DEBUG' => false,
		'DB_UTF8' => true,
		'OAUTH2_TOKEN_LIFETIME' => OAuth2Token::LIFETIME,
		'PAIR_SINGLE_SESSION' => true,
		'PAIR_AUDIT_ALL' => true,
		'PAIR_AUTH_BY_EMAIL' => true,
		'PAIR_API_RATE_LIMIT_ENABLED' => true,
		'PAIR_API_RATE_LIMIT_MAX_ATTEMPTS' => 60,
		'PAIR_API_RATE_LIMIT_DECAY_SECONDS' => 60,
		'PAIR_API_RATE_LIMIT_REDIS_PREFIX' => 'pair:rate_limit:',
		'PAIR_TRUSTED_PROXIES' => '',
		'GOOGLE_MAPS_TIMEOUT' => 15,
		'GOOGLE_MAPS_CONNECT_TIMEOUT' => 5,
		'WHATSAPP_CLOUD_API_VERSION' => 'v23.0',
		'WHATSAPP_CLOUD_API_BASE_URL' => 'https://graph.facebook.com',
		'WHATSAPP_CLOUD_TIMEOUT' => 20,
		'WHATSAPP_CLOUD_CONNECT_TIMEOUT' => 5,
		'REDIS_HOST' => '',
		'REDIS_PORT' => 6379,
		'REDIS_PASSWORD' => '',
		'REDIS_DB' => 0,
		'REDIS_TIMEOUT' => 1,
		'PAIR_CACHE_DRIVER' => 'file',
		'PAIR_CACHE_PATH' => '',
		'PAIR_CACHE_PREFIX' => 'pair',
		'PAIR_CACHE_REDIS_PREFIX' => 'pair:cache:',
		'PAIR_OBSERVABILITY_ENABLED' => false,
		'PAIR_OBSERVABILITY_DEBUG_HEADERS' => true,
		'STRIPE_SECRET_KEY' => '',
		'STRIPE_WEBHOOK_SECRET' => '',
		'STRIPE_API_VERSION' => '',
		'OPENAI_API_KEY' => '',
		'OPENAI_API_BASE_URL' => 'https://api.openai.com/v1',
		'OPENAI_RESPONSES_MODEL' => 'gpt-5.4-mini',
		'OPENAI_EMBEDDINGS_MODEL' => 'text-embedding-3-small',
		'OPENAI_REALTIME_MODEL' => 'gpt-realtime',
		'OPENAI_TIMEOUT' => 30,
		'OPENAI_CONNECT_TIMEOUT' => 5,
		'OPENAI_STORE_RESPONSES' => false,
		'UTC_DATE' => true
	];

	/**
	 * Tracks whether environment values have already been loaded for this process.
	 */
	private static bool $loaded = false;

	/**
	 * Returns the default value of the specified key.
	 *
	 * @param	string	The key to search for default value. null if default is not set.
	 */
	private static function default(string $key): mixed {

		return self::DEFAULTS[$key] ?? null;

	}

	/**
	 * Returns true if the .env file exists.
	 */
	public static function fileExists(): bool {

		return FilesystemMetadata::fileExists(self::FILE);

	}

	/**
	 * Clear cached environment metadata so the next load can inspect the filesystem again.
	 */
	public static function clearCache(): void {

		self::$loaded = false;
		FilesystemMetadata::clear(self::FILE);

	}

	/**
	 * Returns the value of the specified key from the environment variables.
	 *
	 * @param	string	The key to search for. Default value or null if not found.
	 */
	public static function get(string $key): mixed {

		return $_ENV[$key] ?? self::default($key) ?? null;

	}

	/**
	 * Load values from file to the environment variables. String values are trimmed.
	 * Boolean values are converted to PHP true or false. Integer values are converted to PHP
	 * integer. Float values are converted to PHP float.
	 *
	 * @param	bool	$force	When true, clear process-local cache before loading.
	 */
	public static function load(bool $force = false): void {

		if ($force) {
			self::clearCache();
		}

		if (self::$loaded) {
			return;
		}

		if (!self::fileExists()) {

			// Load all defaults as fallback.
			foreach (self::DEFAULTS as $key => $value) {
				$_ENV[$key] = $value;
			}

			self::$loaded = true;

			return;

		}

		$lines = file(self::FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {

			// ignore comments and empty lines
			if ('#' == substr(trim($line), 0, 1) or false === strpos($line, '=')) {
				continue;
			}

			list($key, $value) = explode('=', $line, 2);

			$key = trim($key);
			$value = trim($value);

			// strip surrounding quotes but preserve quoted values as strings
			$wasQuoted = false;
			if (strlen($value) >= 2) {
				$firstChar = $value[0];
				$lastChar = $value[strlen($value) - 1];
				if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
					$wasQuoted = true;
					$value = substr($value, 1, -1);
				}
			}

			// cast to the correct type
			if (!$wasQuoted) {
				if ('true' == strtolower($value)) {
					$value = true;
				} else if ('false' == strtolower($value)) {
					$value = false;
				} else if (is_numeric($value) and (int)$value == $value) {
					$value = (int)$value;
				} else if (is_numeric($value)) {
					$value = (float)$value;
				}
			}

			$_ENV[$key] = $value ?? null;

		}

		self::$loaded = true;

	}

}
