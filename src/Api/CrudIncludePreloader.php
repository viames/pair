<?php

namespace Pair\Api;

use Pair\Orm\ActiveRecord;

/**
 * Contract for bulk-loading CRUD includes before resource transformation.
 */
interface CrudIncludePreloader {

	/**
	 * Load requested includes for a collection of parent records.
	 *
	 * Return values must be grouped by include name, then by parent identifier,
	 * original collection key, or spl_object_id($parent).
	 *
	 * @param	ActiveRecord[]		$objects	Parent records being transformed.
	 * @param	string[]			$includes	Requested include names.
	 * @param	CrudResourceConfig	$config		Normalized CRUD resource config.
	 * @return	array<string, array<int|string, mixed>>
	 */
	public function preload(array $objects, array $includes, CrudResourceConfig $config): array;

}
