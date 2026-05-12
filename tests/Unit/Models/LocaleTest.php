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
	 * Reset shared state before every locale test because other suites may touch Locale singletons.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->resetLocaleRuntimeCaches();
		$this->resetDatabaseSingleton();

	}

	/**
	 * Reset shared database and locale caches after each focused model test.
	 */
	protected function tearDown(): void {

		$this->resetLocaleRuntimeCaches();
		$this->resetDatabaseSingleton();

		parent::tearDown();

	}

	/**
	 * Verify locale representation is lazily cached per persisted locale id.
	 */
	public function testRepresentationCodesAreCached(): void {

		$database = $this->setDatabaseInstance();
		$database->useSqliteMemoryDatabase([
			'CREATE TABLE locales (id INTEGER PRIMARY KEY, language_id INTEGER, country_id INTEGER, official_language INTEGER, default_country INTEGER, app_default INTEGER)',
			'CREATE TABLE languages (id INTEGER PRIMARY KEY, code TEXT, native_name TEXT, english_name TEXT)',
			'CREATE TABLE countries (id INTEGER PRIMARY KEY, code TEXT, native_name TEXT, english_name TEXT)',
			"INSERT INTO languages (id, code, native_name, english_name) VALUES (1, 'it', 'Italiano', 'Italian')",
			"INSERT INTO languages (id, code, native_name, english_name) VALUES (2, 'en', 'English', 'English')",
			"INSERT INTO countries (id, code, native_name, english_name) VALUES (100, 'IT', 'Italia', 'Italy')",
			"INSERT INTO countries (id, code, native_name, english_name) VALUES (200, 'GB', 'United Kingdom', 'United Kingdom')",
			'INSERT INTO locales (id, language_id, country_id, official_language, default_country, app_default) VALUES (51, 1, 100, 1, 1, 1)',
			'INSERT INTO locales (id, language_id, country_id, official_language, default_country, app_default) VALUES (52, 2, 200, 1, 1, 0)',
		]);

		$locale = Locale::find(51);

		$this->assertInstanceOf(Locale::class, $locale);
		$this->assertSame('it-IT', $locale->getRepresentation());
		$this->assertSame('it_IT', $locale->getRepresentation('_'));
		$this->assertArrayHasKey(51, $this->localeRepresentationCodes());
		$this->assertArrayNotHasKey(52, $this->localeRepresentationCodes());

		Database::run("UPDATE languages SET code = 'xx' WHERE id IN (1, 2)");

		$this->assertSame('it-IT', $locale->getRepresentation());

		$secondLocale = Locale::find(52);
		$this->assertInstanceOf(Locale::class, $secondLocale);
		$this->assertSame('xx-GB', $secondLocale->getRepresentation());

	}

	/**
	 * Verify the default locale lookup is cached for the current request and returned as a copy.
	 */
	public function testDefaultLocaleIsCachedForCurrentRequest(): void {

		$database = $this->setDatabaseInstance();
		$database->useSqliteMemoryDatabase([
			'CREATE TABLE locales (id INTEGER PRIMARY KEY, language_id INTEGER, country_id INTEGER, official_language INTEGER, default_country INTEGER, app_default INTEGER)',
			'INSERT INTO locales (id, language_id, country_id, official_language, default_country, app_default) VALUES (51, 1, 100, 1, 1, 1)',
		]);

		$first = Locale::getDefault();
		$this->assertInstanceOf(Locale::class, $first);

		Database::run('UPDATE locales SET app_default = 0 WHERE id = 51');

		$second = Locale::getDefault();
		$this->assertInstanceOf(Locale::class, $second);
		$this->assertNotSame($first, $second);
		$this->assertSame($first->id, $second->id);

		$this->resetLocaleRuntimeCaches();
		$this->assertNull(Locale::getDefault());

	}

	/**
	 * Verify non-persisted locale ids do not trigger the joined locale representation query.
	 */
	public function testZeroLocaleIdUsesRelationFallback(): void {

		$database = $this->setDatabaseInstance();
		$database->useSqliteMemoryDatabase([
			'CREATE TABLE languages (id INTEGER PRIMARY KEY, code TEXT, native_name TEXT, english_name TEXT)',
			'CREATE TABLE countries (id INTEGER PRIMARY KEY, code TEXT, native_name TEXT, english_name TEXT)',
			"INSERT INTO languages (id, code, native_name, english_name) VALUES (1, 'it', 'Italiano', 'Italian')",
			"INSERT INTO countries (id, code, native_name, english_name) VALUES (100, 'IT', 'Italia', 'Italy')",
		]);

		$row = new \stdClass();
		$row->id = 0;
		$row->language_id = 1;
		$row->country_id = 100;
		$row->official_language = 1;
		$row->default_country = 1;
		$row->app_default = 0;
		$locale = new Locale($row);

		$this->assertSame('it-IT', $locale->getRepresentation());
		$this->assertArrayNotHasKey(0, $this->localeRepresentationCodes());

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
	 * Reset process-local Locale caches.
	 */
	private function resetLocaleRuntimeCaches(): void {

		$property = new \ReflectionProperty(Locale::class, 'representationCodes');
		$property->setValue(null, []);

		$property = new \ReflectionProperty(Locale::class, 'defaultLocale');
		$property->setValue(null, null);

		$property = new \ReflectionProperty(Locale::class, 'defaultLocaleLoaded');
		$property->setValue(null, false);

	}

	/**
	 * Return cached locale representation codes for focused cache assertions.
	 *
	 * @return	array<int, array{language: string, country: string}>
	 */
	private function localeRepresentationCodes(): array {

		$property = new \ReflectionProperty(Locale::class, 'representationCodes');

		return $property->getValue();

	}

}
