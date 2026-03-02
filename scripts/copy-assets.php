<?php

/**
 * Copy all the assets into the public assets directory (or a custom target path).
 *
 * Usage:
 *   php vendor/viames/pair/scripts/copy-assets.php [targetFolder] [js|css]
 */

// when executed from vendor/.../scripts, this points to the host project root.
$projectRoot = dirname(__DIR__, 4);
$defaultTarget = $projectRoot . '/public/assets';
$targetArg = $argv[1] ?? '';
$assetTypeArg = strtolower($argv[2] ?? '');
$assetType = '';

if ($assetTypeArg !== '') {
	if (!in_array($assetTypeArg, array('js', 'css'), true)) {
		print "Error: Invalid asset type '{$assetTypeArg}'. Allowed values: js, css." . PHP_EOL;
		exit(1);
	}

	$assetType = $assetTypeArg;
}

// treat relative targets as project-root relative paths.
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
	// directories are created on demand before copying files.
	if ($item->isDir()) {
		continue;
	}

	$relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
	$destination = $targetPath . DIRECTORY_SEPARATOR . $relativePath;
	$extension = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));

	// optionally copy only one asset type (js or css).
	if ($assetType !== '' and $extension !== $assetType) {
		continue;
	}

	$destinationDir = dirname($destination);
	// keep the source folder structure inside the target path.
	if (!is_dir($destinationDir)) {
		if (!mkdir($destinationDir, 0755, true) and !is_dir($destinationDir)) {
			print "Error: Unable to create directory: {$destinationDir}" . PHP_EOL;
			exit(1);
		}
	}

	if (!copy($item->getPathname(), $destination)) {
		print "Error: Unable to copy {$relativePath} to {$destination}" . PHP_EOL;
		exit(1);
	}
}

$summary = $assetType !== '' ? "{$assetType} assets" : 'assets';
print "Copied {$summary} to {$targetPath}" . PHP_EOL;
