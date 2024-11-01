<?php

namespace Pair\Services;

use Pair\Core\Config;
use Pair\Exceptions\PairException;

/**
 * This class sends messages to Telegram users that have started a chat with the bot.
 */
class TelegramNotifier {

	/**
	 * Telegram bot token.
	 */
	private ?string $botToken = NULL;

	/**
	 * Constructor sets the bot token by parameter or by configuration.
	 *
	 * @throws PairException
	 */
	public function __construct(?string $botToken = NULL) {

		if (!$botToken and !Config::get('TELEGRAM_BOT_TOKEN')) {
			throw new PairException('Telegram bot token not set in class constructor nor in configuration (TELEGRAM_BOT_TOKEN)');
		}

		$this->botToken = $botToken ?? Config::get('TELEGRAM_BOT_TOKEN');

	}

	/**
	 * Get the base URL for Telegram API with the bot token.
	 *
	 * @throws PairException
	 */
	private function getBaseUrl(): string {

		if (!$this->botToken) {
			throw new PairException('Telegram bot token not set in configuration nor in class constructor');
		}

		return 'https://api.telegram.org/bot' . $this->botToken;

	}

	/**
	 * Get the chat ID of a user by username string starting with “@”.
	 */
	public function getChatIdByUsername(string $username): ?int {

		$url = $this->getBaseUrl() . '/getUpdates';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		curl_close($ch);

		$updates = json_decode($response, true);

		// no updates found
		if (!isset($updates['result']) or !is_array($updates['result'])) {
			return NULL;
		}

		foreach ($updates['result'] as $update) {
			if (isset($update['message']['chat']['username']) and $update['message']['chat']['username'] === ltrim($username, '@')) {
				return (int)$update['message']['chat']['id'];
			}
		}

		// username not found
		return NULL;
	}

	/**
	 * Send a message to Telegram user by numeric chat ID.
	 *
	 * @throws PairException
	 */
	public function sendMessage(int $chatId, string $message): void {

		$url = $this->getBaseUrl() . '/sendMessage';

		$postData = [
			'chat_id' => $chatId,
			'text'    => $message
		];

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);

		if (FALSE === $response) {
			throw new PairException('Telegram API response error');
		}

		$json = json_decode($response);

		if (!is_object($json) or !isset($json->ok)) {
			throw new PairException('Telegram API response error: ' . $response);
		}

		if (!$json->ok) {
			throw new PairException('Telegram API error: ' . $json->description . ' (' . $json->error_code . ')');
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if (!$httpCode or $httpCode !== 200) {
			throw new PairException('Telegram API not reachable: HTTP ' . $httpCode);
		}

		curl_close($ch);

	}

}