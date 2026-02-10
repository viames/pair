<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * OAuth2 client model.
 *
 * Represents a row in `oauth2_clients` and issues client-credentials access tokens.
 */
class OAuth2Client extends ActiveRecord {

	/**
	 * Client unique identifier.
	 */
	protected string $id;

	/**
	 * The client secret used for authentication.
	 */
	protected string $secret;

	/**
	 * Whether the client is allowed to request tokens.
	 */
	protected bool $enabled;

	/**
	 * The datetime when the client was created.
	 */
	protected ?\DateTime $createdAt;

	/**
	 * The datetime when the client was last updated.
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
	 * Called by the constructor right after the object is populated.
	 */
	protected function _init(): void {

		$this->bindAsBoolean('enabled');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	/**
	 * Generate a random client secret as a hex-encoded string.
	 *
	 * @param int $bytes Number of random bytes before hex encoding.
	 * @return string Hex-encoded random secret.
	 */
	public static function generateSecret(int $bytes = 32): string {

		if ($bytes < 16) {
			$bytes = 16;
		}

		return bin2hex(random_bytes($bytes));

	}

	/**
	 * Return a valid (non-expired) token for this client.
	 * If a valid token already exists, its `updated_at` is refreshed.
	 * Otherwise a new one is generated and stored.
	 *
	 * @throws \RuntimeException If the client is not loaded or not enabled.
	 * @return string The bearer token value.
	 */
	public function issueAccessToken(): string {

		if (!$this->isLoaded()) {
			throw new \RuntimeException('OAuth2 client not found');
		}

		if (!$this->enabled) {
			throw new \RuntimeException('OAuth2 client is disabled');
		}

		$lifetime = OAuth2Token::getLifetimeSeconds();

		$query =
			'SELECT *
			FROM `oauth2_tokens`
			WHERE `client_id` = ?
			AND `updated_at` > DATE_SUB(NOW(), INTERVAL ' . $lifetime . ' SECOND)
			ORDER BY `updated_at` DESC
			LIMIT 1';

		$oauth2Token = OAuth2Token::getObjectByQuery($query, [$this->id]);

		if (!$oauth2Token or !$oauth2Token->areKeysPopulated()) {
			$oauth2Token = new OAuth2Token();
			$oauth2Token->clientId = $this->id;
			$oauth2Token->token = OAuth2Token::generateToken();
		}

		$oauth2Token->store();

		// keep only one fresh token per client
		Database::run(
			'DELETE FROM `oauth2_tokens`
			WHERE `client_id` = ?
			AND (`id` <> ? OR `updated_at` <= DATE_SUB(NOW(), INTERVAL ' . $lifetime . ' SECOND))',
			[$this->id, (int)$oauth2Token->id]
		);

		return (string)$oauth2Token->token;

	}

	/**
	 * Send the access token as an OAuth2-compliant JSON response and terminate execution.
	 */
	public function sendAccessTokenResponse(): void {

		$body = [
			'access_token'	=> $this->issueAccessToken(),
			'expires_in'	=> OAuth2Token::getLifetimeSeconds(),
			'scope'			=> null,
			'token_type'	=> 'Bearer'
		];

		header('Content-Type: application/json', true, 200);
		print json_encode($body);
		die();

	}

}
