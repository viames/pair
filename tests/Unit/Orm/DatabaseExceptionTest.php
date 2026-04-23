<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/**
 * Covers database exception mapping from PDO failures to framework exceptions.
 */
class DatabaseExceptionTest extends TestCase {

	/**
	 * Reset framework singletons that exception tests replace with focused doubles.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->resetApplicationSingleton();
		$this->resetDatabaseSingleton();
		$this->resetLoggerSingleton();
		$this->disableLoggerForExceptionAssertions();

	}

	/**
	 * Restore framework singletons after each focused exception mapping test.
	 */
	protected function tearDown(): void {

		$this->resetApplicationSingleton();
		$this->resetDatabaseSingleton();
		$this->resetLoggerSingleton();

		parent::tearDown();

	}

	/**
	 * Verify duplicate key PDO failures keep their specific Pair error code.
	 */
	public function testRunMapsDuplicateEntryToPairException(): void {

		$previous = DatabaseExceptionPdoException::mysql(
			'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry',
			'23000',
			1062
		);

		$this->setDatabaseHandler(new DatabaseExceptionThrowingPdo($previous));

		try {
			Database::run('INSERT INTO users (`email`) VALUES (?)', ['demo@example.test']);
			$this->fail('Expected duplicate entry PDO failure to be mapped.');
		} catch (PairException $e) {
			$this->assertNotInstanceOf(CriticalException::class, $e);
			$this->assertSame(ErrorCodes::DUPLICATE_ENTRY, $e->getCode());
			$this->assertSame($previous, $e->getPrevious());
		}

	}

	/**
	 * Verify missing table failures escalate as critical framework exceptions.
	 */
	public function testLoadMapsMissingTableToCriticalException(): void {

		$this->enableHeadlessApplication();

		$previous = DatabaseExceptionPdoException::mysql(
			"SQLSTATE[42S02]: Base table or view not found: 1146 Table 'app.missing' doesn't exist",
			'42S02',
			1146
		);

		$this->setDatabaseHandler(new DatabaseExceptionThrowingPdo($previous));

		try {
			Database::load('SELECT * FROM `missing`');
			$this->fail('Expected missing table PDO failure to be mapped.');
		} catch (CriticalException $e) {
			$this->assertSame(ErrorCodes::MISSING_DB_TABLE, $e->getCode());
			$this->assertSame($previous, $e->getPrevious());
			$this->assertSame($previous->getMessage(), $e->getMessage());
		}

	}

	/**
	 * Verify broad HY000 query errors do not become critical without a critical driver code.
	 */
	public function testGenericHy000QueryFailureRemainsRecoverable(): void {

		$previous = DatabaseExceptionPdoException::mysql(
			"SQLSTATE[HY000]: General error: 1364 Field 'name' doesn't have a default value",
			'HY000',
			1364
		);

		$this->setDatabaseHandler(new DatabaseExceptionThrowingPdo($previous));

		try {
			Database::run('INSERT INTO users (`email`) VALUES (?)', ['demo@example.test']);
			$this->fail('Expected generic HY000 PDO failure to be mapped.');
		} catch (PairException $e) {
			$this->assertNotInstanceOf(CriticalException::class, $e);
			$this->assertSame(ErrorCodes::DB_QUERY_FAILED, $e->getCode());
			$this->assertSame($previous, $e->getPrevious());
		}

	}

	/**
	 * Verify PairException escalation preserves throwable metadata for critical database codes.
	 */
	public function testCriticalEscalationPreservesThrowableMetadata(): void {

		$this->enableHeadlessApplication();

		$previous = new \RuntimeException('Original database failure');

		try {
			throw new PairException('Database is missing', ErrorCodes::MISSING_DB, $previous);
		} catch (CriticalException $e) {
			$this->assertSame('Database is missing', $e->getMessage());
			$this->assertSame(ErrorCodes::MISSING_DB, $e->getCode());
			$this->assertSame($previous, $e->getPrevious());
		}

	}

	/**
	 * Install a focused Application singleton that prevents CriticalException from exiting.
	 */
	private function enableHeadlessApplication(): void {

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();

		$headless = new \ReflectionProperty(Application::class, 'headless');
		$headless->setValue($app, true);

		$instance = new \ReflectionProperty(Application::class, 'instance');
		$instance->setValue(null, $app);

	}

	/**
	 * Disable logger side effects while asserting exception object metadata.
	 */
	private function disableLoggerForExceptionAssertions(): void {

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		Logger::getInstance()->disable();

	}

	/**
	 * Install a PDO handler double inside the Database singleton.
	 */
	private function setDatabaseHandler(\PDO $handler): void {

		$reflection = new \ReflectionClass(Database::class);
		$database = $reflection->newInstanceWithoutConstructor();

		$handlerProperty = new \ReflectionProperty(Database::class, 'handler');
		$handlerProperty->setValue($database, $handler);

		$instance = new \ReflectionProperty(Database::class, 'instance');
		$instance->setValue(null, $database);

	}

	/**
	 * Clear the focused Application singleton.
	 */
	private function resetApplicationSingleton(): void {

		$instance = new \ReflectionProperty(Application::class, 'instance');
		$instance->setValue(null, null);

	}

	/**
	 * Clear the focused Database singleton.
	 */
	private function resetDatabaseSingleton(): void {

		$instance = new \ReflectionProperty(Database::class, 'instance');
		$instance->setValue(null, null);

	}

	/**
	 * Clear the focused Logger singleton.
	 */
	private function resetLoggerSingleton(): void {

		$instance = new \ReflectionProperty(Logger::class, 'instance');
		$instance->setValue(null, null);

	}

}

/**
 * PDO double that throws a configured exception when Database prepares a statement.
 */
final class DatabaseExceptionThrowingPdo extends \PDO {

	/**
	 * PDO exception to throw from prepare().
	 */
	private \PDOException $exception;

	/**
	 * Store the exception that should be raised by prepare().
	 */
	public function __construct(\PDOException $exception) {

		$this->exception = $exception;

	}

	/**
	 * Throw the configured PDO exception instead of preparing a statement.
	 */
	public function prepare(string $query, array $options = []): \PDOStatement|false {

		throw $this->exception;

	}

}

/**
 * Test PDOException that exposes MySQL-style SQLSTATE and driver codes.
 */
final class DatabaseExceptionPdoException extends \PDOException {

	/**
	 * Build a PDOException shaped like the MySQL driver exceptions raised by PDO.
	 */
	public static function mysql(string $message, string $sqlState, int $driverCode): self {

		$exception = new self($message);
		$exception->code = $sqlState;
		$exception->errorInfo = [$sqlState, $driverCode, $message];

		return $exception;

	}

}
