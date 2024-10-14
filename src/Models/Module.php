<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;
use Pair\Support\Logger;
use Pair\Support\Plugin;
use Pair\Support\PluginInterface;
use Pair\Support\Utilities;

class Module extends ActiveRecord implements PluginInterface {

	/**
	 * ID as primary key.
	 * @var int
	 */
	protected $id;

	/**
	 * Unique name with no space.
	 * @var string
	 */
	protected $name;

	/**
	 * Release version.
	 * @var string
	 */
	protected $version;

	/**
	 * Publication date, properly converted when inserted into db.
	 * @var DateTime
	 */
	protected $dateReleased;

	/**
	 * Version of application on which installs.
	 * @var string
	 */
	protected $appVersion;

	/**
	 * User ID of installer.
	 * @var int
	 */
	protected $installedBy;

	/**
	 * Installation date, properly converted when inserted into db.
	 * @var DateTime
	 */
	protected $dateInstalled;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'modules';

	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['installedBy'];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsDatetime('dateReleased', 'dateInstalled');

		$this->bindAsInteger('id', 'installedBy');

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return	array
	 */
	protected static function getBinds(): array {

		$varFields = array (
			'id'			=> 'id',
			'name'			=> 'name',
			'version' 		=> 'version',
			'dateReleased'	=> 'date_released',
			'appVersion'	=> 'app_version',
			'installedBy'	=> 'installed_by',
			'dateInstalled'	=> 'date_installed');

		return $varFields;

	}

	/**
	 * Removes files of this Module object before its deletion.
	 */
	protected function beforeDelete() {

		// delete plugin folder
		$plugin = $this->getPlugin();
		$res = Utilities::deleteFolder($plugin->baseFolder);

		if ($res) {

			Logger::event('Plugin folder ' . $plugin->baseFolder . ' has been deleted');

		} else {

			if (is_dir($plugin->baseFolder)) {
				Logger::warning('Plugin folder ' . $plugin->baseFolder . ' has not been deleted due unexpected error');
			} else {
				Logger::warning('Plugin folder ' . $plugin->baseFolder . ' has not been found');
			}
		}

		// deletes object dependances
		$rules = Rule::getAllObjects(['moduleId' => $this->id]);
		foreach ($rules as $rule) {
			$rule->delete();
		}

	}

	/**
	 * Returns absolute path to plugin folder.
	 */
	public function getBaseFolder(): string {

		return APPLICATION_PATH . '/modules';

	}

	/**
	 * Checks if Module is already installed in this application.
	 */
	public static function pluginExists(string $name): bool {

		return (bool)self::countAllObjects(['name'=>$name]);

	}

	/**
	 * Creates and returns the Plugin object of this Module object.
	 */
	public function getPlugin(): Plugin {

		$folder = $this->getBaseFolder() . '/' . strtolower(str_replace([' ', '_'], '', $this->name));
		$dateReleased = $this->dateReleased->format('Y-m-d');

		$plugin = new Plugin('Module', $this->name, $this->version, $dateReleased, $this->appVersion, $folder);

		return $plugin;

	}

	/**
	 * Get option parameters and store this object loaded by a Plugin.
	 */
	public function storeByPlugin(\SimpleXMLElement $options): bool {

		return $this->store();

	}

	/**
	 * Return an installed Module of this application.
	 */
	public static function getByName(string $name): ?self {

		return self::getObjectByQuery('SELECT * FROM `modules` WHERE name = ?', [$name]);

	}

}