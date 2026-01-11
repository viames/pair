<?php

namespace Pair\Push;

use Pair\Core\Env;

/**
 * Reads VAPID configuration from environment.
 */
class VapidConfig {

	/**
	 * Returns the VAPID public key.
	 * 
	 * @return string The VAPID public key.
	 * @throws \RuntimeException If the environment variable is missing.
	 */
	public function publicKey(): string {

		return $this->read('PUSH_VAPID_PUBLIC');

	}

	/**
	 * Returns the VAPID private key.
	 * 
	 * @return string The VAPID private key.
	 * @throws \RuntimeException If the environment variable is missing.
	 */
	public function privateKey(): string {

		return $this->read('PUSH_VAPID_PRIVATE');

	}

	/**
	 * Returns the VAPID subject (contact email or URL).
	 * 
	 * @return string The VAPID subject.
	 * @throws \RuntimeException If the environment variable is missing.
	 */
	public function subject(): string {

		return $this->read('PUSH_VAPID_SUBJECT');

	}

	/**
	 * Reads a value from the environment.
	 * 
	 * @param string $key The environment variable key.
	 * @return string The environment variable value.
	 * @throws \RuntimeException If the environment variable is missing.
	 */
	private function read(string $key): string {

		$value = trim((string)Env::get($key));

		if ('' === $value) {
			throw new \RuntimeException('Missing environment value: ' . $key);
		}

		return $value;

	}

}
