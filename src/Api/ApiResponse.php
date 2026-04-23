<?php

namespace Pair\Api;

use Pair\Helpers\Translator;
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
			'messageKey' => 'AUTH_MISSING_FIELDS',
			'message' => 'Missing username or password'
		],
		'AUTH_PASSWORD_TOO_SHORT' => [
			'httpCode' => 400,
			'messageKey' => 'AUTH_PASSWORD_TOO_SHORT',
			'message' => 'Password is too short'
		],
		'BAD_REQUEST' => [
			'httpCode' => 400,
			'messageKey' => 'BAD_REQUEST',
			'message' => 'This request is invalid'
		],
		'INVALID_FIELD' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_FIELD',
			'message' => 'Invalid field'
		],
		'INVALID_VALUE' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_VALUE',
			'message' => 'Invalid value'
		],
		'INVALID_TYPE' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_TYPE',
			'message' => 'Invalid type'
		],
		'INVALID_FIELDS' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_FIELDS',
			'message' => 'Invalid fields'
		],
		'INVALID_OBJECT' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_OBJECT',
			'message' => 'Invalid object'
		],
		'INVALID_OBJECT_ID' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_OBJECT_ID',
			'message' => 'Invalid object ID'
		],
		'INVALID_OBJECT_DATA' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_OBJECT_DATA',
			'message' => 'Invalid object data'
		],
		'INVALID_OBJECT_DATE' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_OBJECT_DATE',
			'message' => 'Invalid object date'
		],
		'INVALID_OBJECT_TIME' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_OBJECT_TIME',
			'message' => 'Invalid object time'
		],
		'INVALID_OBJECT_DATETIME' => [
			'httpCode' => 400,
			'messageKey' => 'INVALID_OBJECT_DATETIME',
			'message' => 'Invalid object datetime'
		],
		'AUTH_INVALID_CREDENTIALS' => [
			'httpCode' => 401,
			'messageKey' => 'AUTH_INVALID_CREDENTIALS',
			'message' => 'Invalid credentials'
		],
		'UNAUTHORIZED' => [
			'httpCode' => 401,
			'messageKey' => 'UNAUTHORIZED',
			'message' => 'Unauthorized'
		],
		'AUTH_TOKEN_MISSING' => [
			'httpCode' => 401,
			'messageKey' => 'AUTH_TOKEN_MISSING',
			'message' => 'Missing authentication token or session ID'
		],
		'AUTH_TOKEN_EXPIRED' => [
			'httpCode' => 403,
			'messageKey' => 'AUTH_TOKEN_EXPIRED',
			'message' => 'Token expired'
		],
		'FORBIDDEN' => [
			'httpCode' => 403,
			'messageKey' => 'FORBIDDEN',
			'message' => 'Forbidden'
		],
		'NOT_FOUND' => [
			'httpCode' => 404,
			'messageKey' => 'NOT_FOUND',
			'message' => 'Not found'
		],
		'METHOD_NOT_ALLOWED' => [
			'httpCode' => 405,
			'messageKey' => 'METHOD_NOT_ALLOWED',
			'message' => 'Method not allowed'
		],
		'NOT_ACCEPTABLE' => [
			'httpCode' => 406,
			'messageKey' => 'NOT_ACCEPTABLE',
			'message' => 'Not acceptable'
		],
		'CONFLICT' => [
			'httpCode' => 409,
			'messageKey' => 'CONFLICT',
			'message' => 'Conflict'
		],
		'UNSUPPORTED_MEDIA_TYPE' => [
			'httpCode' => 415,
			'messageKey' => 'UNSUPPORTED_MEDIA_TYPE',
			'message' => 'Unsupported media type'
		],
		'TOO_MANY_ATTEMPTS' => [
			'httpCode' => 429,
			'messageKey' => 'TOO_MANY_ATTEMPTS',
			'message' => 'Too many attempts'
		],
		'TOO_MANY_REQUESTS' => [
			'httpCode' => 429,
			'messageKey' => 'TOO_MANY_REQUESTS',
			'message' => 'Too many requests'
		],
		'INTERNAL_SERVER_ERROR' => [
			'httpCode' => 500,
			'messageKey' => 'INTERNAL_SERVER_ERROR',
			'message' => 'Internal server error'
		],
		'NOT_IMPLEMENTED' => [
			'httpCode' => 501,
			'messageKey' => 'NOT_IMPLEMENTED',
			'message' => 'Not implemented'
		],
		'BAD_GATEWAY' => [
			'httpCode' => 502,
			'messageKey' => 'BAD_GATEWAY',
			'message' => 'Bad gateway'
		],
		'GATEWAY_TIMEOUT' => [
			'httpCode' => 504,
			'messageKey' => 'GATEWAY_TIMEOUT',
			'message' => 'Gateway timeout'
		],

	];

	/**
	 * Application-specific error codes registered at runtime.
	 */
	private static array $customErrors = [];

	/**
	 * Built-in English fallback messages loaded without depending on the runtime Translator.
	 */
	private static ?array $fallbackMessages = null;

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
		exit();

	}

	/**
	 * Send a JSON response with data.
	 */
	public static function respond(\stdClass|array|null $data, int $httpCode = 200): void {

		self::jsonResponse($data, $httpCode)->send();
		exit();

	}

	/**
	 * Build an explicit JSON response object for the given payload.
	 */
	public static function jsonResponse(mixed $data, int $httpCode = 200): JsonResponse {

		return new JsonResponse($data, $httpCode);

	}

	/**
	 * Return a localized API payload message with a safe fallback for CLI and early bootstrap paths.
	 */
	public static function localizedMessage(string $key, string|array|null $vars = null, ?string $default = null): string {

		$fallback = $default ?? self::fallbackMessage($key) ?? $key;

		if (method_exists(Translator::class, 'safeDo')) {
			return Translator::safeDo($key, $vars, $fallback);
		}

		return self::formatFallbackMessage($fallback, $vars);

	}

	/**
	 * Send a JSON success response with an optional message.
	 */
	public static function success(?string $message = null): void {

		self::successResponse($message)->send();
		exit();

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
		exit();

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
			$message = (string)($error['message'] ?? self::localizedMessage('INTERNAL_SERVER_ERROR'));

			if (isset($error['messageKey'])) {
				$message = self::localizedMessage((string)$error['messageKey'], null, $message);
			}

			return [
				'httpCode' => intval($error['httpCode'] ?? 500),
				'message' => $message,
			];
		}

		if (!array_key_exists($errorCode, self::ERRORS)) {
			$errorCode = 'INTERNAL_SERVER_ERROR';
		}

		$error = self::ERRORS[$errorCode];
		$message = (string)($error['message'] ?? self::localizedMessage('INTERNAL_SERVER_ERROR'));

		if (isset($error['messageKey'])) {
			$message = self::localizedMessage((string)$error['messageKey'], null, $message);
		}

		return [
			'httpCode' => intval($error['httpCode'] ?? 500),
			'message' => $message,
		];

	}

	/**
	 * Apply placeholder variables when a lightweight Translator test double is in use.
	 */
	private static function formatFallbackMessage(string $message, string|array|null $vars = null): string {

		if (is_null($vars)) {
			return $message;
		}

		if (!is_array($vars)) {
			$vars = [(string)$vars];
		}

		try {
			return vsprintf($message, $vars);
		} catch (\ValueError) {
			return $message;
		}

	}

	/**
	 * Read a built-in fallback message from Pair's English translation file.
	 */
	private static function fallbackMessage(string $key): ?string {

		if (is_null(self::$fallbackMessages)) {
			$filePath = dirname(__DIR__, 2) . '/translations/en-GB.ini';
			$strings = (file_exists($filePath) and is_readable($filePath))
				? parse_ini_file($filePath)
				: false;

			self::$fallbackMessages = is_array($strings) ? $strings : [];
		}

		return self::$fallbackMessages[$key] ?? null;

	}

}
