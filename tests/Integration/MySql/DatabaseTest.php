<?php

declare(strict_types=1);

namespace Pair\Tests\Integration\MySql;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Orm\Database;
use Pair\Orm\Query;
use Pair\Tests\Support\TestCase;

/** Exercises real MySQL connections using only a separately provisioned test schema. */
final class DatabaseTest extends TestCase {

	/** Tables successfully created by this test; pre-existing tables are never dropped. */
	private array $tables = [];

	/** Open the real framework connection under an account with schema-level grants only. */
	protected function setUp(): void {
		parent::setUp();
		$name = getenv('PAIR_TEST_DB_NAME') ?: '';
		if (!preg_match('/^pair_test_[a-zA-Z0-9_]+$/D', $name)) {
			$this->fail('PAIR_TEST_DB_NAME must identify a dedicated schema beginning with pair_test_.');
		}
		foreach (['DB_HOST' => '127.0.0.1', 'DB_USER' => 'pair_test', 'DB_PASS' => ''] as $key => $default) {
			$value = getenv('PAIR_TEST_' . $key);
			$_ENV[$key] = false === $value ? $default : $value;
		}
		$_ENV['DB_NAME'] = $name;
		(new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
		Database::getInstance()->connect();

		// No IF NOT EXISTS: an unrelated or abandoned table must cause setup to fail safely.
		Database::run('CREATE TABLE pair_mysql_parents (id INT PRIMARY KEY) ENGINE=InnoDB');
		$this->tables[] = 'pair_mysql_parents';
		Database::run('CREATE TABLE pair_mysql_records (
			id INT PRIMARY KEY AUTO_INCREMENT,
			parent_id INT NULL,
			name VARCHAR(191) NOT NULL UNIQUE,
			payload JSON NULL,
			name_length INT GENERATED ALWAYS AS (CHAR_LENGTH(name)) VIRTUAL,
			CONSTRAINT pair_mysql_parent_fk FOREIGN KEY (parent_id) REFERENCES pair_mysql_parents(id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
		$this->tables[] = 'pair_mysql_records';
	}

	/** Remove only owned fixtures and release the database singleton, including after failures. */
	protected function tearDown(): void {
		try {
			foreach (array_reverse($this->tables) as $table) {
				Database::run('DROP TABLE `' . $table . '`');
			}
		} finally {
			(new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
			parent::tearDown();
		}
	}

	/** Both fresh and persistent connections negotiate utf8mb4 before any application helper. */
	public function testConnectionCharset(): void {
		$db = Database::getInstance();
		$expected = getenv('PAIR_TEST_MYSQL_VERSION');
		$version = $db->getMysqlVersion();
		$this->assertIsString($version);
		if (false !== $expected and '' !== $expected) {
			$this->assertStringStartsWith($expected . '.', $version);
		}
		$this->assertSame($version, Database::value('SELECT VERSION()'));
		$this->assertCharset();
		$db->disconnect();
		$this->assertFalse($db->isConnected());
		$db->connectPersistent();
		$this->assertCharset();
		$this->assertSame($version, $db->getMysqlVersion());
		Database::run('SET NAMES latin1');
		$db->disconnect();
		$db->connectPersistent();
		$this->assertCharset();
	}

	/** The legacy helper repairs session settings without touching server or schema defaults. */
	public function testLegacyCharset(): void {
		$defaults = Database::load('SELECT @@character_set_server AS server_charset,
			@@collation_server AS server_collation, @@character_set_database AS db_charset,
			@@collation_database AS db_collation', [], Database::DICTIONARY);
		Database::run('SET NAMES latin1');
		Database::getInstance()->setUtf8unicode();
		Database::getInstance()->setUtf8unicode();
		$this->assertCharset();
		$this->assertSame($defaults, Database::load('SELECT @@character_set_server AS server_charset,
			@@collation_server AS server_collation, @@character_set_database AS db_charset,
			@@collation_database AS db_collation', [], Database::DICTIONARY));
	}

	/** Prepared and quoted writes preserve four-byte text, JSON, generated columns and found rows. */
	public function testUnicodeCrud(): void {
		$db = Database::getInstance();
		$name = "Caffè 🐬 𐍈 O'Reilly \\ end";
		$payload = json_encode(['text' => $name], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		$record = (object)['name' => $name, 'payload' => $payload, 'name_length' => 999];
		$this->assertTrue($db->insertObject('pair_mysql_records', $record));
		$id = (int)$db->getLastInsertId();
		$row = Query::table('pair_mysql_records')->where('id', $id)->first();
		$this->assertSame($name, $row->name);
		$this->assertSame(mb_strlen($name), (int)$row->name_length);
		$this->assertSame(['text' => $name], json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR));
		$this->assertSame($name, Database::value('SELECT ' . $db->quote($name)));
		$this->assertSame(1, Query::table('pair_mysql_records')->where('name', $name)->count());

		// FOUND_ROWS must still report one matched row for an unchanged value.
		$this->assertSame(1, Database::run('UPDATE pair_mysql_records SET name = ? WHERE id = ?', [$name, $id]));
		$changed = (object)['name' => 'Aggiornato 🚀', 'name_length' => 123];
		$this->assertSame(1, $db->updateObject('pair_mysql_records', $changed, (object)['id' => $id]));
		$rows = iterator_to_array(Database::iterateDictionary('SELECT name FROM pair_mysql_records WHERE id = ?', [$id]));
		$this->assertSame([['name' => 'Aggiornato 🚀']], $rows);
		$this->assertSame(1, Database::run('DELETE FROM pair_mysql_records WHERE id = ?', [$id]));
		$this->assertSame(0, Query::table('pair_mysql_records')->count());
	}

	/** Real metadata reads remain usable after DDL and describe foreign keys in both directions. */
	public function testSchemaMetadata(): void {
		$db = Database::getInstance();
		$this->assertTrue($db->tableExists('pair_mysql_records'));
		$this->assertTrue($db->isAutoIncrement('pair_mysql_records'));
		$this->assertTrue($db->isVirtualGenerated('pair_mysql_records', 'name_length'));
		$this->assertNull($db->describeColumn('pair_mysql_records', 'added'));
		$foreign = $db->getForeignKeys('pair_mysql_records');
		$this->assertCount(1, $foreign);
		$this->assertSame('parent_id', $foreign[0]->COLUMN_NAME);
		$this->assertSame('pair_mysql_parents', $foreign[0]->REFERENCED_TABLE_NAME);
		$inverse = $db->getInverseForeignKeys('pair_mysql_parents');
		$this->assertCount(1, $inverse);
		$this->assertSame('pair_mysql_records', $inverse[0]->TABLE_NAME);
		Database::run('ALTER TABLE pair_mysql_records ADD added INT NULL');
		$this->assertSame('added', $db->describeColumn('pair_mysql_records', 'added')->Field);
	}

	/** Nested failures roll back to savepoints, and outer failures roll back committed inner work. */
	public function testTransactions(): void {
		$result = Database::transaction(function (): string {
			Database::run('INSERT INTO pair_mysql_parents VALUES (1)');
			try {
				Database::transaction(function (): void {
					Database::run('INSERT INTO pair_mysql_parents VALUES (2)');
					throw new \RuntimeException('Inner rollback');
				});
			} catch (\RuntimeException $error) {
				$this->assertSame('Inner rollback', $error->getMessage());
			}
			Database::transaction(static fn(): int => Database::run('INSERT INTO pair_mysql_parents VALUES (3)'));
			return 'committed';
		});
		$this->assertSame('committed', $result);
		$this->assertSame([1, 3], array_map('intval', Database::load('SELECT id FROM pair_mysql_parents ORDER BY id', [], Database::RESULT_LIST)));
		try {
			Database::transaction(function (): void {
				Database::transaction(static fn(): int => Database::run('INSERT INTO pair_mysql_parents VALUES (4)'));
				throw new \RuntimeException('Outer rollback');
			});
		} catch (\RuntimeException $error) {
			$this->assertSame('Outer rollback', $error->getMessage());
		}
		$this->assertSame(2, Query::table('pair_mysql_parents')->count());
	}

	/** MySQL driver codes map to the existing framework errors on both supported server lines. */
	public function testConstraintErrors(): void {
		Database::run('INSERT INTO pair_mysql_parents VALUES (1)');
		foreach ([
			['INSERT INTO pair_mysql_parents VALUES (1)', ErrorCodes::DUPLICATE_ENTRY],
			["INSERT INTO pair_mysql_records (parent_id,name) VALUES (999,'invalid')", ErrorCodes::FOREIGN_KEY_CONSTRAINT],
		] as [$sql, $code]) {
			try {
				Database::run($sql);
				$this->fail('Expected a constraint exception.');
			} catch (PairException $error) {
				$this->assertSame($code, $error->getCode());
				$this->assertInstanceOf(\PDOException::class, $error->getPrevious());
			}
		}
	}

	/** Inspect the negotiated session, rather than merely matching a constructed DSN. */
	private function assertCharset(): void {
		$row = Database::load('SELECT @@character_set_client AS client_charset,
			@@character_set_connection AS connection_charset, @@character_set_results AS result_charset,
			@@collation_connection AS connection_collation', [], Database::OBJECT);
		$this->assertSame('utf8mb4', $row->client_charset);
		$this->assertSame('utf8mb4', $row->connection_charset);
		$this->assertSame('utf8mb4', $row->result_charset);
		$this->assertSame('utf8mb4_unicode_ci', $row->connection_collation);
	}
}
