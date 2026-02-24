<?php

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Translator;
use Pair\Models\Audit;
use Pair\Models\Session;
use Pair\Models\User;
use Pair\Models\UserPasskey;

/**
 * Passkey/WebAuthn helper service for registration and authentication flows.
 *
 * This service provides challenge lifecycle management, browser options payloads,
 * registration persistence and assertion verification.
 */
class PasskeyAuth {

	/**
	 * Session key used to store active passkey challenges.
	 */
	const SESSION_KEY = '__pair_passkey_challenges';

	/**
	 * Allowed origins that can complete WebAuthn ceremonies.
	 * @var string[]
	 */
	private array $allowedOrigins = [];

	/**
	 * Challenge lifetime in seconds.
	 */
	private int $challengeTtl = 300;

	/**
	 * Relying Party identifier.
	 */
	private string $rpId = '';

	/**
	 * Relying Party display name.
	 */
	private string $rpName = '';

	/**
	 * Require user-verification bit during assertion.
	 */
	private bool $requireUserVerification = false;

	/**
	 * Constructor.
	 *
	 * @param	string|null	$rpId			WebAuthn RP ID.
	 * @param	string|null	$rpName			WebAuthn RP name.
	 * @param	string[]|null	$allowedOrigins	Explicit allowed origins list.
	 * @param	int			$challengeTtl	Challenge TTL in seconds.
	 */
	public function __construct(?string $rpId = null, ?string $rpName = null, ?array $allowedOrigins = null, int $challengeTtl = 300) {

		$this->rpId = $this->resolveRpId($rpId);
		$this->rpName = trim((string)($rpName ?? Env::get('PASSKEY_RP_NAME') ?? Env::get('APP_NAME')));
		$this->allowedOrigins = $this->resolveAllowedOrigins($allowedOrigins);

		$this->challengeTtl = max(60, (int)$challengeTtl);
		$this->requireUserVerification = $this->toBool(Env::get('PASSKEY_REQUIRE_USER_VERIFICATION'));

		if ('' === $this->rpName) {
			$this->rpName = 'Pair App';
		}

		if ('' === $this->rpId) {
			throw new PairException('Passkey RP ID is missing', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!count($this->allowedOrigins)) {
			throw new PairException('Passkey allowed origins are missing', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Starts a WebAuthn authentication ceremony.
	 *
	 * @param	User|null	$user				Optionally restrict to one user.
	 * @param	string[]	$allowCredentialIds	Optional allow-list of credential IDs.
	 * @param	array		$options			Optional payload overrides.
	 */
	public function beginAuthentication(?User $user = null, array $allowCredentialIds = [], array $options = []): array {

		if ($user and !count($allowCredentialIds)) {
			foreach (UserPasskey::getActiveByUserId($user->id) as $passkey) {
				$allowCredentialIds[] = $passkey->credentialId;
			}
		}

		$allowCredentialIds = $this->normalizeCredentialIdList($allowCredentialIds);
		$challenge = $this->storeChallenge('authentication', $user?->id);

		$payload = [
			'challenge'			=> $challenge,
			'rpId'				=> $this->rpId,
			'timeout'			=> isset($options['timeout']) ? (int)$options['timeout'] : 60000,
			'userVerification'	=> $options['userVerification'] ?? ($this->requireUserVerification ? 'required' : 'preferred'),
			'allowCredentials'	=> array_map(function(string $credentialId) {
				return [
					'type'	=> 'public-key',
					'id'	=> $credentialId
				];
			}, $allowCredentialIds)
		];

		return $payload;

	}

	/**
	 * Starts a WebAuthn registration ceremony.
	 *
	 * @param	User		$user				The authenticated user registering a passkey.
	 * @param	string|null	$userDisplayName	Optional custom display name.
	 * @param	string[]	$excludeCredentialIds	Optional exclude-list of credential IDs.
	 * @param	array		$options			Optional payload overrides.
	 */
	public function beginRegistration(User $user, ?string $userDisplayName = null, array $excludeCredentialIds = [], array $options = []): array {

		if (!count($excludeCredentialIds)) {
			foreach (UserPasskey::getActiveByUserId($user->id) as $passkey) {
				$excludeCredentialIds[] = $passkey->credentialId;
			}
		}

		$excludeCredentialIds = $this->normalizeCredentialIdList($excludeCredentialIds);
		$challenge = $this->storeChallenge('registration', $user->id);
		$userName = $this->resolveUserName($user);

		return [
			'challenge' => $challenge,
			'rp' => [
				'id'	=> $this->rpId,
				'name'	=> $this->rpName
			],
			'user' => [
				'id'			=> self::encodeBase64Url((string)$user->id),
				'name'			=> $userName,
				'displayName'	=> $userDisplayName ?? $user->fullName()
			],
			'pubKeyCredParams' => [
				['type' => 'public-key', 'alg' => -7],
				['type' => 'public-key', 'alg' => -257]
			],
			'timeout' => isset($options['timeout']) ? (int)$options['timeout'] : 60000,
			'attestation' => $options['attestation'] ?? 'none',
			'authenticatorSelection' => [
				'residentKey' => $options['residentKey'] ?? 'preferred',
				'userVerification' => $options['userVerification'] ?? ($this->requireUserVerification ? 'required' : 'preferred')
			],
			'excludeCredentials' => array_map(function(string $credentialId) {
				return [
					'type'	=> 'public-key',
					'id'	=> $credentialId
				];
			}, $excludeCredentialIds)
		];

	}

	/**
	 * Completes assertion verification and performs user login.
	 *
	 * @param	array		$credential	Assertion payload from browser.
	 * @param	string		$timezone	IANA time zone identifier.
	 * @param	User|null	$user		Optional expected user.
	 * @return	\stdClass				Object with error, message, userId and sessionId.
	 */
	public function completeAuthentication(array $credential, string $timezone, ?User $user = null): \stdClass {

		$ret = $this->failedLoginResponse();
		$credentialId = null;
		$state = null;
		$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

		try {

			$state = $this->consumeChallenge('authentication', $user?->id);
			$credentialId = $this->resolveCredentialId($credential);

			$passkey = UserPasskey::getActiveByCredentialId($credentialId);

			if (!$passkey) {
				Audit::loginFailed('passkey:' . substr($credentialId, 0, 12), $ipAddress, $userAgent);
				return $ret;
			}

			if ($user and $passkey->userId !== $user->id) {
				Audit::loginFailed('passkey:' . substr($credentialId, 0, 12), $ipAddress, $userAgent);
				return $ret;
			}

			$clientDataJsonB64 = $this->responseField($credential, 'clientDataJSON');
			$authenticatorDataB64 = $this->responseField($credential, 'authenticatorData');
			$signatureB64 = $this->responseField($credential, 'signature');

			$clientDataJson = $this->verifyClientData($clientDataJsonB64, 'webauthn.get', (string)$state->challenge);
			$authenticatorData = $this->parseAuthenticatorData($authenticatorDataB64);

			$expectedRpIdHash = hash('sha256', $this->rpId, true);
			if (!hash_equals($expectedRpIdHash, $authenticatorData->rpIdHash)) {
				Audit::loginFailed('passkey:' . substr($credentialId, 0, 12), $ipAddress, $userAgent);
				return $ret;
			}

			if (!$authenticatorData->userPresent) {
				Audit::loginFailed('passkey:' . substr($credentialId, 0, 12), $ipAddress, $userAgent);
				return $ret;
			}

			if ($this->requireUserVerification and !$authenticatorData->userVerified) {
				Audit::loginFailed('passkey:' . substr($credentialId, 0, 12), $ipAddress, $userAgent);
				return $ret;
			}

			if ($passkey->signCount > 0 and $authenticatorData->signCount > 0 and $authenticatorData->signCount <= $passkey->signCount) {
				Audit::loginFailed('passkey:' . substr($credentialId, 0, 12), $ipAddress, $userAgent);
				return $ret;
			}

			$clientDataHash = hash('sha256', $clientDataJson, true);
			$signedData = $authenticatorData->raw . $clientDataHash;

			if (!$this->verifySignature($signedData, $signatureB64, $passkey->publicKey)) {
				Audit::loginFailed('passkey:' . substr($credentialId, 0, 12), $ipAddress, $userAgent);
				return $ret;
			}

			$passkey->markUsed($authenticatorData->signCount);

			return User::doLoginById($passkey->userId, $timezone);

		} catch (\Throwable $e) {

			$identifier = $credentialId ? 'passkey:' . substr($credentialId, 0, 12) : 'passkey';
			Audit::loginFailed($identifier, $ipAddress, $userAgent);
			return $ret;

		}

	}

	/**
	 * Consumes and validates a previously stored challenge.
	 *
	 * @param	string	$purpose	Expected challenge purpose.
	 * @param	int|null	$userId		Optional expected user ID.
	 */
	private function consumeChallenge(string $purpose, ?int $userId = null): \stdClass {

		$this->ensureSessionStarted();

		$store = (array)Session::get(self::SESSION_KEY);
		$state = $store[$purpose] ?? null;

		// challenge is single-use
		unset($store[$purpose]);
		Session::set(self::SESSION_KEY, $store);

		if (!is_object($state)) {
			throw new PairException('Passkey challenge not found', ErrorCodes::INVALID_REQUEST);
		}

		if (!isset($state->expiresAt) or (int)$state->expiresAt < time()) {
			throw new PairException('Passkey challenge expired', ErrorCodes::INVALID_REQUEST);
		}

		if (!is_null($userId) and (int)$state->userId !== $userId) {
			throw new PairException('Passkey challenge user mismatch', ErrorCodes::VALIDATION_FAILED);
		}

		return $state;

	}

	/**
	 * Decodes a base64url string.
	 */
	private static function decodeBase64Url(string $value): string {

		$value = trim($value);
		$value = strtr($value, '-_', '+/');
		$padding = strlen($value) % 4;

		if ($padding > 0) {
			$value .= str_repeat('=', 4 - $padding);
		}

		$decoded = base64_decode($value, true);

		if (false === $decoded) {
			throw new PairException('Invalid base64url value', ErrorCodes::VALIDATION_FAILED);
		}

		return $decoded;

	}

	/**
	 * Converts DER SubjectPublicKeyInfo bytes to PEM format.
	 */
	private static function derToPem(string $binaryDer): string {

		return "-----BEGIN PUBLIC KEY-----\n" .
			chunk_split(base64_encode($binaryDer), 64, "\n") .
			"-----END PUBLIC KEY-----\n";

	}

	/**
	 * Ensures an active session exists.
	 */
	private function ensureSessionStarted(): void {

		if (session_status() !== PHP_SESSION_ACTIVE) {

			if (headers_sent()) {
				throw new PairException('Session is not active', ErrorCodes::INVALID_REQUEST);
			}

			session_start();
		}

	}

	/**
	 * Encodes bytes as base64url (without padding).
	 */
	private static function encodeBase64Url(string $value): string {

		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

	}

	/**
	 * Returns a standard failed-login response object.
	 */
	private function failedLoginResponse(): \stdClass {

		$ret = new \stdClass();
		$ret->error = true;
		$ret->message = Translator::do('AUTHENTICATION_FAILED');
		$ret->userId = null;
		$ret->sessionId = null;

		return $ret;

	}

	/**
	 * Normalizes and validates an origin value.
	 */
	private function normalizeOrigin(string $origin): ?string {

		$origin = trim($origin);

		if ('' === $origin) {
			return null;
		}

		$parts = parse_url($origin);

		if (!is_array($parts) or !isset($parts['scheme']) or !isset($parts['host'])) {
			return null;
		}

		$scheme = strtolower((string)$parts['scheme']);
		$host = strtolower((string)$parts['host']);
		$port = isset($parts['port']) ? (int)$parts['port'] : null;

		if (!in_array($scheme, ['http', 'https'], true)) {
			return null;
		}

		if ($port) {
			$isDefaultPort = ('http' === $scheme and 80 === $port) or ('https' === $scheme and 443 === $port);
			return $scheme . '://' . $host . ($isDefaultPort ? '' : ':' . $port);
		}

		return $scheme . '://' . $host;

	}

	/**
	 * Normalizes credential IDs list.
	 *
	 * @param	string[]	$credentialIds
	 * @return	string[]
	 */
	private function normalizeCredentialIdList(array $credentialIds): array {

		$list = [];

		foreach ($credentialIds as $id) {
			$id = trim((string)$id);
			if ('' !== $id) {
				$list[$id] = $id;
			}
		}

		return array_values($list);

	}

	/**
	 * Parses assertion authenticator data and extracts flags and signCount.
	 */
	private function parseAuthenticatorData(string $authenticatorDataB64): \stdClass {

		$raw = self::decodeBase64Url($authenticatorDataB64);

		if (strlen($raw) < 37) {
			throw new PairException('Invalid authenticator data', ErrorCodes::VALIDATION_FAILED);
		}

		$flags = ord($raw[32]);
		$signCount = unpack('N', substr($raw, 33, 4));

		$ret = new \stdClass();
		$ret->raw = $raw;
		$ret->rpIdHash = substr($raw, 0, 32);
		$ret->flags = $flags;
		$ret->userPresent = (bool)($flags & 0x01);
		$ret->userVerified = (bool)($flags & 0x04);
		$ret->signCount = (int)$signCount[1];

		return $ret;

	}

	/**
	 * Returns a nested response field from payload.
	 */
	private function responseField(array $credential, string $field): string {

		$response = isset($credential['response']) and is_array($credential['response'])
			? $credential['response']
			: [];

		$value = trim((string)($response[$field] ?? ''));

		if ('' === $value) {
			throw new PairException('Missing passkey response field: ' . $field, ErrorCodes::INVALID_REQUEST);
		}

		return $value;

	}

	/**
	 * Registers and stores a new passkey credential.
	 *
	 * @param	User		$user		Authenticated user.
	 * @param	array		$credential	Registration payload from browser.
	 * @param	string|null	$label		Optional passkey label.
	 * @return	UserPasskey
	 */
	public function registerCredential(User $user, array $credential, ?string $label = null): UserPasskey {

		$state = $this->consumeChallenge('registration', $user->id);
		$credentialId = $this->resolveCredentialId($credential);

		$clientDataJson = $this->responseField($credential, 'clientDataJSON');
		$this->verifyClientData($clientDataJson, 'webauthn.create', (string)$state->challenge);

		if (UserPasskey::getActiveByCredentialId($credentialId)) {
			throw new PairException('Passkey credential already exists', ErrorCodes::DUPLICATE_ENTRY);
		}

		$publicKey = $this->resolvePublicKey($credential);
		$transports = (array)($credential['response']['transports'] ?? $credential['transports'] ?? []);
		$signCount = 0;

		$authenticatorDataB64 = trim((string)($credential['response']['authenticatorData'] ?? ''));
		if ('' !== $authenticatorDataB64) {
			$authenticatorData = $this->parseAuthenticatorData($authenticatorDataB64);

			$expectedRpIdHash = hash('sha256', $this->rpId, true);
			if (!hash_equals($expectedRpIdHash, $authenticatorData->rpIdHash)) {
				throw new PairException('Passkey RP ID mismatch', ErrorCodes::VALIDATION_FAILED);
			}

			if (!$authenticatorData->userPresent) {
				throw new PairException('Passkey user presence not verified', ErrorCodes::VALIDATION_FAILED);
			}

			$signCount = $authenticatorData->signCount;
		}

		$passkey = new UserPasskey();
		$passkey->userId = $user->id;
		$passkey->credentialId = $credentialId;
		$passkey->publicKey = $publicKey;
		$passkey->signCount = $signCount;
		$passkey->label = $label ? trim($label) : null;
		$passkey->createdAt = new \DateTime();
		$passkey->updatedAt = new \DateTime();
		$passkey->setTransports($transports);

		if (!$passkey->store()) {
			throw new PairException('Unable to store passkey credential', ErrorCodes::STORE_FAILED);
		}

		return $passkey;

	}

	/**
	 * Resolves allowed origins from constructor, env or current BASE_HREF.
	 *
	 * @param	string[]|null	$allowedOrigins
	 * @return	string[]
	 */
	private function resolveAllowedOrigins(?array $allowedOrigins = null): array {

		$origins = [];

		if (is_array($allowedOrigins) and count($allowedOrigins)) {
			$origins = $allowedOrigins;
		} else if (is_string(Env::get('PASSKEY_ALLOWED_ORIGINS')) and trim((string)Env::get('PASSKEY_ALLOWED_ORIGINS'))) {
			$origins = explode(',', (string)Env::get('PASSKEY_ALLOWED_ORIGINS'));
		} else if (defined('BASE_HREF') and BASE_HREF) {
			$origins[] = (string)BASE_HREF;
		}

		$normalized = [];

		foreach ($origins as $origin) {
			$origin = $this->normalizeOrigin((string)$origin);
			if ($origin) {
				$normalized[$origin] = $origin;
			}
		}

		return array_values($normalized);

	}

	/**
	 * Resolves credential ID from browser payload.
	 */
	private function resolveCredentialId(array $credential): string {

		$id = trim((string)($credential['id'] ?? $credential['rawId'] ?? ''));

		if ('' === $id) {
			throw new PairException('Missing passkey credential id', ErrorCodes::INVALID_REQUEST);
		}

		if (!preg_match('#^[A-Za-z0-9\-_]+=*$#', $id)) {

			if (preg_match('#^[A-Za-z0-9+/=]+$#', $id)) {
				$id = rtrim(strtr($id, '+/', '-_'), '=');
			} else {
				throw new PairException('Invalid passkey credential id', ErrorCodes::VALIDATION_FAILED);
			}

		}

		return rtrim($id, '=');

	}

	/**
	 * Resolves public key from registration payload.
	 *
	 * Supported fields:
	 * - response.publicKeyPem
	 * - response.publicKey (base64url DER SPKI)
	 */
	private function resolvePublicKey(array $credential): string {

		if (!function_exists('openssl_pkey_get_public')) {
			throw new PairException('OpenSSL extension is required for Passkey', ErrorCodes::MISSING_CONFIGURATION);
		}

		$response = isset($credential['response']) and is_array($credential['response'])
			? $credential['response']
			: [];

		$publicKeyPem = trim((string)($response['publicKeyPem'] ?? $credential['publicKeyPem'] ?? ''));

		if (!$publicKeyPem) {
			$publicKeyDerB64 = trim((string)($response['publicKey'] ?? $credential['publicKey'] ?? ''));

			if (!$publicKeyDerB64) {
				throw new PairException('Missing passkey public key', ErrorCodes::INVALID_REQUEST);
			}

			$publicKeyPem = self::derToPem(self::decodeBase64Url($publicKeyDerB64));
		}

		$publicKey = openssl_pkey_get_public($publicKeyPem);

		if (false === $publicKey) {
			throw new PairException('Invalid passkey public key', ErrorCodes::VALIDATION_FAILED);
		}

		if (is_resource($publicKey)) {
			openssl_free_key($publicKey);
		}

		return $publicKeyPem;

	}

	/**
	 * Resolves RP ID from constructor, env or current host.
	 */
	private function resolveRpId(?string $rpId = null): string {

		$rpId = trim((string)($rpId ?? Env::get('PASSKEY_RP_ID')));

		if ('' !== $rpId) {
			return strtolower($rpId);
		}

		if (defined('BASE_HREF') and BASE_HREF) {
			$host = parse_url((string)BASE_HREF, PHP_URL_HOST);
			if (is_string($host) and '' !== trim($host)) {
				return strtolower(trim($host));
			}
		}

		if (isset($_SERVER['HTTP_HOST']) and trim((string)$_SERVER['HTTP_HOST'])) {
			return strtolower(trim((string)preg_replace('#:\d+$#', '', (string)$_SERVER['HTTP_HOST'])));
		}

		return '';

	}

	/**
	 * Resolves username used in WebAuthn user entity.
	 */
	private function resolveUserName(User $user): string {

		if (Env::get('PAIR_AUTH_BY_EMAIL') and $user->email) {
			return (string)$user->email;
		}

		if ($user->username) {
			return (string)$user->username;
		}

		return 'user-' . (string)$user->id;

	}

	/**
	 * Stores a challenge in session and returns it.
	 *
	 * @param	string	$purpose	Purpose token (registration/authentication).
	 * @param	int|null	$userId		Optional user ID lock.
	 */
	private function storeChallenge(string $purpose, ?int $userId = null): string {

		$this->ensureSessionStarted();

		$challenge = self::encodeBase64Url(random_bytes(32));
		$store = (array)Session::get(self::SESSION_KEY);

		$state = new \stdClass();
		$state->challenge = $challenge;
		$state->purpose = $purpose;
		$state->userId = $userId;
		$state->expiresAt = time() + $this->challengeTtl;

		$store[$purpose] = $state;

		Session::set(self::SESSION_KEY, $store);

		return $challenge;

	}

	/**
	 * Converts env values to boolean.
	 */
	private function toBool(mixed $value): bool {

		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
		}

		return (bool)$value;

	}

	/**
	 * Verifies clientDataJSON against expected type/challenge/origin.
	 *
	 * @param	string	$clientDataJsonB64	Base64url clientDataJSON.
	 * @param	string	$expectedType		Expected type ("webauthn.create" or "webauthn.get").
	 * @param	string	$expectedChallenge	Expected challenge.
	 * @return	string						Decoded clientDataJSON.
	 */
	private function verifyClientData(string $clientDataJsonB64, string $expectedType, string $expectedChallenge): string {

		$clientDataJson = self::decodeBase64Url($clientDataJsonB64);
		$clientData = json_decode($clientDataJson);

		if (!is_object($clientData)) {
			throw new PairException('Invalid passkey client data', ErrorCodes::VALIDATION_FAILED);
		}

		$type = trim((string)($clientData->type ?? ''));
		$challenge = trim((string)($clientData->challenge ?? ''));
		$origin = trim((string)($clientData->origin ?? ''));
		$normalizedOrigin = $this->normalizeOrigin($origin);

		if ($type !== $expectedType) {
			throw new PairException('Unexpected passkey type', ErrorCodes::VALIDATION_FAILED);
		}

		if (!hash_equals($expectedChallenge, $challenge)) {
			throw new PairException('Passkey challenge mismatch', ErrorCodes::VALIDATION_FAILED);
		}

		if (!$normalizedOrigin or !in_array($normalizedOrigin, $this->allowedOrigins, true)) {
			throw new PairException('Passkey origin not allowed', ErrorCodes::VALIDATION_FAILED);
		}

		return $clientDataJson;

	}

	/**
	 * Verifies assertion signature using stored public key.
	 *
	 * @param	string	$signedData		Data that was signed.
	 * @param	string	$signatureB64	Base64url signature.
	 * @param	string	$publicKeyPem	Public key in PEM format.
	 */
	private function verifySignature(string $signedData, string $signatureB64, string $publicKeyPem): bool {

		if (!function_exists('openssl_verify') or !function_exists('openssl_pkey_get_public')) {
			throw new PairException('OpenSSL extension is required for Passkey', ErrorCodes::MISSING_CONFIGURATION);
		}

		$signature = self::decodeBase64Url($signatureB64);
		$publicKey = openssl_pkey_get_public($publicKeyPem);

		if (false === $publicKey) {
			throw new PairException('Invalid passkey public key', ErrorCodes::VALIDATION_FAILED);
		}

		$verified = (1 === openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256));

		if (is_resource($publicKey)) {
			openssl_free_key($publicKey);
		}

		return $verified;

	}

}
