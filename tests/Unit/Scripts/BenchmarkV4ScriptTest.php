<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Scripts;

use Pair\Tests\Support\TestCase;

/**
 * Covers the Pair v4 benchmark script through subprocess execution.
 */
class BenchmarkV4ScriptTest extends TestCase {

	/**
	 * Remove any leftover benchmark template file after each test.
	 */
	protected function tearDown(): void {

		$this->removeBenchmarkTemplates();

		parent::tearDown();

	}

	/**
	 * Verify the benchmark script prints every documented scenario and cleans up its temp layout.
	 */
	public function testBenchmarkScriptPrintsScenarioTableAndCleansUpTemplate(): void {

		$this->removeBenchmarkTemplates();

		$result = $this->runBenchmarkScript();

		$this->assertSame(0, $result['exitCode'], 'STDOUT: ' . $result['stdout'] . ' STDERR: ' . $result['stderr']);
		$this->assertSame('', $result['stderr']);
		$this->assertStringContainsString("Pair v4 benchmarks\n==================\n", $result['stdout']);
		$this->assertBenchmarkLine($result['stdout'], 'bootstrap_minimal_v4_input');
		$this->assertBenchmarkLine($result['stdout'], 'record_payload_v3_to_array');
		$this->assertBenchmarkLine($result['stdout'], 'record_payload_v4_read_model');
		$this->assertBenchmarkLine($result['stdout'], 'json_endpoint_v4_prepare');
		$this->assertBenchmarkLine($result['stdout'], 'server_rendered_v4_page');
		$this->assertSame([], glob($this->benchmarkTemplatePattern()) ?: []);

	}

	/**
	 * Assert that one benchmark scenario line is present with numeric timing columns.
	 *
	 * @param	string	$output		Benchmark script stdout.
	 * @param	string	$scenario	Scenario label expected in the report.
	 */
	private function assertBenchmarkLine(string $output, string $scenario): void {

		$pattern = '/^' . preg_quote($scenario, '/') . '\s+total=\s*[0-9]+\.[0-9]{3}ms avg=\s*[0-9]+\.[0-9]{3}us iterations=[0-9]+$/m';

		$this->assertMatchesRegularExpression($pattern, $output);

	}

	/**
	 * Return the temporary layout path used by the benchmark script.
	 */
	private function benchmarkTemplatePattern(): string {

		return sys_get_temp_dir() . '/pair-benchmark-layout-*';

	}

	/**
	 * Remove any temporary benchmark layout files left behind by interrupted runs.
	 */
	private function removeBenchmarkTemplates(): void {

		foreach (glob($this->benchmarkTemplatePattern()) ?: [] as $templateFile) {
			unlink($templateFile);
		}

	}

	/**
	 * Execute the benchmark script and capture stdout, stderr, and exit code.
	 *
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runBenchmarkScript(): array {

		return $this->runPhpSnippet(
			'require ' . var_export(dirname(__DIR__, 2) . '/../scripts/benchmark-v4.php', true) . ';'
		);

	}

}
