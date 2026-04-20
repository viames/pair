<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;
use Pair\Orm\Database;

/**
 * ActiveRecord test double used to cover CrudController transformation paths without the database.
 */
class FakeCrudRecord extends ActiveRecord {

	/**
	 * Fake primary key definition.
	 */
	public const TABLE_KEY = 'id';

	/**
	 * Fake table name.
	 */
	public const TABLE_NAME = 'fake_records';

	/**
	 * Fake primary key property used by Collection::toArray().
	 */
	public mixed $id = null;

	/**
	 * Fake name property.
	 */
	public mixed $name = null;

	/**
	 * Fake email property.
	 */
	public mixed $email = null;

	/**
	 * Stored scalar payload returned by toArray().
	 *
	 * @var	array<string, mixed>
	 */
	private array $payload = [];

	/**
	 * Stored relation payload indexed by relation name.
	 *
	 * @var	array<string, mixed>
	 */
	private array $relations = [];

	/**
	 * Records returned by the overridden find() helper during focused controller tests.
	 *
	 * @var	array<string, self|null>
	 */
	private static array $findResults = [];

	/**
	 * Return a stable property-to-column map when tests need a bind list.
	 *
	 * @return	array<string, string>
	 */
	public static function getBinds(): array {

		return [
			'id' => 'id',
			'name' => 'name',
			'email' => 'email',
		];

	}

	/**
	 * Seed the static find() result map used by CrudController tests.
	 *
	 * @param	int|string	$id		Primary key value to resolve.
	 * @param	self|null	$record	Record returned when CrudController requests this identifier.
	 */
	public static function seedFindResult(int|string $id, ?self $record): void {

		self::$findResults[(string)$id] = $record;

	}

	/**
	 * Reset the static find() map between tests.
	 */
	public static function resetFindResults(): void {

		self::$findResults = [];

	}

	/**
	 * Seed the fake record with scalar data and optional relation values.
	 *
	 * @param	array<string, mixed>	$payload	Scalar payload returned by toArray().
	 * @param	array<string, mixed>	$relations	Relation objects keyed by relation name.
	 */
	public function seed(array $payload, array $relations = []): static {

		$this->db = Database::getInstance();
		$this->payload = $payload;
		$this->relations = $relations;
		$this->keyProperties = ['id'];
		$this->id = $payload['id'] ?? null;
		$this->name = $payload['name'] ?? null;
		$this->email = $payload['email'] ?? null;

		return $this;

	}

	/**
	 * Return the seeded record configured for the requested identifier.
	 *
	 * @param	int|string|array	$primaryKey	Primary key requested by CrudController.
	 */
	public static function find(int|string|array $primaryKey): ?static {

		if (is_array($primaryKey)) {
			return null;
		}

		$record = self::$findResults[(string)$primaryKey] ?? null;

		return $record instanceof static ? $record : null;

	}

	/**
	 * Return the seeded scalar payload.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return array_merge($this->payload, [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
		]);

	}

	/**
	 * Return the seeded singular relation when present.
	 */
	public function getGroup(): ?ActiveRecord {

		return $this->relations['group'] ?? null;

	}

	/**
	 * Return the seeded collection relation when present.
	 */
	public function getTags(): Collection {

		$relation = $this->relations['tags'] ?? [];

		if ($relation instanceof Collection) {
			return $relation;
		}

		return new Collection((array)$relation);

	}

}
