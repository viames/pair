<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Api\OpenApi\SchemaGenerator;

/**
 * Test double for SchemaGenerator that avoids database access during spec generation tests.
 */
class FakeSchemaGenerator extends SchemaGenerator {

	/**
	 * Return a deterministic base schema for resource tests.
	 *
	 * @param	string	$modelClass	Fully qualified model class name.
	 * @return	array
	 */
	public function generate(string $modelClass): array {

		return [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
			],
		];

	}

	/**
	 * Return a deterministic create schema for resource tests.
	 *
	 * @param	string	$modelClass	Fully qualified model class name.
	 * @param	array	$rules		Validation rules.
	 * @return	array
	 */
	public function generateCreateSchema(string $modelClass, array $rules = []): array {

		return [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
			],
			'required' => ['name'],
		];

	}

	/**
	 * Return a deterministic update schema for resource tests.
	 *
	 * @param	string	$modelClass	Fully qualified model class name.
	 * @param	array	$rules		Validation rules.
	 * @return	array
	 */
	public function generateUpdateSchema(string $modelClass, array $rules = []): array {

		return [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
			],
		];

	}

}
