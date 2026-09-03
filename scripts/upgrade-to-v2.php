<?php

declare(strict_types=1);

/**
 * This script updates all the files in the current directory and its subdirectories
 * replacing the old Pair classes with the new ones.
 */

// Parse options before resolving the application root.
$options = parseArguments($argv);

if ($options['help']) {
	printUsage();
	exit(0);
}

define('APP_ROOT', resolveTargetPath($options['path']));
$writeMode = $options['write'];
$warnings = [];
$writeErrors = [];

print "Pair v1 to v2 migration script\n";
print "===============================\n";
print 'Mode: ' . ($writeMode ? 'write' : 'dry-run') . "\n";

// check if the script is running from the Pair root directory
if (!file_exists(APP_ROOT . '/vendor/autoload.php')) {
	fwrite(STDERR, "Please run this script from the Pair application root directory.\n");
	exit(1);
}

// print the absolute path of the Pair root directory
print "Pair root directory: " . APP_ROOT . "\n";

$configPath = APP_ROOT . '/config.php';
$envPath = APP_ROOT . '/.env';

// check if the .env file already exists
if (file_exists($envPath)) {
	
	print "The .env file already exists.\n";
	
// check if the config.php file exists
} else if (file_exists($configPath)) {

	// get the content of the config.php file
	$configContent = file_get_contents($configPath);

	if (!is_string($configContent)) {
		$configResult = ['content' => '', 'warnings' => []];
		$writeErrors[] = 'config.php: unable to read legacy configuration';
	} else {
		// Parse the config.php content and convert scalar definitions to .env format.
		$configResult = convertConfigToEnv($configContent);
	}

	if (count($configResult['warnings'])) {
		foreach ($configResult['warnings'] as $warning) {
			$warnings[] = 'config.php: ' . $warning;
		}
		$warnings[] = 'config.php was retained and .env was not generated; convert the reported values manually';
		$writeErrors[] = 'config.php: automatic conversion is incomplete';
	} else if ($writeMode) {
		if (!writeUpgradeFile($envPath, $configResult['content'])) {
			$writeErrors[] = '.env: unable to write converted configuration';
		} else {
			print "The config.php file has been converted to .env format; config.php was retained as a safety copy.\n";
		}
	} else {
		print "The config.php file can be converted to .env format; config.php will be retained as a safety copy.\n";
	}

} else {

	print "The config.php file does not exist.\n";

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
$changedFiles = [];

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

	// replace the old classes with the new ones
	$content = str_replace('Pair\Application', 'Pair\Core\Application', $content);
	$content = str_replace('Pair\Controller', 'Pair\Core\Controller', $content);
	$content = str_replace('Pair\Logger', 'Pair\Core\Logger', $content);
	$content = preg_replace('/\bPair\\\\Model\b/', 'Pair\\\\Core\\\\Model', $content);
	$content = str_replace('Pair\Router', 'Pair\Core\Router', $content);
	$content = str_replace('Pair\View', 'Pair\Core\View', $content);
	$content = str_replace('Pair\PluginInterface', 'Pair\Helpers\PluginInterface', $content);
	$content = preg_replace('/\bPair\\\\Plugin\b/', 'Pair\\\\Helpers\\\\Plugin', $content);
	$content = str_replace('Pair\Input', 'Pair\Helpers\Post', $content);
	$content = str_replace('Pair\Options', 'Pair\Helpers\Options', $content);
	$content = str_replace('Pair\Schedule', 'Pair\Helpers\Schedule', $content);
	$content = str_replace('Pair\Translator', 'Pair\Helpers\Translator', $content);
	$content = str_replace('Pair\Upload', 'Pair\Helpers\Upload', $content);
	$content = str_replace('Pair\Utilities', 'Pair\Helpers\Utilities', $content);
	$content = str_replace('Pair\BootstrapMenu', 'Pair\Html\BootstrapMenu', $content);
	$content = str_replace('Pair\Breadcrumb', 'Pair\Html\Breadcrumb', $content);
	$content = str_replace('Pair\Form', 'Pair\Html\Form', $content);
	$content = str_replace('Pair\Menu', 'Pair\Html\Menu', $content);
	$content = str_replace('Pair\Pagination', 'Pair\Html\Pagination', $content);
	$content = str_replace('Pair\Acl', 'Pair\Models\Acl', $content);
	$content = str_replace('Pair\Audit', 'Pair\Models\Audit', $content);
	$content = str_replace('Pair\Country', 'Pair\Models\Country', $content);
	$content = str_replace('Pair\ErrorLog', 'Pair\Models\ErrorLog', $content);
	$content = str_replace('Pair\Group', 'Pair\Models\Group', $content);
	$content = str_replace('Pair\Language', 'Pair\Models\Language', $content);
	$content = str_replace('Pair\Locale', 'Pair\Models\Locale', $content);
	$content = str_replace('Pair\Module', 'Pair\Models\Module', $content);
	$content = str_replace('Pair\Oauth\Oauth2Client', 'Pair\Models\Oauth2Client', $content);
	$content = str_replace('Pair\Oauth\Oauth2Token', 'Pair\Models\Oauth2Token', $content);
	$content = str_replace('Pair\Rule', 'Pair\Models\Rule', $content);
	$content = str_replace('Pair\Session', 'Pair\Models\Session', $content);
	$content = str_replace('Pair\Template', 'Pair\Models\Template', $content);
	$content = str_replace('Pair\Token', 'Pair\Models\Token', $content);
	$content = str_replace('Pair\User', 'Pair\Models\User', $content);
	$content = str_replace('Pair\UserRemember', 'Pair\Models\UserRemember', $content);
	$content = str_replace('Pair\ActiveRecord', 'Pair\Orm\ActiveRecord', $content);
	$content = str_replace('Pair\Database', 'Pair\Orm\Database', $content);
	$content = str_replace('Pair\AmazonS3', 'Pair\Services\AmazonS3', $content);
	$content = str_replace('Pair\Report', 'Pair\Services\Report', $content);

	// menu
	$content = str_replace('$menu->addItem(', '$menu->item(', $content);
	$content = str_replace('$menu->addSeparator(', '$menu->separator(', $content);
	$content = str_replace('$menu->addTitle(', '$menu->title(', $content);

	// toast notifications
	$content = str_replace('->enqueueMessage(', '->toast(', $content);
	$content = str_replace('->enqueueError(', '->toastError(', $content);
	$content = str_replace('->makeQueuedMessagesPersistent(', '->makeToastNotificationsPersistent(', $content);

	// JS methods
	$content = str_replace('::printJsonMessage(', '::pairJsonMessage(', $content);
	$content = str_replace('::printJsonError(', '::pairJsonError(', $content);
	$content = str_replace('::printJsonData(', '::pairJsonData(', $content);

	// Post methods
	$content = str_replace('Input::getInt(', 'Post::int(', $content);
	$content = str_replace('Input::getBool(', 'Post::bool(', $content);
	$content = str_replace('Input::getDate(', 'Post::date(', $content);
	$content = str_replace('Input::getDatetime(', 'Post::datetime(', $content);
	$content = str_replace('Input::getTrim(', 'Post::trim(', $content);
	$content = str_replace('Input::isSent(', 'Post::sent(', $content);
	$content = str_replace('Input::formPostSubmitted(', 'Post::submitted(', $content);
	$content = str_replace('Input::get(', 'Post::get(', $content);

	// Convert typed legacy inputs before the generic addInput() rewrite.
	$content = preg_replace_callback(
		'/->addInput\(\s*([\'\"])([^\'\"]+)\1\s*\)\s*->setType\(\s*([\'\"])([a-z]+)\3\s*\)/i',
		static function(array $matches): string {

			$type = strtolower($matches[4]);
			$method = 'bool' === $type ? 'checkbox' : $type;

			return '->' . $method . '(' . $matches[1] . $matches[2] . $matches[1] . ')';

		},
		$content
	);

	// Form methods
	$content = str_replace('->addSelect(', '->select(', $content);
	$content = str_replace('->setListByObjectArray(', '->options(', $content);
	$content = str_replace('->setListByAssociativeArray(', '->options(', $content);
	$content = str_replace('->setValue(', '->value(', $content);
	$content = str_replace('->setMultiple(', '->multiple(', $content);
	$content = str_replace('->prependEmpty(', '->empty(', $content);
	$content = str_replace('->addInput(', '->text(', $content);
	$content = str_replace('->setReadonly(', '->readonly(', $content);
	$content = str_replace('->setDisabled(', '->disabled(', $content);
	$content = str_replace('->setRequired(', '->required(', $content);
	$content = str_replace('->setPlaceholder(', '->placeholder(', $content);
	$content = str_replace('->setLabel(', '->label(', $content);
	$content = str_replace('->setAccept(', '->accept(', $content);
	$content = str_replace('->addButton(', '->button(', $content);
	$content = str_replace('->addControlClass(', '->classForControls(', $content);
	$content = str_replace('->getControl(', '->control(', $content);
	$content = str_replace('->getAllControls(', '->controls(', $content);
	$content = str_replace('->setDateFormat(', '->dateFormat(', $content);
	$content = str_replace('->setDatetimeFormat(', '->datetimeFormat(', $content);
	$content = str_replace('->setGroupedList(', '->grouped(', $content);
	$content = str_replace('->addTextarea(', '->textarea(', $content);
	$content = str_replace('->setValuesByObject(', '->values(', $content);
	$content = str_replace('->setAllReadonly(', '->allReadonly(', $content);

	// User
	$content = str_replace('User::getCurrent(', 'User::current(', $content);

	// Application
	$content = str_replace('Application::isDevelopmentHost()', '\'development\' == $app->getEnvironment()', $content);
	$content = str_replace('defined(\'PAIR_DEVELOPMENT\') and PAIR_DEVELOPMENT', '\'development\' == $app->getEnvironment()', $content);

	// Utilities
	$content = str_replace('Pair\Utilities::', 'Pair\Helpers\Utilities::', $content);
	$content = str_replace('::printJsonMessage(', '::pairJsonMessage(', $content);
	$content = str_replace('::printJsonError(', '::pairJsonError(', $content);
	$content = str_replace('::printJsonData(', '::pairJsonData(', $content);

	// Config
	$content = str_replace('print PRODUCT_NAME', 'print Config::get(\'PRODUCT_NAME\')', $content);
	$content = str_replace('= PRODUCT_NAME', '= Config::get(\'PRODUCT_NAME\')', $content);
	$content = str_replace('print PRODUCT_VERSION', 'print Config::get(\'PRODUCT_VERSION\')', $content);
	$content = str_replace('= PRODUCT_VERSION', '= Config::get(\'PRODUCT_VERSION\')', $content);

	// Database
	$content = preg_replace('|PAIR_DB_([A-Z_]+)|im', 'Database::$1', $content);

	// Template
	if (0 === strpos($file->getPathname(), APP_ROOT . '/templates/')) {
		$content = str_replace('Pair\Template::', 'Pair\Models\Template::', $content);
		$content = str_replace('print $this->pageStyles', '$app->printStyles()', $content);
		$content = str_replace('print $this->pageScripts', '$app->printScripts()', $content);
		$content = str_replace('<?php print $this->pageContent ?>', '{{content}}', $content);
		$content = str_replace('<?php print $this->pageTitle ?>', '{{title}}', $content);
		$content = str_replace('<?php print $this->log ?>', '{{logBar}}', $content);
		$content = preg_replace('|print \$this->([a-z]+)Widget|im', '$app->printWidget(\'$1\')', $content);
	}

	// Controller|Model|View
	$content = str_replace('protected function init() {', 'protected function init(): void {', $content);

	// View
	if (0 === strpos($file->getFilename(), 'view') and '.php' === substr($file->getFilename(), -4)) {
		$content = str_replace('public function render() {', 'public function render(): void {', $content);
		$content = preg_replace('|\$this->app->pageTitle[ \t]*=[ \t]*([^;]+);|im', '$this->setPageTitle($1);', $content);
	}
	
	// Layout
	$content = str_replace('->printSortableColumn(', '->sortable(', $content);

	// Logger
	$content = str_replace('Logger::event(', 'Logger::notice(', $content);
	$content = str_replace('ErrorLog::keepSnapshot(', 'Logger::error(', $content);

	// Form labels
	$search  = '^([\t]*)<label class="(col-[a-z\-0-9]+) control-label">(<\?php \$this->form->printLabel\(\'[a-z0-9_]+\'\) \?>)</label>';
	$replace = '$1<div class="$2">$3</div>';
	$content = preg_replace('|'.$search.'|im', $replace, $content);

	// Breadcrumb
	$content = str_replace('$breadcrumb->getPaths()', '$breadcrumb->getPath()', $content);
	$search = '^[\t]*\$breadcrumb = Breadcrumb::getInstance\(\);\n([\t]*)\$breadcrumb->addPath\(';
	$replace = '$1Breadcrumb::path(';
	$content = preg_replace('|'.$search.'|im', $replace, $content);

	$search = '^([\t]*)\$breadcrumb->addPath\(';
	$replace = '$1Breadcrumb::path(';
	$content = preg_replace('|'.$search.'|im', $replace, $content);

	// Crafter
	$content = preg_replace('|([ =\'])DEVELOPER[^_]|im', '$1CRAFTER', $content);

	// Classes
	$content = str_replace('protected function beforeCreate() {', 'protected function beforeCreate(): void {', $content);
	$content = str_replace('protected function afterCreate() {', 'protected function afterCreate(): void {', $content);
	$content = str_replace('protected function beforeUpdate() {', 'protected function beforeUpdate(): void {', $content);
	$content = str_replace('protected function afterUpdate() {', 'protected function afterUpdate(): void {', $content);
	$content = str_replace('protected function beforeDelete() {', 'protected function beforeDelete(): void {', $content);
	$content = str_replace('protected function afterDelete() {', 'protected function afterDelete(): void {', $content);
	$content = str_replace('protected function beforeStore() {', 'protected function beforeStore(): void {', $content);
	$content = str_replace('protected function afterStore() {', 'protected function afterStore(): void {', $content);

	// Widget
	$regex = [
		'[ \t]*\$widget[ \t]*=[ \t]*new[ t]*Widget\(\);[ \t]*[\n\r]',
		'[ \t]*\$this->app->[a-z]+Widget[ \t]*=[ \t]*\$widget->render\(\'[a-zA-Z]+\'\);[ \t]*[\n\r][ \t]*[\n\r]',
		'[ \t]*use Pair\\\Html\\\Widget;[ \t]*[\n\r]'
	];
		
	foreach ($regex as $r) {
		$content = preg_replace('|'.$r.'|im', '', $content);
	}

	// update the file only if the content has changed
	if ($content !== $originalContent) {
		$updatedFiles++;
		$changedFiles[] = relativePath(APP_ROOT, $file->getPathname());

		if ($writeMode and !writeUpgradeFile($file->getPathname(), $content)) {
			$writeErrors[] = relativePath(APP_ROOT, $file->getPathname()) . ': unable to write file';
		}
	}

	if (preg_match('/->(?:addMulti|getItemObject)\s*\(/', $content) === 1) {
		$warnings[] = relativePath(APP_ROOT, $file->getPathname()) . ': removed Menu API detected; migrate addMulti() or getItemObject() manually';
	}

	if (preg_match('/::(?:getJsMessage|printJsMessage)\s*\(/', $content) === 1) {
		$warnings[] = relativePath(APP_ROOT, $file->getPathname()) . ': removed JavaScript message helper detected; migrate it manually';
	}

}

print "Updated files: $updatedFiles\n";
foreach ($changedFiles as $changedFile) {
	print '- ' . $changedFile . "\n";
}
printWarnings($warnings);
printWriteErrors($writeErrors);
print "===============================\n";
print count($writeErrors) ? "Upgrade failed.\n" : "Upgrade completed.\n";

if (count($writeErrors)) {
	exit(1);
}

/**
 * Converts the content of a config.php file to .env format.
 *
 * @param string $configContent The content of the config.php file.
 * @return array{content: string, warnings: string[]} Converted content and unsupported statements.
 */
function convertConfigToEnv(string $configContent): array {

	$envContent = '';
	$warnings = [];

	// split the content by lines
	$lines = explode("\n", $configContent);

	foreach ($lines as $line) {

		// match and remove <?php with any leading spaces or tabs
		if (preg_match('/^\s*<\?php\s*$/', $line)) {
			continue;
		}

		// Convert scalar define() declarations and preserve quotes for string values.
		if (preg_match('/^\s*define\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*"|true|false|null|-?\d+(?:\.\d+)?)\s*\)\s*;\s*(?:\/\/.*)?$/i', $line, $matches)) {

			$key = $matches[1];
			$value = $matches[2];
			$envContent .= "$key=$value\n";

		// match PHP comments and convert them to .env comments
		} else if (preg_match('/^\s*\/\/\s*(.*)$/', $line, $matches)) {

			$comment = ucfirst(trim($matches[1]));
			$envContent .= "# $comment\n";

		// preserve empty lines
		} else if ('' === trim($line)) {

			$envContent .= "\n";

		} else if ('?>' === trim($line)) {

			continue;

		} else {

			$warnings[] = 'unsupported configuration statement: ' . trim($line);

		}

	}

	return [
		'content' => $envContent,
		'warnings' => $warnings,
	];

}

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
 * Print usage details for the Pair v1 upgrader.
 */
function printUsage(): void {

	print "Usage: php scripts/upgrade-to-v2.php [--dry-run] [--write] [--path=/absolute/app/path]\n";
	print "Defaults to write mode for backward compatibility.\n";

}

/**
 * Resolve the requested application root.
 */
function resolveTargetPath(string $path): string {

	return realpath($path) ?: $path;

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

/**
 * Print warnings that require manual migration work.
 *
 * @param string[] $warnings Collected warnings.
 */
function printWarnings(array $warnings): void {

	if (!count($warnings)) {
		print "Warnings: none\n";
		return;
	}

	print 'Warnings: ' . count($warnings) . "\n";
	foreach (array_unique($warnings) as $warning) {
		print '- ' . $warning . "\n";
	}

}

/**
 * Print write failures separately from migration warnings.
 *
 * @param string[] $writeErrors Collected write failures.
 */
function printWriteErrors(array $writeErrors): void {

	if (!count($writeErrors)) {
		return;
	}

	print 'Write errors: ' . count($writeErrors) . "\n";
	foreach ($writeErrors as $error) {
		print '- ' . $error . "\n";
	}

}
