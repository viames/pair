<?php

declare(strict_types=1);

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Http\JsonResponse;

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
	 * Stripe PHP client or test-compatible adapter.
	 */
	private object $client;

	/**
	 * Webhook signing secret, optional if webhooks are not used.
	 */
	private ?string $webhookSecret = null;

	/**
	 * Build a new instance reading secrets from Env.
	 *
	 * Required Env keys: STRIPE_SECRET_KEY. Optional keys: STRIPE_WEBHOOK_SECRET, STRIPE_API_VERSION.
	 */
	public function __construct(?object $client = null, ?string $webhookSecret = null) {

		$this->client = $client ?? $this->createDefaultClient();

		$this->webhookSecret = $webhookSecret ?? (Env::get('STRIPE_WEBHOOK_SECRET') ?: null);
	
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
	 * Build the default Stripe SDK client from environment values.
	 */
	private function createDefaultClient(): object {

		if (!class_exists(StripeClient::class)) {
			throw new \RuntimeException('Stripe PHP SDK is not installed. Run: composer require stripe/stripe-php');
		}

		$secretKey = trim((string)Env::get('STRIPE_SECRET_KEY'));

		if (!strlen($secretKey)) {
			throw new PairException('Missing STRIPE_SECRET_KEY in .env', ErrorCodes::MISSING_CONFIGURATION);
		}

		$config = [
			'api_key' => $secretKey,
		];

		$apiVersion = trim((string)Env::get('STRIPE_API_VERSION'));

		if (strlen($apiVersion)) {
			$config['api_version'] = $apiVersion;
		}

		return new StripeClient($config);

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
	 * Construct and verify a Stripe Event from the current HTTP request body and signature header.
	 */
	public function constructWebhookEventFromRequest(?string $payload = null, ?string $signature = null): \Stripe\Event {

		$payload = $payload ?? (string)file_get_contents('php://input');
		$signature = $signature ?? (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

		return $this->constructWebhookEvent($payload, $signature);

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

		$session = $this->createHostedCheckoutSession($lineItems, $successUrl, $cancelUrl, $options);

		return (string)($session['url'] ?? '');

	}

	/**
	 * Create a hosted Stripe Checkout Session and return normalized session data.
	 *
	 * @param	array	$lineItems	Stripe Checkout line items.
	 * @param	string	$successUrl	URL Stripe redirects to after successful payment.
	 * @param	string	$cancelUrl	URL Stripe redirects to when the customer cancels.
	 * @param	array	$options	Optional keys: mode, customer, customer_email, metadata, automatic_tax, allow_promotion_codes, subscription_data, client_reference_id, locale, payment_method_types, idempotency_key.
	 * @return	array<string, mixed>
	 */
	public function createHostedCheckoutSession(array $lineItems, string $successUrl, string $cancelUrl, array $options = []): array {

		$params = [
			'mode'                  => $options['mode'] ?? 'payment',
			'line_items'            => $lineItems,
			'success_url'           => $successUrl,
			'cancel_url'            => $cancelUrl,
			'customer'              => $options['customer'] ?? null,
			'customer_email'        => $options['customer_email'] ?? null,
			'allow_promotion_codes' => (bool)($options['allow_promotion_codes'] ?? true),
			'automatic_tax'         => ['enabled' => (bool)($options['automatic_tax'] ?? false)],
			'metadata'              => $options['metadata'] ?? [],
			'subscription_data'     => $options['subscription_data'] ?? null,
			'client_reference_id'   => $options['client_reference_id'] ?? null,
			'locale'                => $options['locale'] ?? null,
			'payment_method_types'  => $options['payment_method_types'] ?? null,
		];

		try {
			$session = $this->createCheckoutSessionObject($params, $options);
			return $this->normalizeCheckoutSession($session);
		} catch (\Throwable $e) {
			throw new PairException('Stripe Checkout error: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
		}
	
	}

	/**
	 * Create an embedded Stripe Checkout Session and return normalized session data.
	 *
	 * @param	array	$lineItems	Stripe Checkout line items.
	 * @param	string	$returnUrl	URL used by embedded Checkout when a redirect is required.
	 * @param	array	$options	Optional keys: mode, customer, customer_email, metadata, automatic_tax, allow_promotion_codes, redirect_on_completion, subscription_data, client_reference_id, locale, payment_method_types, idempotency_key.
	 * @return	array<string, mixed>
	 */
	public function createEmbeddedCheckoutSession(array $lineItems, string $returnUrl, array $options = []): array {

		$params = [
			'mode'                   => $options['mode'] ?? 'payment',
			'line_items'             => $lineItems,
			'ui_mode'                => 'embedded',
			'return_url'             => $returnUrl,
			'customer'               => $options['customer'] ?? null,
			'customer_email'         => $options['customer_email'] ?? null,
			'allow_promotion_codes'  => (bool)($options['allow_promotion_codes'] ?? true),
			'automatic_tax'          => ['enabled' => (bool)($options['automatic_tax'] ?? false)],
			'metadata'               => $options['metadata'] ?? [],
			'redirect_on_completion' => $options['redirect_on_completion'] ?? null,
			'subscription_data'      => $options['subscription_data'] ?? null,
			'client_reference_id'    => $options['client_reference_id'] ?? null,
			'locale'                 => $options['locale'] ?? null,
			'payment_method_types'   => $options['payment_method_types'] ?? null,
		];

		try {
			$session = $this->createCheckoutSessionObject($params, $options);
			return $this->normalizeCheckoutSession($session);
		} catch (\Throwable $e) {
			throw new PairException('Stripe embedded Checkout error: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
		}

	}

	/**
	 * Create a hosted subscription Checkout Session and return normalized session data.
	 *
	 * @param	array	$lineItems	Stripe Checkout line items with recurring prices.
	 * @param	string	$successUrl	URL Stripe redirects to after successful setup.
	 * @param	string	$cancelUrl	URL Stripe redirects to when the customer cancels.
	 * @param	array	$options	Optional Checkout Session options.
	 * @return	array<string, mixed>
	 */
	public function createSubscriptionCheckoutSession(array $lineItems, string $successUrl, string $cancelUrl, array $options = []): array {

		$options['mode'] = 'subscription';

		return $this->createHostedCheckoutSession($lineItems, $successUrl, $cancelUrl, $options);

	}

	/**
	 * Create a Stripe Customer Portal Session and return its URL.
	 *
	 * @param	string	$customerId	Stripe Customer ID.
	 * @param	string	$returnUrl	URL customers can return to from the portal.
	 * @param	array	$options	Optional keys: configuration, flow_data, locale, on_behalf_of, idempotency_key.
	 */
	public function createCustomerPortalSession(string $customerId, string $returnUrl, array $options = []): string {

		$params = [
			'customer'      => $customerId,
			'return_url'    => $returnUrl,
			'configuration' => $options['configuration'] ?? null,
			'flow_data'     => $options['flow_data'] ?? null,
			'locale'        => $options['locale'] ?? null,
			'on_behalf_of'  => $options['on_behalf_of'] ?? null,
		];

		try {
			$session = $this->client->billingPortal->sessions->create(
				$this->withoutNullValues($params),
				$this->requestOptions($options)
			);

			return (string)($session->url ?? '');
		} catch (\Throwable $e) {
			throw new PairException('Stripe customer portal error: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
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
			'payment_method_types'      => $options['payment_method_types'] ?? null,
			'capture_method'            => $options['capture_method'] ?? 'automatic', // 'manual' for authorizations
			'customer'                  => $options['customer'] ?? null,
			'metadata'                  => $options['metadata'] ?? [],
			'setup_future_usage'        => $options['setup_future_usage'] ?? null,
			'description'               => $options['description'] ?? null,
			'receipt_email'             => $options['receipt_email'] ?? null,
			'confirm'                   => $options['confirm'] ?? null,
		];

		try {
			$intent = $this->client->paymentIntents->create(
				$this->withoutNullValues($params),
				$this->requestOptions($options, true)
			);

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
	 * Create a Checkout Session object through the Stripe client.
	 */
	private function createCheckoutSessionObject(array $params, array $options): object {

		return $this->client->checkout->sessions->create(
			$this->withoutNullValues($params),
			$this->requestOptions($options, true)
		);

	}

	/**
	 * Return request options accepted by the Stripe SDK.
	 *
	 * @return	array<string, mixed>
	 */
	private function requestOptions(array $options, bool $generateIdempotencyKey = false): array {

		$idempotencyKey = $options['idempotency_key'] ?? null;

		if (!$idempotencyKey and $generateIdempotencyKey) {
			$idempotencyKey = $this->newIdempotencyKey();
		}

		return $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : [];

	}

	/**
	 * Normalize a Checkout Session object into the small response shape Pair examples use.
	 *
	 * @return	array<string, mixed>
	 */
	private function normalizeCheckoutSession(object $session): array {

		return [
			'id' => $session->id ?? null,
			'url' => $session->url ?? null,
			'client_secret' => $session->client_secret ?? null,
			'status' => $session->status ?? null,
			'mode' => $session->mode ?? null,
			'customer' => $session->customer ?? null,
		];

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
	public function refund(string $paymentIntentId, ?int $amountInMinorUnits = null): array {

		$params = [
			'payment_intent' => $paymentIntentId,
			'amount'         => $amountInMinorUnits,
		];

		try {
			$refund = $this->client->refunds->create($this->withoutNullValues($params));
			return ['id' => $refund->id, 'status' => $refund->status];
		} catch (\Throwable $e) {
			throw new PairException('Stripe refund failed: ' . $e->getMessage(), ErrorCodes::STRIPE_ERROR, $e);
		}

	}

	/**
	 * Verify a Stripe webhook and run a type-specific handler when provided.
	 *
	 * @param	array<string, callable>	$handlers	Handlers keyed by Stripe event type or "*" fallback.
	 */
	public function webhookResponse(string $payload, string $signature, array $handlers = []): JsonResponse {

		$event = $this->constructWebhookEvent($payload, $signature);

		return $this->webhookResponseFromEvent($event, $handlers);

	}

	/**
	 * Run a handler for an already verified Stripe event and return a standard acknowledgement.
	 *
	 * @param	array<string, callable>	$handlers	Handlers keyed by Stripe event type or "*" fallback.
	 */
	public function webhookResponseFromEvent(object $event, array $handlers = []): JsonResponse {

		$type = isset($event->type) ? (string)$event->type : '';
		$handler = $handlers[$type] ?? $handlers['*'] ?? null;

		if ($handler) {

			if (!is_callable($handler)) {
				throw new \InvalidArgumentException('Stripe webhook handler for "' . $type . '" must be callable.');
			}

			$handler($event);

		}

		return new JsonResponse([
			'received' => true,
			'type' => $type,
		]);

	}

	/**
	 * Helper to convert major units (e.g. 12.34 EUR) to minor units (e.g. 1234 cents).
	 */
	public static function toMinorUnits(float $amount): int {

		return (int) round($amount * 100);

	}

	/**
	 * Remove null values recursively from Stripe request payloads.
	 *
	 * @return	array<string, mixed>
	 */
	private function withoutNullValues(array $values): array {

		$filtered = [];

		foreach ($values as $key => $value) {

			if (is_null($value)) {
				continue;
			}

			if (is_array($value)) {
				$value = $this->withoutNullValues($value);
			}

			$filtered[$key] = $value;

		}

		return $filtered;

	}

}
