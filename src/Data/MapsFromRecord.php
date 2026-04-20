<?php

declare(strict_types=1);

namespace Pair\Data;

use Pair\Orm\ActiveRecord;

/**
 * Contract for read models built explicitly from persistence records.
 */
interface MapsFromRecord {

	/**
	 * Build the read model from an ActiveRecord instance.
	 */
	public static function fromRecord(ActiveRecord $record): static;

}
