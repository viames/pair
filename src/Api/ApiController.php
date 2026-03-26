<?php

namespace Pair\Api;

use Pair\Core\Env;
use Pair\Core\Application;
use Pair\Core\Controller;
use Pair\Exceptions\PairException;
use Pair\Models\Session;
use Pair\Models\User;
use Pair\Services\WhatsAppCloudClient;

/**
 * Abstract base class for API controllers. Extends the standard Controller
 * with API-specific features: authentication helpers, JSON request handling,
 * and middleware pipeline support.
 */
abstract class ApiController extends Controller {

	/**
	 * The Bearer token string for OAuth2 authentication.
	 */
	protected ?string $bearerToken = null;

	/**
	 * The session object for session-based authentication.
	 */
	protected ?Session $session = null;

	/**
	 * The parsed HTTP request.
	 */
	protected Request $request;

	/**
	 * The middleware pipeline.
	 */
	private MiddlewarePipeline $pipeline;

	/**
	 * Cached WhatsApp Cloud API client.
	 */
	private ?WhatsAppCloudClient $whatsAppCloudClient = null;

	/**
	 * Initialize the API controller with request and middleware pipeline.
	 * Subclasses that override _init() must call parent::_init().
	 */
	protected function _init(): void {

		$this->request = new Request();
		$this->pipeline = new MiddlewarePipeline();
		$this->registerDefaultMiddleware();

	}

	/**
	 * Set the Bearer token string.
	 */
	public function setBearerToken(string $bearerToken): void {

		$this->bearerToken = $bearerToken;

	}

	/**
	 * Set the current Session object.
	 */
	public function setSession(Session $session): void {

		$this->session = $session;

	}

	/**
	 * Get the currently authenticated user, or null if not authenticated.
	 */
	public function getUser(): ?User {

		$app = Application::getInstance();
		$user = $app->currentUser;

		if ($user and $user->isLoaded()) {
			return $user;
		}

		return null;

	}

	/**
	 * Require an authenticated user. Sends 401 error if not authenticated.
	 */
	public function requireAuth(): User {

		$user = $this->getUser();

		if (!$user) {
			ApiResponse::error('UNAUTHORIZED');
		}

		return $user;

	}

	/**
	 * Require a valid Bearer token. Sends 401 error if missing.
	 */
	public function requireBearer(): string {

		if (!$this->bearerToken) {
			ApiResponse::error('AUTH_TOKEN_MISSING');
		}

		return $this->bearerToken;

	}

	/**
	 * Get the parsed JSON body from the request.
	 */
	public function getJsonBody(): mixed {

		return $this->request->json();

	}

	/**
	 * Require a JSON POST request. Validates Content-Type and HTTP method.
	 * Returns the parsed JSON body or sends a 400 error.
	 */
	public function requireJsonPost(): mixed {

		if ($this->request->method() !== 'POST') {
			ApiResponse::error('METHOD_NOT_ALLOWED', ['expected' => 'POST', 'actual' => $this->request->method()]);
		}

		if (!$this->request->isJson()) {
			ApiResponse::error('UNSUPPORTED_MEDIA_TYPE', ['expected' => 'application/json']);
		}

		$body = $this->request->json();

		if (is_null($body)) {
			ApiResponse::error('BAD_REQUEST', ['detail' => 'Invalid or empty JSON body']);
		}

		return $body;

	}

	/**
	 * Add a middleware to the pipeline.
	 */
	public function middleware(Middleware $middleware): void {

		$this->pipeline->add($middleware);

	}

	/**
	 * Ready-to-use unauthenticated webhook endpoint for Meta WhatsApp Cloud API.
	 *
	 * Route:
	 * - GET  /api/whatsappWebhook
	 * - POST /api/whatsappWebhook
	 *
	 * GET validates the `hub.*` challenge and returns the plain-text challenge.
	 * POST validates the webhook signature, decodes the payload, extracts normalized
	 * events, and forwards them to handleWhatsAppWebhook().
	 */
	public function whatsappWebhookAction(): void {

		$method = strtoupper($this->request->method());

		if ('GET' === $method) {
			$this->verifyWhatsAppWebhookChallenge();
			return;
		}

		if ('POST' === $method) {
			$this->receiveWhatsAppWebhook();
			return;
		}

		ApiResponse::error('METHOD_NOT_ALLOWED', ['expected' => 'GET or POST', 'actual' => $method]);

	}

	/**
	 * Register the default middleware stack for API controllers.
	 */
	protected function registerDefaultMiddleware(): void {

		if (!Env::get('PAIR_API_RATE_LIMIT_ENABLED')) {
			return;
		}

		$maxAttempts = max(1, intval(Env::get('PAIR_API_RATE_LIMIT_MAX_ATTEMPTS') ?? 60));
		$decaySeconds = max(1, intval(Env::get('PAIR_API_RATE_LIMIT_DECAY_SECONDS') ?? 60));

		// attach the default throttle globally so API controllers are protected out of the box.
		$this->pipeline->add(new ThrottleMiddleware($maxAttempts, $decaySeconds));

	}

	/**
	 * Override this hook in the application controller to process inbound WhatsApp events.
	 *
	 * The default implementation simply acknowledges receipt.
	 *
	 * @param	array	$events		Normalized events extracted from the Meta webhook payload.
	 * @param	array	$payload	Original decoded webhook payload.
	 * @return	array|\stdClass|null
	 */
	protected function handleWhatsAppWebhook(array $events, array $payload): \stdClass|array|null {

		return [
			'received' => true,
			'events' => count($events),
		];

	}

	/**
	 * Run the middleware pipeline, then execute the destination callable.
	 *
	 * @param	callable	$destination	The final action to execute after all middleware.
	 */
	public function runMiddleware(callable $destination): void {

		$this->pipeline->run($this->request, function () use ($destination) {
			$destination();
		});

	}

	/**
	 * Return the lazily built WhatsApp Cloud API client.
	 */
	private function whatsAppCloudClient(): WhatsAppCloudClient {

		if (!$this->whatsAppCloudClient) {
			$this->whatsAppCloudClient = new WhatsAppCloudClient();
		}

		return $this->whatsAppCloudClient;

	}

	/**
	 * Receive a POST webhook delivery from Meta, validate it, and dispatch normalized events.
	 */
	private function receiveWhatsAppWebhook(): void {

		$client = $this->whatsAppCloudClient();

		if (!$client->webhookAppSecretSet()) {
			ApiResponse::error('INTERNAL_SERVER_ERROR', ['detail' => 'Missing WHATSAPP_CLOUD_APP_SECRET']);
		}

		$payload = $this->request->rawBody();

		if ('' === trim($payload)) {
			ApiResponse::error('BAD_REQUEST', ['detail' => 'Empty WhatsApp webhook payload']);
		}

		if (!$client->verifyWebhookSignature($payload)) {
			ApiResponse::error('FORBIDDEN', ['detail' => 'Invalid WhatsApp webhook signature']);
		}

		try {
			$decodedPayload = $client->decodeWebhookPayload($payload);
		} catch (PairException $e) {
			ApiResponse::error('BAD_REQUEST', ['detail' => $e->getMessage()]);
		}

		$events = $client->extractWebhookEvents($decodedPayload);
		$responseData = $this->handleWhatsAppWebhook($events, $decodedPayload);

		ApiResponse::respond($responseData);

	}

	/**
	 * Return the challenge requested by Meta during webhook verification.
	 */
	private function verifyWhatsAppWebhookChallenge(): void {

		$client = $this->whatsAppCloudClient();

		if (!$client->webhookVerifyTokenSet()) {
			ApiResponse::error('INTERNAL_SERVER_ERROR', ['detail' => 'Missing WHATSAPP_CLOUD_WEBHOOK_VERIFY_TOKEN']);
		}

		try {
			$challenge = $client->verifyWebhookChallenge();
		} catch (PairException $e) {
			ApiResponse::error('FORBIDDEN', ['detail' => $e->getMessage()]);
		}

		http_response_code(200);
		header('Content-Type: text/plain; charset=utf-8');
		echo $challenge;

	}

	/**
	 * Catch-all for missing action methods. Sends a 404 error.
	 */
	public function __call(mixed $name, mixed $arguments): void {

		ApiResponse::error('NOT_FOUND', ['action' => str_replace('Action', '', $name)]);

	}

}
