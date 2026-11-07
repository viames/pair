<?php

declare(strict_types=1);

/** Verify distributable packages through real, offline Composer installs in disposable projects. */
$frameworkRoot = dirname(__DIR__, 3);
require $frameworkRoot . '/vendor/autoload.php';
$starterRoot = $argv[1] ?? null;
$workspace = sys_get_temp_dir() . '/pair-install-' . bin2hex(random_bytes(8));
mkdir($workspace, 0700, true);
mkdir($workspace . '/archives');
mkdir($workspace . '/composer-home');
$environment = array_merge(getenv(), [
	'COMPOSER_HOME' => $workspace . '/composer-home',
	'COMPOSER_CACHE_DIR' => $workspace . '/composer-cache',
	'COMPOSER_NO_INTERACTION' => '1',
	'COMPOSER_DISABLE_NETWORK' => '1',
	'PAIR_INSTALL_PHPUNIT' => $frameworkRoot . '/vendor/bin/phpunit',
]);
$composer = getenv('COMPOSER_BINARY') ?: 'composer';
$exitCode = 0;

/** Execute argument arrays without a shell and preserve useful failure diagnostics. */
function installationRun(array $command, string $directory, array $environment): string {
	$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, $environment);
	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start installation command.');
	}
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[2]);
	$status = proc_close($process);
	if ($status !== 0) {
		throw new RuntimeException(implode(' ', $command) . " failed ({$status}):\n" . substr($stdout . $stderr, -12000));
	}
	return $stdout . $stderr;
}

/** Fail the smoke check before it can be mistaken for a successful release gate. */
function installationCheck(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/** Produce the actual Composer dist and add only a synthetic artifact version for local resolution. */
function installationArchive(string $source, string $file, string $version, string $workspace, string $composer, array $environment): void {
	installationRun([$composer, '--no-plugins', 'archive', '--format=zip', '--dir=' . $workspace . '/archives', '--file=' . $file], $source, $environment);
	$zip = new ZipArchive();
	installationCheck($zip->open($workspace . '/archives/' . $file . '.zip') === true, 'Cannot inspect Composer archive.');
	$metadata = json_decode($zip->getFromName('composer.json'), true, flags: JSON_THROW_ON_ERROR);
	$metadata['version'] = $version;
	$zip->addFromString('composer.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	$zip->close();
}

/** Remove only this run's newly allocated workspace, including custom Composer vendor trees. */
function installationRemove(string $path): void {
	if (is_dir($path) && !is_link($path)) {
		foreach (new FilesystemIterator($path) as $file) {
			installationRemove($file->getPathname());
		}
		rmdir($path);
	} else {
		unlink($path);
	}
}

try {
	installationCheck(class_exists(ZipArchive::class), 'The installation test requires the zip extension.');
	// No remote registry or credentials: both runtime packages come from the local checkout/install.
	$repositories = [['type' => 'artifact', 'url' => $workspace . '/archives'], ['packagist.org' => false]];
	file_put_contents($workspace . '/composer-home/config.json', json_encode(['repositories' => $repositories], JSON_THROW_ON_ERROR));
	installationArchive($frameworkRoot, 'pair', '4.1.12', $workspace, $composer, $environment);
	installationArchive(Composer\InstalledVersions::getInstallPath('psr/log'), 'psr-log', Composer\InstalledVersions::getPrettyVersion('psr/log'), $workspace, $composer, $environment);
	if ($starterRoot !== null) {
		// Composer resolves dev requirements even with --no-dev; keep fresh resolution fully offline.
		foreach (Composer\InstalledVersions::getInstalledPackages() as $package) {
			if (in_array($package, ['viames/pair', 'psr/log'], true)) {
				continue;
			}
			$path = Composer\InstalledVersions::getInstallPath($package);
			if ($path !== null) {
				installationArchive($path, str_replace('/', '-', $package), Composer\InstalledVersions::getPrettyVersion($package), $workspace, $composer, $environment);
			}
		}
	}

	foreach (['vendor', 'dependencies'] as $vendorDirectory) {
		$project = $workspace . '/project-' . $vendorDirectory;
		mkdir($project);
		file_put_contents($project . '/composer.json', json_encode([
			'name' => 'pair-test/consumer',
			'require' => ['viames/pair' => '4.1.12'],
			'repositories' => $repositories,
			'config' => ['vendor-dir' => $vendorDirectory, 'bin-dir' => 'tools', 'allow-plugins' => false],
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
		installationRun([$composer, '--no-plugins', 'install', '--no-progress', '--no-dev'], $project, $environment);
		$package = $project . '/' . $vendorDirectory . '/viames/pair';
		installationCheck(!is_dir($package . '/vendor'), 'The package must not ship its own vendor tree.');
		installationCheck(!is_dir($package . '/tests'), 'Composer dist must exclude framework tests.');
		installationRun([PHP_BINARY, $project . '/tools/pair', 'make:module', 'orders'], $project, $environment);
		installationRun([PHP_BINARY, $project . '/tools/pair', 'make:api', 'catalogapi'], $project, $environment);
		installationRun([PHP_BINARY, $project . '/tools/pair', 'make:crud', 'order', '--table=orders', '--fields=id,customer_id,total_amount'], $project, $environment);
		installationRun([PHP_BINARY, $package . '/bin/pair', '--help'], $project, $environment);
		foreach (['modules/orders/controller.php', 'modules/orders/classes/OrdersDefaultPageState.php', 'modules/orders/layouts/default.php', 'modules/catalogapi/controller.php', 'models/Order.php', 'classes/OrderReadModel.php'] as $file) {
			installationCheck(is_file($project . '/' . $file), 'Missing generated file: ' . $file);
			installationRun([PHP_BINARY, '-l', $project . '/' . $file], $project, $environment);
		}
		echo 'PASS: Composer archive, ' . $vendorDirectory . '/, custom bin-dir, direct executable and all generators.' . PHP_EOL;
	}

	if ($starterRoot !== null) {
		$starterRoot = realpath($starterRoot);
		installationCheck($starterRoot !== false && is_file($starterRoot . '/composer.json'), 'Expected a starter checkout.');
		$metadata = json_decode(file_get_contents($starterRoot . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
		installationCheck(($metadata['name'] ?? '') === 'viames/pair_boilerplate', 'Unexpected starter package name.');
		installationArchive($starterRoot, 'starter', '4.0.0', $workspace, $composer, $environment);
		$project = $workspace . '/starter';
		// Resolve the latest stable artifact with no dev-main or subsequent composer require workaround.
		installationRun([$composer, '--no-plugins', 'create-project', '--repository=' . json_encode($repositories[0]), '--no-dev', '--no-progress', '--no-audit', 'viames/pair_boilerplate', $project], $workspace, $environment);
		$lock = json_decode(file_get_contents($project . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
		$pair = array_values(array_filter($lock['packages'], static fn(array $package): bool => $package['name'] === 'viames/pair'));
		installationCheck(($pair[0]['version'] ?? null) === '4.1.12', 'The stable starter did not install the candidate Pair 4 package.');
		installationCheck(!file_exists($project . '/.env'), 'Fresh install must not create credentials or configure a database.');
		installationRun([$composer, 'run', 'pair', '--', 'make:module', 'orders'], $project, $environment);
		installationRun([PHP_BINARY, $project . '/vendor/bin/pair', 'make:api', 'catalogapi'], $project, $environment);
		installationCheck(is_file($project . '/modules/orders/controller.php') && is_file($project . '/modules/catalogapi/controller.php'), 'Starter CLI entrypoints did not generate their files.');
		// Render only the first installer page; no form submission, DB access or application bootstrapping.
		$probe = $workspace . '/installer-probe.php';
		file_put_contents($probe, '<?php $_SERVER["PHP_SELF"] = "/index.php"; $_SERVER["REQUEST_METHOD"] = "GET"; require ' . var_export($project . '/public/index.php', true) . ';');
		$html = installationRun([PHP_BINARY, $probe], $project, $environment);
		installationCheck(str_contains($html, 'Configure the first database connection') && !str_contains($html, 'Fatal error') && !str_contains($html, 'Warning:'), 'The fresh installer did not render cleanly.');
		installationCheck(!file_exists($project . '/.env'), 'Installer GET must not write database configuration.');
		echo installationRun([PHP_BINARY, $frameworkRoot . '/vendor/bin/phpunit', '-c', $project . '/phpunit.xml.dist'], $project, $environment);
		echo 'PASS: stable create-project installs Pair 4, Composer hooks skip an unconfigured DB, both CLIs work and installer GET renders.' . PHP_EOL;
		if (getenv('PAIR_INSTALL_DB_NAME') !== false) {
			echo installationRun([PHP_BINARY, $project . '/tests/install-database.php'], $project, $environment);
		}
	}
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL);
	$exitCode = 1;
} finally {
	if (getenv('PAIR_INSTALL_KEEP') === '1') {
		echo 'Kept installation fixtures: ' . $workspace . PHP_EOL;
	} else {
		installationRemove($workspace);
	}
}

exit($exitCode);
