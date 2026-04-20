<?php

namespace Pair\Api;

use Pair\Http\JsonResponse;

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
	 * Build an explicit API error response from the registered error vocabulary.
	 *
	 * @param	array<string, mixed>	$extra	Additional payload fields merged into the response body.
	 */
	public static function errorResponse(string $errorCode, array $extra = []): ApiErrorResponse {

		$error = self::resolveErrorDefinition($errorCode);

		return new ApiErrorResponse($errorCode, $error['message'], $error['httpCode'], $extra);

	}

	/**
	 * Send an error JSON response. Looks up the error code in custom errors first,
	 * then built-in errors, falling back to INTERNAL_SERVER_ERROR.
	 */
	public static function error(string $errorCode, array $extra = []): void {

		self::errorResponse($errorCode, $extra)->send();

	}

	/**
	 * Send a JSON response with data.
	 */
	public static function respond(\stdClass|array|null $data, int $httpCode = 200): void {

		self::jsonResponse($data, $httpCode)->send();

	}

	/**
	 * Build an explicit JSON response object for the given payload.
	 */
	public static function jsonResponse(\stdClass|array|null $data, int $httpCode = 200): JsonResponse {

		return new JsonResponse($data, $httpCode);

	}

	/**
	 * Send a JSON success response with an optional message.
	 */
	public static function success(?string $message = null): void {

		self::successResponse($message)->send();

	}

	/**
	 * Build an explicit JSON success response with an optional message payload.
	 */
	public static function successResponse(?string $message = null): JsonResponse {

		$data = $message ? ['message' => $message] : null;

		return self::jsonResponse($data);

	}

	/**
	 * Send a paginated JSON response with data and pagination metadata.
	 */
	public static function paginated(array $data, int $page, int $perPage, int $total): void {

		self::paginatedResponse($data, $page, $perPage, $total)->send();

	}

	/**
	 * Build an explicit paginated JSON response with the standard data/meta envelope.
	 */
	public static function paginatedResponse(array $data, int $page, int $perPage, int $total): JsonResponse {

		$lastPage = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

		return self::jsonResponse([
			'data' => $data,
			'meta' => [
				'page'		=> $page,
				'perPage'	=> $perPage,
				'total'		=> $total,
				'lastPage'	=> $lastPage,
			]
		]);

	}

	/**
	 * Resolve one error definition from custom or built-in registries.
	 *
	 * @return	array{httpCode: int, message: string}
	 */
	private static function resolveErrorDefinition(string &$errorCode): array {

		if (array_key_exists($errorCode, self::$customErrors)) {
			$error = self::$customErrors[$errorCode];

			return [
				'httpCode' => intval($error['httpCode'] ?? 500),
				'message' => (string)($error['message'] ?? 'Internal server error'),
			];
		}

		if (!array_key_exists($errorCode, self::ERRORS)) {
			$errorCode = 'INTERNAL_SERVER_ERROR';
		}

		$error = self::ERRORS[$errorCode];

		return [
			'httpCode' => intval($error['httpCode'] ?? 500),
			'message' => (string)($error['message'] ?? 'Internal server error'),
		];

	}

}
