<?php

/**
 * Update the version in the .env file with the latest tag from the Git repository
 */

// definition of the path to the .env file
$envFile = dirname(__DIR__, 4) . '/.env';

// check if the .env file exists
if (!file_exists($envFile)) {
    echo "Error: File .env non found in " . $envFile . PHP_EOL;
    exit(1);
}

// read the latest tag of the current branch from the Git repository
$gitTag = trim(shell_exec('git describe --tags --abbrev=0 2>/dev/null'));

// check if a valid Git tag was found
if (empty($gitTag)) {
    echo "Error: Unable to get the Git tag. Check that the repository has at least one tag." . PHP_EOL;
    exit(1);
}

// remove the 'v' prefix if present
$version = ltrim($gitTag, 'v');

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
        echo "Notice: Version {$version} is already set in .env file. No changes needed." . PHP_EOL;
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
        echo "Error: APP_NAME not found in the .env file" . PHP_EOL;
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