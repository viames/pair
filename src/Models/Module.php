<?php

namespace Pair\Models;

use Pair\Core\Logger;
use Pair\Helpers\Plugin;
use Pair\Helpers\PluginBase;
use Pair\Helpers\Utilities;

class Module extends PluginBase {

	/**
	 * ID as primary key.
	 */
	protected int $id;

	/**
	 * Unique name with no space.
	 */
	protected string $name;

	/**
	 * Release version.
	 */
	protected string $version;

	/**
	 * Publication date, properly converted when inserted into db.
	 */
	protected \DateTime $dateReleased;

	/**
	 * Version of application on which installs.
	 */
	protected string $appVersion;

	/**
	 * User ID of installer.
	 */
	protected int $installedBy;

	/**
	 * Installation date, properly converted when inserted into db.
	 */
	protected \DateTime $dateInstalled;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'modules';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['installedBy'];

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'			=> ['int unsigned', 'NO', 'PRI', NULL, 'auto_increment'],
		'name'			=> ['varchar(50)', 'NO', 'UNI', NULL, ''],
		'version'		=> ['varchar(10)', 'NO', '', NULL, ''],
		'date_released'	=> ['datetime', 'NO', '', NULL, ''],
		'app_version'	=> ['varchar(10)', 'NO', '', '1.0', ''],
		'installed_by'	=> ['int unsigned', 'NO', 'MUL', NULL, ''],
		'date_installed'=> ['datetime', 'NO', '', NULL, '']
	];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsDatetime('dateReleased', 'dateInstalled');

		$this->bindAsInteger('id', 'installedBy');

	}

	/**
	 * Removes files of this Module object before its deletion.
	 *
	 * @throws	PairException
	 */
	protected function beforeDelete(): void {

		// delete plugin folder
		$plugin = $this->getPlugin();

		if (!Utilities::deleteFolder($plugin->baseFolder)) {
			throw new PairException('Could not delete module folder ' . $plugin->baseFolder);
		}

		$logger = Logger::getInstance();
		$logger->info('Plugin folder ' . $plugin->baseFolder . ' has been deleted');

	}

	/**
	 * Returns absolute path to plugin folder.
	 */
	public function getBaseFolder(): string {

		return APPLICATION_PATH . '/modules';

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 */
	protected static function getBinds(): array {

		return [
			'id'			=> 'id',
			'name'			=> 'name',
			'version'		=> 'version',
			'dateReleased'	=> 'date_released',
			'appVersion'	=> 'app_version',
			'installedBy'	=> 'installed_by',
			'dateInstalled'	=> 'date_installed'
		];

	}

	/**
	 * Return an installed Module of this application.
	 */
	public static function getByName(string $name): ?self {

		return self::getObjectByQuery('SELECT * FROM `modules` WHERE name = ?', [$name]);

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
	 * Checks if Module is already installed in this application.
	 */
	public static function pluginExists(string $name): bool {

		return (bool)self::countAllObjects(['name'=>$name]);

	}

	/**
	 * Get option parameters and store this object loaded by a Plugin.
	 */
	public function storeByPlugin(\SimpleXMLElement $options): bool {

		return $this->store();

	}

}