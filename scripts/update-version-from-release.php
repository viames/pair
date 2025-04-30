<?php

/**
 * Update the version in the .env file with the latest release from the Git repository
 */

// definition of the path to the .env file
$envFile = dirname(__DIR__, 4) . '/.env';

// check if the .env file exists
if (!file_exists($envFile)) {
	print "Error: File .env non found in " . $envFile . PHP_EOL;
	exit(1);
}

// obtain the latest tag from the current branch
$gitRelease = trim(shell_exec('git describe --tags --abbrev=0 2>/dev/null'));

// if no tags are found in the current branch, try to get the latest tag in general
if (empty($gitRelease)) {
	$gitRelease = trim(shell_exec('git for-each-ref refs/tags/ --count=1 --sort=-creatordate --format="%(refname:short)"'));

	// if still no tags, use the current branch
	if (empty($gitRelease)) {
		$gitRelease = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null'));
	}
}

// check if a valid Git release was found
if (empty($gitRelease)) {
	print "Error: Unable to get the Git release. Check that the repository has at least one release or branch." . PHP_EOL;
	exit(1);
}

// remove the 'release-' or 'v' prefix if present
$version = ltrim($gitRelease, 'release-v');

// read the content of the .env file
$envContent = file_get_contents($envFile);

// search for an existing version definition in the file
$pattern = '/^APP_VERSION[ ]*=(.*)$/m';

// search for the APP_VERSION line
if (preg_match($pattern, $envContent, $matches)) {

	// extract current version from .env
	$currentVersion = trim($matches[1]);

	// compare with Git version
	if ($currentVersion === $version) {
		print "Notice: Version {$version} is already set in .env file. No changes needed." . PHP_EOL;
		exit(0);
	}

	// replace the existing version
	$envContent = preg_replace($pattern, 'APP_VERSION = ' . $version, $envContent);
	$msg = 'Updated version: ' . $version . PHP_EOL;

} else {

	// add the version if it doesn't exist, right after the line of APP_NAME
	$appNamePattern = '/^APP_NAME=(.*)$/m';

	// APP_NAME not found, exit
	if (!preg_match($appNamePattern, $envContent)) {
		print "Error: APP_NAME not found in the .env file" . PHP_EOL;
		exit(1);
	}

	$envContent = preg_replace($appNamePattern, "APP_NAME=\$1" . PHP_EOL . "APP_VERSION={$version}", $envContent);
	$msg = "Added version: {$version}" . PHP_EOL;

}

// write the updated content to the .env file
if (FALSE === file_put_contents($envFile, $envContent)) {
	print "Error: Unable to write to the .env file" . PHP_EOL;
	exit(1);
}

print $msg;
print "File .env was updated successfully." . PHP_EOL;