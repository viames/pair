<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;

class ScheduleLog extends ActiveRecord {

	/**
	 * This property maps “id” column.
	 */
	protected int $id;

	/**
	 * This property maps “job” column.
	 */
	protected string $job;

	/**
	 * This property maps “result” column.
	 */
	protected bool $result;

	/**
	 * This property maps “info” column.
	 */
	protected ?string $info;

	/**
	 * This property maps “created_at” column.
	 */
	protected \DateTime $createdAt;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'schedule_logs';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsBoolean('result');

		$this->bindAsDatetime('createdAt');

		$this->bindAsInteger('id');

	}

	/**
	 * Scrive un record di log.
	 */
	public static function add(string $job, bool $result, ?string $info = null): bool {

		$log = new self();

		$log->job = substr($job, 0, 30);
		$log->result = $result;
		$log->info = $info ? substr($info, 0, 200) : null;

		return $log->store();

	}

}