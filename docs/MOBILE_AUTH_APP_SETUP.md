# Enable Mobile Auth In A Pair App

This guide shows how a Pair v4 application can enable the built-in mobile authentication contract used by `PairMobileKit` and `PairMobileAndroid`.

## 1. Apply The Token Migration

Apply the framework migration that creates `api_tokens`:

```sh
php migrate.php
```

or apply the file explicitly in projects that run migrations manually:

```text
migrations/20260510_api_tokens.sql
migrations/20260510_api_tokens_device_metadata.sql
```

The table stores only token hashes. Raw access and refresh tokens are returned once, in login, registration, or refresh responses.

## 2. Configure Token Lifetimes

Use short-lived access tokens and longer refresh tokens:

```ini
PAIR_MOBILE_ACCESS_TOKEN_LIFETIME=900
PAIR_MOBILE_REFRESH_TOKEN_LIFETIME=2592000
```

Invalid or non-positive values fall back to the framework defaults above.

## 3. Expose The Standard API Module

Applications that extend `Pair\Api\ApiController` get these routes through `authAction()`:

```text
POST /api/auth/login
POST /api/auth/register
POST /api/auth/refresh
GET  /api/auth/me
POST /api/auth/logout
```

The same contract works under versioned API prefixes such as `/api/v1` when the application router maps the API module there.

## 4. Override Registration And Payload Hooks

`login`, `refresh`, `me`, and `logout` are usable without app code. Registration is intentionally app-owned because products differ on required fields, privacy consent, tenant bootstrap, and welcome flows.

```php
<?php

namespace App\Modules\Api;

use App\Models\User;
use Pair\Api\ApiController as BaseApiController;
use Pair\Api\ApiErrorResponse;

class ApiController extends BaseApiController {

	/**
	 * Return the user snapshot exposed to native clients.
	 */
	protected function mobileAuthUserSnapshot(\Pair\Models\User $user): array {

		return [
			'id' => (int)$user->id,
			'email' => $user->email,
			'name' => $user->fullName(),
		];

	}

	/**
	 * Return optional application context restored at startup.
	 */
	protected function mobileAuthContext(\Pair\Models\User $user): ?array {

		return [
			'tenant' => 'default',
		];

	}

	/**
	 * Validate signup input and create the application user.
	 */
	protected function mobileAuthRegisterUser(array $body): \Pair\Models\User|ApiErrorResponse {

		if (empty($body['email']) or empty($body['password']) or empty($body['privacy_accepted'])) {
			return $this->errorResponse('BAD_REQUEST');
		}

		$user = new User();
		$user->email = trim((string)$body['email']);
		$user->username = $user->email;
		$user->name = trim((string)($body['name'] ?? ''));
		$user->hash = User::getHashedPasswordWithSalt((string)$body['password']);
		$user->enabled = true;
		$user->store();

		return $user;

	}

}
```

Adapt the example to the application's actual user fields, tenant ownership, validation rules, and consent records.

## 5. Publish The OpenAPI Contract

Use `SpecGenerator::addMobileAuthPaths()` when generating the application OpenAPI document:

```php
use Pair\Api\OpenApi\SpecGenerator;

$spec = new SpecGenerator('Example API', '1.0.0');
$spec->addMobileAuthPaths('/api/v1');

return $spec->toJson();
```

The helper adds the standard auth paths, component schemas, and `bearerAuth` security scheme.

## 6. Wire The Native Client

Both native libraries expect:

* `access_token`
* optional `refresh_token`
* `expires_in` or `expires_at`
* `user`
* optional `context`

Startup must run through the platform session manager before internal screens are shown. Network failures should keep the local snapshot; definitive auth failures should clear it.

## 7. Administrative Revocation

Pair does not expose generic admin revocation endpoints in the core API because admin ACL, tenant scope, support impersonation, and audit policy are application-specific.

Recommended application endpoints:

```text
GET    /api/admin/users/{userId}/tokens
DELETE /api/admin/users/{userId}/tokens/{tokenId}
DELETE /api/admin/users/{userId}/tokens
```

Implementation rules:

* require an explicit admin permission before reading or mutating token rows
* resolve `userId` inside the application's tenant or ownership scope
* never return raw token hashes
* show only safe metadata such as token ID, device name, created time, last used time, expiration, and revocation state
* use `ApiToken::revokeByIdForUser()` for device/session revocation
* use `ApiToken::revokeAllForUserAndDevice()` when the app records a stable device hash and needs to revoke all bearer tokens for one device
* use `ApiToken::revokeAllForUser()` for account-wide mobile logout
* write an audit record when support or admin users revoke another user's tokens

This keeps the generic token model reusable while leaving sensitive authorization policy in the application.
