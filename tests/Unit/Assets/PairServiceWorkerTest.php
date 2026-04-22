<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static safety contracts in the bundled Pair service worker.
 */
class PairServiceWorkerTest extends TestCase {

	/**
	 * Verify runtime cache writes consult request and response cacheability.
	 */
	public function testRuntimeCacheChecksRequestAndResponseBeforeWriting(): void {

		$source = $this->serviceWorkerSource();

		$this->assertStringContainsString('if (!isCacheableResponse(request, response)) return;', $source);
		$this->assertStringContainsString('Cache-Control', $source);
		$this->assertStringContainsString('no-store|no-cache|private', $source);
		$this->assertStringContainsString('isApiRequestWithoutExplicitCachePolicy(request, cacheControl)', $source);
		$this->assertStringContainsString('url.pathname.startsWith("/api/")', $source);
		$this->assertStringContainsString('isSensitiveCacheRequest(request)', $source);
		$this->assertStringContainsString('request.headers.get("Authorization")', $source);
		$this->assertStringContainsString('url.searchParams.has(param)', $source);

	}

	/**
	 * Return the service worker source code.
	 */
	private function serviceWorkerSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairSW.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairSW.js');
		}

		return $source;

	}

}
