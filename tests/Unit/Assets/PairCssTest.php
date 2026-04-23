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
