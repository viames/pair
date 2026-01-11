<?php

namespace Pair\Push;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends Web Push notifications using the VAPID authentication and Minishlink library.
 * Requires "minishlink/web-push" package.
 */
class WebPushSender {

	private VapidConfig $config;

	private int $ttl = 3600;

	/**
	 * Constructor.
	 * 
	 * @param VapidConfig|null $config The VAPID configuration. If null, a default configuration is used.
	 * @throws \RuntimeException If the WebPush library is not installed.
	 */
	public function __construct(?VapidConfig $config = null) {

		// check whether WebPush package is available
		if (!class_exists(WebPush::class)) {
			throw new \RuntimeException('WebPush library is not installed.');
		}

		$this->config = $config ?? new VapidConfig();

	}

	/**
	 * Sends a notification to a single subscription. This method does not handle subscription deletion.
	 * 
	 * @param Notification $notification The notification to send.
	 * @param PushSubscription $subscription The subscription data.
	 * @return DeliveryResult The result of the delivery.
	 */
	public function send(Notification $notification, PushSubscription $subscription): DeliveryResult {

		$endpoint = (string)$subscription->endpoint;
		$result = new DeliveryResult($endpoint);

		try {

			$authConfig = [
				'VAPID' => [
					'subject' => $this->config->subject(),
					'publicKey' => $this->config->publicKey(),
					'privateKey' => $this->config->privateKey(),
				]
			];

			$webPush = new WebPush($authConfig);

			$sub = Subscription::create([
				'endpoint' => $subscription->endpoint,
				'publicKey' => $subscription->p256dh,
				'authToken' => $subscription->auth,
			]);

			$payload = json_encode($notification->toPayload());

			$report = null;
			if (method_exists($webPush, 'sendOneNotification')) {
				$report = $webPush->sendOneNotification($sub, $payload, ['TTL' => $this->ttl]);
			} else {
				$webPush->sendNotification($sub, $payload, ['TTL' => $this->ttl]);
				$reports = $webPush->flush();
				if (is_array($reports) and count($reports)) {
					$report = array_shift($reports);
				}
			}

			if ($report and method_exists($report, 'isSuccess') and $report->isSuccess()) {
				$result->success = true;
				return $result;
			}

			if ($report and method_exists($report, 'getResponse')) {
				$response = $report->getResponse();
				if ($response and method_exists($response, 'getStatusCode')) {
					$result->statusCode = (int)$response->getStatusCode();
				}
			}

			if ($report and method_exists($report, 'getReason')) {
				$result->error = $report->getReason();
			} else if (!$report) {
				$result->error = 'Push report not available.';
			}

			if (in_array($result->statusCode, [404, 410], true)) {
				$result->shouldDeleteSubscription = true;
			}

		} catch (\Throwable $e) {

			$result->error = $e->getMessage();

		}

		return $result;

	}

}
