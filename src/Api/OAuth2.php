<?php

declare(strict_types=1);

namespace Pair\Api;

use Pair\Http\AuthorizationHeader;
use Pair\Http\JsonResponse;

/** Protocol helpers for OAuth 2.0 client credentials; independent of application storage and scopes. */
final class OAuth2 {
	/** Decode an RFC 6749 form component without accepting incomplete escapes or invalid UTF-8. */
	public static function decode(string $value): string {
		if (preg_match('/%(?![0-9A-Fa-f]{2})/', $value)) {
			throw new \InvalidArgumentException('Invalid form encoding.');
		}
		$value = urldecode($value);
		if (!preg_match('//u', $value)) {
			throw new \InvalidArgumentException('Invalid UTF-8 form value.');
		}
		return $value;
	}

	/** Parse a token request, rejecting repeated names and treating empty values as omitted. */
	public static function parameters(string $body): array {
		$data = [];
		$seen = [];
		foreach (explode('&', $body) as $part) {
			if ('' === $part) {
				continue;
			}
			[$key, $value] = array_pad(explode('=', $part, 2), 2, '');
			$key = self::decode($key);
			$value = self::decode($value);
			if (array_key_exists($key, $seen)) {
				throw new \InvalidArgumentException('Duplicate OAuth parameter.');
			}
			$seen[$key] = true;
			if ('' !== $value) {
				$data[$key] = $value;
			}
		}
		// Unknown parameters remain available for extensions; callers must ignore unsupported names.
		return $data;
	}

	/** Parse client_secret_basic, decoding each form-encoded component exactly once. */
	public static function clientCredentials(?string $header): ?array {
		$credentials = AuthorizationHeader::basicCredentials($header);
		if (null === $credentials) {
			return null;
		}
		try {
			return ['id'=>self::decode($credentials['id']), 'secret'=>self::decode($credentials['secret'])];
		} catch (\InvalidArgumentException) {
			return null;
		}
	}

	/** Build only registered token-endpoint errors, with the ASCII character set required by RFC 6749. */
	public static function errorPayload(string $code, ?string $description = null): array {
		$messages = [
			'invalid_request'=>'The token request is missing a parameter or is malformed.',
			'invalid_client'=>'Client authentication failed.',
			'invalid_grant'=>'The authorization grant is invalid.',
			'unauthorized_client'=>'Client is not authorized for this grant.',
			'unsupported_grant_type'=>'Grant type is not supported.',
			'invalid_scope'=>'Requested scope is not available for this client.',
		];
		if (!isset($messages[$code])) {
			throw new \InvalidArgumentException('Unsupported OAuth token error.');
		}
		$description ??= $messages[$code];
		if (preg_match('/[^\x20-\x21\x23-\x5b\x5d-\x7e]/', $description)) {
			throw new \InvalidArgumentException('OAuth error_description must use the RFC 6749 ASCII character set.');
		}
		return ['error'=>$code, 'error_description'=>$description];
	}

	/** Build a token response without a null scope or refresh token. */
	public static function tokenPayload(string $token, int $lifetime, ?string $scope = null): array {
		if ('' === $token or $lifetime < 1 or !preg_match('~^[A-Za-z0-9._\~+/-]+=*$~D', $token)) {
			throw new \InvalidArgumentException('Invalid access token response.');
		}
		$data = ['access_token'=>$token, 'token_type'=>'Bearer', 'expires_in'=>$lifetime];
		if (null !== $scope) {
			if (!preg_match('/^[\x21\x23-\x5b\x5d-\x7e]+(?: [\x21\x23-\x5b\x5d-\x7e]+)*$/D', $scope)) {
				throw new \InvalidArgumentException('Invalid OAuth scope.');
			}
			$data['scope'] = $scope;
		}
		return $data;
	}

	/** Protect mandatory token headers even when additional header names use different casing. */
	private static function headers(array $extra, bool $challenge = false): array {
		$headers = ['Cache-Control'=>'no-store', 'Pragma'=>'no-cache', 'Content-Type'=>'application/json'];
		if ($challenge) {
			$headers['WWW-Authenticate'] = 'Basic realm="oauth2"';
		}
		foreach ($extra as $name=>$value) {
			if (!in_array(strtolower($name), ['cache-control','pragma','content-type','www-authenticate'], true)) {
				$headers[$name] = $value;
			}
		}
		return $headers;
	}

	/** Return an explicit token response with mandatory cache prevention. */
	public static function tokenResponse(string $token, int $lifetime, ?string $scope = null, array $headers = []): JsonResponse {
		return new JsonResponse(self::tokenPayload($token, $lifetime, $scope), 200, self::headers($headers));
	}

	/** Return a token error with Basic challenge for client authentication failures. */
	public static function errorResponse(string $code, ?string $description = null, array $headers = []): JsonResponse {
		return new JsonResponse(self::errorPayload($code, $description), 'invalid_client' === $code ? 401 : 400,
			self::headers($headers, 'invalid_client' === $code));
	}
}
