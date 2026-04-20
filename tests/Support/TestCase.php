<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Core\Env;
use Pair\Html\UiTheme;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base test case that isolates superglobals and temporary environment files between tests.
 */
abstract class TestCase extends PhpUnitTestCase {

	/**
	 * Snapshot of $_COOKIE before the current test.
	 */
	private array $cookieBackup = [];

	/**
	 * Snapshot of $_ENV before the current test.
	 */
	private array $envBackup = [];

	/**
	 * Snapshot of $_FILES before the current test.
	 */
	private array $filesBackup = [];

	/**
	 * Snapshot of $_GET before the current test.
	 */
	private array $getBackup = [];

	/**
	 * Snapshot of $_POST before the current test.
	 */
	private array $postBackup = [];

	/**
	 * Snapshot of $_SERVER before the current test.
	 */
	private array $serverBackup = [];

	/**
	 * Reset mutable globals before each test to keep cases deterministic.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->cookieBackup = $_COOKIE;
		$this->envBackup = $_ENV;
		$this->filesBackup = $_FILES;
		$this->getBackup = $_GET;
		$this->postBackup = $_POST;
		$this->serverBackup = $_SERVER;

		$_COOKIE = [];
		$_ENV = [];
		$_FILES = [];
		$_GET = [];
		$_POST = [];
		$_SERVER = ['REQUEST_METHOD' => 'GET'];

		// Reset the global UI framework override so tests stay isolated.
		UiTheme::reset();

		$this->removeEnvFile();
		$this->removeDirectory(TEMP_PATH . 'rate_limits');

	}

	/**
	 * Restore mutable globals and clean the temporary .env file after each test.
	 */
	protected function tearDown(): void {

		$this->removeEnvFile();
		$this->removeDirectory(TEMP_PATH . 'rate_limits');

		$_COOKIE = $this->cookieBackup;
		$_ENV = $this->envBackup;
		$_FILES = $this->filesBackup;
		$_GET = $this->getBackup;
		$_POST = $this->postBackup;
		$_SERVER = $this->serverBackup;

		UiTheme::reset();

		parent::tearDown();

	}

	/**
	 * Force a private or protected property value through reflection for focused unit tests.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$name	Property name.
	 * @param	mixed	$value	Value to assign.
	 */
	protected function setInaccessibleProperty(object $object, string $name, mixed $value): void {

		$reflection = new \ReflectionProperty($object, $name);
		$reflection->setValue($object, $value);

	}

	/**
	 * Invoke a private or protected method through reflection for focused unit tests.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$name	Method name.
	 * @param	array	$args	Method arguments.
	 */
	protected function invokeInaccessibleMethod(object $object, string $name, array $args = []): mixed {

		$reflection = new \ReflectionMethod($object, $name);

		return $reflection->invokeArgs($object, $args);

	}

	/**
	 * Write the temporary .env file used by tests that exercise environment loading.
	 *
	 * @param	string	$contents	Full file contents.
	 */
	protected function writeEnvFile(string $contents): void {

		file_put_contents(Env::FILE, $contents);

	}

	/**
	 * Remove the temporary .env file when present.
	 */
	protected function removeEnvFile(): void {

		if (file_exists(Env::FILE)) {
			unlink(Env::FILE);
		}

	}

	/**
	 * Remove a directory tree created by tests.
	 *
	 * @param	string	$path	Absolute directory path to remove.
	 */
	protected function removeDirectory(string $path): void {

		if (!file_exists($path)) {
			return;
		}

		foreach (scandir($path) ?: [] as $item) {

			if ($item === '.' or $item === '..') {
				continue;
			}

			$itemPath = $path . DIRECTORY_SEPARATOR . $item;

			if (is_dir($itemPath) and !is_link($itemPath)) {
				$this->removeDirectory($itemPath);
			} else {
				unlink($itemPath);
			}

		}

		rmdir($path);

	}

	/**
	 * Execute a standalone PHP snippet in a subprocess and capture stdout, stderr, and exit code.
	 *
	 * @param	string	$body	PHP statements to execute after the shared bootstrap.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	protected function runPhpSnippet(string $body): array {

		$scriptPath = tempnam(sys_get_temp_dir(), 'pair-test-script-');

		if (false === $scriptPath) {
			$this->fail('Unable to allocate a temporary PHP script for the test subprocess.');
		}

		$script = implode("\n", [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'if (!defined(\'APPLICATION_PATH\')) {',
			"\tdefine('APPLICATION_PATH', " . var_export(APPLICATION_PATH, true) . ');',
			'}',
			'',
			'if (!defined(\'TEMP_PATH\')) {',
			"\tdefine('TEMP_PATH', " . var_export(TEMP_PATH, true) . ');',
			'}',
			'',
			'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';',
			'',
			$body,
			'',
		]);

		file_put_contents($scriptPath, $script);

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open([PHP_BINARY, $scriptPath], $descriptors, $pipes, dirname(__DIR__, 2));

		if (!is_resource($process)) {
			unlink($scriptPath);
			$this->fail('Unable to start the PHP subprocess for the test.');
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);

		unlink($scriptPath);

		return [
			'stdout' => is_string($stdout) ? $stdout : '',
			'stderr' => is_string($stderr) ? $stderr : '',
			'exitCode' => $exitCode,
		];

	}

}
