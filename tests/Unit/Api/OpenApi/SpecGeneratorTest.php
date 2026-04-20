<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api\OpenApi;

use Pair\Api\OpenApi\SpecGenerator;
use Pair\Tests\Support\FakeCrudReadModel;
use Pair\Tests\Support\FakeCrudRecord;
use Pair\Tests\Support\FakeSchemaGenerator;
use Pair\Tests\Support\TrackingSchemaGenerator;
use Pair\Tests\Support\TestCase;

/**
 * Covers spec generation paths and metadata using a schema generator double instead of the database.
 */
class SpecGeneratorTest extends TestCase {

	/**
	 * Verify CRUD resources, custom paths, servers, and schemas are merged into the final spec.
	 */
	public function testBuildIncludesCrudPathsSchemasAndCustomPaths(): void {

		$generator = new SpecGenerator('Pair Test API', '1.2.3');
		$generator->setDescription('Minimal spec for framework tests');
		$generator->addServer('https://api.example.test', 'Primary test server');
		$generator->addPath('/health', 'get', [
			'summary' => 'Health check',
			'responses' => [
				'200' => ['description' => 'OK'],
			],
		]);

		$this->setInaccessibleProperty($generator, 'schemaGenerator', new FakeSchemaGenerator());
		$this->setInaccessibleProperty($generator, 'resources', [
			'users' => [
				'class' => \stdClass::class,
				'config' => [
					'perPage' => 15,
					'maxPerPage' => 50,
					'sortable' => ['name'],
					'searchable' => ['name', 'email'],
					'includes' => ['group'],
					'filterable' => ['status'],
					'rules' => [
						'create' => ['name' => 'required|string'],
						'update' => ['name' => 'string'],
					],
				],
				'basePath' => '/api',
			],
		]);

		$spec = $generator->build();

		$this->assertSame('3.1.0', $spec['openapi']);
		$this->assertSame('Pair Test API', $spec['info']['title']);
		$this->assertArrayHasKey('/health', $spec['paths']);
		$this->assertArrayHasKey('/api/users', $spec['paths']);
		$this->assertArrayHasKey('/api/users/{id}', $spec['paths']);
		$this->assertSame(15, $spec['paths']['/api/users']['get']['parameters'][1]['schema']['default']);
		$this->assertSame('https://api.example.test', $spec['servers'][0]['url']);
		$this->assertArrayHasKey('User', $spec['components']['schemas']);
		$this->assertArrayHasKey('UserCreate', $spec['components']['schemas']);
		$this->assertArrayHasKey('UserUpdate', $spec['components']['schemas']);

	}

	/**
	 * Verify Pair v4 resources document the explicit read model as the response schema source.
	 */
	public function testBuildUsesReadModelForResponseSchemaAndModelForWriteSchemas(): void {

		$generator = new SpecGenerator('Pair Test API', '1.2.3');
		$schemaGenerator = new TrackingSchemaGenerator();

		$this->setInaccessibleProperty($generator, 'schemaGenerator', $schemaGenerator);
		$this->setInaccessibleProperty($generator, 'resources', [
			'users' => [
				'class' => FakeCrudRecord::class,
				'config' => [
					'readModel' => FakeCrudReadModel::class,
					'rules' => [
						'create' => ['name' => 'required|string'],
						'update' => ['name' => 'string'],
					],
				],
				'basePath' => '/api',
			],
		]);

		$spec = $generator->build();

		$this->assertSame([FakeCrudReadModel::class], $schemaGenerator->generatedClasses);
		$this->assertSame(FakeCrudRecord::class, $schemaGenerator->createSchemaCalls[0]['class']);
		$this->assertSame(FakeCrudRecord::class, $schemaGenerator->updateSchemaCalls[0]['class']);
		$this->assertSame(FakeCrudReadModel::class, $spec['components']['schemas']['User']['x-class']);
		$this->assertSame(FakeCrudRecord::class, $spec['components']['schemas']['UserCreate']['x-class']);
		$this->assertSame(FakeCrudRecord::class, $spec['components']['schemas']['UserUpdate']['x-class']);

	}

}
