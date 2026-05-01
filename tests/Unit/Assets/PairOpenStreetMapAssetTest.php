<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static safety contracts in the bundled OpenStreetMap client script.
 */
class PairOpenStreetMapAssetTest extends TestCase {

	/**
	 * Verify marker links are normalized before being assigned to anchors.
	 */
	public function testMarkerUrlsAreNormalizedBeforeRendering(): void {

		$source = $this->pairOpenStreetMapSource();

		$this->assertStringContainsString('static #normalizeMarkerUrl(rawUrl)', $source);
		$this->assertStringContainsString('allowedProtocols.includes(parsedUrl.protocol) ? url :', $source);
		$this->assertStringContainsString('url: this.#normalizeMarkerUrl(rawPoint.url || rawPoint.detailUrl ||', $source);

	}

	/**
	 * Verify invalid point payloads and excessive zoom options are guarded.
	 */
	public function testPointAndZoomInputGuardsArePresent(): void {

		$source = $this->pairOpenStreetMapSource();

		$this->assertStringContainsString('if (!rawPoint || typeof rawPoint !==', $source);
		$this->assertStringContainsString('static #clampInteger(value, minimum, maximum)', $source);
		$this->assertStringContainsString('static #maxAllowedZoom = 22;', $source);

	}

	/**
	 * Return the Pair OpenStreetMap client source code.
	 */
	private function pairOpenStreetMapSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairOpenStreetMap.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairOpenStreetMap.js');
		}

		return $source;

	}

}
