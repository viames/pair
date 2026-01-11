<?php

namespace Pair\Push;

/**
 * Dispatches notifications to user subscriptions.
 */
class PushDispatcher {

	private SubscriptionRepository $repository;

	private WebPushSender $sender;

	/**
	 * Constructor.
	 * 
	 * @param SubscriptionRepository|null $repository The subscription repository. If null, a default repository is used.
	 * @param WebPushSender|null $sender The Web Push sender. If null, a default sender is used.
	 */
	public function __construct(?SubscriptionRepository $repository = null, ?WebPushSender $sender = null) {

		$this->repository = $repository ?? new SubscriptionRepository();
		$this->sender = $sender ?? new WebPushSender();

	}

	/**
	 * Sends a notification to all subscriptions of a user.
	 *
	 * @param int $userId The user ID.
	 * @param Notification $notification The notification to send.
	 * @return DeliveryResult[]
	 */
	public function sendToUser(int $userId, Notification $notification): array {

		$results = [];
		$subscriptions = $this->repository->listByUser($userId);

		foreach ($subscriptions as $subscription) {
			$result = $this->sender->send($notification, $subscription);
			$results[] = $result;

			if ($result->shouldDeleteSubscription) {
				$this->repository->deleteByEndpoint($result->endpoint);
			}
		}

		return $results;

	}

}
