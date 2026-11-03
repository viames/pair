<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Core\Application;
use Pair\Models\Session;
use Pair\Orm\Database;
use Pair\Tests\Support\FakeCrudDatabase;
use Pair\Tests\Support\TestCase;

/** Verifies activity persistence at DATETIME precision without changing session validity. */
final class SessionActivityTest extends TestCase {

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

	/** Different seconds are persisted, while repeated touches retain the newest in-memory time. */
	public function testActivityPrecision(): void {
		$session = new Session();
		(new \ReflectionProperty(Session::class, 'id'))->setValue($session, 'synthetic-session');
		$touch = new \ReflectionMethod(Session::class, 'extendTimeoutAt');
		$first = new \DateTime('2026-01-01 10:00:00.100000', new \DateTimeZone('UTC'));
		$touch->invoke($session, $first);
		$this->assertCount(1, $this->database->updatedObjects);
		$repeated = new \DateTime('2026-01-01 10:00:00.900000', new \DateTimeZone('UTC'));
		for ($i = 0; $i < 20; $i++) { $touch->invoke($session, $repeated); }
		$this->assertCount(1, $this->database->updatedObjects);
		$this->assertSame($repeated, (new \ReflectionProperty(Session::class, 'startTime'))->getValue($session));
		$touch->invoke($session, new \DateTime('2026-01-01 10:00:01', new \DateTimeZone('UTC')));
		$this->assertCount(2, $this->database->updatedObjects);
		$this->assertSame('sessions', $this->database->updatedObjects[1]['table']);
		$this->assertSame('synthetic-session', $this->database->updatedObjects[1]['key']->id);
	}

	/** Missing and stale activity still expire; a genuine touch renews the original lifetime. */
	public function testValidityIsPreserved(): void {
		$session = new Session();
		(new \ReflectionProperty(Session::class, 'id'))->setValue($session, 'synthetic-session');
		$this->assertTrue($session->isExpired(1));
		(new \ReflectionProperty(Session::class, 'startTime'))->setValue($session, new \DateTime('-10 minutes'));
		$this->assertTrue($session->isExpired(1));
		$session->extendTimeout();
		$this->assertFalse($session->isExpired(1));
		$this->assertCount(1, $this->database->updatedObjects);
	}

	/** The same wall-clock text in another timezone is a different instant and must be written. */
	public function testTimezoneChange(): void {
		$session = new Session();
		(new \ReflectionProperty(Session::class, 'id'))->setValue($session, 'synthetic-session');
		$touch = new \ReflectionMethod(Session::class, 'extendTimeoutAt');
		$touch->invoke($session, new \DateTime('2026-01-01 10:00:00', new \DateTimeZone('UTC')));
		$touch->invoke($session, new \DateTime('2026-01-01 10:00:00', new \DateTimeZone('Europe/Rome')));
		$this->assertCount(2, $this->database->updatedObjects);
	}

}
