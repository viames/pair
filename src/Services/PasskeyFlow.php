<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Owns a short-lived cookie-free PHP session used only for native passkey challenges.
 */
class PasskeyFlow {

	private const PREFIX = 'pair-passkey-';

	/**
	 * Active opaque flow identifier.
	 */
	private string $id;

	/**
	 * Start a new flow or resume a previously issued flow identifier.
	 */
	public function __construct(?string $id = null) {

		if (PHP_SESSION_ACTIVE === session_status()) {
			session_write_close();
		}

		$id = trim((string)$id);

		if ('' === $id) {
			$id = self::PREFIX . bin2hex(random_bytes(32));
		} else if (!preg_match('/^' . self::PREFIX . '[a-f0-9]{64}$/', $id)) {
			throw new PairException('Invalid passkey flow ID', ErrorCodes::VALIDATION_FAILED);
		}

		$this->id = $id;
		session_id($id);

		if (!session_start([
			'use_cookies' => 0,
			'use_only_cookies' => 1,
			'use_trans_sid' => 0,
		])) {
			throw new PairException('Unable to start passkey flow', ErrorCodes::INVALID_REQUEST);
		}

	}

	/**
	 * Close the challenge session while preserving it for the verification request.
	 */
	public function close(): void {

		if (PHP_SESSION_ACTIVE === session_status() and session_id() === $this->id) {
			session_write_close();
		}

	}

	/**
	 * Destroy the challenge session after verification or failure.
	 */
	public function destroy(): void {

		if (PHP_SESSION_ACTIVE !== session_status() or session_id() !== $this->id) {
			return;
		}

		$_SESSION = [];
		session_destroy();

	}

	/**
	 * Return the opaque identifier that the native client must echo during verification.
	 */
	public function id(): string {

		return $this->id;

	}

}
