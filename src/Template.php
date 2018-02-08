<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Template extends ActiveRecord implements PluginInterface {

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
	 * Flag for default template only.
	 * @var bool
	 */
	protected $default;	
	
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
	 * Flag to declare this derived from default template.
	 * @var bool
	 */
	protected $derived;
	
	/**
	 * Palette for charts as CSV of HEX colors.
	 * @var array
	 */
	protected $palette;
	
	/**
	 * Template from which it derives. It’s NULL if standard Template.
	 * @var Template|NULL
	 */
	protected $base;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'templates';

	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';
	
	/**
	 * Legacy method to get working the old templates.
	 *
	 * @param	string	Requested property’s name.
	 * 
	 * @return	multitype
	 * 
	 * @deprecated
	 */
	public function __get($name) {

		$app = Application::getInstance();
		
		// patch for Widget variables
		if ('Widget' == substr($name, -6)) {
			return $app->$name;
		}
		
		switch ($name) {
			
			case 'templatePath':
				return $this->getPath();
				break;
				
			case 'currentUser':
			case 'langCode':
			case 'log':
			case 'pageTitle':
			case 'pageStyles':
			case 'pageContent':
			case 'pageScripts':
				return $app->$name;
				break;

			default:
				return $this->$name;
				break;
		
		}
		
	}
	
	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {
	
		$this->bindAsBoolean('default', 'derived');
	
		$this->bindAsCsv('palette');
		
		$this->bindAsDatetime('dateReleased', 'dateInstalled');
		
		$this->bindAsInteger('id', 'installedBy');
		
	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return	array
	 */
	protected static function getBinds() {

		$varFields = array(
			'id'			=> 'id',
			'name'			=> 'name',
			'version'		=> 'version',
			'dateReleased'	=> 'date_released',
			'appVersion'	=> 'app_version',
			'default'		=> 'is_default',
			'installedBy'	=> 'installed_by',
			'dateInstalled'	=> 'date_installed',
			'derived'		=> 'derived',
			'palette'		=> 'palette');
		
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
		
	}
	
	/**
	 * Returns absolute path to plugin folder.
	 *
	 * @return	string
	 *  
	 * @see		PluginInterface::getBaseFolder()
	 */
	public function getBaseFolder() {
	
		return APPLICATION_PATH . '/templates';
	
	}
	
	/**
	 * Checks if Template is already installed in this application.
	 *
	 * @param	string	Name of Template to search.
	 * 
	 * @return	boolean
	 * 
	 * @see		PluginInterface::pluginExists()
	 */
	public static function pluginExists($name) {
	
		$db = Database::getInstance();
		$db->setQuery('SELECT COUNT(1) FROM templates WHERE name = ?');
		return (bool)$db->loadCount($name);
	
	}
	
	/**
	 * Creates and returns the Plugin object of this Template object.
	 *
	 * @return	Plugin
	 * 
	 * @see		PluginInterface::getPlugin()
	 */
	public function getPlugin() {
	
		$folder = $this->getBaseFolder() . '/' . strtolower(str_replace(array(' ', '_'), '', $this->name));
		$dateReleased = $this->dateReleased->format('Y-m-d');
		
		// special parameters for Template plugin
		$options = [
			'derived' => (string)\intval($this->derived),
			'palette' => implode(',', $this->palette)
		];
		
		$plugin = new Plugin('Template', $this->name, $this->version, $dateReleased, $this->appVersion, $folder, $options);
	
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
		
		// get options
		$children = $options->children();
		
		$this->derived = (bool)$children->derived;
		
		// the needed cast to string for each property
		foreach ($children->palette->children() as $color) {
			$this->palette[] = (string)$color;
		}
		
		return $this->store();
		
	}
	
	/**
	 * Returns the default Template object.
	 *
	 * @return	Template
	 */
	public static function getDefault() {
	
		$db = Database::getInstance();
		$db->setQuery('SELECT * FROM templates WHERE is_default=1');
		return new Template($db->loadObject());
	
	}
	
	/**
	 * Load and return a Template object by its name.
	 *
	 * @param	string	Template name.
	 *
	 * @return	Template|NULL
	 */
	public static function getPluginByName($name) {

		$db = Database::getInstance();
		$db->setQuery('SELECT * FROM templates WHERE name=?');
		$obj = $db->loadObject($name);
		return (is_a($obj, 'stdClass') ? new Template($obj) : NULL);
		
	}
	
	/**
	 * Set a standard Template object as the base for a derived Template.
	 * 
	 * @param	string	Template name.
	 */
	public function setBase($templateName) {

		$this->base = static::getPluginByName($templateName);
		
	}
	
	public function loadStyle($styleName) {
		
		// by default load template style
		$styleFile = $this->getBaseFolder() . '/' . strtolower($this->name) . '/' . $styleName . '.php';
		
		// if this is derived template, try to load the file from its folder
		if (!file_exists($styleFile) and $this->derived and is_a($this->base, 'Pair\Template')) {
			$styleFile = $this->getBaseFolder() . '/' . strtolower($this->base->name) . '/' . $styleName . '.php';
		}

		if (!file_exists($styleFile)) {
			
			throw new \Exception('Template style file ' . $styleFile . ' was not found');
			
		} else {
			
			// load the style page file
			require $styleFile;
			
		}
		
	}
	
	public function getPath() {
		
		$templateName = $this->derived ? $this->base->name : $this->name;
		return 'templates/' . strtolower($templateName) . '/';
		
	}

}