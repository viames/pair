<?php

namespace Pair\Helpers;

/**
 * Interface for installable plugins.
 */
interface PluginInterface {

	/**
	 * Returns absolute path to plugin folder.
	 */
	public function getBaseFolder(): string;
	
	/**
	 * Checks if plugin is already installed.
	 * @param	string	Name of plugin to search.
	 */
	public static function pluginExists(string $name): bool;
	
	/**
	 * Creates and returns a Plugin object for implemented class.
	 */
	public function getPlugin(): Plugin;
	
	/**
	 * Store an object loaded by a Plugin.
	 * @param	SimpleXMLElement	List of options.
	 */
	public function storeByPlugin(\SimpleXMLElement $options): bool;
	
}
