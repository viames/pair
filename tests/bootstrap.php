<?php

declare(strict_types=1);

/**
 * Bootstrap the PHPUnit test suite with an isolated application path and Composer autoloading.
 */
$workspaceRoot = dirname(__DIR__);
$testApplicationPath = sys_get_temp_dir() . '/pair-framework-tests';

if (!defined('APPLICATION_PATH')) {
	define('APPLICATION_PATH', $testApplicationPath);
}

if (!defined('TEMP_PATH')) {
	define('TEMP_PATH', APPLICATION_PATH . '/tmp/');
}

if (!is_dir(APPLICATION_PATH)) {
	mkdir(APPLICATION_PATH, 0777, true);
}

if (!is_dir(TEMP_PATH)) {
	mkdir(TEMP_PATH, 0777, true);
}

$autoloadFile = $workspaceRoot . '/vendor/autoload.php';

if (!file_exists($autoloadFile)) {
	throw new RuntimeException('Run "composer install" before executing the Pair test suite.');
}

require $autoloadFile;
