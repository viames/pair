<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Services\ClaudeClient;
use Pair\Tests\Support\TestCase;

/**
 * Covers ClaudeClient request shaping without calling Anthropic.
 */
class ClaudeClientTest extends TestCase {

	/**
	 * Verify Messages API payloads use safe defaults and preserve requested options.
	 */
	public function testCreateMessageBuildsMessagesPayload(): void {

		$client = new FakeClaudeClient();

		$response = $client->createMessage([
			['role' => 'user', 'content' => 'Summarize this order.'],
		], [
			'system' => 'Answer in Italian.',
			'metadata' => ['order_id' => 'order_123'],
			'max_tokens' => 200,
			'temperature' => null,
		]);

		$request = $client->lastRequest();

		$this->assertSame('msg_test', $response['id']);
		$this->assertSame('POST', $request['method']);
		$this->assertSame('/messages', $request['path']);
		$this->assertSame('claude-test', $request['payload']['model']);
		$this->assertSame(200, $request['payload']['max_tokens']);
		$this->assertSame('Summarize this order.', $request['payload']['messages'][0]['content']);
		$this->assertSame('Answer in Italian.', $request['payload']['system']);
		$this->assertSame(['order_id' => 'order_123'], $request['payload']['metadata']);
		$this->assertArrayNotHasKey('temperature', $request['payload']);

	}

	/**
	 * Verify text convenience responses read Claude text content blocks.
	 */
	public function testCreateTextResponseReturnsContentText(): void {

		$client = new FakeClaudeClient();

		$this->assertSame('Pair response text.', $client->createTextResponse('Say hello.'));

	}

	/**
	 * Verify system messages are lifted outside the Claude message transcript.
	 */
	public function testCreateMessageLiftsSystemMessages(): void {

		$client = new FakeClaudeClient();

		$client->createMessage([
			['role' => 'system', 'content' => 'Keep answers concise.'],
			['role' => 'user', 'content' => 'Hello.'],
			['role' => 'assistant', 'content' => 'Hi.'],
		]);

		$request = $client->lastRequest();

		$this->assertSame('Keep answers concise.', $request['payload']['system']);
		$this->assertCount(2, $request['payload']['messages']);
		$this->assertSame('user', $request['payload']['messages'][0]['role']);
		$this->assertSame('assistant', $request['payload']['messages'][1]['role']);

	}

	/**
	 * Verify raw content block arrays are preserved for multimodal callers.
	 */
	public function testCreateMessagePreservesContentBlocks(): void {

		$client = new FakeClaudeClient();

		$client->createMessage([
			[
				'role' => 'user',
				'content' => [
					[
						'type' => 'text',
						'text' => 'Describe this file.',
					],
					[
						'type' => 'document',
						'source' => ['type' => 'file', 'file_id' => 'file_123'],
					],
				],
			],
		]);

		$content = $client->lastRequest()['payload']['messages'][0]['content'];

		$this->assertSame('Describe this file.', $content[0]['text']);
		$this->assertSame('document', $content[1]['type']);

	}

	/**
	 * Verify fallback text extraction concatenates multiple text blocks.
	 */
	public function testExtractTextReadsContentBlocks(): void {

		$text = ClaudeClient::extractText([
			'content' => [
				['type' => 'text', 'text' => 'Hello '],
				['type' => 'text', 'text' => 'Pair.'],
			],
		]);

		$this->assertSame('Hello Pair.', $text);

	}

	/**
	 * Verify configured API keys can be detected before outbound calls are made.
	 */
	public function testApiKeySetReflectsConfiguredKey(): void {

		$this->assertTrue((new FakeClaudeClient())->apiKeySet());
		$this->assertFalse((new ClaudeClient(''))->apiKeySet());

	}

}

/**
 * Test double that records Claude requests and returns deterministic payloads.
 */
final class FakeClaudeClient extends ClaudeClient {

	/**
	 * Captured Claude requests.
	 *
	 * @var	array<int, array{method: string, path: string, payload: array}>
	 */
	private array $requests = [];

	/**
	 * Build the fake with deterministic model names.
	 */
	public function __construct() {

		parent::__construct('claude-key', 'https://claude.test/v1', 'claude-test');

	}

	/**
	 * Return the most recent captured request.
	 *
	 * @return	array{method: string, path: string, payload: array}
	 */
	public function lastRequest(): array {

		return $this->requests[count($this->requests) - 1] ?? ['method' => '', 'path' => '', 'payload' => []];

	}

	/**
	 * Capture request details and return deterministic Claude-shaped responses.
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$this->requests[] = [
			'method' => $method,
			'path' => $path,
			'payload' => $payload,
		];

		if ('/messages' === $path) {
			return [
				'id' => 'msg_test',
				'type' => 'message',
				'role' => 'assistant',
				'content' => [
					[
						'type' => 'text',
						'text' => 'Pair response text.',
					],
				],
			];
		}

		return [];

	}

}
