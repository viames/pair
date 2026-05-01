<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Services\SocialAuth;
use Pair\Tests\Support\TestCase;

/**
 * Covers social OAuth request shaping and callback normalization without calling providers.
 */
class SocialAuthTest extends TestCase {

	/**
	 * Start a clean native session for SocialAuth state storage.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		if (PHP_SESSION_ACTIVE !== session_status()) {
			session_start();
		}

		$_SESSION = [];

	}

	/**
	 * Close the native session after each social auth test.
	 */
	protected function tearDown(): void {

		if (PHP_SESSION_ACTIVE === session_status()) {
			$_SESSION = [];
			session_write_close();
		}

		parent::tearDown();

	}

	/**
	 * Verify configured built-in providers are exposed as UI-safe summaries.
	 */
	public function testProvidersFromEnvExposeConfiguredBuiltIns(): void {

		$_ENV['PAIR_SOCIAL_AUTH_PROVIDERS'] = 'google, apple, microsoft, whatsapp';
		$_ENV['PAIR_SOCIAL_GOOGLE_CLIENT_ID'] = 'google-client';
		$_ENV['PAIR_SOCIAL_GOOGLE_CLIENT_SECRET'] = 'google-secret';
		$_ENV['PAIR_SOCIAL_APPLE_CLIENT_ID'] = 'apple-client';
		$_ENV['PAIR_SOCIAL_APPLE_CLIENT_SECRET'] = 'apple-secret';
		$_ENV['PAIR_SOCIAL_MICROSOFT_CLIENT_ID'] = 'microsoft-client';
		$_ENV['PAIR_SOCIAL_MICROSOFT_CLIENT_SECRET'] = 'microsoft-secret';
		$_ENV['PAIR_SOCIAL_WHATSAPP_CLIENT_ID'] = 'whatsapp-client';
		$_ENV['PAIR_SOCIAL_WHATSAPP_CLIENT_SECRET'] = 'whatsapp-secret';
		$_ENV['PAIR_SOCIAL_WHATSAPP_CONFIG_ID'] = 'whatsapp-config';

		$providers = (new SocialAuth())->providers();

		$this->assertSame([
			['key' => 'google', 'label' => 'Google', 'icon' => 'fab fa-google'],
			['key' => 'apple', 'label' => 'Apple', 'icon' => 'fab fa-apple'],
			['key' => 'microsoft', 'label' => 'Microsoft', 'icon' => 'fab fa-microsoft'],
			['key' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'fab fa-whatsapp'],
		], $providers);

	}

	/**
	 * Verify Apple can normalize profile claims from the ID token and form_post user payload.
	 */
	public function testAppleStyleCallbackCanUseIdTokenAndFormPostUserPayload(): void {

		$auth = new FakeSocialAuth([
			'apple' => [
				'label' => 'Apple',
				'icon' => 'fab fa-apple',
				'client_id' => 'apple-client',
				'client_secret' => 'apple-secret',
				'authorize_url' => 'https://appleid.apple.com/auth/authorize',
				'token_url' => 'https://appleid.apple.com/auth/token',
				'profile_url' => '',
				'scopes' => ['name', 'email'],
				'authorize_params' => ['response_mode' => 'form_post'],
			],
		]);

		$redirectUri = 'https://app.example.test/auth/social/apple/callback';
		$beginUrl = $auth->begin('apple', $redirectUri, ['flow' => 'register']);
		$query = $this->queryFromUrl($beginUrl);
		$auth->setNextTokenResponse([
			'access_token' => 'apple-access-token',
			'id_token' => $this->fakeJwtPayload([
				'sub' => 'apple-subject',
				'email' => 'apple@example.test',
				'email_verified' => 'true',
			]),
		]);

		$profile = $auth->complete('apple', [
			'code' => 'apple-code',
			'state' => $query['state'],
			'user' => json_encode([
				'name' => [
					'firstName' => 'Ada',
					'lastName' => 'Lovelace',
				],
			]),
		], $redirectUri);

		$this->assertSame('apple', $profile->provider);
		$this->assertSame('apple-subject', $profile->subject);
		$this->assertSame('apple@example.test', $profile->email);
		$this->assertTrue($profile->email_verified);
		$this->assertSame('Ada Lovelace', $profile->name);
		$this->assertSame('Ada', $profile->given_name);
		$this->assertSame('Lovelace', $profile->family_name);

	}

	/**
	 * Verify begin() builds an OAuth authorization URL with state, scope and redirect URI.
	 */
	public function testBeginBuildsAuthorizationUrl(): void {

		$auth = new SocialAuth($this->providerConfig());
		$url = $auth->begin('example', 'https://app.example.test/auth/social/example/callback', ['flow' => 'register']);
		$query = $this->queryFromUrl($url);

		$this->assertStringStartsWith('https://provider.example.test/authorize?', $url);
		$this->assertSame('code', $query['response_type']);
		$this->assertSame('example-client', $query['client_id']);
		$this->assertSame('https://app.example.test/auth/social/example/callback', $query['redirect_uri']);
		$this->assertSame('openid email profile', $query['scope']);
		$this->assertNotEmpty($query['state']);

	}

	/**
	 * Verify complete() exchanges the code, consumes state and normalizes the provider profile.
	 */
	public function testCompleteReturnsNormalizedProfile(): void {

		$auth = new FakeSocialAuth($this->providerConfig());
		$redirectUri = 'https://app.example.test/auth/social/example/callback';
		$beginUrl = $auth->begin('example', $redirectUri, ['flow' => 'login', 'timezone' => 'Europe/Rome']);
		$query = $this->queryFromUrl($beginUrl);

		$profile = $auth->complete('example', [
			'code' => 'oauth-code',
			'state' => $query['state'],
		], $redirectUri);

		$this->assertSame('example', $profile->provider);
		$this->assertSame('provider-user-1', $profile->subject);
		$this->assertSame('alice@example.test', $profile->email);
		$this->assertTrue($profile->email_verified);
		$this->assertSame('Alice Example', $profile->name);
		$this->assertSame('login', $profile->context['flow']);
		$this->assertSame('oauth-code', $auth->lastTokenRequest()['code']);
		$this->assertSame('access-token', $auth->lastProfileToken());

	}

	/**
	 * Return a deterministic provider configuration for tests.
	 *
	 * @return	array<string, array<string, mixed>>
	 */
	private function providerConfig(): array {

		return [
			'example' => [
				'label' => 'Example',
				'icon' => 'fal fa-user-circle',
				'client_id' => 'example-client',
				'client_secret' => 'example-secret',
				'authorize_url' => 'https://provider.example.test/authorize',
				'token_url' => 'https://provider.example.test/token',
				'profile_url' => 'https://provider.example.test/userinfo',
				'scopes' => ['openid', 'email', 'profile'],
			],
		];

	}

	/**
	 * Parse the query string of an absolute URL.
	 *
	 * @return	array<string, string>
	 */
	private function queryFromUrl(string $url): array {

		$queryString = (string)parse_url($url, PHP_URL_QUERY);
		parse_str($queryString, $query);

		return array_map(static fn(mixed $value): string => (string)$value, $query);

	}

	/**
	 * Build an unsigned JWT-like string for payload decoding tests.
	 *
	 * @param	array<string, mixed>	$payload	JWT payload.
	 */
	private function fakeJwtPayload(array $payload): string {

		return implode('.', [
			$this->base64Url(json_encode(['alg' => 'none'], JSON_UNESCAPED_SLASHES) ?: '{}'),
			$this->base64Url(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}'),
			'',
		]);

	}

	/**
	 * Encode test data as base64url without padding.
	 */
	private function base64Url(string $value): string {

		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

	}

}

/**
 * Test double that avoids network calls and captures OAuth request data.
 */
final class FakeSocialAuth extends SocialAuth {

	/**
	 * Last captured access token.
	 */
	private string $lastProfileToken = '';

	/**
	 * Last captured token request payload.
	 *
	 * @var	array<string, mixed>
	 */
	private array $lastTokenRequest = [];

	/**
	 * Next fake token response.
	 *
	 * @var	array<string, mixed>
	 */
	private array $nextTokenResponse = ['access_token' => 'access-token'];

	/**
	 * Return the last token passed to the fake profile request.
	 */
	public function lastProfileToken(): string {

		return $this->lastProfileToken;

	}

	/**
	 * Return the last token request payload.
	 *
	 * @return	array<string, mixed>
	 */
	public function lastTokenRequest(): array {

		return $this->lastTokenRequest;

	}

	/**
	 * Configure the next fake OAuth token response.
	 *
	 * @param	array<string, mixed>	$response	Token response.
	 */
	public function setNextTokenResponse(array $response): void {

		$this->nextTokenResponse = $response;

	}

	/**
	 * Capture the token payload and return a deterministic access token.
	 */
	protected function requestToken(array $provider, array $payload): array {

		$this->lastTokenRequest = $payload;

		return $this->nextTokenResponse;

	}

	/**
	 * Capture the access token and return a deterministic OIDC-style profile.
	 */
	protected function requestProfile(array $provider, string $accessToken): array {

		$this->lastProfileToken = $accessToken;

		if ('' === trim((string)($provider['profile_url'] ?? ''))) {
			return [];
		}

		return [
			'sub' => 'provider-user-1',
			'email' => 'alice@example.test',
			'email_verified' => true,
			'name' => 'Alice Example',
			'given_name' => 'Alice',
			'family_name' => 'Example',
			'picture' => 'https://cdn.example.test/alice.jpg',
		];

	}

}
