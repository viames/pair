<?php

/**
 * This script updates all the files in the application directory and its subdirectories
 * replacing the necessary code to make the application compatible with Pair v3.
 */

print "Pair v2 to v3 migration script\n";
print "===============================\n";

// set the root directory of the Pair project
define('APP_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));

// check if the script is running from the Pair root directory
if (!file_exists(APP_ROOT . '/vendor/autoload.php')) {
	die("Please run this script from the Pair root directory.\n");
}

// print the absolute path of the Pair root directory
print "Pair root directory: " . APP_ROOT . "\n";

$envPath = APP_ROOT . '/.env';

$directory = new RecursiveDirectoryIterator(APP_ROOT);
$iterator = new RecursiveIteratorIterator($directory);

// search only in PHP files
$files = new RegexIterator($iterator, '/\.php$/');

// exclude the files and folders that should not be modified
$escludeList = [
	'vendor/',
	'.git/'
];

$updatedFiles = 0;

// loop through all the files
foreach ($files as $file) {

	foreach ($escludeList as $esclude) {
		if (FALSE !== strpos($file->getPathname(), $esclude)) {
			continue 2;
		}
	}

	// get the content of the file
	$content = file_get_contents($file);

	// replace the old functions with the new ones
	$content = str_replace('protected function init(): void {', 'protected function _init(): void {', $content);

	// Env
	$content = str_replace('Pair\Core\Env', 'Pair\Core\Env', $content);
	$content = str_replace('Config::get(', 'Env::get(', $content);

	// APP_NAME, APP_VERSION and APP_ENV
	$content = str_replace('PRODUCT_NAME', 'APP_NAME', $content);
	$content = str_replace('PRODUCT_VERSION', 'APP_VERSION', $content);
	$content = str_replace('PAIR_ENVIRONMENT', 'APP_ENV', $content);

	// update the file only if the content has changed
	if ($content !== file_get_contents($file)) {
		file_put_contents($file->getPathname(), $content);
		$updatedFiles++;
	}

}

print "Updated files: $updatedFiles\n";
print "===============================\n";
print "Upgrade completed.\n";