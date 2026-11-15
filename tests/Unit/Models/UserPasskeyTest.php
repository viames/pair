<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Core\Application;
use Pair\Models\UserPasskey;
use Pair\Orm\Database;
use Pair\Tests\Support\FakeCrudDatabase;
use Pair\Tests\Support\TestCase;

/** Verifies the database writes used by passkey assertion and revocation flows. */
final class UserPasskeyTest extends TestCase {

	private FakeCrudDatabase $database;
	private mixed $oldApplication;
	private mixed $oldDatabase;
	private bool $oldLoggerEnabled;

	/** Installs isolated framework doubles without opening a network connection. */
	protected function setUp(): void {
		parent::setUp();
		if (!defined('BASE_TIMEZONE')) { define('BASE_TIMEZONE', 'UTC'); }
		$logger = \Pair\Core\Logger::getInstance();
		$this->oldLoggerEnabled = (new \ReflectionProperty($logger, 'enabled'))->getValue($logger);
		$logger->disable();
		$application = new \ReflectionProperty(Application::class, 'instance');
		$this->oldApplication = $application->isInitialized() ? $application->getValue() : null;
		$application->setValue(null, (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor());
		$database = new \ReflectionProperty(Database::class, 'instance');
		$this->oldDatabase = $database->isInitialized() ? $database->getValue() : null;
		$this->database = (new \ReflectionClass(FakeCrudDatabase::class))->newInstanceWithoutConstructor();
		$database->setValue(null, $this->database);
	}

	/** Restores singletons so later authentication tests keep their own environment. */
	protected function tearDown(): void {
		(new \ReflectionProperty(\Pair\Core\Logger::class, 'enabled'))->setValue(\Pair\Core\Logger::getInstance(), $this->oldLoggerEnabled);
		(new \ReflectionProperty(Application::class, 'instance'))->setValue(null, $this->oldApplication);
		(new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $this->oldDatabase);
		parent::tearDown();
	}

	/** All assertion metadata must reach the database, including the signature counter. */
	public function testUsagePersists(): void {
		$key = $this->key(4);
		$this->assertTrue($key->markUsed(7));
		$write = $this->database->updatedObjects[0];
		$this->assertSame('user_passkeys', $write['table']);
		$this->assertSame(11, $write['key']->id);
		$this->assertSame(7, $write['object']->sign_count);
		$this->assertNotEmpty($write['object']->last_used_at);
		$this->assertNotEmpty($write['object']->updated_at);
	}

	/** Older or zero counters cannot lower a previously stored signature counter. */
	public function testCounterNeverDecreases(): void {
		$key = $this->key(7);
		$key->markUsed(0);
		$this->assertSame(7, $this->database->updatedObjects[0]['object']->sign_count);
		$key->markUsed(3);
		$this->assertSame(7, $this->database->updatedObjects[1]['object']->sign_count);
	}

	/** Revocation and its lifecycle timestamp are persisted together. */
	public function testRevocationPersists(): void {
		$key = $this->key(7);
		$this->assertTrue($key->revoke());
		$write = $this->database->updatedObjects[0]['object'];
		$this->assertNotEmpty($write->revoked_at);
		$this->assertNotEmpty($write->updated_at);
		$this->assertTrue($key->isRevoked());
	}

	/** Create a credential without a database read or real authentication material. */
	private function key(int $counter): UserPasskey {
		$key = new UserPasskey();
		$key->id = 11;
		$key->signCount = $counter;
		return $key;
	}

}
