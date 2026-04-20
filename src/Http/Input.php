<?php

declare(strict_types=1);

namespace Pair\Http;

/**
 * Immutable request input object for Pair v4 controllers.
 */
final readonly class Input {

	/**
	 * HTTP method in uppercase.
	 */
	private string $method;

	/**
	 * Query-string parameters.
	 *
	 * @var	array<string, mixed>
	 */
	private array $query;

	/**
	 * Body parameters from form or JSON input.
	 *
	 * @var	array<string, mixed>
	 */
	private array $body;

	/**
	 * Request headers normalized to lowercase keys.
	 *
	 * @var	array<string, string>
	 */
	private array $headers;

	/**
	 * Build an immutable input object.
	 *
	 * @param	array<string, mixed>	$query	Query-string parameters.
	 * @param	array<string, mixed>	$body	Body parameters.
	 * @param	array<string, string>	$headers	Normalized request headers.
	 */
	public function __construct(string $method = 'GET', array $query = [], array $body = [], array $headers = []) {

		$this->method = strtoupper($method);
		$this->query = $query;
		$this->body = $body;
		$this->headers = $headers;

	}

	/**
	 * Build the input object from PHP superglobals.
	 */
	public static function fromGlobals(?string $rawBody = null): self {

		$headers = self::extractHeaders($_SERVER ?? []);
		$body = $_POST ?? [];

		if (!count($body) and self::isJsonRequest($headers)) {

			if (is_null($rawBody)) {
				$rawBody = file_get_contents('php://input') ?: '';
			}

			$body = self::parseJsonBody($rawBody);

		}

		return new self(
			(string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
			$_GET ?? [],
			$body,
			$headers
		);

	}

	/**
	 * Return the request method.
	 */
	public function method(): string {

		return $this->method;

	}

	/**
	 * Return a header value by case-insensitive name.
	 */
	public function header(string $name, ?string $default = null): ?string {

		$headerName = strtolower($name);

		return $this->headers[$headerName] ?? $default;

	}

	/**
	 * Return all normalized headers.
	 *
	 * @return	array<string, string>
	 */
	public function headers(): array {

		return $this->headers;

	}

	/**
	 * Return a single query parameter or the full query array.
	 */
	public function query(?string $key = null, mixed $default = null): mixed {

		if (is_null($key)) {
			return $this->query;
		}

		return $this->query[$key] ?? $default;

	}

	/**
	 * Return a single body parameter or the full body array.
	 */
	public function body(?string $key = null, mixed $default = null): mixed {

		if (is_null($key)) {
			return $this->body;
		}

		return $this->body[$key] ?? $default;

	}

	/**
	 * Return a merged view of query and body data with body values taking precedence.
	 *
	 * @return	array<string, mixed>
	 */
	public function all(): array {

		return array_merge($this->query, $this->body);

	}

	/**
	 * Return a merged input value by key.
	 */
	public function value(?string $key = null, mixed $default = null): mixed {

		if (is_null($key)) {
			return $this->all();
		}

		$data = $this->all();

		return $data[$key] ?? $default;

	}

	/**
	 * Return whether the merged input contains a non-null key.
	 */
	public function has(string $key): bool {

		return !is_null($this->value($key));

	}

	/**
	 * Return only the requested merged keys.
	 *
	 * @param	string[]	$keys	List of keys to extract.
	 * @return	array<string, mixed>
	 */
	public function only(array $keys): array {

		$data = [];

		foreach ($keys as $key) {
			if ($this->has($key)) {
				$data[$key] = $this->value($key);
			}
		}

		return $data;

	}

	/**
	 * Read a merged input value as string.
	 */
	public function string(string $key, ?string $default = null): ?string {

		$value = $this->value($key);

		if (is_null($value)) {
			return $default;
		}

		return (string)$value;

	}

	/**
	 * Read a merged input value as integer.
	 */
	public function int(string $key, ?int $default = null): ?int {

		$value = $this->value($key);

		if (is_null($value) or $value === '') {
			return $default;
		}

		return (int)$value;

	}

	/**
	 * Read a merged input value as boolean.
	 */
	public function bool(string $key, ?bool $default = null): ?bool {

		$value = $this->value($key);

		if (is_null($value) or $value === '') {
			return $default;
		}

		if (is_bool($value)) {
			return $value;
		}

		$normalized = strtolower(trim((string)$value));

		if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;

	}

	/**
	 * Read a merged input value as array.
	 *
	 * @return	array<mixed>
	 */
	public function array(string $key, array $default = []): array {

		$value = $this->value($key);

		if (is_null($value)) {
			return $default;
		}

		return is_array($value) ? $value : [$value];

	}

	/**
	 * Detect whether the request body should be parsed as JSON.
	 *
	 * @param	array<string, string>	$headers	Normalized request headers.
	 */
	private static function isJsonRequest(array $headers): bool {

		$contentType = strtolower($headers['content-type'] ?? '');

		return str_contains($contentType, 'application/json');

	}

	/**
	 * Extract HTTP headers from the PHP server array.
	 *
	 * @param	array<string, mixed>	$server	Raw PHP server array.
	 * @return	array<string, string>
	 */
	private static function extractHeaders(array $server): array {

		$headers = [];

		foreach ($server as $key => $value) {

			if (!is_string($key) or !is_scalar($value)) {
				continue;
			}

			if (str_starts_with($key, 'HTTP_')) {
				$headerName = strtolower(str_replace('_', '-', substr($key, 5)));
				$headers[$headerName] = (string)$value;
				continue;
			}

			if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
				$headerName = strtolower(str_replace('_', '-', $key));
				$headers[$headerName] = (string)$value;
			}

		}

		return $headers;

	}

	/**
	 * Decode a JSON body into an associative array.
	 *
	 * @return	array<string, mixed>
	 */
	private static function parseJsonBody(string $rawBody): array {

		$trimmed = trim($rawBody);

		if ($trimmed === '') {
			return [];
		}

		$decoded = json_decode($trimmed, true);

		return is_array($decoded) ? $decoded : [];

	}

}
