<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static safety contracts in the bundled LogBar client script.
 */
class PairLogBarAssetTest extends TestCase {

	/**
	 * Verify framework breakpoint detection is present for Bootstrap and Bulma LogBar shells.
	 */
	public function testBreakpointDetectionSupportsBootstrapAndBulma(): void {

		$source = $this->pairLogBarSource();

		$this->assertStringContainsString('const BOOTSTRAP_BREAKPOINTS', $source);
		$this->assertStringContainsString('{ name: "lg", min: 992 }', $source);
		$this->assertStringContainsString('const BULMA_BREAKPOINTS', $source);
		$this->assertStringContainsString('{ name: "desktop", min: 1024 }', $source);
		$this->assertStringContainsString('metric.querySelector(".logbar-context-value")', $source);
		$this->assertStringContainsString('window.addEventListener("resize", scheduleBreakpointUpdate);', $source);

	}

	/**
	 * Verify runtime context values can be copied without adding external dependencies.
	 */
	public function testRuntimeContextCopyIsHandledByLogBarClient(): void {

		$source = $this->pairLogBarSource();

		$this->assertStringContainsString('[data-logbar-copy-value]', $source);
		$this->assertStringContainsString('function copyContextValue(button)', $source);
		$this->assertStringContainsString('global.navigator.clipboard.writeText(value)', $source);

	}

	/**
	 * Return the Pair LogBar client source code.
	 */
	private function pairLogBarSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairLogBar.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairLogBar.js');
		}

		return $source;

	}

}
