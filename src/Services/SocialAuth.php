<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Models\Session;

/**
 * OAuth/OIDC client helper for social sign-in flows.
 */
class SocialAuth {

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 15;

	/**
	 * Social auth state lifetime in seconds.
	 */
	private const STATE_TTL = 600;

	/**
	 * Session key used to store active social auth state values.
	 */
	private const SESSION_KEY = '__pair_social_auth_states';

	/**
	 * Built-in provider metadata for common OAuth/OIDC providers.
	 */
	private const DEFAULT_PROVIDERS = [
		'google' => [
			'label' => 'Google',
			'icon' => 'fab fa-google',
			'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token_url' => 'https://oauth2.googleapis.com/token',
			'profile_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
			'scopes' => ['openid', 'email', 'profile'],
			'profile_query' => [],
		],
		'apple' => [
			'label' => 'Apple',
			'icon' => 'fab fa-apple',
			'authorize_url' => 'https://appleid.apple.com/auth/authorize',
			'token_url' => 'https://appleid.apple.com/auth/token',
			'profile_url' => '',
			'scopes' => ['name', 'email'],
			'profile_query' => [],
			'authorize_params' => ['response_mode' => 'form_post'],
		],
		'microsoft' => [
			'label' => 'Microsoft',
			'icon' => 'fab fa-microsoft',
			'authorize_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
			'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			'profile_url' => 'https://graph.microsoft.com/oidc/userinfo',
			'scopes' => ['openid', 'email', 'profile'],
			'profile_query' => [],
		],
		'whatsapp' => [
			'label' => 'WhatsApp',
			'icon' => 'fab fa-whatsapp',
			'authorize_url' => 'https://www.facebook.com/v23.0/dialog/oauth',
			'token_url' => 'https://graph.facebook.com/v23.0/oauth/access_token',
			'profile_url' => 'https://graph.facebook.com/v23.0/me',
			'scopes' => ['business_management', 'whatsapp_business_management'],
			'profile_query' => ['fields' => 'id,name,email,picture.type(large)'],
		],
	];

	/**
	 * HTTP connection timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Normalized provider configurations keyed by provider key.
	 *
	 * @var	array<string, array<string, mixed>>
	 */
	private array $providers;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Build a SocialAuth helper using explicit provider settings or Env configuration.
	 *
	 * @param	array<string, array<string, mixed>>|null	$providers	Optional provider map.
	 */
	public function __construct(?array $providers = null, ?int $timeout = null, ?int $connectTimeout = null) {

		$this->providers = $this->normalizeProviders($providers ?? $this->providersFromEnv());
		$this->timeout = max(1, (int)($timeout ?? Env::get('PAIR_SOCIAL_AUTH_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('PAIR_SOCIAL_AUTH_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));

	}

	/**
	 * Build an authorization URL and store the anti-forgery state in session.
	 *
	 * @param	array<string, mixed>	$context	Flow-specific state restored on callback.
	 * @param	array<string, mixed>	$params		Extra authorization request parameters.
	 */
	public function begin(string $providerKey, string $redirectUri, array $context = [], array $params = []): string {

		$provider = $this->provider($providerKey);
		$redirectUri = $this->sanitizeUrl($redirectUri, 'redirect URI');
		$state = $this->storeState($provider['key'], $redirectUri, $context);

		$query = array_merge([
			'response_type' => 'code',
			'client_id' => $provider['client_id'],
			'redirect_uri' => $redirectUri,
			'scope' => implode(' ', $provider['scopes']),
			'state' => $state,
		], $provider['authorize_params'], $params);

		return $this->buildUrl($provider['authorize_url'], $this->withoutEmptyValues($query));

	}

	/**
	 * Complete an OAuth callback, exchange the code and return a normalized social profile.
	 *
	 * @param	array<string, mixed>	$query	Callback query parameters.
	 */
	public function complete(string $providerKey, array $query, ?string $redirectUri = null): \stdClass {

		$provider = $this->provider($providerKey);

		if (isset($query['error']) and '' !== trim((string)$query['error'])) {
			throw new PairException('Social authentication was rejected by the provider.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$code = trim((string)($query['code'] ?? ''));
		$stateValue = trim((string)($query['state'] ?? ''));

		if ('' === $code or '' === $stateValue) {
			throw new PairException('Social authentication callback is missing code or state.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$state = $this->consumeState($stateValue, $provider['key']);

		if (!is_null($redirectUri) and !hash_equals((string)$state->redirectUri, $redirectUri)) {
			throw new PairException('Social authentication redirect URI mismatch.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$token = $this->requestToken($provider, [
			'grant_type' => 'authorization_code',
			'code' => $code,
			'redirect_uri' => (string)$state->redirectUri,
			'client_id' => $provider['client_id'],
			'client_secret' => $provider['client_secret'],
		]);

		$accessToken = trim((string)($token['access_token'] ?? ''));

		if ('' === $accessToken) {
			throw new PairException('Social authentication token response did not include an access token.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$profile = $this->requestProfile($provider, $accessToken);
		$callbackProfile = $this->profileFromCallbackUser($query['user'] ?? null);
		$idTokenProfile = $this->profileFromIdToken((string)($token['id_token'] ?? ''));
		$mergedProfile = array_merge($callbackProfile, $idTokenProfile, $profile);

		return $this->normalizeProfile($provider, $mergedProfile, $token, $state);

	}

	/**
	 * Return summaries for providers that are ready to show in UI.
	 *
	 * @return	list<array{key:string,label:string,icon:string}>
	 */
	public function providers(): array {

		return array_values(array_map(static function(array $provider): array {
			return [
				'key' => $provider['key'],
				'label' => $provider['label'],
				'icon' => $provider['icon'],
			];
		}, $this->providers));

	}

	/**
	 * Return true when the provider exists in the normalized configuration.
	 */
	public function hasProvider(string $providerKey): bool {

		return array_key_exists($this->normalizeProviderKey($providerKey), $this->providers);

	}

	/**
	 * Return one normalized provider configuration or throw a Pair exception.
	 *
	 * @return	array<string, mixed>
	 */
	private function provider(string $providerKey): array {

		$key = $this->normalizeProviderKey($providerKey);

		if (!isset($this->providers[$key])) {
			throw new PairException('Social authentication provider is not configured.', ErrorCodes::MISSING_CONFIGURATION);
		}

		return $this->providers[$key];

	}

	/**
	 * Decode a JWT payload without treating it as an authorization proof.
	 *
	 * @return	array<string, mixed>
	 */
	private function profileFromIdToken(string $idToken): array {

		$parts = explode('.', trim($idToken));

		if (count($parts) < 2) {
			return [];
		}

		$payload = $this->decodeBase64Url($parts[1]);
		$decoded = json_decode($payload, true);

		return is_array($decoded) ? $decoded : [];

	}

	/**
	 * Decode optional callback user data returned by providers such as Apple.
	 *
	 * @return	array<string, mixed>
	 */
	private function profileFromCallbackUser(mixed $userPayload): array {

		if (is_array($userPayload)) {
			return $userPayload;
		}

		if (!is_string($userPayload) or '' === trim($userPayload)) {
			return [];
		}

		$decoded = json_decode($userPayload, true);

		return is_array($decoded) ? $decoded : [];

	}

	/**
	 * Convert provider-specific profile payloads into a stable Pair shape.
	 *
	 * @param	array<string, mixed>	$provider	Provider configuration.
	 * @param	array<string, mixed>	$profile	Provider profile payload.
	 * @param	array<string, mixed>	$token		Token response payload.
	 */
	private function normalizeProfile(array $provider, array $profile, array $token, \stdClass $state): \stdClass {

		$subject = $this->stringValue($profile['sub'] ?? $profile['id'] ?? '');

		if ('' === $subject) {
			throw new PairException('Social authentication profile did not include a stable subject.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$email = $this->normalizeEmail($profile['email'] ?? '');
		$emailVerified = $this->boolValue($profile['email_verified'] ?? $profile['verified_email'] ?? null);
		$name = $this->stringValue($profile['name'] ?? $profile['displayName'] ?? '');
		$givenName = $this->stringValue($profile['given_name'] ?? $profile['givenName'] ?? '');
		$familyName = $this->stringValue($profile['family_name'] ?? $profile['surname'] ?? '');

		if ('' === $givenName and '' === $familyName and is_array($profile['name'] ?? null)) {
			$givenName = $this->stringValue($profile['name']['firstName'] ?? '');
			$familyName = $this->stringValue($profile['name']['lastName'] ?? '');
			$name = trim($givenName . ' ' . $familyName);
		}

		// Facebook nests avatar URLs while OIDC providers usually expose a flat picture value.
		$avatarUrl = $this->stringValue($profile['picture']['data']['url'] ?? $profile['picture'] ?? '');

		return (object)[
			'provider' => $provider['key'],
			'provider_label' => $provider['label'],
			'subject' => $subject,
			'email' => $email,
			'email_verified' => $emailVerified,
			'name' => $name,
			'given_name' => $givenName,
			'family_name' => $familyName,
			'avatar_url' => $avatarUrl,
			'raw_profile' => $profile,
			'token' => $token,
			'state' => $state,
			'context' => is_array($state->context ?? null) ? $state->context : [],
		];

	}

	/**
	 * Exchange an OAuth authorization code for tokens.
	 *
	 * @param	array<string, mixed>	$provider	Provider configuration.
	 * @param	array<string, mixed>	$payload	Token request payload.
	 */
	protected function requestToken(array $provider, array $payload): array {

		if ('apple' === ($provider['key'] ?? '') and '' === trim((string)($payload['client_secret'] ?? ''))) {
			$payload['client_secret'] = $this->appleClientSecret($provider);
		}

		return $this->requestJson('POST', $provider['token_url'], [], $this->withoutEmptyValues($payload), [
			'Content-Type: application/x-www-form-urlencoded',
		]);

	}

	/**
	 * Load the social profile using the OAuth access token.
	 *
	 * @param	array<string, mixed>	$provider	Provider configuration.
	 */
	protected function requestProfile(array $provider, string $accessToken): array {

		if ('' === trim((string)($provider['profile_url'] ?? ''))) {
			return [];
		}

		return $this->requestJson('GET', $provider['profile_url'], $provider['profile_query'], null, [
			'Authorization: Bearer ' . $accessToken,
		]);

	}

	/**
	 * Execute an HTTP request and decode the JSON response.
	 *
	 * @param	array<string, mixed>	$query		Query-string parameters.
	 * @param	array<string, mixed>|null	$payload	Optional form payload.
	 * @param	string[]	$headers	Additional request headers.
	 */
	private function requestJson(string $method, string $url, array $query = [], ?array $payload = null, array $headers = []): array {

		$finalUrl = $this->buildUrl($url, $query);
		$curl = curl_init($finalUrl);

		if (false === $curl) {
			throw new PairException('Unable to initialize social authentication request.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$httpHeaders = array_merge(['Accept: application/json'], $headers);

		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $httpHeaders);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		if (!is_null($payload)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload));
		}

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Social authentication request failed: ' . $error, ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		curl_close($curl);

		$decoded = json_decode((string)$responseBody, true);

		if (!is_array($decoded)) {
			throw new PairException('Social authentication provider returned invalid JSON.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		if ($statusCode >= 400) {
			throw new PairException($this->providerErrorMessage($decoded), ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		return $decoded;

	}

	/**
	 * Store a single-use state object in the native PHP session.
	 *
	 * @param	array<string, mixed>	$context	Flow-specific state restored on callback.
	 */
	private function storeState(string $providerKey, string $redirectUri, array $context): string {

		$this->ensureSessionStarted();

		$stateValue = $this->encodeBase64Url(random_bytes(32));
		$store = (array)Session::get(self::SESSION_KEY);

		$state = new \stdClass();
		$state->provider = $providerKey;
		$state->redirectUri = $redirectUri;
		$state->context = $context;
		$state->expiresAt = time() + self::STATE_TTL;

		$store[$stateValue] = $state;
		Session::set(self::SESSION_KEY, $store);

		return $stateValue;

	}

	/**
	 * Consume and validate a stored social auth state object.
	 */
	private function consumeState(string $stateValue, string $providerKey): \stdClass {

		$this->ensureSessionStarted();

		$store = (array)Session::get(self::SESSION_KEY);
		$state = $store[$stateValue] ?? null;

		// State is single-use to prevent replaying an OAuth callback.
		unset($store[$stateValue]);
		Session::set(self::SESSION_KEY, $store);

		if (!is_object($state)) {
			throw new PairException('Social authentication state was not found.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		if (($state->expiresAt ?? 0) < time()) {
			throw new PairException('Social authentication state expired.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		if (!hash_equals((string)($state->provider ?? ''), $providerKey)) {
			throw new PairException('Social authentication provider mismatch.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		return $state;

	}

	/**
	 * Load provider configuration from environment variables.
	 *
	 * @return	array<string, array<string, mixed>>
	 */
	private function providersFromEnv(): array {

		$providers = [];

		foreach ($this->listValue(Env::get('PAIR_SOCIAL_AUTH_PROVIDERS')) as $providerKey) {
			$key = $this->normalizeProviderKey($providerKey);

			if ('' === $key) {
				continue;
			}

			$providers[$key] = $this->providerFromEnv($key);
		}

		return $providers;

	}

	/**
	 * Build one provider config by merging built-in defaults and Env overrides.
	 *
	 * @return	array<string, mixed>
	 */
	private function providerFromEnv(string $providerKey): array {

		$prefix = 'PAIR_SOCIAL_' . strtoupper(str_replace('-', '_', $providerKey)) . '_';
		$defaults = self::DEFAULT_PROVIDERS[$providerKey] ?? [
			'label' => ucfirst($providerKey),
			'icon' => 'fal fa-user-circle',
			'scopes' => ['openid', 'email', 'profile'],
			'profile_query' => [],
			'authorize_params' => [],
		];

		$authorizeParams = $this->queryValue(Env::get($prefix . 'AUTHORIZE_PARAMS') ?: ($defaults['authorize_params'] ?? []));

		if ('whatsapp' === $providerKey and Env::get($prefix . 'CONFIG_ID')) {
			$authorizeParams['config_id'] = Env::get($prefix . 'CONFIG_ID');
		}

		return array_merge($defaults, [
			'client_id' => Env::get($prefix . 'CLIENT_ID'),
			'client_secret' => Env::get($prefix . 'CLIENT_SECRET'),
			'team_id' => Env::get($prefix . 'TEAM_ID'),
			'key_id' => Env::get($prefix . 'KEY_ID'),
			'private_key' => Env::get($prefix . 'PRIVATE_KEY'),
			'private_key_path' => Env::get($prefix . 'PRIVATE_KEY_PATH'),
			'client_secret_ttl' => Env::get($prefix . 'CLIENT_SECRET_TTL'),
			'label' => Env::get($prefix . 'LABEL') ?: ($defaults['label'] ?? ucfirst($providerKey)),
			'icon' => Env::get($prefix . 'ICON') ?: ($defaults['icon'] ?? 'fal fa-user-circle'),
			'authorize_url' => Env::get($prefix . 'AUTHORIZE_URL') ?: ($defaults['authorize_url'] ?? ''),
			'token_url' => Env::get($prefix . 'TOKEN_URL') ?: ($defaults['token_url'] ?? ''),
			'profile_url' => Env::get($prefix . 'PROFILE_URL') ?: ($defaults['profile_url'] ?? ''),
			'scopes' => $this->listValue(Env::get($prefix . 'SCOPES') ?: ($defaults['scopes'] ?? [])),
			'profile_query' => $this->queryValue(Env::get($prefix . 'PROFILE_QUERY') ?: ($defaults['profile_query'] ?? [])),
			'authorize_params' => $authorizeParams,
		]);

	}

	/**
	 * Normalize and keep only providers that have enough data for OAuth.
	 *
	 * @param	array<string, array<string, mixed>>	$providers	Provider map.
	 * @return	array<string, array<string, mixed>>
	 */
	private function normalizeProviders(array $providers): array {

		$normalized = [];

		foreach ($providers as $providerKey => $provider) {
			$key = $this->normalizeProviderKey((string)($provider['key'] ?? $providerKey));

			if ('' === $key) {
				continue;
			}

			$config = array_merge(self::DEFAULT_PROVIDERS[$key] ?? [], $provider);
			$config['key'] = $key;
			$config['client_id'] = trim((string)($config['client_id'] ?? $config['clientId'] ?? ''));
			$config['client_secret'] = trim((string)($config['client_secret'] ?? $config['clientSecret'] ?? ''));
			$config['team_id'] = trim((string)($config['team_id'] ?? $config['teamId'] ?? ''));
			$config['key_id'] = trim((string)($config['key_id'] ?? $config['keyId'] ?? ''));
			$config['private_key'] = trim((string)($config['private_key'] ?? $config['privateKey'] ?? ''));
			$config['private_key_path'] = trim((string)($config['private_key_path'] ?? $config['privateKeyPath'] ?? ''));
			$config['client_secret_ttl'] = (int)($config['client_secret_ttl'] ?? $config['clientSecretTtl'] ?? 0);
			$config['label'] = trim((string)($config['label'] ?? ucfirst($key)));
			$config['icon'] = trim((string)($config['icon'] ?? 'fal fa-user-circle'));
			$config['authorize_url'] = $this->sanitizeUrl((string)($config['authorize_url'] ?? $config['authorizeUrl'] ?? ''), 'authorize URL');
			$config['token_url'] = $this->sanitizeUrl((string)($config['token_url'] ?? $config['tokenUrl'] ?? ''), 'token URL');
			$config['profile_url'] = $this->sanitizeOptionalUrl((string)($config['profile_url'] ?? $config['profileUrl'] ?? ''), 'profile URL');
			$config['scopes'] = $this->listValue($config['scopes'] ?? []);
			$config['profile_query'] = $this->queryValue($config['profile_query'] ?? $config['profileQuery'] ?? []);
			$config['authorize_params'] = $this->queryValue($config['authorize_params'] ?? $config['authorizeParams'] ?? []);

			if ('' === $config['client_id'] or '' === $config['label']) {
				continue;
			}

			$normalized[$key] = $config;
		}

		return $normalized;

	}

	/**
	 * Convert a CSV, space-separated string, or array into clean list values.
	 *
	 * @return	list<string>
	 */
	private function listValue(mixed $value): array {

		if (is_string($value)) {
			$value = preg_split('/[\s,]+/', $value) ?: [];
		}

		if (!is_array($value)) {
			return [];
		}

		$list = [];

		foreach ($value as $item) {
			$item = trim((string)$item);

			if ('' !== $item) {
				$list[] = $item;
			}
		}

		return $list;

	}

	/**
	 * Convert a query-string or array value into a normalized query parameter map.
	 *
	 * @return	array<string, mixed>
	 */
	private function queryValue(mixed $value): array {

		if (is_string($value)) {
			parse_str($value, $parsed);
			$value = $parsed;
		}

		return is_array($value) ? $value : [];

	}

	/**
	 * Return a compact provider error message without exposing token payloads.
	 */
	private function providerErrorMessage(array $response): string {

		if (isset($response['error_description']) and is_string($response['error_description']) and '' !== trim($response['error_description'])) {
			return trim($response['error_description']);
		}

		if (isset($response['error']['message']) and is_string($response['error']['message']) and '' !== trim($response['error']['message'])) {
			return trim($response['error']['message']);
		}

		if (isset($response['error']) and is_string($response['error']) and '' !== trim($response['error'])) {
			return trim($response['error']);
		}

		return 'Social authentication provider returned an error.';

	}

	/**
	 * Generate the Sign in with Apple client secret JWT when key material is configured.
	 *
	 * @param	array<string, mixed>	$provider	Provider configuration.
	 */
	private function appleClientSecret(array $provider): string {

		$teamId = trim((string)($provider['team_id'] ?? ''));
		$keyId = trim((string)($provider['key_id'] ?? ''));
		$clientId = trim((string)($provider['client_id'] ?? ''));
		$privateKey = $this->applePrivateKey($provider);

		if ('' === $teamId or '' === $keyId or '' === $clientId or '' === $privateKey) {
			throw new PairException('Missing Sign in with Apple client secret or key configuration.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$issuedAt = time();
		$expiresAt = $issuedAt + max(60, min((int)($provider['client_secret_ttl'] ?? 15777000), 15777000));
		$header = ['alg' => 'ES256', 'kid' => $keyId];
		$payload = [
			'iss' => $teamId,
			'iat' => $issuedAt,
			'exp' => $expiresAt,
			'aud' => 'https://appleid.apple.com',
			'sub' => $clientId,
		];

		$unsignedToken = $this->encodeJsonSegment($header) . '.' . $this->encodeJsonSegment($payload);

		// Apple requires ES256 JWT signatures encoded as raw R || S bytes.
		if (!openssl_sign($unsignedToken, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
			throw new PairException('Unable to sign Sign in with Apple client secret.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		return $unsignedToken . '.' . $this->encodeBase64Url($this->derSignatureToJose($derSignature));

	}

	/**
	 * Load the Apple private key from direct configuration or a local key file.
	 *
	 * @param	array<string, mixed>	$provider	Provider configuration.
	 */
	private function applePrivateKey(array $provider): string {

		$privateKey = trim((string)($provider['private_key'] ?? ''));

		if ('' !== $privateKey) {
			return str_replace('\n', "\n", $privateKey);
		}

		$path = trim((string)($provider['private_key_path'] ?? ''));

		if ('' === $path or !is_readable($path)) {
			return '';
		}

		$contents = file_get_contents($path);

		return is_string($contents) ? $contents : '';

	}

	/**
	 * Encode a JWT JSON segment using base64url.
	 *
	 * @param	array<string, mixed>	$payload	JSON payload.
	 */
	private function encodeJsonSegment(array $payload): string {

		$json = json_encode($payload, JSON_UNESCAPED_SLASHES);

		if (!is_string($json)) {
			throw new PairException('Unable to encode social authentication JWT.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		return $this->encodeBase64Url($json);

	}

	/**
	 * Convert an ECDSA DER signature to JOSE raw format.
	 */
	private function derSignatureToJose(string $derSignature): string {

		$offset = 0;

		if (ord($derSignature[$offset++] ?? "\0") !== 0x30) {
			throw new PairException('Invalid Apple client secret signature.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$this->readDerLength($derSignature, $offset);

		if (ord($derSignature[$offset++] ?? "\0") !== 0x02) {
			throw new PairException('Invalid Apple client secret signature.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$r = $this->readDerInteger($derSignature, $offset);

		if (ord($derSignature[$offset++] ?? "\0") !== 0x02) {
			throw new PairException('Invalid Apple client secret signature.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		$s = $this->readDerInteger($derSignature, $offset);

		return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);

	}

	/**
	 * Read a DER length field and advance the offset.
	 */
	private function readDerLength(string $der, int &$offset): int {

		$length = ord($der[$offset++] ?? "\0");

		if ($length < 0x80) {
			return $length;
		}

		$bytes = $length & 0x7f;
		$length = 0;

		for ($i = 0; $i < $bytes; $i++) {
			$length = ($length << 8) + ord($der[$offset++] ?? "\0");
		}

		return $length;

	}

	/**
	 * Read and normalize a DER integer from an ECDSA signature.
	 */
	private function readDerInteger(string $der, int &$offset): string {

		$length = $this->readDerLength($der, $offset);
		$value = substr($der, $offset, $length);
		$offset += $length;

		return ltrim($value, "\0");

	}

	/**
	 * Validate and normalize a provider URL.
	 */
	private function sanitizeUrl(string $url, string $label): string {

		$url = trim($url);

		if ('' === $url or !filter_var($url, FILTER_VALIDATE_URL)) {
			throw new PairException('Social authentication ' . $label . ' is not valid.', ErrorCodes::SOCIAL_AUTH_ERROR);
		}

		return $url;

	}

	/**
	 * Validate and normalize an optional provider URL.
	 */
	private function sanitizeOptionalUrl(string $url, string $label): string {

		$url = trim($url);

		return '' === $url ? '' : $this->sanitizeUrl($url, $label);

	}

	/**
	 * Normalize provider keys for URLs, state and configuration lookup.
	 */
	private function normalizeProviderKey(string $providerKey): string {

		return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($providerKey))) ?: '';

	}

	/**
	 * Normalize scalar strings and discard non-scalars.
	 */
	private function stringValue(mixed $value): string {

		return is_scalar($value) ? trim((string)$value) : '';

	}

	/**
	 * Normalize email values for matching.
	 */
	private function normalizeEmail(mixed $value): string {

		$email = is_scalar($value) ? mb_strtolower(trim((string)$value)) : '';

		return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';

	}

	/**
	 * Convert common provider boolean formats into true or false.
	 */
	private function boolValue(mixed $value): bool {

		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return (int)$value === 1;
		}

		return in_array(strtolower(trim((string)$value)), ['true', 'yes', 'verified'], true);

	}

	/**
	 * Build the final URL with query-string parameters.
	 *
	 * @param	array<string, mixed>	$query	Query-string parameters.
	 */
	private function buildUrl(string $url, array $query = []): string {

		if (!count($query)) {
			return $url;
		}

		return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);

	}

	/**
	 * Remove empty strings and nulls from outgoing provider payloads.
	 *
	 * @param	array<string, mixed>	$values	Payload values.
	 * @return	array<string, mixed>
	 */
	private function withoutEmptyValues(array $values): array {

		return array_filter($values, static fn(mixed $value): bool => !is_null($value) and '' !== $value);

	}

	/**
	 * Ensure an active native session exists.
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
	 * Encode bytes as base64url without padding.
	 */
	private function encodeBase64Url(string $value): string {

		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

	}

	/**
	 * Decode base64url text into raw JSON payload bytes.
	 */
	private function decodeBase64Url(string $value): string {

		$value = strtr(trim($value), '-_', '+/');
		$padding = strlen($value) % 4;

		if ($padding > 0) {
			$value .= str_repeat('=', 4 - $padding);
		}

		$decoded = base64_decode($value, true);

		return false === $decoded ? '' : $decoded;

	}

}
