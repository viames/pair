<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Plugin {
	
	/**
	 * Plugin type (module, template, other) 
	 * @var string
	 */
	protected $type;
	
	/**
	 * The plugin name;
	 * @var string
	 */
	protected $name;
	
	/**
	 * Absolute path to plugin base folder based on its type, without trailing slash.
	 * @var string
	 */
	protected $baseFolder;
	
	/**
	 * Plugin version number.
	 * @var string
	 */
	protected $version;
	
	/**
	 * Date of package release in format Y-m-d.
	 * @var string
	 */
	protected $dateReleased;
	
	/**
	 * Version of application on which installs.
	 * @var string
	 */
	protected $appVersion;
	
	/**
	 * A list of plugin option fields.
	 * @var array
	 */
	protected $options = array();
	
	/**
	 * Cache folder’s name on relative path.
	 * @var string
	 */
	const TEMP_FOLDER = 'temp';
	
	/**
	 * Life time of file in cache folder (minutes).
	 * @var int
	 */
	const FILE_EXPIRE = 30;
	
	/**
	 * Creates a Plugin object and inizialise its optional parameters.
	 * 
	 * @param	string	Type (template, module).
	 * @param	string	Official plugin name.
	 * @param	string	Version number.
	 * @param	string	Date of public release in format Y-m-d.
	 * @param	string	Absolute path to plugin folder, without trailing slash.
	 * @param	array	List of option fields (optional, specific to each plugin type).
	 */
	public function __construct($type = NULL, $name = NULL, $version = NULL, $dateReleased = NULL, $appVersion = NULL, $baseFolder = NULL, $options = array()) {
		
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
	 * @throws	Exception
	 * @return	mixed|NULL
	 */
	public function __get($name) {
	
		try {
	
			if (!property_exists($this, $name)) {
				throw new \Exception('Property “'. $name .'” doesn’t exist for object '. get_called_class());
			}
	
			return $this->$name;
	
		} catch (\Exception $e) {
	
			trigger_error($e->getMessage());
			return NULL;
	
		}
	
	}
	
	/**
	 * Magic method to set an object property value.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set($name, $value) {
	
		$this->$name = $value;
	
	}
	
	/**
	 * Manages ZIP file, reads manifest file, copies plugin files and creates record in db.
	 * 
	 * @param	string	Absolute path and file name of plugin package.
	 * 
	 * @return	bool
	 */
	public function installPackage($package) {
		
		$app = Application::getInstance();
		
		$ret = TRUE;
		
		$zip		= new \ZipArchive;
		$zipOpened	= $zip->open($package);
		
		// TODO managing all ZIP errors (ZipArchive::ER_EXISTS, ZipArchive::ER_INCONS, etc.)
		
		if (TRUE !== $zipOpened) {
				
			trigger_error('ERROR_EXTRACTING_ZIP_CONTENT');
			return FALSE;
			
		}
		
		$zip->extractTo(self::TEMP_FOLDER);
		
		// checks if all contents are in a subfolder
		$stat = $zip->statIndex(0);
		$subfolder = (0==$stat['size']  and '/'==substr($stat['name'],-1)) ? $stat['name'] : FALSE;
		
		// locates manifest file
		$manifestIndex = $zip->locateName('manifest.xml', \ZipArchive::FL_NOCASE|\ZipArchive::FL_NODIR);
		
		// gets installation info by manifest file
		$manifest = simplexml_load_string($zip->getFromIndex($manifestIndex));
		
		// plugin tree
		$mPlugin = $manifest->plugin;
		
		$mAttributes = $mPlugin->attributes();
		
		// TODO management of unvalid packages
		
		// gets class name for this plugin (Module, Template, other)
		$pluginClass = ucfirst($mAttributes->type);
		
		if (in_array($pluginClass, array('Module', 'Template'))) {
			$pluginClass = 'Pair\\' . $pluginClass;
		}

		// checks if plugin is already installed
		if ($pluginClass::pluginExists($mPlugin->name)) {
				
			// TODO manage plugin update in case of new version
		
			$app->enqueueError('PLUGIN_IS_ALREADY_INSTALLED');
			return;
		
		}
			
		// creates plugin object
		$plugin					= new $pluginClass();
		$plugin->name			= (string)$mPlugin->name;
		$plugin->version		= (string)$mPlugin->version;
		$plugin->dateReleased	= date('Y-m-d H:i:s', strtotime((string)$mPlugin->dateReleased));
		$plugin->appVersion		= (string)$mPlugin->appVersion;
		$plugin->installedBy	= $app->currentUser->id;
		$plugin->dateInstalled	= time();
		
		$this->baseFolder = $plugin->getBaseFolder();
		
		// calls specific plugin subclass function
		$functionName	= 'install' . ucfirst($mAttributes->type) . 'Package';
		if (method_exists(get_called_class(), $functionName)) {
			$this->$functionName($plugin, $manifest);
		}
		
		// saves plugin object to db
		if (!$plugin->create()) {
			$ret = FALSE;
		}
			
		// the temporary directory where extracted files reside
		$sourceFolder = APPLICATION_PATH . '/' . self::TEMP_FOLDER . '/' . $mPlugin->folder;
		
		// this is destination path for copy plugin files
		$pluginFolder = $this->baseFolder . '/' . $mPlugin->folder;
		
		// creates final plugin folder
		$old = umask(0);
		if (!mkdir($pluginFolder, 0777, TRUE)) {
			$ret = FALSE;
			trigger_error('Directory creation on ' . $pluginFolder . ' failed');
		}
		umask($old);

		// sets full permissions on final folder
		if (!chmod($pluginFolder, 0777)) {
			$ret = FALSE;
			trigger_error('Set permissions on directory ' . $pluginFolder . ' failed');
		}
		
		// TODO must notify all lost files when copying
		
		$files = $mPlugin->files->children();
		
		// copy all plugin files
		foreach ($files as $file) {
			
			$source = $sourceFolder . '/' . $file;
			$dest	= $pluginFolder . '/' . $file;
			
			try {
				
				if (!file_exists(dirname($dest))) {
					$old = umask(0);
					mkdir(dirname($dest), 0777, true);
					umask($old);
				}

				copy($source, $dest);
				
			} catch (\Exception $e) {
				trigger_error('Failed to copy file ' . $source . ' into path ' . $this->baseFolder);
				$ret = FALSE;
				
			}
			
		}
		
		$zip->close();
		
		// ZIP archive and temporary files deletion
		Utilities::deleteFolder($package);
		Utilities::deleteFolder($sourceFolder);
		
		return $ret;
		
	}
	
	/**
	 * Updates the manifest file, zips plugin’s folder and lets user download it.
	 */
	public function downloadPackage() {

		if (!is_dir($this->baseFolder)) {
			$app = Application::getInstance();
			$app->enqueueError('Plugin folder is missing');
			//$app->redirect($this->type . 's/default');
		}
		
		$this->createManifestFile();
		
		// removes old archives
		self::removeOldFiles();
		
		// useful paths
		$pathInfos	= pathInfo($this->baseFolder);
		$parentPath	= $pathInfos['dirname'];
		$dirName	= $pathInfos['basename'];
		
		// creates unique file name
		$filename	= Utilities::uniqueFilename($this->name . ucfirst($this->type) . '.zip', self::TEMP_FOLDER);
		$zipFile	= self::TEMP_FOLDER . '/' . $filename;
		
		// creates the ZIP archive
		$zip = new \ZipArchive();
		$zip->open($zipFile, \ZipArchive::CREATE);
		$zip->addEmptyDir($dirName);
		
		// recursive function to add all files in all sub-folders
		self::folderToZip($this->baseFolder, $zip, $parentPath);
		
		$zip->close();
		
		// add some log
		$app = Application::getInstance();
		$app->logEvent('Created ZIP file archive ' . $zipFile . ' for ' . $this->type . ' plugin');

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
			$excluded = array('.', '..', '.DS_Store', 'thumbs.db');
			
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
		
		$app = Application::getInstance();
		$counter = 0;

		$files = Utilities::getDirectoryFilenames(self::TEMP_FOLDER);
		
		foreach ($files as $file) {
			
			$pathFile	= self::TEMP_FOLDER . '/' . $file;
			$fileLife	= time() - filemtime($pathFile);
			$maxLife	= self::FILE_EXPIRE * 60;
			
			if ($fileLife > $maxLife) {
				$counter++;
				unlink($pathFile);
			}
			
		}
		
		if ($counter) {
			$app->logEvent($counter . ' files has been deleted from ' . self::TEMP_FOLDER);
		} else {
			$app->logEvent('No old files deleted from ' . self::TEMP_FOLDER);
		}
		
	}
	
	/**
	 * Creates or updates manifest.xml file for declare package content.
	 * 
	 * @return	bool
	 */
	public function createManifestFile() {
	
		$app = Application::getInstance();
		
		// prevent missing folders
		if (!is_dir($this->baseFolder)) {
			$app->logError('Folder ' . $this->baseFolder . ' of ' . $this->name . ' ' . $this->type . ' plugin cannot be accessed');
			return FALSE;
		}
	
		$manifest = new \SimpleXMLElement("<manifest></manifest>");
	
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
		
		// calls specific plugin subclass function
		$functionName	= 'create' . ucfirst($this->type) . 'Manifest';
		if (method_exists(get_called_class(), $functionName)) {
			$this->$functionName($manifest);
		}
		
		// will contains all found files
		$filesChild = $plugin->addChild('files');
		
		// list all found files as multi-dimensional array
		$files = Utilities::getDirectoryFilenames($this->baseFolder);
		
		// adds file to <files> child
		foreach ($files as $file) {
			$filesChild->addChild('file', $file);
		}
	
		$filename = $this->baseFolder . '/manifest.xml';
		
		// TODO manage unwritable folder
		
		$res = $manifest->asXML($filename);
		
		$app->logEvent('Created manifest file ' . $filename . ' for ' . $this->type . ' plugin');
	
		return $res;
	
	}
	
	/**
	 * Insert a new db record for the plugin in manifest.
	 *
	 * @param	string	Manifest file XML content.
	 */
	public static function createPluginByManifest($manifestContent) {
	
		$app = Application::getInstance();
	
		// gets installation info by manifest file
		$manifest = simplexml_load_string($manifestContent);
	
		// plugin tree
		$mPlugin = $manifest->plugin;
	
		$mAttributes = $mPlugin->attributes();
	
		// gets class name for this plugin (Module, Template or other)
		$pluginClass = ucfirst($mAttributes->type);
		
		// checks if class name is in Pair framework namespace
		$class = class_exists('Pair\\' . $pluginClass) ? 'Pair\\' . $pluginClass : '\\' . $pluginClass;
	
		// creates plugin object
		$plugin					= new $class();
		$plugin->name			= (string)$mPlugin->name;
		$plugin->version		= (string)$mPlugin->version;
		$plugin->dateReleased	= date('Y-m-d H:i:s', strtotime((string)$mPlugin->dateReleased));
		$plugin->installedBy	= $app->currentUser->id;
		$plugin->dateInstalled	= date('Y-m-d H:i:s');
		$plugin->appVersion		= (string)$mPlugin->appVersion;

		// calls specific plugin subclass function
		$functionName	= 'install' . ucfirst($mAttributes->type) . 'Package';
		if (method_exists(get_called_class(), $functionName)) {
			self::$functionName($plugin, $manifest);
		}
		
		// saves plugin object to db
		$plugin->create();
	
	}
	
	/**
	 * This method is automatically called by installPackage() when a template type
	 * being installs.
	 *
	 * @param	Template			Plugin subclass object.
	 * @param	SimpleXMLElement	Manifest file.
	 */
	protected static function installTemplatePackage($plugin, $manifest) {
	
		// get options
		$options = $manifest->plugin->options->children();
	
		$plugin->derived = (bool)$options->derived;
		
		// temp variable
		$palette = array();
	
		// the needed cast to string for each property
		foreach ($options->palette->children() as $color) {
			$palette[] = (string)$color;
		}
	
		// assigns to Template palette property
		$plugin->palette = $palette;
	
	}
	
	/**
	 * This method is automatically called by createManifestFile() when type equals template.
	 *
	 * @param	SimpleXMLElement	Manifest file.
	 */
	protected function createTemplateManifest($manifest) {
	
		foreach ($this->options as $optionName=>$optionValue) {
	
			switch ($optionName) {
	
				case 'derived':
					$derivedChild = $manifest->plugin->options->addChild('derived', (string)intval($optionValue));
					break;
				
				// if the options has name palette, will split for each color
				case 'palette':
					$paletteChild = $manifest->plugin->options->addChild('palette');
					foreach ($optionValue as $color) {
						$paletteChild->addChild('color', $color);
					}
					break;
						
			}
				
		}
	
	}
	
}
