<?php

declare(strict_types=1);

namespace Pair\Data;

use Pair\Orm\ActiveRecord;

/**
 * Convert persistence records into explicit read models.
 */
final class RecordMapper {

	/**
	 * Build a read model from a record using an explicit mapper class.
	 *
	 * @param	string	$readModelClass	Fully qualified read-model class name.
	 */
	public static function map(ActiveRecord $record, string $readModelClass): ReadModel {

		if (!class_exists($readModelClass)) {
			throw new \InvalidArgumentException('Read model class ' . $readModelClass . ' was not found');
		}

		if (!is_subclass_of($readModelClass, ReadModel::class)) {
			throw new \InvalidArgumentException('Read model class ' . $readModelClass . ' must implement ' . ReadModel::class);
		}

		if (!is_subclass_of($readModelClass, MapsFromRecord::class)) {
			throw new \InvalidArgumentException('Read model class ' . $readModelClass . ' must implement ' . MapsFromRecord::class);
		}

		return $readModelClass::fromRecord($record);

	}

}
