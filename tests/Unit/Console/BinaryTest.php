<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Console;

use Pair\Tests\Support\TestCase;

/** Exercises the executable bootstrap, not just the generator class. */
final class BinaryTest extends TestCase {

	/** Remove only the isolated executable fixtures after each case. */
	protected function tearDown(): void {
		$this->removeDirectory($this->fixturePath());
		parent::tearDown();
	}

	/** The framework checkout works even when invoked from an unrelated directory. */
	public function testCheckout(): void {
		mkdir($this->fixturePath(), 0777, true);
		$result = $this->execute(dirname(__DIR__, 3) . '/bin/pair', ['--help']);
		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertStringContainsString('make:module', $result['stdout']);
	}

	/** Direct execution inside an installed package resolves the consuming project's vendor tree. */
	public function testInstalled(): void {
		$binary = $this->fixturePath() . '/vendor/viames/pair/bin/pair';
		$this->copyBinary($binary);
		$this->writeAutoload($this->fixturePath() . '/vendor/autoload.php');
		$result = $this->execute($binary, ['make:module', 'orders']);
		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertFileExists($this->fixturePath() . '/modules/orders/controller.php');
		$this->assertFileExists($this->fixturePath() . '/modules/orders/classes/OrdersDefaultPageState.php');
		$this->assertFileExists($this->fixturePath() . '/modules/orders/layouts/default.php');
	}

	/** A Composer proxy's explicit loader takes precedence over an unrelated package-local loader. */
	public function testProxy(): void {
		$binary = $this->fixturePath() . '/packages/pair/bin/pair';
		$this->copyBinary($binary);
		$this->writeAutoload($this->fixturePath() . '/application/dependencies/autoload.php');
		mkdir($this->fixturePath() . '/packages/pair/vendor', 0777, true);
		file_put_contents($this->fixturePath() . '/packages/pair/vendor/autoload.php', '<?php throw new RuntimeException("Wrong project loader");');
		$proxy = $this->fixturePath() . '/proxy.php';
		file_put_contents($proxy, '<?php $_composer_autoload_path = ' . var_export($this->fixturePath() . '/application/dependencies/autoload.php', true)
			. '; require ' . var_export($binary, true) . ';');
		$result = $this->execute($proxy, ['make:api', 'catalogapi']);
		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertFileExists($this->fixturePath() . '/modules/catalogapi/controller.php');
	}

	/** Missing dependencies return an actionable error without a PHP fatal or stack trace. */
	public function testMissingLoader(): void {
		$binary = $this->fixturePath() . '/missing/package/bin/pair';
		$this->copyBinary($binary);
		$result = $this->execute($binary, ['--help']);
		$this->assertSame(1, $result['exitCode']);
		$this->assertSame('', $result['stdout']);
		$this->assertStringContainsString('Run composer install', $result['stderr']);
		$this->assertStringNotContainsString('Fatal error', $result['stderr']);
	}

	/** Give every test a deterministic fixture root owned by this class. */
	private function fixturePath(): string {
		return TEMP_PATH . 'pair-binary-fixture';
	}

	/** Copy the actual executable so path resolution runs in the tested layout. */
	private function copyBinary(string $path): void {
		mkdir(dirname($path), 0777, true);
		copy(dirname(__DIR__, 3) . '/bin/pair', $path);
	}

	/** Use the real framework autoloader behind a consuming project's fixture loader. */
	private function writeAutoload(string $path): void {
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}
		file_put_contents($path, '<?php require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';');
	}

	/** Run a separate PHP process so exit codes and bootstrap globals are exercised. */
	private function execute(string $binary, array $arguments): array {
		$process = proc_open([PHP_BINARY, $binary, ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->fixturePath());
		$this->assertIsResource($process);
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		return ['stdout' => $stdout, 'stderr' => $stderr, 'exitCode' => proc_close($process)];
	}
}
