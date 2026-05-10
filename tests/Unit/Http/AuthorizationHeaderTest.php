<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Http\AuthorizationHeader;
use Pair\Tests\Support\TestCase;

/**
 * Covers shared Authorization header parsing used by API and OAuth bootstrap code.
 */
class AuthorizationHeaderTest extends TestCase {

	/**
	 * Verify server fallback keys resolve the same Authorization header value.
	 */
	public function testFromServerReadsCommonAuthorizationKeys(): void {

		$this->assertSame('Bearer direct', AuthorizationHeader::fromServer([
			'Authorization' => ' Bearer direct ',
		]));

		$this->assertSame('Bearer http', AuthorizationHeader::fromServer([
			'HTTP_AUTHORIZATION' => 'Bearer http',
		]));

		$this->assertSame('Bearer redirect', AuthorizationHeader::fromServer([
			'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect',
		]));

	}

	/**
	 * Verify bearer parsing accepts case-insensitive schemes and rejects malformed values.
	 */
	public function testBearerTokenParsesCaseInsensitiveScheme(): void {

		$this->assertSame('token-123', AuthorizationHeader::bearerToken(' bEaReR token-123 '));
		$this->assertNull(AuthorizationHeader::bearerToken('Bearer token with-spaces'));
		$this->assertNull(AuthorizationHeader::bearerToken('Basic abc'));

	}

	/**
	 * Verify Basic credential parsing trims values and rejects incomplete pairs.
	 */
	public function testBasicCredentialsParsesValidHeader(): void {

		$header = 'Basic ' . base64_encode(' client-id : secret-value ');

		$this->assertSame([
			'id'		=> 'client-id',
			'secret'	=> 'secret-value',
		], AuthorizationHeader::basicCredentials($header));

		$this->assertNull(AuthorizationHeader::basicCredentials('Basic ' . base64_encode('client-only')));
		$this->assertNull(AuthorizationHeader::basicCredentials('Bearer token-123'));

	}

}
