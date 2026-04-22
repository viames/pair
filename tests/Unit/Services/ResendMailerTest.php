<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Http\JsonResponse;
use Pair\Services\ResendMailer;
use Pair\Tests\Support\TestCase;

/**
 * Covers ResendMailer request shaping without calling Resend.
 */
class ResendMailerTest extends TestCase {

	/**
	 * Verify transactional emails are normalized into the Resend API payload shape.
	 */
	public function testSendTransactionalBuildsEmailPayload(): void {

		$mailer = new FakeResendMailer();

		$response = $mailer->sendTransactional([
			'to' => ['Ada <ada@example.test>'],
			'subject' => 'Welcome',
			'html' => '<p>Hello</p>',
			'tags' => ['user_id' => 'user_123'],
			'headers' => ['X-Entity-ID' => 'user_123'],
		], [
			'idempotency_key' => 'welcome-user-123',
		]);

		$request = $mailer->lastRequest();

		$this->assertSame('email_test_123', $response['id']);
		$this->assertSame('POST', $request['method']);
		$this->assertSame('/emails', $request['path']);
		$this->assertSame('Pair Test <no-reply@example.test>', $request['payload']['from']);
		$this->assertSame(['Ada <ada@example.test>'], $request['payload']['to']);
		$this->assertSame('Welcome', $request['payload']['subject']);
		$this->assertSame('<p>Hello</p>', $request['payload']['html']);
		$this->assertSame([
			['name' => 'environment', 'value' => 'test'],
			['name' => 'user_id', 'value' => 'user_123'],
		], $request['payload']['tags']);
		$this->assertSame(['X-App' => 'pair-test', 'X-Entity-ID' => 'user_123'], $request['payload']['headers']);
		$this->assertContains('Idempotency-Key: welcome-user-123', $request['headers']);

	}

	/**
	 * Verify the legacy Mailer send contract maps to a Resend transactional email.
	 */
	public function testSendMapsLegacyMailerContract(): void {

		$mailer = new FakeResendMailer();

		$mailer->send([
			['name' => 'Grace', 'email' => 'grace@example.test'],
		], 'Legacy subject', 'Legacy title', '<p>Legacy text</p>');

		$request = $mailer->lastRequest();

		$this->assertSame('/emails', $request['path']);
		$this->assertSame(['Grace <grace@example.test>'], $request['payload']['to']);
		$this->assertSame('Legacy subject', $request['payload']['subject']);
		$this->assertStringContainsString('Legacy title', $request['payload']['html']);
		$this->assertSame('Legacy text', $request['payload']['text']);

	}

	/**
	 * Verify local file attachments are Base64 encoded for Resend.
	 */
	public function testSendTransactionalNormalizesLocalAttachment(): void {

		$attachmentPath = TEMP_PATH . 'resend-attachment.txt';
		file_put_contents($attachmentPath, 'invoice');

		$mailer = new FakeResendMailer();
		$mailer->sendTransactional([
			'to' => 'billing@example.test',
			'subject' => 'Invoice',
			'html' => '<p>Invoice</p>',
			'attachments' => [
				['filePath' => $attachmentPath, 'filename' => 'invoice.txt'],
			],
		]);

		$attachment = $mailer->lastRequest()['payload']['attachments'][0];

		$this->assertSame('invoice.txt', $attachment['filename']);
		$this->assertSame(base64_encode('invoice'), $attachment['content']);

	}

	/**
	 * Verify Resend webhook verification returns decoded payloads for valid signatures.
	 */
	public function testVerifyWebhookPayloadAcceptsValidSvixSignature(): void {

		$mailer = new FakeResendMailer();
		$payload = json_encode([
			'type' => 'email.delivered',
			'data' => ['email_id' => 'email_test_123'],
		], JSON_THROW_ON_ERROR);
		$timestamp = (string)time();
		$headers = $this->signedWebhookHeaders($payload, 'msg_test', $timestamp, 'secret-test');

		$event = $mailer->verifyWebhookPayload($payload, $headers);

		$this->assertSame('email.delivered', $event['type']);
		$this->assertSame('email_test_123', $event['data']['email_id']);

	}

	/**
	 * Verify webhook handlers are selected by event type and return a JSON acknowledgement.
	 */
	public function testWebhookResponseFromEventInvokesMatchingHandler(): void {

		$mailer = new FakeResendMailer();
		$handledEmailId = null;

		$response = $mailer->webhookResponseFromEvent([
			'type' => 'email.bounced',
			'data' => ['email_id' => 'email_test_456'],
		], [
			'email.bounced' => function (array $event) use (&$handledEmailId): void {
				$handledEmailId = $event['data']['email_id'];
			},
		]);

		$this->assertSame('email_test_456', $handledEmailId);
		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(
			['received' => true, 'type' => 'email.bounced'],
			$this->readJsonResponsePayload($response)
		);

	}

	/**
	 * Verify configured API keys can be detected before outbound calls are made.
	 */
	public function testApiKeySetReflectsConfiguredKey(): void {

		$this->assertTrue((new FakeResendMailer())->apiKeySet());

		$reflection = new \ReflectionClass(ResendMailer::class);
		$mailer = $reflection->newInstanceWithoutConstructor();

		$this->assertFalse($mailer->apiKeySet());

	}

	/**
	 * Read the private JsonResponse payload for focused response-shape assertions.
	 */
	private function readJsonResponsePayload(JsonResponse $response): mixed {

		$property = new \ReflectionProperty($response, 'payload');

		return $property->getValue($response);

	}

	/**
	 * Build signed Svix headers using the same signing format Resend documents.
	 */
	private function signedWebhookHeaders(string $payload, string $id, string $timestamp, string $secret): array {

		return [
			'svix-id' => $id,
			'svix-timestamp' => $timestamp,
			'svix-signature' => 'v1,' . base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $payload, $secret, true)),
		];

	}

}

/**
 * Test double that records Resend requests and returns deterministic payloads.
 */
final class FakeResendMailer extends ResendMailer {

	/**
	 * Captured Resend requests.
	 *
	 * @var	array<int, array{method: string, path: string, payload: array, headers: array}>
	 */
	private array $requests = [];

	/**
	 * Build the fake with deterministic configuration.
	 */
	public function __construct() {

		parent::__construct([
			'apiKey' => 're_test',
			'apiBaseUrl' => 'https://api.resend.test',
			'fromAddress' => 'no-reply@example.test',
			'fromName' => 'Pair Test',
			'webhookSecret' => 'secret-test',
			'defaultTags' => ['environment' => 'test'],
			'defaultHeaders' => ['X-App' => 'pair-test'],
		]);

	}

	/**
	 * Return the most recent captured request.
	 *
	 * @return	array{method: string, path: string, payload: array, headers: array}
	 */
	public function lastRequest(): array {

		return $this->requests[count($this->requests) - 1] ?? ['method' => '', 'path' => '', 'payload' => [], 'headers' => []];

	}

	/**
	 * Return a deterministic email body without loading translation fixtures.
	 */
	protected function getBody(string $preHeader, string $title, string $text): string {

		return '<h1>' . $title . '</h1>' . $text;

	}

	/**
	 * Capture request details and return a deterministic Resend response.
	 */
	protected function requestJson(string $method, string $path, array $payload = [], array $headers = []): array {

		$this->requests[] = [
			'method' => $method,
			'path' => $path,
			'payload' => $payload,
			'headers' => $headers,
		];

		return [
			'id' => 'email_test_123',
		];

	}

}
