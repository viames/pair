<?php

namespace Pair\Api;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Exceptions\PairException;
use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;
use Pair\Http\TextResponse;
use Pair\Models\ApiToken;
use Pair\Models\Session;
use Pair\Models\User;
use Pair\Models\UserPasskey;
use Pair\Services\PasskeyAuth;
use Pair\Services\PasskeyFlow;
use Pair\Services\WhatsAppCloudClient;
use Pair\Web\Controller;

/**
 * Abstract base class for API controllers. Extends the explicit Pair v4 web
 * controller with API-specific authentication helpers, JSON request handling,
 * and middleware pipeline support.
 */
abstract class ApiController extends Controller {

	/**
	 * The Bearer token string for OAuth2 authentication.
	 */
	protected ?string $bearerToken = null;

	/**
	 * The mobile/API token row resolved from the Bearer token.
	 */
	protected ?ApiToken $apiToken = null;

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
	 * Bootstrap the API controller without falling back to the legacy MVC bridge.
	 */
	protected function boot(): void {

		$this->request = new Request();
		$this->pipeline = new MiddlewarePipeline();
		$this->_init();

	}

	/**
	 * Initialize the API controller with request and middleware pipeline.
	 * Subclasses that override _init() must call parent::_init().
	 */
	protected function _init(): void {

		$this->registerDefaultMiddleware();

	}

	/**
	 * Set the Bearer token string.
	 */
	public function setBearerToken(string $bearerToken): void {

		$this->bearerToken = $bearerToken;

	}

	/**
	 * Set the mobile/API token row resolved during bootstrap.
	 */
	public function setApiToken(ApiToken $apiToken): void {

		$this->apiToken = $apiToken;

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
			exit();
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
			exit();
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
	 * Require a JSON POST request and return either the parsed body or an explicit API error response.
	 */
	public function requireJsonPostOrResponse(): array|ApiErrorResponse {

		if ($this->request->method() !== 'POST') {
			return $this->errorResponse('METHOD_NOT_ALLOWED', ['expected' => 'POST', 'actual' => $this->request->method()]);
		}

		if (!$this->request->isJson()) {
			return $this->errorResponse('UNSUPPORTED_MEDIA_TYPE', ['expected' => 'application/json']);
		}

		$body = $this->request->json();

		if (is_null($body)) {
			return $this->errorResponse('BAD_REQUEST', ['detail' => ApiResponse::localizedMessage('API_DETAIL_INVALID_OR_EMPTY_JSON_BODY')]);
		}

		return $body;

	}

	/**
	 * Require a JSON POST request. Validates Content-Type and HTTP method.
	 * Returns the parsed JSON body or sends a 400 error.
	 */
	public function requireJsonPost(): mixed {

		$result = $this->requireJsonPostOrResponse();

		// Preserve the legacy terminate-on-error contract for existing callers.
		if ($result instanceof ApiErrorResponse) {
			$result->send();
			exit();
		}

		return $result;

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
	 * Ready-to-use mobile auth endpoint for login, refresh, current user and logout.
	 *
	 * Routes:
	 * - POST /api/auth/login
	 * - POST /api/auth/register
	 * - POST /api/auth/refresh
	 * - GET  /api/auth/me
	 * - POST /api/auth/logout
	 * - POST /api/auth/passkey/options
	 * - POST /api/auth/passkey/verify
	 * - GET|POST|DELETE /api/auth/passkeys[/options|/verify|/{id}]
	 */
	public function authAction(): ResponseInterface {

		$operation = strtolower((string)($this->router->getParam('operation') ?: $this->router->getParam(0)));
		$subOperation = strtolower((string)$this->router->getParam(1));
		$method = strtoupper($this->request->method());

		if ('login' == $operation and 'POST' == $method) {
			return $this->mobileAuthLogin();
		}

		if ('register' == $operation and 'POST' == $method) {
			return $this->mobileAuthRegister();
		}

		if ('refresh' == $operation and 'POST' == $method) {
			return $this->mobileAuthRefresh();
		}

		if ('me' == $operation and 'GET' == $method) {
			return $this->mobileAuthMe();
		}

		if ('logout' == $operation and in_array($method, ['POST', 'DELETE'])) {
			return $this->mobileAuthLogout();
		}

		if ('passkey' == $operation and 'options' == $subOperation and 'POST' == $method) {
			return $this->mobilePasskeyLoginOptions();
		}

		if ('passkey' == $operation and 'verify' == $subOperation and 'POST' == $method) {
			return $this->mobilePasskeyLoginVerify();
		}

		if ('passkeys' == $operation and '' === $subOperation and 'GET' == $method) {
			return $this->mobilePasskeyList();
		}

		if ('passkeys' == $operation and 'options' == $subOperation and 'POST' == $method) {
			return $this->mobilePasskeyRegisterOptions();
		}

		if ('passkeys' == $operation and 'verify' == $subOperation and 'POST' == $method) {
			return $this->mobilePasskeyRegisterVerify();
		}

		if ('passkeys' == $operation and ctype_digit($subOperation) and 'DELETE' == $method) {
			return $this->mobilePasskeyRevoke((int)$subOperation);
		}

		return $this->errorResponse('NOT_FOUND', [
			'action' => 'auth',
			'operation' => $operation,
		]);

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
	 * Return true when an API action may run before bearer or session authentication.
	 */
	public function allowsUnauthenticatedAction(string $routerAction, string $methodName): bool {

		return false;

	}

	/**
	 * Return the public user snapshot sent to mobile clients.
	 *
	 * @return	array<string, mixed>
	 */
	protected function mobileAuthUserSnapshot(User $user): array {

		return [
			'id'		=> (int)$user->id,
			'username'	=> (string)$user->username,
			'email'		=> $user->email,
			'name'		=> (string)$user->name,
			'surname'	=> (string)$user->surname,
		];

	}

	/**
	 * Return optional application context for mobile auth responses.
	 *
	 * @return	array<string, mixed>|null
	 */
	protected function mobileAuthContext(User $user): ?array {

		return null;

	}

	/**
	 * Create a user for mobile registration. Applications should override this hook.
	 */
	protected function mobileAuthRegisterUser(array $body): User|ApiErrorResponse {

		return $this->errorResponse('NOT_IMPLEMENTED');

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
	 * Handle POST /api/auth/login without creating or mutating PHP web sessions.
	 */
	private function mobileAuthLogin(): ResponseInterface {

		$body = $this->requireJsonPostOrResponse();

		if ($body instanceof ApiErrorResponse) {
			return $body;
		}

		$identifier = trim((string)($body['email'] ?? $body['username'] ?? ''));
		$password = (string)($body['password'] ?? '');

		if ('' === $identifier or '' === $password) {
			return $this->errorResponse('AUTH_MISSING_FIELDS');
		}

		$userClass = Application::getInstance()->userClass;
		$result = $userClass::doTokenLogin($identifier, $password);

		if ($result->error) {
			return $this->errorResponse('AUTH_INVALID_CREDENTIALS');
		}

		$user = new $userClass((int)$result->userId);

		if (!$user instanceof User or !$user->isLoaded()) {
			return $this->errorResponse('AUTH_INVALID_CREDENTIALS');
		}

		return $this->issueMobileAuthResponse($user, $body);

	}

	/**
	 * Handle POST /api/auth/register through an application-provided user creation hook.
	 */
	private function mobileAuthRegister(): ResponseInterface {

		$body = $this->requireJsonPostOrResponse();

		if ($body instanceof ApiErrorResponse) {
			return $body;
		}

		$user = $this->mobileAuthRegisterUser($body);

		if ($user instanceof ApiErrorResponse) {
			return $user;
		}

		if (!$user->isLoaded()) {
			return $this->errorResponse('INVALID_OBJECT_DATA');
		}

		return $this->issueMobileAuthResponse($user, $body, 201);

	}

	/**
	 * Handle POST /api/auth/refresh with rotating refresh tokens.
	 */
	private function mobileAuthRefresh(): ResponseInterface {

		$body = $this->requireJsonPostOrResponse();

		if ($body instanceof ApiErrorResponse) {
			return $body;
		}

		$refreshToken = trim((string)($body['refresh_token'] ?? ''));

		if ('' === $refreshToken) {
			return $this->errorResponse('AUTH_REFRESH_TOKEN_MISSING');
		}

		$issued = ApiToken::refresh($refreshToken);

		if (!$issued) {
			return $this->errorResponse('AUTH_REFRESH_TOKEN_INVALID');
		}

		$user = $issued['token']->getUser();

		if (!$user or !$this->mobileAuthUserCanUseTokens($user)) {
			$issued['token']->revoke();
			return $this->errorResponse('AUTH_REFRESH_TOKEN_INVALID');
		}

		return $this->dataResponse($this->mobileAuthPayload($user, $issued));

	}

	/**
	 * Handle GET /api/auth/me for verified startup bootstrap.
	 */
	private function mobileAuthMe(): ResponseInterface {

		$user = $this->requireAuthOrResponse();

		if ($user instanceof ApiErrorResponse) {
			return $user;
		}

		$payload = [
			'user' => $this->mobileAuthUserSnapshot($user),
		];

		$context = $this->mobileAuthContext($user);

		if (!is_null($context)) {
			$payload['context'] = $context;
		}

		return $this->dataResponse($payload);

	}

	/**
	 * Handle POST or DELETE /api/auth/logout by revoking the current token and optional refresh token.
	 */
	private function mobileAuthLogout(): ResponseInterface {

		$body = [];

		if ($this->request->isJson()) {
			$decoded = $this->request->json();
			$body = is_array($decoded) ? $decoded : [];
		}

		if ($this->apiToken) {
			$this->apiToken->revoke();
		}

		$refreshToken = trim((string)($body['refresh_token'] ?? ''));

		if ('' !== $refreshToken) {
			ApiToken::revokeByRefreshToken($refreshToken);
		}

		return $this->dataResponse(new \stdClass());

	}

	/**
	 * Return the safe metadata exposed by mobile passkey management endpoints.
	 *
	 * @return	array<string, mixed>
	 */
	private function mobilePasskeyData(UserPasskey $passkey): array {

		return [
			'id' => (int)$passkey->id,
			'label' => $passkey->label,
			'created_at' => $passkey->createdAt?->format(\DateTimeInterface::ATOM),
			'last_used_at' => $passkey->lastUsedAt?->format(\DateTimeInterface::ATOM),
			'transports' => $passkey->getTransports(),
		];

	}

	/**
	 * Handle POST /api/auth/passkey/options for discoverable native login.
	 */
	private function mobilePasskeyLoginOptions(): ResponseInterface {

		$body = $this->requireJsonPostOrResponse();

		if ($body instanceof ApiErrorResponse) {
			return $body;
		}

		$flow = new PasskeyFlow();

		try {
			$options = (new PasskeyAuth(requireUserVerification: true))->beginAuthentication(null, [], [
				'userVerification' => 'required',
			]);
			$flow->close();
		} catch (\Throwable $throwable) {
			$flow->destroy();
			throw $throwable;
		}

		return $this->dataResponse([
			'flow_id' => $flow->id(),
			'publicKey' => $options,
		]);

	}

	/**
	 * Handle POST /api/auth/passkey/verify and issue the standard mobile Bearer session.
	 */
	private function mobilePasskeyLoginVerify(): ResponseInterface {

		$body = $this->requireJsonPostOrResponse();

		if ($body instanceof ApiErrorResponse) {
			return $body;
		}

		if (!is_string($body['flow_id'] ?? null)) {
			return $this->errorResponse('BAD_REQUEST');
		}

		$flowId = trim($body['flow_id']);
		$credential = (isset($body['credential']) and is_array($body['credential'])) ? $body['credential'] : null;

		if ('' === $flowId or !$credential) {
			return $this->errorResponse('BAD_REQUEST');
		}

		try {
			$flow = new PasskeyFlow($flowId);
		} catch (PairException) {
			return $this->errorResponse('BAD_REQUEST');
		}

		try {
			$userId = (new PasskeyAuth(requireUserVerification: true))->verifyAuthentication($credential);
		} finally {
			$flow->destroy();
		}

		if (!$userId) {
			return $this->errorResponse('AUTH_INVALID_CREDENTIALS');
		}

		$userClass = Application::getInstance()->userClass;
		$result = $userClass::doTokenLoginById($userId);

		if ($result->error or !$result->userId) {
			return $this->errorResponse('AUTH_INVALID_CREDENTIALS');
		}

		$user = new $userClass((int)$result->userId);

		if (!$user instanceof User or !$this->mobileAuthUserCanUseTokens($user)) {
			return $this->errorResponse('AUTH_INVALID_CREDENTIALS');
		}

		return $this->issueMobileAuthResponse($user, $body);

	}

	/**
	 * Handle GET /api/auth/passkeys for the current Bearer-authenticated user.
	 */
	private function mobilePasskeyList(): ResponseInterface {

		$user = $this->requireAuthOrResponse();

		if ($user instanceof ApiErrorResponse) {
			return $user;
		}

		$items = [];

		foreach (UserPasskey::getActiveByUserId((int)$user->id) as $passkey) {
			$items[] = $this->mobilePasskeyData($passkey);
		}

		return $this->dataResponse(['items' => $items]);

	}

	/**
	 * Handle POST /api/auth/passkeys/options for the current Bearer-authenticated user.
	 */
	private function mobilePasskeyRegisterOptions(): ResponseInterface {

		$user = $this->requireAuthOrResponse();

		if ($user instanceof ApiErrorResponse) {
			return $user;
		}

		$body = $this->requireJsonPostOrResponse();

		if ($body instanceof ApiErrorResponse) {
			return $body;
		}

		if (isset($body['display_name']) and !is_string($body['display_name'])) {
			return $this->errorResponse('BAD_REQUEST');
		}

		$displayName = trim((string)($body['display_name'] ?? ''));

		if (mb_strlen($displayName) > 120) {
			return $this->errorResponse('BAD_REQUEST');
		}
		$flow = new PasskeyFlow();

		try {
			$options = (new PasskeyAuth(requireUserVerification: true))->beginRegistration(
				$user,
				'' === $displayName ? null : $displayName,
				[],
				[
					'attestation' => 'none',
					'residentKey' => 'required',
					'userVerification' => 'required',
				]
			);
			$flow->close();
		} catch (\Throwable $throwable) {
			$flow->destroy();
			throw $throwable;
		}

		return $this->dataResponse([
			'flow_id' => $flow->id(),
			'publicKey' => $options,
		]);

	}

	/**
	 * Handle POST /api/auth/passkeys/verify for the current Bearer-authenticated user.
	 */
	private function mobilePasskeyRegisterVerify(): ResponseInterface {

		$user = $this->requireAuthOrResponse();

		if ($user instanceof ApiErrorResponse) {
			return $user;
		}

		$body = $this->requireJsonPostOrResponse();

		if ($body instanceof ApiErrorResponse) {
			return $body;
		}

		if (!is_string($body['flow_id'] ?? null)
			or (isset($body['label']) and !is_string($body['label']))) {
			return $this->errorResponse('BAD_REQUEST');
		}

		$flowId = trim($body['flow_id']);
		$credential = (isset($body['credential']) and is_array($body['credential'])) ? $body['credential'] : null;
		$label = trim((string)($body['label'] ?? ''));

		if ('' === $flowId or !$credential or mb_strlen($label) > 120) {
			return $this->errorResponse('BAD_REQUEST');
		}

		try {
			$flow = new PasskeyFlow($flowId);
		} catch (PairException) {
			return $this->errorResponse('BAD_REQUEST');
		}

		try {
			$passkey = (new PasskeyAuth(requireUserVerification: true))->registerCredential(
				$user,
				$credential,
				'' === $label ? null : $label
			);
		} finally {
			$flow->destroy();
		}

		return $this->dataResponse(['passkey' => $this->mobilePasskeyData($passkey)], httpCode: 201);

	}

	/**
	 * Handle DELETE /api/auth/passkeys/{id} with an ownership check.
	 */
	private function mobilePasskeyRevoke(int $passkeyId): ResponseInterface {

		$user = $this->requireAuthOrResponse();

		if ($user instanceof ApiErrorResponse) {
			return $user;
		}

		$passkey = new UserPasskey($passkeyId);

		if (!$passkey->isLoaded() or $passkey->userId !== $user->id) {
			return $this->errorResponse('NOT_FOUND');
		}

		if (!$passkey->isRevoked() and !$passkey->revoke()) {
			return $this->errorResponse('INTERNAL_SERVER_ERROR');
		}

		return $this->dataResponse(new \stdClass());

	}

	/**
	 * Issue a new mobile bearer session response for an authenticated user.
	 */
	private function issueMobileAuthResponse(User $user, array $body, int $httpCode = 200): ResponseInterface {

		$remember = $this->truthy($body['remember_me'] ?? true);
		$issued = ApiToken::issueForUser(
			$user,
			$remember,
			$this->metadataString($body['device_name'] ?? $body['deviceName'] ?? null),
			$_SERVER['REMOTE_ADDR'] ?? null,
			$_SERVER['HTTP_USER_AGENT'] ?? null
		);

		return $this->dataResponse($this->mobileAuthPayload($user, $issued), httpCode: $httpCode);

	}

	/**
	 * Build the standard mobile auth payload from an issued token pair.
	 *
	 * @param	array{token: ApiToken, accessToken: string, refreshToken: string|null}	$issued	Issued token data.
	 * @return	array<string, mixed>
	 */
	private function mobileAuthPayload(User $user, array $issued): array {

		$payload = [
			'user'			=> $this->mobileAuthUserSnapshot($user),
			'access_token'	=> $issued['accessToken'],
			'expires_in'	=> ApiToken::getAccessLifetimeSeconds(),
			'expires_at'	=> $issued['token']->accessExpiresAtIso(),
			'token_type'	=> 'Bearer',
		];

		if ($issued['refreshToken']) {
			$payload['refresh_token'] = $issued['refreshToken'];
		}

		$context = $this->mobileAuthContext($user);

		if (!is_null($context)) {
			$payload['context'] = $context;
		}

		return $payload;

	}

	/**
	 * Return true when the user may keep using API tokens.
	 */
	private function mobileAuthUserCanUseTokens(User $user): bool {

		return $user->isLoaded() and (bool)$user->enabled and (int)$user->faults <= 9;

	}

	/**
	 * Normalize a boolean-like request value.
	 */
	private function truthy(mixed $value): bool {

		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return 0 !== (int)$value;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);

	}

	/**
	 * Return an optional string metadata value from a request field.
	 */
	private function metadataString(mixed $value): ?string {

		$value = trim((string)$value);

		return strlen($value) ? $value : null;

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
			return $this->errorResponse('INTERNAL_SERVER_ERROR', ['detail' => ApiResponse::localizedMessage('API_DETAIL_MISSING_WHATSAPP_APP_SECRET')]);
		}

		$payload = $this->request->rawBody();

		if ('' === trim($payload)) {
			return $this->errorResponse('BAD_REQUEST', ['detail' => ApiResponse::localizedMessage('API_DETAIL_EMPTY_WHATSAPP_WEBHOOK_PAYLOAD')]);
		}

		if (!$client->verifyWebhookSignature($payload)) {
			return $this->errorResponse('FORBIDDEN', ['detail' => ApiResponse::localizedMessage('API_DETAIL_INVALID_WHATSAPP_WEBHOOK_SIGNATURE')]);
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
			return $this->errorResponse('INTERNAL_SERVER_ERROR', ['detail' => ApiResponse::localizedMessage('API_DETAIL_MISSING_WHATSAPP_WEBHOOK_VERIFY_TOKEN')]);
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
