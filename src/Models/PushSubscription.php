<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;

class PushSubscription extends ActiveRecord {

	/**
	 * The table primary key.
	 */
	protected int $id;

	/**
	 * The user ID this subscription belongs to. Can be null for guest users.
	 */
	protected ?int $userId = null;

	/**
	 * The push service URL.
	 */
	protected string $endpoint;

	/**
	 * The “p256dh” key.
	 */
	protected string $p256dh;

	/**
	 * The “auth” key.
	 */
	protected string $auth;

	/**
	 * The user agent string.
	 */
	protected ?string $userAgent = null;

	/**
	 * The date and time when the subscription was revoked.
	 */
	protected ?DateTime $revokedAt = null;

	/**
	 * The date and time when the subscription was last seen.
	 */
	protected ?DateTime $lastSeenAt = null;

	/**
	 * The date and time when the subscription was created.
	 */
	protected DateTime $createdAt;

	/**
	 * The date and time when the subscription was last updated.
	 */
	protected DateTime $updatedAt;

	/**
	 * Name of the related db table.
	 */
	const TABLE_NAME = 'push_subscriptions';

	/**
	 * Name of the primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['userId'];

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'				=> ['int unsigned', 'NO', 'PRI', NULL, 'auto_increment'],
		'user_id'			=> ['int unsigned', 'YES', 'MUL', 'NULL', ''],
		'endpoint'			=> ['TEXT', 'NO', '', '', ''],
		'p256dh'			=> ['varchar(255)', 'NO', '', '', ''],
		'auth'				=> ['varchar(255)', 'NO', '', '', ''],
		'user_agent'		=> ['varchar(255)', 'YES', '', 'NULL', ''],
		'revoked_at'		=> ['datetime', 'YES', '', 'NULL', ''],
		'last_seen_at'		=> ['datetime', 'YES', '', 'NULL', ''],
		'created_at'		=> ['datetime', 'NO', '', '', ''],
		'updated_at'		=> ['datetime', 'NO', '', '', '']
	];

	protected function _init(): void {

		$this->bindAsDatetime('revokedAt', 'lastSeenAt', 'createdAt', 'updatedAt');

		$this->bindAsInteger('id', 'userId');

	}

}