<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiExposable;
use Pair\Api\CrudResourceConfig;
use Pair\Tests\Support\FakeCrudExposeableModel;
use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit CRUD configuration contract exposed by the ApiExposable trait.
 */
class ApiExposableTest extends TestCase {

	/**
	 * Verify getApiConfig() merges trait defaults with the model-specific overrides.
	 */
	public function testGetApiConfigMergesDefaultsWithOverrides(): void {

		$config = FakeCrudExposeableModel::getApiConfig();

		$this->assertSame('Pair\Tests\Support\FakeCrudReadModel', $config['readModel']);
		$this->assertNull($config['resource']);
		$this->assertSame(['name'], $config['searchable']);
		$this->assertSame(['createdAt'], $config['sortable']);
		$this->assertSame(['status'], $config['filterable']);
		$this->assertSame(['group', 'tags'], $config['includes']);
		$this->assertSame(15, $config['perPage']);
		$this->assertSame(30, $config['maxPerPage']);
		$this->assertSame('-createdAt', $config['defaultSort']);
		$this->assertSame(['name' => 'required|string'], $config['rules']['create']);
		$this->assertSame(['name' => 'string'], $config['rules']['update']);

	}

	/**
	 * Verify the trait exposes the same config through the typed internal value object.
	 */
	public function testGetCrudResourceConfigReturnsTypedConfig(): void {

		$config = FakeCrudExposeableModel::getCrudResourceConfig();

		$this->assertInstanceOf(CrudResourceConfig::class, $config);
		$this->assertSame('Pair\Tests\Support\FakeCrudReadModel', $config->readModel());
		$this->assertSame(['name'], $config->searchable());
		$this->assertSame(['createdAt'], $config->sortable());
		$this->assertSame(['status'], $config->filterable());
		$this->assertSame(['group', 'tags'], $config->includes());
		$this->assertSame(15, $config->perPage());
		$this->assertSame(30, $config->maxPerPage());
		$this->assertSame('-createdAt', $config->defaultSort());
		$this->assertSame(['name' => 'required|string'], $config->createRules());
		$this->assertSame(['name' => 'string'], $config->updateRules());

	}

	/**
	 * Verify the helper predicates reflect the configured searchable, sortable, filterable, and includable fields.
	 */
	public function testHelperPredicatesReflectConfiguredCapabilities(): void {

		$this->assertTrue(FakeCrudExposeableModel::isSearchable('name'));
		$this->assertFalse(FakeCrudExposeableModel::isSearchable('email'));

		$this->assertTrue(FakeCrudExposeableModel::isSortable('createdAt'));
		$this->assertFalse(FakeCrudExposeableModel::isSortable('name'));

		$this->assertTrue(FakeCrudExposeableModel::isFilterable('status'));
		$this->assertFalse(FakeCrudExposeableModel::isFilterable('group'));

		$this->assertTrue(FakeCrudExposeableModel::isIncludable('group'));
		$this->assertFalse(FakeCrudExposeableModel::isIncludable('permissions'));

	}

	/**
	 * Verify the trait defaults stay conservative when a model does not override apiConfig().
	 */
	public function testGetApiConfigReturnsConservativeDefaultsWhenNotOverridden(): void {

		$config = ApiExposableDefaultsFixture::getApiConfig();

		$this->assertNull($config['readModel']);
		$this->assertNull($config['resource']);
		$this->assertSame([], $config['searchable']);
		$this->assertSame([], $config['sortable']);
		$this->assertSame([], $config['filterable']);
		$this->assertSame([], $config['includes']);
		$this->assertSame([], $config['includeReadModels']);
		$this->assertSame([], $config['includeResources']);
		$this->assertSame(20, $config['perPage']);
		$this->assertSame(100, $config['maxPerPage']);
		$this->assertSame(['create' => [], 'update' => []], $config['rules']);
		$this->assertNull($config['defaultSort']);
		$this->assertFalse(ApiExposableDefaultsFixture::isSearchable('name'));
		$this->assertFalse(ApiExposableDefaultsFixture::isSortable('createdAt'));
		$this->assertFalse(ApiExposableDefaultsFixture::isFilterable('status'));
		$this->assertFalse(ApiExposableDefaultsFixture::isIncludable('group'));

	}

}

/**
 * Minimal fixture class used to verify ApiExposable defaults without custom overrides.
 */
final class ApiExposableDefaultsFixture {

	use ApiExposable;

}
