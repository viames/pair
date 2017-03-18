<?php
		
/**
 * @version	$Id$
 * @author	Viames Marino 
 * @package	Pair
 */

namespace Pair;

class Module extends ActiveRecord implements pluginInterface {

	/**
	 * Property that binds db field id.
	 * @var int
	 */
	protected $id;
	/**
	 * Property that binds db field name.
	 * @var string
	 */
	protected $name;
	/**
	 * Property that binds db field version.
	 * @var string
	 */
	protected $version;
	/**
	 * Property that binds db field date_released.
	 * @var DateTime
	 */
	protected $dateReleased;
	/**
	 * Version of application on which installs.
	 * @var string
	 */
	protected $appVersion;
	
	/**
	 * Property that binds db field installed_by.
	 * @var int
	 */
	protected $installedBy;
	/**
	 * Property that binds db field date_installed.
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
	protected static function getBinds() {
		
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
	
		$plugin = $this->getPlugin();
		$res = Utilities::deleteFolder($plugin->baseFolder);
	
		$app = Application::getInstance();
		
		if ($res) {
				
			$app->logEvent('Plugin folder ' . $plugin->baseFolder . ' has been deleted');
		
		} else {
				
			if (is_dir($plugin->baseFolder)) {
				$app->logWarning('Plugin folder ' . $plugin->baseFolder . ' has not been deleted due unexpected error');
			} else {
				$app->logWarning('Plugin folder ' . $plugin->baseFolder . ' has not been found');
			}
		}

		// deletes object dependances
		$rules = Rule::getAllObjects(array('moduleId' => $this->id));
		foreach ($rules as $rule) {
			$rule->delete();
		}
		
	}
	
	/**
	 * Returns absolute path to plugin folder.
	 *
	 *  @return	string
	 */
	public function getBaseFolder() {
	
		return APPLICATION_PATH . '/modules';
	
	}
	
	/**
	 * Checks if Module is already installed in this application.
	 *
	 * @param	string	Name of Module to search.
	 * @return	boolean
	 */
	public static function pluginExists($name) {
	
		$db = Database::getInstance();
		$db->setQuery('SELECT COUNT(*) FROM modules WHERE name = ?');
		$res = $db->loadCount($name);
	
		return $res ? TRUE : FALSE;
	
	}
	
	/**
	 * Creates and returns the Plugin object of this Module object.
	 *
	 * @return Plugin
	 */
	public function getPlugin() {
		
		$folder			= APPLICATION_PATH . '/modules/' . strtolower(str_replace(array(' ', '_'), '', $this->name));
		$dateReleased	= $this->dateReleased->format('Y-m-d');
	
		$plugin = new Plugin('module', $this->name, $this->version, $dateReleased, $this->appVersion, $folder);
	
		return $plugin;
	
	}
	
}