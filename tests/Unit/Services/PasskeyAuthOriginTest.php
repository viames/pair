<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Services\PasskeyAuth;
use PHPUnit\Framework\TestCase;

final class PasskeyAuthOriginTest extends TestCase {

	public function testAcceptsCredentialManagerAndroidOrigin(): void {

		$this->assertSame(
			'android:apk-key-hash:QcwVyHrkFHu0-nTkES0z-fmeVDOmb3rKXhp8x5BdD-E',
			$this->normalizeOrigin('android:apk-key-hash:QcwVyHrkFHu0-nTkES0z-fmeVDOmb3rKXhp8x5BdD-E')
		);

	}

	public function testRejectsMalformedAndroidOrigin(): void {

		$this->assertNull($this->normalizeOrigin('android:apk-key-hash:not-a-certificate-hash'));
		$this->assertNull($this->normalizeOrigin('android:apk-key-hash:QcwVyHrkFHu0+nTkES0z/fmeVDOmb3rKXhp8x5BdD+E='));

	}

	private function normalizeOrigin(string $origin): ?string {

		$reflection = new \ReflectionClass(PasskeyAuth::class);
		$service = $reflection->newInstanceWithoutConstructor();
		$method = $reflection->getMethod('normalizeOrigin');

		return $method->invoke($service, $origin);

	}

}
