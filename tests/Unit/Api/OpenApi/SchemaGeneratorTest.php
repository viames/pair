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

}
