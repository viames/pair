<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Api\OpenApi\SchemaGenerator;

/**
 * SchemaGenerator test double that records which classes are used for each schema phase.
 */
class TrackingSchemaGenerator extends SchemaGenerator {

	/**
	 * Classes passed to generate().
	 *
	 * @var	string[]
	 */
	public array $generatedClasses = [];

	/**
	 * Calls passed to generateCreateSchema().
	 *
	 * @var	array<int, array{class: string, rules: array}>
	 */
	public array $createSchemaCalls = [];

	/**
	 * Calls passed to generateUpdateSchema().
	 *
	 * @var	array<int, array{class: string, rules: array}>
	 */
	public array $updateSchemaCalls = [];

	/**
	 * Record the response schema source class.
	 *
	 * @param	string	$modelClass	Fully qualified class name.
	 * @return	array
	 */
	public function generate(string $modelClass): array {

		$this->generatedClasses[] = $modelClass;

		return [
			'type' => 'object',
			'x-class' => $modelClass,
			'properties' => [],
		];

	}

	/**
	 * Record the create-schema source class.
	 *
	 * @param	string	$modelClass	Fully qualified class name.
	 * @param	array	$rules		Validation rules.
	 * @return	array
	 */
	public function generateCreateSchema(string $modelClass, array $rules = []): array {

		$this->createSchemaCalls[] = [
			'class' => $modelClass,
			'rules' => $rules,
		];

		return [
			'type' => 'object',
			'x-class' => $modelClass,
			'properties' => [],
		];

	}

	/**
	 * Record the update-schema source class.
	 *
	 * @param	string	$modelClass	Fully qualified class name.
	 * @param	array	$rules		Validation rules.
	 * @return	array
	 */
	public function generateUpdateSchema(string $modelClass, array $rules = []): array {

		$this->updateSchemaCalls[] = [
			'class' => $modelClass,
			'rules' => $rules,
		];

		return [
			'type' => 'object',
			'x-class' => $modelClass,
			'properties' => [],
		];

	}

}
