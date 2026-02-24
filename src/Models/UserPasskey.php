<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;

/**
 * Stores WebAuthn passkey credentials associated to application users.
 *
 * The record contains the credential identifier, the public key used to verify
 * assertions and security metadata such as the signature counter.
 */
class UserPasskey extends ActiveRecord {

	/**
	 * Passkey unique identifier.
	 */
	protected int $id;

	/**
	 * ID of the related user.
	 */
	protected int $userId;

	/**
	 * Base64url-encoded credential ID.
	 */
	protected string $credentialId;

	/**
	 * Public key in PEM format.
	 */
	protected string $publicKey;

	/**
	 * Signature counter returned by the authenticator.
	 */
	protected int $signCount = 0;

	/**
	 * Optional passkey label, set by the application.
	 */
	protected ?string $label = null;

	/**
	 * Optional transports as JSON-encoded array.
	 */
	protected ?string $transports = null;

	/**
	 * Last successful assertion timestamp.
	 */
	protected ?\DateTime $lastUsedAt = null;

	/**
	 * Revoked timestamp. Null means active credential.
	 */
	protected ?\DateTime $revokedAt = null;

	/**
	 * Creation timestamp.
	 */
	protected \DateTime $createdAt;

	/**
	 * Last update timestamp.
	 */
	protected \DateTime $updatedAt;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'users_passkeys';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['userId'];

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'				=> ['int unsigned', 'NO', 'PRI', null, 'auto_increment'],
		'user_id'			=> ['int unsigned', 'NO', 'MUL', null, ''],
		'credential_id'		=> ['varchar(255)', 'NO', 'UNI', null, ''],
		'public_key'		=> ['TEXT', 'NO', '', null, ''],
		'sign_count'		=> ['int unsigned', 'NO', '', '0', ''],
		'label'				=> ['varchar(120)', 'YES', '', null, ''],
		'transports'		=> ['varchar(255)', 'YES', '', null, ''],
		'last_used_at'		=> ['datetime', 'YES', '', null, ''],
		'revoked_at'		=> ['datetime', 'YES', '', null, ''],
		'created_at'		=> ['datetime', 'NO', '', null, ''],
		'updated_at'		=> ['datetime', 'NO', '', null, '']
	];

	/**
	 * Called by constructor after object population.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id', 'userId', 'signCount');

		$this->bindAsDatetime('lastUsedAt', 'revokedAt', 'createdAt', 'updatedAt');

	}

	/**
	 * Returns the active passkey by credential ID.
	 *
	 * @param	string	$credentialId	Base64url credential identifier.
	 * @return	self|null
	 */
	public static function getActiveByCredentialId(string $credentialId): ?self {

		$query =
			'SELECT *
			FROM `' . self::TABLE_NAME . '`
			WHERE `credential_id` = ?
			AND `revoked_at` IS NULL
			LIMIT 1';

		$credentialId = trim($credentialId);

		if ('' === $credentialId) {
			return null;
		}

		return self::getObjectByQuery($query, [$credentialId]);

	}

	/**
	 * Returns all active passkeys for a user.
	 *
	 * @param	int	$userId	User ID.
	 * @return	Collection
	 */
	public static function getActiveByUserId(int $userId): Collection {

		$query =
			'SELECT *
			FROM `' . self::TABLE_NAME . '`
			WHERE `user_id` = ?
			AND `revoked_at` IS NULL
			ORDER BY `created_at` DESC';

		return self::getObjectsByQuery($query, [$userId]);

	}

	/**
	 * Returns mapping [propertyName => db_field_name].
	 */
	protected static function getBinds(): array {

		return [
			'id'			=> 'id',
			'userId'		=> 'user_id',
			'credentialId'	=> 'credential_id',
			'publicKey'		=> 'public_key',
			'signCount'		=> 'sign_count',
			'label'			=> 'label',
			'transports'	=> 'transports',
			'lastUsedAt'	=> 'last_used_at',
			'revokedAt'		=> 'revoked_at',
			'createdAt'		=> 'created_at',
			'updatedAt'		=> 'updated_at'
		];

	}

	/**
	 * Returns credential transports as array.
	 *
	 * @return	string[]
	 */
	public function getTransports(): array {

		if (!$this->transports) {
			return [];
		}

		$decoded = json_decode($this->transports, true);

		return is_array($decoded) ? array_values($decoded) : [];

	}

	/**
	 * Returns true if the passkey is revoked.
	 */
	public function isRevoked(): bool {

		return !is_null($this->revokedAt);

	}

	/**
	 * Updates the last-used timestamp and, when increased, the signature counter.
	 *
	 * @param	int	$signCount	New sign counter from authenticator.
	 */
	public function markUsed(int $signCount): bool {

		$this->lastUsedAt = new \DateTime();
		$this->updatedAt = new \DateTime();

		if ($signCount > $this->signCount) {
			$this->signCount = $signCount;
		}

		return $this->update('lastUsedAt', 'updatedAt', 'signCount');

	}

	/**
	 * Revokes this passkey, making it unusable for future logins.
	 */
	public function revoke(): bool {

		$this->revokedAt = new \DateTime();
		$this->updatedAt = new \DateTime();

		return $this->update('revokedAt', 'updatedAt');

	}

	/**
	 * Sets credential transports list.
	 *
	 * @param	string[]	$transports
	 */
	public function setTransports(array $transports): void {

		$transports = array_filter(array_map(fn($item) => trim((string)$item), $transports));
		$this->transports = count($transports) ? json_encode(array_values($transports)) : null;

	}

}
