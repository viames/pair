<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/** Exercises transaction ownership and savepoints with a real isolated PDO connection. */
final class DatabaseTransactionTest extends TestCase {

	private \PDO $connection;

	/** Installs an in-memory handler without application data or network access. */
	protected function setUp(): void {
		parent::setUp();
		$this->connection = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		$this->connection->exec('CREATE TABLE entries (id INTEGER PRIMARY KEY)');
		$database = (new \ReflectionClass(Database::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Database::class, 'handler'))->setValue($database, $this->connection);
		(new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $database);
	}

	/** Restores the singleton so other tests cannot inherit this connection. */
	protected function tearDown(): void {
		(new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
		parent::tearDown();
	}

	/** A successful owned transaction commits and returns the callback result. */
	public function testOwnedCommit(): void {
		$result = Database::transaction(function (): string {
			$this->connection->exec('INSERT INTO entries VALUES (1)');
			return 'committed';
		});
		$this->assertSame('committed', $result);
		$this->assertFalse($this->connection->inTransaction());
		$this->assertSame(1, (int)$this->connection->query('SELECT COUNT(*) FROM entries')->fetchColumn());
	}

	/** Any Throwable rolls back an owned transaction, including PHP errors. */
	public function testOwnedRollback(): void {
		try {
			Database::transaction(function (): void {
				$this->connection->exec('INSERT INTO entries VALUES (1)');
				throw new \Error('synthetic');
			});
			$this->fail('Expected failure');
		} catch (\Error $error) {
			$this->assertSame('synthetic', $error->getMessage());
		}
		$this->assertFalse($this->connection->inTransaction());
		$this->assertSame(0, (int)$this->connection->query('SELECT COUNT(*) FROM entries')->fetchColumn());
	}

	/** A nested failure discards only its writes; nested success never commits the caller. */
	public function testNestedSavepoints(): void {
		$this->connection->beginTransaction();
		$this->connection->exec('INSERT INTO entries VALUES (1)');
		try {
			Database::transaction(function (): void {
				$this->connection->exec('INSERT INTO entries VALUES (2)');
				throw new \RuntimeException('synthetic');
			});
			$this->fail('Expected failure');
		} catch (\RuntimeException $error) {
			$this->assertSame('synthetic', $error->getMessage());
		}
		$this->assertTrue($this->connection->inTransaction());
		$this->assertSame([1], array_map('intval', $this->connection->query('SELECT id FROM entries')->fetchAll(\PDO::FETCH_COLUMN)));
		Database::transaction(fn() => $this->connection->exec('INSERT INTO entries VALUES (3)'));
		$this->assertTrue($this->connection->inTransaction());
		$this->connection->rollBack();
		$this->assertSame(0, (int)$this->connection->query('SELECT COUNT(*) FROM entries')->fetchColumn());
	}
}
