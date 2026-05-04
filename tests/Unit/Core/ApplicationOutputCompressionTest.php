<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Tests\Support\TestCase;

/**
 * Covers output-compression negotiation handled by the Application runtime.
 */
class ApplicationOutputCompressionTest extends TestCase {

	/**
	 * Verify that the Accept-Encoding parser honors quality values, wildcards and exact tokens.
	 */
	public function testAcceptEncodingParserHonorsHttpQualityValues(): void {

		$this->assertTrue($this->invokeApplicationStatic('acceptsGzipEncoding', ['br, gzip;q=1']));
		$this->assertTrue($this->invokeApplicationStatic('acceptsGzipEncoding', ['br, *;q=0.5']));
		$this->assertFalse($this->invokeApplicationStatic('acceptsGzipEncoding', ['br, gzip;q=0']));
		$this->assertFalse($this->invokeApplicationStatic('acceptsGzipEncoding', ['xgzip, br']));
		$this->assertFalse($this->invokeApplicationStatic('acceptsGzipEncoding', ['br, *;q=0']));

	}

	/**
	 * Verify that origin gzip mode is configurable and remains automatic by default.
	 */
	public function testOriginGzipModeSupportsAutoAndBooleanToggles(): void {

		$this->writeEnvFile(implode("\n", [
			'APP_ENV=development',
			'DB_UTF8=false',
		]));
		Env::load();
		$this->assertSame('auto', $this->invokeApplicationStatic('originGzipMode'));

		$this->writeEnvFile(implode("\n", [
			'APP_ENV=development',
			'DB_UTF8=false',
			'PAIR_ORIGIN_GZIP=false',
		]));
		Env::load(true);
		$this->assertSame('off', $this->invokeApplicationStatic('originGzipMode'));

		$this->writeEnvFile(implode("\n", [
			'APP_ENV=development',
			'DB_UTF8=false',
			'PAIR_ORIGIN_GZIP=true',
		]));
		Env::load(true);
		$this->assertSame('on', $this->invokeApplicationStatic('originGzipMode'));

	}

	/**
	 * Verify that Cloudflare is detected as an edge that can compress the origin response.
	 */
	public function testEdgeCompressionDetectsCloudflareHeaders(): void {

		$_SERVER['HTTP_CF_RAY'] = 'test-ray';
		$this->assertTrue($this->invokeApplicationStatic('isEdgeCompressedRequest'));

		unset($_SERVER['HTTP_CF_RAY']);
		$_SERVER['HTTP_CDN_LOOP'] = 'cloudflare';
		$this->assertTrue($this->invokeApplicationStatic('isEdgeCompressedRequest'));

		unset($_SERVER['HTTP_CDN_LOOP']);
		$this->assertFalse($this->invokeApplicationStatic('isEdgeCompressedRequest'));

	}

	/**
	 * Verify that output compression is not started in the CLI runtime.
	 */
	public function testGzipOutputBufferIsDisabledForCliRuntime(): void {

		$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

		$this->assertFalse($this->invokeApplicationStatic('shouldUseGzipOutputBuffer'));

	}

	/**
	 * Invoke a non-public static Application method to test bootstrap logic.
	 *
	 * @param	string	$method	Static method to invoke.
	 * @param	array	$args	Arguments to pass to the method.
	 */
	private function invokeApplicationStatic(string $method, array $args = []): mixed {

		$reflection = new \ReflectionMethod(Application::class, $method);

		return $reflection->invoke(null, ...$args);

	}

}
