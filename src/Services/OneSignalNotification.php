<?php

namespace Pair\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use Pair\Core\Env;
use Pair\Helpers\Log;
use Pair\Models\User;
use Pair\Orm\Collection;

use stdClass;

/**
 * Class for sending push notifications via OneSignal service.
 */
class OneSignalNotification {

	/**
	 * API endpoint for notifications.
	 */
	const API_URL = 'https://onesignal.com/api/v1/notifications';

	/**
	 * Cancels a scheduled notification.
	 *
	 * @param string $notificationId The ID of the notification to cancel.
	 * @return bool True if successful, false otherwise.
	 */
	public static function cancel(string $notificationId): bool {

		$appId = Env::get('ONE_SIGNAL_APP_ID');
		$apiKey = Env::get('ONE_SIGNAL_REST_API_KEY');

		if (!$appId or !$apiKey) {
			Log::error('OneSignal: missing credentials for cancellation');
			return false;
		}

		$client = new Client();

		try {
			$response = $client->delete(self::API_URL . '/' . $notificationId . '?app_id=' . $appId, [
				'headers' => [
					'Authorization' => 'Basic ' . $apiKey
				],
				'timeout' => 10
			]);

			$result = json_decode($response->getBody());
			return isset($result->success) && $result->success;

		} catch (GuzzleException $e) {
			Log::error('OneSignal cancel error: ' . $e->getMessage());
			return false;
		}

	}

	/**
	 * Executes the HTTP request to OneSignal.
	 *
	 * @param array $fields The payload to send.
	 * @return stdClass|null The API response or NULL on failure.
	 */
	private static function request(array $fields): ?stdClass {

		$apiKey = Env::get('ONE_SIGNAL_REST_API_KEY');

		if (!$apiKey) {
			Log::error('OneSignal: missing API Key (ONE_SIGNAL_REST_API_KEY)');
			return null;
		}

		$client = new Client();

		try {
			$response = $client->post(self::API_URL, [
				'headers' => [
					'Authorization' => 'Basic ' . $apiKey,
					'Content-Type'  => 'application/json; charset=utf-8',
				],
				'json' => $fields,
				'timeout' => 10
			]);

			return json_decode($response->getBody());

		} catch (GuzzleException $e) {
			Log::error('OneSignal request error: ' . $e->getMessage());
			return null;
		}

	}

	/**
	 * Sends a push notification to a list of users identified by their UUIDs.
	 *
	 * @param array        $userUuids List of recipient user UUIDs.
	 * @param string|array $title     Notification title (string or array for localization).
	 * @param string|array $message   Message content (string or array for localization).
	 * @param array        $data      Optional additional data to attach to the notification.
	 * @param array        $options   Additional options (e.g. 'url', 'big_picture', 'buttons').
	 * @return stdClass|null          OneSignal response object or NULL on error.
	 */
	public static function send(array $userUuids, string|array $title, string|array $message, array $data = [], array $options = []): ?stdClass {

		$appId = Env::get('ONE_SIGNAL_APP_ID');

		// checks if App ID is present
		if (!$appId) {
			Log::error('OneSignal: missing App ID (ONE_SIGNAL_APP_ID)');
			return null;
		}

		// prepares content and headings supporting both string (en/it default) and array (custom locales)
		$content = is_array($message) ? $message : ['en' => $message, 'it' => $message];
		$headings = is_array($title) ? $title : ['en' => $title, 'it' => $title];

		// builds the request payload
		$fields = [
			'app_id' => $appId,
			'include_external_user_ids' => $userUuids,
			'contents' => $content,
			'headings' => $headings,
			'name'	=> Env::get('APP_NAME')
		];

		// merges additional options (e.g. url, big_picture) into main fields
		if (!empty($options)) {
			$fields = array_merge($fields, $options);
		}

		// adds any extra data if present
		if (!empty($data)) {
			$fields['data'] = $data;
		}

		return self::request($fields);

	}

	/**
	 * Sends a push notification to a segment of users.
	 *
	 * @param string       $segment Segment name (e.g. "Active Users").
	 * @param string|array $title   Notification title.
	 * @param string|array $message Message content.
	 * @param array        $data    Optional additional data.
	 * @param array        $options Additional options.
	 * @return stdClass|null
	 */
	public static function sendToSegment(string $segment, string|array $title, string|array $message, array $data = [], array $options = []): ?stdClass {

		$appId = Env::get('ONE_SIGNAL_APP_ID');

		if (!$appId) {
			Log::error('OneSignal: missing App ID (ONE_SIGNAL_APP_ID)');
			return null;
		}

		$content = is_array($message) ? $message : ['en' => $message, 'it' => $message];
		$headings = is_array($title) ? $title : ['en' => $title, 'it' => $title];

		$fields = [
			'app_id' => $appId,
			'included_segments' => [$segment],
			'contents' => $content,
			'headings' => $headings,
			'name'	=> Env::get('APP_NAME')
		];

		if (!empty($options)) {
			$fields = array_merge($fields, $options);
		}

		if (!empty($data)) {
			$fields['data'] = $data;
		}

		return self::request($fields);

	}

	/**
	 * Sends a push notification to a single User object.
	 *
	 * @param User         $user    Recipient user object.
	 * @param string|array $title   Notification title.
	 * @param string|array $message Message content.
	 * @param array        $data    Optional additional data.
	 * @param array        $options Additional options (e.g. 'url').
	 * @return stdClass|null        OneSignal response or NULL if user has no UUID or on error.
	 */
	public static function sendToUser(User $user, string|array $title, string|array $message, array $data = [], array $options = []): ?stdClass {

		// checks if the user has a valid UUID for OneSignal
		if (empty($user->uuid)) {
			return null;
		}

		return self::send([$user->uuid], $title, $message, $data, $options);

	}

	/**
	 * Sends a push notification to a collection or array of User objects.
	 *
	 * @param Collection|array $users   List of User objects.
	 * @param string|array     $title   Notification title.
	 * @param string|array     $message Message content.
	 * @param array            $data    Optional additional data.
	 * @param array            $options Additional options (e.g. 'url').
	 * @return stdClass|null            OneSignal response or NULL if no valid recipients found.
	 */
	public static function sendToUsers(Collection|array $users, string|array $title, string|array $message, array $data = [], array $options = []): ?stdClass {

		$uuids = [];

		// extracts valid UUIDs from users
		foreach ($users as $user) {
			if ($user instanceof User and !empty($user->uuid)) {
				$uuids[] = $user->uuid;
			}
		}

		// if there are no recipients, sends nothing
		if (empty($uuids)) {
			return null;
		}

		return self::send($uuids, $title, $message, $data, $options);

	}

}