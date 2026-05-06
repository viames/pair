<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;
use Pair\Tests\Support\FakeCrudDatabase;
use Pair\Tests\Support\TestCase;

/**
 * Covers ActiveRecord hydration behavior that must stay cheap and mutation-aware.
 */
class ActiveRecordTest extends TestCase {

	/**
	 * Reset the shared database singleton after focused ActiveRecord tests.
	 */
	protected function tearDown(): void {

		$this->resetDatabaseSingleton();

		parent::tearDown();

	}

	/**
	 * Verify database hydration does not mark fields as changed while explicit mutations still do.
	 */
	public function testHydrationSkipsUpdatedPropertiesButMutationsAreTracked(): void {

		$database = $this->setDatabaseInstance();

		$record = new ActiveRecordHydrationRecord((object)[
			'id' => 10,
			'name' => 'Original',
			'email' => 'original@example.test',
		]);

		$this->assertSame([], $this->updatedProperties($record));
		$this->assertSame(0, $database->virtualGeneratedChecks);

		$record->name = 'Changed';

		$this->assertSame(['name'], $this->updatedProperties($record));
		$this->assertSame(1, $database->virtualGeneratedChecks);

	}

	/**
	 * Install and return a lightweight fake database singleton for ActiveRecord setters.
	 */
	private function setDatabaseInstance(): FakeCrudDatabase {

		$reflection = new \ReflectionClass(FakeCrudDatabase::class);
		$database = $reflection->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, $database);

		return $database;

	}

	/**
	 * Reset the shared database singleton between tests so fake state cannot leak.
	 */
	private function resetDatabaseSingleton(): void {

		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Return the private updated property list through reflection.
	 *
	 * @return	array<int, string>
	 */
	private function updatedProperties(ActiveRecord $record): array {

		$property = new \ReflectionProperty(ActiveRecord::class, 'updatedProperties');

		return $property->getValue($record);

	}

}

/**
 * ActiveRecord test double with protected properties so magic setters are exercised.
 */
class ActiveRecordHydrationRecord extends ActiveRecord {

	/**
	 * Fake primary key definition.
	 */
	public const TABLE_KEY = 'id';

	/**
	 * Fake table name.
	 */
	public const TABLE_NAME = 'fake_records';

	/**
	 * Fake record identifier.
	 */
	protected int $id;

	/**
	 * Fake record name.
	 */
	protected string $name;

	/**
	 * Fake record email.
	 */
	protected ?string $email = null;

	/**
	 * Return a stable property-to-column map for the hydration test.
	 *
	 * @return	array<string, string>
	 */
	protected static function getBinds(): array {

		return [
			'id' => 'id',
			'name' => 'name',
			'email' => 'email',
		];

	}

}
