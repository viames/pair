<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

/**
 * Minimal fake model used to exercise API query filtering without touching the real ORM models.
 */
class FakeApiModel {

	/**
	 * Fake table name exposed to QueryFilter.
	 */
	public const TABLE_NAME = 'users';

	/**
	 * Return a stable property-to-column map for query filter tests.
	 *
	 * @return	array<string, string>
	 */
	public static function getBinds(): array {

		return [
			'id' => 'id',
			'name' => 'name',
			'email' => 'email',
			'status' => 'status',
			'createdAt' => 'created_at',
		];

	}

	/**
	 * Return the selected columns used by QueryFilter::apply().
	 *
	 * @return	string[]
	 */
	public static function getQueryColumns(): array {

		return [
			'id',
			'name',
			'email',
			'status',
			'created_at',
		];

	}

}
