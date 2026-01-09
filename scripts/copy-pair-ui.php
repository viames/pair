<?php

/**
 * Copy PairUI.js into the public assets directory (or a custom target path).
 *
 * Usage:
 *   php vendor/viames/pair/scripts/copy-pair-ui.php [targetPath]
 */

$projectRoot = dirname(__DIR__, 4);
$defaultTarget = $projectRoot . '/public/assets/pair.ui.js';
$targetArg = $argv[1] ?? '';

if ($targetArg !== '' and $targetArg[0] !== DIRECTORY_SEPARATOR) {
	$targetPath = $projectRoot . '/' . ltrim($targetArg, '/');
} else {
	$targetPath = $targetArg !== '' ? $targetArg : $defaultTarget;
}

$sourceCandidates = [
	__DIR__ . '/PairUI.js',
	dirname(__DIR__) . '/assets/PairUI.js',
];

$sourcePath = '';
foreach ($sourceCandidates as $candidate) {
	if (file_exists($candidate)) {
		$sourcePath = $candidate;
		break;
	}
}

if ($sourcePath === '') {
	print "Error: PairUI.js not found in expected locations." . PHP_EOL;
	exit(1);
}

$targetDir = dirname($targetPath);
if (!is_dir($targetDir)) {
	if (!mkdir($targetDir, 0755, true) and !is_dir($targetDir)) {
		print "Error: Unable to create target directory: {$targetDir}" . PHP_EOL;
		exit(1);
	}
}

if (!copy($sourcePath, $targetPath)) {
	print "Error: Unable to copy PairUI.js to {$targetPath}" . PHP_EOL;
	exit(1);
}

print "Copied PairUI.js to {$targetPath}" . PHP_EOL;
