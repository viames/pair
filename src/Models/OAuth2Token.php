<?php

namespace Pair\Models;

use Pair\Core\Env;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * OAuth2 access token model.
 *
 * Responsibilities:
 * - Represents a row in the `oauth2_tokens` table.
 * - Parses Authorization headers for Basic and Bearer schemes.
 * - Validates token existence and freshness against configured lifetime.
 * - Sends minimal RFC 2616 JSON responses for common HTTP statuses.
 */
class OAuth2Token extends ActiveRecord {

	/**
	 * Maps to the auto-increment `id` column.
	 */
	protected int $id;

	/**
	 * Maps to the `client_id` column (foreign key to oauth2_clients.id).
	 */
	protected string $clientId;

	/**
	 * Maps to the `token` column (bearer token string).
	 */
	protected string $token;

	/**
	 * Maps to the `created_at` column.
	 */
	protected \DateTime $createdAt;

	/**
	 * Maps to the `updated_at` column.
	 */
	protected \DateTime $updatedAt;

	/**
	 * Name of the related database table.
	 */
	const TABLE_NAME = 'oauth2_tokens';

	/**
	 * Legacy default lifetime in seconds (unused if `OAUTH2_TOKEN_LIFETIME` is set).
	 */
	const LIFETIME = 3600;

	/**
	 * Name of the primary key field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Called by the constructor after population.
	 * Used to define casting/binding of fields to PHP types.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	/**
	 * Get the raw Authorization header value, if any. Supports common server variables and Apache’s request
	 * headers.
	 *
	 * @return string|null The header value (e.g., "Bearer <token>" or "Basic <base64>"), or null if missing.
	 */
	private static function getAuthorizationHeader(): ?string {

		$header = null;

		if (isset($_SERVER['Authorization'])) {

			$header = trim($_SERVER["Authorization"]);

		} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI

			$header = trim($_SERVER["HTTP_AUTHORIZATION"]);

		} else if (function_exists('apache_request_headers')) {

			$requestHeaders = apache_request_headers();

			// normalize header names capitalization to catch "authorization" vs "Authorization"
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

			if (isset($requestHeaders['Authorization'])) {
				$header = trim($requestHeaders['Authorization']);
			}

		}

		return $header;

	}

	/**
	 * Extract an OAuth2 Bearer token from the Authorization header.
	 *
	 * @return string|null The token string, or null if header is missing or not Bearer.
	 */
	public static function readBearerToken(): ?string {

		$headers = self::getAuthorizationHeader();

		if (!empty($headers) and preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
			return $matches[1];
		}

		return null;

	}

	/**
	 * Extract client_id and client_secret from a Basic Authorization header.
	 *
	 * @return \stdClass|null An object with ->id and ->secret, or null if missing/invalid.
	 */
	public static function readBasicAuth(): ?\stdClass {

		$headers = self::getAuthorizationHeader();

		if (!empty($headers) and preg_match('/Basic\s(\S+)/', $headers, $matches)) {
			$parts = base64_decode($matches[1]);
			$client = new \stdClass();
			list($client->id, $client->secret) = explode(':', $parts, 2);
			return $client;
		}

		return null;

	}

	/**
	 * Validate that a given bearer token exists and is still within lifetime. Lifetime is evaluated
	 * by checking tokens whose `updated_at` is greater than * "NOW() minus OAUTH2_TOKEN_LIFETIME seconds".
	 *
	 * @param	string	The token to validate.
	 * @return	bool	True if valid and fresh, false otherwise.
	 */
	public static function validate(string $bearerToken): bool {

		$query =
			'SELECT COUNT(1)
			FROM ' . self::TABLE_NAME . '
			WHERE token = ?
			AND updated_at > DATE_SUB(NOW(), INTERVAL ' . (int)Env::get('OAUTH2_TOKEN_LIFETIME') . ' SECOND)';

		return (bool)Database::load($query, [$bearerToken], Database::COUNT);

	}

	/**
	 * Send a 200 OK JSON response with details and terminate execution.
	 *
	 * @param string Human-readable detail message.
	 */
	public static function ok(string $detail): void {

		self::sendRfc2616Response('#sec10.2.1','OK','200', $detail);

	}

	/**
	 * Send a 400 Bad Request JSON response and terminate execution. The request could not be
	 * understood by the server due to malformed syntax. The client should not repeat the
	 * request without modifications.
	 *
	 * @param string Human-readable detail message.
	 */
	public static function badRequest(string $detail): void {

		self::sendRfc2616Response('#sec10.4.1','Bad Request','400',$detail);

	}

	/**
	 * The request requires user authentication. The response MUST include a WWW-Authenticate
	 * header field (section 14.47) containing a challenge applicable to the requested
	 * resource. The client MAY repeat the request with a suitable Authorization header field
	 * (section 14.8). If the request already included Authorization credentials, then the 401
	 * response indicates that authorization has been refused for those credentials.
	 *
	 * @param string Human-readable detail message.
	 */
	public static function unauthorized(string $detail): void {

		self::sendRfc2616Response('#sec10.4.2','Unauthorized','401',$detail);

	}

	/**
	 * The server understood the request, but is refusing to fulfill it. Authorization will
	 * not help and the request SHOULD NOT be repeated. If the request method was not HEAD and
	 * the server wishes to make public why the request has not been fulfilled, it SHOULD
	 * describe the reason for the refusal in the entity. If the server does not wish to make
	 * this information available to the client, the status code 404 (Not Found) can be used
	 * instead.
	 * 
	 * @param string Human-readable detail message.
	 */
	public static function forbidden(string $detail): void {

		self::sendRfc2616Response('#sec10.4.4','Forbidden','403', $detail);

	}

	/**
	 * Send a JSON object in HTTP response.
	 * 
	 * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
	 * @param	string		RFC section hash suffix.
	 * @param	string		Short status text.
	 * @param	string		HTTP status code as string.
	 * @param	string|null	Optional detail message.
	 */
	private static function sendRfc2616Response(string $type, string $title, string $status, ?string $detail=null): void {

		header('Content-Type: application/json', true, (int)$status);
		$body = [
			'type'	=> 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html' . $type,
			'title'	=> $title,
			'status'=> $status,
			'detail'=> $detail];
		print json_encode($body);
		die();

	}

}