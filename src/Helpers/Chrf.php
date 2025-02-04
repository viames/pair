<?php

namespace Pair\Helpers;

class Chrf {

	/**
	 * Generate a CSRF token field for form security.
	 *
	 * @return string HTML of the hidden input field with the CSRF token.
	 */
	public static function generateToken(): string {

		// start session if not already started
		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}

		// generate a unique CSRF token
		$token = bin2hex(random_bytes(32));

		// store the token in the session
		$_SESSION['csrf_token'] = $token;

		// return the hidden input field HTML
		return sprintf('<input type="hidden" name="csrf_token" value="%s">', htmlspecialchars($token, ENT_QUOTES, 'UTF-8'));

	}

	/**
	 * Validate a submitted CSRF token.
	 *
	 * @param string $token The token to validate.
	 * @return bool True if the token is valid, false otherwise.
	 */
	public static function validateToken(string $token): bool {

		// retrieve the token from the session
		$sessionToken = $_SESSION['csrf_token'] ?? '';

		// clear the session token to prevent reuse
		unset($_SESSION['csrf_token']);

		// check if the provided token matches the session token
		return hash_equals($sessionToken, $token);

	}

	/**
	 * Check if a CSRF token exists in the session.
	 *
	 * @return bool True if a token exists, false otherwise.
	 */
	public static function tokenExists(): bool {

		// check if the CSRF token is set in the session
		return isset($_SESSION['csrf_token']);

	}

}