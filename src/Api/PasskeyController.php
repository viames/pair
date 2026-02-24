<?php

namespace Pair\Api;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Models\User;
use Pair\Models\UserPasskey;
use Pair\Services\PasskeyAuth;

/**
 * API controller base class with ready-to-use Passkey endpoints.
 *
 * Endpoints:
 * - POST /api/passkey/login/options
 * - POST /api/passkey/login/verify
 * - POST /api/passkey/register/options        (requires sid)
 * - POST /api/passkey/register/verify         (requires sid)
 * - GET  /api/passkey/list                    (requires sid)
 * - DELETE /api/passkey/revoke/{id}           (requires sid)
 *
 * Applications can extend this class directly:
 * class ApiController extends \Pair\Api\PasskeyController {}
 */
abstract class PasskeyController extends CrudController {

	/**
	 * Cached PasskeyAuth service.
	 */
	private ?PasskeyAuth $passkeyAuth = null;

	/**
	 * Handle all passkey endpoints by URL params.
	 */
	public function passkeyAction(): void {

		$resource = strtolower((string)$this->router->getParam(0));
		$operation = strtolower((string)$this->router->getParam(1));
		$method = strtoupper($this->request->method());

		if ('login' == $resource and 'options' == $operation and 'POST' == $method) {
			$this->passkeyLoginOptions();
			return;
		}

		if ('login' == $resource and 'verify' == $operation and 'POST' == $method) {
			$this->passkeyLoginVerify();
			return;
		}

		if ('register' == $resource and 'options' == $operation and 'POST' == $method) {
			$this->passkeyRegisterOptions();
			return;
		}

		if ('register' == $resource and 'verify' == $operation and 'POST' == $method) {
			$this->passkeyRegisterVerify();
			return;
		}

		if ('list' == $resource and 'GET' == $method) {
			$this->passkeyList();
			return;
		}

		if ('revoke' == $resource and 'DELETE' == $method) {
			$this->passkeyRevoke();
			return;
		}

		ApiResponse::error('NOT_FOUND', [
			'action' => 'passkey',
			'resource' => $resource,
			'operation' => $operation
		]);

	}

	/**
	 * Returns optional JSON body for POST requests.
	 *
	 * Empty JSON body is converted to an empty array.
	 *
	 * @return array<string,mixed>
	 */
	private function optionalJsonPost(): array {

		if ('POST' !== $this->request->method()) {
			ApiResponse::error('METHOD_NOT_ALLOWED', ['expected' => 'POST', 'actual' => $this->request->method()]);
		}

		if (!$this->request->isJson()) {
			ApiResponse::error('UNSUPPORTED_MEDIA_TYPE', ['expected' => 'application/json']);
		}

		$body = $this->request->json();

		if (is_null($body)) {
			return [];
		}

		if (!is_array($body)) {
			ApiResponse::error('INVALID_OBJECT', ['field' => 'body']);
		}

		return $body;

	}

	/**
	 * Resolve an user by login identifier (username or email by config).
	 */
	private function getUserByLoginIdentifier(string $identifier): ?User {

		$identifier = trim($identifier);

		if ('' === $identifier) {
			return null;
		}

		$field = Env::get('PAIR_AUTH_BY_EMAIL') ? 'email' : 'username';
		$userClass = Application::getInstance()->userClass;
		$tableName = defined($userClass . '::TABLE_NAME') ? $userClass::TABLE_NAME : 'users';
		$query = 'SELECT * FROM `' . $tableName . '` WHERE `' . $field . '` = ? LIMIT 1';

		$user = $userClass::getObjectByQuery($query, [$identifier]);

		return is_a($user, 'Pair\Models\User') ? $user : null;

	}

	/**
	 * Returns normalized timezone or UTC fallback.
	 */
	private function normalizeTimezone(?string $timezone): string {

		$timezone = trim((string)$timezone);

		if ('' !== $timezone and in_array($timezone, \DateTimeZone::listIdentifiers())) {
			return $timezone;
		}

		return 'UTC';

	}

	/**
	 * Returns the passkey service instance.
	 */
	private function passkey(): PasskeyAuth {

		if (!$this->passkeyAuth) {
			$this->passkeyAuth = new PasskeyAuth();
		}

		return $this->passkeyAuth;

	}

	/**
	 * Endpoint: GET /api/passkey/list
	 */
	private function passkeyList(): void {

		$user = $this->requireAuth();
		$data = [];

		foreach (UserPasskey::getActiveByUserId($user->id) as $passkey) {
			$data[] = [
				'id' => $passkey->id,
				'label' => $passkey->label,
				'credentialId' => $passkey->credentialId,
				'createdAt' => $passkey->createdAt?->format('Y-m-d H:i:s'),
				'lastUsedAt' => $passkey->lastUsedAt?->format('Y-m-d H:i:s'),
				'transports' => $passkey->getTransports()
			];
		}

		ApiResponse::respond($data);

	}

	/**
	 * Endpoint: POST /api/passkey/login/options
	 */
	private function passkeyLoginOptions(): void {

		$body = $this->optionalJsonPost();
		$identifier = trim((string)($body['username'] ?? ''));
		$user = $identifier ? $this->getUserByLoginIdentifier($identifier) : null;

		$options = $this->passkey()->beginAuthentication($user);

		ApiResponse::respond(['publicKey' => $options]);

	}

	/**
	 * Endpoint: POST /api/passkey/login/verify
	 */
	private function passkeyLoginVerify(): void {

		$body = $this->optionalJsonPost();
		$credential = isset($body['credential']) and is_array($body['credential']) ? $body['credential'] : null;

		if (!$credential) {
			ApiResponse::error('BAD_REQUEST', ['detail' => 'Missing passkey credential payload']);
		}

		$identifier = trim((string)($body['username'] ?? ''));
		$user = $identifier ? $this->getUserByLoginIdentifier($identifier) : null;
		$timezone = $this->normalizeTimezone($body['timezone'] ?? null);
		$result = $this->passkey()->completeAuthentication($credential, $timezone, $user);

		if ($result->error) {
			ApiResponse::error('AUTH_INVALID_CREDENTIALS');
		}

		ApiResponse::respond([
			'message' => 'Authenticated',
			'userId' => $result->userId,
			'sessionId' => $result->sessionId
		]);

	}

	/**
	 * Endpoint: POST /api/passkey/register/options
	 */
	private function passkeyRegisterOptions(): void {

		$user = $this->requireAuth();
		$body = $this->optionalJsonPost();
		$displayName = trim((string)($body['displayName'] ?? ''));

		$options = $this->passkey()->beginRegistration($user, ('' === $displayName ? null : $displayName));

		ApiResponse::respond(['publicKey' => $options]);

	}

	/**
	 * Endpoint: POST /api/passkey/register/verify
	 */
	private function passkeyRegisterVerify(): void {

		$user = $this->requireAuth();
		$body = $this->optionalJsonPost();
		$credential = isset($body['credential']) and is_array($body['credential']) ? $body['credential'] : null;

		if (!$credential) {
			ApiResponse::error('BAD_REQUEST', ['detail' => 'Missing passkey credential payload']);
		}

		$label = trim((string)($body['label'] ?? ''));
		$passkey = $this->passkey()->registerCredential($user, $credential, ('' === $label ? null : $label));

		ApiResponse::respond([
			'message' => 'Passkey registered',
			'passkey' => [
				'id' => $passkey->id,
				'label' => $passkey->label,
				'credentialId' => $passkey->credentialId,
				'createdAt' => $passkey->createdAt?->format('Y-m-d H:i:s')
			]
		], 201);

	}

	/**
	 * Endpoint: DELETE /api/passkey/revoke/{id}
	 */
	private function passkeyRevoke(): void {

		$user = $this->requireAuth();
		$passkeyId = intval((string)$this->router->getParam(1));

		if ($passkeyId < 1) {
			ApiResponse::error('BAD_REQUEST', ['detail' => 'Invalid passkey ID']);
		}

		$passkey = new UserPasskey($passkeyId);

		if (!$passkey->isLoaded() or $passkey->userId !== $user->id) {
			ApiResponse::error('NOT_FOUND', ['detail' => 'Passkey not found']);
		}

		if (!$passkey->isRevoked() and !$passkey->revoke()) {
			ApiResponse::error('INTERNAL_SERVER_ERROR', ['detail' => 'Unable to revoke passkey']);
		}

		ApiResponse::respond(null, 204);

	}

}
