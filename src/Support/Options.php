<?php

namespace Pair\Support;

use Pair\Orm\Database;

class Options {

	/**
	 * Singleton instance object.
	 * @var Options|NULL
	 */
	private static $instance = NULL;

	/**
	 * DB connection object.
	 * @var Database
	 */
	protected $db;

	/**
	 * Option list.
	 * @var array
	 */
	private $list;

	/**
	 * Constructor, connects to database.
	 */
	private function __construct() {

		$this->db = Database::getInstance();

	}

	/**
	 * Returns this object’s singleton.
	 *
	 * @return	Options
	 */
	public static function getInstance() {

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

		$this->checkPopulated();

		return $this->list;

	}

	/**
	 * Proxy method to return an option’s value.
	 *
	 * @param	string	The option’s name.
	 * @throws	Exception
	 */
	public static function get(string $name): mixed {

		$self = static::getInstance();

		$self->checkPopulated();

		try {

			if (!static::exists($name)) {
				throw new \Exception('Cannot read the value of option “'. $name .'” as it doesn’t exist.');
			}

			return $self->list[$name]->value;

		} catch(\Exception $e) {

			Logger::warning($e->getMessage());

		}

	}

	/**
	 * Proxy method to return an option’s value.
	 *
	 * @param	string	The option’s name.
	 * @throws	Exception
	 * @return	bool
	 */
	public static function set($name, $value): bool {

		$ret = FALSE;

		// instance of the singleton
		$self = static::getInstance();

		// populate all the options by reading db once
		$self->checkPopulated();

		// check if named option exists
		try {

			if (!array_key_exists($name, $self->list)) {
				throw new \Exception('Cannot write the value of option “'. $name .'” as it doesn’t exist.');
			}

			switch ($self->list[$name]->type) {

				case 'bool';
					$value = $value ? 1 : 0;
					break;

				case 'password':
					if ($self->isCryptAvailable()) {
						$value = openssl_encrypt($value, 'AES128', OPTIONS_CRYPT_KEY);
					} else {
						throw new \Exception('OPTIONS_CRYPT_KEY constant must be defined into config.php file.');
					}
					break;

			}

			// update the value into db
			$ret = (bool)Database::run('UPDATE `options` SET `value` = ? WHERE `name` = ?', [$value, $name]);

			// update value into the singleton object
			$self->list[$name]->value = $value;

		} catch(\Exception $e) {

			Logger::warning($e->getMessage());

		}

		return $ret;

	}

	/**
	 * Refresh the option values by loading from DB.
	 */
	public function refresh() {

		$this->populate();

	}

	/**
	 * If this option list is empty, start loading from DB.
	 */
	private function checkPopulated() {

		if (!is_array($this->list)) {
			$this->populate();
		}

	}

	/**
	 * Load from DB and sets all options to this object.
	 */
	private function populate() {

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
	 *
	 * @return	mixed
	 */
	private function castTo($value, string $type) {

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
					$value = openssl_decrypt($value, 'AES128', OPTIONS_CRYPT_KEY);
				} else {
					Logger::warning('OPTIONS_CRYPT_KEY constant should be defined into config.php file.');
				}
				break;

			case 'list':
				break;

		}

		return $value;

	}

	/**
	 * Check wheter options crypt key has been defined into config.php file.
	 *
	 * @return boolean
	 */
	public function isCryptAvailable(): bool {

		return (defined('OPTIONS_CRYPT_KEY') and strlen(OPTIONS_CRYPT_KEY) > 0);

	}

	/**
	 * Check if an option’s exists.
	 *
	 * @param	string	The option’s name.
	 * @throws	Exception
	 * @return	bool
	 */
	public static function exists(string $name): bool {

		$self = static::getInstance();
		$self->checkPopulated();

		return array_key_exists($name, $self->list);

	}

}