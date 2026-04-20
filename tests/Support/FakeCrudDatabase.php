<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Orm\Database;

/**
 * Lightweight database double used to exercise ActiveRecord create/update flows without a real connection.
 */
class FakeCrudDatabase extends Database {

	/**
	 * Controls whether the fake table should behave as auto-incrementing.
	 */
	public bool $autoIncrement = true;

	/**
	 * Last insert identifier returned after a fake insert.
	 */
	public string $lastInsertId = '1';

	/**
	 * Insert operations captured during the current test.
	 *
	 * @var	array<int, array{table: string, object: \stdClass, encryptables: array|null}>
	 */
	public array $insertedObjects = [];

	/**
	 * Update operations captured during the current test.
	 *
	 * @var	array<int, array{table: string, object: \stdClass, key: \stdClass, encryptables: array|null}>
	 */
	public array $updatedObjects = [];

	/**
	 * Install an in-memory SQLite database after executing the provided schema or seed statements.
	 *
	 * @param	array<int, string>	$statements	SQL statements executed before exposing the PDO handler to Database::load().
	 */
	public function useSqliteMemoryDatabase(array $statements): void {

		$handler = new \PDO('sqlite::memory:');
		$handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		foreach ($statements as $statement) {
			$handler->exec($statement);
		}

		$this->setPdoHandler($handler);

	}

	/**
	 * Install an in-memory SQLite handler seeded with rows so Database::load()
	 * can exercise the real count/list query path without MySQL.
	 *
	 * @param	array<int, array{id: int, name: string, email: string}>	$rows	Rows inserted into the fake table.
	 */
	public function useSqliteMemoryTable(array $rows): void {

		$handler = new \PDO('sqlite::memory:');
		$handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$handler->exec('CREATE TABLE fake_records (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');

		$statement = $handler->prepare('INSERT INTO fake_records (id, name, email) VALUES (?, ?, ?)');

		foreach ($rows as $row) {
			$statement->execute([$row['id'], $row['name'], $row['email']]);
		}

		$this->setPdoHandler($handler);

	}

	/**
	 * Inject a real PDO handler so the framework keeps using Database::load() unchanged.
	 */
	private function setPdoHandler(\PDO $handler): void {

		$property = new \ReflectionProperty(Database::class, 'handler');
		$property->setValue($this, $handler);

	}

	/**
	 * Return the configured auto-increment behavior for the fake table.
	 */
	public function isAutoIncrement(string $tableName): bool {

		return $this->autoIncrement;

	}

	/**
	 * Pretend no column is virtual-generated so ActiveRecord setters stay purely in-memory during tests.
	 */
	public function isVirtualGenerated(string $tableName, string $columnName): bool {

		return false;

	}

	/**
	 * Return a permissive in-memory column description so ActiveRecord can prepare fake insert/update payloads.
	 */
	public function describeColumn(string $tableName, string $column): ?\stdClass {

		$description = new \stdClass();
		$description->Field = $column;
		$description->Type = 'varchar(255)';
		$description->Null = 'YES';
		$description->Key = '';
		$description->Default = null;
		$description->Extra = '';

		return $description;

	}

	/**
	 * Capture one fake insert operation without touching a real database connection.
	 */
	public function insertObject(string $table, \stdClass $object, ?array $encryptables = []): bool {

		$this->insertedObjects[] = [
			'table' => $table,
			'object' => clone $object,
			'encryptables' => $encryptables,
		];

		return true;

	}

	/**
	 * Return the configured fake insert identifier.
	 */
	public function getLastInsertId(): string|bool {

		return $this->lastInsertId;

	}

	/**
	 * Capture one fake update operation without touching a real database connection.
	 */
	public function updateObject(string $table, \stdClass &$object, \stdClass $key, ?array $encryptables = []): int {

		$this->updatedObjects[] = [
			'table' => $table,
			'object' => clone $object,
			'key' => clone $key,
			'encryptables' => $encryptables,
		];

		return 1;

	}

}
