<?php

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\PairException;
use Pair\Exceptions\ErrorCodes;

/**
 * This class sends messages and images to Telegram users that have started a chat with the bot.
 */
class TelegramSender {

	/**
	 * Telegram bot token.
	 */
	private ?string $botToken = null;

	/**
	 * Constructor sets the bot token by parameter or by configuration.
	 *
	 * @throws PairException
	 */
	public function __construct(?string $botToken = null) {

		if (!$botToken and !Env::get('TELEGRAM_BOT_TOKEN')) {
			throw new PairException('Telegram bot token not set in class constructor nor in configuration (TELEGRAM_BOT_TOKEN)');
		}

		$this->botToken = $botToken ?? Env::get('TELEGRAM_BOT_TOKEN');

	}

	/**
	 * Check if the bot token is set.
	 */
	public function botTokenSet(): bool {

		return (bool)$this->botToken;

	}

	/**
	 * Get the chat ID of a user by username string starting with “@”.
	 */
	public function chatId(string $username): ?int {

		$url = $this->getBaseUrl() . '/getUpdates';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);

		$updates = json_decode($response, true);

		// no updates found
		if (!isset($updates['result']) or !is_array($updates['result'])) {
			return null;
		}

		foreach ($updates['result'] as $update) {
			if (isset($update['message']['chat']['username']) and $update['message']['chat']['username'] === ltrim($username, '@')) {
				return (int)$update['message']['chat']['id'];
			}
		}

		// username not found
		return null;

	}

	/**
	 * Get the link to a Telegram chat by numeric chat ID.
	 * Returns an empty string if the username is not found.
	 */
	public function link(int $chatId): string {

		$username = $this->username($chatId);

		return $username ? 'https://t.me/' . ltrim($username, '@') : '';

	}

	/**
	 * Get the base URL for Telegram API with the bot token.
	 *
	 * @throws PairException
	 */
	private function getBaseUrl(): string {

		if (!$this->botToken) {
			throw new PairException('Telegram bot token not set in configuration nor in class constructor', ErrorCodes::TELEGRAM_FAILURE);
		}

		return 'https://api.telegram.org/bot' . $this->botToken;

	}

	/**
	 * Send a message to Telegram user by numeric chat ID.
	 *
	 * @throws PairException
	 */
	public function message(int $chatId, string $message, ?string $parseMode = null): void {

		$postData = [
			'chat_id' => $chatId,
			'text'    => $message
		];

		if (in_array($parseMode, ['HTML', 'Markdown', 'MarkdownV2'])) {
			$postData['parse_mode'] = $parseMode;
		}

		$this->send($chatId, 'sendMessage', $postData);

	}

	/**
	 * Send an image to Telegram user by numeric chat ID by choosing the method based on the image path.
	 * If the image path starts with “http” it will be sent as a URL, otherwise as a local file.
	 *
	 * @throws PairException
	 */
	public function photo(int $chatId, string $imagePath, ?string $caption = null): void {

		if ($chatId < 1) {
			throw new PairException('Telegram Chat ID value not valid (' . $chatId . ')', ErrorCodes::TELEGRAM_FAILURE);
		}

		$postData = ['chat_id' => $chatId];

		// image caption is optional
		$caption = trim($caption ?? '');
		if ($caption) {
			$postData['caption'] = $caption;
		}

		// if the image is not a URL, it must be a local file path
		if (!str_starts_with($imagePath, 'http')) {

			if (!file_exists($imagePath) or !is_readable($imagePath)) {
				throw new PairException('Image file not found or not readable: ' . $imagePath, ErrorCodes::TELEGRAM_FAILURE);
			}

			$postData['photo'] = new \CURLFile(
				realpath($imagePath),
				mime_content_type($imagePath),
				basename($imagePath)
			);

		} else {

			// if it’s a URL, we can send it directly
			$postData['photo'] = $imagePath;

		}

		// send the image to Telegram
		$this->send($chatId, 'sendPhoto', $postData);

	}

	/**
	 * Common method to send data to Telegram API.
	 *
	 * @throws PairException
	 */
	private function send(int $chatId, string $method, array $postData): void {

		if (!$this->botToken) {
			throw new PairException('Telegram bot token not set in configuration nor in class constructor', ErrorCodes::TELEGRAM_FAILURE);
		}

		if ($chatId < 1) {
			throw new PairException('Telegram Chat ID value not valid (' . $chatId . ')', ErrorCodes::TELEGRAM_FAILURE);
		}

		$url = $this->getBaseUrl() . '/' . $method;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);

		if (false === $response) {
			throw new PairException('Telegram API response error', ErrorCodes::TELEGRAM_FAILURE);
		}

		$json = json_decode($response);

		if (!is_object($json) or !isset($json->ok)) {
			throw new PairException('Telegram API response error: ' . $response, ErrorCodes::TELEGRAM_FAILURE);
		}

		if (!$json->ok) {
			$msg = 400 == $json->error_code
				? 'Telegram API error: ' . $json->description
				: 'Telegram API error: ' . $json->error_code . ' - ' . $json->description;

			// if the chat ID is not valid, it will return an error
			if (isset($json->parameters->retry_after)) {
				$msg .= ' (retry after ' . $json->parameters->retry_after . ' seconds)';
			}

			// throw an exception with the error message
			throw new PairException($msg, ErrorCodes::TELEGRAM_FAILURE);
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if (!$httpCode or $httpCode !== 200) {
			throw new PairException('Telegram API not reachable: HTTP ' . $httpCode, ErrorCodes::TELEGRAM_FAILURE);
		}

	}

	/**
	 * Get the username of a Telegram user by their chat ID.
	 * Returns null if the user does not have a username or if the chat ID is invalid.
	 */
	public function username(int $chatId): ?string {

		if ($chatId < 1) {
			return null;
		}

		$url = $this->getBaseUrl() . '/getChat?chat_id=' . $chatId;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);

		$data = json_decode($response, true);

		if (isset($data['ok']) and $data['ok'] and isset($data['result']['username'])) {
			return '@' . $data['result']['username'];
		}

		return null;

	}

}
