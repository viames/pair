<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Services\OpenAIClient;
use Pair\Tests\Support\TestCase;

/**
 * Covers OpenAIClient request shaping without calling OpenAI.
 */
class OpenAIClientTest extends TestCase {

	/**
	 * Verify Responses API payloads use safe defaults and preserve requested options.
	 */
	public function testCreateResponseBuildsResponsesPayload(): void {

		$client = new FakeOpenAIClient();

		$response = $client->createResponse('Summarize this order.', [
			'instructions' => 'Answer in Italian.',
			'metadata' => ['order_id' => 'order_123'],
			'max_output_tokens' => 200,
			'temperature' => null,
		]);

		$request = $client->lastRequest();

		$this->assertSame('resp_test', $response['id']);
		$this->assertSame('POST', $request['method']);
		$this->assertSame('/responses', $request['path']);
		$this->assertSame('gpt-test', $request['payload']['model']);
		$this->assertSame('Summarize this order.', $request['payload']['input']);
		$this->assertSame('Answer in Italian.', $request['payload']['instructions']);
		$this->assertFalse($request['payload']['store']);
		$this->assertArrayNotHasKey('temperature', $request['payload']);

	}

	/**
	 * Verify text convenience responses read the normalized output_text field.
	 */
	public function testCreateTextResponseReturnsOutputText(): void {

		$client = new FakeOpenAIClient();

		$this->assertSame('Pair response text.', $client->createTextResponse('Say hello.'));

	}

	/**
	 * Verify fallback text extraction walks message content items.
	 */
	public function testExtractOutputTextReadsMessageContent(): void {

		$text = OpenAIClient::extractOutputText([
			'output' => [
				[
					'type' => 'message',
					'content' => [
						['type' => 'output_text', 'text' => 'Hello '],
						['type' => 'output_text', 'text' => 'Pair.'],
					],
				],
			],
		]);

		$this->assertSame('Hello Pair.', $text);

	}

	/**
	 * Verify raw embeddings requests include model, format, dimensions, and user metadata.
	 */
	public function testCreateEmbeddingResponseBuildsEmbeddingPayload(): void {

		$client = new FakeOpenAIClient();

		$response = $client->createEmbeddingResponse('Document chunk', [
			'dimensions' => 256,
			'user' => 'user_123',
		]);

		$request = $client->lastRequest();

		$this->assertSame('list', $response['object']);
		$this->assertSame('/embeddings', $request['path']);
		$this->assertSame('embed-test', $request['payload']['model']);
		$this->assertSame('Document chunk', $request['payload']['input']);
		$this->assertSame('float', $request['payload']['encoding_format']);
		$this->assertSame(256, $request['payload']['dimensions']);
		$this->assertSame('user_123', $request['payload']['user']);

	}

	/**
	 * Verify embedText returns the first float vector from an embeddings response.
	 */
	public function testEmbedTextReturnsSingleVector(): void {

		$client = new FakeOpenAIClient();

		$this->assertSame([0.1, 0.2, 0.3], $client->embedText('Document chunk'));

	}

	/**
	 * Verify embedTexts returns vectors sorted by the API result index.
	 */
	public function testEmbedTextsReturnsVectorsInInputOrder(): void {

		$client = new FakeOpenAIClient();

		$this->assertSame([
			[0.1, 0.2, 0.3],
			[0.4, 0.5, 0.6],
		], $client->embedTexts(['First chunk', 'Second chunk']));

	}

	/**
	 * Verify Realtime client secret requests include a GA session envelope.
	 */
	public function testCreateRealtimeClientSecretBuildsSessionPayload(): void {

		$client = new FakeOpenAIClient();

		$response = $client->createRealtimeClientSecret([
			'audio' => [
				'output' => ['voice' => 'marin'],
			],
		]);

		$request = $client->lastRequest();

		$this->assertSame('ek_test', $response['value']);
		$this->assertSame('/realtime/client_secrets', $request['path']);
		$this->assertSame('realtime', $request['payload']['session']['type']);
		$this->assertSame('rt-test', $request['payload']['session']['model']);
		$this->assertSame('marin', $request['payload']['session']['audio']['output']['voice']);

	}

	/**
	 * Verify configured API keys can be detected before outbound calls are made.
	 */
	public function testApiKeySetReflectsConfiguredKey(): void {

		$this->assertTrue((new FakeOpenAIClient())->apiKeySet());
		$this->assertFalse((new OpenAIClient(''))->apiKeySet());

	}

}

/**
 * Test double that records OpenAI requests and returns deterministic payloads.
 */
final class FakeOpenAIClient extends OpenAIClient {

	/**
	 * Captured OpenAI requests.
	 *
	 * @var	array<int, array{method: string, path: string, payload: array}>
	 */
	private array $requests = [];

	/**
	 * Build the fake with deterministic model names.
	 */
	public function __construct() {

		parent::__construct('sk-test', 'https://api.openai.test/v1', 'gpt-test', 'embed-test', 'rt-test');

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
	 * Capture request details and return deterministic OpenAI-shaped responses.
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$this->requests[] = [
			'method' => $method,
			'path' => $path,
			'payload' => $payload,
		];

		if ('/responses' === $path) {
			return [
				'id' => 'resp_test',
				'output_text' => 'Pair response text.',
			];
		}

		if ('/embeddings' === $path) {
			return [
				'object' => 'list',
				'data' => [
					[
						'object' => 'embedding',
						'index' => 1,
						'embedding' => [0.4, 0.5, 0.6],
					],
					[
						'object' => 'embedding',
						'index' => 0,
						'embedding' => [0.1, 0.2, 0.3],
					],
				],
				'model' => $payload['model'] ?? 'embed-test',
			];
		}

		if ('/realtime/client_secrets' === $path) {
			return [
				'value' => 'ek_test',
				'expires_at' => 1893456000,
			];
		}

		return [];

	}

}
