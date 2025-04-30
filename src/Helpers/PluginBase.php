<?php

namespace Pair\Helpers;

use Pair\Core\Env;
use Pair\Orm\ActiveRecord;

/**
 * Base class for installable plugins.
 */
abstract class PluginBase extends ActiveRecord {

	/**
	 * Returns absolute path to plugin folder.
	 */
	abstract public function getBaseFolder(): string;

	/**
	 * Creates and returns a Plugin object for implemented class.
	 */
	abstract public function getPlugin(): Plugin;

	/**
	 * Check if plugin is compatible with current application version.
	 */
	public function isCompatibleWithApp(): bool {
		
		// check if plugin is compatible with current application version
		return version_compare($this->appVersion, Env::get('APP_VERSION'), '>=');

	}

	/**
	 * Check if plugin is compatible with current application major version.
	 */
	public function isCompatibleWithAppMajorVersion(): bool {

		$appMajorVersion = (int)explode('.', Env::get('APP_VERSION'))[0];
		$pluginMajorVersion = (int)explode('.', $this->appVersion)[0];
		
		return ($pluginMajorVersion >= $appMajorVersion);

	}

	/**
	 * Checks if plugin is already installed.
	 * @param	string	Name of plugin to search.
	 */
	abstract public static function pluginExists(string $name): bool;

	/**
	 * Store an object loaded by a Plugin.
	 * @param	SimpleXMLElement	List of options.
	 */
	abstract public function storeByPlugin(\SimpleXMLElement $options): bool;

}
