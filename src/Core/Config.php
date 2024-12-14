<?php

namespace Pair\Core;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Models\Oauth2Token;

class Config {

	/**
	 * Configuration file path.
	 */
	const FILE = APPLICATION_PATH . '/.env';

	/**
	 * Default configuration values.
	 */
	const DEFAULTS = [
		'BASE_URI' => '',
		'DB_UTF8' => TRUE,
		'OAUTH2_TOKEN_LIFETIME' => Oauth2Token::LIFETIME,
		'PAIR_SINGLE_SESSION' => TRUE,
		'PAIR_ENVIRONMENT' => 'production',
		'PAIR_DEBUG' => FALSE,
		'PAIR_AUDIT_PASSWORD_CHANGED' => FALSE,
		'PAIR_AUDIT_LOGIN_FAILED' => FALSE,
		'PAIR_AUDIT_LOGIN_SUCCESSFUL' => FALSE,
		'PAIR_AUDIT_LOGOUT' => FALSE,
		'PAIR_AUDIT_SESSION_EXPIRED' => FALSE,
		'PAIR_AUDIT_REMEMBER_ME_LOGIN' => FALSE,
		'PAIR_AUDIT_USER_CREATED' => FALSE,
		'PAIR_AUDIT_USER_DELETED' => FALSE,
		'PAIR_AUDIT_USER_CHANGED' => FALSE,
		'PAIR_AUDIT_PERMISSIONS_CHANGED' => FALSE,
		'PAIR_AUTH_BY_EMAIL' => TRUE,
		'PRODUCT_NAME' => 'Pair Application',
		'PRODUCT_VERSION' => '1.0',
		'UTC_DATE' => TRUE
	];

	/**
	 * Returns the default value of the specified key.
	 *
	 * @param	string	$key	The key to search for.
	 */
	private static function default(string $key): mixed {

		return self::DEFAULTS[$key] ?? NULL;

	}

	/**
	 * Returns TRUE if the configuration file exists.
	 */
	public static function envFileExists(): bool {

		return file_exists(self::FILE);

	}

	/**
	 * Returns the value of the specified key from the environment variables.
	 *
	 * @param	string	The key to search for.
	 */
	public static function get(string $key): mixed {

		return $_ENV[$key] ?? self::default($key);

	}

	/**
	 * Load the configuration file into the environment variables.
	 */
	public static function load(): void {

		if (!self::envFileExists()) {
			throw new PairException('Error loading .env configuration file', ErrorCodes::LOADING_ENV);
		}

		$lines = file(self::FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {

			if (strpos($line, '=') === FALSE) {
				continue;
			}

			list($key, $value) = explode('=', $line, 2);

			$key = trim($key);
			$value = trim($value);

			$_ENV[$key] = $value ?: NULL;

		}

	}

}