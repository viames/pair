<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Utilities;

class Plugin {

	/**
	 * Plugin type (module, template, {custom}).
	 */
	protected string $type;

	/**
	 * The plugin name.
	 */
	protected string $name;

	/**
	 * Absolute path to plugin base folder based on its type, without trailing slash.
	 */
	protected string $baseFolder;

	/**
	 * Plugin version number.
	 */
	protected string $version;

	/**
	 * Date of package release in format Y-m-d.
	 */
	protected string $dateReleased;

	/**
	 * Version of application on which installs.
	 */
	protected string $appVersion;

	/**
	 * A list of plugin option fields.
	 */
	protected array $options = [];

	/**
	 * Life time of file in cache folder (minutes).
	 */
	const FILE_EXPIRE = 30;

	/**
	 * Creates a Plugin object and inizialise its optional parameters.
	 *
	 * @param	string	Type (template, module).
	 * @param	string	Official plugin name.
	 * @param	string	Version number.
	 * @param	string	Date of public release in format Y-m-d.
	 * @param	string	Application compatible version.
	 * @param	string	Absolute path to plugin folder, without trailing slash.
	 * @param	array	List of option fields (optional, specific to each plugin type).
	 */
	public function __construct($type = null, $name = null, $version = null, $dateReleased = null, $appVersion = null, $baseFolder = null, $options = []) {

		$this->type			= $type;
		$this->name			= $name;
		$this->version		= $version;
		$this->dateReleased	= $dateReleased;
		$this->appVersion	= $appVersion;
		$this->baseFolder	= $baseFolder;
		$this->options		= $options;

	}

	/**
	 * Will returns property’s value if set. Throw an exception and returns NULL if not set.
	 *
	 * @param	string	Property’s name.
	 */
	public function __get(string $name): mixed {

		return $this->$name;

	}

	/**
	 * Magic method to set an object property value.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set(string $name, mixed $value): void {

		$this->$name = $value;

	}

	/**
	 * Manages ZIP file, reads manifest file, copies plugin files and creates record in db.
	 *
	 * @param	string	Absolute path and file name of plugin package.
	 */
	public function installPackage(string $package): bool {

		$ret = TRUE;

		$zip		= new \ZipArchive;
		$zipOpened	= $zip->open($package);

		// TODO managing all ZIP errors (ZipArchive::ER_EXISTS, ZipArchive::ER_INCONS, etc.)

		if (TRUE !== $zipOpened) {
			throw new PairException('Error opening ZIP file ' . $package);
		}

		// make a random temporary folder
		$tempFolder = TEMP_PATH . substr(md5(time()),0,6);

		// extract all zip contents
		$zip->extractTo($tempFolder);

		// locates manifest file
		$manifestIndex = $zip->locateName('manifest.xml', \ZipArchive::FL_NOCASE|\ZipArchive::FL_NODIR);

		// get XML content of manifest from ZIP
		$manifest = simplexml_load_string($zip->getFromIndex($manifestIndex));

		// check if manifest is valid
		if (!is_a($manifest, '\SimpleXMLElement')) {
			throw new PairException('Manifest file is not valid');
		}

		// get installation info by manifest file
		$plugin = static::createPluginByManifest($manifest);

		// error check
		if (is_null($plugin)) {
			$zip->close();
			Utilities::deleteFolder($package);
			return FALSE;
		}

		// set the plugin-type common folder
		$this->baseFolder = $plugin->getBaseFolder();

		// this is destination path for copy plugin files
		$pluginFolder = $this->baseFolder . '/' . strtolower($manifest->plugin->folder);

		// creates final plugin folder
		$old = umask(0);
		if (!mkdir($pluginFolder, 0777, TRUE)) {
			throw new PairException('Directory creation on ' . $pluginFolder . ' failed');
		}
		umask($old);

		// sets full permissions on final folder
		if (!chmod($pluginFolder, 0777)) {
			throw new PairException('Set permissions on directory ' . $pluginFolder . ' failed');
		}

		// TODO must notify all lost files when copying

		$files = $manifest->plugin->files->children();

		// copy all plugin files
		foreach ($files as $file) {

			$source = $tempFolder . '/' . $file;
			$dest	= $pluginFolder . '/' . $file;

			if (!file_exists(dirname($dest))) {
				$old = umask(0);
				mkdir(dirname($dest), 0777, true);
				umask($old);
			}

			if (!copy($source, $dest)) {
				throw new PairException('Copy file ' . $source . ' to ' . $dest . ' failed');
			}

		}

		$zip->close();

		$sourceFolder = dirname($package);

		// ZIP archive and temporary files deletion
		Utilities::deleteFolder($package);
		Utilities::deleteFolder($sourceFolder);

		return $ret;

	}

	/**
	 * Load the manifest from a passed file.
	 *
	 * @param	string				Path to the file.
	 */
	public static function getManifestByFile(string $file): \SimpleXMLElement {

		$app = Application::getInstance();

		if (!file_exists($file)) {
			throw new PairException('Manifest file ' . $file . ' doens’t exist');
		}

		$contents = file_get_contents($file);

		$xml = simplexml_load_string($contents);

		return $xml;

	}

	/**
	 * Insert a new db record for the plugin in manifest. Return an object of the plugin type.
	 *
	 * @param	SimpleXMLElement	Manifest file XML content.
	 */
	public static function createPluginByManifest(\SimpleXMLElement $manifest): mixed {

		$app = Application::getInstance();

		// plugin tree
		$mPlugin = $manifest->plugin;

		$mAttributes = $mPlugin->attributes();

		// get class name for this plugin (Module, Template, other)
		$class = ucfirst($mAttributes->type);

		// add namespace for Pair classes
		if (in_array($class, ['Module', 'Template'])) {
			$class = 'Pair\\Models\\' . $class;
		}

		// check if plugin is already installed
		if ($class::pluginExists($mPlugin->name)) {
			$msg = Translator::do('PLUGIN_IS_ALREADY_INSTALLED', $mPlugin->name);
			throw new PairException($msg, ErrorCodes::PLUGIN_ALREADY_INSTALLED);
		}

		// creates plugin object
		$plugin					= new $class();
		$plugin->name			= (string)$mPlugin->name;
		$plugin->version		= (string)$mPlugin->version;
		$plugin->dateReleased	= date('Y-m-d H:i:s', strtotime((string)$mPlugin->dateReleased));
		$plugin->appVersion		= (string)$mPlugin->appVersion;
		$plugin->installedBy	= $app->currentUser->id;
		$plugin->dateInstalled	= new \DateTime();

		// saves plugin object to db
		$plugin->storeByPlugin($mPlugin->options);

		return $plugin;

	}

	/**
	 * Updates the manifest file, zips plugin’s folder and lets user download it.
	 */
	public function downloadPackage(): void {

		if (!is_dir($this->baseFolder)) {
			throw new PairException('Plugin folder is missing');
		}

		$this->createManifestFile();

		// removes old archives
		static::removeOldFiles();

		// useful paths
		$pathInfos	= pathInfo($this->baseFolder);
		$parentPath	= $pathInfos['dirname'];
		$dirName	= $pathInfos['basename'];

		// creates unique file name
		$filename	= Utilities::uniqueFilename($this->name . ucfirst($this->type) . '.zip', TEMP_PATH);
		$zipFile	= TEMP_PATH . $filename;

		// creates the ZIP archive
		$zip = new \ZipArchive();
		$zip->open($zipFile, \ZipArchive::CREATE);
		$zip->addEmptyDir($dirName);

		// recursive function to add all files in all sub-folders
		self::folderToZip($this->baseFolder, $zip, $parentPath);

		$zip->close();

		// add some log
		$logger = Logger::getInstance();
		$logger->debug('Created ZIP file archive ' . $zipFile . ' for ' . $this->type . ' plugin');

		// lets user download the ZIP file
		header("Content-Type: application/zip");
		header("Content-Disposition: attachment; filename=$filename");
		header("Content-Length: " . filesize($zipFile));

		// sends the zip as download
		readfile($zipFile);
		exit();

	}

	/**
	 * Recursively adds all files and all sub-directories to a ZIP file.
	 *
	 * @param	string		Folder without trailing slash.
	 * @param	ZipArchive	Reference ZIP file parameter.
	 * @param	string		Parent path that will be excluded from all files path.
	 */
	private static function folderToZip($folder, &$zipFile, $parentPath) {

		$handle = opendir($folder);

		while (false !== $f = readdir($handle)) {

			// list of unwanted files
			$excluded = ['.', '..', '.DS_Store', 'thumbs.db'];

			if (!in_array($f, $excluded)) {

				$filePath = $folder . '/' . $f;

				// removes prefix from file path before add to zip
				if (0 === strpos($filePath, $parentPath)) {
					$localPath = substr($filePath, strlen($parentPath . '/'));
				}

				if (is_file($filePath)) {

					$zipFile->addFile($filePath, $localPath);

				} elseif (is_dir($filePath)) {

					// adds sub-directory
					$zipFile->addEmptyDir($localPath);
					self::folderToZip($filePath, $zipFile, $parentPath);
				}

			}

		}

		closedir($handle);

	}

	/**
	 * Deletes file with created date older than EXPIRE_TIME const.
	 */
	public static function removeOldFiles() {

		if (!is_dir(TEMP_PATH)) {
			$logger = Logger::getInstance();
			$logger->error('Folder {path} cannot be accessed', ['path' => TEMP_PATH]);
			return;
		}

		$counter = 0;

		$files = Utilities::getDirectoryFilenames(TEMP_PATH);

		foreach ($files as $file) {

			$pathFile	= TEMP_PATH . '/' . $file;
			$fileLife	= time() - filemtime($pathFile);
			$maxLife	= static::FILE_EXPIRE * 60;

			if ($fileLife > $maxLife) {
				$counter++;
				unlink($pathFile);
			}

		}

		$logger = Logger::getInstance();
		$logger->info($counter
			? $counter . ' files has been deleted from temporary folder'
			: 'No old files deleted from temporary folder'
		);

	}

	/**
	 * Creates or updates manifest.xml file for declare package content.
	 */
	public function createManifestFile(): bool {

		// lambda method to add an array to the XML
		$addChildArray = function ($element, $list) use (&$addChildArray) {

			foreach ($list as $name => $value) {

				// if is an array, run again recursively
				if (is_array($value)) {
					$listChild = $element->addChild($name);
					$addChildArray($listChild, $value);
				} else if (is_int($value) or is_string($value)) {
					$element->addChild($name, (string)$value);
				} else {
					$logger = Logger::getInstance();
					$logger->error('Option item {name} is not valid', ['name' => $name]);
				}

			}

		};

		// prevent missing folders
		if (!is_dir($this->baseFolder)) {
			$logger = Logger::getInstance();
			$logger->error('Folder {path} of {name} {type} plugin cannot be accessed', [
				'path' => $this->baseFolder,
				'name' => $this->name,
				'type' => $this->type
			]);
			return FALSE;
		}

		$manifest = new \SimpleXMLElement('<!DOCTYPE xml><manifest></manifest>');

		$plugin = $manifest->addChild('plugin');

		// plugin type
		$plugin->addAttribute('type', $this->type);

		// more infos
		$plugin->addChild('name',			$this->name);
		$plugin->addChild('folder',			basename($this->baseFolder));
		$plugin->addChild('version',		$this->version);
		$plugin->addChild('dateReleased',	$this->dateReleased);
		$plugin->addChild('appVersion',		$this->appVersion);

		// custom options
		$optionsChild = $plugin->addChild('options');

		// recursively add options elements
		$addChildArray($optionsChild, $this->options);

		// will contains all found files
		$filesChild = $plugin->addChild('files');

		// list all found files as multi-dimensional array
		$files = Utilities::getDirectoryFilenames($this->baseFolder);

		// adds file to <files> child
		foreach ($files as $file) {
			$filesChild->addChild('file', $file);
		}

		$filename = $this->baseFolder . '/manifest.xml';

		return $manifest->asXML($filename);

	}

}