<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Template extends ActiveRecord implements pluginInterface {

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
	 * Flag for default template only.
	 * @var int
	 */
	protected $default;
	
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
	 * Converts from string to Datetime object in two ways.
	 */
	protected function init() {
	
		$this->bindAsDatetime('dateReleased', 'dateInstalled');
		
		$this->bindAsBoolean('default', 'derived');
		
		$this->bindAsInteger('id', 'installedBy');
	
		$this->bindAsCsv('palette');
	
	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
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
	 * Changes instances that use this template to default template.
	 */
	protected function beforeDelete() {
		
		$default = Template::getDefault();
		
		$query = 'UPDATE instances SET template_id = ? WHERE template_id = ?';
		$res = $this->db->exec($query, array($default->id, $this->id));
		
		// deletes template plugin folder
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
	 *  @return	string
	 */
	public function getBaseFolder() {
	
		return APPLICATION_PATH . '/templates';
	
	}
	
	/**
	 * Checks if Template is already installed in this application.
	 *
	 * @param	string	Name of Template to search.
	 * @return	boolean
	 */
	public static function pluginExists($name) {
	
		$db = Database::getInstance();
		$db->setQuery('SELECT COUNT(*) FROM templates WHERE name = ?');
		$res = $db->loadResult($name);
	
		return $res ? TRUE : FALSE;
	
	}
	
	/**
	 * Creates and returns the Plugin object of this Template object.
	 *
	 * @return Plugin
	 */
	public function getPlugin() {
	
		$folder			= APPLICATION_PATH . '/templates/' . strtolower(str_replace(array(' ', '_'), '', $this->name));
		$dateReleased	= $this->dateReleased->format('Y-m-d');
		$options		= array('derived' => $this->derived, 'palette' => $this->palette);
	
		$plugin = new Plugin('template', $this->name, $this->version, $dateReleased, $this->appVersion, $folder, $options);
	
		return $plugin;
	
	}
	
	/**
	 * Returns the default Template object.
	 *
	 * @return	Template
	 */
	public static function getDefault() {
	
		$db  = Database::getInstance();
		$db->setQuery('SELECT * FROM templates WHERE is_default=1');
		$template = new Template($db->loadObject());
		return $template;
	
	}
	
	/**
	 * Returns list of all registered templates.
	 * 
	 * @return array:Template
	 */
	public static function getAllTemplates() {
	
		$db = Database::getInstance();
		$db->setQuery('SELECT * FROM templates');
		$list = $db->loadObjectList();

		$templates = array();
		
		foreach ($list as $item) {
			$calledClass = get_called_class();
			$templates[] = new $calledClass($item);
		}
		
		return $templates;
		
	}
	
	/**
	 * Creates a list of colors boxes for this template.
	 * 
	 * @return string
	 */
	public function getPaletteSamples() {
		
		$ret = '';

		foreach ($this->palette as $color) {
			$ret .= '<div class="colorSample" style="background-color:' . $color . '" title="' . $color . '"></div>';
		}
		
		return $ret;
		
	}
	
}
