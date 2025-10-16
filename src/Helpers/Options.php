<?php

namespace Pair\Helpers;

use Pair\Core\Env;
use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Orm\Database;

class Options {

	/**
	 * Singleton instance object.
	 */
	private static ?self $instance = NULL;

	/**
	 * DB connection object.
	 */
	protected Database $db;

	/**
	 * Option list.
	 */
	private ?array $list = NULL;

	/**
	 * Constructor, connects to database.
	 */
	private function __construct() {

		$this->db = Database::getInstance();

	}

	/**
	 * Returns this object’s singleton.
	 */
	public static function getInstance(): self {

		if (NULL == self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;

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
			return NULL;
		}

		return $self->list[$name]->value;

	}

	/**
	 * Proxy method to return an option’s value.
	 *
	 * @param	string	The option’s name.
	 * @param	string	The option’s value.
	 * @throws	PairException
	 */
	public static function set(string $name, mixed $value): bool {

		$ret = FALSE;

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
					$value = openssl_encrypt($value, 'AES128', Env::get('OPTIONS_CRYPT_KEY'));
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

	/**
	 * Refresh the option values by loading from DB.
	 */
	public function refresh(): void {

		$this->populate();

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
					$value = openssl_decrypt($value, 'AES128', Env::get('OPTIONS_CRYPT_KEY'));
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
	 * Check wheter options crypt key has been defined into .env file.
	 */
	public function isCryptAvailable(): bool {

		return (strlen((string)Env::get('OPTIONS_CRYPT_KEY')) > 0);

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

}