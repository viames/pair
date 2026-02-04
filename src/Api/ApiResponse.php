<?php

namespace Pair\Api;

use Pair\Helpers\Utilities;

/**
 * Standard API response builder with extensible error registry.
 * Provides static methods for sending JSON responses and errors.
 */
class ApiResponse {

	/**
	 * Standard HTTP error codes dictionary.
	 */
	public const ERRORS = [

		'AUTH_MISSING_FIELDS' => [
			'httpCode' => 400,
			'message' => 'Missing username or password'
		],
		'AUTH_PASSWORD_TOO_SHORT' => [
			'httpCode' => 400,
			'message' => 'Password is too short'
		],
		'BAD_REQUEST' => [
			'httpCode' => 400,
			'message' => 'This request is invalid'
		],
		'INVALID_FIELD' => [
			'httpCode' => 400,
			'message' => 'Invalid field'
		],
		'INVALID_VALUE' => [
			'httpCode' => 400,
			'message' => 'Invalid value'
		],
		'INVALID_TYPE' => [
			'httpCode' => 400,
			'message' => 'Invalid type'
		],
		'INVALID_FIELDS' => [
			'httpCode' => 400,
			'message' => 'Invalid fields'
		],
		'INVALID_OBJECT' => [
			'httpCode' => 400,
			'message' => 'Invalid object'
		],
		'INVALID_OBJECT_ID' => [
			'httpCode' => 400,
			'message' => 'Invalid object ID'
		],
		'INVALID_OBJECT_DATA' => [
			'httpCode' => 400,
			'message' => 'Invalid object data'
		],
		'INVALID_OBJECT_DATE' => [
			'httpCode' => 400,
			'message' => 'Invalid object date'
		],
		'INVALID_OBJECT_TIME' => [
			'httpCode' => 400,
			'message' => 'Invalid object time'
		],
		'INVALID_OBJECT_DATETIME' => [
			'httpCode' => 400,
			'message' => 'Invalid object datetime'
		],
		'AUTH_INVALID_CREDENTIALS' => [
			'httpCode' => 401,
			'message' => 'Invalid credentials'
		],
		'UNAUTHORIZED' => [
			'httpCode' => 401,
			'message' => 'Unauthorized'
		],
		'AUTH_TOKEN_MISSING' => [
			'httpCode' => 401,
			'message' => 'Missing authentication token or session ID'
		],
		'AUTH_TOKEN_EXPIRED' => [
			'httpCode' => 403,
			'message' => 'Token expired'
		],
		'FORBIDDEN' => [
			'httpCode' => 403,
			'message' => 'Forbidden'
		],
		'NOT_FOUND' => [
			'httpCode' => 404,
			'message' => 'Not found'
		],
		'METHOD_NOT_ALLOWED' => [
			'httpCode' => 405,
			'message' => 'Method not allowed'
		],
		'NOT_ACCEPTABLE' => [
			'httpCode' => 406,
			'message' => 'Not acceptable'
		],
		'CONFLICT' => [
			'httpCode' => 409,
			'message' => 'Conflict'
		],
		'UNSUPPORTED_MEDIA_TYPE' => [
			'httpCode' => 415,
			'message' => 'Unsupported media type'
		],
		'TOO_MANY_ATTEMPTS' => [
			'httpCode' => 429,
			'message' => 'Too many attempts'
		],
		'TOO_MANY_REQUESTS' => [
			'httpCode' => 429,
			'message' => 'Too many requests'
		],
		'INTERNAL_SERVER_ERROR' => [
			'httpCode' => 500,
			'message' => 'Internal server error'
		],
		'NOT_IMPLEMENTED' => [
			'httpCode' => 501,
			'message' => 'Not implemented'
		],
		'BAD_GATEWAY' => [
			'httpCode' => 502,
			'message' => 'Bad gateway'
		],
		'GATEWAY_TIMEOUT' => [
			'httpCode' => 504,
			'message' => 'Gateway timeout'
		],

	];

	/**
	 * Application-specific error codes registered at runtime.
	 */
	private static array $customErrors = [];

	/**
	 * Register application-specific error codes. Custom errors take precedence over built-in ones.
	 */
	public static function registerErrors(array $errors): void {

		self::$customErrors = array_merge(self::$customErrors, $errors);

	}

	/**
	 * Send an error JSON response. Looks up the error code in custom errors first,
	 * then built-in errors, falling back to INTERNAL_SERVER_ERROR.
	 */
	public static function error(string $errorCode, array $extra = []): void {

		// custom errors take precedence
		if (array_key_exists($errorCode, self::$customErrors)) {

			$error = self::$customErrors[$errorCode];

		// fall back to built-in errors
		} else {

			// use INTERNAL_SERVER_ERROR if the code is not recognized
			if (!array_key_exists($errorCode, self::ERRORS)) {
				$errorCode = 'INTERNAL_SERVER_ERROR';
			}

			$error = self::ERRORS[$errorCode];

		}

		// sanitize extra keys to strings only
		foreach (array_keys($extra) as $key) {
			if (!is_string($key)) {
				unset($extra[$key]);
			}
		}

		Utilities::jsonError($errorCode, $error['message'], $error['httpCode'], $extra);

	}

	/**
	 * Send a JSON response with data.
	 */
	public static function respond(\stdClass|array|null $data, int $httpCode = 200): void {

		Utilities::jsonResponse($data, $httpCode);

	}

	/**
	 * Send a JSON success response with an optional message.
	 */
	public static function success(?string $message = null): void {

		$data = $message ? ['message' => $message] : null;
		Utilities::jsonResponse($data);

	}

	/**
	 * Send a paginated JSON response with data and pagination metadata.
	 */
	public static function paginated(array $data, int $page, int $perPage, int $total): void {

		$lastPage = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

		Utilities::jsonResponse([
			'data' => $data,
			'meta' => [
				'page'		=> $page,
				'perPage'	=> $perPage,
				'total'		=> $total,
				'lastPage'	=> $lastPage,
			]
		]);

	}

}
