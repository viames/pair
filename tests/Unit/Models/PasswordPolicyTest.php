<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Core\Env;
use Pair\Models\User;
use Pair\Tests\Support\TestCase;

/**
 * Tests configurable hashing and login preservation without database access.
 */
class PasswordPolicyTest extends TestCase {

	/**
	 * Verify absent settings retain the original hashing and login defaults.
	 */
	public function testDefaults(): void {

		$this->assertSame('auto', Env::get('PAIR_PASSWORD_HASH_ALGORITHM'));
		$this->assertTrue(Env::get('PAIR_PASSWORD_REHASH_ON_LOGIN'));
		$hash = User::getHashedPasswordWithSalt('password');
		$this->assertSame(defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt', password_get_info($hash)['algoName']);

	}

	/**
	 * Verify bcrypt hashes are accepted by the Pair 1 crypt-based verifier.
	 */
	public function testLegacyBcrypt(): void {

		$_ENV['PAIR_PASSWORD_HASH_ALGORITHM'] = 'bcrypt';
		$hash = User::getHashedPasswordWithSalt('shared-password');
		$this->assertSame('bcrypt', password_get_info($hash)['algoName']);
		$this->assertSame(12, password_get_info($hash)['options']['cost']);
		$this->assertSame($hash, crypt('shared-password', $hash));
		$this->assertTrue(User::checkPassword('shared-password', $hash));
		$this->assertFalse(User::checkPassword('wrong-password', $hash));

	}

	/**
	 * Verify disabling rehash preserves hashes even when algorithm selection would fail.
	 */
	public function testPreserveHash(): void {

		$_ENV['PAIR_PASSWORD_REHASH_ON_LOGIN'] = false;
		$_ENV['PAIR_PASSWORD_HASH_ALGORITHM'] = 'invalid';
		$hashes = [crypt('shared-password', '$2a$12$abcdefghijklmnopqrstuu')];
		if (defined('PASSWORD_ARGON2ID')) {
			$hashes[] = password_hash('shared-password', PASSWORD_ARGON2ID);
		}

		foreach ($hashes as $hash) {
			$user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
			(new \ReflectionProperty(User::class, 'hash'))->setValue($user, $hash);
			(new \ReflectionMethod(User::class, 'rehashPasswordIfNeeded'))->invoke($user, 'shared-password');
			$this->assertSame($hash, $user->hash);
		}

	}

	/**
	 * Verify the same selected policy drives rehash decisions.
	 */
	public function testRehashPolicy(): void {

		$_ENV['PAIR_PASSWORD_HASH_ALGORITHM'] = 'bcrypt';
		$method = new \ReflectionMethod(User::class, 'passwordNeedsRehash');
		$this->assertFalse($method->invoke(null, password_hash('password', PASSWORD_BCRYPT, ['cost' => 12])));
		$this->assertTrue($method->invoke(null, password_hash('password', PASSWORD_BCRYPT, ['cost' => 4])));
		if (defined('PASSWORD_ARGON2ID')) {
			$_ENV['PAIR_PASSWORD_HASH_ALGORITHM'] = 'auto';
			$this->assertTrue($method->invoke(null, password_hash('password', PASSWORD_BCRYPT, ['cost' => 12])));
		}

	}

	/**
	 * Verify invalid algorithms cannot silently produce an unexpected password format.
	 */
	public function testInvalidAlgorithm(): void {

		$_ENV['PAIR_PASSWORD_HASH_ALGORITHM'] = 'invalid';
		$this->expectException(\InvalidArgumentException::class);
		User::getHashedPasswordWithSalt('password');

	}

	/**
	 * Verify explicitly selecting Argon2id requires runtime support.
	 */
	public function testExplicitArgon(): void {

		$_ENV['PAIR_PASSWORD_HASH_ALGORITHM'] = 'argon2id';
		if (!defined('PASSWORD_ARGON2ID')) {
			$this->expectException(\RuntimeException::class);
			User::getHashedPasswordWithSalt('password');
			return;
		}

		$hash = User::getHashedPasswordWithSalt('password');
		$this->assertSame('argon2id', password_get_info($hash)['algoName']);
		$this->assertTrue(User::checkPassword('password', $hash));

	}

}
