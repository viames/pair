<?php

namespace Pair\Oauth;

use Pair\ActiveRecord;
use Pair\Database;

class Oauth2Token extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 * @var int
	 */
	protected $id;

	/**
	 * This property maps “client_id” column.
	 * @var string
	 */
	protected $clientId;

	/**
	 * This property maps “token” column.
	 * @var string
	 */
	protected $token;

	/**
	 * This property maps “created_at” column.
	 * @var DateTime
	 */
	protected $createdAt;

	/**
	 * This property maps “updated_at” column.
	 * @var DateTime
	 */
	protected $updatedAt;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'oauth2_tokens';

	/**
	 * Default token lifetime in seconds.
	 * @var int
	 */
	const LIFETIME = 3600;

	/**
	 * Name of primary key db field.
	 * @var string|array
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsInteger('id');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	private static function getAuthorizationHeader(): ?string {

		$headers = NULL;

		if (isset($_SERVER['Authorization'])) {

			$headers = trim($_SERVER["Authorization"]);

		} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI

			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);

		} else if (function_exists('apache_request_headers')) {

			$requestHeaders = apache_request_headers();

			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

			//print_r($requestHeaders);

			if (isset($requestHeaders['Authorization'])) {
				$headers = trim($requestHeaders['Authorization']);
			}

		}

		return $headers;

	}

	/**
	 * Get access token from header.
	 *
	 * @return string|NULL
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
	 *
	 * @return \stdClass|NULL
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
	 * @param string $bearerToken
	 * @return bool
	 */
	public static function validate(string $bearerToken): bool {

		$query =
			'SELECT COUNT(1)' .
			' FROM ' . self::TABLE_NAME .
			' WHERE token = ?' .
			' AND updated_at > DATE_SUB(NOW(), INTERVAL ' . (int)OAUTH2_TOKEN_LIFETIME . ' SECOND)';

		return (bool)Database::load($query, [$bearerToken], PAIR_DB_COUNT);

	}

	public static function ok(string $detail): void {

		self::sendRfc2616Response('#sec10.2.1','OK','200', $detail);

	}

	/**
	 * The request could not be understood by the server due to malformed syntax. The client
	 * SHOULD NOT repeat the request without modifications.
	 * @param string $detail
	 * @return void
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
	 * @param string $detail
	 * @return void
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
	 * @return void
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
	 * @param null|string $detail
	 * @return void
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