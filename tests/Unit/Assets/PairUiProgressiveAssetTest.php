<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static safety contracts for the progressive PairUI helpers.
 */
class PairUiProgressiveAssetTest extends TestCase {

	/**
	 * Verify progressive regions use explicit region headers and replacement events.
	 */
	public function testProgressiveRegionsAreExplicitAndEvented(): void {

		$source = $this->pairUiSource();

		$this->assertStringContainsString('PairUI.region = {', $source);
		$this->assertStringContainsString('data-pair-region', $source);
		$this->assertStringContainsString('data-pair-source', $source);
		$this->assertStringContainsString('X-Pair-Region', $source);
		$this->assertStringContainsString('X-Pair-UI', $source);
		$this->assertStringContainsString('AbortController', $source);
		$this->assertStringContainsString('pair:region:refreshing', $source);
		$this->assertStringContainsString('pair:region:replaced', $source);
		$this->assertStringContainsString('pair:region:error', $source);

	}

	/**
	 * Verify progressive actions and filters remain opt-in through data attributes.
	 */
	public function testActionsAndFiltersAreOptIn(): void {

		$source = $this->pairUiSource();

		$this->assertStringContainsString('PairUI.actions = {', $source);
		$this->assertStringContainsString('form[data-pair-submit]', $source);
		$this->assertStringContainsString('[data-pair-action]', $source);
		$this->assertStringContainsString('PairUI.filters = {', $source);
		$this->assertStringContainsString('[data-pair-filter]', $source);
		$this->assertStringContainsString('data-pair-refresh', $source);
		$this->assertStringContainsString('getPayloadRefreshRegions', $source);
		$this->assertStringContainsString('getPayloadRefreshUrl', $source);
		$this->assertStringContainsString('params instanceof FormData', $source);
		$this->assertStringContainsString('options.refresh === false', $source);
		$this->assertGreaterThanOrEqual(2, substr_count($source, 'options.refresh === false'));
		$this->assertStringContainsString('escapeCssAttributeValue', $source);
		$this->assertStringContainsString('getClientMessage("ERROR", "Error")', $source);
		$this->assertStringNotContainsString('getClientMessage("ERROR", "Errore")', $source);
		$this->assertStringNotContainsString('PairUI.actions.bind();', $source);
		$this->assertStringNotContainsString('PairUI.filters.bind();', $source);

	}

	/**
	 * Return the PairUI client source code.
	 */
	private function pairUiSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairUI.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairUI.js');
		}

		return $source;

	}

}
