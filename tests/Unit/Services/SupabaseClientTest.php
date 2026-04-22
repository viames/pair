<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Exceptions\PairException;
use Pair\Services\SupabaseClient;
use Pair\Tests\Support\TestCase;

/**
 * Covers the optional Supabase bridge without performing network calls.
 */
class SupabaseClientTest extends TestCase {

	/**
	 * Define the minimal routing constant needed by PairException logging in isolated tests.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

	}

	/**
	 * Verify storage uploads shape the expected object request.
	 */
	public function testStorageUploadShapesObjectRequest(): void {

		$client = new FakeSupabaseClient();

		$response = $client->storageUpload('avatars', 'users/1/avatar.png', 'image-bytes', 'image/png', [
			'cacheControl' => '3600',
			'upsert' => true,
		]);
		$request = $client->lastJsonRequest();

		$this->assertSame(['ok' => true], $response);
		$this->assertSame('POST', $request['method']);
		$this->assertSame('/storage/v1/object/avatars/users/1/avatar.png', $request['path']);
		$this->assertSame('image-bytes', $request['payload']);
		$this->assertSame('image/png', $request['headers']['Content-Type']);
		$this->assertSame('3600', $request['headers']['Cache-Control']);
		$this->assertSame('true', $request['headers']['x-upsert']);
		$this->assertTrue($request['options']['serviceRole']);

	}

	/**
	 * Verify storage list and signed URL helpers use Storage API payloads.
	 */
	public function testStorageListAndSignedUrlShapeRequests(): void {

		$client = new FakeSupabaseClient();

		$client->storageList('assets', 'public', [
			'limit' => 10,
			'offset' => 20,
			'sortBy' => ['column' => 'name', 'order' => 'asc'],
		]);
		$listRequest = $client->lastJsonRequest();

		$client->storageSignedUrl('assets', 'public/file.pdf', 120, [
			'download' => true,
		]);
		$signedRequest = $client->lastJsonRequest();

		$this->assertSame('/storage/v1/object/list/assets', $listRequest['path']);
		$this->assertSame('public', $listRequest['payload']['prefix']);
		$this->assertSame(10, $listRequest['payload']['limit']);
		$this->assertSame(20, $listRequest['payload']['offset']);
		$this->assertSame(['column' => 'name', 'order' => 'asc'], $listRequest['payload']['sortBy']);
		$this->assertSame('/storage/v1/object/sign/assets/public/file.pdf', $signedRequest['path']);
		$this->assertSame(120, $signedRequest['payload']['expiresIn']);
		$this->assertTrue($signedRequest['payload']['download']);

	}

	/**
	 * Verify binary downloads and public URLs preserve storage path semantics.
	 */
	public function testStorageDownloadAndPublicUrl(): void {

		$client = new FakeSupabaseClient();

		$body = $client->storageDownload('assets', 'public/report final.pdf');
		$request = $client->lastBinaryRequest();
		$url = $client->storagePublicUrl('assets', 'public/report final.pdf', [
			'download' => 'report.pdf',
		]);

		$this->assertSame('file-body', $body);
		$this->assertSame('GET', $request['method']);
		$this->assertSame('/storage/v1/object/assets/public/report%20final.pdf', $request['path']);
		$this->assertSame('https://pair-test.supabase.co/storage/v1/object/public/assets/public/report%20final.pdf?download=report.pdf', $url);

	}

	/**
	 * Verify Auth bridge methods choose user JWT and service role modes explicitly.
	 */
	public function testAuthBridgeShapesUserAndAdminRequests(): void {

		$client = new FakeSupabaseClient();

		$client->authUser('user-access-token');
		$userRequest = $client->lastJsonRequest();

		$client->authAdminGetUser('user-123');
		$adminRequest = $client->lastJsonRequest();

		$this->assertSame('/auth/v1/user', $userRequest['path']);
		$this->assertSame('user-access-token', $userRequest['options']['bearerToken']);
		$this->assertFalse($userRequest['options']['serviceRole']);
		$this->assertSame('/auth/v1/admin/users/user-123', $adminRequest['path']);
		$this->assertTrue($adminRequest['options']['serviceRole']);

	}

	/**
	 * Verify PostgREST select and RPC requests preserve schema and auth options.
	 */
	public function testPostgrestSelectAndRpcShapeRequests(): void {

		$client = new FakeSupabaseClient();

		$client->restSelect('profiles', [
			'id' => 'eq.123',
		], [
			'select' => 'id,email',
			'schema' => 'public',
			'bearerToken' => 'user-token',
		]);
		$selectRequest = $client->lastJsonRequest();

		$client->rpc('sync_profile', [
			'user_id' => '123',
		], [
			'prefer' => 'return=representation',
			'serviceRole' => true,
		]);
		$rpcRequest = $client->lastJsonRequest();

		$this->assertSame('GET', $selectRequest['method']);
		$this->assertSame('/rest/v1/profiles', $selectRequest['path']);
		$this->assertSame(['id' => 'eq.123', 'select' => 'id,email'], $selectRequest['options']['query']);
		$this->assertSame('public', $selectRequest['options']['schema']);
		$this->assertSame('user-token', $selectRequest['options']['bearerToken']);
		$this->assertSame('POST', $rpcRequest['method']);
		$this->assertSame('/rest/v1/rpc/sync_profile', $rpcRequest['path']);
		$this->assertSame(['user_id' => '123'], $rpcRequest['payload']);
		$this->assertSame('return=representation', $rpcRequest['headers']['Prefer']);
		$this->assertTrue($rpcRequest['options']['serviceRole']);

	}

	/**
	 * Verify Realtime URL creation uses WebSocket protocol and configured key.
	 */
	public function testRealtimeWebSocketUrlUsesConfiguredKey(): void {

		$client = new FakeSupabaseClient();
		$url = $client->realtimeWebSocketUrl([
			'log_level' => 'info',
			'vsn' => '2.0.0',
		]);
		$parts = parse_url($url);
		parse_str((string)($parts['query'] ?? ''), $query);

		$this->assertSame('wss', $parts['scheme']);
		$this->assertSame('pair-test.supabase.co', $parts['host']);
		$this->assertSame('/realtime/v1/websocket', $parts['path']);
		$this->assertSame('anon-key', $query['apikey']);
		$this->assertSame('2.0.0', $query['vsn']);
		$this->assertSame('info', $query['log_level']);

	}

	/**
	 * Verify admin-only methods fail before any HTTP call when service role is absent.
	 */
	public function testAdminRequestRequiresServiceRoleKey(): void {

		$client = new SupabaseClient('https://pair-test.supabase.co', 'anon-key', '');

		$this->expectException(PairException::class);
		$this->expectExceptionMessage('Missing Supabase service role key.');

		$client->authAdminGetUser('user-123');

	}

}

/**
 * Fake Supabase client that captures requests instead of sending them.
 */
class FakeSupabaseClient extends SupabaseClient {

	/**
	 * Captured binary requests.
	 *
	 * @var	list<array<string, mixed>>
	 */
	private array $binaryRequests = [];

	/**
	 * Captured JSON requests.
	 *
	 * @var	list<array<string, mixed>>
	 */
	private array $jsonRequests = [];

	/**
	 * Build a fake client with deterministic configuration.
	 */
	public function __construct() {

		parent::__construct('https://pair-test.supabase.co', 'anon-key', 'service-key');

	}

	/**
	 * Return the most recent binary request.
	 *
	 * @return	array<string, mixed>
	 */
	public function lastBinaryRequest(): array {

		return $this->binaryRequests[array_key_last($this->binaryRequests)] ?? [];

	}

	/**
	 * Return the most recent JSON request.
	 *
	 * @return	array<string, mixed>
	 */
	public function lastJsonRequest(): array {

		return $this->jsonRequests[array_key_last($this->jsonRequests)] ?? [];

	}

	/**
	 * Capture a binary request and return a deterministic body.
	 */
	protected function requestBinary(string $method, string $path, array $headers = [], array $options = []): string {

		$this->binaryRequests[] = [
			'method' => $method,
			'path' => $path,
			'headers' => $headers,
			'options' => $options,
		];

		return 'file-body';

	}

	/**
	 * Capture a JSON request and return a deterministic response.
	 */
	protected function requestJson(string $method, string $path, array|string|null $payload = null, array $headers = [], array $options = []): array {

		$this->jsonRequests[] = [
			'method' => $method,
			'path' => $path,
			'payload' => $payload,
			'headers' => $headers,
			'options' => $options,
		];

		return ['ok' => true];

	}

}
