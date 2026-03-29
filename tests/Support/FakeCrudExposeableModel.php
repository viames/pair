<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Api\ApiExposable;

/**
 * Minimal model-like class exposing ApiExposable defaults for CrudController registration tests.
 */
class FakeCrudExposeableModel {

	use ApiExposable;

	/**
	 * Return a deterministic API config for tests.
	 *
	 * @return	array<string, mixed>
	 */
	public static function apiConfig(): array {

		return [
			'resource' => FakeCrudResource::class,
			'searchable' => ['name'],
			'sortable' => ['createdAt'],
			'filterable' => ['status'],
			'includes' => ['group', 'tags'],
			'perPage' => 15,
			'maxPerPage' => 30,
			'rules' => [
				'create' => ['name' => 'required|string'],
				'update' => ['name' => 'string'],
			],
			'defaultSort' => '-createdAt',
		];

	}

}
