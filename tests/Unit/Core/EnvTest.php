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
		$this->assertFalse(Env::get('PAIR_OBSERVABILITY_ENABLED'));
		$this->assertTrue(Env::get('PAIR_OBSERVABILITY_DEBUG_HEADERS'));
		$this->assertSame('', Env::get('STRIPE_SECRET_KEY'));
		$this->assertSame('', Env::get('STRIPE_WEBHOOK_SECRET'));
		$this->assertSame('', Env::get('STRIPE_API_VERSION'));
		$this->assertSame('', Env::get('OPENAI_API_KEY'));
		$this->assertSame('https://api.openai.com/v1', Env::get('OPENAI_API_BASE_URL'));
		$this->assertSame('gpt-5.4-mini', Env::get('OPENAI_RESPONSES_MODEL'));
		$this->assertSame('text-embedding-3-small', Env::get('OPENAI_EMBEDDINGS_MODEL'));
		$this->assertSame('gpt-realtime', Env::get('OPENAI_REALTIME_MODEL'));
		$this->assertSame(30, Env::get('OPENAI_TIMEOUT'));
		$this->assertSame(5, Env::get('OPENAI_CONNECT_TIMEOUT'));
		$this->assertFalse(Env::get('OPENAI_STORE_RESPONSES'));
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

	/**
	 * Verify repeated loads reuse cached values until a forced reload is requested.
	 */
	public function testLoadCachesParsedValuesUntilForced(): void {

		$this->writeEnvFile('APP_NAME=Initial Application');

		Env::load();

		$this->writeEnvFile('APP_NAME=Changed Application');

		Env::load();

		$this->assertSame('Initial Application', Env::get('APP_NAME'));

		Env::load(true);

		$this->assertSame('Changed Application', Env::get('APP_NAME'));

	}

	/**
	 * Verify explicit cache clearing allows the next load to re-read the .env file.
	 */
	public function testClearCacheAllowsReloadAfterEnvFileChanges(): void {

		$this->writeEnvFile('APP_NAME=Cached Application');

		Env::load();

		$this->writeEnvFile('APP_NAME=Reloaded Application');
		Env::clearCache();
		Env::load();

		$this->assertSame('Reloaded Application', Env::get('APP_NAME'));

	}

}
