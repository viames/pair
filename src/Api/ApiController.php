<?php

namespace Pair\Api;

use Pair\Core\Env;
use Pair\Core\Application;
use Pair\Core\Controller;
use Pair\Exceptions\PairException;
use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;
use Pair\Http\TextResponse;
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
	 * Require an authenticated user and return either the user or an explicit UNAUTHORIZED response.
	 */
	public function requireAuthOrResponse(): User|ApiErrorResponse {

		$user = $this->getUser();

		if (!$user) {
			return $this->errorResponse('UNAUTHORIZED');
		}

		return $user;

	}

	/**
	 * Require an authenticated user. Sends 401 error if not authenticated.
	 */
	public function requireAuth(): User {

		$result = $this->requireAuthOrResponse();

		// Preserve the legacy terminate-on-error contract for existing callers.
		if ($result instanceof ApiErrorResponse) {
			$result->send();
			throw new \LogicException('ApiController::requireAuth() expected ApiErrorResponse::send() to terminate the request.');
		}

		return $result;

	}

	/**
	 * Require a valid Bearer token and return either the token or an explicit AUTH_TOKEN_MISSING response.
	 */
	public function requireBearerOrResponse(): string|ApiErrorResponse {

		if (!$this->bearerToken) {
			return $this->errorResponse('AUTH_TOKEN_MISSING');
		}

		return $this->bearerToken;

	}

	/**
	 * Require a valid Bearer token. Sends 401 error if missing.
	 */
	public function requireBearer(): string {

		$result = $this->requireBearerOrResponse();

		// Preserve the legacy terminate-on-error contract for existing callers.
		if ($result instanceof ApiErrorResponse) {
			$result->send();
			throw new \LogicException('ApiController::requireBearer() expected ApiErrorResponse::send() to terminate the request.');
		}

		return $result;

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
	public function whatsappWebhookAction(): ResponseInterface {

		$method = strtoupper($this->request->method());

		if ('GET' === $method) {
			return $this->verifyWhatsAppWebhookChallenge();
		}

		if ('POST' === $method) {
			return $this->receiveWhatsAppWebhook();
		}

		return $this->errorResponse('METHOD_NOT_ALLOWED', ['expected' => 'GET or POST', 'actual' => $method]);

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
	 * @return	mixed		The value returned by the middleware chain or destination when the pipeline reaches it.
	 */
	public function runMiddleware(callable $destination): mixed {

		return $this->pipeline->run($this->request, function (Request $request) use ($destination): mixed {
			return $destination();
		});

	}

	/**
	 * Build an explicit API error response from the shared error registry.
	 *
	 * @param	array<string, mixed>	$extra	Additional payload fields merged into the error body.
	 */
	protected function errorResponse(string $errorCode, array $extra = []): ApiErrorResponse {

		return ApiResponse::errorResponse($errorCode, $extra);

	}

	/**
	 * Build an explicit JSON response for API actions.
	 */
	protected function jsonResponse(\stdClass|array|null $payload, int $httpCode = 200): JsonResponse {

		return new JsonResponse($payload, $httpCode);

	}

	/**
	 * Build an explicit text response for non-JSON API endpoints such as webhook challenges.
	 */
	protected function textResponse(string $content, int $httpCode = 200, string $contentType = 'text/plain; charset=utf-8'): TextResponse {

		return new TextResponse($content, $httpCode, $contentType);

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
	 * Receive a POST webhook delivery from Meta, validate it, and return the normalized response.
	 */
	private function receiveWhatsAppWebhook(): ResponseInterface {

		$client = $this->whatsAppCloudClient();

		if (!$client->webhookAppSecretSet()) {
			return $this->errorResponse('INTERNAL_SERVER_ERROR', ['detail' => 'Missing WHATSAPP_CLOUD_APP_SECRET']);
		}

		$payload = $this->request->rawBody();

		if ('' === trim($payload)) {
			return $this->errorResponse('BAD_REQUEST', ['detail' => 'Empty WhatsApp webhook payload']);
		}

		if (!$client->verifyWebhookSignature($payload)) {
			return $this->errorResponse('FORBIDDEN', ['detail' => 'Invalid WhatsApp webhook signature']);
		}

		try {
			$decodedPayload = $client->decodeWebhookPayload($payload);
		} catch (PairException $e) {
			return $this->errorResponse('BAD_REQUEST', ['detail' => $e->getMessage()]);
		}

		$events = $client->extractWebhookEvents($decodedPayload);
		$responseData = $this->handleWhatsAppWebhook($events, $decodedPayload);

		return $this->jsonResponse($responseData);

	}

	/**
	 * Return the challenge requested by Meta during webhook verification as an explicit text response.
	 */
	private function verifyWhatsAppWebhookChallenge(): ResponseInterface {

		$client = $this->whatsAppCloudClient();

		if (!$client->webhookVerifyTokenSet()) {
			return $this->errorResponse('INTERNAL_SERVER_ERROR', ['detail' => 'Missing WHATSAPP_CLOUD_WEBHOOK_VERIFY_TOKEN']);
		}

		try {
			$challenge = $client->verifyWebhookChallenge();
		} catch (PairException $e) {
			return $this->errorResponse('FORBIDDEN', ['detail' => $e->getMessage()]);
		}

		return $this->textResponse($challenge);

	}

	/**
	 * Catch-all for missing action methods. Returns an explicit 404 API error response.
	 */
	public function __call(mixed $name, mixed $arguments): mixed {

		return $this->errorResponse('NOT_FOUND', ['action' => str_replace('Action', '', $name)]);

	}

}
