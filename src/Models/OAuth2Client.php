<?php

namespace Pair\Models;

use Pair\Core\Env;
use Pair\Orm\ActiveRecord;

/**
 * OAuth2 client model.
 *
 * Responsibilities:
 * - Represents a row in the `oauth2_clients` table.
 * - Issues or reuses an access token for this client (see getToken()).
 * - Sends the access token as a JSON HTTP response (see sendToken()).
 *
 * NOTE: This class only handles token creation/refresh for a client.
 *       Authentication/authorization flows are assumed to be handled elsewhere.
 */
class OAuth2Client extends ActiveRecord {

	/**
	 * Maps to the `id` column (client identifier).
	 */
	protected string $id;

	/**
	 * Maps to the `secret` column (client secret).
	 */
	protected string $secret;

	/**
	 * Maps to the `enabled` column (whether the client can request tokens).
	 */
	protected bool $enabled;

	/**
	 * Maps to the `created_at` column, the datetime when the client was created.
	 */
	protected ?\DateTime $createdAt;

	/**
	 * Maps to the `updated_at` column, the datetime when the client was last updated.
	 */
	protected ?\DateTime $updatedAt;

	/**
	 * Name of the related database table.
	 */
	const TABLE_NAME = 'oauth2_clients';

	/**
	 * Name of the primary key field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Called by the constructor right after the object is populated. Used to define
	 * casting/binding of fields to PHP types.
	 */
	protected function _init(): void {
		
		$this->bindAsBoolean('enabled');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	/**
	 * Generate a random client secret as a hex-encoded string.
	 *
	 * @param	int		Number of random bytes BEFORE hex encoding (final string is 2x this size).
	 * @return	string	Hex string containing the random secret.
	 */
	public static function generateSecret(int $length = 32): string {

		return bin2hex(random_bytes($length));

	}

	/**
	 * Return a valid (non-expired) token for this client.
	 * - If a token exists and is still valid, it is reused and its `updated_at` is refreshed.
	 * - If no valid token exists, a new token is created and persisted.
	 *
	 * Token validity is evaluated against `OAUTH2_TOKEN_LIFETIME` (in seconds) comparing
	 * `updated_at` with "now minus lifetime".
	 *
	 * @return string The bearer token string.
	 */
	public function getToken(): string {

		$query = 'SELECT * FROM `oauth2_tokens` WHERE `client_id` = ?';

		$ot = OAuth2Token::getObjectByQuery($query, [$this->id]);

		// compute the expiration threshold: now - lifetime seconds
		$now = new \DateTime();
		$expiredTime = $now->sub(new \DateInterval('PT' . (int)Env::get('OAUTH2_TOKEN_LIFETIME') . 'S'));

		// if a stored token exists but is expired, delete it
		if ($ot and $ot->updatedAt < $expiredTime) {
			$ot->delete();
		}

		// if there is no valid token, create a new one
		if (!$ot or !$ot->areKeysPopulated()) {
			$ot = new OAuth2Token();
			$ot->clientId = $this->id;
			$ot->token = bin2hex(openssl_random_pseudo_bytes(256));
		}

		// insert the record or refresh `updated_at` on existing one
		$ot->store();

		return $ot->token;

	}

	/**
	 * Send the access token as an OAuth2-compliant JSON response and terminate execution.
	 *
	 * The JSON body includes:
	 * - access_token: the token string
	 * - expires_in:   lifetime in seconds from configuration
	 * - scope:        null (not used)
	 * - token_type:   "Bearer"
	 *
	 * This method sets Content-Type to application/json and HTTP status 200.
	 * It prints the JSON body and then stops execution.
	 */
	public function sendToken(): void {

		// build the response body with a freshly issued or refreshed token
		$body = [
			'access_token'	=> $this->getToken(),
			'expires_in'	=> Env::get('OAUTH2_TOKEN_LIFETIME'),
			'scope'			=> null,
			'token_type'	=> 'Bearer'
		];

		// send JSON response
		header('Content-Type: application/json', true, 200);
		print json_encode($body);
		die();

	}

}