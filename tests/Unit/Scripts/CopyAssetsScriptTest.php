<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Scripts;

use Pair\Tests\Support\TestCase;

/**
 * Covers the bundled asset copy script used by consuming Pair applications.
 */
class CopyAssetsScriptTest extends TestCase {

	/**
	 * Remove the temporary package fixture after each test.
	 */
	protected function tearDown(): void {

		$this->removeDirectory($this->fixturePath());

		parent::tearDown();

	}

	/**
	 * Verify the JavaScript-only copy path includes the OpenStreetMap asset.
	 */
	public function testJavascriptCopyIncludesOpenStreetMapAsset(): void {

		$this->removeDirectory($this->fixturePath());

		$projectPath = $this->fixturePath() . '/project';
		$packagePath = $projectPath . '/vendor/viames/pair';
		$targetPath = $projectPath . '/public/assets';

		$this->createPackageFixture($packagePath);

		$result = $this->runCopyAssetsScript($packagePath . '/scripts/copy-assets.php', ['public/assets', 'js']);

		$this->assertSame(0, $result['exitCode'], 'STDOUT: ' . $result['stdout'] . ' STDERR: ' . $result['stderr']);
		$this->assertStringContainsString('Copied js assets to ', $result['stdout']);
		$this->assertFileExists($targetPath . '/PairOpenStreetMap.js');
		$this->assertFileDoesNotExist($targetPath . '/pair.css');

	}

	/**
	 * Create a minimal Composer vendor tree that matches the script path assumptions.
	 */
	private function createPackageFixture(string $packagePath): void {

		mkdir($packagePath . '/scripts', 0777, true);
		mkdir($packagePath . '/assets', 0777, true);

		copy(dirname(__DIR__, 3) . '/scripts/copy-assets.php', $packagePath . '/scripts/copy-assets.php');
		copy(dirname(__DIR__, 3) . '/assets/PairOpenStreetMap.js', $packagePath . '/assets/PairOpenStreetMap.js');
		copy(dirname(__DIR__, 3) . '/assets/pair.css', $packagePath . '/assets/pair.css');

	}

	/**
	 * Return the isolated package fixture path.
	 */
	private function fixturePath(): string {

		return TEMP_PATH . 'copy-assets-fixture';

	}

	/**
	 * Execute the asset copy script and capture stdout, stderr, and exit code.
	 *
	 * @param	string[]	$arguments	CLI arguments passed after the script path.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runCopyAssetsScript(string $scriptPath, array $arguments): array {

		$command = array_merge([PHP_BINARY, $scriptPath], $arguments);
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		// The script resolves project root from its vendor path, so cwd is intentionally irrelevant.
		$process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 3));

		if (!is_resource($process)) {
			$this->fail('Unable to start the copy-assets script.');
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
