<?php

namespace Pair;

class UserRemember extends ActiveRecord {
	
	/**
	 * This property maps “user_id” column.
	 * @var int
	 */
	protected $userId;

	/**
	 * This property maps “remember_me column.
	 * @var string
	 */
	protected $rememberMe;
	
	/**
	 * This property maps “created_at” column.
	 * @var DateTime
	 */
	protected $description;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'users_remembers';
	
	/**
	 * Name of primary key db field.
	 * @var array
	 */
	const TABLE_KEY = ['user_id', 'remember_me'];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsDatetime('createdAt');

		$this->bindAsInteger('user_id');

	}

}