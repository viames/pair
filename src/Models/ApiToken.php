<?php

declare(strict_types=1);

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * Stores mobile/API bearer sessions with short-lived access tokens and optional rotating refresh tokens.
 */
class ApiToken extends ActiveRecord {

	/**
	 * Token row identifier.
	 */
	protected int $id;

	/**
	 * ID of the authenticated user.
	 */
	protected int $userId;

	/**
	 * SHA-256 hash of the current access token.
	 */
	protected string $accessTokenHash;

	/**
	 * SHA-256 hash of the current refresh token, when persistent refresh is enabled.
	 */
	protected ?string $refreshTokenHash = null;

	/**
	 * Access-token expiration timestamp.
	 */
	protected \DateTime $accessExpiresAt;

	/**
	 * Refresh-token expiration timestamp.
	 */
	protected ?\DateTime $refreshExpiresAt = null;

	/**
	 * Optional stable device hash associated with the bearer session.
	 */
	protected ?string $deviceHash = null;

	/**
	 * Optional password-version hash captured when the token was issued.
	 */
	protected ?string $passwordVersionHash = null;

	/**
	 * Optional client-provided device label.
	 */
	protected ?string $deviceName = null;

	/**
	 * Client IP address observed when the token was issued.
	 */
	protected ?string $ipAddress = null;

	/**
	 * Client user agent observed when the token was issued.
	 */
	protected ?string $userAgent = null;

	/**
	 * Last successful refresh timestamp.
	 */
	protected ?\DateTime $lastUsedAt = null;

	/**
	 * Revocation timestamp. Null means the token is active.
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
	const TABLE_NAME = 'api_tokens';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'					=> ['int unsigned', 'NO', 'PRI', null, 'auto_increment'],
		'user_id'				=> ['int unsigned', 'NO', 'MUL', null, ''],
		'access_token_hash'		=> ['char(64)', 'NO', 'UNI', null, ''],
		'refresh_token_hash'	=> ['char(64)', 'YES', 'UNI', null, ''],
		'access_expires_at'		=> ['datetime', 'NO', 'MUL', null, ''],
		'refresh_expires_at'	=> ['datetime', 'YES', 'MUL', null, ''],
		'device_hash'			=> ['varchar(64)', 'YES', 'MUL', null, ''],
		'password_version_hash'	=> ['char(64)', 'YES', '', null, ''],
		'device_name'			=> ['varchar(120)', 'YES', '', null, ''],
		'ip_address'			=> ['varchar(45)', 'YES', '', null, ''],
		'user_agent'			=> ['varchar(255)', 'YES', '', null, ''],
		'last_used_at'			=> ['datetime', 'YES', '', null, ''],
		'revoked_at'			=> ['datetime', 'YES', 'MUL', null, ''],
		'created_at'			=> ['datetime', 'NO', '', null, ''],
		'updated_at'			=> ['datetime', 'NO', '', null, '']
	];

	/**
	 * Called by constructor after object population.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id', 'userId');

		$this->bindAsDatetime('accessExpiresAt', 'refreshExpiresAt', 'lastUsedAt', 'revokedAt', 'createdAt', 'updatedAt');

	}

	/**
	 * Remove the auto-increment key from insert payloads before the row exists.
	 */
	protected function afterPrepareData(\stdClass &$dbObj) {

		if (property_exists($dbObj, 'id') and !$this->getId()) {
			unset($dbObj->id);
		}

	}

	/**
	 * Generate a random token value safe for bearer use.
	 */
	public static function generateToken(int $bytes = 32): string {

		if ($bytes < 16) {
			$bytes = 16;
		}

		return bin2hex(random_bytes($bytes));

	}

	/**
	 * Return the deterministic database hash for a bearer token.
	 */
	public static function hashToken(string $token): string {

		return hash('sha256', trim($token));

	}

	/**
	 * Return the configured access-token lifetime in seconds.
	 */
	public static function getAccessLifetimeSeconds(): int {

		$lifetime = Env::get('PAIR_MOBILE_ACCESS_TOKEN_LIFETIME');

		return (is_numeric($lifetime) and (int)$lifetime > 0) ? (int)$lifetime : 900;

	}

	/**
	 * Return the configured refresh-token lifetime in seconds.
	 */
	public static function getRefreshLifetimeSeconds(): int {

		$lifetime = Env::get('PAIR_MOBILE_REFRESH_TOKEN_LIFETIME');

		return (is_numeric($lifetime) and (int)$lifetime > 0) ? (int)$lifetime : 2592000;

	}

	/**
	 * Issue a new bearer session for the given user.
	 *
	 * @return	array{token: self, accessToken: string, refreshToken: string|null}
	 */
	public static function issueForUser(User $user, bool $withRefreshToken = true, ?string $deviceName = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $deviceHash = null, ?string $passwordVersionHash = null): array {

		$accessToken = self::generateToken();
		$refreshToken = $withRefreshToken ? self::generateToken(48) : null;
		$now = new \DateTime();

		$token = new self();
		$token->userId = (int)$user->id;
		$token->accessTokenHash = self::hashToken($accessToken);
		$token->refreshTokenHash = $refreshToken ? self::hashToken($refreshToken) : null;
		$token->accessExpiresAt = (clone $now)->modify('+' . self::getAccessLifetimeSeconds() . ' seconds');
		$token->refreshExpiresAt = $refreshToken ? (clone $now)->modify('+' . self::getRefreshLifetimeSeconds() . ' seconds') : null;
		$token->deviceHash = self::truncateNullable($deviceHash, 64);
		$token->passwordVersionHash = self::normalizeHash($passwordVersionHash);
		$token->deviceName = self::truncateNullable($deviceName, 120);
		$token->ipAddress = self::truncateNullable($ipAddress, 45);
		$token->userAgent = self::truncateNullable($userAgent, 255);
		$token->createdAt = clone $now;
		$token->updatedAt = clone $now;
		$token->store();

		return [
			'token'			=> $token,
			'accessToken'	=> $accessToken,
			'refreshToken'	=> $refreshToken,
		];

	}

	/**
	 * Return the active token row for a bearer access token.
	 */
	public static function getActiveByAccessToken(string $accessToken): ?self {

		$hash = self::hashToken($accessToken);
		$now = self::nowString();
		$query =
			'SELECT *
			FROM `' . self::TABLE_NAME . '`
			WHERE `access_token_hash` = ?
			AND `access_expires_at` > ?
			AND `revoked_at` IS NULL
			LIMIT 1';

		try {
			$token = self::getObjectByQuery($query, [$hash, $now]);
		} catch (\Throwable $e) {
			// Keep legacy OAuth2 bearer validation working until the api_tokens migration is applied.
			return null;
		}

		return ($token and $token->areKeysPopulated()) ? $token : null;

	}

	/**
	 * Rotate a refresh token and return a fresh bearer session payload.
	 *
	 * @return	array{token: self, accessToken: string, refreshToken: string}|null
	 */
	public static function refresh(string $refreshToken): ?array {

		$refreshToken = trim($refreshToken);

		if ('' === $refreshToken) {
			return null;
		}

		$oldRefreshHash = self::hashToken($refreshToken);
		$now = self::nowString();
		$query =
			'SELECT *
			FROM `' . self::TABLE_NAME . '`
			WHERE `refresh_token_hash` = ?
			AND `refresh_expires_at` > ?
			AND `revoked_at` IS NULL
			LIMIT 1';

		$token = self::getObjectByQuery($query, [$oldRefreshHash, $now]);

		if (!$token or !$token->areKeysPopulated()) {
			return null;
		}

		$accessToken = self::generateToken();
		$newRefreshToken = self::generateToken(48);
		$newAccessHash = self::hashToken($accessToken);
		$newRefreshHash = self::hashToken($newRefreshToken);
		$issuedAt = new \DateTime();
		$accessExpiresAt = (clone $issuedAt)->modify('+' . self::getAccessLifetimeSeconds() . ' seconds');
		$refreshExpiresAt = (clone $issuedAt)->modify('+' . self::getRefreshLifetimeSeconds() . ' seconds');

		// The old refresh hash in the WHERE clause makes rotation atomic for concurrent callers.
		$updated = Database::run(
			'UPDATE `' . self::TABLE_NAME . '`
			SET `access_token_hash` = ?,
				`refresh_token_hash` = ?,
				`access_expires_at` = ?,
				`refresh_expires_at` = ?,
				`last_used_at` = ?,
				`updated_at` = ?
			WHERE `id` = ?
			AND `refresh_token_hash` = ?
			AND `refresh_expires_at` > ?
			AND `revoked_at` IS NULL',
			[
				$newAccessHash,
				$newRefreshHash,
				$accessExpiresAt->format('Y-m-d H:i:s'),
				$refreshExpiresAt->format('Y-m-d H:i:s'),
				$issuedAt->format('Y-m-d H:i:s'),
				$issuedAt->format('Y-m-d H:i:s'),
				(int)$token->id,
				$oldRefreshHash,
				$now,
			]
		);

		if (1 !== $updated) {
			return null;
		}

		$token->accessTokenHash = $newAccessHash;
		$token->refreshTokenHash = $newRefreshHash;
		$token->accessExpiresAt = $accessExpiresAt;
		$token->refreshExpiresAt = $refreshExpiresAt;
		$token->lastUsedAt = $issuedAt;
		$token->updatedAt = $issuedAt;

		return [
			'token'			=> $token,
			'accessToken'	=> $accessToken,
			'refreshToken'	=> $newRefreshToken,
		];

	}

	/**
	 * Revoke the active row matching a refresh token.
	 */
	public static function revokeByRefreshToken(string $refreshToken): bool {

		$hash = self::hashToken($refreshToken);
		$now = new \DateTime();

		return (bool)Database::run(
			'UPDATE `' . self::TABLE_NAME . '`
			SET `revoked_at` = ?, `updated_at` = ?
			WHERE `refresh_token_hash` = ?
			AND `revoked_at` IS NULL',
			[$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), $hash]
		);

	}

	/**
	 * Revoke one active bearer session owned by the given user.
	 */
	public static function revokeByIdForUser(int $tokenId, User $user): bool {

		if ($tokenId < 1) {
			return false;
		}

		$now = new \DateTime();

		return (bool)Database::run(
			'UPDATE `' . self::TABLE_NAME . '`
			SET `revoked_at` = ?, `updated_at` = ?
			WHERE `id` = ?
			AND `user_id` = ?
			AND `revoked_at` IS NULL',
			[$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), $tokenId, (int)$user->id]
		);

	}

	/**
	 * Revoke every active bearer session owned by the given user.
	 */
	public static function revokeAllForUser(User $user): int {

		$now = new \DateTime();

		return Database::run(
			'UPDATE `' . self::TABLE_NAME . '`
			SET `revoked_at` = ?, `updated_at` = ?
			WHERE `user_id` = ?
			AND `revoked_at` IS NULL',
			[$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), (int)$user->id]
		);

	}

	/**
	 * Revoke every active bearer session owned by the given user and device hash.
	 */
	public static function revokeAllForUserAndDevice(User $user, string $deviceHash): int {

		$deviceHash = trim($deviceHash);

		if ('' === $deviceHash) {
			return 0;
		}

		$now = new \DateTime();

		return Database::run(
			'UPDATE `' . self::TABLE_NAME . '`
			SET `revoked_at` = ?, `updated_at` = ?
			WHERE `user_id` = ?
			AND `device_hash` = ?
			AND `revoked_at` IS NULL',
			[$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), (int)$user->id, $deviceHash]
		);

	}

	/**
	 * Revoke this bearer session.
	 */
	public function revoke(): bool {

		$this->revokedAt = new \DateTime();
		$this->updatedAt = new \DateTime();

		return $this->update('revokedAt', 'updatedAt');

	}

	/**
	 * Return the user associated with this bearer session.
	 */
	public function getUser(): ?User {

		$userClass = Application::getInstance()->userClass;
		$user = new $userClass((int)$this->userId);

		return ($user instanceof User and $user->isLoaded()) ? $user : null;

	}

	/**
	 * Return response metadata for the current token expiration.
	 */
	public function accessExpiresAtIso(): string {

		return $this->accessExpiresAt->format(\DateTimeInterface::ATOM);

	}

	/**
	 * Returns mapping [propertyName => db_field_name].
	 */
	protected static function getBinds(): array {

		return [
			'id'				=> 'id',
			'userId'			=> 'user_id',
			'accessTokenHash'	=> 'access_token_hash',
			'refreshTokenHash'	=> 'refresh_token_hash',
			'accessExpiresAt'	=> 'access_expires_at',
			'refreshExpiresAt'	=> 'refresh_expires_at',
			'deviceHash'		=> 'device_hash',
			'passwordVersionHash' => 'password_version_hash',
			'deviceName'		=> 'device_name',
			'ipAddress'			=> 'ip_address',
			'userAgent'			=> 'user_agent',
			'lastUsedAt'		=> 'last_used_at',
			'revokedAt'			=> 'revoked_at',
			'createdAt'			=> 'created_at',
			'updatedAt'			=> 'updated_at'
		];

	}

	/**
	 * Return a database-comparable current timestamp.
	 */
	private static function nowString(): string {

		return (new \DateTime())->format('Y-m-d H:i:s');

	}

	/**
	 * Trim and truncate optional metadata before storage.
	 */
	private static function truncateNullable(?string $value, int $length): ?string {

		$value = trim((string)$value);

		if ('' === $value) {
			return null;
		}

		return mb_substr($value, 0, $length);

	}

	/**
	 * Normalize optional SHA-256 hashes before persisting token metadata.
	 */
	private static function normalizeHash(?string $value): ?string {

		$value = strtolower(trim((string)$value));

		return preg_match('/^[a-f0-9]{64}$/', $value) ? $value : null;

	}

}
