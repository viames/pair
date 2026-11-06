<?php

namespace Pair\Models;

use Pair\Core\Env;
use Pair\Http\AuthorizationHeader;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * OAuth2 access token model.
 *
 * Represents a row in `oauth2_tokens`, parses Authorization headers and
 * validates bearer token freshness.
 */
class OAuth2Token extends ActiveRecord {

	/**
	 * Token unique identifier.
	 */
	protected int $id;

	/**
	 * ID of the OAuth2 client this token belongs to.
	 */
	protected string $clientId;

	/**
	 * The bearer token string.
	 */
	protected string $token;

	/**
	 * The datetime when the token was created.
	 */
	protected \DateTime $createdAt;

	/**
	 * The datetime when the token was last refreshed.
	 */
	protected \DateTime $updatedAt;

	/**
	 * Name of the related database table.
	 */
	const TABLE_NAME = 'oauth2_tokens';

	/**
	 * Default token lifetime in seconds.
	 */
	const LIFETIME = 3600;

	/**
	 * Name of the primary key field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Called by the constructor after population.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	/**
	 * Send a 400 Bad Request JSON response and terminate execution.
	 * 
	 * @param string $detail Detail message to include in the response.
	 */
	public static function badRequest(string $detail): void {

		self::sendProblemDetailResponse('#sec10.4.1', 'Bad Request', 400, $detail);

	}

	/**
	 * Extract client credentials from a Basic Authorization header.
	 *
	 * @return array{id: string, secret: string}|null
	 */
	public static function basicCredentials(): ?array {

		// Preserve this legacy facade; new OAuth endpoints use Pair\Api\OAuth2::clientCredentials().
		$header = self::getAuthorizationHeader();
		if (!$header or !preg_match('/^\\s*Basic\\s+(\\S+)\\s*$/i', $header, $matches)) {
			return null;
		}
		$decoded = base64_decode($matches[1], true);
		if (false === $decoded or !str_contains($decoded, ':')) {
			return null;
		}
		[$id, $secret] = explode(':', $decoded, 2);
		$id = trim($id);
		$secret = trim($secret);
		return ('' !== $id and '' !== $secret) ? ['id'=>$id, 'secret'=>$secret] : null;

	}

	/**
	 * Extract an OAuth2 Bearer token from the Authorization header.
	 *
	 * @return string|null The bearer token, or null if not present.
	 */
	public static function bearerToken(): ?string {

		// Preserve the legacy facade while AuthorizationHeader enforces the RFC 6750 grammar.
		$header = self::getAuthorizationHeader();
		if (!$header or !preg_match('/^\\s*Bearer\\s+(\\S+)\\s*$/i', $header, $matches)) {
			return null;
		}
		return trim($matches[1]);

	}

	/**
	 * Send a 403 Forbidden JSON response and terminate execution.
	 * 
	 * @param string $detail Detail message to include in the response.
	 */
	public static function forbidden(string $detail): void {

		self::sendProblemDetailResponse('#sec10.4.4', 'Forbidden', 403, $detail);

	}

	/**
	 * Generate a random bearer token value.
	 *
	 * @param int $bytes Number of random bytes before hex encoding.
	 * @return string Hex-encoded random token.
	 */
	public static function generateToken(int $bytes = 256): string {

		if ($bytes < 16) {
			$bytes = 16;
		}

		return bin2hex(random_bytes($bytes));

	}

	/**
	 * Return the effective OAuth2 token lifetime in seconds.
	 *
	 * @return int Lifetime in seconds.
	 */
	public static function getLifetimeSeconds(): int {

		$lifetime = Env::get('OAUTH2_TOKEN_LIFETIME');

		if (!is_numeric($lifetime) or (int)$lifetime <= 0) {
			return self::LIFETIME;
		}

		return (int)$lifetime;

	}

	/**
	 * Preserve pre-v4 token validation for applications with a frozen external contract.
	 * @deprecated New endpoints must use isValid() or validateBearer().
	 *
	 * @param string $bearerToken The token value to validate.
	 * @return bool True if the token is valid and not expired.
	 */
	public static function isLegacyValid(string $bearerToken): bool {

		$bearerToken = trim($bearerToken);

		if ('' === $bearerToken) {
			return false;
		}

		$query =
			'SELECT COUNT(1)
			FROM `' . self::TABLE_NAME . '`
			WHERE `token` = ?
			AND `updated_at` > DATE_SUB(NOW(), INTERVAL ' . self::getLifetimeSeconds() . ' SECOND)';

		return (bool)Database::load($query, [$bearerToken], Database::COUNT);

	}

	/** Validate a token with client revocation and exact token comparison, keeping the historical refresh clock. */
	public static function isValid(string $bearerToken): bool {
		return null !== self::validateBearer($bearerToken, null, false);
	}

	/** Return the enabled client's ID for an exact bearer token, with a fixed lifetime by default. */
	public static function validateBearer(string $bearerToken, ?int $lifetime = null, bool $fixedLifetime = true): ?string {
		if (AuthorizationHeader::bearerToken('Bearer ' . $bearerToken) !== $bearerToken) {
			return null;
		}
		$lifetime ??= self::getLifetimeSeconds();
		if ($lifetime < 1) {
			throw new \InvalidArgumentException('OAuth lifetime must be positive.');
		}
		$clock = $fixedLifetime ? 'created_at' : 'updated_at';
		$row = Database::load('SELECT t.token, t.client_id FROM oauth2_tokens t INNER JOIN oauth2_clients c ON c.id=t.client_id WHERE t.token=? AND c.enabled=1 AND t.' . $clock . '>DATE_SUB(NOW(), INTERVAL ' . $lifetime . ' SECOND)', [$bearerToken], Database::OBJECT);
		if (!$row or !hash_equals((string)$row->token, $bearerToken)) {
			return null;
		}
		return (string)$row->client_id;
	}

	/**
	 * Send a 401 Unauthorized JSON response with a WWW-Authenticate header and terminate execution.
	 * 
	 * @param string $detail Detail message to include in the response.
	 */
	public static function unauthorized(string $detail): void {

		self::sendProblemDetailResponse('#sec10.4.2', 'Unauthorized', 401, $detail, [
			'WWW-Authenticate: Bearer'
		]);

	}

	/**
	 * Return the raw Authorization header value, if present.
	 *
	 * @return string|null The header value, or null if not found.
	 */
	private static function getAuthorizationHeader(): ?string {

		return AuthorizationHeader::fromGlobals();

	}

	/**
	 * Send an RFC 7807 Problem Details JSON response and terminate execution.
	 *
	 * @see https://www.rfc-editor.org/rfc/rfc7807
	 * @param string $type RFC 2616 Section 10 hash suffix for the type URL.
	 * @param string $title Short status text.
	 * @param int $status HTTP status code.
	 * @param string|null $detail Optional detail message.
	 * @param string[] $headers Optional headers to emit before body.
	 */
	private static function sendProblemDetailResponse(string $type, string $title, int $status, ?string $detail = null, array $headers = []): void {

		foreach ($headers as $headerValue) {
			header($headerValue);
		}

		header('Content-Type: application/json', true, $status);
		$body = [
			'type'	=> 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html' . $type,
			'title'	=> $title,
			'status'=> $status,
			'detail'=> $detail
		];
		print json_encode($body);
		die();

	}

}
