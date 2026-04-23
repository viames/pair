<?php

declare(strict_types=1);

/**
 * Upgrade a Pair v3 application tree to the explicit Pair v4 contracts.
 */
exit(main($argv));

/**
 * Execute the upgrade workflow and print a clear report.
 *
 * @param	string[]	$argv	Raw command-line arguments.
 */
function main(array $argv): int {

	$options = parseArguments($argv);

	if ($options['help']) {
		printUsage();
		return 0;
	}

	$targetPath = resolveTargetPath($options['path']);

	if (!is_dir($targetPath)) {
		fwrite(STDERR, 'Target path not found: ' . $targetPath . PHP_EOL);
		return 1;
	}

	$changedFiles = [];
	$warnings = [];

	foreach (collectPhpFiles($targetPath) as $filePath) {

		$original = file_get_contents($filePath);

		if (!is_string($original)) {
			$warnings[] = relativePath($targetPath, $filePath) . ': unable to read file';
			continue;
		}

		$result = transformPhpFile($filePath, $original);

		if ($result['changed']) {
			$changedFiles[relativePath($targetPath, $filePath)] = $result['changes'];

			if ($options['write']) {
				file_put_contents($filePath, $result['content']);
			}
		}

		foreach ($result['generatedFiles'] as $generatedFilePath => $generatedFile) {

			$changedFiles[relativePath($targetPath, $generatedFilePath)] = $generatedFile['changes'];

			if ($options['write']) {
				$generatedDirectory = dirname($generatedFilePath);

				if (!is_dir($generatedDirectory)) {
					mkdir($generatedDirectory, 0777, true);
				}

				file_put_contents($generatedFilePath, $generatedFile['content']);
			}

		}

		foreach ($result['warnings'] as $warning) {
			$warnings[] = relativePath($targetPath, $filePath) . ': ' . $warning;
		}

	}

	foreach (collectPackageManifestFiles($targetPath) as $filePath) {

		$original = file_get_contents($filePath);

		if (!is_string($original)) {
			$warnings[] = relativePath($targetPath, $filePath) . ': unable to read file';
			continue;
		}

		$result = transformPackageManifestFile($original);

		if ($result['changed']) {
			$changedFiles[relativePath($targetPath, $filePath)] = $result['changes'];

			if ($options['write']) {
				file_put_contents($filePath, $result['content']);
			}
		}

	}

	foreach (collectNomenclatureTextFiles($targetPath) as $filePath) {

		$original = file_get_contents($filePath);

		if (!is_string($original)) {
			$warnings[] = relativePath($targetPath, $filePath) . ': unable to read file';
			continue;
		}

		$result = transformNomenclatureTextFile($original);

		if ($result['changed']) {
			$changedFiles[relativePath($targetPath, $filePath)] = $result['changes'];

			if ($options['write']) {
				file_put_contents($filePath, $result['content']);
			}
		}

	}

	printReport($targetPath, $options['write'], $changedFiles, $warnings);

	return 0;

}

/**
 * Parse the command-line arguments supported by the upgrader.
 *
 * @param	string[]	$argv	Raw command-line arguments.
 * @return	array{help: bool, path: string, write: bool}
 */
function parseArguments(array $argv): array {

	$options = [
		'help' => false,
		'path' => getcwd() ?: '.',
		'write' => false,
	];

	foreach (array_slice($argv, 1) as $argument) {

		if ($argument === '--help' or $argument === '-h') {
			$options['help'] = true;
			continue;
		}

		if ($argument === '--write') {
			$options['write'] = true;
			continue;
		}

		if ($argument === '--dry-run') {
			$options['write'] = false;
			continue;
		}

		if (str_starts_with($argument, '--path=')) {
			$options['path'] = substr($argument, 7);
		}

	}

	return $options;

}

/**
 * Print the CLI usage information.
 */
function printUsage(): void {

	print "Pair v3 to v4 upgrader\n";
	print "Usage: php scripts/upgrade-to-v4.php [--dry-run] [--write] [--path=/absolute/app/path]\n";
	print "Defaults to dry-run mode against the current working directory.\n";

}

/**
 * Normalize the target path used by the upgrader.
 */
function resolveTargetPath(string $path): string {

	$resolvedPath = realpath($path);

	return $resolvedPath ?: $path;

}

/**
 * Collect every PHP file in the target tree except known generated, test, or external folders.
 *
 * @return	string[]
 */
function collectPhpFiles(string $rootPath): array {

	$files = [];
	$directory = new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS);
	$iterator = new RecursiveIteratorIterator($directory);

	foreach ($iterator as $fileInfo) {

		if (!$fileInfo instanceof SplFileInfo or !$fileInfo->isFile()) {
			continue;
		}

		$filePath = $fileInfo->getPathname();

		if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
			continue;
		}

		if (shouldSkipPath($filePath)) {
			continue;
		}

		$files[] = $filePath;

	}

	sort($files);

	return $files;

}

/**
 * Collect package manifest files that can be moved from plugin to package nodes.
 *
 * @return	string[]
 */
function collectPackageManifestFiles(string $rootPath): array {

	$files = [];
	$directory = new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS);
	$iterator = new RecursiveIteratorIterator($directory);

	foreach ($iterator as $fileInfo) {

		if (!$fileInfo instanceof SplFileInfo or !$fileInfo->isFile()) {
			continue;
		}

		$filePath = $fileInfo->getPathname();

		if (strtolower(basename($filePath)) !== 'manifest.xml') {
			continue;
		}

		if (shouldSkipPath($filePath)) {
			continue;
		}

		$files[] = $filePath;

	}

	sort($files);

	return $files;

}

/**
 * Collect app metadata files that may contain official package nomenclature tokens.
 *
 * @return	string[]
 */
function collectNomenclatureTextFiles(string $rootPath): array {

	$files = [];
	$directory = new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS);
	$iterator = new RecursiveIteratorIterator($directory);

	foreach ($iterator as $fileInfo) {

		if (!$fileInfo instanceof SplFileInfo or !$fileInfo->isFile()) {
			continue;
		}

		$filePath = $fileInfo->getPathname();

		if (shouldSkipPath($filePath)) {
			continue;
		}

		$fileName = strtolower(basename($filePath));
		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		if (!in_array($fileName, ['composer.json', 'composer.lock'], true) and 'ini' !== $extension) {
			continue;
		}

		$files[] = $filePath;

	}

	sort($files);

	return $files;

}

/**
 * Return whether the current path should be ignored by the upgrader.
 */
function shouldSkipPath(string $filePath): bool {

	$skipFragments = [
		DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
		DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
		DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
		DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
	];

	foreach ($skipFragments as $fragment) {
		if (str_contains($filePath, $fragment)) {
			return true;
		}
	}

	return false;

}

/**
 * Transform one PHP file and collect both edits and unresolved warnings.
 *
 * @return	array{changed: bool, changes: string[], content: string, generatedFiles: array<string, array{changes: string[], content: string}>, warnings: string[]}
 */
function transformPhpFile(string $filePath, string $content): array {

	$changes = [];
	$generatedFiles = [];
	$warnings = [];
	$updated = $content;

	$isController = str_ends_with(strtolower($filePath), DIRECTORY_SEPARATOR . 'controller.php');
	$isLegacyView = str_contains($content, 'use Pair\\Core\\View;')
		or str_contains($content, 'extends \\Pair\\Core\\View')
		or preg_match('/extends\s+View\b/', $content) === 1;
	$isApiExposable = str_contains($content, 'ApiExposable');

	[$updated, $nomenclatureChanges, $nomenclatureWarnings] = upgradePluginNomenclature($updated);
	$changes = array_merge($changes, $nomenclatureChanges);
	$warnings = array_merge($warnings, $nomenclatureWarnings);

	if ($isController) {
		[$updated, $controllerChanges, $controllerWarnings] = transformControllerFile($updated);
		$changes = array_merge($changes, $controllerChanges);
		$warnings = array_merge($warnings, $controllerWarnings);
	}

	if ($isApiExposable) {
		[$updated, $injectedReadModel] = injectPayloadReadModel($updated);

		if ($injectedReadModel) {
			$changes[] = 'added explicit readModel => \\Pair\\Data\\Payload::class to apiConfig()';
		}
	}

	[$updated, $wrappedPayloads] = wrapImplicitJsonPayloads($updated);

	if ($wrappedPayloads > 0) {
		$changes[] = 'wrapped common ->toArray() JSON payloads with Pair\\Data\\Payload';
	}

	if ($isLegacyView) {
		$warnings[] = 'legacy View class detected; convert render() logic and layout state wiring into typed page state manually';
		$warnings = array_merge($warnings, buildLegacyViewMigrationWarnings($content));

		[$plannedFiles, $planningWarnings] = planLegacyViewArtifacts($filePath, $content);
		$generatedFiles = array_merge($generatedFiles, $plannedFiles);
		$warnings = array_merge($warnings, $planningWarnings);
	}

	if (preg_match('/->html\s*\(/', $updated) === 1) {
		$warnings[] = 'ActiveRecord::html() detected; move HTML formatting into the view/layout layer';
	}

	if (preg_match('/(^|[^a-zA-Z0-9_])reload\s*\(/', $updated) === 1) {
		$warnings[] = 'reload() detected; replace implicit reload flows with explicit re-query or new read-model mapping';
	}

	return [
		'changed' => $updated !== $content,
		'changes' => array_values(array_unique($changes)),
		'content' => $updated,
		'generatedFiles' => $generatedFiles,
		'warnings' => array_values(array_unique($warnings)),
	];

}

/**
 * Transform one package manifest from the old plugin node to the v4 package node.
 *
 * @return	array{changed: bool, changes: string[], content: string}
 */
function transformPackageManifestFile(string $content): array {

	$changes = [];
	$updated = $content;

	$updated = replaceRegex($updated, '/<plugin(\s|>)/', '<package$1', $changes, 'renamed installable manifest node from plugin to package');
	$updated = replaceLiteral($updated, '</plugin>', '</package>', $changes, 'renamed installable manifest closing node from plugin to package');

	return [
		'changed' => $updated !== $content,
		'changes' => array_values(array_unique($changes)),
		'content' => $updated,
	];

}

/**
 * Transform project metadata tokens that expose old plugin terminology.
 *
 * @return	array{changed: bool, changes: string[], content: string}
 */
function transformNomenclatureTextFile(string $content): array {

	$changes = [];
	$updated = $content;

	$replacements = [
		'PLUGIN_ALREADY_INSTALLED' => ['PACKAGE_ALREADY_INSTALLED', 'renamed package error code token'],
		'PLUGIN_IS_ALREADY_INSTALLED' => ['PACKAGE_IS_ALREADY_INSTALLED', 'renamed package translation key'],
		'FIX_PLUGINS' => ['FIX_PACKAGES', 'renamed package maintenance translation key'],
		'PLUGINS_HAVE_BEEN_FIXED' => ['PACKAGES_HAVE_BEEN_FIXED', 'renamed package maintenance result key'],
		'plugin-system' => ['package-system', 'renamed Composer keyword from plugin-system to package-system'],
		'runtime-plugins' => ['runtime-extensions', 'renamed Composer keyword from runtime-plugins to runtime-extensions'],
		'runtime-plugin' => ['runtime-extension', 'renamed Composer keyword from runtime-plugin to runtime-extension'],
	];

	foreach ($replacements as $search => [$replace, $label]) {
		$updated = replaceLiteral($updated, $search, $replace, $changes, $label);
	}

	return [
		'changed' => $updated !== $content,
		'changes' => array_values(array_unique($changes)),
		'content' => $updated,
	];

}

/**
 * Update old runtime plugin and installable plugin APIs to Pair v4 names.
 *
 * @return	array{0: string, 1: string[], 2: string[]}
 */
function upgradePluginNomenclature(string $content): array {

	$changes = [];
	$warnings = [];
	$updated = $content;

	$runtimeMarkers = [
		'PluginInterface',
		'RuntimePluginInterface',
		'registerPlugin(',
		'registerRuntimePlugin(',
	];
	$packageMarkers = [
		'Pair\\Helpers\\Plugin',
		'Pair\\Helpers\\PluginBase',
		'Pair\\Helpers\\InstallablePlugin',
		'InstallablePlugin',
		'PluginBase',
		'installPackage(',
		'downloadPackage(',
		'createManifestFile(',
		'getManifestByFile(',
		'removeOldFiles(',
		'createPluginByManifest(',
		'getInstallablePlugin(',
		'getInstallablePluginByName(',
		'getPluginByName(',
		'getPlugin(',
		'pluginExists(',
		'storeByPlugin(',
		'PLUGIN_ALREADY_INSTALLED',
		'PLUGIN_IS_ALREADY_INSTALLED',
		'->plugin',
	];

	$usesRuntimePluginApi = contentContainsAny($updated, $runtimeMarkers);
	$usesInstallablePluginApi = contentContainsAny($updated, $packageMarkers);

	if ($usesRuntimePluginApi) {
		[$updated, $runtimeChanges] = upgradeRuntimePluginApi($updated);
		$changes = array_merge($changes, $runtimeChanges);

		if (preg_match('/\bclass\s+[A-Za-z_][A-Za-z0-9_]*Plugin\b/', $updated) === 1) {
			$warnings[] = 'runtime extension class still ends with Plugin; rename the class and file manually when autoloading permits it';
		}
	}

	if ($usesInstallablePluginApi) {
		[$updated, $packageChanges] = upgradeInstallablePluginApi($updated);
		$changes = array_merge($changes, $packageChanges);
	}

	return [$updated, array_values(array_unique($changes)), array_values(array_unique($warnings))];

}

/**
 * Return whether content contains at least one marker.
 *
 * @param	string[]	$markers	Search markers.
 */
function contentContainsAny(string $content, array $markers): bool {

	foreach ($markers as $marker) {
		if (str_contains($content, $marker)) {
			return true;
		}
	}

	return false;

}

/**
 * Rename runtime plugin API references to runtime extension API references.
 *
 * @return	array{0: string, 1: string[]}
 */
function upgradeRuntimePluginApi(string $content): array {

	$changes = [];
	$updated = $content;

	$replacements = [
		'Pair\\Core\\RuntimePluginInterface' => ['Pair\\Core\\RuntimeExtensionInterface', 'renamed runtime plugin interface import to RuntimeExtensionInterface'],
		'Pair\\Core\\PluginInterface' => ['Pair\\Core\\RuntimeExtensionInterface', 'renamed runtime plugin interface import to RuntimeExtensionInterface'],
		'Pair\\RuntimePluginInterface' => ['Pair\\Core\\RuntimeExtensionInterface', 'renamed runtime plugin interface import to RuntimeExtensionInterface'],
		'Pair\\PluginInterface' => ['Pair\\Core\\RuntimeExtensionInterface', 'renamed runtime plugin interface import to RuntimeExtensionInterface'],
		'RuntimePluginInterface' => ['RuntimeExtensionInterface', 'renamed runtime plugin interface type to RuntimeExtensionInterface'],
		'PluginInterface' => ['RuntimeExtensionInterface', 'renamed runtime plugin interface type to RuntimeExtensionInterface'],
		'registerRuntimePlugin(' => ['registerRuntimeExtension(', 'renamed registerRuntimePlugin() calls to registerRuntimeExtension()'],
		'registerPlugin(' => ['registerRuntimeExtension(', 'renamed registerPlugin() calls to registerRuntimeExtension()'],
	];

	foreach ($replacements as $search => [$replace, $label]) {
		$updated = replaceLiteral($updated, $search, $replace, $changes, $label);
	}

	return [$updated, array_values(array_unique($changes))];

}

/**
 * Rename installable plugin API references to installable package API references.
 *
 * @return	array{0: string, 1: string[]}
 */
function upgradeInstallablePluginApi(string $content): array {

	$changes = [];
	$updated = $content;

	$literalReplacements = [
		'Pair\\Helpers\\InstallablePluginBase' => ['Pair\\Packages\\InstallablePackageRecord', 'renamed installable plugin record import to InstallablePackageRecord'],
		'Pair\\Helpers\\InstallablePlugin' => ['Pair\\Packages\\InstallablePackage', 'renamed installable plugin helper import to InstallablePackage'],
		'Pair\\Helpers\\PluginBase' => ['Pair\\Packages\\InstallablePackageRecord', 'renamed PluginBase import to InstallablePackageRecord'],
		'Pair\\Helpers\\Plugin' => ['Pair\\Packages\\InstallablePackage', 'renamed Plugin helper import to InstallablePackage'],
		'PLUGIN_ALREADY_INSTALLED' => ['PACKAGE_ALREADY_INSTALLED', 'renamed package error code token'],
		'PLUGIN_IS_ALREADY_INSTALLED' => ['PACKAGE_IS_ALREADY_INSTALLED', 'renamed package translation key'],
		'installPackage(' => ['installArchive(', 'renamed installPackage() calls to installArchive()'],
		'downloadPackage(' => ['downloadArchive(', 'renamed downloadPackage() calls to downloadArchive()'],
		'createManifestFile(' => ['writeManifestFile(', 'renamed createManifestFile() calls to writeManifestFile()'],
		'getManifestByFile(' => ['readManifestFile(', 'renamed getManifestByFile() calls to readManifestFile()'],
		'removeOldFiles(' => ['removeOldArchives(', 'renamed removeOldFiles() calls to removeOldArchives()'],
		'createPluginByManifest(' => ['createRecordFromManifest(', 'renamed createPluginByManifest() calls to createRecordFromManifest()'],
		'getInstallablePluginByName(' => ['getByName(', 'renamed getInstallablePluginByName() calls to getByName()'],
		'getInstallablePlugin(' => ['getInstallablePackage(', 'renamed getInstallablePlugin() calls to getInstallablePackage()'],
		'getPluginByName(' => ['getByName(', 'renamed getPluginByName() calls to getByName()'],
		'getPlugin(' => ['getInstallablePackage(', 'renamed getPlugin() calls to getInstallablePackage()'],
		'pluginExists(' => ['packageRecordExists(', 'renamed pluginExists() calls to packageRecordExists()'],
		'storeByPlugin(' => ['storeFromPackageManifest(', 'renamed storeByPlugin() calls to storeFromPackageManifest()'],
	];

	foreach ($literalReplacements as $search => [$replace, $label]) {
		$updated = replaceLiteral($updated, $search, $replace, $changes, $label);
	}

	$regexReplacements = [
		'/\bInstallablePluginBase\b/' => ['InstallablePackageRecord', 'renamed InstallablePluginBase type references to InstallablePackageRecord'],
		'/\bInstallablePlugin\b/' => ['InstallablePackage', 'renamed InstallablePlugin type references to InstallablePackage'],
		'/\bPluginBase\b/' => ['InstallablePackageRecord', 'renamed PluginBase type references to InstallablePackageRecord'],
		'/\bnew\s+Plugin\s*\(/' => ['new InstallablePackage(', 'renamed Plugin construction to InstallablePackage'],
		'/\bPlugin::/' => ['InstallablePackage::', 'renamed Plugin static calls to InstallablePackage'],
		'/(?<![A-Za-z0-9_\\\\])Plugin\b(?=\s*(?:[|&,\)=;{\$]|$))/m' => ['InstallablePackage', 'renamed Plugin type references to InstallablePackage'],
	];

	foreach ($regexReplacements as $pattern => [$replacement, $label]) {
		$updated = replaceRegex($updated, $pattern, $replacement, $changes, $label);
	}

	$updated = replaceLiteral($updated, 'getBaseFolder(', 'getPackageBaseFolder(', $changes, 'renamed getBaseFolder() package calls to getPackageBaseFolder()');

	if (str_contains($updated, 'manifest') and str_contains($updated, '->plugin')) {
		$updated = replaceRegex($updated, '/->\s*plugin\b/', '->package', $changes, 'renamed manifest plugin node access to package node access');
	}

	return [$updated, array_values(array_unique($changes))];

}

/**
 * Plan generated migration artifacts for one legacy view file.
 *
 * @return	array{0: array<string, array{changes: string[], content: string}>, 1: string[]}
 */
function planLegacyViewArtifacts(string $filePath, string $content): array {

	$plannedFiles = [];
	$warnings = [];
	$assignments = extractAssignedLayoutVariables($content);

	if (!count($assignments)) {
		return [$plannedFiles, $warnings];
	}

	$className = extractLegacyViewClassName($content);

	if (is_null($className)) {
		$warnings[] = 'unable to derive a page-state class name from the legacy View class';
		return [$plannedFiles, $warnings];
	}

	$stateClassName = derivePageStateClassName($className);
	$stateFilePath = dirname($filePath) . '/classes/' . $stateClassName . '.php';

	if (file_exists($stateFilePath)) {
		$existingContent = file_get_contents($stateFilePath);
		$generatedContent = buildPageStateSkeleton($stateClassName, $className, $assignments);

		if ($existingContent === $generatedContent) {
			return [$plannedFiles, $warnings];
		}

		$warnings[] = 'page-state skeleton already exists at ' . basename(dirname($filePath)) . '/classes/' . $stateClassName . '.php; review it manually before overwriting';
		return [$plannedFiles, $warnings];
	}

	$plannedFiles[$stateFilePath] = [
		'changes' => ['generated Pair v4 page-state skeleton from legacy view assignments'],
		'content' => buildPageStateSkeleton($stateClassName, $className, $assignments),
	];

	return [$plannedFiles, $warnings];

}

/**
 * Extract the legacy assigned variable names in declaration order.
 *
 * @return	string[]
 */
function extractAssignedLayoutVariables(string $content): array {

	$assignments = [];

	if (preg_match_all('/->assign\s*\(\s*[\'"](?P<name>[A-Za-z_][A-Za-z0-9_]*)[\'"]\s*,/m', $content, $matches) !== false) {
		foreach ($matches['name'] as $name) {
			if (!in_array($name, $assignments, true)) {
				$assignments[] = $name;
			}
		}
	}

	return $assignments;

}

/**
 * Extract the first legacy View class name from a file.
 */
function extractLegacyViewClassName(string $content): ?string {

	if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)\s+extends\s+View\b/', $content, $matches) === 1) {
		return $matches[1];
	}

	return null;

}

/**
 * Derive a Pair v4 page-state class name from a legacy View class name.
 */
function derivePageStateClassName(string $viewClassName): string {

	if (!str_contains($viewClassName, 'View')) {
		return $viewClassName . 'PageState';
	}

	return str_replace('View', '', $viewClassName) . 'PageState';

}

/**
 * Build the contents of a generated Pair v4 page-state skeleton.
 *
 * @param	string[]	$assignments	Layout variable names assigned by the legacy view.
 */
function buildPageStateSkeleton(string $stateClassName, string $viewClassName, array $assignments): string {

	$constructorProperties = [];
	$arrayEntries = [];

	foreach ($assignments as $assignment) {
		$constructorProperties[] = "\t\tpublic mixed $" . $assignment;
		$arrayEntries[] = "\t\t\t'" . $assignment . "' => \$this->" . $assignment . ',';
	}

	$constructorSignature = implode(",\n", $constructorProperties);
	$arrayBody = implode("\n", $arrayEntries);

	return <<<PHP
<?php

declare(strict_types=1);

use Pair\Data\ArraySerializableData;
use Pair\Data\ReadModel;

/**
 * Generated by Pair upgrade-to-v4 from {$viewClassName}.
 *
 * This skeleton covers only the values assigned through View::assign().
 * Move page title, breadcrumbs, form preparation, and any HTML-specific logic
 * from the legacy view into an explicit Pair v4 controller action manually.
 */
final readonly class {$stateClassName} implements ReadModel {

\tuse ArraySerializableData;

\t/**
\t * Build the page state.
\t *
\t * Replace mixed properties with concrete application types during the migration.
\t */
\tpublic function __construct(
{$constructorSignature}
\t) {}

\t/**
\t * Export the page state as an array for debugging and migration tooling.
\t *
\t * @return\tarray<string, mixed>
\t */
\tpublic function toArray(): array {

\t\treturn [
{$arrayBody}
\t\t];

\t}

}
PHP;

}

/**
 * Transform a controller file only when it already follows the explicit v4 response path.
 *
 * @return	array{0: string, 1: string[], 2: string[]}
 */
function transformControllerFile(string $content): array {

	$changes = [];
	$warnings = [];
	$updated = $content;
	$usesLegacyController = str_contains($content, 'use Pair\Core\Controller;')
		or str_contains($content, 'extends \Pair\Core\Controller');
	$usesWebController = str_contains($content, 'use Pair\Web\Controller;')
		or str_contains($content, 'extends \Pair\Web\Controller');
	$legacyMarkers = detectLegacyControllerMarkers($content);
	$usesExplicitResponse = controllerUsesExplicitResponseContracts($content);

	// Skip the controller-base switch when the file still depends on implicit MVC state.
	if ($usesLegacyController and (!$usesExplicitResponse or count($legacyMarkers))) {
		$warnings[] = buildLegacyControllerWarning($legacyMarkers, $usesExplicitResponse);
		return [$updated, $changes, $warnings];
	}

	if ($usesLegacyController) {
		$updated = replaceLiteral($updated, 'use Pair\Core\Controller;', 'use Pair\Web\Controller;', $changes, 'switched controller base import to Pair\\Web\\Controller');
		$updated = replaceLiteral($updated, 'extends \Pair\Core\Controller', 'extends \Pair\Web\Controller', $changes, 'switched controller inheritance to Pair\\Web\\Controller');
		$usesWebController = str_contains($updated, 'use Pair\Web\Controller;')
			or str_contains($updated, 'extends \Pair\Web\Controller');
	}

	if ($usesWebController) {
		[$updated, $translationChanges] = upgradeLegacyControllerTranslations($updated);
		$changes = array_merge($changes, $translationChanges);

		$updated = replaceRegex(
			$updated,
			'/\b(protected|public|private)\s+function\s+_init\s*\(/',
			'$1 function boot(',
			$changes,
			'renamed legacy _init() hook to boot()'
		);
	}

	return [$updated, $changes, $warnings];

}

/**
 * Detect controller APIs that still couple the module to the legacy MVC lifecycle.
 *
 * @return	string[]
 */
function detectLegacyControllerMarkers(string $content): array {

	$markers = [];
	$checks = [
		'setView()' => '/->setView\s*\(/',
		'$this->model' => '/\$this->model\b/',
		'$this->view' => '/\$this->view\b/',
		'loadModel()' => '/->loadModel\s*\(/',
		'loadModelForActions()' => '/->loadModelForActions\s*\(/',
		'raiseError()' => '/->raiseError\s*\(/',
		'getObjectRequestedById()' => '/->getObjectRequestedById\s*\(/',
		'renderView()' => '/->renderView\s*\(/',
	];

	foreach ($checks as $label => $pattern) {
		if (preg_match($pattern, $content) === 1) {
			$markers[] = $label;
		}
	}

	return $markers;

}

/**
 * Detect whether the controller already uses explicit v4 response contracts.
 */
function controllerUsesExplicitResponseContracts(string $content): bool {

	$patterns = [
		'/:\s*(?:\\\\?Pair\\\\Web\\\\PageResponse|\\\\?Pair\\\\Http\\\\JsonResponse|\\\\?Pair\\\\Http\\\\ResponseInterface|PageResponse|JsonResponse|ResponseInterface)\b/',
		'/return\s+new\s+(?:\\\\?Pair\\\\Web\\\\PageResponse|\\\\?Pair\\\\Http\\\\JsonResponse|PageResponse|JsonResponse)\b/',
		'/->page\s*\(/',
		'/->json\s*\(/',
	];

	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $content) === 1) {
			return true;
		}
	}

	return false;

}

/**
 * Build one actionable warning for controllers that still need a manual migration.
 *
 * @param	string[]	$legacyMarkers	Legacy APIs still referenced by the controller.
 */
function buildLegacyControllerWarning(array $legacyMarkers, bool $usesExplicitResponse): string {

	$message = 'controller still depends on the legacy MVC flow; keep Pair\\Core\\Controller until the module is manually migrated';

	if (!count($legacyMarkers)) {
		return $message . ' and starts returning an explicit Pair\\Http\\ResponseInterface';
	}

	$message .= ' (legacy APIs: ' . implode(', ', $legacyMarkers) . ')';

	if (!$usesExplicitResponse) {
		$message .= ' and add explicit PageResponse or JsonResponse returns before switching controller base';
	}

	return $message;

}

/**
 * Replace legacy controller translation calls with an explicit Translator helper.
 *
 * @return	array{0: string, 1: string[]}
 */
function upgradeLegacyControllerTranslations(string $content): array {

	$changes = [];

	if (preg_match('/->lang\s*\(/', $content) !== 1) {
		return [$content, $changes];
	}

	$updated = preg_replace('/->lang\s*\(/', '->translate(', $content, -1, $replacedCalls);

	if (!is_string($updated) or $replacedCalls === 0) {
		return [$content, $changes];
	}

	$changes[] = 'replaced legacy lang() controller calls with translate()';
	[$updated, $translatorImported] = ensureUseImport($updated, 'Pair\\Helpers\\Translator');

	if ($translatorImported) {
		$changes[] = 'added Pair\\Helpers\\Translator import for explicit controller translations';
	}

	[$updated, $translateMethodAdded] = ensureControllerTranslateMethod($updated);

	if ($translateMethodAdded) {
		$changes[] = 'added explicit translate() helper to the controller';
	}

	return [$updated, $changes];

}

/**
 * Ensure that one PHP import exists in the file.
 *
 * @return	array{0: string, 1: bool}
 */
function ensureUseImport(string $content, string $import): array {

	$useLine = 'use ' . $import . ';';

	if (str_contains($content, $useLine)) {
		return [$content, false];
	}

	if (preg_match_all('/^use\s+[^\n]+;\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE) !== false and count($matches[0])) {
		$lastMatch = $matches[0][count($matches[0]) - 1];
		$insertPosition = $lastMatch[1] + strlen($lastMatch[0]);
		return [
			substr($content, 0, $insertPosition) . PHP_EOL . $useLine . substr($content, $insertPosition),
			true,
		];
	}

	if (preg_match('/^namespace\s+[^\n]+;\s*$/m', $content, $namespaceMatch, PREG_OFFSET_CAPTURE) === 1) {
		$insertPosition = $namespaceMatch[0][1] + strlen($namespaceMatch[0][0]);
		return [
			substr($content, 0, $insertPosition) . PHP_EOL . PHP_EOL . $useLine . substr($content, $insertPosition),
			true,
		];
	}

	$updated = preg_replace(
		'/^(<\?php\s*(?:\Rdeclare\(strict_types=1\);\s*)?)/',
		'$1' . PHP_EOL . $useLine . PHP_EOL,
		$content,
		1,
		$inserted
	);

	if (!is_string($updated) or $inserted === 0) {
		return [$content, false];
	}

	return [$updated, true];

}

/**
 * Ensure that the migrated controller owns an explicit translate() helper.
 *
 * @return	array{0: string, 1: bool}
 */
function ensureControllerTranslateMethod(string $content): array {

	if (preg_match('/function\s+translate\s*\(/', $content) === 1) {
		return [$content, false];
	}

	$method = <<<'PHP'

	/**
	 * Translate a language key inside the controller.
	 */
	private function translate(string $key, string|array|null $vars = null): string {

		return Translator::do($key, $vars);

	}
PHP;

	$updated = preg_replace('/\n}\s*$/', $method . "\n\n}\n", $content, 1, $inserted);

	if (!is_string($updated) or $inserted === 0) {
		return [$content, false];
	}

	return [$updated, true];

}

/**
 * Build explicit warnings for view responsibilities that must move into the controller.
 *
 * @return	string[]
 */
function buildLegacyViewMigrationWarnings(string $content): array {

	$warnings = [];
	$responsibilities = detectLegacyViewResponsibilities($content);

	if (count($responsibilities)) {
		$warnings[] = 'legacy View still owns controller-side responsibilities (' . implode(', ', $responsibilities) . ')';
	}

	if (preg_match('/\$this->model\b/', $content) === 1) {
		$warnings[] = 'legacy View still loads data from $this->model; move record loading and mapping into the controller';
	}

	if (legacyViewUsesAssign($content)) {
		$warnings[] = 'legacy view assignments detected; convert assigned values into a typed page state object';
	}

	if (legacyViewUsesAssignState($content)) {
		$warnings[] = 'legacy View already uses assignState(); move typed state construction into the controller and return PageResponse directly';
	}

	return $warnings;

}

/**
 * Detect whether the legacy view still assigns loose layout variables.
 */
function legacyViewUsesAssign(string $content): bool {

	return preg_match('/->assign\s*\(/', $content) === 1;

}

/**
 * Detect whether the legacy view already assigns one explicit state object.
 */
function legacyViewUsesAssignState(string $content): bool {

	return preg_match('/->assignState\s*\(/', $content) === 1;

}

/**
 * Detect controller-side concerns that still live inside one legacy view.
 *
 * @return	string[]
 */
function detectLegacyViewResponsibilities(string $content): array {

	$responsibilities = [];
	$checks = [
		'pageTitle()' => '/->pageTitle\s*\(/',
		'Breadcrumb::path()' => '/Breadcrumb::path\s*\(/',
		'$this->app->activeMenuItem' => '/->activeMenuItem\s*=/',
	];

	foreach ($checks as $label => $pattern) {
		if (preg_match($pattern, $content) === 1) {
			$responsibilities[] = $label;
		}
	}

	return $responsibilities;

}

/**
 * Replace a literal string and register the change label when the content changes.
 *
 * @param	string[]	$changes	Collected change labels.
 */
function replaceLiteral(string $content, string $search, string $replace, array &$changes, string $label): string {

	$updated = str_replace($search, $replace, $content, $count);

	if ($count > 0) {
		$changes[] = $label;
	}

	return $updated;

}

/**
 * Replace a regex pattern and register the change label when the content changes.
 *
 * @param	string[]	$changes	Collected change labels.
 */
function replaceRegex(string $content, string $pattern, string $replacement, array &$changes, string $label): string {

	$updated = preg_replace($pattern, $replacement, $content, -1, $count);

	if (!is_string($updated)) {
		return $content;
	}

	if ($count > 0) {
		$changes[] = $label;
	}

	return $updated;

}

/**
 * Inject an explicit migration read model into ApiExposable::apiConfig() when missing.
 *
 * @return	array{0: string, 1: bool}
 */
function injectPayloadReadModel(string $content): array {

	if (!preg_match('/function\s+apiConfig\s*\(\)\s*:\s*array\s*\{(?P<body>.*?)\n\t\}/s', $content, $matches)) {
		return [$content, false];
	}

	$functionBody = $matches['body'];

	if (preg_match('/[\'"](readModel|resource)[\'"]\s*=>/', $functionBody) === 1) {
		return [$content, false];
	}

	$updatedBody = preg_replace(
		'/return\s*\[/',
		"return [\n\t\t\t'readModel' => \\Pair\\Data\\Payload::class,",
		$functionBody,
		1,
		$count
	);

	if (!is_string($updatedBody) or $count === 0) {
		return [$content, false];
	}

	return [str_replace($functionBody, $updatedBody, $content), true];

}

/**
 * Wrap common JSON payloads that still rely on implicit ->toArray() output.
 *
 * @return	array{0: string, 1: int}
 */
function wrapImplicitJsonPayloads(string $content): array {

	// Keep the upgrader tolerant of the repo's readable multiline call style.
	$pattern = '#(ApiResponse::respond|Utilities::jsonResponse)\(\s*([\s\S]*?->toArray\(\))(\s*(?:,\s*[\s\S]*?)?)\s*\);#';

	$updated = preg_replace_callback(
		$pattern,
		function(array $matches): string {

			if (str_contains($matches[2], 'Payload::fromArray(')) {
				return $matches[0];
			}

			return $matches[1] . '(\Pair\Data\Payload::fromArray(' . $matches[2] . ')->toArray()' . $matches[3] . ');';

		},
		$content,
		-1,
		$count
	);

	if (!is_string($updated)) {
		return [$content, 0];
	}

	return [$updated, $count];

}

/**
 * Convert an absolute path into a report-friendly relative path.
 */
function relativePath(string $rootPath, string $filePath): string {

	$prefix = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

	if (str_starts_with($filePath, $prefix)) {
		return substr($filePath, strlen($prefix));
	}

	return $filePath;

}

/**
 * Print the final report for the current upgrader execution.
 *
 * @param	array<string, string[]>	$changedFiles	List of changed files and applied edits.
 * @param	string[]				$warnings		Collected warnings.
 */
function printReport(string $targetPath, bool $writeMode, array $changedFiles, array $warnings): void {

	print "Pair v3 to v4 upgrader\n";
	print "======================\n";
	print 'Target: ' . $targetPath . PHP_EOL;
	print 'Mode: ' . ($writeMode ? 'write' : 'dry-run') . PHP_EOL . PHP_EOL;

	if (!count($changedFiles)) {
		print "Changed files: none\n";
	} else {
		print 'Changed files: ' . count($changedFiles) . PHP_EOL;

		foreach ($changedFiles as $filePath => $changes) {
			print '- ' . $filePath . PHP_EOL;

			foreach ($changes as $change) {
				print '  * ' . $change . PHP_EOL;
			}
		}
	}

	print PHP_EOL;

	if (!count($warnings)) {
		print "Warnings: none\n";
		return;
	}

	print 'Warnings: ' . count($warnings) . PHP_EOL;

	foreach ($warnings as $warning) {
		print '- ' . $warning . PHP_EOL;
	}

}
