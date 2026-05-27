<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Http\JsonResponse;
use Pair\Services\StripeGateway;
use Pair\Tests\Support\TestCase;

/**
 * Covers StripeGateway request shaping without calling the Stripe API.
 */
class StripeGatewayTest extends TestCase {

	/**
	 * Verify the legacy Checkout helper still returns the hosted session URL.
	 */
	public function testCreateCheckoutSessionReturnsHostedUrlForLegacyCall(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$url = $gateway->createCheckoutSession(
			[['price' => 'price_test', 'quantity' => 1]],
			'https://app.test/payment/success',
			'https://app.test/payment/cancel',
			['idempotency_key' => 'checkout-key']
		);

		$this->assertSame('https://checkout.stripe.test/session', $url);
		$this->assertSame('payment', $client->checkout->sessions->lastParams['mode']);
		$this->assertSame(['idempotency_key' => 'checkout-key'], $client->checkout->sessions->lastOptions);

	}

	/**
	 * Verify hosted Checkout Sessions include supported options and omit null values.
	 */
	public function testCreateHostedCheckoutSessionNormalizesSessionData(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$session = $gateway->createHostedCheckoutSession(
			[['price' => 'price_test', 'quantity' => 2]],
			'https://app.test/payment/success',
			'https://app.test/payment/cancel',
			[
				'metadata' => ['order_id' => 'order_123'],
				'customer' => null,
				'idempotency_key' => 'hosted-key',
			]
		);

		$this->assertSame('cs_test_123', $session['id']);
		$this->assertSame('https://checkout.stripe.test/session', $session['url']);
		$this->assertSame('open', $session['status']);
		$this->assertArrayNotHasKey('customer', $client->checkout->sessions->lastParams);
		$this->assertSame(['order_id' => 'order_123'], $client->checkout->sessions->lastParams['metadata']);
		$this->assertSame(['enabled' => false], $client->checkout->sessions->lastParams['automatic_tax']);

	}

	/**
	 * Verify embedded Checkout Sessions use ui_mode and return_url instead of redirect URLs.
	 */
	public function testCreateEmbeddedCheckoutSessionUsesEmbeddedParameters(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$session = $gateway->createEmbeddedCheckoutSession(
			[['price' => 'price_test', 'quantity' => 1]],
			'https://app.test/payment/return?session_id={CHECKOUT_SESSION_ID}',
			[
				'redirect_on_completion' => 'if_required',
				'idempotency_key' => 'embedded-key',
			]
		);

		$this->assertSame('cs_secret_test_123', $session['client_secret']);
		$this->assertSame('embedded', $client->checkout->sessions->lastParams['ui_mode']);
		$this->assertSame('https://app.test/payment/return?session_id={CHECKOUT_SESSION_ID}', $client->checkout->sessions->lastParams['return_url']);
		$this->assertSame('if_required', $client->checkout->sessions->lastParams['redirect_on_completion']);
		$this->assertArrayNotHasKey('success_url', $client->checkout->sessions->lastParams);
		$this->assertArrayNotHasKey('cancel_url', $client->checkout->sessions->lastParams);

	}

	/**
	 * Verify subscription Checkout Sessions force Stripe subscription mode.
	 */
	public function testCreateSubscriptionCheckoutSessionForcesSubscriptionMode(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$gateway->createSubscriptionCheckoutSession(
			[['price' => 'price_recurring', 'quantity' => 1]],
			'https://app.test/billing/success',
			'https://app.test/billing/cancel',
			['idempotency_key' => 'subscription-key']
		);

		$this->assertSame('subscription', $client->checkout->sessions->lastParams['mode']);

	}

	/**
	 * Verify Customer Portal Sessions pass customer and return URL to Stripe.
	 */
	public function testCreateCustomerPortalSessionReturnsPortalUrl(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$url = $gateway->createCustomerPortalSession(
			'cus_test',
			'https://app.test/account',
			[
				'configuration' => 'bpc_test',
				'idempotency_key' => 'portal-key',
			]
		);

		$this->assertSame('https://billing.stripe.test/session', $url);
		$this->assertSame('cus_test', $client->billingPortal->sessions->lastParams['customer']);
		$this->assertSame('https://app.test/account', $client->billingPortal->sessions->lastParams['return_url']);
		$this->assertSame(['idempotency_key' => 'portal-key'], $client->billingPortal->sessions->lastOptions);

	}

	/**
	 * Verify the default Stripe client uses the SDK option name for pinned API versions.
	 */
	public function testDefaultClientConfigUsesStripeVersionOption(): void {

		$_ENV['STRIPE_API_VERSION'] = '2026-04-22.dahlia';

		$gateway = new StripeGateway(new FakeStripeClient());

		$config = $this->invokeInaccessibleMethod($gateway, 'defaultClientConfig', ['sk_test_pair']);

		$this->assertSame('sk_test_pair', $config['api_key']);
		$this->assertSame('2026-04-22.dahlia', $config['stripe_version']);
		$this->assertArrayNotHasKey('api_version', $config);

	}

	/**
	 * Verify PaymentIntent creation normalizes currency, omits nulls, and sends idempotency.
	 */
	public function testCreatePaymentIntentShapesStripePayload(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$intent = $gateway->createPaymentIntent(
			4990,
			'EUR',
			[
				'description' => 'Order #123',
				'customer' => null,
				'idempotency_key' => 'intent-key',
			]
		);

		$this->assertSame('pi_test_123', $intent['id']);
		$this->assertSame(4990, $client->paymentIntents->lastParams['amount']);
		$this->assertSame('eur', $client->paymentIntents->lastParams['currency']);
		$this->assertSame('Order #123', $client->paymentIntents->lastParams['description']);
		$this->assertArrayNotHasKey('customer', $client->paymentIntents->lastParams);
		$this->assertSame(['idempotency_key' => 'intent-key'], $client->paymentIntents->lastOptions);

	}

	/**
	 * Verify PaymentIntent creation gets a safe generated idempotency key by default.
	 */
	public function testCreatePaymentIntentGeneratesIdempotencyKeyByDefault(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$gateway->createPaymentIntent(1000, 'eur');

		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $client->paymentIntents->lastOptions['idempotency_key']);

	}

	/**
	 * Verify explicit idempotency keys are preserved even when PHP would treat them as falsey.
	 */
	public function testCreatePaymentIntentPreservesFalseyIdempotencyKey(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$gateway->createPaymentIntent(1000, 'eur', ['idempotency_key' => '0']);

		$this->assertSame(['idempotency_key' => '0'], $client->paymentIntents->lastOptions);

	}

	/**
	 * Verify PaymentIntent capture uses a deterministic idempotency key by default.
	 */
	public function testCaptureUsesDeterministicIdempotencyKeyByDefault(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$result = $gateway->capture('pi_capture_test');

		$this->assertSame('pi_capture_test', $result['id']);
		$this->assertSame('pi_capture_test', $client->paymentIntents->lastCaptureId);
		$this->assertSame('pair:stripe:capture:pi_capture_test', $client->paymentIntents->lastCaptureOptions['idempotency_key']);

	}

	/**
	 * Verify refunds pass amount and request idempotency to Stripe.
	 */
	public function testRefundUsesIdempotencyOptions(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$refund = $gateway->refund('pi_refund_test', 500, ['idempotency_key' => 'refund-key']);

		$this->assertSame('re_test_123', $refund['id']);
		$this->assertSame('pi_refund_test', $client->refunds->lastParams['payment_intent']);
		$this->assertSame(500, $client->refunds->lastParams['amount']);
		$this->assertSame(['idempotency_key' => 'refund-key'], $client->refunds->lastOptions);

	}

	/**
	 * Verify refunds generate a request idempotency key by default.
	 */
	public function testRefundGeneratesIdempotencyKeyByDefault(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);

		$gateway->refund('pi_refund_test', 500);

		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $client->refunds->lastOptions['idempotency_key']);

	}

	/**
	 * Verify verified webhook events invoke matching handlers and return an acknowledgement.
	 */
	public function testWebhookResponseFromEventInvokesMatchingHandler(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);
		$handledEventId = null;

		$response = $gateway->webhookResponseFromEvent(
			(object)['id' => 'evt_test', 'type' => 'checkout.session.completed'],
			[
				'checkout.session.completed' => function (object $event) use (&$handledEventId): void {
					$handledEventId = $event->id;
				},
			]
		);

		$this->assertSame('evt_test', $handledEventId);
		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(
			['received' => true, 'type' => 'checkout.session.completed'],
			$this->readJsonResponsePayload($response)
		);

	}

	/**
	 * Verify duplicate webhook events can be acknowledged without running the handler twice.
	 */
	public function testWebhookResponseFromEventSkipsDuplicateEvents(): void {

		$client = new FakeStripeClient();
		$gateway = new StripeGateway($client);
		$handlerCalls = 0;

		$response = $gateway->webhookResponseFromEvent(
			(object)['id' => 'evt_duplicate', 'type' => 'checkout.session.completed'],
			[
				'checkout.session.completed' => function () use (&$handlerCalls): void {
					$handlerCalls++;
				},
			],
			function (string $eventId, string $type): bool {
				return 'evt_duplicate' === $eventId and 'checkout.session.completed' === $type;
			}
		);

		$this->assertSame(0, $handlerCalls);
		$this->assertSame(
			['received' => true, 'type' => 'checkout.session.completed', 'duplicate' => true],
			$this->readJsonResponsePayload($response)
		);

	}

	/**
	 * Verify major-unit amounts can be converted to minor units for Stripe.
	 */
	public function testToMinorUnitsRoundsToCents(): void {

		$this->assertSame(1235, StripeGateway::toMinorUnits(12.345));
		$this->assertSame(1008, StripeGateway::toMinorUnits('10.075', 'eur'));
		$this->assertSame(500, StripeGateway::toMinorUnits('500', 'jpy'));
		$this->assertSame(500, StripeGateway::toMinorUnits('5', 'isk'));

	}

	/**
	 * Verify special Stripe currencies reject unsupported fractional charges.
	 */
	public function testToMinorUnitsRejectsInvalidSpecialCurrencyFractions(): void {

		$this->expectException(\InvalidArgumentException::class);

		StripeGateway::toMinorUnits('5.001', 'isk');

	}

	/**
	 * Verify zero-decimal currencies reject fractional major-unit amounts.
	 */
	public function testToMinorUnitsRejectsZeroDecimalFractions(): void {

		$this->expectException(\InvalidArgumentException::class);

		StripeGateway::toMinorUnits('500.4', 'jpy');

	}

	/**
	 * Read the private JsonResponse payload for focused response-shape assertions.
	 */
	private function readJsonResponsePayload(JsonResponse $response): mixed {

		$property = new \ReflectionProperty($response, 'payload');

		return $property->getValue($response);

	}

}

/**
 * Minimal fake of the Stripe client shape used by StripeGateway.
 */
final class FakeStripeClient {

	/**
	 * Fake Checkout namespace.
	 */
	public FakeStripeCheckoutNamespace $checkout;

	/**
	 * Fake Billing Portal namespace.
	 */
	public FakeStripeBillingPortalNamespace $billingPortal;

	/**
	 * Fake PaymentIntents resource.
	 */
	public FakeStripePaymentIntentResource $paymentIntents;

	/**
	 * Fake Refunds resource.
	 */
	public FakeStripeRefundResource $refunds;

	/**
	 * Build fake Stripe resources with deterministic responses.
	 */
	public function __construct() {

		$this->checkout = new FakeStripeCheckoutNamespace();
		$this->billingPortal = new FakeStripeBillingPortalNamespace();
		$this->paymentIntents = new FakeStripePaymentIntentResource();
		$this->refunds = new FakeStripeRefundResource();

	}

}

/**
 * Fake Stripe Checkout namespace.
 */
final class FakeStripeCheckoutNamespace {

	/**
	 * Fake Checkout Sessions resource.
	 */
	public FakeStripeCheckoutSessionResource $sessions;

	/**
	 * Build fake Checkout resources.
	 */
	public function __construct() {

		$this->sessions = new FakeStripeCheckoutSessionResource();

	}

}

/**
 * Fake Stripe Billing Portal namespace.
 */
final class FakeStripeBillingPortalNamespace {

	/**
	 * Fake Billing Portal Sessions resource.
	 */
	public FakeStripePortalSessionResource $sessions;

	/**
	 * Build fake Billing Portal resources.
	 */
	public function __construct() {

		$this->sessions = new FakeStripePortalSessionResource();

	}

}

/**
 * Fake Stripe Checkout Sessions resource.
 */
final class FakeStripeCheckoutSessionResource {

	/**
	 * Last received create payload.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastParams = [];

	/**
	 * Last received request options.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastOptions = [];

	/**
	 * Capture the request and return a deterministic Checkout Session object.
	 */
	public function create(array $params, array $options = []): object {

		$this->lastParams = $params;
		$this->lastOptions = $options;

		return (object)[
			'id' => 'cs_test_123',
			'url' => 'https://checkout.stripe.test/session',
			'client_secret' => 'cs_secret_test_123',
			'status' => 'open',
			'mode' => $params['mode'] ?? 'payment',
			'customer' => $params['customer'] ?? null,
		];

	}

}

/**
 * Fake Stripe Billing Portal Sessions resource.
 */
final class FakeStripePortalSessionResource {

	/**
	 * Last received create payload.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastParams = [];

	/**
	 * Last received request options.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastOptions = [];

	/**
	 * Capture the request and return a deterministic Portal Session object.
	 */
	public function create(array $params, array $options = []): object {

		$this->lastParams = $params;
		$this->lastOptions = $options;

		return (object)[
			'id' => 'bps_test_123',
			'url' => 'https://billing.stripe.test/session',
		];

	}

}

/**
 * Fake Stripe PaymentIntents resource.
 */
final class FakeStripePaymentIntentResource {

	/**
	 * Last received create payload.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastParams = [];

	/**
	 * Last received request options.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastOptions = [];

	/**
	 * Last captured PaymentIntent ID.
	 */
	public ?string $lastCaptureId = null;

	/**
	 * Last received capture payload.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastCaptureParams = [];

	/**
	 * Last received capture options.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastCaptureOptions = [];

	/**
	 * Capture the request and return a deterministic PaymentIntent object.
	 */
	public function create(array $params, array $options = []): object {

		$this->lastParams = $params;
		$this->lastOptions = $options;

		return (object)[
			'id' => 'pi_test_123',
			'client_secret' => 'pi_secret_test_123',
			'status' => 'requires_payment_method',
		];

	}

	/**
	 * Return a deterministic captured PaymentIntent object.
	 */
	public function capture(string $paymentIntentId, array $params = [], array $options = []): object {

		$this->lastCaptureId = $paymentIntentId;
		$this->lastCaptureParams = $params;
		$this->lastCaptureOptions = $options;

		return (object)[
			'id' => $paymentIntentId,
			'status' => 'succeeded',
		];

	}

}

/**
 * Fake Stripe Refunds resource.
 */
final class FakeStripeRefundResource {

	/**
	 * Last received create payload.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastParams = [];

	/**
	 * Last received request options.
	 *
	 * @var	array<string, mixed>
	 */
	public array $lastOptions = [];

	/**
	 * Capture the request and return a deterministic Refund object.
	 */
	public function create(array $params, array $options = []): object {

		$this->lastParams = $params;
		$this->lastOptions = $options;

		return (object)[
			'id' => 're_test_123',
			'status' => 'succeeded',
		];

	}

}
