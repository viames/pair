<?php

namespace Pair\Api;

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
	 * Validate request data against a set of rules. Returns the validated data array
	 * or sends a 400 error response on failure.
	 *
	 * Supported rules (pipe-separated): required, string, int, numeric, email, min:N, max:N, bool.
	 *
	 * @param	array	$rules	Associative array of field => 'rule1|rule2|...'
	 */
	public function validate(array $rules): array {

		$data = $this->all();
		$validated = [];
		$errors = [];

		foreach ($rules as $field => $ruleString) {

			$fieldRules = explode('|', $ruleString);
			$value = $data[$field] ?? null;
			$isRequired = in_array('required', $fieldRules);

			// check required
			if ($isRequired and (is_null($value) or $value === '')) {
				$errors[$field] = 'The field ' . $field . ' is required';
				continue;
			}

			// skip further validation if field is not present and not required
			if (is_null($value) and !$isRequired) {
				continue;
			}

			// validate each rule
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

		if (!empty($errors)) {
			ApiResponse::error('INVALID_FIELDS', ['errors' => $errors]);
		}

		return $validated;

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
	 * Get the client IP address.
	 */
	public function ip(): string {

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

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
					$min = (int)$ruleParam;
					if (is_string($value) and mb_strlen($value) < $min) {
						return 'The field ' . $field . ' must be at least ' . $min . ' characters';
					} else if (is_numeric($value) and $value < $min) {
						return 'The field ' . $field . ' must be at least ' . $min;
					}
				}
				break;

			case 'max':
				if (!is_null($ruleParam)) {
					$max = (int)$ruleParam;
					if (is_string($value) and mb_strlen($value) > $max) {
						return 'The field ' . $field . ' must not exceed ' . $max . ' characters';
					} else if (is_numeric($value) and $value > $max) {
						return 'The field ' . $field . ' must not exceed ' . $max;
					}
				}
				break;

		}

		return null;

	}

}
