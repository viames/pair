<?php

declare(strict_types=1);

/**
 * This script updates all the files in the application directory and its subdirectories
 * replacing the necessary code to make the application compatible with Pair v3.
 */

$options = parseArguments($argv);

if ($options['help']) {
	printUsage();
	exit(0);
}

define('APP_ROOT', resolveTargetPath($options['path']));
$writeMode = $options['write'];
$writeErrors = [];

print "Pair v2 to v3 migration script\n";
print "===============================\n";
print 'Mode: ' . ($writeMode ? 'write' : 'dry-run') . "\n";

// check if the script is running from the Pair root directory
if (!file_exists(APP_ROOT . '/vendor/autoload.php')) {
	fwrite(STDERR, "Please run this script from the Pair application root directory.\n");
	exit(1);
}

// print the absolute path of the Pair root directory
print "Pair root directory: " . APP_ROOT . "\n";

$envPath = APP_ROOT . '/.env';

// update .env keys if present
if (file_exists($envPath)) {
	$envContent = file_get_contents($envPath);

	if (!is_string($envContent)) {
		$writeErrors[] = '.env: unable to read file';
	} else {
		$originalEnvContent = $envContent;

		$envContent = preg_replace('/^\s*PRODUCT_NAME\s*=\s*/m', 'APP_NAME=', $envContent);
		$envContent = preg_replace('/^\s*PRODUCT_VERSION\s*=\s*/m', 'APP_VERSION=', $envContent);
		$envContent = preg_replace('/^\s*PAIR_ENVIRONMENT\s*=\s*/m', 'APP_ENV=', $envContent);
		$envContent = preg_replace('/^\s*PAIR_DEBUG\s*=\s*/m', 'APP_DEBUG=', $envContent);

		if ($envContent !== $originalEnvContent) {
			if ($writeMode and !writeUpgradeFile($envPath, $envContent)) {
				$writeErrors[] = '.env: unable to write file';
			} else {
				print $writeMode ? ".env file updated.\n" : ".env file would be updated.\n";
			}
		}
	}
} else {
	print ".env file not found.\n";
}

$directory = new RecursiveDirectoryIterator(APP_ROOT);
$iterator = new RecursiveIteratorIterator($directory);

// search only in PHP files
$files = new RegexIterator($iterator, '/\.php$/');

// exclude the files and folders that should not be modified
$excludeList = [
	DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
	DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
	DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
	DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
];

$updatedFiles = 0;
$filesWithConfig = [];
$filesWithInit = [];
$filesWithInitCalls = [];
$filesWithOldEnvKeys = [];
$filesWithDatabaseConstants = [];
$filesWithPairJson = [];
$filesWithOauth2 = [];
$filesWithChartJs = [];
$filesWithTelegramLegacyClass = [];
$filesWithTemplateParse = [];
$filesWithTemplatePhp = [];
$filesWithPrintStyles = [];
$filesWithPrintScripts = [];

// loop through all the files
foreach ($files as $file) {

	foreach ($excludeList as $exclude) {
		if (false !== strpos($file->getPathname(), $exclude)) {
			continue 2;
		}
	}

	// get the content of the file
	$content = file_get_contents($file->getPathname());

	if (!is_string($content)) {
		$writeErrors[] = relativePath(APP_ROOT, $file->getPathname()) . ': unable to read file';
		continue;
	}

	$originalContent = $content;
	$isPairLifecycleClass = usesPairLifecycleClass($content);

	// Rename only Pair lifecycle hooks, leaving unrelated application services untouched.
	if ($isPairLifecycleClass) {
		$content = preg_replace('/\b(protected|public|private)\s+function\s+init\s*\(/', '$1 function _init(', $content);
		$content = preg_replace('/\b(parent|self|static)\s*::\s*init\s*\(/', '$1::_init(', $content);
		$content = preg_replace('/\$this\s*->\s*init\s*\(/', '$this->_init(', $content);
	}

	// Pair v2 installable records used PluginInterface; Pair v3 introduced PluginBase.
	$content = str_replace('use Pair\\Helpers\\PluginInterface;', 'use Pair\\Helpers\\PluginBase;', $content);
	$content = preg_replace(
		'/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\s+extends\s+(?:\\\\?Pair\\\\Orm\\\\)?ActiveRecord\s+implements\s+(?:\\\\?Pair\\\\Helpers\\\\)?PluginInterface\b/',
		'class $1 extends PluginBase',
		$content
	);

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
	$content = str_replace('PAIR_DEBUG', 'APP_DEBUG', $content);

	// Page title: v2 script introduced setPageTitle(), Pair3 uses pageTitle()
	$content = str_replace('setPageTitle(', 'pageTitle(', $content);
	$content = str_replace('setGuestModule(', 'guestModule(', $content);

	// OAuth2 classes
	$content = str_replace('Pair\\Models\\Oauth2Client', 'Pair\\Models\\OAuth2Client', $content);
	$content = str_replace('Pair\\Models\\Oauth2Token', 'Pair\\Models\\OAuth2Token', $content);
	$content = str_replace('Pair\\Oauth\\Oauth2Client', 'Pair\\Models\\OAuth2Client', $content);
	$content = str_replace('Pair\\Oauth\\Oauth2Token', 'Pair\\Models\\OAuth2Token', $content);
	$content = preg_replace('/\bOauth2Client\b/', 'OAuth2Client', $content);
	$content = preg_replace('/\bOauth2Token\b/', 'OAuth2Token', $content);

	// ChartJs moved from Services to Helpers
	$content = str_replace('Pair\\Services\\ChartJsDataset', 'Pair\\Helpers\\ChartJsDataset', $content);
	$content = str_replace('Pair\\Services\\ChartJs', 'Pair\\Helpers\\ChartJs', $content);

	// Telegram legacy classes now use TelegramBotClient
	$content = str_replace('Pair\\Services\\TelegramNotifier', 'Pair\\Services\\TelegramBotClient', $content);
	$content = str_replace('Pair\\Services\\TelegramSender', 'Pair\\Services\\TelegramBotClient', $content);
	$content = preg_replace('/\bTelegramNotifier\b/', 'TelegramBotClient', $content);
	$content = preg_replace('/\bTelegramSender\b/', 'TelegramBotClient', $content);

	// Template parser moved to TemplateRenderer
	$content = str_replace('Pair\\Models\\Template::parse(', '\\Pair\\Html\\TemplateRenderer::parse(', $content);
	$content = preg_replace('/\bTemplate::parse\s*\(/', '\\Pair\\Html\\TemplateRenderer::parse(', $content);

	// Database constants mapped by v2 script (PAIR_DB_* -> Database::*) are not in Pair3
	$content = preg_replace('/\bDatabase::HOST\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_HOST\')', $content);
	$content = preg_replace('/\bDatabase::USER\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_USER\')', $content);
	$content = preg_replace('/\bDatabase::PASS\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_PASS\')', $content);
	$content = preg_replace('/\bDatabase::NAME\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_NAME\')', $content);
	$content = preg_replace('/\bDatabase::UTF8\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_UTF8\')', $content);
	$content = preg_replace('/\bDatabase::PORT\b/', '\\\\Pair\\\\Core\\\\Env::get(\'DB_PORT\')', $content);
	$content = preg_replace('/\bPAIR_DB_([A-Z_]+)\b/', 'Database::$1', $content);

	// Avoid deprecated static call on instance (v2 script used $app->getEnvironment()).
	$content = str_replace('$app->getEnvironment()', '\\Pair\\Core\\Application::getEnvironment()', $content);
	$content = str_replace('$this->app->getEnvironment()', '\\Pair\\Core\\Application::getEnvironment()', $content);

	// Templates: Pair3 uses {{styles}}/{{scripts}} placeholders
	if (0 === strpos($file->getPathname(), APP_ROOT . '/templates/')) {
		$templateReplacements = [
			'/<\?php\s*(?:print|echo)?\s*\$app->printStyles\(\)\s*;?\s*\?>/i' => '{{styles}}',
			'/<\?php\s*(?:print|echo)?\s*\$this->app->printStyles\(\)\s*;?\s*\?>/i' => '{{styles}}',
			'/<\?php\s*(?:print|echo)?\s*\$app->printScripts\(\)\s*;?\s*\?>/i' => '{{scripts}}',
			'/<\?php\s*(?:print|echo)?\s*\$this->app->printScripts\(\)\s*;?\s*\?>/i' => '{{scripts}}',
			'/<\?php\s*(?:print|echo)?\s*\$this->pageStyles\s*;?\s*\?>/i' => '{{styles}}',
			'/<\?php\s*(?:print|echo)?\s*\$this->pageScripts\s*;?\s*\?>/i' => '{{scripts}}',
			'/<\?=\s*\$this->pageStyles\s*\?>/i' => '{{styles}}',
			'/<\?=\s*\$this->pageScripts\s*\?>/i' => '{{scripts}}',
			'/<\?=\s*\$app->printStyles\(\)\s*\?>/i' => '{{styles}}',
			'/<\?=\s*\$app->printScripts\(\)\s*\?>/i' => '{{scripts}}',
			'/<\?=\s*\$this->app->printStyles\(\)\s*\?>/i' => '{{styles}}',
			'/<\?=\s*\$this->app->printScripts\(\)\s*\?>/i' => '{{scripts}}'
		];
		foreach ($templateReplacements as $pattern => $replacement) {
			$content = preg_replace($pattern, $replacement, $content);
		}

		$content = str_replace('$app->printStyles()', '{{styles}}', $content);
		$content = str_replace('$this->app->printStyles()', '{{styles}}', $content);
		$content = str_replace('$app->printScripts()', '{{scripts}}', $content);
		$content = str_replace('$this->app->printScripts()', '{{scripts}}', $content);
		$content = str_replace('<?php print $this->pageStyles ?>', '{{styles}}', $content);
		$content = str_replace('<?php print $this->pageScripts ?>', '{{scripts}}', $content);
		$content = str_replace('<?php print $this->pageStyles; ?>', '{{styles}}', $content);
		$content = str_replace('<?php print $this->pageScripts; ?>', '{{scripts}}', $content);

		if (preg_match('/<\?(php|=)/i', $content)) {
			$filesWithTemplatePhp[] = $file->getPathname();
		}
	}

	// update the file only if the content has changed
	if ($content !== $originalContent) {
		$updatedFiles++;

		if ($writeMode and !writeUpgradeFile($file->getPathname(), $content)) {
			$writeErrors[] = relativePath(APP_ROOT, $file->getPathname()) . ': unable to write file';
		}
	}

	// collect warnings for manual review
	if (preg_match('/\bConfig::/', $content)) {
		$filesWithConfig[] = $file->getPathname();
	}
	if (preg_match('/\bfunction\s+init\s*\(/', $content)) {
		$filesWithInit[] = $file->getPathname();
	}
	if (preg_match('/\b(parent|self|static)\s*::\s*init\s*\(|\$this\s*->\s*init\s*\(/', $content)) {
		$filesWithInitCalls[] = $file->getPathname();
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
	if (preg_match('/\bOauth2(Client|Token)\b/', $content)) {
		$filesWithOauth2[] = $file->getPathname();
	}
	if (preg_match('/\bPair\\\\Services\\\\ChartJs(Dataset)?\b/', $content)) {
		$filesWithChartJs[] = $file->getPathname();
	}
	if (preg_match('/\b(TelegramNotifier|TelegramSender)\b/', $content)) {
		$filesWithTelegramLegacyClass[] = $file->getPathname();
	}
	if (preg_match('/\bTemplate::parse\s*\(/', $content)) {
		$filesWithTemplateParse[] = $file->getPathname();
	}
	if (preg_match('/\bprintStyles\s*\(/', $content)) {
		$filesWithPrintStyles[] = $file->getPathname();
	}
	if (preg_match('/\bprintScripts\s*\(/', $content)) {
		$filesWithPrintScripts[] = $file->getPathname();
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
if (count($filesWithInitCalls)) {
	print "Warning: remaining init() calls found in:\n";
	foreach ($filesWithInitCalls as $path) {
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
if (count($filesWithOauth2)) {
	print "Warning: Oauth2* class names still used in:\n";
	foreach ($filesWithOauth2 as $path) {
		print "- $path\n";
	}
}
if (count($filesWithChartJs)) {
	print "Warning: ChartJs classes still referenced in Pair\\Services in:\n";
	foreach ($filesWithChartJs as $path) {
		print "- $path\n";
	}
}
if (count($filesWithTelegramLegacyClass)) {
	print "Warning: legacy Telegram classes still referenced in:\n";
	foreach ($filesWithTelegramLegacyClass as $path) {
		print "- $path\n";
	}
}
if (count($filesWithTemplateParse)) {
	print "Warning: Template::parse still referenced in:\n";
	foreach ($filesWithTemplateParse as $path) {
		print "- $path\n";
	}
}
if (count($filesWithPrintStyles)) {
	print "Warning: printStyles() still referenced outside templates in:\n";
	foreach ($filesWithPrintStyles as $path) {
		print "- $path\n";
	}
}
if (count($filesWithPrintScripts)) {
	print "Warning: printScripts() still referenced outside templates in:\n";
	foreach ($filesWithPrintScripts as $path) {
		print "- $path\n";
	}
}
if (count($filesWithTemplatePhp)) {
	print "Warning: PHP tags found in templates (Pair v3 templates are static):\n";
	foreach ($filesWithTemplatePhp as $path) {
		print "- $path\n";
	}
}
if (count($writeErrors)) {
	print "Write errors: " . count($writeErrors) . "\n";
	foreach ($writeErrors as $error) {
		print "- $error\n";
	}
}
print "===============================\n";
print count($writeErrors) ? "Upgrade failed.\n" : "Upgrade completed.\n";

exit(count($writeErrors) ? 1 : 0);

/**
 * Parse command-line options while preserving write mode as the legacy default.
 *
 * @param string[] $argv Raw command-line arguments.
 * @return array{help: bool, path: string, write: bool}
 */
function parseArguments(array $argv): array {

	$options = [
		'help' => false,
		'path' => defaultTargetPath(),
		'write' => true,
	];

	foreach (array_slice($argv, 1) as $argument) {
		if ('--help' === $argument or '-h' === $argument) {
			$options['help'] = true;
		} else if ('--dry-run' === $argument) {
			$options['write'] = false;
		} else if ('--write' === $argument) {
			$options['write'] = true;
		} else if (str_starts_with($argument, '--path=')) {
			$options['path'] = substr($argument, 7);
		}
	}

	return $options;

}

/**
 * Resolve the historical Composer application root when available.
 */
function defaultTargetPath(): string {

	$legacyApplicationRoot = dirname(__DIR__, 4);

	if (file_exists($legacyApplicationRoot . '/vendor/autoload.php')) {
		return $legacyApplicationRoot;
	}

	return getcwd() ?: '.';

}

/**
 * Print usage details for the Pair v2 upgrader.
 */
function printUsage(): void {

	print "Usage: php scripts/upgrade-to-v3.php [--dry-run] [--write] [--path=/absolute/app/path]\n";
	print "Defaults to write mode for backward compatibility.\n";

}

/**
 * Resolve the requested application root.
 */
function resolveTargetPath(string $path): string {

	return realpath($path) ?: $path;

}

/**
 * Return whether the file contains a Pair lifecycle subclass.
 */
function usesPairLifecycleClass(string $content): bool {

	$baseNames = ['ActiveRecord', 'Controller', 'Model', 'View'];

	foreach ($baseNames as $baseName) {
		if (preg_match('/\bextends\s+(?:\\\\?Pair\\\\(?:Core\\\\|Orm\\\\)?' . $baseName . '|' . $baseName . ')\b/', $content) === 1) {
			return true;
		}
	}

	return false;

}

/**
 * Return a report-friendly relative path.
 */
function relativePath(string $rootPath, string $filePath): string {

	$prefix = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

	return str_starts_with($filePath, $prefix) ? substr($filePath, strlen($prefix)) : $filePath;

}

/**
 * Write one upgrade result and verify that the complete content was persisted.
 */
function writeUpgradeFile(string $filePath, string $content): bool {

	$bytes = @file_put_contents($filePath, $content);

	return is_int($bytes) and $bytes === strlen($content);

}
