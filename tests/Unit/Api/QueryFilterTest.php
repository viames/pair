<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\QueryFilter;
use Pair\Api\Request;
use Pair\Tests\Support\FakeApiModel;
use Pair\Tests\Support\TestCase;

/**
 * Covers query parsing and SQL generation for list endpoints without executing database queries.
 */
class QueryFilterTest extends TestCase {

	/**
	 * Verify filters, search, sorting, pagination, fields and includes are translated consistently.
	 */
	public function testApplyBuildsExpectedSqlBindingsAndProjectionState(): void {

		$_GET = [
			'filter' => [
				'status' => 'active',
				'createdAt' => '>=2025-01-01',
			],
			'sort' => '-name',
			'search' => 'alice',
			'fields' => 'id,name',
			'include' => 'group,role',
			'page' => '2',
			'perPage' => '15',
		];

		$filter = new QueryFilter(FakeApiModel::class, new Request(), [
			'filterable' => ['status', 'createdAt'],
			'sortable' => ['name'],
			'searchable' => ['name', 'email'],
			'includes' => ['group'],
			'perPage' => 20,
			'maxPerPage' => 50,
		]);

		$result = $filter->apply();

		$this->assertSame(
			'SELECT `id`, `name`, `email`, `status`, `created_at` FROM `users` WHERE `status` = ? AND `created_at` >= ? AND (`name` LIKE ? OR `email` LIKE ?) ORDER BY `name` DESC LIMIT 15 OFFSET 15',
			$result['query']->toSql()
		);
		$this->assertSame(['active', '2025-01-01', '%alice%', '%alice%'], $result['query']->getBindings());
		$this->assertSame(2, $result['page']);
		$this->assertSame(15, $result['perPage']);
		$this->assertSame(['id', 'name'], $result['fields']);
		$this->assertSame(['group'], $result['includes']);

	}

	/**
	 * Verify disallowed inputs are ignored while pagination bounds and default sorting stay enforced.
	 */
	public function testApplyIgnoresDisallowedInputsAndEnforcesPaginationBounds(): void {

		$_GET = [
			'filter' => [
				'unknown' => 'value',
				'status' => '!archived',
			],
			'sort' => '-unknown',
			'include' => 'role,permissions',
			'page' => '0',
			'perPage' => '999',
		];

		$filter = new QueryFilter(FakeApiModel::class, new Request(), [
			'filterable' => ['status'],
			'sortable' => ['createdAt'],
			'includes' => ['permissions'],
			'defaultSort' => '-createdAt',
			'perPage' => 20,
			'maxPerPage' => 40,
		]);

		$result = $filter->apply();

		$this->assertSame(
			'SELECT `id`, `name`, `email`, `status`, `created_at` FROM `users` WHERE `status` != ? LIMIT 40 OFFSET 0',
			$result['query']->toSql()
		);
		$this->assertSame(['archived'], $result['query']->getBindings());
		$this->assertSame(1, $result['page']);
		$this->assertSame(40, $result['perPage']);
		$this->assertNull($result['fields']);
		$this->assertSame(['permissions'], $result['includes']);

	}

}
