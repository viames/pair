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

// update .env keys if present
if (file_exists($envPath)) {
	$envContent = file_get_contents($envPath);
	$originalEnvContent = $envContent;

	$envContent = preg_replace('/^PRODUCT_NAME=/m', 'APP_NAME=', $envContent);
	$envContent = preg_replace('/^PRODUCT_VERSION=/m', 'APP_VERSION=', $envContent);
	$envContent = preg_replace('/^PAIR_ENVIRONMENT=/m', 'APP_ENV=', $envContent);
	$envContent = preg_replace('/^PAIR_DEBUG=/m', 'APP_DEBUG=', $envContent);

	if ($envContent !== $originalEnvContent) {
		file_put_contents($envPath, $envContent);
		print ".env file updated.\n";
	}
} else {
	print ".env file not found.\n";
}

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
$filesWithConfig = [];
$filesWithInit = [];
$filesWithOldEnvKeys = [];
$filesWithDatabaseConstants = [];
$filesWithPairJson = [];

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
	$content = preg_replace('/\b(protected|public|private)\s+function\s+init\s*\(/', '$1 function _init(', $content);

	// Env
	$content = str_replace('Pair\Core\Config', 'Pair\Core\Env', $content);
	$content = str_replace('Pair\\Config', 'Pair\\Core\\Env', $content);
	$content = str_replace('Config::get(', 'Env::get(', $content);
	$content = str_replace('Config::', 'Env::', $content);
	$content = str_replace('use Pair\\Core\\Config;', 'use Pair\\Core\\Env;', $content);
	$content = str_replace('use Pair\\Config;', 'use Pair\\Core\\Env;', $content);

	// APP_NAME, APP_VERSION and APP_ENV
	$content = str_replace('PRODUCT_NAME', 'APP_NAME', $content);
	$content = str_replace('PRODUCT_VERSION', 'APP_VERSION', $content);
	$content = str_replace('PAIR_ENVIRONMENT', 'APP_ENV', $content);

	// Page title: v2 script introduced setPageTitle(), Pair3 uses pageTitle()
	$content = str_replace('setPageTitle(', 'pageTitle(', $content);

	// Database constants mapped by v2 script (PAIR_DB_* -> Database::*) are not in Pair3
	$content = preg_replace('/\bDatabase::HOST\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_HOST\')', $content);
	$content = preg_replace('/\bDatabase::USER\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_USER\')', $content);
	$content = preg_replace('/\bDatabase::PASS\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_PASS\')', $content);
	$content = preg_replace('/\bDatabase::NAME\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_NAME\')', $content);
	$content = preg_replace('/\bDatabase::UTF8\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_UTF8\')', $content);
	$content = preg_replace('/\bDatabase::PORT\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_PORT\')', $content);

	// Avoid deprecated static call on instance (v2 script used $app->getEnvironment()).
	$content = str_replace('$app->getEnvironment()', '\\Pair\\Core\\Application::getEnvironment()', $content);
	$content = str_replace('$this->app->getEnvironment()', '\\Pair\\Core\\Application::getEnvironment()', $content);

	// Templates: Pair3 uses {{styles}}/{{scripts}} placeholders
	if (0 === strpos($file->getPathname(), APP_ROOT . '/templates/')) {
		$content = str_replace('$app->printStyles()', '{{styles}}', $content);
		$content = str_replace('$this->app->printStyles()', '{{styles}}', $content);
		$content = str_replace('$app->printScripts()', '{{scripts}}', $content);
		$content = str_replace('$this->app->printScripts()', '{{scripts}}', $content);
		$content = str_replace('<?php print $this->pageStyles ?>', '{{styles}}', $content);
		$content = str_replace('<?php print $this->pageScripts ?>', '{{scripts}}', $content);
		$content = str_replace('<?php print $this->pageStyles; ?>', '{{styles}}', $content);
		$content = str_replace('<?php print $this->pageScripts; ?>', '{{scripts}}', $content);
	}

	// update the file only if the content has changed
	if ($content !== file_get_contents($file)) {
		file_put_contents($file->getPathname(), $content);
		$updatedFiles++;
	}

	// collect warnings for manual review
	if (preg_match('/\bConfig::/', $content)) {
		$filesWithConfig[] = $file->getPathname();
	}
	if (preg_match('/\bfunction\s+init\s*\(/', $content)) {
		$filesWithInit[] = $file->getPathname();
	}
	if (preg_match('/\b(PRODUCT_NAME|PRODUCT_VERSION|PAIR_ENVIRONMENT|PAIR_DEBUG)\b/', $content)) {
		$filesWithOldEnvKeys[] = $file->getPathname();
	}
	if (preg_match('/\bDatabase::(HOST|USER|PASS|NAME|UTF8|PORT)\b/', $content)) {
		$filesWithDatabaseConstants[] = $file->getPathname();
	}
	if (preg_match('/\bpairJson(Error|Message|Success|Data)\s*\(/', $content)) {
		$filesWithPairJson[] = $file->getPathname();
	}

}

print "Updated files: $updatedFiles\n";
if (count($filesWithConfig)) {
	print "Warning: remaining Config:: usages found in:\n";
	foreach ($filesWithConfig as $path) {
		print "- $path\n";
	}
}
if (count($filesWithInit)) {
	print "Warning: remaining init() methods found in:\n";
	foreach ($filesWithInit as $path) {
		print "- $path\n";
	}
}
if (count($filesWithOldEnvKeys)) {
	print "Warning: old env keys still referenced in:\n";
	foreach ($filesWithOldEnvKeys as $path) {
		print "- $path\n";
	}
}
if (count($filesWithDatabaseConstants)) {
	print "Warning: Database::* constants still referenced in:\n";
	foreach ($filesWithDatabaseConstants as $path) {
		print "- $path\n";
	}
}
if (count($filesWithPairJson)) {
	print "Warning: deprecated pairJson* helpers still used in:\n";
	foreach ($filesWithPairJson as $path) {
		print "- $path\n";
	}
}
print "===============================\n";
print "Upgrade completed.\n";
