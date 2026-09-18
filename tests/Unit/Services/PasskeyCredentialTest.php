<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Exceptions\PairException;
use Pair\Services\PasskeyCredential;
use PHPUnit\Framework\TestCase;

/**
 * Covers native WebAuthn attestation normalization.
 */
final class PasskeyCredentialTest extends TestCase {

	/**
	 * Verify that an ES256 native attestation becomes Pair's browser-compatible payload.
	 */
	public function testNormalizesNativeEs256Attestation(): void {

		$key = openssl_pkey_new([
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'curve_name' => 'prime256v1',
		]);
		$this->assertNotFalse($key);
		$details = openssl_pkey_get_details($key);
		$this->assertIsArray($details);
		$this->assertIsArray($details['ec'] ?? null);

		$x = $details['ec']['x'] ?? null;
		$y = $details['ec']['y'] ?? null;
		$this->assertIsString($x);
		$this->assertIsString($y);

		$credentialId = random_bytes(16);
		$coseKey = self::cborMap([
			self::cborUnsigned(1) . self::cborUnsigned(2),
			self::cborUnsigned(3) . self::cborNegative(-7),
			self::cborNegative(-1) . self::cborUnsigned(1),
			self::cborNegative(-2) . self::cborBytes($x),
			self::cborNegative(-3) . self::cborBytes($y),
		]);
		$authenticatorData = hash('sha256', 'example.test', true)
			. "\x45"
			. pack('N', 0)
			. str_repeat("\x00", 16)
			. pack('n', strlen($credentialId))
			. $credentialId
			. $coseKey;
		$attestationObject = self::cborMap([
			self::cborText('fmt') . self::cborText('none'),
			self::cborText('authData') . self::cborBytes($authenticatorData),
			self::cborText('attStmt') . self::cborMap([]),
		]);

		$normalized = PasskeyCredential::normalizeRegistration([
			'id' => self::base64Url($credentialId),
			'response' => [
				'clientDataJSON' => self::base64Url('{"type":"webauthn.create"}'),
				'attestationObject' => self::base64Url($attestationObject),
			],
		]);

		$this->assertSame(self::base64Url($authenticatorData), $normalized['response']['authenticatorData']);
		$publicKeyDer = self::decodeBase64Url($normalized['response']['publicKey']);
		$publicKeyPem = "-----BEGIN PUBLIC KEY-----\n"
			. chunk_split(base64_encode($publicKeyDer), 64, "\n")
			. "-----END PUBLIC KEY-----\n";
		$this->assertNotFalse(openssl_pkey_get_public($publicKeyPem));

	}

	/**
	 * Verify that the credential ID is bound to the attested authenticator data.
	 */
	public function testRejectsMismatchedCredentialId(): void {

		$credentialId = random_bytes(16);
		$coseKey = self::cborMap([
			self::cborUnsigned(1) . self::cborUnsigned(2),
			self::cborUnsigned(3) . self::cborNegative(-7),
			self::cborNegative(-1) . self::cborUnsigned(1),
			self::cborNegative(-2) . self::cborBytes(str_repeat("\x01", 32)),
			self::cborNegative(-3) . self::cborBytes(str_repeat("\x02", 32)),
		]);
		$authenticatorData = hash('sha256', 'example.test', true)
			. "\x45"
			. pack('N', 0)
			. str_repeat("\x00", 16)
			. pack('n', strlen($credentialId))
			. $credentialId
			. $coseKey;
		$attestationObject = self::cborMap([
			self::cborText('authData') . self::cborBytes($authenticatorData),
		]);

		$this->expectException(PairException::class);

		PasskeyCredential::normalizeRegistration([
			'id' => self::base64Url(random_bytes(16)),
			'response' => ['attestationObject' => self::base64Url($attestationObject)],
		]);

	}

	/**
	 * Verify ambiguous CBOR maps cannot overwrite a security-relevant field.
	 */
	public function testRejectsDuplicateCborMapKey(): void {

		$attestationObject = self::cborMap([
			self::cborText('authData') . self::cborBytes('first'),
			self::cborText('authData') . self::cborBytes('second'),
		]);

		$this->expectException(PairException::class);

		PasskeyCredential::normalizeRegistration([
			'id' => self::base64Url('credential'),
			'response' => ['attestationObject' => self::base64Url($attestationObject)],
		]);

	}

	/**
	 * Verify that browser credentials with an extracted public key remain unchanged.
	 */
	public function testPreservesExistingBrowserPublicKey(): void {

		$credential = [
			'id' => 'credential-id',
			'response' => [
				'publicKey' => 'existing-key',
				'attestationObject' => 'not-cbor',
			],
		];

		$this->assertSame($credential, PasskeyCredential::normalizeRegistration($credential));

	}

	/**
	 * Encode a binary CBOR value with a definite length.
	 */
	private static function cborBytes(string $value): string {

		return self::cborHeader(2, strlen($value)) . $value;

	}

	/**
	 * Encode the smallest CBOR header for the supplied major type and value.
	 */
	private static function cborHeader(int $majorType, int $value): string {

		if ($value < 24) {
			return chr(($majorType << 5) | $value);
		}

		if ($value <= 0xff) {
			return chr(($majorType << 5) | 24) . chr($value);
		}

		return chr(($majorType << 5) | 25) . pack('n', $value);

	}

	/**
	 * Encode a CBOR map from pre-serialized key-value entries.
	 *
	 * @param	string[]	$entries
	 */
	private static function cborMap(array $entries): string {

		return self::cborHeader(5, count($entries)) . implode('', $entries);

	}

	/**
	 * Encode a negative CBOR integer.
	 */
	private static function cborNegative(int $value): string {

		return self::cborHeader(1, -1 - $value);

	}

	/**
	 * Encode a UTF-8 CBOR string.
	 */
	private static function cborText(string $value): string {

		return self::cborHeader(3, strlen($value)) . $value;

	}

	/**
	 * Encode an unsigned CBOR integer.
	 */
	private static function cborUnsigned(int $value): string {

		return self::cborHeader(0, $value);

	}

	/**
	 * Decode test base64url output.
	 */
	private static function decodeBase64Url(string $value): string {

		$standard = strtr($value, '-_', '+/');
		$standard .= str_repeat('=', (4 - strlen($standard) % 4) % 4);
		$decoded = base64_decode($standard, true);

		self::assertIsString($decoded);
		return $decoded;

	}

	/**
	 * Encode bytes as unpadded base64url.
	 */
	private static function base64Url(string $value): string {

		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

	}

}
