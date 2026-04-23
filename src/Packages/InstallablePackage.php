<?php

declare(strict_types=1);

namespace Pair\Packages;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;

/**
 * Manages ZIP-backed installable packages and their manifest lifecycle.
 */
class InstallablePackage {

	/**
	 * Package type (module, template, provider, or a custom package record type).
	 */
	protected ?string $type = null;

	/**
	 * Package name.
	 */
	protected ?string $name = null;

	/**
	 * Absolute path to the installed package folder, without trailing slash.
	 */
	protected ?string $baseFolder = null;

	/**
	 * Package version number.
	 */
	protected ?string $version = null;

	/**
	 * Date of package release in format Y-m-d.
	 */
	protected ?string $dateReleased = null;

	/**
	 * Compatible application version declared by the package.
	 */
	protected ?string $appVersion = null;

	/**
	 * Package option fields serialized into manifest.xml.
	 */
	protected array $options = [];

	/**
	 * Maximum age of generated archives in the temporary folder, in minutes.
	 */
	public const FILE_EXPIRE = 30;

	/**
	 * Creates an InstallablePackage object and initializes its optional metadata.
	 *
	 * @param	string|null	$type			Package type (template, module, provider, or custom).
	 * @param	string|null	$name			Official package name.
	 * @param	string|null	$version		Version number.
	 * @param	string|null	$dateReleased	Date of public release in format Y-m-d.
	 * @param	string|null	$appVersion		Application compatible version.
	 * @param	string|null	$baseFolder		Absolute path to package folder, without trailing slash.
	 * @param	array		$options		Option fields specific to the package type.
	 */
	public function __construct(?string $type = null, ?string $name = null, ?string $version = null, ?string $dateReleased = null, ?string $appVersion = null, ?string $baseFolder = null, array $options = []) {

		$this->type			= $type;
		$this->name			= $name;
		$this->version		= $version;
		$this->dateReleased	= $dateReleased;
		$this->appVersion	= $appVersion;
		$this->baseFolder	= $baseFolder;
		$this->options		= $options;

	}

	/**
	 * Return a package metadata property value.
	 *
	 * @param	string	Property’s name.
	 */
	public function __get(string $name): mixed {

		if (!property_exists($this, $name)) {
			throw new PairException('Package property ' . $name . ' does not exist', ErrorCodes::PROPERTY_NOT_FOUND);
		}

		return $this->$name;

	}

	/**
	 * Set a package metadata property value.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set(string $name, mixed $value): void {

		if (!property_exists($this, $name)) {
			throw new PairException('Package property ' . $name . ' does not exist', ErrorCodes::PROPERTY_NOT_FOUND);
		}

		$this->$name = $value;

	}

	/**
	 * Install a ZIP archive by reading its manifest and copying only manifest-declared files.
	 *
	 * @param	string	$archivePath	Absolute path and file name of the ZIP archive.
	 */
	public function installArchive(string $archivePath): bool {

		if (!is_file($archivePath) or !is_readable($archivePath)) {
			throw new PairException('Package archive ' . $archivePath . ' cannot be read');
		}

		$zip = new \ZipArchive();
		$zipOpened = $zip->open($archivePath);

		if (true !== $zipOpened) {
			throw new PairException('Error opening ZIP package ' . $archivePath . ' (code ' . $zipOpened . ')');
		}

		$packageFolder = null;

		try {
			$manifestIndex = static::locateManifestIndex($zip);
			$manifestPath = static::normalizeArchivePath((string)$zip->getNameIndex($manifestIndex));
			$manifestContents = $zip->getFromIndex($manifestIndex);

			if (!is_string($manifestContents)) {
				throw new PairException('Manifest file cannot be read from package archive');
			}

			$manifest = static::loadManifestXml($manifestContents);
			$record = static::buildRecordFromManifest($manifest);
			$this->baseFolder = $record->getPackageBaseFolder();
			$packageFolder = static::resolvePackageFolder($manifest, $this->baseFolder);

			static::createPackageFolder($packageFolder);
			static::copyManifestFiles($zip, $manifest, $manifestPath, $packageFolder);
			static::storeRecordFromManifest($record, $manifest);
		} catch (\Throwable $error) {
			if (is_string($packageFolder) and is_dir($packageFolder)) {
				Utilities::deleteFolder($packageFolder);
			}

			throw $error;
		} finally {
			$zip->close();
		}

		// Remove only the uploaded archive, never the whole temporary folder.
		Utilities::deleteFolder($archivePath);

		return true;

	}

	/**
	 * Load and validate a manifest XML file from disk.
	 *
	 * @param	string	$file	Path to the manifest file.
	 */
	public static function readManifestFile(string $file): \SimpleXMLElement {

		if (!is_file($file) or !is_readable($file)) {
			throw new PairException('Manifest file ' . $file . ' does not exist or cannot be read');
		}

		$contents = file_get_contents($file);

		if (!is_string($contents)) {
			throw new PairException('Manifest file ' . $file . ' cannot be read');
		}

		return static::loadManifestXml($contents);

	}

	/**
	 * Create and store the package record declared by a manifest.
	 *
	 * @param	\SimpleXMLElement	$manifest	Manifest file XML content.
	 */
	public static function createRecordFromManifest(\SimpleXMLElement $manifest): InstallablePackageRecord {

		$record = static::buildRecordFromManifest($manifest);
		static::storeRecordFromManifest($record, $manifest);

		return $record;

	}

	/**
	 * Build the package record declared by a manifest without storing it.
	 *
	 * @param	\SimpleXMLElement	$manifest	Manifest XML content.
	 */
	private static function buildRecordFromManifest(\SimpleXMLElement $manifest): InstallablePackageRecord {

		$app = Application::getInstance();
		$mPackage = static::getManifestPackageNode($manifest);
		$mAttributes = $mPackage->attributes();
		$type = ucfirst(trim((string)$mAttributes->type));

		if ('' === $type) {
			throw new PairException('Package manifest does not declare a package type');
		}

		// Built-in package record types live in Pair\Models; custom types must be autoloadable.
		$class = in_array($type, ['Module', 'Template'], true) ? 'Pair\\Models\\' . $type : $type;

		if (!class_exists($class) or !is_subclass_of($class, InstallablePackageRecord::class)) {
			throw new PairException('Package record class ' . $class . ' is not available');
		}

		$name = static::readManifestValue($mPackage, 'name');

		if ($class::packageRecordExists($name)) {
			$msg = Translator::do('PACKAGE_IS_ALREADY_INSTALLED', $name);
			throw new PairException($msg, ErrorCodes::PACKAGE_ALREADY_INSTALLED);
		}

		$record = new $class();
		$record->name = $name;
		$record->version = static::readManifestValue($mPackage, 'version');
		$record->dateReleased = static::readManifestDate($mPackage, 'dateReleased')->format('Y-m-d H:i:s');
		$record->appVersion = static::readManifestValue($mPackage, 'appVersion');
		$record->installedBy = $app->currentUser->id;
		$record->dateInstalled = new \DateTime();

		return $record;

	}

	/**
	 * Copy all manifest-declared files from the archive into the package folder.
	 *
	 * @param	\ZipArchive		$zip			Opened ZIP archive.
	 * @param	\SimpleXMLElement	$manifest		Manifest XML content.
	 * @param	string			$manifestPath	Path to manifest.xml inside the archive.
	 * @param	string			$packageFolder	Target package folder.
	 */
	private static function copyManifestFiles(\ZipArchive $zip, \SimpleXMLElement $manifest, string $manifestPath, string $packageFolder): void {

		$files = static::getManifestPackageNode($manifest)->files->children();

		if (!count($files)) {
			throw new PairException('Package manifest does not declare files to install');
		}

		$archiveRoot = dirname($manifestPath);
		$archiveRoot = '.' === $archiveRoot ? '' : $archiveRoot;

		foreach ($files as $file) {
			$relativeFile = static::normalizeArchivePath((string)$file);
			$archiveFile = '' === $archiveRoot ? $relativeFile : $archiveRoot . '/' . $relativeFile;
			$destination = $packageFolder . '/' . $relativeFile;

			static::copyArchiveFile($zip, $archiveFile, $destination);
		}

	}

	/**
	 * Copy one file from the ZIP archive to a destination path.
	 *
	 * @param	\ZipArchive	$zip			Opened ZIP archive.
	 * @param	string		$archiveFile	File path inside the archive.
	 * @param	string		$destination	Absolute destination path.
	 */
	private static function copyArchiveFile(\ZipArchive $zip, string $archiveFile, string $destination): void {

		$source = $zip->getStream($archiveFile);

		if (!is_resource($source)) {
			throw new PairException('Package archive is missing declared file ' . $archiveFile);
		}

		$destinationFolder = dirname($destination);

		if (!is_dir($destinationFolder)) {
			static::createPackageFolder($destinationFolder);
		}

		$target = fopen($destination, 'wb');

		if (!is_resource($target)) {
			fclose($source);
			throw new PairException('Destination file ' . $destination . ' cannot be opened');
		}

		$written = stream_copy_to_stream($source, $target);
		fclose($target);
		fclose($source);

		if (false === $written) {
			throw new PairException('Unable to copy package file ' . $archiveFile);
		}

		chmod($destination, 0664);

	}

	/**
	 * Create a package folder with consistent permissions.
	 *
	 * @param	string	$folder	Folder path to create.
	 */
	private static function createPackageFolder(string $folder): void {

		if (is_dir($folder)) {
			return;
		}

		$old = umask(0);
		$created = mkdir($folder, 0775, true);
		umask($old);

		if (!$created) {
			throw new PairException('Directory creation on ' . $folder . ' failed');
		}

		chmod($folder, 0775);

	}

	/**
	 * Return the package node from the manifest and validate its basic structure.
	 *
	 * @param	\SimpleXMLElement	$manifest	Manifest XML content.
	 */
	private static function getManifestPackageNode(\SimpleXMLElement $manifest): \SimpleXMLElement {

		if (!isset($manifest->package) or !$manifest->package instanceof \SimpleXMLElement) {
			throw new PairException('Package manifest does not contain a package node');
		}

		return $manifest->package;

	}

	/**
	 * Load manifest XML using local-only XML parsing and framework validation.
	 *
	 * @param	string	$contents	Raw manifest XML.
	 */
	private static function loadManifestXml(string $contents): \SimpleXMLElement {

		$previous = libxml_use_internal_errors(true);
		$manifest = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if (!$manifest instanceof \SimpleXMLElement) {
			throw new PairException('Manifest file is not valid XML');
		}

		static::getManifestPackageNode($manifest);

		return $manifest;

	}

	/**
	 * Locate manifest.xml in the archive.
	 *
	 * @param	\ZipArchive	$zip	Opened ZIP archive.
	 */
	private static function locateManifestIndex(\ZipArchive $zip): int {

		$manifestIndex = $zip->locateName('manifest.xml', \ZipArchive::FL_NOCASE | \ZipArchive::FL_NODIR);

		if (false === $manifestIndex) {
			throw new PairException('Package archive does not contain manifest.xml');
		}

		return $manifestIndex;

	}

	/**
	 * Normalize and validate one relative path declared inside a package archive.
	 *
	 * @param	string	$path	Archive path.
	 */
	private static function normalizeArchivePath(string $path): string {

		$path = str_replace('\\', '/', trim($path));
		$path = preg_replace('#/+#', '/', $path);
		$path = is_string($path) ? $path : '';

		if ('' === $path or str_contains($path, "\0") or str_starts_with($path, '/')) {
			throw new PairException('Package archive path is not valid');
		}

		$parts = [];

		foreach (explode('/', $path) as $part) {
			if ('' === $part or '.' === $part) {
				continue;
			}

			if ('..' === $part) {
				throw new PairException('Package archive path cannot traverse parent folders');
			}

			$parts[] = $part;
		}

		if (!count($parts)) {
			throw new PairException('Package archive path is not valid');
		}

		return implode('/', $parts);

	}

	/**
	 * Read a required manifest value as a trimmed string.
	 *
	 * @param	\SimpleXMLElement	$node	Manifest package node.
	 * @param	string				$name	Child element name.
	 */
	private static function readManifestValue(\SimpleXMLElement $node, string $name): string {

		$value = trim((string)$node->{$name});

		if ('' === $value) {
			throw new PairException('Package manifest does not define ' . $name);
		}

		return $value;

	}

	/**
	 * Read a required manifest date value.
	 *
	 * @param	\SimpleXMLElement	$node	Manifest package node.
	 * @param	string				$name	Child element name.
	 */
	private static function readManifestDate(\SimpleXMLElement $node, string $name): \DateTimeImmutable {

		try {
			return new \DateTimeImmutable(static::readManifestValue($node, $name));
		} catch (\Throwable $error) {
			throw new PairException('Package manifest date ' . $name . ' is not valid', 0, $error);
		}

	}

	/**
	 * Resolve and validate the final installation folder for the package.
	 *
	 * @param	\SimpleXMLElement	$manifest	Manifest XML content.
	 * @param	string				$baseFolder	Base folder for this package type.
	 */
	private static function resolvePackageFolder(\SimpleXMLElement $manifest, string $baseFolder): string {

		$folder = static::normalizeArchivePath(static::readManifestValue(static::getManifestPackageNode($manifest), 'folder'));

		if (str_contains($folder, '/')) {
			throw new PairException('Package folder must be a single folder name');
		}

		return rtrim($baseFolder, '/') . '/' . strtolower($folder);

	}

	/**
	 * Store package-specific manifest data on a package record.
	 *
	 * @param	InstallablePackageRecord	$record		Record to store.
	 * @param	\SimpleXMLElement		$manifest	Manifest XML content.
	 */
	private static function storeRecordFromManifest(InstallablePackageRecord $record, \SimpleXMLElement $manifest): void {

		$packageNode = static::getManifestPackageNode($manifest);

		if (!$record->storeFromPackageManifest($packageNode->options)) {
			throw new PairException('Package record could not be stored');
		}

	}

	/**
	 * Update manifest.xml, create a ZIP archive, and send it as a download response.
	 */
	public function downloadArchive(): void {

		if (!is_string($this->baseFolder) or !is_dir($this->baseFolder)) {
			throw new PairException('Package folder is missing');
		}

		$this->writeManifestFile();

		// Remove expired generated archives before creating the new one.
		static::removeOldArchives();

		// useful paths
		$pathInfos	= pathInfo($this->baseFolder);
		$parentPath	= $pathInfos['dirname'];
		$dirName	= $pathInfos['basename'];

		// creates unique file name
		$filename	= Utilities::uniqueFilename((string)$this->name . ucfirst((string)$this->type) . '.zip', TEMP_PATH);
		$zipFile	= TEMP_PATH . $filename;

		// creates the ZIP archive
		$zip = new \ZipArchive();
		$zipOpened = $zip->open($zipFile, \ZipArchive::CREATE);

		if (true !== $zipOpened) {
			throw new PairException('Unable to create ZIP package archive ' . $zipFile . ' (code ' . $zipOpened . ')');
		}

		$zip->addEmptyDir($dirName);

		// recursive function to add all files in all sub-folders
		self::addFolderToArchive($this->baseFolder, $zip, $parentPath);

		$zip->close();

		// add some log
		$logger = Logger::getInstance();
		$logger->debug('Created ZIP package archive ' . $zipFile . ' for ' . $this->type . ' package');

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
	private static function addFolderToArchive(string $folder, \ZipArchive &$zipFile, string $parentPath): void {

		$handle = opendir($folder);

		if (!is_resource($handle)) {
			throw new PairException('Package folder ' . $folder . ' cannot be opened');
		}

		while (false !== $f = readdir($handle)) {

			// list of unwanted files
			$excluded = ['.', '..', '.DS_Store', 'thumbs.db'];

			if (!in_array($f, $excluded)) {

				$filePath = $folder . '/' . $f;

				// removes prefix from file path before add to zip
				if (0 === strpos($filePath, $parentPath)) {
					$localPath = substr($filePath, strlen($parentPath . '/'));
				} else {
					throw new PairException('Package file ' . $filePath . ' is outside the package parent folder');
				}

				if (is_file($filePath)) {

					$zipFile->addFile($filePath, $localPath);

				} elseif (is_dir($filePath)) {

					// adds sub-directory
					$zipFile->addEmptyDir($localPath);
					self::addFolderToArchive($filePath, $zipFile, $parentPath);
				}

			}

		}

		closedir($handle);

	}

	/**
	 * Deletes generated archive files older than FILE_EXPIRE.
	 */
	public static function removeOldArchives(): void {

		if (!is_dir(TEMP_PATH)) {
			$logger = Logger::getInstance();
			$logger->error('Folder {path} cannot be accessed', ['path' => TEMP_PATH]);
			return;
		}

		$counter = 0;

		$files = Utilities::getDirectoryFilenames(TEMP_PATH);

		foreach ($files as $file) {

			$pathFile	= TEMP_PATH . '/' . $file;

			if (!is_file($pathFile) or 'zip' !== strtolower(pathinfo($pathFile, PATHINFO_EXTENSION))) {
				continue;
			}

			$fileLife	= time() - filemtime($pathFile);
			$maxLife	= static::FILE_EXPIRE * 60;

			if ($fileLife > $maxLife) {
				$counter++;
				unlink($pathFile);
			}

		}

		$logger = Logger::getInstance();
		$logger->info($counter
			? $counter . ' package archives have been deleted from temporary folder'
			: 'No old package archives deleted from temporary folder'
		);

	}

	/**
	 * Creates or updates manifest.xml to declare package content.
	 */
	public function writeManifestFile(): bool {

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
		if (!is_string($this->baseFolder) or !is_dir($this->baseFolder)) {
			$logger = Logger::getInstance();
			$logger->error('Folder {path} of {name} {type} package cannot be accessed', [
				'path' => $this->baseFolder,
				'name' => $this->name,
				'type' => $this->type
			]);
			return false;
		}

		$manifest = new \SimpleXMLElement('<!DOCTYPE xml><manifest></manifest>');

		$package = $manifest->addChild('package');

		// Add the package type used to resolve the package record class.
		$package->addAttribute('type', (string)$this->type);

		// more infos
		$package->addChild('name',			(string)$this->name);
		$package->addChild('folder',		basename((string)$this->baseFolder));
		$package->addChild('version',		(string)$this->version);
		$package->addChild('dateReleased',	(string)$this->dateReleased);
		$package->addChild('appVersion',	(string)$this->appVersion);

		// custom options
		$optionsChild = $package->addChild('options');

		// recursively add options elements
		$addChildArray($optionsChild, $this->options);

		// will contains all found files
		$filesChild = $package->addChild('files');

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
