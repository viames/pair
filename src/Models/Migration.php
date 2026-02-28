<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

class Migration extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * This property maps “file” column.
	 */
	protected string $file;

	/**
	 * This property maps “source” column.
	 */
	protected ?string $source = null;

	/**
	 * This property maps “query_index” column.
	 */
	protected ?int $queryIndex;

	/**
	 * This property maps “description” column.
	 */
	protected ?string $description;

	/**
	 * This property maps “affected_rows” column.
	 */
	protected ?int $affectedRows;

	/**
	 * This property maps “result” column.
	 */
	protected ?bool $result;

	/**
	 * This property maps “created_at” column.
	 */
	protected \DateTime $createdAt;

	/**
	 * This property maps “updated_at” column.
	 */
	protected \DateTime $updatedAt;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'migrations';

	/**
	 * Allowed values for source column.
	 */
	const SOURCE_APP = 'app';
	const SOURCE_PAIR = 'pair';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Initializes the ActiveRecord-specific types of this object’s properties.
	 */
	protected function _init(): void {

		$this->bindAsBoolean('result');

		$this->bindAsDatetime('createdAt', 'updatedAt');

		$this->bindAsInteger('id', 'queryIndex', 'affectedRows');

	}

	/**
	 * Ensures backward compatibility when DB column source is not available yet.
	 */
	protected function afterPrepareData(\stdClass &$dbObj) {

		if (!Database::getInstance()->describeColumn(self::TABLE_NAME, 'source')) {
			unset($dbObj->source);
		}

	}

	/**
	 * Returns the time spent to execute the migration.
	 */
	public function executionTime(): string {

		$diff = $this->updatedAt->getTimestamp() - $this->createdAt->getTimestamp();

		$hours = floor($diff / 3600);
		$minutes = floor(($diff - $hours * 3600) / 60);
		$seconds = $diff - $hours * 3600 - $minutes * 60;

		return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

	}

}