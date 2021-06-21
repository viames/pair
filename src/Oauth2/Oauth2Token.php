<?php

namespace Pair;

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

	/**
     * Get access token from header.
     */
    public static function readBearerToken(): ?string {

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

        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
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

	public static function badRequest(string $detail): void {

		$content = [
			'type'	=> 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.1',
			'title'	=> 'Bad Request',
			'status'=> '400',
			'detail'=> $detail
		];

		header('Content-Type: application/json', TRUE, 400);
		print json_encode($content);
		die();

	}

	public static function unauthorized(string $detail): void {

		$content = [
			'type'  => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.2',
			'title'	=> 'Unauthorized',
			'status'=> '401',
			'detail'=> $detail
		];

		header('Content-Type: application/json', TRUE, 401);
		print json_encode($content);
		die();

	}

}