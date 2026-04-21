<?php

declare(strict_types=1);

namespace Pair\Console;

/**
 * Minimal Pair code generator for modules, API modules, and CRUD resource skeletons.
 */
final class MakeCommand {

	/**
	 * Files created during the current run.
	 *
	 * @var string[]
	 */
	private array $createdFiles = [];

	/**
	 * Files skipped because they already existed with identical content.
	 *
	 * @var string[]
	 */
	private array $skippedFiles = [];

	/**
	 * Files that would be overwritten unless --force is used.
	 *
	 * @var string[]
	 */
	private array $blockedFiles = [];

	/**
	 * Execute the command from CLI arguments.
	 *
	 * @param	string[]	$argv				Raw command-line arguments.
	 * @param	string|null	$workingDirectory	Working directory override for tests.
	 */
	public function run(array $argv, ?string $workingDirectory = null): int {

		$this->createdFiles = [];
		$this->skippedFiles = [];
		$this->blockedFiles = [];

		$workingDirectory ??= getcwd() ?: '.';
		$arguments = array_slice($argv, 1);

		if (!count($arguments) or in_array($arguments[0], ['--help', '-h', 'help'], true)) {
			$this->printUsage();
			return 0;
		}

		try {
			$command = array_shift($arguments);
			$options = $this->parseOptions($arguments, $workingDirectory);

			match ($command) {
				'make:module' => $this->makeModule($options),
				'make:api' => $this->makeApi($options),
				'make:crud' => $this->makeCrud($options),
				default => throw new \InvalidArgumentException('Unknown command: ' . $command),
			};
		} catch (\InvalidArgumentException $exception) {
			fwrite(STDERR, $exception->getMessage() . PHP_EOL);
			$this->printUsage();
			return 1;
		}

		$this->printReport();

		return count($this->blockedFiles) ? 1 : 0;

	}

	/**
	 * Build the file plan for an API module.
	 *
	 * @param	array<string, mixed>	$options	Parsed command options.
	 */
	private function makeApi(array $options): void {

		$name = $this->requireName($options);
		$classPrefix = $this->classPrefix($name);
		$modulePath = $options['path'] . '/modules/' . $name;

		$this->writeFile(
			$modulePath . '/controller.php',
			$this->apiControllerTemplate($classPrefix),
			$options['force']
		);

		if ($options['withTest']) {
			$this->writeFile(
				$options['path'] . '/tests/Unit/Modules/' . $classPrefix . 'ApiModuleTest.php',
				$this->apiTestTemplate($classPrefix),
				$options['force']
			);
		}

	}

	/**
	 * Build the file plan for a CRUD resource skeleton.
	 *
	 * @param	array<string, mixed>	$options	Parsed command options.
	 */
	private function makeCrud(array $options): void {

		$name = $this->requireName($options);
		$table = $this->requireTable($options);
		$classPrefix = $this->classPrefix($name);
		$fields = $this->fields($options);

		$this->writeFile(
			$options['path'] . '/models/' . $classPrefix . '.php',
			$this->crudModelTemplate($classPrefix, $table, $fields),
			$options['force']
		);

		$this->writeFile(
			$options['path'] . '/classes/' . $classPrefix . 'ReadModel.php',
			$this->crudReadModelTemplate($classPrefix, $fields),
			$options['force']
		);

		if ($options['withTest']) {
			$this->writeFile(
				$options['path'] . '/tests/Unit/Models/' . $classPrefix . 'CrudTest.php',
				$this->crudTestTemplate($classPrefix),
				$options['force']
			);
		}

	}

	/**
	 * Build the file plan for a web module.
	 *
	 * @param	array<string, mixed>	$options	Parsed command options.
	 */
	private function makeModule(array $options): void {

		$name = $this->requireName($options);
		$classPrefix = $this->classPrefix($name);
		$modulePath = $options['path'] . '/modules/' . $name;

		$this->writeFile(
			$modulePath . '/controller.php',
			$this->moduleControllerTemplate($name, $classPrefix),
			$options['force']
		);

		$this->writeFile(
			$modulePath . '/classes/' . $classPrefix . 'DefaultPageState.php',
			$this->pageStateTemplate($classPrefix),
			$options['force']
		);

		$this->writeFile(
			$modulePath . '/layouts/default.php',
			$this->layoutTemplate(),
			$options['force']
		);

		if ($options['withJs']) {
			$this->writeFile(
				$modulePath . '/assets/' . $name . '.js',
				$this->moduleJavascriptTemplate($name, $classPrefix),
				$options['force']
			);
		}

		if ($options['withTest']) {
			$this->writeFile(
				$options['path'] . '/tests/Unit/Modules/' . $classPrefix . 'ModuleTest.php',
				$this->moduleTestTemplate($classPrefix),
				$options['force']
			);
		}

	}

	/**
	 * Return the generated API controller file.
	 */
	private function apiControllerTemplate(string $classPrefix): string {

		return <<<PHP
<?php

declare(strict_types=1);

use Pair\Api\ApiResponse;
use Pair\Api\CrudController;
use Pair\Http\ResponseInterface;

/**
 * Generated Pair v4 API controller.
 */
final class {$classPrefix}Controller extends CrudController {

\t/**
\t * Return a minimal health response for API smoke checks.
\t */
\tpublic function healthAction(): ResponseInterface {

\t\treturn ApiResponse::jsonResponse(['ok' => true]);

\t}

}
PHP . "\n";

	}

	/**
	 * Return the generated API test skeleton file.
	 */
	private function apiTestTemplate(string $classPrefix): string {

		return <<<PHP
<?php

declare(strict_types=1);

/**
 * Generated smoke test skeleton for the {$classPrefix} API module.
 */
final class {$classPrefix}ApiModuleTest extends \\PHPUnit\\Framework\\TestCase {

\t/**
\t * Replace this assertion with an application-specific API smoke test.
\t */
\tpublic function testGeneratedApiModuleSkeletonExists(): void {

\t\t\$this->assertTrue(true);

\t}

}
PHP . "\n";

	}

	/**
	 * Return a class prefix derived from a module or resource name.
	 */
	private function classPrefix(string $name): string {

		return ucfirst($name);

	}

	/**
	 * Return the generated CRUD model file.
	 *
	 * @param	string[]	$fields	Database field names.
	 */
	private function crudModelTemplate(string $classPrefix, string $table, array $fields): string {

		$properties = [];
		$binds = [];

		foreach ($fields as $field) {
			$property = $this->propertyName($field);
			$properties[] = "\t/**\n\t * Mapped {$field} column value.\n\t */\n\tpublic mixed \${$property} = null;";
			$binds[] = "\t\t\t'{$property}' => '{$field}',";
		}

		$propertiesBlock = implode("\n\n", $properties);
		$bindsBlock = implode("\n", $binds);

		return <<<PHP
<?php

declare(strict_types=1);

use Pair\Api\ApiExposable;
use Pair\Orm\ActiveRecord;

require_once APPLICATION_PATH . '/classes/{$classPrefix}ReadModel.php';

/**
 * Generated ActiveRecord model for the {$table} table.
 */
final class {$classPrefix} extends ActiveRecord {

\tuse ApiExposable;

\t/**
\t * Primary key column.
\t */
\tpublic const TABLE_KEY = 'id';

\t/**
\t * Database table name.
\t */
\tpublic const TABLE_NAME = '{$table}';

{$propertiesBlock}

\t/**
\t * Return the CRUD API configuration for this model.
\t *
\t * @return\tarray<string, mixed>
\t */
\tpublic static function apiConfig(): array {

\t\treturn [
\t\t\t'readModel' => {$classPrefix}ReadModel::class,
\t\t\t'sortable' => ['id'],
\t\t\t'filterable' => ['id'],
\t\t\t'rules' => [
\t\t\t\t'create' => [],
\t\t\t\t'update' => [],
\t\t\t],
\t\t];

\t}

\t/**
\t * Return explicit property-to-column bindings.
\t *
\t * @return\tarray<string, string>
\t */
\tpublic static function getBinds(): array {

\t\treturn [
{$bindsBlock}
\t\t];

\t}

}
PHP . "\n";

	}

	/**
	 * Return the generated CRUD read model file.
	 *
	 * @param	string[]	$fields	Database field names.
	 */
	private function crudReadModelTemplate(string $classPrefix, array $fields): string {

		$constructorProperties = [];
		$recordArguments = [];
		$arrayEntries = [];

		foreach ($fields as $field) {
			$property = $this->propertyName($field);
			$constructorProperties[] = "\t\tpublic mixed \${$property}";
			$recordArguments[] = "\t\t\t\$record->{$property}";
			$arrayEntries[] = "\t\t\t'{$property}' => \$this->{$property},";
		}

		$constructorBlock = implode(",\n", $constructorProperties);
		$recordBlock = implode(",\n", $recordArguments);
		$arrayBlock = implode("\n", $arrayEntries);

		return <<<PHP
<?php

declare(strict_types=1);

use Pair\Data\ArraySerializableData;
use Pair\Data\MapsFromRecord;
use Pair\Data\ReadModel;
use Pair\Orm\ActiveRecord;

/**
 * Generated read model for {$classPrefix} API responses.
 */
final readonly class {$classPrefix}ReadModel implements ReadModel, MapsFromRecord {

\tuse ArraySerializableData;

\t/**
\t * Build the read model.
\t */
\tpublic function __construct(
{$constructorBlock}
\t) {}

\t/**
\t * Build the read model from a persistence record.
\t */
\tpublic static function fromRecord(ActiveRecord \$record): static {

\t\treturn new self(
{$recordBlock}
\t\t);

\t}

\t/**
\t * Export the read model as an array.
\t *
\t * @return\tarray<string, mixed>
\t */
\tpublic function toArray(): array {

\t\treturn [
{$arrayBlock}
\t\t];

\t}

}
PHP . "\n";

	}

	/**
	 * Return the generated CRUD test skeleton file.
	 */
	private function crudTestTemplate(string $classPrefix): string {

		return <<<PHP
<?php

declare(strict_types=1);

/**
 * Generated smoke test skeleton for the {$classPrefix} CRUD resource.
 */
final class {$classPrefix}CrudTest extends \\PHPUnit\\Framework\\TestCase {

\t/**
\t * Replace this assertion with model and API contract tests.
\t */
\tpublic function testGeneratedCrudSkeletonExists(): void {

\t\t\$this->assertTrue(true);

\t}

}
PHP . "\n";

	}

	/**
	 * Return the parsed field list for CRUD generation.
	 *
	 * @param	array<string, mixed>	$options	Parsed command options.
	 * @return	string[]
	 */
	private function fields(array $options): array {

		$fields = $options['fields'];

		if (!in_array('id', $fields, true)) {
			array_unshift($fields, 'id');
		}

		return array_values(array_unique($fields));

	}

	/**
	 * Return the generated layout file.
	 */
	private function layoutTemplate(): string {

		return <<<PHP
<section class="pair-page">
\t<header class="pair-page__header">
\t\t<h1><?= htmlspecialchars(\$state->title, ENT_QUOTES, 'UTF-8') ?></h1>
\t</header>
</section>
PHP . "\n";

	}

	/**
	 * Return the generated module controller file.
	 */
	private function moduleControllerTemplate(string $name, string $classPrefix): string {

		return <<<PHP
<?php

declare(strict_types=1);

use Pair\Web\Controller;
use Pair\Web\PageResponse;

require_once __DIR__ . '/classes/{$classPrefix}DefaultPageState.php';

/**
 * Generated Pair v4 controller for the {$name} module.
 */
final class {$classPrefix}Controller extends Controller {

\t/**
\t * Return the default module page.
\t */
\tpublic function defaultAction(): PageResponse {

\t\treturn \$this->page('default', new {$classPrefix}DefaultPageState('{$classPrefix}'), '{$classPrefix}');

\t}

}
PHP . "\n";

	}

	/**
	 * Return the generated module JavaScript file.
	 */
	private function moduleJavascriptTemplate(string $name, string $classPrefix): string {

		return <<<JS
/**
 * Initialize progressive enhancements for the {$name} module.
 */
function init{$classPrefix}Module() {
}

document.addEventListener('DOMContentLoaded', init{$classPrefix}Module);
JS . "\n";

	}

	/**
	 * Return the generated module test skeleton file.
	 */
	private function moduleTestTemplate(string $classPrefix): string {

		return <<<PHP
<?php

declare(strict_types=1);

/**
 * Generated smoke test skeleton for the {$classPrefix} module.
 */
final class {$classPrefix}ModuleTest extends \\PHPUnit\\Framework\\TestCase {

\t/**
\t * Replace this assertion with module-specific response tests.
\t */
\tpublic function testGeneratedModuleSkeletonExists(): void {

\t\t\$this->assertTrue(true);

\t}

}
PHP . "\n";

	}

	/**
	 * Return the generated page-state class file.
	 */
	private function pageStateTemplate(string $classPrefix): string {

		return <<<PHP
<?php

declare(strict_types=1);

use Pair\Data\ArraySerializableData;
use Pair\Data\ReadModel;

/**
 * Generated typed state for the {$classPrefix} default layout.
 */
final readonly class {$classPrefix}DefaultPageState implements ReadModel {

\tuse ArraySerializableData;

\t/**
\t * Build the page state.
\t */
\tpublic function __construct(public string \$title) {}

\t/**
\t * Export the page state as an array.
\t *
\t * @return\tarray<string, mixed>
\t */
\tpublic function toArray(): array {

\t\treturn [
\t\t\t'title' => \$this->title,
\t\t];

\t}

}
PHP . "\n";

	}

	/**
	 * Parse CLI options after the command name.
	 *
	 * @param	string[]	$arguments			Command arguments.
	 * @param	string		$workingDirectory	Current working directory.
	 * @return	array{name: ?string, path: string, force: bool, withJs: bool, withTest: bool, table: ?string, fields: string[]}
	 */
	private function parseOptions(array $arguments, string $workingDirectory): array {

		$options = [
			'name' => null,
			'path' => $this->absolutePath($workingDirectory, '.'),
			'force' => false,
			'withJs' => false,
			'withTest' => false,
			'table' => null,
			'fields' => ['id'],
		];

		foreach ($arguments as $argument) {

			if ($argument === '--force') {
				$options['force'] = true;
				continue;
			}

			if ($argument === '--with-js') {
				$options['withJs'] = true;
				continue;
			}

			if ($argument === '--with-test') {
				$options['withTest'] = true;
				continue;
			}

			if (str_starts_with($argument, '--path=')) {
				$options['path'] = $this->absolutePath($workingDirectory, substr($argument, 7));
				continue;
			}

			if (str_starts_with($argument, '--table=')) {
				$options['table'] = $this->validateTable(substr($argument, 8));
				continue;
			}

			if (str_starts_with($argument, '--fields=')) {
				$options['fields'] = $this->parseFields(substr($argument, 9));
				continue;
			}

			if (str_starts_with($argument, '--')) {
				throw new \InvalidArgumentException('Unknown option: ' . $argument);
			}

			if (!is_null($options['name'])) {
				throw new \InvalidArgumentException('Only one name argument is supported.');
			}

			$options['name'] = $this->validateName($argument);

		}

		return $options;

	}

	/**
	 * Parse and validate a comma-separated field list.
	 *
	 * @return	string[]
	 */
	private function parseFields(string $rawFields): array {

		$fields = [];

		foreach (explode(',', $rawFields) as $field) {
			$field = trim($field);

			if ($field === '') {
				continue;
			}

			if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
				throw new \InvalidArgumentException('Invalid field name: ' . $field);
			}

			$fields[] = $field;
		}

		return count($fields) ? array_values(array_unique($fields)) : ['id'];

	}

	/**
	 * Print the command report.
	 */
	private function printReport(): void {

		if (count($this->createdFiles)) {
			print "Created files:\n";
			foreach ($this->createdFiles as $file) {
				print "- " . $file . "\n";
			}
		}

		if (count($this->skippedFiles)) {
			print "Unchanged files:\n";
			foreach ($this->skippedFiles as $file) {
				print "- " . $file . "\n";
			}
		}

		if (count($this->blockedFiles)) {
			print "Blocked existing files:\n";
			foreach ($this->blockedFiles as $file) {
				print "- " . $file . "\n";
			}
			print "Use --force only when you intend to replace generated files.\n";
		}

	}

	/**
	 * Print command usage.
	 */
	private function printUsage(): void {

		print "Pair generator\n";
		print "Usage:\n";
		print "  pair make:module <name> [--path=/app] [--with-js] [--with-test] [--force]\n";
		print "  pair make:api <name> [--path=/app] [--with-test] [--force]\n";
		print "  pair make:crud <name> --table=<table> [--fields=id,name] [--path=/app] [--with-test] [--force]\n";

	}

	/**
	 * Convert a field name into a camelCase property name.
	 */
	private function propertyName(string $field): string {

		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field))));

	}

	/**
	 * Return the required generator name.
	 *
	 * @param	array<string, mixed>	$options	Parsed command options.
	 */
	private function requireName(array $options): string {

		if (!is_string($options['name']) or $options['name'] === '') {
			throw new \InvalidArgumentException('Missing generator name.');
		}

		return $options['name'];

	}

	/**
	 * Return the required database table option.
	 *
	 * @param	array<string, mixed>	$options	Parsed command options.
	 */
	private function requireTable(array $options): string {

		if (!is_string($options['table']) or $options['table'] === '') {
			throw new \InvalidArgumentException('Missing required --table option.');
		}

		return $options['table'];

	}

	/**
	 * Resolve a path against the current working directory.
	 */
	private function absolutePath(string $workingDirectory, string $path): string {

		if ($path === '') {
			return $workingDirectory;
		}

		if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
			return rtrim($path, DIRECTORY_SEPARATOR);
		}

		return rtrim($workingDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);

	}

	/**
	 * Validate a generator name compatible with Pair module class resolution.
	 */
	private function validateName(string $name): string {

		$name = strtolower(trim($name));

		if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
			throw new \InvalidArgumentException('Invalid name "' . $name . '". Use lowercase letters and numbers only.');
		}

		return $name;

	}

	/**
	 * Validate a database table name.
	 */
	private function validateTable(string $table): string {

		$table = trim($table);

		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
			throw new \InvalidArgumentException('Invalid table name: ' . $table);
		}

		return $table;

	}

	/**
	 * Write a generated file without overwriting user edits unless --force was supplied.
	 */
	private function writeFile(string $filePath, string $content, bool $force): void {

		if (file_exists($filePath)) {

			$existingContent = file_get_contents($filePath);

			if ($existingContent === $content) {
				$this->skippedFiles[] = $filePath;
				return;
			}

			if (!$force) {
				$this->blockedFiles[] = $filePath;
				return;
			}

		}

		$directory = dirname($filePath);

		if (!is_dir($directory)) {
			mkdir($directory, 0777, true);
		}

		file_put_contents($filePath, $content);
		$this->createdFiles[] = $filePath;

	}

}
