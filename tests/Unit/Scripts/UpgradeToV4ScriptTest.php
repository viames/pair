<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Scripts;

use Pair\Tests\Support\TestCase;

/**
 * Covers the Pair v3 to v4 upgrade script with realistic fixture files.
 */
class UpgradeToV4ScriptTest extends TestCase {

	/**
	 * Remove the temporary fixture app after each test.
	 */
	protected function tearDown(): void {

		$this->removeDirectory($this->fixtureTargetPath());

		parent::tearDown();

	}

	/**
	 * Verify dry-run mode reports changes but does not modify the fixture files.
	 */
	public function testDryRunReportsChangesWithoutWritingFiles(): void {

		$this->copyFixtureTree(
			dirname(__DIR__, 2) . '/Fixtures/upgrade-to-v4/legacy-app',
			$this->fixtureTargetPath()
		);

		$controllerPath = $this->fixtureTargetPath() . '/modules/user/controller.php';
		$originalController = file_get_contents($controllerPath);
		$statusControllerPath = $this->fixtureTargetPath() . '/modules/status/controller.php';
		$originalStatusController = file_get_contents($statusControllerPath);

		$result = $this->runUpgradeScript(['--dry-run', '--path=' . $this->fixtureTargetPath()]);

		$this->assertSame(0, $result['exitCode']);
		$this->assertStringContainsString('Mode: dry-run', $result['stdout']);
		$this->assertStringContainsString('modules/status/controller.php', $result['stdout']);
		$this->assertStringContainsString('modules/user/classes/UserDefaultPageState.php', $result['stdout']);
		$this->assertStringContainsString('models/Faq.php', $result['stdout']);
		$this->assertStringContainsString('legacy View class detected', $result['stdout']);
		$this->assertStringContainsString('legacy View still owns controller-side responsibilities', $result['stdout']);
		$this->assertStringContainsString('legacy View still loads data from $this->model', $result['stdout']);
		$this->assertStringContainsString('controller still depends on the legacy MVC flow', $result['stdout']);
		$this->assertSame($originalController, file_get_contents($controllerPath));
		$this->assertSame($originalStatusController, file_get_contents($statusControllerPath));

	}

	/**
	 * Verify write mode applies the automatic rewrites and keeps reporting manual follow-ups.
	 */
	public function testWriteModeAppliesChangesAndKeepsWarnings(): void {

		$this->copyFixtureTree(
			dirname(__DIR__, 2) . '/Fixtures/upgrade-to-v4/legacy-app',
			$this->fixtureTargetPath()
		);

		$result = $this->runUpgradeScript(['--write', '--path=' . $this->fixtureTargetPath()]);

		$this->assertSame(0, $result['exitCode']);
		$this->assertStringContainsString('Mode: write', $result['stdout']);
		$this->assertStringContainsString('controller still depends on the legacy MVC flow', $result['stdout']);
		$this->assertStringContainsString('ActiveRecord::html() detected', $result['stdout']);

		$unsafeControllerContent = file_get_contents($this->fixtureTargetPath() . '/modules/user/controller.php');
		$this->assertStringContainsString('use Pair\\Core\\Controller;', (string)$unsafeControllerContent);
		$this->assertStringContainsString('protected function _init(): void', (string)$unsafeControllerContent);

		$safeControllerContent = file_get_contents($this->fixtureTargetPath() . '/modules/status/controller.php');
		$this->assertStringContainsString('use Pair\\Web\\Controller;', (string)$safeControllerContent);
		$this->assertStringContainsString('use Pair\\Helpers\\Translator;', (string)$safeControllerContent);
		$this->assertStringContainsString('protected function boot(): void', (string)$safeControllerContent);
		$this->assertStringContainsString("\$this->translate('STATUS')", (string)$safeControllerContent);
		$this->assertStringContainsString('private function translate(string $key, string|array|null $vars = null): string', (string)$safeControllerContent);
		$this->assertStringNotContainsString('->lang(', (string)$safeControllerContent);

		$pageStateContent = file_get_contents($this->fixtureTargetPath() . '/modules/user/classes/UserDefaultPageState.php');
		$this->assertStringContainsString('final readonly class UserDefaultPageState implements ReadModel', (string)$pageStateContent);
		$this->assertStringContainsString('public mixed $userName', (string)$pageStateContent);
		$this->assertStringContainsString("'userName' => \$this->userName,", (string)$pageStateContent);

		$faqContent = file_get_contents($this->fixtureTargetPath() . '/models/Faq.php');
		$this->assertStringContainsString("'readModel' => \\Pair\\Data\\Payload::class,", (string)$faqContent);

		$apiControllerContent = file_get_contents($this->fixtureTargetPath() . '/modules/api/controller.php');
		$this->assertStringContainsString('ApiResponse::respond(\Pair\Data\Payload::fromArray($user->toArray())->toArray(), 201);', (string)$apiControllerContent);

		$secondResult = $this->runUpgradeScript(['--write', '--path=' . $this->fixtureTargetPath()]);
		$this->assertSame(0, $secondResult['exitCode']);
		$this->assertStringNotContainsString('modules/user/classes/UserDefaultPageState.php', $secondResult['stdout']);

	}

	/**
	 * Copy a fixture tree to an isolated writable target.
	 */
	private function copyFixtureTree(string $sourcePath, string $targetPath): void {

		$directory = new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $item) {

			$relativePath = substr($item->getPathname(), strlen($sourcePath) + 1);
			$destinationPath = $targetPath . DIRECTORY_SEPARATOR . $relativePath;

			if ($item->isDir()) {
				if (!is_dir($destinationPath)) {
					mkdir($destinationPath, 0777, true);
				}
				continue;
			}

			$destinationDirectory = dirname($destinationPath);

			if (!is_dir($destinationDirectory)) {
				mkdir($destinationDirectory, 0777, true);
			}

			copy($item->getPathname(), $destinationPath);

		}

	}

	/**
	 * Return the isolated fixture target path for the current test process.
	 */
	private function fixtureTargetPath(): string {

		return TEMP_PATH . 'upgrade-to-v4-fixture';

	}

	/**
	 * Execute the upgrader script and capture its output.
	 *
	 * @param	string[]	$arguments	CLI arguments passed after the script path.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runUpgradeScript(array $arguments): array {

		$command = array_merge(
			[PHP_BINARY, dirname(__DIR__, 2) . '/../scripts/upgrade-to-v4.php'],
			$arguments
		);

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2) . '/..');

		if (!is_resource($process)) {
			$this->fail('Unable to start the upgrade-to-v4 script.');
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);

		return [
			'stdout' => is_string($stdout) ? $stdout : '',
			'stderr' => is_string($stderr) ? $stderr : '',
			'exitCode' => $exitCode,
		];

	}

}
