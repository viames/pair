<?php

namespace Pair;

class Oauth2Client extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 * @var string
	 */
	protected $id;

	/**
	 * This property maps “secret” column.
	 * @var string
	 */
	protected $secret;

	/**
	 * This property maps “enabled” column.
	 * @var int
	 */
	protected $enabled;

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
	const TABLE_NAME = 'oauth2_clients';

	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsBoolean('enabled');

		$this->bindAsDatetime('createdAt', 'updatedAt');

	}

	/**
	 * Se esiste un token non scaduto, ne aggiorna la data e lo restituisce, altrimenti
	 * ne crea uno nuovo.
	 * @return	string
	 */
	public function getToken(): string {

		$query = 'SELECT * FROM `oauth2_tokens` WHERE `client_id` = ?';

		$ot = Oauth2Token::getObjectByQuery($query, [$this->id]);

		// creates a date in the past by the number of seconds in duration
		$now = new \DateTime();
		$expiredTime = $now->sub(new \DateInterval('PT' . (int)OAUTH2_TOKEN_LIFETIME . 'S'));

		// crea un nuovo token
		if ($ot and $ot->updatedAt < $expiredTime) {
			$ot->delete();
		}

		if (!$ot->areKeysPopulated()) {
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
		$tokenObj = [
			'access_token'	=> $this->getToken(),
			'expires_in'	=> OAUTH2_TOKEN_LIFETIME,
			'scope'			=> NULL,
			'token_type'	=> 'Bearer'
		];

		// invia il token
		header('Content-Type: application/json', TRUE);
		print json_encode($tokenObj);
		die();

	}

}