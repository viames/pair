<?php

namespace Pair\Models;

use Pair\Core\Env;
use Pair\Orm\ActiveRecord;

class Oauth2Client extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected string $id;

	/**
	 * This property maps “secret” column.
	 */
	protected string $secret;

	/**
	 * This property maps “enabled” column.
	 */
	protected bool $enabled;

	/**
	 * This property maps “created_at” column.
	 */
	protected ?\DateTime $createdAt;

	/**
	 * This property maps “updated_at” column.
	 */
	protected ?\DateTime $updatedAt;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'oauth2_clients';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {
		
		$this->bindAsBoolean('enabled');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	/**
	 * Generates a random secret.
	 */
	public static function generateSecret(int $length = 32): string {

		return bin2hex(openssl_random_pseudo_bytes($length));

	}

	/**
	 * If there is a non-expired token, update its date and return it, otherwise
	 * create a new one.
	 */
	public function getToken(): string {

		$query = 'SELECT * FROM `oauth2_tokens` WHERE `client_id` = ?';

		$ot = Oauth2Token::getObjectByQuery($query, [$this->id]);

		// creates a date in the past by the number of seconds in duration
		$now = new \DateTime();
		$expiredTime = $now->sub(new \DateInterval('PT' . (int)Env::get('OAUTH2_TOKEN_LIFETIME') . 'S'));

		// crea un nuovo token
		if ($ot and $ot->updatedAt < $expiredTime) {
			$ot->delete();
		}

		if (!$ot or !$ot->areKeysPopulated()) {
			$ot = new Oauth2Token();
			$ot->clientId = $this->id;
			$ot->token = bin2hex(openssl_random_pseudo_bytes(256));
		}

		// crea il record oppure sovrascrive updateAt
		$ot->store();

		return $ot->token;

	}

	public function sendToken(): void {

		// crea il token
		$body = [
			'access_token'	=> $this->getToken(),
			'expires_in'	=> Env::get('OAUTH2_TOKEN_LIFETIME'),
			'scope'			=> NULL,
			'token_type'	=> 'Bearer'
		];

		// invia il token
		header('Content-Type: application/json', TRUE, 200);
		print json_encode($body);
		die();

	}

}