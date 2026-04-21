<?php

namespace Pair\Api;

use Pair\Core\Env;

/**
 * HTTP request wrapper for API controllers. Provides methods for accessing
 * request method, headers, JSON body, query parameters, and validation.
 */
class Request {

	/**
	 * Raw body content from php://input, lazy-loaded.
	 */
	private ?string $rawBody = null;

	/**
	 * Decoded JSON body, lazy-loaded.
	 */
	private ?array $jsonData = null;

	/**
	 * Whether the JSON body has been parsed.
	 */
	private bool $jsonParsed = false;

	/**
	 * Return the HTTP request method (GET, POST, PUT, DELETE, etc.).
	 */
	public function method(): string {

		return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

	}

	/**
	 * Get a request header value by name.
	 *
	 * @param	string	$name	Header name (case-insensitive).
	 */
	public function header(string $name): ?string {

		// convert header name to the $_SERVER format
		$key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

		// special cases for Content-Type and Content-Length
		if ($name === 'Content-Type' or strtolower($name) === 'content-type') {
			return $_SERVER['CONTENT_TYPE'] ?? null;
		}

		if ($name === 'Content-Length' or strtolower($name) === 'content-length') {
			return $_SERVER['CONTENT_LENGTH'] ?? null;
		}

		return $_SERVER[$key] ?? null;

	}

	/**
	 * Extract the Bearer token from the Authorization header.
	 */
	public function bearerToken(): ?string {

		$auth = $this->header('Authorization');

		if ($auth and str_starts_with($auth, 'Bearer ')) {
			return substr($auth, 7);
		}

		return null;

	}

	/**
	 * Returns the idempotency key from request headers, if present.
	 * Supports both Idempotency-Key and X-Idempotency-Key.
	 */
	public function idempotencyKey(): ?string {

		$key = $this->header('Idempotency-Key');

		if (is_null($key) or !strlen(trim($key))) {
			$key = $this->header('X-Idempotency-Key');
		}

		$key = trim((string)$key);

		return strlen($key) ? $key : null;

	}

	/**
	 * Get the raw request body from php://input.
	 */
	public function rawBody(): string {

		if (is_null($this->rawBody)) {
			$this->rawBody = file_get_contents('php://input') ?: '';
		}

		return $this->rawBody;

	}

	/**
	 * Get a value from the parsed JSON body. If no key is given, return the entire body.
	 *
	 * @param	string|null	$key		Dot-free key name, or null for entire body.
	 * @param	mixed		$default	Default value if key is not found.
	 */
	public function json(?string $key = null, mixed $default = null): mixed {

		if (!$this->jsonParsed) {
			$this->parseJson();
		}

		if (is_null($key)) {
			return $this->jsonData ?? $default;
		}

		return $this->jsonData[$key] ?? $default;

	}

	/**
	 * Get all request data merged from query parameters and JSON body.
	 * JSON body values take precedence over query parameters.
	 */
	public function all(): array {

		$query = $_GET ?? [];
		$json = $this->json() ?? [];

		return array_merge($query, $json);

	}

	/**
	 * Get a query parameter value. If no key is given, return all query parameters.
	 *
	 * @param	string|null	$key		Parameter name, or null for all.
	 * @param	mixed		$default	Default value if key is not found.
	 */
	public function query(?string $key = null, mixed $default = null): mixed {

		if (is_null($key)) {
			return $_GET ?? [];
		}

		return $_GET[$key] ?? $default;

	}

	/**
	 * Validate request data against a set of rules and return either the validated
	 * data array or an explicit INVALID_FIELDS response.
	 *
	 * Supported rules (pipe-separated): required, string, int, numeric, email, min:N, max:N, bool.
	 *
	 * @param	array	$rules	Associative array of field => 'rule1|rule2|...'
	 * @return	array|ApiErrorResponse
	 */
	public function validateOrResponse(array $rules): array|ApiErrorResponse {

		$result = $this->collectValidationResult($rules);

		if (!empty($result['errors'])) {
			return ApiResponse::errorResponse('INVALID_FIELDS', ['errors' => $result['errors']]);
		}

		return $result['validated'];

	}

	/**
	 * Validate request data and map it into an explicit request object.
	 *
	 * @param	string	$requestDataClass	Class implementing RequestData.
	 * @param	array	$rules				Associative array of field => 'rule1|rule2|...'.
	 */
	public function validateObjectOrResponse(string $requestDataClass, array $rules = []): RequestData|ApiErrorResponse {

		$result = $this->validateOrResponse($rules);

		if ($result instanceof ApiErrorResponse) {
			return $result;
		}

		return $this->mapToRequestData($requestDataClass, $result);

	}

	/**
	 * Validate request data against a set of rules. Returns the validated data array
	 * or sends a 400 error response on failure.
	 *
	 * Supported rules (pipe-separated): required, string, int, numeric, email, min:N, max:N, bool.
	 *
	 * @param	array	$rules	Associative array of field => 'rule1|rule2|...'
	 */
	public function validate(array $rules): array {

		$result = $this->validateOrResponse($rules);

		// Preserve the legacy terminate-on-error contract for existing callers.
		if ($result instanceof ApiErrorResponse) {
			$result->send();
			exit();
		}

		return $result;

	}

	/**
	 * Require that specific fields are present in the request data.
	 * Shorthand for validate() with all fields marked as required.
	 *
	 * @param	array	$fields	List of required field names.
	 */
	public function requireFields(array $fields): array {

		$rules = [];

		foreach ($fields as $field) {
			$rules[$field] = 'required';
		}

		return $this->validate($rules);

	}

	/**
	 * Check whether the request Content-Type is JSON.
	 */
	public function isJson(): bool {

		$contentType = $this->header('Content-Type') ?? '';
		return str_contains($contentType, 'application/json');

	}

	/**
	 * Get the effective client IP address. Forwarded headers are trusted only when the
	 * immediate remote address belongs to a configured trusted proxy.
	 */
	public function ip(): string {

		$remoteAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

		if ($this->isTrustedProxy($remoteAddress)) {
			$forwardedIp = $this->forwardedClientIp();

			if (!is_null($forwardedIp)) {
				return $forwardedIp;
			}
		}

		return filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : '0.0.0.0';

	}

	/**
	 * True when request is replayed from offline queue.
	 */
	public function isReplayRequest(): bool {

		$value = strtolower((string)($this->header('X-Pair-Replay') ?? ''));

		return ($value === '1' or $value === 'true');

	}

	/**
	 * Parse the JSON body from php://input.
	 */
	private function parseJson(): void {

		$this->jsonParsed = true;

		$body = $this->rawBody();

		if ($body !== '') {
			$decoded = json_decode($body, true);
			if (is_array($decoded)) {
				$this->jsonData = $decoded;
			}
		}

	}

	/**
	 * Collect the validated request data and field-level validation errors.
	 *
	 * @param	array<string, string>	$rules	Associative array of field => 'rule1|rule2|...'
	 * @return	array{validated: array<string, mixed>, errors: array<string, string>}
	 */
	private function collectValidationResult(array $rules): array {

		$data = $this->all();
		$validated = [];
		$errors = [];

		foreach ($rules as $field => $ruleString) {

			$fieldRules = explode('|', $ruleString);
			$value = $data[$field] ?? null;
			$isRequired = in_array('required', $fieldRules);

			// Keep required-field failures deterministic before running the remaining validators.
			if ($isRequired and (is_null($value) or $value === '')) {
				$errors[$field] = 'The field ' . $field . ' is required';
				continue;
			}

			// Missing optional fields are intentionally ignored by the lightweight validator.
			if (is_null($value) and !$isRequired) {
				continue;
			}

			foreach ($fieldRules as $rule) {

				if ($rule === 'required') {
					continue;
				}

				$ruleParts = explode(':', $rule, 2);
				$ruleName = $ruleParts[0];
				$ruleParam = $ruleParts[1] ?? null;

				$error = $this->validateRule($field, $value, $ruleName, $ruleParam);

				if ($error) {
					$errors[$field] = $error;
					break;
				}

			}

			if (!isset($errors[$field])) {
				$validated[$field] = $value;
			}

		}

		return [
			'validated' => $validated,
			'errors' => $errors,
		];

	}

	/**
	 * Map validated data into a request object through the explicit RequestData contract.
	 *
	 * @param	string					$requestDataClass	Class implementing RequestData.
	 * @param	array<string, mixed>	$data				Validated request data.
	 */
	private function mapToRequestData(string $requestDataClass, array $data): RequestData {

		if (!class_exists($requestDataClass)) {
			throw new \InvalidArgumentException('Request data class ' . $requestDataClass . ' was not found');
		}

		if (!is_subclass_of($requestDataClass, RequestData::class)) {
			throw new \InvalidArgumentException('Request data class ' . $requestDataClass . ' must implement ' . RequestData::class);
		}

		return $requestDataClass::fromArray($data);

	}

	/**
	 * Extract the client IP from trusted forwarding headers.
	 */
	private function forwardedClientIp(): ?string {

		$forwarded = $this->header('Forwarded');
		$forwardedIp = $this->parseForwardedHeader($forwarded);

		if (!is_null($forwardedIp)) {
			return $forwardedIp;
		}

		$forwardedFor = $this->header('X-Forwarded-For');

		if (!is_string($forwardedFor) or !strlen(trim($forwardedFor))) {
			return null;
		}

		foreach (explode(',', $forwardedFor) as $candidate) {
			$ip = $this->normalizeForwardedIp($candidate);

			if (!is_null($ip)) {
				return $ip;
			}
		}

		return null;

	}

	/**
	 * Parse the standardized Forwarded header and return the first valid client IP.
	 */
	private function parseForwardedHeader(?string $header): ?string {

		if (!is_string($header) or !strlen(trim($header))) {
			return null;
		}

		foreach (explode(',', $header) as $entry) {
			foreach (explode(';', $entry) as $part) {
				$part = trim($part);

				if (stripos($part, 'for=') !== 0) {
					continue;
				}

				$value = trim(substr($part, 4));
				return $this->normalizeForwardedIp($value);
			}
		}

		return null;

	}

	/**
	 * Normalize a forwarded IP token, stripping quotes, IPv6 brackets, and ports.
	 */
	private function normalizeForwardedIp(?string $value): ?string {

		if (!is_string($value)) {
			return null;
		}

		$value = trim($value, " \t\n\r\0\x0B\"'");

		if (!strlen($value) or strtolower($value) === 'unknown') {
			return null;
		}

		if (str_starts_with($value, '[')) {
			$end = strpos($value, ']');

			if (false !== $end) {
				$value = substr($value, 1, $end - 1);
			}
		} else if (substr_count($value, ':') === 1 and !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$value = substr($value, 0, strrpos($value, ':'));
		}

		return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;

	}

	/**
	 * Return true when the remote address belongs to a configured trusted proxy.
	 * Supports exact IPs and CIDR ranges separated by commas.
	 */
	private function isTrustedProxy(string $ip): bool {

		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			return false;
		}

		$trusted = Env::get('PAIR_TRUSTED_PROXIES');

		if (!is_string($trusted) or !strlen(trim($trusted))) {
			return false;
		}

		foreach (explode(',', $trusted) as $candidate) {
			$candidate = trim($candidate);

			if (!strlen($candidate)) {
				continue;
			}

			if ($this->ipMatchesRange($ip, $candidate)) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Match an IP address against an exact IP or CIDR range.
	 */
	private function ipMatchesRange(string $ip, string $range): bool {

		if (!str_contains($range, '/')) {
			return $ip === $range;
		}

		list($subnet, $prefixLength) = explode('/', $range, 2);
		$ipBinary = inet_pton($ip);
		$subnetBinary = inet_pton($subnet);

		if (false === $ipBinary or false === $subnetBinary or strlen($ipBinary) !== strlen($subnetBinary)) {
			return false;
		}

		$prefixLength = intval($prefixLength);
		$maxPrefix = strlen($ipBinary) * 8;

		if ($prefixLength < 0 or $prefixLength > $maxPrefix) {
			return false;
		}

		$fullBytes = intdiv($prefixLength, 8);
		$remainingBits = $prefixLength % 8;

		if ($fullBytes and substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
			return false;
		}

		if (!$remainingBits) {
			return true;
		}

		$mask = (0xFF << (8 - $remainingBits)) & 0xFF;

		return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);

	}

	/**
	 * Validate a single field value against a rule.
	 *
	 * @param	string		$field		Field name.
	 * @param	mixed		$value		Field value.
	 * @param	string		$ruleName	Rule name (string, int, numeric, email, min, max, bool).
	 * @param	string|null	$ruleParam	Optional rule parameter (for min, max).
	 * @return	string|null	Error message or null if valid.
	 */
	private function validateRule(string $field, mixed $value, string $ruleName, ?string $ruleParam): ?string {

		switch ($ruleName) {

			case 'string':
				if (!is_string($value)) {
					return 'The field ' . $field . ' must be a string';
				}
				break;

			case 'int':
				if (!is_int($value) and !ctype_digit((string)$value)) {
					return 'The field ' . $field . ' must be an integer';
				}
				break;

			case 'numeric':
				if (!is_numeric($value)) {
					return 'The field ' . $field . ' must be numeric';
				}
				break;

			case 'email':
				if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
					return 'The field ' . $field . ' must be a valid email address';
				}
				break;

			case 'bool':
				if (!is_bool($value) and !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
					return 'The field ' . $field . ' must be a boolean';
				}
				break;

			case 'min':
				if (!is_null($ruleParam)) {
					if (is_numeric($value) and is_numeric($ruleParam)) {
						$min = (float)$ruleParam;
						if ((float)$value < $min) {
							return 'The field ' . $field . ' must be at least ' . $ruleParam;
						}
					} else if (is_string($value)) {
						$min = (int)$ruleParam;
						if (mb_strlen($value) < $min) {
							return 'The field ' . $field . ' must be at least ' . $min . ' characters';
						}
					}
				}
				break;

			case 'max':
				if (!is_null($ruleParam)) {
					if (is_numeric($value) and is_numeric($ruleParam)) {
						$max = (float)$ruleParam;
						if ((float)$value > $max) {
							return 'The field ' . $field . ' must not exceed ' . $ruleParam;
						}
					} else if (is_string($value)) {
						$max = (int)$ruleParam;
						if (mb_strlen($value) > $max) {
							return 'The field ' . $field . ' must not exceed ' . $max . ' characters';
						}
					}
				}
				break;

		}

		return null;

	}

}
