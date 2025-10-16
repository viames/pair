<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Exceptions\CriticalException;
use Pair\Helpers\Plugin;
use Pair\Helpers\PluginBase;
use Pair\Helpers\Utilities;
use Pair\Html\Widget;

class Template extends PluginBase {

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
	protected bool $isDefault;

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
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'			=> ['int unsigned', 'NO', 'PRI', NULL, 'auto_increment'],
		'name'			=> ['varchar(50)', 'NO', 'UNI', NULL, ''],
		'version'		=> ['varchar(10)', 'NO', '', NULL, ''],
		'date_released'	=> ['datetime', 'NO', '', NULL, ''],
		'app_version'	=> ['varchar(10)', 'NO', '', '1.0', ''],
		'is_default'	=> ['tinyint(1)', 'NO', '', '0', ''],
		'installed_by'	=> ['int unsigned', 'NO', 'MUL', NULL, ''],
		'date_installed'=> ['datetime', 'NO', '', NULL, ''],
		'derived'		=> ['tinyint unsigned', 'NO', '', '0', ''],
		'palette'		=> ['varchar(255)', 'NO', '', NULL, '']
	];

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsBoolean('default', 'derived');

		$this->bindAsCsv('palette');

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
			throw new PairException('Could not delete template folder ' . $plugin->baseFolder);
		}

		$logger = Logger::getInstance();
		$logger->info('Plugin folder ' . $plugin->baseFolder . ' has been deleted');

	}

	/**
	 * Returns absolute path to plugin folder.
	 */
	public function getBaseFolder(): string {

		return APPLICATION_PATH . '/templates';

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
			'isDefault'		=> 'is_default',
			'installedBy'	=> 'installed_by',
			'dateInstalled'	=> 'date_installed',
			'derived'		=> 'derived',
			'palette'		=> 'palette'
		];

	}

	/**
	 * Returns the default Template object.
	 */
	public static function getDefault(): ?self {

		return self::getObjectByQuery('SELECT * FROM `templates` WHERE `is_default`=1');

	}

	/**
	 * Returns the path to the template folder.
	 */
	public function getPath() {

		$templateName = $this->derived ? $this->base->name : $this->name;
		return APPLICATION_PATH . '/templates/' . strtolower($templateName) . '/';

	}

	/**
	 * Load and return a Template object by its name.
	 *
	 * @param	string	Template name.
	 */
	public static function getPluginByName(string $name): ?self {

		return self::getObjectByQuery('SELECT * FROM `templates` WHERE `name`=?', [$name]);

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

		return new Plugin('Template', $this->name, $this->version, $dateReleased, $this->appVersion, $folder, $options);

	}

	/**
	 * Get the style page file absolute path.
	 *
	 * @param	string	Style name.
	 */
	public function getStyleFile(string $styleName): string {

		// by default load template style
		$styleFile = $this->getBaseFolder() . '/' . strtolower($this->name) . '/' . $styleName . '.php';

		// if this is derived template, try to load the file from its folder
		if (!file_exists($styleFile) and $this->derived and is_a($this->base, 'Pair\Template')) {
			$styleFile = $this->getBaseFolder() . '/' . strtolower($this->base->name) . '/' . $styleName . '.php';
		}

		if (!file_exists($styleFile)) {
			throw new CriticalException('Template style ' . $styleName . ' not found');
		}

		return $styleFile;

	}

	/**
	 * Parses the template style file and replaces placeholders with HTML code.
	 */
	public static function parse(string $styleFile): void {

		$app = Application::getInstance();

		// load the style page file
		$templateHtml = file_get_contents($styleFile);

		$widgets = [];

		foreach (Widget::availableWidgets() as $name) {

			$pattern = '/\{\{\s*' . preg_quote($name, '/') . '\s*\}\}/';

			// replace the widget placeholder with its rendered output
			if (preg_match($pattern, $templateHtml)) {
				$widgets[] = new Widget($name);
			}

		}

		// placeholders to replace with $app properties
		$placeholders = [
			'langCode'	=> 'langCode',
			'title'		=> 'pageTitle',
			'heading'	=> 'pageHeading',
			'content'	=> 'pageContent',
			'logBar'	=> 'logBar'
		];

		foreach ($placeholders as $placeholder => $property) {

			// regex for both {{placeholder}} and {{ placeholder }}
			$pattern = '/\{\{\s*' . preg_quote($placeholder, '/') . '\s*\}\}/';

			// placeholder could be not found or $app property could be NULL
			if (!preg_match($pattern, $templateHtml) or !$app->$property) {
				continue;
			}

			// replace in template
			$templateHtml = preg_replace($pattern, $app->$property, $templateHtml, 1);

		}

		// renders each existing widget
		foreach ($widgets as $widget) {
			$pattern = '/\{\{\s*' . preg_quote($widget->name, '/') . '\s*\}\}/';
			$templateHtml = preg_replace($pattern, $widget->render(), $templateHtml, 1);
		}

		eval('?>' . $templateHtml);

	}

	/**
	 * Checks if Template is already installed in this application.
	 *
	 * @param	string	Name of Template to search.
	 */
	public static function pluginExists(string $name): bool {

		return (bool)self::countAllObjects(['name'=>$name]);

	}

	/**
	 * Set a standard Template object as the base for a derived Template.
	 *
	 * @param	string	Template name.
	 */
	public function setBase(string $templateName): void {

		$this->base = static::getPluginByName($templateName);

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

}