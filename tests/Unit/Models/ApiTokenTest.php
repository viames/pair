<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Models\ApiToken;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/**
 * Covers mobile/API bearer token issuance and refresh rotation.
 */
class ApiTokenTest extends TestCase {

	/**
	 * Ensure framework constants used by ActiveRecord timestamps are available.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('BASE_TIMEZONE')) {
			define('BASE_TIMEZONE', 'UTC');
		}

	}

	/**
	 * Reset the database singleton after each test.
	 */
	protected function tearDown(): void {

		$this->resetDatabaseSingleton();

		parent::tearDown();

	}

	/**
	 * Verify token values are opaque and only hashes are stored.
	 */
	public function testIssueForUserStoresHashesAndOptionalRefreshToken(): void {

		$this->setSqliteDatabase([$this->apiTokensSchema()]);
		$user = $this->newLoadedUser(7);

		$passwordVersionHash = hash('sha256', 'password-version');
		$issued = ApiToken::issueForUser($user, true, 'iPhone 15', '203.0.113.10', 'PairMobileKit', 'device-a', $passwordVersionHash);
		$row = Database::load('SELECT * FROM `api_tokens` LIMIT 1', [], Database::OBJECT);

		$this->assertNotSame($issued['accessToken'], $row->access_token_hash);
		$this->assertSame(ApiToken::hashToken($issued['accessToken']), $row->access_token_hash);
		$this->assertSame(ApiToken::hashToken((string)$issued['refreshToken']), $row->refresh_token_hash);
		$this->assertSame('device-a', $row->device_hash);
		$this->assertSame($passwordVersionHash, $row->password_version_hash);
		$this->assertSame('iPhone 15', $row->device_name);
		$this->assertSame('203.0.113.10', $row->ip_address);

	}

	/**
	 * Verify refresh rotation invalidates the previous refresh token atomically.
	 */
	public function testRefreshRotatesTokensAndRejectsReplay(): void {

		$this->setSqliteDatabase([$this->apiTokensSchema()]);
		$user = $this->newLoadedUser(12);
		$issued = ApiToken::issueForUser($user, true);

		$rotated = ApiToken::refresh((string)$issued['refreshToken']);

		$this->assertNotNull($rotated);
		$this->assertNotSame($issued['accessToken'], $rotated['accessToken']);
		$this->assertNotSame($issued['refreshToken'], $rotated['refreshToken']);
		$this->assertNull(ApiToken::refresh((string)$issued['refreshToken']));
		$this->assertNull(ApiToken::getActiveByAccessToken($issued['accessToken']));
		$this->assertInstanceOf(ApiToken::class, ApiToken::getActiveByAccessToken($rotated['accessToken']));

	}

	/**
	 * Verify issuing a non-persistent token leaves refresh metadata empty.
	 */
	public function testIssueForUserCanSkipRefreshToken(): void {

		$this->setSqliteDatabase([$this->apiTokensSchema()]);
		$user = $this->newLoadedUser(13);

		$issued = ApiToken::issueForUser($user, false);
		$row = Database::load('SELECT * FROM `api_tokens` LIMIT 1', [], Database::OBJECT);

		$this->assertNull($issued['refreshToken']);
		$this->assertNull($row->refresh_token_hash);
		$this->assertNull($row->refresh_expires_at);

	}

	/**
	 * Verify account and device revocation methods only affect tokens owned by the target user.
	 */
	public function testRevokeByIdForUserAndRevokeAllForUserScopeByUser(): void {

		$this->setSqliteDatabase([$this->apiTokensSchema()]);
		$firstUser = $this->newLoadedUser(21);
		$secondUser = $this->newLoadedUser(22);
		$first = ApiToken::issueForUser($firstUser, true);
		$second = ApiToken::issueForUser($firstUser, true);
		$other = ApiToken::issueForUser($secondUser, true);
		$firstId = (int)Database::load('SELECT id FROM `api_tokens` WHERE `access_token_hash` = ?', [ApiToken::hashToken($first['accessToken'])], Database::OBJECT)->id;
		$secondId = (int)Database::load('SELECT id FROM `api_tokens` WHERE `access_token_hash` = ?', [ApiToken::hashToken($second['accessToken'])], Database::OBJECT)->id;
		$otherId = (int)Database::load('SELECT id FROM `api_tokens` WHERE `access_token_hash` = ?', [ApiToken::hashToken($other['accessToken'])], Database::OBJECT)->id;

		$this->assertFalse(ApiToken::revokeByIdForUser($otherId, $firstUser));
		$this->assertTrue(ApiToken::revokeByIdForUser($firstId, $firstUser));
		$this->assertNull(ApiToken::getActiveByAccessToken($first['accessToken']));
		$this->assertInstanceOf(ApiToken::class, ApiToken::getActiveByAccessToken($second['accessToken']));
		$this->assertInstanceOf(ApiToken::class, ApiToken::getActiveByAccessToken($other['accessToken']));

		$this->assertSame(1, ApiToken::revokeAllForUser($firstUser));
		$this->assertNull(ApiToken::getActiveByAccessToken($second['accessToken']));
		$this->assertInstanceOf(ApiToken::class, ApiToken::getActiveByAccessToken($other['accessToken']));
		$this->assertSame(0, ApiToken::revokeAllForUser($firstUser));
		$this->assertSame($otherId, (int)Database::load('SELECT id FROM `api_tokens` WHERE `access_token_hash` = ?', [ApiToken::hashToken($other['accessToken'])], Database::OBJECT)->id);
		$this->assertSame($secondId, (int)Database::load('SELECT id FROM `api_tokens` WHERE `access_token_hash` = ?', [ApiToken::hashToken($second['accessToken'])], Database::OBJECT)->id);

	}

	/**
	 * Verify device-scoped revocation only affects matching active bearer tokens.
	 */
	public function testRevokeAllForUserAndDeviceScopesByDeviceHash(): void {

		$this->setSqliteDatabase([$this->apiTokensSchema()]);
		$user = $this->newLoadedUser(31);
		$otherUser = $this->newLoadedUser(32);
		$first = ApiToken::issueForUser($user, true, null, null, null, 'device-a');
		$second = ApiToken::issueForUser($user, true, null, null, null, 'device-b');
		$other = ApiToken::issueForUser($otherUser, true, null, null, null, 'device-a');

		$this->assertSame(1, ApiToken::revokeAllForUserAndDevice($user, 'device-a'));
		$this->assertNull(ApiToken::getActiveByAccessToken($first['accessToken']));
		$this->assertInstanceOf(ApiToken::class, ApiToken::getActiveByAccessToken($second['accessToken']));
		$this->assertInstanceOf(ApiToken::class, ApiToken::getActiveByAccessToken($other['accessToken']));
		$this->assertSame(0, ApiToken::revokeAllForUserAndDevice($user, ''));
		$this->assertSame(0, ApiToken::revokeAllForUserAndDevice($user, 'device-a'));

	}

	/**
	 * Install a real Database singleton backed by in-memory SQLite.
	 *
	 * @param	list<string>	$statements	Schema or seed statements.
	 */
	private function setSqliteDatabase(array $statements): void {

		$reflection = new \ReflectionClass(Database::class);
		$database = $reflection->newInstanceWithoutConstructor();
		$handler = new \PDO('sqlite::memory:');
		$handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		foreach ($statements as $statement) {
			$handler->exec($statement);
		}

		$handlerProperty = new \ReflectionProperty(Database::class, 'handler');
		$handlerProperty->setValue($database, $handler);

		$instanceProperty = new \ReflectionProperty(Database::class, 'instance');
		$instanceProperty->setValue(null, $database);

	}

	/**
	 * Reset the shared database singleton.
	 */
	private function resetDatabaseSingleton(): void {

		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Create a lightweight loaded user with only an ID.
	 */
	private function newLoadedUser(int $id): User {

		$reflection = new \ReflectionClass(User::class);
		$user = $reflection->newInstanceWithoutConstructor();

		$this->setInaccessibleProperty($user, 'id', $id);
		$this->setPrivateProperty($user, ActiveRecord::class, 'loadedFromDb', true);

		return $user;

	}

	/**
	 * Assign a private property value through reflection.
	 */
	private function setPrivateProperty(object $object, string $class, string $name, mixed $value): void {

		$reflection = new \ReflectionProperty($class, $name);
		$reflection->setValue($object, $value);

	}

	/**
	 * Return the SQLite schema used by token tests.
	 */
	private function apiTokensSchema(): string {

		return 'CREATE TABLE api_tokens (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			access_token_hash TEXT NOT NULL UNIQUE,
			refresh_token_hash TEXT NULL UNIQUE,
			access_expires_at TEXT NOT NULL,
			refresh_expires_at TEXT NULL,
			device_hash TEXT NULL,
			password_version_hash TEXT NULL,
			device_name TEXT NULL,
			ip_address TEXT NULL,
			user_agent TEXT NULL,
			last_used_at TEXT NULL,
			revoked_at TEXT NULL,
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)';

	}

}
