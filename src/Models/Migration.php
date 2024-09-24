<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;

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
	protected ?int $result;

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
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init(): void {

		$this->bindAsDatetime('createdAt', 'updatedAt');

		$this->bindAsInteger('id', 'queryIndex', 'affectedRows', 'result');

	}

}