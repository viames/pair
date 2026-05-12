<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/**
 * Covers request-local database metadata cache invalidation.
 */
class DatabaseMetadataCacheTest extends TestCase {

	/**
	 * Verify read queries keep schema metadata caches intact.
	 */
	public function testReadQueriesKeepSchemaMetadataCache(): void {

		$database = Database::getInstance();

		$this->setInaccessibleProperty($database, 'definitions', ['users' => ['describe' => []]]);
		$this->setInaccessibleProperty($database, 'tableExistsCache', ['users' => true]);
		$this->setInaccessibleProperty($database, 'tableExistsCacheLoaded', true);
		$this->setInaccessibleProperty($database, 'tableExistsLookupCount', 3);

		$this->invokeInaccessibleMethod($database, 'clearSchemaMetadataCacheForQuery', ['SELECT * FROM `users`']);

		$this->assertSame(['users' => ['describe' => []]], $this->inaccessibleProperty($database, 'definitions'));
		$this->assertSame(['users' => true], $this->inaccessibleProperty($database, 'tableExistsCache'));
		$this->assertTrue($this->inaccessibleProperty($database, 'tableExistsCacheLoaded'));
		$this->assertSame(3, $this->inaccessibleProperty($database, 'tableExistsLookupCount'));

	}

	/**
	 * Verify schema-changing queries clear table metadata caches.
	 */
	public function testSchemaMutationQueriesClearSchemaMetadataCache(): void {

		$database = Database::getInstance();

		$this->setInaccessibleProperty($database, 'definitions', ['users' => ['describe' => []]]);
		$this->setInaccessibleProperty($database, 'tableExistsCache', ['users' => true]);
		$this->setInaccessibleProperty($database, 'tableExistsCacheLoaded', true);
		$this->setInaccessibleProperty($database, 'tableExistsLookupCount', 3);

		$this->invokeInaccessibleMethod($database, 'clearSchemaMetadataCacheForQuery', ['ALTER TABLE `users` ADD COLUMN `name` varchar(255)']);

		$this->assertSame([], $this->inaccessibleProperty($database, 'definitions'));
		$this->assertSame([], $this->inaccessibleProperty($database, 'tableExistsCache'));
		$this->assertFalse($this->inaccessibleProperty($database, 'tableExistsCacheLoaded'));
		$this->assertSame(0, $this->inaccessibleProperty($database, 'tableExistsLookupCount'));

	}

	/**
	 * Verify common DDL spellings are recognized as schema mutations.
	 */
	public function testSchemaMutationDetectionRecognizesCommonDdl(): void {

		$database = Database::getInstance();

		$this->assertTrue($this->invokeInaccessibleMethod($database, 'isSchemaMutationQuery', ['CREATE TABLE `users` (`id` int)']));
		$this->assertTrue($this->invokeInaccessibleMethod($database, 'isSchemaMutationQuery', ['DROP TEMPORARY TABLE `users`']));
		$this->assertTrue($this->invokeInaccessibleMethod($database, 'isSchemaMutationQuery', ['TRUNCATE `sessions`']));
		$this->assertTrue($this->invokeInaccessibleMethod($database, 'isSchemaMutationQuery', ['RENAME TABLE `old` TO `new`']));
		$this->assertFalse($this->invokeInaccessibleMethod($database, 'isSchemaMutationQuery', ['SELECT VERSION()']));

	}

	/**
	 * Return a private property value through reflection for focused cache assertions.
	 */
	private function inaccessibleProperty(object $object, string $name): mixed {

		$reflection = new \ReflectionProperty($object, $name);

		return $reflection->getValue($object);

	}

}
