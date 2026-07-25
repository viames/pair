<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers progressive WebAuthn contracts in the bundled Pair passkey client.
 */
class PairPasskeyTest extends TestCase {

	/**
	 * Verify conditional mediation capability checks fail closed on unsupported browsers.
	 */
	public function testConditionalMediationCapabilityCheckIsProgressive(): void {

		$source = $this->passkeySource();

		$this->assertStringContainsString('static async isConditionalMediationAvailable()', $source);
		$this->assertStringContainsString('typeof global.PublicKeyCredential.isConditionalMediationAvailable !== "function"', $source);
		$this->assertStringContainsString('await global.PublicKeyCredential.isConditionalMediationAvailable()', $source);
		$this->assertStringContainsString('return false;', $source);

	}

	/**
	 * Verify assertion requests forward optional mediation and cancellation controls.
	 */
	public function testAssertionForwardsConditionalMediationAndAbortSignal(): void {

		$source = $this->passkeySource();

		$this->assertStringContainsString('static async getAssertion(publicKeyOptions, options = {})', $source);
		$this->assertStringContainsString('["silent", "optional", "required", "conditional"].includes(mediation)', $source);
		$this->assertStringContainsString('credentialOptions.mediation = mediation;', $source);
		$this->assertStringContainsString('credentialOptions.signal = options.signal;', $source);
		$this->assertStringContainsString('navigator.credentials.get(credentialOptions)', $source);

	}

	/**
	 * Verify the high-level login helper keeps old defaults while accepting the new options.
	 */
	public function testLoginForwardsMediationAndSignalWithoutChangingDefaults(): void {

		$source = $this->passkeySource();

		$this->assertStringContainsString('...(options.requestOptions || {})', $source);
		$this->assertStringContainsString('if (options.signal)', $source);
		$this->assertStringContainsString('mediation: options.mediation ?? null', $source);
		$this->assertStringContainsString('signal: options.signal ?? null', $source);
		$this->assertStringContainsString('username: options.username ?? null', $source);

	}

	/**
	 * Return the Pair passkey client source code.
	 */
	private function passkeySource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairPasskey.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairPasskey.js');
		}

		return $source;

	}

}
