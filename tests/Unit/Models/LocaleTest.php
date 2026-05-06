<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Models;

use Pair\Models\Locale;
use Pair\Orm\Database;
use Pair\Tests\Support\FakeCrudDatabase;
use Pair\Tests\Support\TestCase;

/**
 * Covers locale helpers used during every authenticated web request.
 */
class LocaleTest extends TestCase {

	/**
	 * Reset shared database and locale caches after each focused model test.
	 */
	protected function tearDown(): void {

		$this->resetLocaleRepresentationCache();
		$this->resetDatabaseSingleton();

		parent::tearDown();

	}

	/**
	 * Verify locale representation is built from joined codes and then cached in-process.
	 */
	public function testRepresentationCodesAreCached(): void {

		$database = $this->setDatabaseInstance();
		$database->useSqliteMemoryDatabase([
			'CREATE TABLE locales (id INTEGER PRIMARY KEY, language_id INTEGER, country_id INTEGER, official_language INTEGER, default_country INTEGER, app_default INTEGER)',
			'CREATE TABLE languages (id INTEGER PRIMARY KEY, code TEXT, native_name TEXT, english_name TEXT)',
			'CREATE TABLE countries (id INTEGER PRIMARY KEY, code TEXT, native_name TEXT, english_name TEXT)',
			"INSERT INTO languages (id, code, native_name, english_name) VALUES (1, 'it', 'Italiano', 'Italian')",
			"INSERT INTO countries (id, code, native_name, english_name) VALUES (100, 'IT', 'Italia', 'Italy')",
			'INSERT INTO locales (id, language_id, country_id, official_language, default_country, app_default) VALUES (51, 1, 100, 1, 1, 1)',
		]);

		$locale = Locale::find(51);

		$this->assertInstanceOf(Locale::class, $locale);
		$this->assertSame('it-IT', $locale->getRepresentation());
		$this->assertSame('it_IT', $locale->getRepresentation('_'));

		Database::run("UPDATE languages SET code = 'xx' WHERE id = 1");

		$this->assertSame('it-IT', $locale->getRepresentation());

	}

	/**
	 * Install and return a lightweight fake database singleton.
	 */
	private function setDatabaseInstance(): FakeCrudDatabase {

		$reflection = new \ReflectionClass(FakeCrudDatabase::class);
		$database = $reflection->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, $database);

		return $database;

	}

	/**
	 * Reset the shared database singleton between tests.
	 */
	private function resetDatabaseSingleton(): void {

		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Reset process-local Locale representation cache.
	 */
	private function resetLocaleRepresentationCache(): void {

		$property = new \ReflectionProperty(Locale::class, 'representationCodes');
		$property->setValue(null, []);

	}

}
