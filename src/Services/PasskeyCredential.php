<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Normalizes native WebAuthn registration credentials for Pair.
 *
 * Browser clients may send a DER SubjectPublicKeyInfo directly, while native
 * iOS and Android APIs return the public key inside a CBOR attestation object.
 */
class PasskeyCredential {

	private const MAX_ATTESTATION_BYTES = 65536;
	private const MAX_CBOR_DEPTH = 16;

	/**
	 * Add the authenticator data and DER public key expected by PasskeyAuth.
	 *
	 * Existing browser payloads that already contain a public key are returned unchanged.
	 *
	 * @param	array<string, mixed>	$credential	Registration credential supplied by a WebAuthn client.
	 * @return	array<string, mixed>
	 */
	public static function normalizeRegistration(array $credential): array {

		$response = (isset($credential['response']) and is_array($credential['response']))
			? $credential['response']
			: [];

		if ('' !== trim((string)($response['publicKey'] ?? $response['publicKeyPem'] ?? ''))) {
			return $credential;
		}

		$attestationObject = self::decodeBase64Url((string)($response['attestationObject'] ?? ''));
		$offset = 0;
		$attestation = self::decodeCborValue($attestationObject, $offset, 0);

		if ($offset !== strlen($attestationObject)
			or !is_array($attestation)
			or !isset($attestation['authData'])
			or !is_string($attestation['authData'])) {
			throw new PairException('Invalid passkey attestation object', ErrorCodes::VALIDATION_FAILED);
		}

		$authenticatorData = $attestation['authData'];
		$attestedCredential = self::extractCredential($authenticatorData);
		$credentialId = self::decodeBase64Url((string)($credential['id'] ?? $credential['rawId'] ?? ''));

		if (!hash_equals($attestedCredential['credentialId'], $credentialId)) {
			throw new PairException('Passkey credential ID does not match attestation', ErrorCodes::VALIDATION_FAILED);
		}

		$response['authenticatorData'] = self::encodeBase64Url($authenticatorData);
		$response['publicKey'] = self::encodeBase64Url($attestedCredential['publicKey']);
		$credential['response'] = $response;

		return $credential;

	}

	/**
	 * Decode a bounded base64url value.
	 */
	private static function decodeBase64Url(string $value): string {

		$value = trim($value);

		if ('' === $value
			or strlen($value) > self::MAX_ATTESTATION_BYTES * 2
			or !preg_match('/^[A-Za-z0-9_-]+$/', $value)
			or 1 === strlen($value) % 4) {
			throw new PairException('Invalid passkey attestation encoding', ErrorCodes::VALIDATION_FAILED);
		}

		$standard = strtr($value, '-_', '+/');
		$standard .= str_repeat('=', (4 - strlen($standard) % 4) % 4);
		$decoded = base64_decode($standard, true);

		if (false === $decoded or strlen($decoded) > self::MAX_ATTESTATION_BYTES) {
			throw new PairException('Invalid passkey attestation encoding', ErrorCodes::VALIDATION_FAILED);
		}

		return $decoded;

	}

	/**
	 * Decode the bounded CBOR subset used by WebAuthn attestation and COSE keys.
	 */
	private static function decodeCborValue(string $data, int &$offset, int $depth): mixed {

		if ($depth > self::MAX_CBOR_DEPTH or $offset >= strlen($data)) {
			throw new PairException('Invalid passkey CBOR data', ErrorCodes::VALIDATION_FAILED);
		}

		$initial = ord($data[$offset++]);
		$majorType = $initial >> 5;
		$additional = $initial & 0x1f;
		$length = self::decodeCborLength($data, $offset, $additional);

		if (0 === $majorType) {
			return $length;
		}

		if (1 === $majorType) {
			return -1 - $length;
		}

		if (2 === $majorType or 3 === $majorType) {
			if ($length < 0 or $offset + $length > strlen($data)) {
				throw new PairException('Truncated passkey CBOR data', ErrorCodes::VALIDATION_FAILED);
			}

			$value = substr($data, $offset, $length);
			$offset += $length;
			return $value;
		}

		if (4 === $majorType) {
			$value = [];
			for ($index = 0; $index < $length; $index++) {
				$value[] = self::decodeCborValue($data, $offset, $depth + 1);
			}
			return $value;
		}

		if (5 === $majorType) {
			$value = [];
			for ($index = 0; $index < $length; $index++) {
				$key = self::decodeCborValue($data, $offset, $depth + 1);
				if (!is_int($key) and !is_string($key)) {
					throw new PairException('Invalid passkey CBOR map key', ErrorCodes::VALIDATION_FAILED);
				}
				if (array_key_exists($key, $value)) {
					throw new PairException('Duplicate passkey CBOR map key', ErrorCodes::VALIDATION_FAILED);
				}
				$value[$key] = self::decodeCborValue($data, $offset, $depth + 1);
			}
			return $value;
		}

		if (7 === $majorType and in_array($additional, [20, 21, 22], true)) {
			return 20 === $additional ? false : (21 === $additional ? true : null);
		}

		throw new PairException('Unsupported passkey CBOR type', ErrorCodes::VALIDATION_FAILED);

	}

	/**
	 * Read a definite CBOR length while rejecting indefinite and oversized values.
	 */
	private static function decodeCborLength(string $data, int &$offset, int $additional): int {

		if ($additional < 24) {
			return $additional;
		}

		$byteCount = match ($additional) {
			24 => 1,
			25 => 2,
			26 => 4,
			default => throw new PairException('Unsupported passkey CBOR length', ErrorCodes::VALIDATION_FAILED),
		};

		if ($offset + $byteCount > strlen($data)) {
			throw new PairException('Truncated passkey CBOR data', ErrorCodes::VALIDATION_FAILED);
		}

		$value = 0;
		for ($index = 0; $index < $byteCount; $index++) {
			$value = ($value << 8) | ord($data[$offset++]);
		}

		if ($value > self::MAX_ATTESTATION_BYTES) {
			throw new PairException('Passkey CBOR value is too large', ErrorCodes::VALIDATION_FAILED);
		}

		return $value;

	}

	/**
	 * Encode a positive ASN.1 INTEGER from unsigned bytes.
	 */
	private static function derInteger(string $value): string {

		$value = ltrim($value, "\x00");

		if ('' === $value) {
			$value = "\x00";
		}

		if (ord($value[0]) & 0x80) {
			$value = "\x00" . $value;
		}

		return "\x02" . self::derLength(strlen($value)) . $value;

	}

	/**
	 * Encode a short or multi-byte DER length.
	 */
	private static function derLength(int $length): string {

		if ($length < 128) {
			return chr($length);
		}

		$encoded = '';
		while ($length > 0) {
			$encoded = chr($length & 0xff) . $encoded;
			$length >>= 8;
		}

		return chr(0x80 | strlen($encoded)) . $encoded;

	}

	/**
	 * Encode bytes as unpadded WebAuthn base64url.
	 */
	private static function encodeBase64Url(string $value): string {

		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

	}

	/**
	 * Extract the credential ID and convert an ES256 or RS256 COSE key to DER SPKI.
	 *
	 * @return	array{credentialId: string, publicKey: string}
	 */
	private static function extractCredential(string $authenticatorData): array {

		$flags = strlen($authenticatorData) > 32 ? ord($authenticatorData[32]) : 0;

		if (strlen($authenticatorData) < 55
			or 0x40 !== ($flags & 0x40)
			or 0x01 !== ($flags & 0x01)) {
			throw new PairException('Passkey authenticator data has no attested credential', ErrorCodes::VALIDATION_FAILED);
		}

		$credentialIdLength = unpack('nlength', substr($authenticatorData, 53, 2));
		$credentialIdLength = (int)($credentialIdLength['length'] ?? 0);
		$coseOffset = 55 + $credentialIdLength;

		if ($credentialIdLength < 1 or $coseOffset >= strlen($authenticatorData)) {
			throw new PairException('Invalid passkey authenticator data', ErrorCodes::VALIDATION_FAILED);
		}

		$offset = $coseOffset;
		$coseKey = self::decodeCborValue($authenticatorData, $offset, 0);

		if (!is_array($coseKey)) {
			throw new PairException('Invalid passkey COSE key', ErrorCodes::VALIDATION_FAILED);
		}

		$publicKey = self::subjectPublicKeyInfo($coseKey);

		return [
			'credentialId' => substr($authenticatorData, 55, $credentialIdLength),
			'publicKey' => $publicKey,
		];

	}

	/**
	 * Convert an ES256 or RS256 COSE key to a DER SubjectPublicKeyInfo structure.
	 *
	 * @param	array<int|string, mixed>	$coseKey
	 */
	private static function subjectPublicKeyInfo(array $coseKey): string {

		$keyType = $coseKey[1] ?? null;
		$algorithm = $coseKey[3] ?? null;

		if (2 === $keyType and -7 === $algorithm
			and 1 === ($coseKey[-1] ?? null)
			and is_string($coseKey[-2] ?? null)
			and is_string($coseKey[-3] ?? null)
			and 32 === strlen($coseKey[-2])
			and 32 === strlen($coseKey[-3])) {
			$algorithmIdentifier = hex2bin('301306072A8648CE3D020106082A8648CE3D030107');
			$encodedPoint = "\x04" . $coseKey[-2] . $coseKey[-3];
			$subjectPublicKey = "\x03" . self::derLength(strlen($encodedPoint) + 1) . "\x00" . $encodedPoint;
			$body = $algorithmIdentifier . $subjectPublicKey;

			return "\x30" . self::derLength(strlen($body)) . $body;
		}

		if (3 === $keyType and -257 === $algorithm
			and is_string($coseKey[-1] ?? null)
			and is_string($coseKey[-2] ?? null)
			and '' !== $coseKey[-1]
			and '' !== $coseKey[-2]) {
			$rsaKeyBody = self::derInteger($coseKey[-1]) . self::derInteger($coseKey[-2]);
			$rsaKey = "\x30" . self::derLength(strlen($rsaKeyBody)) . $rsaKeyBody;
			$algorithmIdentifier = hex2bin('300D06092A864886F70D0101010500');
			$subjectPublicKey = "\x03" . self::derLength(strlen($rsaKey) + 1) . "\x00" . $rsaKey;
			$body = $algorithmIdentifier . $subjectPublicKey;

			return "\x30" . self::derLength(strlen($body)) . $body;
		}

		throw new PairException('Unsupported passkey COSE key', ErrorCodes::VALIDATION_FAILED);

	}

}
