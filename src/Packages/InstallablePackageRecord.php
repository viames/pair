<?php

namespace Pair\Packages;

use Pair\Core\Env;
use Pair\Orm\ActiveRecord;

/**
 * Base class for records created from installable packages.
 */
abstract class InstallablePackageRecord extends ActiveRecord {

	/**
	 * Returns the absolute folder where records of this package type are installed.
	 */
	abstract public function getPackageBaseFolder(): string;

	/**
	 * Creates and returns an InstallablePackage object for this installed record.
	 */
	abstract public function getInstallablePackage(): InstallablePackage;

	/**
	 * Check if package is compatible with current application version.
	 */
	public function isCompatibleWithApp(): bool {
		
		// Check the package-declared target version against the current application version.
		return version_compare($this->appVersion, Env::get('APP_VERSION'), '>=');

	}

	/**
	 * Check if package is compatible with current application major version.
	 */
	public function isCompatibleWithAppMajorVersion(): bool {

		$appMajorVersion = (int)explode('.', Env::get('APP_VERSION'))[0];
		$packageMajorVersion = (int)explode('.', $this->appVersion)[0];
		
		return ($packageMajorVersion >= $appMajorVersion);

	}

	/**
	 * Checks if a package record is already installed.
	 * @param	string	Name of package record to search.
	 */
	abstract public static function packageRecordExists(string $name): bool;

	/**
	 * Store package-specific options loaded from the manifest.
	 * @param	SimpleXMLElement	List of options.
	 */
	abstract public function storeFromPackageManifest(\SimpleXMLElement $options): bool;

}
