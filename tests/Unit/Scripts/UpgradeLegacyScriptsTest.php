<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Scripts;

use Pair\Tests\Support\TestCase;

/**
 * Covers the Pair v1 and v2 upgrade scripts and their composition with the v4 upgrader.
 */
class UpgradeLegacyScriptsTest extends TestCase {

	/**
	 * Remove the isolated legacy application after each test.
	 */
	protected function tearDown(): void {

		$this->removeDirectory($this->targetPath());

		parent::tearDown();

	}

	/**
	 * Verify the v1 upgrader preserves configuration and rewrites ordered legacy patterns safely.
	 */
	public function testV1UpgradeConvertsScalarConfigAndLegacyApis(): void {

		$this->createApplication([
			'config.php' => <<<'PHP'
<?php

define('PRODUCT_NAME', 'Legacy App');
define('PRODUCT_VERSION', '0012');
define('PAIR_DEBUG', false);
PHP,
			'modules/sample/controller.php' => <<<'PHP'
<?php

use Pair\Controller;
use Pair\Plugin;
use Pair\PluginInterface;
use Pair\Upload;

class SampleController extends Controller {

	protected function init() {}

	public function formAction(): void {

		$form = new Pair\Form();
		$form->addInput('enabled')->setType('bool');
		$form->setAllReadonly();
		$user = Pair\User::getCurrent();
		$upload = new Upload('document');
		$plugin = new Plugin();

	}

}

class SamplePackage extends Pair\ActiveRecord implements PluginInterface {

	public function getBaseFolder(): string { return '/tmp'; }
	public static function pluginExists(string $name): bool { return false; }
	public function getPlugin(): Plugin { return new Plugin(); }
	public function storeByPlugin(\SimpleXMLElement $options): bool { return true; }

}
PHP,
		]);

		$result = $this->runUpgrade('upgrade-to-v2.php', ['--write', '--path=' . $this->targetPath()]);

		$this->assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
		$this->assertFileExists($this->targetPath() . '/config.php');
		$this->assertStringContainsString("PRODUCT_NAME='Legacy App'", (string)file_get_contents($this->targetPath() . '/.env'));
		$this->assertStringContainsString("PRODUCT_VERSION='0012'", (string)file_get_contents($this->targetPath() . '/.env'));

		$content = (string)file_get_contents($this->targetPath() . '/modules/sample/controller.php');
		$this->assertStringContainsString('use Pair\\Helpers\\Plugin;', $content);
		$this->assertStringContainsString('use Pair\\Helpers\\PluginInterface;', $content);
		$this->assertStringContainsString('use Pair\\Helpers\\Upload;', $content);
		$this->assertStringContainsString("->checkbox('enabled')", $content);
		$this->assertStringContainsString('->allReadonly()', $content);
		$this->assertStringContainsString('Pair\\Models\\User::current()', $content);
		$this->assertStringNotContainsString('InstallablePackageInterface', $content);
		$this->assertStringNotContainsString('setType(', $content);

		$secondResult = $this->runUpgrade('upgrade-to-v2.php', ['--write', '--path=' . $this->targetPath()]);
		$this->assertSame(0, $secondResult['exitCode']);
		$this->assertSame($content, (string)file_get_contents($this->targetPath() . '/modules/sample/controller.php'));
		$this->assertStringContainsString('Updated files: 0', $secondResult['stdout']);

	}

	/**
	 * Verify unsupported PHP configuration expressions never cause partial conversion or deletion.
	 */
	public function testV1UpgradeRejectsUnsupportedConfigWithoutDataLoss(): void {

		$this->createApplication([
			'config.php' => <<<'PHP'
<?php

define('PRODUCT_NAME', 'Legacy App');
define('CUSTOM_PATH', dirname(__DIR__) . '/storage');
PHP,
		]);

		$result = $this->runUpgrade('upgrade-to-v2.php', ['--write', '--path=' . $this->targetPath()]);

		$this->assertSame(1, $result['exitCode']);
		$this->assertFileExists($this->targetPath() . '/config.php');
		$this->assertFileDoesNotExist($this->targetPath() . '/.env');
		$this->assertStringContainsString('unsupported configuration statement', $result['stdout']);
		$this->assertStringContainsString('automatic conversion is incomplete', $result['stdout']);

	}

	/**
	 * Verify both legacy upgraders can preview changes without mutating application files.
	 */
	public function testLegacyDryRunModesDoNotWriteFiles(): void {

		$this->createApplication([
			'config.php' => "<?php\n\ndefine('PRODUCT_NAME', 'Legacy App');\n",
			'modules/sample/controller.php' => "<?php\n\nuse Pair\\Controller;\n",
		]);
		$controllerPath = $this->targetPath() . '/modules/sample/controller.php';
		$originalController = (string)file_get_contents($controllerPath);

		$v2Result = $this->runUpgrade('upgrade-to-v2.php', ['--dry-run', '--path=' . $this->targetPath()]);

		$this->assertSame(0, $v2Result['exitCode']);
		$this->assertStringContainsString('Mode: dry-run', $v2Result['stdout']);
		$this->assertFileDoesNotExist($this->targetPath() . '/.env');
		$this->assertSame($originalController, file_get_contents($controllerPath));

		$this->removeDirectory($this->targetPath());
		$this->createApplication([
			'.env' => "PRODUCT_NAME = Legacy App\n",
			'modules/sample/controller.php' => <<<'PHP'
<?php

use Pair\Core\Controller;

class SampleController extends Controller {

	protected function init(): void {}

}
PHP,
		]);
		$controllerPath = $this->targetPath() . '/modules/sample/controller.php';
		$originalController = (string)file_get_contents($controllerPath);

		$v3Result = $this->runUpgrade('upgrade-to-v3.php', ['--dry-run', '--path=' . $this->targetPath()]);

		$this->assertSame(0, $v3Result['exitCode']);
		$this->assertStringContainsString('Mode: dry-run', $v3Result['stdout']);
		$this->assertStringContainsString('PRODUCT_NAME = Legacy App', (string)file_get_contents($this->targetPath() . '/.env'));
		$this->assertSame($originalController, file_get_contents($controllerPath));

	}

	/**
	 * Verify legacy upgrader write failures are reported through the process exit code.
	 */
	public function testLegacyWriteFailuresReturnNonZeroExitCodes(): void {

		$cases = [
			'upgrade-to-v2.php' => "<?php\n\nuse Pair\\Controller;\n",
			'upgrade-to-v3.php' => <<<'PHP'
<?php

use Pair\Core\Controller;

class SampleController extends Controller {

	protected function init(): void {}

}
PHP,
		];

		foreach ($cases as $script => $content) {
			$this->removeDirectory($this->targetPath());
			$this->createApplication(['modules/sample/controller.php' => $content]);
			$controllerPath = $this->targetPath() . '/modules/sample/controller.php';
			chmod($controllerPath, 0444);

			try {
				$result = $this->runUpgrade($script, ['--write', '--path=' . $this->targetPath()]);
			} finally {
				chmod($controllerPath, 0644);
			}

			$this->assertSame(1, $result['exitCode'], $script . "\n" . $result['stderr'] . $result['stdout']);
			$this->assertStringContainsString('Write errors: 1', $result['stdout']);
			$this->assertStringContainsString('modules/sample/controller.php: unable to write file', $result['stdout']);
		}

	}

	/**
	 * Verify the v2 upgrader normalizes spaced env keys without touching unrelated init methods.
	 */
	public function testV2UpgradeScopesLifecycleAndMigratesPluginRecords(): void {

		$this->createApplication([
			'.env' => "PRODUCT_NAME = Legacy App\nPRODUCT_VERSION = 2.4\nPAIR_ENVIRONMENT = development\nPAIR_DEBUG = true\n",
			'modules/sample/controller.php' => <<<'PHP'
<?php

use Pair\Core\Controller;

class SampleController extends Controller {

	protected function init(): void {}

}
PHP,
			'classes/IndependentService.php' => <<<'PHP'
<?php

class IndependentService {

	public function init(): void {}

	public function start(): void {

		$this->init();

	}

}
PHP,
			'models/SamplePackage.php' => <<<'PHP'
<?php

use Pair\Helpers\Plugin;
use Pair\Helpers\PluginInterface;
use Pair\Orm\ActiveRecord;

class SamplePackage extends ActiveRecord implements PluginInterface {

	public function getBaseFolder(): string { return '/tmp'; }
	public static function pluginExists(string $name): bool { return false; }
	public function getPlugin(): Plugin { return new Plugin(); }
	public function storeByPlugin(\SimpleXMLElement $options): bool { return true; }

}
PHP,
		]);

		$result = $this->runUpgrade('upgrade-to-v3.php', ['--write', '--path=' . $this->targetPath()]);

		$this->assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
		$env = (string)file_get_contents($this->targetPath() . '/.env');
		$this->assertStringContainsString('APP_NAME=Legacy App', $env);
		$this->assertStringContainsString('APP_VERSION=2.4', $env);
		$this->assertStringContainsString('APP_ENV=development', $env);
		$this->assertStringContainsString('APP_DEBUG=true', $env);

		$controller = (string)file_get_contents($this->targetPath() . '/modules/sample/controller.php');
		$service = (string)file_get_contents($this->targetPath() . '/classes/IndependentService.php');
		$package = (string)file_get_contents($this->targetPath() . '/models/SamplePackage.php');
		$this->assertStringContainsString('function _init()', $controller);
		$this->assertStringContainsString('function init()', $service);
		$this->assertStringContainsString('$this->init()', $service);
		$this->assertStringContainsString('use Pair\\Helpers\\PluginBase;', $package);
		$this->assertStringContainsString('extends PluginBase', $package);
		$this->assertStringNotContainsString('implements PluginInterface', $package);

	}

	/**
	 * Verify a representative v1 package reaches the Pair v4 package contract through all scripts.
	 */
	public function testCompleteV1ToV4UpgradePathIsComposable(): void {

		$this->createApplication([
			'config.php' => "<?php\n\ndefine('PRODUCT_NAME', 'Legacy App');\ndefine('PRODUCT_VERSION', '1.9.18');\n",
			'models/SamplePackage.php' => <<<'PHP'
<?php

use Pair\Plugin;
use Pair\PluginInterface;

class SamplePackage extends Pair\ActiveRecord implements PluginInterface {

	public function getBaseFolder(): string { return '/tmp'; }
	public static function pluginExists(string $name): bool { return false; }
	public function getPlugin(): Plugin { return new Plugin(); }
	public function storeByPlugin(\SimpleXMLElement $options): bool { return true; }

}
PHP,
		]);

		foreach (['upgrade-to-v2.php', 'upgrade-to-v3.php', 'upgrade-to-v4.php'] as $script) {
			$result = $this->runUpgrade($script, ['--write', '--path=' . $this->targetPath()]);
			$this->assertSame(0, $result['exitCode'], $script . "\n" . $result['stderr'] . $result['stdout']);
		}

		$env = (string)file_get_contents($this->targetPath() . '/.env');
		$package = (string)file_get_contents($this->targetPath() . '/models/SamplePackage.php');
		$this->assertStringContainsString("APP_NAME='Legacy App'", $env);
		$this->assertStringContainsString("APP_VERSION='1.9.18'", $env);
		$this->assertStringContainsString('use Pair\\Packages\\InstallablePackage;', $package);
		$this->assertStringContainsString('use Pair\\Packages\\InstallablePackageRecord;', $package);
		$this->assertStringContainsString('extends InstallablePackageRecord', $package);
		$this->assertStringContainsString('getInstallablePackage()', $package);
		$this->assertStringContainsString('packageRecordExists(', $package);
		$this->assertStringContainsString('storeFromPackageManifest(', $package);
		$this->assertStringNotContainsString('PluginInterface', $package);

	}

	/**
	 * Create one isolated application tree with a Composer autoload marker.
	 *
	 * @param array<string, string> $files Fixture files indexed by relative path.
	 */
	private function createApplication(array $files): void {

		$files['vendor/autoload.php'] = "<?php\n";

		foreach ($files as $relativePath => $content) {
			$filePath = $this->targetPath() . '/' . $relativePath;
			$directory = dirname($filePath);

			if (!is_dir($directory)) {
				mkdir($directory, 0777, true);
			}

			file_put_contents($filePath, $content);
		}

	}

	/**
	 * Execute one upgrade script and capture its process result.
	 *
	 * @param string[] $arguments CLI arguments passed after the script path.
	 * @return array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runUpgrade(string $script, array $arguments): array {

		$command = array_merge(
			[PHP_BINARY, dirname(__DIR__, 3) . '/scripts/' . $script],
			$arguments
		);
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 3));

		if (!is_resource($process)) {
			$this->fail('Unable to start ' . $script . '.');
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

	/**
	 * Return the isolated application path for the current test process.
	 */
	private function targetPath(): string {

		return TEMP_PATH . 'upgrade-legacy-fixture';

	}

}
