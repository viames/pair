<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api\OpenApi;

use Pair\Api\OpenApi\SchemaGenerator;
use Pair\Tests\Support\FakeOpenApiUserReadModel;
use Pair\Tests\Support\TestCase;

/**
 * Covers OpenAPI schema generation for typed Pair v4 read models.
 */
class SchemaGeneratorTest extends TestCase {

	/**
	 * Verify typed read models are converted into response schemas without requiring database metadata.
	 */
	public function testGenerateBuildsSchemaFromTypedReadModel(): void {

		$generator = new SchemaGenerator();
		$schema = $generator->generate(FakeOpenApiUserReadModel::class);

		$this->assertSame('object', $schema['type']);
		$this->assertSame('integer', $schema['properties']['id']['type']);
		$this->assertSame('string', $schema['properties']['name']['type']);
		$this->assertSame(['string', 'null'], $schema['properties']['email']['type']);
		$this->assertSame('boolean', $schema['properties']['enabled']['type']);
		$this->assertSame('date-time', $schema['properties']['createdAt']['format']);
		$this->assertSame('object', $schema['properties']['address']['type']);
		$this->assertSame('string', $schema['properties']['address']['properties']['city']['type']);
		$this->assertContains('id', $schema['required']);
		$this->assertContains('createdAt', $schema['required']);
		$this->assertNotContains('email', $schema['required']);

	}

	/**
	 * Verify full schemas are cached until the source class is explicitly invalidated.
	 */
	public function testGenerateCachesSchemasUntilCleared(): void {

		CachedOpenApiSchemaFixture::reset();

		$generator = new SchemaGenerator();

		$first = $generator->generate(CachedOpenApiSchemaFixture::class);
		$second = $generator->generate(CachedOpenApiSchemaFixture::class);

		$this->assertSame($first, $second);
		$this->assertSame(1, CachedOpenApiSchemaFixture::$schemaCalls);
		$this->assertSame(1, $second['x-generation']);

		$generator->clearCache(CachedOpenApiSchemaFixture::class);

		$third = $generator->generate(CachedOpenApiSchemaFixture::class);

		$this->assertSame(2, CachedOpenApiSchemaFixture::$schemaCalls);
		$this->assertSame(2, $third['x-generation']);

	}

	/**
	 * Verify create request schemas are cached by class and validation rules.
	 */
	public function testGenerateCreateSchemaCachesRuleSpecificSchemasUntilCleared(): void {

		CachedOpenApiSchemaFixture::reset();

		$generator = new SchemaGenerator();
		$rules = ['name' => 'required|string'];

		$first = $generator->generateCreateSchema(CachedOpenApiSchemaFixture::class, $rules);
		$second = $generator->generateCreateSchema(CachedOpenApiSchemaFixture::class, $rules);

		$this->assertSame($first, $second);
		$this->assertSame(1, CachedOpenApiSchemaFixture::$schemaCalls);
		$this->assertSame(['name'], $second['required']);
		$this->assertArrayHasKey('name', $second['properties']);
		$this->assertArrayNotHasKey('email', $second['properties']);

		$generator->clearCache(CachedOpenApiSchemaFixture::class);

		$third = $generator->generateCreateSchema(CachedOpenApiSchemaFixture::class, $rules);

		$this->assertSame(2, CachedOpenApiSchemaFixture::$schemaCalls);
		$this->assertSame(2, $third['x-generation']);

	}

}

/**
 * Fixture with explicit OpenAPI schema override used to verify schema caching.
 */
final class CachedOpenApiSchemaFixture {

	/**
	 * Number of times openApiSchema() has been evaluated.
	 */
	public static int $schemaCalls = 0;

	/**
	 * Return an explicit schema and record the generation count.
	 *
	 * @return	array<string, mixed>
	 */
	public static function openApiSchema(): array {

		self::$schemaCalls++;

		return [
			'type' => 'object',
			'x-generation' => self::$schemaCalls,
			'properties' => [
				'name' => ['type' => 'string'],
				'email' => ['type' => 'string'],
			],
			'required' => ['name', 'email'],
		];

	}

	/**
	 * Reset call counters before a focused cache test.
	 */
	public static function reset(): void {

		self::$schemaCalls = 0;

	}

}
