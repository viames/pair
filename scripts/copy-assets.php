<?php

/**
 * Copy all the assets into the public assets directory (or a custom target path).
 *
 * Usage:
 *   php vendor/viames/pair/scripts/copy-assets.php [targetFolder]
 */

$projectRoot = dirname(__DIR__, 4);
$defaultTarget = $projectRoot . '/public/assets';
$targetArg = $argv[1] ?? '';

if ($targetArg !== '' and $targetArg[0] !== DIRECTORY_SEPARATOR) {
	$targetPath = $projectRoot . '/' . ltrim($targetArg, '/');
} else {
	$targetPath = $targetArg !== '' ? $targetArg : $defaultTarget;
}

$sourceDir = dirname(__DIR__) . '/assets';
if (!is_dir($sourceDir)) {
	print "Error: assets directory not found: {$sourceDir}" . PHP_EOL;
	exit(1);
}

if (!is_dir($targetPath)) {
	if (!mkdir($targetPath, 0755, true) and !is_dir($targetPath)) {
		print "Error: Unable to create target directory: {$targetPath}" . PHP_EOL;
		exit(1);
	}
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
	$relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
	$destination = $targetPath . DIRECTORY_SEPARATOR . $relativePath;

	if ($item->isDir()) {
		if (!is_dir($destination)) {
			if (!mkdir($destination, 0755, true) and !is_dir($destination)) {
				print "Error: Unable to create directory: {$destination}" . PHP_EOL;
				exit(1);
			}
		}
		continue;
	}

	if (!copy($item->getPathname(), $destination)) {
		print "Error: Unable to copy {$relativePath} to {$destination}" . PHP_EOL;
		exit(1);
	}
}

print "Copied assets to {$targetPath}" . PHP_EOL;
