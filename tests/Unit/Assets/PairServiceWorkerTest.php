<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static safety contracts in the bundled Pair service worker.
 */
class PairServiceWorkerTest extends TestCase {

	/**
	 * Verify the PWA client refuses explicit queue requests containing sensitive data.
	 */
	public function testPwaClientRejectsSensitiveExplicitQueueRequests(): void {

		$source = $this->pwaSource();

		$this->assertStringContainsString('normalizedUrl.origin !== global.location.origin', $source);
		$this->assertStringContainsString('this._isSensitiveQueueRequest(normalizedUrl, normalizedHeaders)', $source);
		$this->assertStringContainsString('String(key).toLowerCase() === "authorization"', $source);
		$this->assertStringContainsString('/\\/(auth|login|logout|oauth|passkey|session)(\\/|$)/i', $source);

	}

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
		$this->assertStringContainsString('readHeaderValue(headers, "Authorization")', $source);
		$this->assertStringContainsString('typeof headers.get === "function"', $source);
		$this->assertStringContainsString('url.searchParams.has(param)', $source);

	}

	/**
	 * Verify sensitive mutations cannot enter or leave the offline queue.
	 */
	public function testSensitiveMutationsAreNeverQueuedOrReplayed(): void {

		$source = $this->serviceWorkerSource();

		$this->assertStringContainsString('return isSensitiveRequestData(request.url, request.headers);', $source);
		$this->assertStringContainsString('if (isSensitiveCacheRequest(request)) return false;', $source);
		$this->assertStringContainsString('if (isSensitiveRequestData(url.href, headers)) return false;', $source);
		$this->assertStringContainsString('if (isSensitiveRequestData(item.url, item.headers)) {', $source);
		$this->assertStringContainsString('await deleteQueuedRequest(item.id).catch(() => false);', $source);

	}

	/**
	 * Return the Pair PWA client source code.
	 */
	private function pwaSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairPWA.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairPWA.js');
		}

		return $source;

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
