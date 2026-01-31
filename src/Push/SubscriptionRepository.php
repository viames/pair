<?php

namespace Pair\Push;

use Pair\Models\PushSubscription;
use Pair\Orm\Collection;
use Pair\Orm\Database;

/**
 * Database repository for push subscriptions.
 */
class SubscriptionRepository {

	/**
	 * Deletes a subscription by endpoint.
	 * 
	 * @param string $endpoint The subscription endpoint.
	 */
	public function deleteByEndpoint(string $endpoint): void {

		$endpoint = trim($endpoint);
		if (!$endpoint) {
			return;
		}

		Database::run('DELETE FROM `push_subscriptions` WHERE `endpoint` = ?', [$endpoint]);

	}

	/**
	 * Returns active subscriptions for a user.
	 * 
	 * @param int $userId The user ID.
	 * @return Collection The list of subscriptions.
	 */
	public function listByUser(int $userId): Collection {

		$query = 'SELECT *
			FROM `push_subscriptions`
			WHERE `user_id` = ?
			AND `revoked_at` IS NULL';

		return PushSubscription::getObjectsByQuery($query, [$userId]);

	}

	/**
	 * Marks a subscription as revoked.
	 * 
	 * @param string $endpoint The subscription endpoint.
	 */
	public function revokeByEndpoint(string $endpoint): void {

		$endpoint = trim($endpoint);
		if (!$endpoint) {
			return;
		}

		$query = 'UPDATE `push_subscriptions`
			SET `revoked_at` = NOW(), `updated_at` = NOW()
			WHERE `endpoint` = ?';

		Database::run($query, [$endpoint]);

	}

	/**
	 * Insert or update a subscription by endpoint for a user.
	 * 
	 * @param int|null $userId The user ID, or null for anonymous.
	 * @param array $subscription The subscription data.
	 * @param string|null $userAgent The user agent string.
	 * @throws \InvalidArgumentException If the subscription data is invalid.
	 */
	public function upsert(?int $userId, array $subscription, ?string $userAgent): void {

		// extract subscription fields
		$endpoint = trim((string)($subscription['endpoint'] ?? ''));
		$keys = (array)($subscription['keys'] ?? []);
		$p256dh = trim((string)($keys['p256dh'] ?? $subscription['p256dh'] ?? ''));
		$auth = trim((string)($keys['auth'] ?? $subscription['auth'] ?? ''));

		// validate required fields
		if (!$endpoint or !$p256dh or !$auth) {
			throw new \InvalidArgumentException('Subscription payload is missing required fields.');
		}

		// truncate user agent to 255 characters
		$userAgent = $userAgent ? substr($userAgent, 0, 255) : null;

		$query = 'INSERT INTO `push_subscriptions`
			(`user_id`, `endpoint`, `p256dh`, `auth`, `user_agent`, `created_at`, `updated_at`, `last_seen_at`, `revoked_at`)
			VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NULL)
			ON DUPLICATE KEY UPDATE
				`user_id` = VALUES(`user_id`),
				`p256dh` = VALUES(`p256dh`),
				`auth` = VALUES(`auth`),
				`user_agent` = VALUES(`user_agent`),
				`updated_at` = NOW(),
				`last_seen_at` = NOW(),
				`revoked_at` = NULL';

		Database::run($query, [$userId, $endpoint, $p256dh, $auth, $userAgent]);

	}

}
