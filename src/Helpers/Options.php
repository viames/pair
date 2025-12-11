<?php

namespace Pair\Helpers;

use Pair\Core\Env;
use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Orm\Database;

/**
 * Options helper class. Manages application options stored into DB.
 */
class Options {

	/**
	 * Singleton instance object.
	 */
	private static ?self $instance = null;

	/**
	 * DB connection object.
	 */
	protected Database $db;

	/**
	 * Option list.
	 */
	private ?array $list = null;

	/**
	 * Constructor, connects to database.
	 */
	private function __construct() {

		$this->db = Database::getInstance();

	}

	/**
	 * Private utility for casting to proper type.
	 *
	 * @param	mixed	The variable value.
	 * @param	string	the variable type (text, int, bool, custom, list).
	 */
	private function castTo(mixed $value, string $type): mixed {

		switch ($type) {

			default:
				$value = (string)$value;
				break;

			case 'int':
				$value = (int)$value;
				break;

			case 'bool':
				$value = (bool)$value;
				break;

			case 'password':
				if ($this->isCryptAvailable()) {
					$method = 'AES-128-CBC';
					$ivLength = openssl_cipher_iv_length($method);
					$decoded = base64_decode((string)$value, true);

					if (false === $decoded or strlen($decoded) <= $ivLength) {
						return (string)$value;
					}

					$iv = substr($decoded, 0, $ivLength);
					$cipherText = substr($decoded, $ivLength);

					$plain = openssl_decrypt($cipherText, $method, Env::get('OPTIONS_CRYPT_KEY'), OPENSSL_RAW_DATA, $iv);
					$value = (false === $plain) ? (string)$value : $plain;
				} else {
					$logger = Logger::getInstance();
					$logger->warning('OPTIONS_CRYPT_KEY value must be defined into .env configuration file.');
				}
				break;

			case 'list':
				break;

		}

		return $value;

	}

	/**
	 * Proxy method to return an option’s value.
	 *
	 * @param	string	The option’s name.
	 */
	public static function get(string $name): mixed {

		$self = static::getInstance();

		$self->populate();

		if (!static::exists($name)) {
			$logger = Logger::getInstance();
			$logger->warning('Option “'. $name .'” doesn’t exist.');
			return null;
		}

		return $self->list[$name]->value;

	}

	/**
	 * Returns all options as associative array.
	 *
	 * @return array
	 */
	public function getAll(): array {

		$this->populate();

		return $this->list;

	}

	/**
	 * Returns this object’s singleton.
	 */
	public static function getInstance(): self {

		if (null == self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Check if an option’s exists.
	 *
	 * @param	string	The option’s name.
	 */
	public static function exists(string $name): bool {

		$self = static::getInstance();
		$self->populate();

		return array_key_exists($name, $self->list);

	}

	/**
	 * Check wheter options crypt key has been defined into .env file.
	 */
	public function isCryptAvailable(): bool {

		return (strlen((string)Env::get('OPTIONS_CRYPT_KEY')) > 0);

	}

	/**
	 * Load from DB and sets all options to this object.
	 */
	private function populate(): void {

		if (is_array($this->list)) {
			return;
		}

		$res = Database::load('SELECT * FROM `options` ORDER BY `group`');

		$this->list = [];

		foreach ($res as $o) {

			// esplode le options
			if ('list'==$o->type) {

				$listItems = explode('|', $o->list_options);

				foreach ($listItems as $listEntry) {

					list ($val,$text)	= explode(',', $listEntry);

					$listItem = new \stdClass();

					$listItem->value = $val;
					$listItem->text  = $text;

					$o->listItems[] = $listItem;

				}

			}

			// casting
			$o->value = $this->castTo($o->value, $o->type);

			$this->list[$o->name] = $o;

		}

	}

	/**
	 * Refresh the option values by loading from DB.
	 */
	public function refresh(): void {

		$this->populate();

	}

	/**
	 * Proxy method to return an option’s value.
	 *
	 * @param	string	The option’s name.
	 * @param	string	The option’s value.
	 * @throws	PairException
	 */
	public static function set(string $name, mixed $value): bool {

		$ret = false;

		// instance of the singleton
		$self = static::getInstance();

		// populate all the options by reading db once
		$self->populate();

		// check if named option exists
		if (!array_key_exists($name, $self->list)) {
			throw new PairException('Option “'. $name .'” doesn’t exist', ErrorCodes::MISSING_CONFIGURATION);
		}

		switch ($self->list[$name]->type) {

			case 'bool';
				$value = $value ? 1 : 0;
				break;

			case 'password':
				if ($self->isCryptAvailable()) {
					$method = 'AES-128-CBC';
					$ivLength = openssl_cipher_iv_length($method);
					$iv = openssl_random_pseudo_bytes($ivLength); // generate secure random IV
					$cipherText = openssl_encrypt($value, $method, Env::get('OPTIONS_CRYPT_KEY'), OPENSSL_RAW_DATA, $iv);
					$value = base64_encode($iv . $cipherText);
				} else {
					throw new PairException('OPTIONS_CRYPT_KEY value must be set into .env configuration file', ErrorCodes::MISSING_CONFIGURATION);
				}
				break;

		}

		// update the value into db
		$ret = (bool)Database::run('UPDATE `options` SET `value` = ? WHERE `name` = ?', [$value, $name]);

		// update value into the singleton object
		$self->list[$name]->value = $value;

		return $ret;

	}

}
