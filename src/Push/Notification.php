<?php

namespace Pair\Push;

/**
 * Value object for a Web Push notification.
 */
class Notification {

	/**
	 * Notification title.
	 */
	public string $title;

	/**
	 * Notification body.
	 */
	public string $body;

	/**
	 * Optional URL to open on click.
	 */
	public ?string $url;

	/**
	 * Optional icon URL.
	 */
	public ?string $icon;

	/**
	 * Optional tag to collapse notifications.
	 */
	public ?string $tag;

	/**
	 * Custom data payload.
	 */
	public array $data;

	/**
	 * Constructor.
	 * 
	 * @param string $title The notification title.
	 * @param string $body The notification body.
	 * @param string|null $url Optional URL to open on click.
	 * @param string|null $icon Optional icon URL.
	 * @param string|null $tag Optional tag to collapse notifications.
	 * @param array $data Custom data payload.
	 */
	public function __construct(string $title, string $body, ?string $url = null, ?string $icon = null, ?string $tag = null, array $data = []) {

		$this->title	= $title;
		$this->body		= $body;
		$this->url		= $url;
		$this->icon		= $icon;
		$this->tag		= $tag;
		$this->data		= $data;

	}

	/**
	 * Returns an array payload compatible with the Service Worker.
	 * 
	 * @return array The payload array.
	 */
	public function toPayload(): array {

		$payload = [
			'title' => $this->title,
			'body' => $this->body,
		];

		if ($this->icon) {
			$payload['icon'] = $this->icon;
		}

		if ($this->tag) {
			$payload['tag'] = $this->tag;
		}

		$data = $this->data;

		if ($this->url) {
			$data['url'] = $this->url;
		}

		if (!empty($data)) {
			$payload['data'] = $data;
		}

		return $payload;

	}

}
