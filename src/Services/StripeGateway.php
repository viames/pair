<?php
namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

use Stripe\StripeClient;
use Stripe\Webhook as StripeWebhook;

/**
 * This lightweight Stripe adapter centralizes configuration, idempotency, and error
 * handling, exposing a narrow API for Checkout Sessions and Payment Intents.
 * The amounts must be expressed in minor units (e.g. cents for EUR). The currency
 * must be a lowercase ISO currency code (e.g. "eur").
 * This class does not persist anything: persistence is a domain concern.
 * Requires the Stripe PHP SDK: composer require stripe/stripe-php
 */
class StripeGateway {

	/**
	 * Stripe PHP client.
	 */
	private StripeClient $client;

	/**
	 * Webhook signing secret, optional if webhooks are not used.
	 */
	private ?string $webhookSecret = NULL;

	/**
	 * Build a new instance reading secrets from Env.
	 *
	 * Required Env keys: STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, STRIPE_API_VERSION
	 */
	public function __construct(?StripeClient $client = NULL) {

		$this->client = $client ?? new StripeClient([
			'api_key'     => Env::get('STRIPE_SECRET_KEY'),
			'api_version' => Env::get('STRIPE_API_VERSION'),
		]);

		$this->webhookSecret = Env::get('STRIPE_WEBHOOK_SECRET') ?: NULL;
	
	}

	/**
	 * Capture a previously authorized Payment Intent.
	 */
	public function capture(string $paymentIntentId): array {

		try {
			$pi = $this->client->paymentIntents->capture($paymentIntentId);
			return ['id' => $pi->id, 'status' => $pi->status];
		} catch (\Throwable $e) {
			throw new PairException('Stripe capture failed: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
		}

	}

	/**
	 * Construct and verify a Stripe Event from webhook payload and signature.
	 * The signature header is usually in HTTP_STRIPE_SIGNATURE.
	 */
	public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event {

		if (! $this->webhookSecret) {
			throw new PairException('Missing STRIPE_WEBHOOK_SECRET in .env', ErrorCodes::STRIPE_ERROR);
		}

		try {
			return StripeWebhook::constructEvent($payload, $signature, $this->webhookSecret);
		} catch (\Throwable $e) {
			throw new PairException('Invalid Stripe signature', ErrorCodes::STRIPE_ERROR, $e);
		}

	}

	/**
	 * Create a Stripe Checkout Session and return its URL.
	 *
	 * @param	array	Array of line items as required by Stripe.
	 * @param	string	URL to redirect after successful payment.
	 * @param	string	URL to redirect after cancellation.
	 * @param	array	Optional keys: mode, customer, customer_email, metadata, automatic_tax(bool), allow_promotion_codes(bool), idempotency_key.
	 */
	public function createCheckoutSession(array $lineItems, string $successUrl, string $cancelUrl, array $options = []): string {

		$params = [
			'mode'                  => $options['mode'] ?? 'payment',
			'line_items'            => $lineItems,
			'success_url'           => $successUrl,
			'cancel_url'            => $cancelUrl,
			'customer'              => $options['customer'] ?? NULL,
			'customer_email'        => $options['customer_email'] ?? NULL,
			'allow_promotion_codes' => (bool)($options['allow_promotion_codes'] ?? TRUE),
			'automatic_tax'         => ['enabled' => (bool)($options['automatic_tax'] ?? FALSE)],
			'metadata'              => $options['metadata'] ?? [],
		];

		$opts = [
			'idempotency_key' => $options['idempotency_key'] ?? $this->newIdempotencyKey(),
		];

		try {
			$session = $this->client->checkout->sessions->create($params, $opts);
			return $session->url;
		} catch (\Throwable $e) {
			throw new PairException('Stripe Checkout error: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
		}
	
	}

	/**
	 * Create a Payment Intent and return id, client_secret and status.
	 *
	 * @param int $amountInMinorUnits Amount in minor units (e.g. cents for EUR).
	 * @param string $currency ISO currency code, lowercase (e.g. "eur").
	 * @param array $options Optional keys: capture_method, customer, metadata, setup_future_usage, payment_method_types, idempotency_key.
	 */
	public function createPaymentIntent(int $amountInMinorUnits, string $currency, array $options = []): array {

		$params = [
			'amount'                    => $amountInMinorUnits,
			'currency'                  => strtolower($currency),
			'automatic_payment_methods' => ['enabled' => ! isset($options['payment_method_types'])],
			'payment_method_types'      => $options['payment_method_types'] ?? NULL,
			'capture_method'            => $options['capture_method'] ?? 'automatic', // 'manual' for authorizations
			'customer'                  => $options['customer'] ?? NULL,
			'metadata'                  => $options['metadata'] ?? [],
			'setup_future_usage'        => $options['setup_future_usage'] ?? NULL, // e.g. 'off_session'
		];

		try {
			$intent = $this->client->paymentIntents->create($params, [
				'idempotency_key' => $options['idempotency_key'] ?? $this->newIdempotencyKey(),
			]);

			return [
				'id'            => $intent->id,
				'client_secret' => $intent->client_secret,
				'status'        => $intent->status,
			];
		} catch (\Throwable $e) {
			throw new PairException('Stripe PaymentIntent error: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
		}

	}

	/**
	 * Create a random idempotency key.
	 */
	private function newIdempotencyKey(): string {

		return bin2hex(random_bytes(16));

	}

	/**
	 * Refund a payment (full or partial if $amountInMinorUnits is provided).
	 */
	public function refund(string $paymentIntentId, ?int $amountInMinorUnits = NULL): array {

		$params = [
			'payment_intent' => $paymentIntentId,
			'amount'         => $amountInMinorUnits,
		];

		try {
			$refund = $this->client->refunds->create($params);
			return ['id' => $refund->id, 'status' => $refund->status];
		} catch (\Throwable $e) {
			throw new PairException('Stripe refund failed: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
		}

	}

	/**
	 * Helper to convert major units (e.g. 12.34 EUR) to minor units (e.g. 1234 cents).
	 */
	public static function toMinorUnits(float $amount): int {

		return (int) round($amount * 100);

	}

}