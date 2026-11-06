<?php

declare(strict_types=1);

namespace Pair\Http;

/**
 * Shared Authorization header parsing helpers for API and OAuth bootstrap code.
 */
final class AuthorizationHeader {

	/**
	 * Return the raw Authorization header from PHP globals, including common server fallbacks.
	 */
	public static function fromGlobals(): ?string {

		$header = self::fromServer($_SERVER ?? []);

		if (!is_null($header)) {
			return $header;
		}

		if (!function_exists('apache_request_headers')) {
			return null;
		}

		// Some SAPIs expose Authorization only through apache_request_headers().
		foreach (apache_request_headers() as $name => $value) {
			if ('authorization' == strtolower((string)$name)) {
				$header = trim((string)$value);
				return strlen($header) ? $header : null;
			}
		}

		return null;

	}

	/**
	 * Return the raw Authorization header from a server array.
	 *
	 * @param	array<string, mixed>	$server	Server variables such as $_SERVER.
	 */
	public static function fromServer(array $server): ?string {

		foreach (['Authorization', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {

			if (!isset($server[$key])) {
				continue;
			}

			$header = trim((string)$server[$key]);

			if (strlen($header)) {
				return $header;
			}

		}

		return null;

	}

	/**
	 * Extract an OAuth2 Bearer token from an Authorization header value.
	 */
	public static function bearerToken(?string $header): ?string {

		if (!$header or !preg_match('~^Bearer +([A-Za-z0-9._\~+/-]+=*)$~iD', trim($header, " \t"), $matches)) {
			return null;
		}

		return $matches[1];

	}

	/**
	 * Extract Basic credentials from an Authorization header value.
	 *
	 * @return	array{id: string, secret: string}|null
	 */
	public static function basicCredentials(?string $header): ?array {

		if (!$header or !preg_match('~^Basic +([A-Za-z0-9+/]+={0,2})$~iD', trim($header, " \t"), $matches)) {
			return null;
		}

		$decoded = base64_decode($matches[1], true);

		if (false === $decoded or !str_contains($decoded, ':') or rtrim(base64_encode($decoded), '=') !== rtrim($matches[1], '=')) {
			return null;
		}

		list($clientId, $clientSecret) = explode(':', $decoded, 2);
		// Credentials are octet strings: whitespace inside either component is significant.

		if ('' === $clientId or '' === $clientSecret) {
			return null;
		}

		return [
			'id'		=> $clientId,
			'secret'	=> $clientSecret,
		];

	}

}
