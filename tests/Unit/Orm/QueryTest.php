<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Orm\Query;
use Pair\Tests\Support\TestCase;

/**
 * Covers SQL generation behavior for the lightweight query builder.
 */
class QueryTest extends TestCase {

	/**
	 * Verify qualified wildcard columns keep the wildcard segment raw.
	 */
	public function testQualifiedWildcardColumnsAreWrappedCorrectly(): void {

		$sql = Query::table('users')->select('users.*')->toSql();

		$this->assertSame('SELECT `users`.* FROM `users`', $sql);

	}

	/**
	 * Verify a raw select replaces the implicit wildcard projection.
	 */
	public function testSelectRawRemovesImplicitWildcard(): void {

		$sql = Query::table('users')->selectRaw('COUNT(*) AS total')->toSql();

		$this->assertSame('SELECT COUNT(*) AS total FROM `users`', $sql);

	}

	/**
	 * Verify a raw select keeps explicit select columns when they are configured first.
	 */
	public function testSelectRawKeepsExplicitSelectColumns(): void {

		$sql = Query::table('users')
			->select('id')
			->selectRaw('COUNT(*) AS total')
			->toSql();

		$this->assertSame('SELECT `id`, COUNT(*) AS total FROM `users`', $sql);

	}

}
