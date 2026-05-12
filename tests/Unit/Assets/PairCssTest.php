<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static safety contracts in the bundled Pair stylesheet.
 */
class PairCssTest extends TestCase {

	/**
	 * Verify LogBar defaults to light theme while still supporting public theme hooks.
	 */
	public function testLogBarUsesLightDefaultsAndPublicThemeHooks(): void {

		$source = $this->pairCssSource();

		$this->assertStringContainsString('--pair-logbar-bg: var(--logbar-bg, var(--bs-card-bg, #ffffff));', $source);
		$this->assertStringContainsString('#logbar.logbar-shell:not(.logbar-shell-bootstrap)', $source);
		$this->assertStringContainsString('--pair-logbar-bg: var(--bs-card-bg, #ffffff);', $source);
		$this->assertStringContainsString('html[data-bs-theme="dark"] #logbar', $source);
		$this->assertStringContainsString('@media (prefers-color-scheme: dark)', $source);
		$this->assertStringContainsString('background: var(--pair-logbar-bg);', $source);
		$this->assertStringNotContainsString('background: #171c22;', $source);

	}

	/**
	 * Verify query diagnostics have dedicated styling hooks.
	 */
	public function testLogBarQueryDiagnosticsHaveStylingHooks(): void {

		$source = $this->pairCssSource();

		$this->assertStringContainsString('#logbar .logbar-query-issues', $source);
		$this->assertStringContainsString('#logbar .logbar-query-badge', $source);
		$this->assertStringContainsString('#logbar.logbar-shell-native [data-logbar-query-detail-toggle]', $source);
		$this->assertStringNotContainsString('#logbar .logbar-query-detail-toggle {', $source);

	}

	/**
	 * Verify compact context chips use visual centering instead of baseline alignment.
	 */
	public function testLogBarContextChipsAreVisuallyCentered(): void {

		$source = $this->pairCssSource();

		$this->assertStringContainsString('#logbar .logbar-context-item {' . PHP_EOL . '    align-items: center;', $source);
		$this->assertStringContainsString('#logbar .logbar-context-copy {' . PHP_EOL . '    align-items: center;', $source);
		$this->assertStringContainsString('    display: inline-flex;' . PHP_EOL . '    font: inherit;', $source);

	}

	/**
	 * Verify OK database metrics do not get success-colored frames.
	 */
	public function testLogBarOkMetricsDoNotUseGreenFrames(): void {

		$source = $this->pairCssSource();

		$this->assertStringContainsString('#logbar .logbar-metric.warning', $source);
		$this->assertStringContainsString('#logbar .logbar-metric.error', $source);
		$this->assertStringNotContainsString('#logbar .logbar-metric.database.active,' . PHP_EOL . '#logbar .logbar-metric.database:hover', $source);
		$this->assertStringNotContainsString('border-color: var(--pair-logbar-query);' . PHP_EOL . '    box-shadow: inset 0 0 0 1px rgba(120, 200, 138, 0.28);', $source);

	}

	/**
	 * Return the Pair stylesheet source code.
	 */
	private function pairCssSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/pair.css');

		if (!is_string($source)) {
			$this->fail('Unable to read pair.css');
		}

		return $source;

	}

}
