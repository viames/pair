<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Env;
use Pair\Tests\Support\TestCase;

/**
 * Covers environment defaults and casting without relying on a real application .env file.
 */
class EnvTest extends TestCase {

	/**
	 * Verify framework defaults are loaded when the temporary .env file is absent.
	 */
	public function testLoadFallsBackToDefaultsWhenEnvFileIsMissing(): void {

		Env::load();

		$this->assertSame('Pair Application', Env::get('APP_NAME'));
		$this->assertFalse(Env::get('APP_DEBUG'));
		$this->assertSame(6379, Env::get('REDIS_PORT'));

	}

	/**
	 * Verify scalar values are cast correctly while quoted values remain strings.
	 */
	public function testLoadCastsUnquotedValuesAndPreservesQuotedStrings(): void {

		$this->writeEnvFile(implode("\n", [
			'APP_NAME="Pair Test Application"',
			'APP_DEBUG=true',
			'REDIS_PORT=6380',
			'CUSTOM_FLOAT=1.5',
			'CUSTOM_STRING="001"',
		]));

		Env::load();

		$this->assertSame('Pair Test Application', Env::get('APP_NAME'));
		$this->assertTrue(Env::get('APP_DEBUG'));
		$this->assertSame(6380, Env::get('REDIS_PORT'));
		$this->assertSame(1.5, Env::get('CUSTOM_FLOAT'));
		$this->assertSame('001', Env::get('CUSTOM_STRING'));

	}

}
