<?php
		
namespace Pair;

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
	
		// delete plugin folder
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
	 * @return	string
	 *  
	 * @see		PluginInterface::getBaseFolder()
	 */
	public function getBaseFolder() {
	
		return APPLICATION_PATH . '/modules';
	
	}
	
	/**
	 * Checks if Module is already installed in this application.
	 *
	 * @param	string	Name of Module to search.
	 * 
	 * @return	boolean
	 * 
	 * @see		PluginInterface::pluginExists()
	 */
	public static function pluginExists($name) {
	
		$db = Database::getInstance();
		$db->setQuery('SELECT COUNT(1) FROM modules WHERE name = ?');
		return (bool)$db->loadCount($name);
	
	}
	
	/**
	 * Creates and returns the Plugin object of this Module object.
	 *
	 * @return	Plugin
	 * 
	 * @see		PluginInterface::getPlugin()
	 */
	public function getPlugin() {
		
		$folder = $this->getBaseFolder() . '/' . strtolower(str_replace(array(' ', '_'), '', $this->name));
		$dateReleased = $this->dateReleased->format('Y-m-d');
	
		$plugin = new Plugin('Module', $this->name, $this->version, $dateReleased, $this->appVersion, $folder);
	
		return $plugin;
	
	}
	
	/**
	 * Get option parameters and store this object loaded by a Plugin.
	 *
	 * @param	SimpleXMLElement	List of options.
	 * 
	 * @return	bool
	 *
	 * @see		PluginInterface::storeByPlugin()
	 */
	public function storeByPlugin(\SimpleXMLElement $options) {
		
		return $this->store();
		
	}
	
	/**
	 * Return an installed Module of this application.
	 *
	 * @param	string	Name of Module to search.
	 *
	 * @return	Module
	 */
	public static function getByName($name) {
		
		$db = Database::getInstance();
		$db->setQuery('SELECT * FROM modules WHERE name = ?');
		return $db->loadObject($name);
		
	}
	
}