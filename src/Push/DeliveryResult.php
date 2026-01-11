<?php

namespace Pair\Push;

/**
 * Delivery result for a single push subscription.
 */
class DeliveryResult {

	public bool $success = false;

	public ?int $statusCode = null;

	public ?string $error = null;

	public string $endpoint;

	public bool $shouldDeleteSubscription = false;

	/**
	 * Constructor.
	 * 
	 * @param string $endpoint The subscription endpoint.
	 * @param bool $success Whether the delivery was successful.
	 * @param int|null $statusCode The HTTP status code, if available.
	 * @param string|null $error The error message, if any.
	 * @param bool $shouldDeleteSubscription Whether the subscription should be deleted.
	 */
	public function __construct(
		string $endpoint,
		bool $success = false,
		?int $statusCode = null,
		?string $error = null,
		bool $shouldDeleteSubscription = false
	) {

		$this->endpoint = $endpoint;
		$this->success = $success;
		$this->statusCode = $statusCode;
		$this->error = $error;
		$this->shouldDeleteSubscription = $shouldDeleteSubscription;

	}

}
