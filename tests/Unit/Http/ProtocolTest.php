<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Api\OAuth2;
use Pair\Api\Request;
use Pair\Http\AuthorizationHeader;
use Pair\Http\EntityTag;
use Pair\Http\MediaType;
use Pair\Tests\Support\TestCase;

/** Deterministic protocol cases, with no database or network access. */
final class ProtocolTest extends TestCase {
	/** Check range precedence, media parameters, quoted delimiters, and exact weights. */
	public function testMediaRanges(): void {
		foreach ([
			[null, true], ['', false], ['application/json', true], ['APPLICATION/JSON', true],
			['text/html', false], ['application/*;q=0.1', true], ['*/*;q=1, application/json;q=0', false],
			['application/json;profile=example;q=0, application/json;q=1', true],
			['application/json;profile="comma,semicolon;value", */*;q=0.1', true],
			['application/json;profile=example', false], ['application/json;q=0.001', true],
			['application/json;q=0, application/json;q=1', true], [',, application/json,', true],
		] as [$accept, $expected]) {
			$this->assertSame($expected, MediaType::accepts($accept), (string)$accept);
		}
		$this->assertSame(['application/json', ['profile'=>'A,B;C'], 500], MediaType::parse('Application/JSON;profile="A,B;C";q=0.5', true));
		$this->assertTrue(MediaType::accepts('text/plain;format=flowed;q=0.1, text/plain;q=0', 'text/plain;format=flowed'));
		foreach (['application/json;q=1.001','application/json;q=.5','application/json;q="0.5"','application/json;q=0.0001','application/json;q=1;q=0','application/json;profile="broken','application/json;profile=a;PROFILE=b'] as $bad) {
			try { MediaType::accepts($bad); $this->fail('Malformed header accepted: '.$bad); }
			catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
		}
	}

	/** Check strong/weak comparison, wildcard existence, lists, and precondition ordering. */
	public function testPreconditions(): void {
		foreach ([
			['GET','"a"',null,null,true,200],
			['OPTIONS','"a"','"b"','*',true,200], ['TRACE','"a"','"b"','*',true,200], ['CONNECT','"a"','"b"','*',true,200], ['GET','"a"','"a"',null,true,200],
			['GET','"a"','"b"',null,true,412], ['GET','"a"','W/"a"',null,true,412],
			['GET','"a"','"b", "a"',null,true,200], ['GET','"a"','*',null,true,200],
			['GET','"a"',null,'W/"a"',true,304], ['HEAD','"a"',null,'*',true,304],
			['POST','"a"',null,'"a"',true,412], ['GET','"a"','"b"','"a"',true,412],
			['POST',null,'*',null,false,412], ['POST',null,null,'*',false,200],
			['POST',null,null,'*',true,412], ['GET','"a,b"','"a,b"',null,true,200],
			['GET','"a\\"','"a\\"',null,true,200], ['GET',null,null,'"a"',true,200],
			['GET','"a"',',, "a",',null,true,200],
		] as [$method,$etag,$match,$none,$exists,$expected]) {
			$this->assertSame($expected, EntityTag::evaluate($method,$etag,$match,$none,$exists));
		}
		foreach (['*, "a"','w/"a"','"a" "b"','"unclosed','"a";x=1'] as $bad) {
			try { EntityTag::parse($bad); $this->fail('Malformed entity-tag accepted.'); }
			catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
		}
	}

	/** Preserve credential octets and keep OAuth form decoding separate from generic Basic. */
	public function testCredentials(): void {
		$basic = 'Basic '.base64_encode(' client : secret ');
		$this->assertSame(['id'=>' client ', 'secret'=>' secret '], AuthorizationHeader::basicCredentials($basic));
		$this->assertSame(['id'=>'client:id', 'secret'=>' +:%20 '], OAuth2::clientCredentials('Basic '.base64_encode('client%3Aid:%20%2B%3A%2520%20')));
		foreach (['Bearer a,b','Bearer a=b','Bearer a"b',"Bearer\ta",'Bearer a b'] as $bad) {
			$this->assertNull(AuthorizationHeader::bearerToken($bad));
		}
		$this->assertSame('a._~+/==', AuthorizationHeader::bearerToken('bEaReR  a._~+/=='));
		$this->assertNull(OAuth2::clientCredentials('Basic '.base64_encode('client:%GG')));
		$_SERVER['REQUEST_METHOD'] = 'get';
		$this->assertSame('get', (new Request())->method());
		$_SERVER['CONTENT_TYPE'] = 'notapplication/json';
		$this->assertFalse((new Request())->isJson());
		$_SERVER['CONTENT_TYPE'] = 'Application/JSON; charset="utf-8"';
		$this->assertTrue((new Request())->isJson());
	}

	/** Enforce token request and error syntax without prescribing application scope policy. */
	public function testOAuthPayloads(): void {
		$this->assertSame(['grant_type'=>'client_credentials','extension'=>'value'], OAuth2::parameters('grant_type=client_credentials&scope=&extension=value'));
		foreach (['a=1&a=2','scope=&scope=','a=%GG','a=%FF'] as $bad) {
			try { OAuth2::parameters($bad); $this->fail('Invalid form accepted.'); }
			catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
		}
		foreach (['invalid_request','invalid_client','invalid_grant','unauthorized_client','unsupported_grant_type','invalid_scope'] as $code) {
			$payload = OAuth2::errorPayload($code);
			$this->assertSame($code, $payload['error']);
			$this->assertDoesNotMatchRegularExpression('/[^\x20-\x21\x23-\x5b\x5d-\x7e]/', $payload['error_description']);
		}
		$this->assertArrayNotHasKey('scope', OAuth2::tokenPayload('valid-token', 3600));
		foreach ([['invalid_request','È errato'],['invalid_client','"quoted"'],['temporarily_unavailable',null]] as [$code,$description]) {
			try { OAuth2::errorPayload($code,$description); $this->fail('Invalid OAuth error accepted.'); }
			catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
		}
	}

	/** Additional headers must never override mandatory OAuth cache or authentication headers. */
	public function testTokenHeaders(): void {
		$response = OAuth2::tokenResponse('valid-token',3600,null,['cache-control'=>'public','PRAGMA'=>'cache','content-type'=>'text/html']);
		$headers = (new \ReflectionProperty($response,'headers'))->getValue($response);
		$this->assertSame(['Cache-Control'=>'no-store','Pragma'=>'no-cache','Content-Type'=>'application/json'], $headers);
		$error = OAuth2::errorResponse('invalid_client',null,['www-authenticate'=>'None']);
		$headers = (new \ReflectionProperty($error,'headers'))->getValue($error);
		$this->assertSame('Basic realm="oauth2"', $headers['WWW-Authenticate']);
	}

	/** Verify actual output for scalar/empty JSON, HEAD, forbidden bodies, and serialization errors. */
	public function testResponseBodies(): void {
		$result = $this->runPhpSnippet(<<<'PHP'
$cases = [[[],200,false,'[]'],[false,200,false,'false'],[0,200,false,'0'],[null,201,false,'null'],[['x'=>1],204,false,''],[['x'=>1],205,false,''],[['x'=>1],304,false,''],[['x'=>1],200,true,'']];
foreach ($cases as [$payload,$status,$head,$expected]) {
 ob_start(); (new Pair\Http\JsonResponse($payload,$status,[], $head))->send(); $body=ob_get_clean();
 if ($body !== $expected || http_response_code() !== $status) { throw new RuntimeException('Response semantics'); }
}
http_response_code(418);
try { (new Pair\Http\JsonResponse(NAN))->send(); throw new RuntimeException('Invalid JSON emitted'); }
catch (JsonException) { if (http_response_code() !== 418) { throw new RuntimeException('Headers emitted before JSON validation'); } }
print 'OK';
PHP);
		$this->assertSame(0, $result['exitCode'], $result['stderr']);
		$this->assertSame('OK', $result['stdout']);
	}
}
