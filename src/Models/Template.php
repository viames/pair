<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;
use Pair\Support\Logger;
use Pair\Support\Plugin;
use Pair\Support\PluginInterface;
use Pair\Support\Utilities;

class Template extends ActiveRecord implements PluginInterface {

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
	 * Flag for default template only.
	 */
	protected bool $default;

	/**
	 * User ID of installer.
	 */
	protected int $installedBy;

	/**
	 * Installation date, properly converted when inserted into db.
	 */
	protected \DateTime $dateInstalled;

	/**
	 * Flag to declare this derived from default template.
	 */
	protected bool $derived;

	/**
	 * Palette for charts as CSV of HEX colors.
	 */
	protected array $palette;

	/**
	 * Template from which it derives. It’s NULL if standard Template.
	 */
	protected ?Template $base;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'templates';

	/**
	 * Name of primary key db field.
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
	protected static function getBinds(): array {

		$varFields = [
			'id'			=> 'id',
			'name'			=> 'name',
			'version'		=> 'version',
			'dateReleased'	=> 'date_released',
			'appVersion'	=> 'app_version',
			'default'		=> 'is_default',
			'installedBy'	=> 'installed_by',
			'dateInstalled'	=> 'date_installed',
			'derived'		=> 'derived',
			'palette'		=> 'palette'
		];

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

	}

	/**
	 * Returns absolute path to plugin folder.
	 *
	 * @return	string
	 *
	 * @see		PluginInterface::getBaseFolder()
	 */
	public function getBaseFolder(): string {

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
	public static function pluginExists($name): bool {

		$db = Database::getInstance();
		$db->setQuery('SELECT COUNT(1) FROM `templates` WHERE name = ?');
		return (bool)$db->loadCount($name);

	}

	/**
	 * Creates and returns the Plugin object of this Template object.
	 */
	public function getPlugin(): Plugin {

		$folder = $this->getBaseFolder() . '/' . strtolower(str_replace([' ', '_'], '', $this->name));
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
	 */
	public function storeByPlugin(\SimpleXMLElement $options): bool {

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
	 * @return	Template|NULL
	 */
	public static function getDefault(): ?self {

		return self::getObjectByQuery('SELECT * FROM `templates` WHERE `is_default`=1');

	}

	/**
	 * Load and return a Template object by its name.
	 *
	 * @param	string	Template name.
	 * @return	Template|NULL
	 */
	public static function getPluginByName(string $name): self {

		return self::getObjectByQuery('SELECT * FROM `templates` WHERE `name`=?', [$name]);

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
		return APPLICATION_PATH . '/templates/' . strtolower($templateName) . '/';

	}

}