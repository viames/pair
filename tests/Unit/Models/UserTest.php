<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;
use Pair\Tests\Support\TestCase;

/**
 * Covers request-local user authorization helpers.
 */
class UserTest extends TestCase {

	/**
	 * Verify ACL checks use the same wildcard, default-action, and exact-action semantics.
	 */
	public function testCanAccessUsesIndexedAclRules(): void {

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		$user = $this->newUserWithAcl([
			$this->rule('dashboard', null),
			$this->rule('orders', 'default'),
			$this->rule('reports', 'export'),
			$this->rule('admin', null, true),
		]);

		$this->assertTrue($user->canAccess('dashboard'));
		$this->assertTrue($user->canAccess('dashboard', 'stats'));
		$this->assertTrue($user->canAccess('orders'));
		$this->assertFalse($user->canAccess('orders', 'edit'));
		$this->assertTrue($user->canAccess('reports/export'));
		$this->assertFalse($user->canAccess('reports/delete'));
		$this->assertFalse($user->canAccess('admin'));
		$this->assertTrue($user->canAccess('user', 'profile'));

	}

	/**
	 * Create a user test double with an already-loaded ACL cache.
	 *
	 * @param	array<int, object>	$rules	Rule-like objects consumed by User::canAccess().
	 */
	private function newUserWithAcl(array $rules): User {

		$reflection = new \ReflectionClass(User::class);
		$user = $reflection->newInstanceWithoutConstructor();

		$super = new \ReflectionProperty(User::class, 'super');
		$super->setValue($user, false);

		$cache = new \ReflectionProperty(ActiveRecord::class, 'cache');
		$cache->setValue($user, [
			'acl' => new Collection($rules),
		]);

		return $user;

	}

	/**
	 * Build the minimal rule object shape used by the ACL map.
	 */
	private function rule(string $moduleName, ?string $action, bool $superOnly = false): object {

		return (object)[
			'moduleName' => $moduleName,
			'action' => $action,
			'superOnly' => $superOnly,
		];

	}

}
