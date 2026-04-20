<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

/**
 * Minimal deletable resource double used to exercise CrudController delete dispatch without ORM dependencies.
 */
final class FakeCrudDeletableRecord {

	/**
	 * Record returned by the static find() helper during focused delete-dispatch tests.
	 */
	private static ?self $findResult = null;

	/**
	 * Tracks whether CrudController invoked delete() on this resource.
	 */
	public bool $deleteCalled = false;

	/**
	 * Whether the resource should report itself as deletable.
	 */
	private bool $deletable = true;

	/**
	 * Whether delete() should report success.
	 */
	private bool $deleteResult = true;

	/**
	 * Seed the static find() result used by CrudController delete tests.
	 */
	public static function seedFindResult(?self $record): void {

		self::$findResult = $record;

	}

	/**
	 * Reset the static find() result between tests.
	 */
	public static function resetFindResult(): void {

		self::$findResult = null;

	}

	/**
	 * Return the seeded record when CrudController resolves the requested identifier.
	 */
	public static function find(int|string|array $primaryKey): ?self {

		return is_array($primaryKey) ? null : self::$findResult;

	}

	/**
	 * Configure whether the fake resource should be deletable.
	 */
	public function setDeletable(bool $deletable): self {

		$this->deletable = $deletable;

		return $this;

	}

	/**
	 * Configure whether delete() should report success.
	 */
	public function setDeleteResult(bool $deleteResult): self {

		$this->deleteResult = $deleteResult;

		return $this;

	}

	/**
	 * Return whether CrudController should allow deletion for this resource.
	 */
	public function isDeletable(): bool {

		return $this->deletable;

	}

	/**
	 * Track the delete call and return the configured deletion outcome.
	 */
	public function delete(): bool {

		$this->deleteCalled = true;

		return $this->deleteResult;

	}

}
