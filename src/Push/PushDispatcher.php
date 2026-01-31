<?php

namespace Pair\Push;

/**
 * Dispatches notifications to user subscriptions.
 */
class PushDispatcher {

	/**
	 * The subscription repository, used to retrieve and manage subscriptions.
	 */
	private SubscriptionRepository $repository;

	/**
	 * The Web Push sender, used to send notifications.
	 */
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

		// each User can have multiple subscriptions
		$subscriptions = $this->repository->listByUser($userId);

		// send notification to each subscription of the user
		foreach ($subscriptions as $subscription) {
			$result = $this->sender->send($notification, $subscription);
			$results[] = $result;

			// if the subscription is no longer valid, delete it
			if ($result->shouldDeleteSubscription) {
				$this->repository->deleteByEndpoint($result->endpoint);
			}
		}

		return $results;

	}

}
