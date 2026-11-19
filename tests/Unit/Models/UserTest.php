<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Models\User;
use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/**
 * Covers request-local user authorization helpers.
 */
class UserTest extends TestCase {

	/**
	 * Verify new password hashes use the preferred local algorithm and remain verifiable.
	 */
	public function testPasswordHashUsesPreferredAlgorithmAndVerifies(): void {

		$hash = User::getHashedPasswordWithSalt('correct-password');
		$info = password_get_info($hash);

		if (defined('PASSWORD_ARGON2ID')) {
			$this->assertSame('argon2id', $info['algoName']);
		} else {
			$this->assertSame('bcrypt', $info['algoName']);
		}

		$this->assertTrue(User::checkPassword('correct-password', $hash));
		$this->assertFalse(User::checkPassword('wrong-password', $hash));

	}

	/**
	 * Verify ACL checks use the same wildcard, default-action, and exact-action semantics.
	 */
	public function testCanAccessUsesIndexedAclRules(): void {

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		$statement = $this->createMock(\PDOStatement::class);
		$statement->expects($this->once())->method('execute')->with([2])->willReturn(true);
		$statement->method('fetchAll')->willReturn([
			(object)['module_name' => 'dashboard', 'action' => null],
			(object)['module_name' => 'orders', 'action' => 'default'],
			(object)['module_name' => 'reports', 'action' => 'export'],
		]);
		$pdo = $this->createMock(\PDO::class);
		$pdo->expects($this->once())->method('prepare')->with($this->callback(static function (string $query): bool {
			return str_contains($query, 'SELECT m.`name` AS `module_name`, r.`action`')
				and str_contains($query, 'a.`group_id` = ? AND r.`super_only` = 0');
		}))->willReturn($statement);

		$instance = new \ReflectionProperty(Database::class, 'instance');
		$previous = $instance->getValue();
		$database = (new \ReflectionClass(Database::class))->newInstanceWithoutConstructor();
		$this->setInaccessibleProperty($database, 'handler', $pdo);
		$instance->setValue(null, $database);

		try {
			$user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
			$this->setInaccessibleProperty($user, 'groupId', 2);
			$this->setInaccessibleProperty($user, 'super', false);

			$this->assertTrue($user->canAccess('dashboard'));
			$this->assertTrue($user->canAccess('dashboard', 'stats'));
			$this->assertTrue($user->canAccess('orders'));
			$this->assertFalse($user->canAccess('orders', 'edit'));
			$this->assertTrue($user->canAccess('reports/export'));
			$this->assertFalse($user->canAccess('reports/delete'));
			$this->assertFalse($user->canAccess('admin'));
			$this->assertTrue($user->canAccess('user', 'profile'));
		} finally {
			$instance->setValue(null, $previous);
		}

	}

}
