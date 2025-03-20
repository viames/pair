<?php

namespace Pair\Models;

use Pair\Core\Config;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

class Oauth2Token extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * This property maps “client_id” column.
	 */
	protected string $clientId;

	/**
	 * This property maps “token” column.
	 */
	protected string $token;

	/**
	 * This property maps “created_at” column.
	 */
	protected \DateTime $createdAt;

	/**
	 * This property maps “updated_at” column.
	 */
	protected \DateTime $updatedAt;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'oauth2_tokens';

	/**
	 * Default token lifetime in seconds.
	 */
	const LIFETIME = 3600;

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	/**
	 * Get the Authorization header.
	 */
	private static function getAuthorizationHeader(): ?string {

		$header = NULL;

		if (isset($_SERVER['Authorization'])) {

			$header = trim($_SERVER["Authorization"]);

		} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI

			$header = trim($_SERVER["HTTP_AUTHORIZATION"]);

		} else if (function_exists('apache_request_headers')) {

			$requestHeaders = apache_request_headers();

			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

			if (isset($requestHeaders['Authorization'])) {
				$header = trim($requestHeaders['Authorization']);
			}

		}

		return $header;

	}

	/**
	 * Get access token from header.
	 */
	public static function readBearerToken(): ?string {

		$headers = self::getAuthorizationHeader();

		if (!empty($headers) and preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
			return $matches[1];
		}

		return NULL;

	}

	/**
	 * Read client_id and client_secret from header authorization.
	 */
	public static function readBasicAuth(): ?\stdClass {

		$headers = self::getAuthorizationHeader();

		if (!empty($headers) and preg_match('/Basic\s(\S+)/', $headers, $matches)) {
			$parts = base64_decode($matches[1]);
			$client = new \stdClass();
			list($client->id, $client->secret) = explode(':', $parts, 2);
			return $client;
		}

		return NULL;

	}

	/**
	 * Verify that the past token exists and has a compatible date and creates a past date for the number of seconds in duration
	 *
	 * @param string $bearerToken
	 */
	public static function validate(string $bearerToken): bool {

		$query =
			'SELECT COUNT(1)
			FROM ' . self::TABLE_NAME . '
			WHERE token = ?
			AND updated_at > DATE_SUB(NOW(), INTERVAL ' . (int)Config::get('OAUTH2_TOKEN_LIFETIME') . ' SECOND)';

		return (bool)Database::load($query, [$bearerToken], Database::COUNT);

	}

	public static function ok(string $detail): void {

		self::sendRfc2616Response('#sec10.2.1','OK','200', $detail);

	}

	/**
	 * The request could not be understood by the server due to malformed syntax. The client
	 * Should not repeat the request without modifications.
	 *
	 * @param string $detail
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
	 * @param string $detail
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
	 * @param string $detail
	 */
	public static function forbidden(string $detail): void {

		self::sendRfc2616Response('#sec10.4.4','Forbidden','403', $detail);

	}

	/**
	 * Send a JSON object in HTTP response.
	 * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
	 * @param string $type
	 * @param string $title
	 * @param string $status
	 * @param NULL|string $detail
	 */
	private static function sendRfc2616Response(string $type, string $title, string $status, ?string $detail=NULL): void {

		header('Content-Type: application/json', TRUE, (int)$status);
		$body = [
			'type'	=> 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html' . $type,
			'title'	=> $title,
			'status'=> $status,
			'detail'=> $detail];
		print json_encode($body);
		die();

	}

}