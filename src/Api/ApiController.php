<?php

namespace Pair\Api;

use Pair\Core\Application;
use Pair\Core\Controller;
use Pair\Models\Session;
use Pair\Models\User;

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
	 * Initialize the API controller with request and middleware pipeline.
	 * Subclasses that override _init() must call parent::_init().
	 */
	protected function _init(): void {

		$this->request = new Request();
		$this->pipeline = new MiddlewarePipeline();

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
	 * Catch-all for missing action methods. Sends a 404 error.
	 */
	public function __call(mixed $name, mixed $arguments): void {

		ApiResponse::error('NOT_FOUND', ['action' => str_replace('Action', '', $name)]);

	}

}
