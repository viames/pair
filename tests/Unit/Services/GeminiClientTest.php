<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Services\GeminiClient;
use Pair\Tests\Support\TestCase;

/**
 * Covers GeminiClient request shaping without calling Gemini.
 */
class GeminiClientTest extends TestCase {

	/**
	 * Verify generateContent payloads use safe defaults and preserve requested options.
	 */
	public function testGenerateContentBuildsPayload(): void {

		$client = new FakeGeminiClient();

		$response = $client->generateContent('Summarize this order.', [
			'system_instruction' => 'Answer in Italian.',
			'max_output_tokens' => 200,
			'temperature' => null,
			'top_p' => 0.8,
			'safety_settings' => [
				[
					'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
					'threshold' => 'BLOCK_ONLY_HIGH',
				],
			],
		]);

		$request = $client->lastRequest();

		$this->assertSame('candidate_test', $response['candidates'][0]['finishReason']);
		$this->assertSame('POST', $request['method']);
		$this->assertSame('/models/gemini-test:generateContent', $request['path']);
		$this->assertSame('user', $request['payload']['contents'][0]['role']);
		$this->assertSame('Summarize this order.', $request['payload']['contents'][0]['parts'][0]['text']);
		$this->assertSame('Answer in Italian.', $request['payload']['systemInstruction']['parts'][0]['text']);
		$this->assertSame(200, $request['payload']['generationConfig']['maxOutputTokens']);
		$this->assertSame(0.8, $request['payload']['generationConfig']['topP']);
		$this->assertArrayNotHasKey('temperature', $request['payload']['generationConfig']);
		$this->assertSame('BLOCK_ONLY_HIGH', $request['payload']['safetySettings'][0]['threshold']);

	}

	/**
	 * Verify text convenience responses read Gemini candidate content parts.
	 */
	public function testGenerateTextReturnsCandidateText(): void {

		$client = new FakeGeminiClient();

		$this->assertSame('Pair response text.', $client->generateText('Say hello.'));

	}

	/**
	 * Verify chat messages map system and assistant roles into Gemini payload shape.
	 */
	public function testChatNormalizesRolesAndSystemInstruction(): void {

		$client = new FakeGeminiClient();

		$client->chat([
			['role' => 'system', 'content' => 'Keep answers concise.'],
			['role' => 'user', 'content' => 'Hello.'],
			['role' => 'assistant', 'content' => 'Hi.'],
			['role' => 'user', 'content' => 'Continue.'],
		]);

		$request = $client->lastRequest();

		$this->assertSame('Keep answers concise.', $request['payload']['systemInstruction']['parts'][0]['text']);
		$this->assertSame('user', $request['payload']['contents'][0]['role']);
		$this->assertSame('model', $request['payload']['contents'][1]['role']);
		$this->assertSame('user', $request['payload']['contents'][2]['role']);

	}

	/**
	 * Verify raw embedding requests include model, content, and dimensionality options.
	 */
	public function testCreateEmbeddingResponseBuildsEmbeddingPayload(): void {

		$client = new FakeGeminiClient();

		$response = $client->createEmbeddingResponse('Document chunk', [
			'output_dimensionality' => 768,
		]);

		$request = $client->lastRequest();

		$this->assertSame([0.1, 0.2, 0.3], $response['embedding']['values']);
		$this->assertSame('/models/embed-test:embedContent', $request['path']);
		$this->assertSame('models/embed-test', $request['payload']['model']);
		$this->assertSame('Document chunk', $request['payload']['content']['parts'][0]['text']);
		$this->assertSame(768, $request['payload']['outputDimensionality']);

	}

	/**
	 * Verify embedText returns the first float vector from an embedding response.
	 */
	public function testEmbedTextReturnsSingleVector(): void {

		$client = new FakeGeminiClient();

		$this->assertSame([0.1, 0.2, 0.3], $client->embedText('Document chunk'));

	}

	/**
	 * Verify configured API keys can be detected before outbound calls are made.
	 */
	public function testApiKeySetReflectsConfiguredKey(): void {

		$this->assertTrue((new FakeGeminiClient())->apiKeySet());
		$this->assertFalse((new GeminiClient(''))->apiKeySet());

	}

}

/**
 * Test double that records Gemini requests and returns deterministic payloads.
 */
final class FakeGeminiClient extends GeminiClient {

	/**
	 * Captured Gemini requests.
	 *
	 * @var	array<int, array{method: string, path: string, payload: array}>
	 */
	private array $requests = [];

	/**
	 * Build the fake with deterministic model names.
	 */
	public function __construct() {

		parent::__construct('gemini-key', 'https://gemini.test/v1beta', 'gemini-test', 'embed-test');

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
	 * Capture request details and return deterministic Gemini-shaped responses.
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$this->requests[] = [
			'method' => $method,
			'path' => $path,
			'payload' => $payload,
		];

		if (str_ends_with($path, ':generateContent')) {
			return [
				'candidates' => [
					[
						'finishReason' => 'candidate_test',
						'content' => [
							'parts' => [
								['text' => 'Pair response text.'],
							],
						],
					],
				],
			];
		}

		if (str_ends_with($path, ':embedContent')) {
			return [
				'embedding' => [
					'values' => [0.1, 0.2, 0.3],
				],
			];
		}

		return [];

	}

}
