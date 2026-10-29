<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Cache\SchemaCache;
use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/** Verifies description reuse, invalidation and connection-local bypass. */
final class DatabaseDescriptionTest extends TestCase {

	/** Isolate the singleton and use a synthetic PDO statement instead of a database. */
	private function database(int $queries): Database {
		$instance = new \ReflectionProperty(Database::class, 'instance');
		$instance->setValue(null, null);
		$database = Database::getInstance();
		$statement = $this->createStub(\PDOStatement::class);
		$statement->method('execute')->willReturn(true);
		$statement->method('fetchAll')->willReturn([(object)['Field' => 'fresh', 'Type' => 'int', 'Null' => 'YES', 'Default' => null]]);
		$pdo = $this->createMock(\PDO::class);
		$pdo->expects($this->exactly($queries))->method('prepare')->with('DESCRIBE `fixture`')->willReturn($statement);
		$this->setInaccessibleProperty($database, 'handler', $pdo);
		return $database;
	}

	/** Shared descriptions survive the request-local reset and preserve column types and defaults. */
	public function testSharedDescription(): void {
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'description-fixture';
		$database = $this->database(1);
		$first = $database->describeTable('fixture');
		$this->invokeInaccessibleMethod($database, 'clearSchemaMetadataCache');
		$this->assertEquals($first, $database->describeTable('fixture'));
		$this->assertSame('int', $database->describeColumn('fixture', 'fresh')->Type);
		$this->assertNull($database->describeColumn('fixture', 'missing'));
	}

	/** Without an explicit version every request resolves its own description. */
	public function testDisabledDescription(): void {
		$database = $this->database(2);
		$database->describeTable('fixture');
		$this->invokeInaccessibleMethod($database, 'clearSchemaMetadataCache');
		$this->assertCount(1, $database->describeTable('fixture'));
	}

	/** Successful DDL invalidates both description layers. */
	public function testDescriptionInvalidation(): void {
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'description-fixture';
		$database = $this->database(2);
		$database->describeTable('fixture');
		$this->invokeInaccessibleMethod($database, 'clearSchemaMetadataCacheForQuery', ['ALTER TABLE fixture ADD fresh INT']);
		$this->assertCount(1, $database->describeTable('fixture'));
	}

	/** A schema switch or temporary table must never consume another connection's shared columns. */
	public function testDescriptionBypass(): void {
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'description-fixture';
		SchemaCache::remember('fixture:describe', static fn(): array => [(object)['Field' => 'stale']]);
		$database = $this->database(2);
		$this->invokeInaccessibleMethod($database, 'clearSchemaMetadataCacheForQuery', ['USE other_fixture']);
		$this->assertSame('fresh', $database->describeTable('fixture')[0]->Field);
		$this->invokeInaccessibleMethod($database, 'clearSchemaMetadataCacheForQuery', ['CREATE TEMPORARY TABLE fixture (fresh INT)']);
		$this->assertSame('fresh', $database->describeTable('fixture')[0]->Field);
	}

	/** Avoid leaking the synthetic PDO connection to unrelated tests. */
	protected function tearDown(): void {
		(new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
		parent::tearDown();
	}
}
