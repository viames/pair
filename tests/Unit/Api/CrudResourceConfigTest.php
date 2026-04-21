<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\CrudResourceConfig;
use Pair\Tests\Support\FakeCrudReadModel;
use Pair\Tests\Support\FakeCrudResource;
use Pair\Tests\Support\TestCase;

/**
 * Covers the typed CRUD resource configuration value object.
 */
class CrudResourceConfigTest extends TestCase {

	/**
	 * Verify default values match the legacy array contract used by CRUD resources.
	 */
	public function testFromArrayAppliesLegacyDefaults(): void {

		$config = CrudResourceConfig::fromArray([]);

		$this->assertNull($config->readModel());
		$this->assertNull($config->resource());
		$this->assertSame([], $config->searchable());
		$this->assertSame([], $config->sortable());
		$this->assertSame([], $config->filterable());
		$this->assertSame([], $config->includes());
		$this->assertSame([], $config->includeReadModels());
		$this->assertSame([], $config->includeResources());
		$this->assertSame(20, $config->perPage());
		$this->assertSame(100, $config->maxPerPage());
		$this->assertSame(['create' => [], 'update' => []], $config->rules());
		$this->assertNull($config->defaultSort());

	}

	/**
	 * Verify explicit overrides are normalized and exposed through typed accessors.
	 */
	public function testFromArrayNormalizesOverrides(): void {

		$config = CrudResourceConfig::fromArray([
			'readModel' => FakeCrudReadModel::class,
			'resource' => FakeCrudResource::class,
			'searchable' => ['name'],
			'sortable' => ['createdAt'],
			'filterable' => ['status'],
			'includes' => ['group'],
			'includeReadModels' => ['group' => FakeCrudReadModel::class],
			'includeResources' => ['group' => FakeCrudResource::class],
			'perPage' => 15,
			'maxPerPage' => 30,
			'rules' => [
				'create' => ['name' => 'required|string'],
				'update' => ['name' => 'string'],
			],
			'defaultSort' => '-createdAt',
		]);

		$this->assertSame(FakeCrudReadModel::class, $config->readModel());
		$this->assertSame(FakeCrudResource::class, $config->resource());
		$this->assertSame(['name'], $config->searchable());
		$this->assertSame(['createdAt'], $config->sortable());
		$this->assertSame(['status'], $config->filterable());
		$this->assertSame(['group'], $config->includes());
		$this->assertSame(['group' => FakeCrudReadModel::class], $config->includeReadModels());
		$this->assertSame(['group' => FakeCrudResource::class], $config->includeResources());
		$this->assertSame(15, $config->perPage());
		$this->assertSame(30, $config->maxPerPage());
		$this->assertSame(['name' => 'required|string'], $config->createRules());
		$this->assertSame(['name' => 'string'], $config->updateRules());
		$this->assertSame('-createdAt', $config->defaultSort());

	}

	/**
	 * Verify from() keeps existing value objects unchanged for internal call paths.
	 */
	public function testFromReturnsExistingConfigInstance(): void {

		$config = CrudResourceConfig::fromArray(['perPage' => 10]);

		$this->assertSame($config, CrudResourceConfig::from($config));

	}

	/**
	 * Verify toArray() preserves compatibility with callers expecting legacy arrays.
	 */
	public function testToArrayReturnsLegacyCompatibleShape(): void {

		$config = CrudResourceConfig::fromArray([
			'readModel' => FakeCrudReadModel::class,
			'rules' => [
				'create' => ['name' => 'required|string'],
			],
		]);

		$this->assertSame([
			'readModel' => FakeCrudReadModel::class,
			'resource' => null,
			'searchable' => [],
			'sortable' => [],
			'filterable' => [],
			'includes' => [],
			'includeReadModels' => [],
			'includeResources' => [],
			'perPage' => 20,
			'maxPerPage' => 100,
			'rules' => [
				'create' => ['name' => 'required|string'],
				'update' => [],
			],
			'defaultSort' => null,
		], $config->toArray());

	}

}
