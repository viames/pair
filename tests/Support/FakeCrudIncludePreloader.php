<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Api\CrudIncludePreloader;
use Pair\Api\CrudResourceConfig;
use Pair\Orm\ActiveRecord;

/**
 * Test preloader used to verify bulk CRUD include loading.
 */
final class FakeCrudIncludePreloader implements CrudIncludePreloader {

	/**
	 * Number of preload invocations.
	 */
	public static int $calls = 0;

	/**
	 * Last include names requested by CrudController.
	 *
	 * @var	string[]
	 */
	public static array $lastIncludes = [];

	/**
	 * Last parent identifiers requested by CrudController.
	 *
	 * @var	array<int, int|string|array|null>
	 */
	public static array $lastParentIds = [];

	/**
	 * Relation values keyed by include name and parent key.
	 *
	 * @var	array<string, array<int|string, mixed>>
	 */
	private static array $relations = [];

	/**
	 * Clear static preloader state between tests.
	 */
	public static function reset(): void {

		self::$calls = 0;
		self::$lastIncludes = [];
		self::$lastParentIds = [];
		self::$relations = [];

	}

	/**
	 * Seed one relation value returned by the fake preloader.
	 *
	 * @param	string		$include	Include name.
	 * @param	int|string	$parentKey	Parent id, original collection key, or spl_object_id().
	 * @param	mixed		$related	Relation value to return.
	 */
	public static function seed(string $include, int|string $parentKey, mixed $related): void {

		self::$relations[$include][$parentKey] = $related;

	}

	/**
	 * Return the preloaded relation map for the requested includes.
	 *
	 * @param	ActiveRecord[]		$objects	Parent records being transformed.
	 * @param	string[]			$includes	Requested include names.
	 * @param	CrudResourceConfig	$config		Normalized CRUD resource config.
	 * @return	array<string, array<int|string, mixed>>
	 */
	public function preload(array $objects, array $includes, CrudResourceConfig $config): array {

		self::$calls++;
		self::$lastIncludes = $includes;
		self::$lastParentIds = array_map(static fn (ActiveRecord $object): int|string|array|null => $object->getId(), $objects);

		return array_intersect_key(self::$relations, array_flip($includes));

	}

}
