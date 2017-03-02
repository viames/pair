<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Interface for installable plugins.
 */
interface pluginInterface {

	/**
	 * Returns absolute path to plugin folder.
	 * 
	 *  @return	string
	 */
	public function getBaseFolder();
	
	/**
	 * Checks if plugin is already installed.
	 *
	 * @param	string	Name of plugin to search.
	 * 
	 * @return	boolean
	 */
	public static function pluginExists($name);
	
	/**
	 * Creates and returns a Plugin object for implemented class.
	 *
	 * @return Plugin
	 */
	public function getPlugin();
	
}
