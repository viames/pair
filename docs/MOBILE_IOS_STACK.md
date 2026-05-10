# Pair Mobile iOS Stack

This document describes the reusable native iOS stack for apps that talk to Pair APIs. The goal is to avoid rebuilding login, session handling, networking, and image caching differently in every project. The Android counterpart is documented in `docs/MOBILE_ANDROID_STACK.md`.

## Boundaries

Pair provides:

- stable API contracts for login, registration, `/auth/me`, and logout;
- built-in Pair v4 mobile auth endpoints in `Pair\Api\ApiController`;
- short-lived Bearer access tokens and optional persistent refresh tokens;
- mobile `remember_me` enabled by default;
- mobile sessions separated from cookies, PHP sessions, and web remember-me records;
- Swift Package `PairMobileKit` in `mobile/ios/PairMobileKit`.

Each app provides:

- domain-specific models, such as municipality, venue, customer, or tenant;
- native routing and UI;
- city, tenant, or default-context management;
- application endpoints beyond authentication.

## Swift Package

The package is installable through Swift Package Manager from:

```text
mobile/ios/PairMobileKit
```

Main components:

- `PairURLSessionTransport`: `URLSession` with HTTP cache, conservative timeouts, and cookies disabled.
- `PairAPIClient`: JSON client with Bearer auth, `data` envelopes, and 401 invalidation.
- `PairAuthService`: login and registration with `remember_me=true` forced and not exposed to users.
- `PairAuthSession` and `PairStoredAuthSession`: token metadata, user snapshot, expiration, and optional app context.
- `PairAuthSessionManager`: Keychain-backed bootstrap, token refresh, and single-flight refresh coalescing.
- `PairJSONValue`: nested JSON extra payloads without custom request types for small project fields.
- `PairKeychainStore`: Codable Keychain store using an attribute that can migrate to a new phone.
- `PairRemoteImageCache` and `PairCachedRemoteImage`: cookie-free memory and disk image cache.

## Minimum API Contract

Pair v4 ships a default mobile auth action in `Pair\Api\ApiController`. Applications that expose the standard API module can use:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/refresh`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

Auth endpoints use JSON and respond with a `data` envelope.

Login:

```http
POST /api/v1/auth/login
```

```json
{
  "email": "mario@example.test",
  "password": "password",
  "remember_me": true
}
```

Registration:

```http
POST /api/v1/auth/register
```

```json
{
  "name": "Mario Rossi",
  "email": "mario@example.test",
  "password": "password",
  "privacy_accepted": true,
  "remember_me": true
}
```

Response:

```json
{
  "data": {
    "user": {
      "id": 1,
      "email": "mario@example.test",
      "name": "Mario Rossi"
    },
    "access_token": "short-lived-access-token",
    "refresh_token": "persistent-refresh-token",
    "expires_in": 900
  }
}
```

`expires_at` may be returned instead of `expires_in`. The refresh token is optional in the model, but short-lived access-token deployments should return one so the app can refresh without sending the user back to login. Backends may rotate the refresh token; apps must persist the refreshed snapshot only after the refresh response succeeds.

Refresh:

```http
POST /api/v1/auth/refresh
```

```json
{
  "refresh_token": "persistent-refresh-token"
}
```

The response has the same token payload as login and may contain a rotated `refresh_token`.

`PairAuthService.refresh(refreshToken:)` calls the standard refresh endpoint. `PairAuthSessionManager` still receives a `refresh` closure so the host app can update the shared API client, preserve app context, and adapt custom endpoint paths when needed.

Logout:

```http
POST /api/v1/auth/logout
```

```json
{
  "refresh_token": "persistent-refresh-token"
}
```

The refresh token is optional on logout. Sending it lets the backend revoke the persisted token row even when the current access token is no longer useful.

## Backend Storage

Pair v4 stores mobile bearer sessions in `api_tokens`. Apply the Pair auth migrations, including `migrations/20260510_api_tokens.sql` and `migrations/20260510_api_tokens_device_metadata.sql`, before enabling the default mobile auth endpoints in an application.

The table stores only SHA-256 token hashes. Access tokens are short lived and refresh tokens are optional but rotated atomically by `ApiToken::refresh()`. Concurrent refresh calls using the same old refresh token result in one successful rotation; later calls fail because the old hash no longer matches. Applications can also persist a safe `device_hash` and `password_version_hash` for device-scoped logout and password-change invalidation without storing raw credentials.

Token lifetimes are configured with:

```ini
PAIR_MOBILE_ACCESS_TOKEN_LIFETIME=900
PAIR_MOBILE_REFRESH_TOKEN_LIFETIME=2592000
```

Applications can override `mobileAuthUserSnapshot()`, `mobileAuthContext()`, and `mobileAuthRegisterUser()` in their API controller. The default registration hook returns `NOT_IMPLEMENTED` so each product owns its signup validation and consent requirements.

See `docs/MOBILE_AUTH_APP_SETUP.md` for the end-to-end application guide, including migration, registration override, OpenAPI publishing, and administrative revocation recommendations.

## Session and Device Transfer

Apps store the session snapshot in Keychain with `kSecAttrAccessibleAfterFirstUnlock`. The snapshot includes the access token, optional refresh token, access-token expiration, user snapshot, and optional app context. This allows the token state to be included in backup and device-transfer flows supported by the system.

Apps with synchronous session services can use `PairKeychainDataStore` directly and keep their existing account names and payload formats.

Startup must run through `PairAuthSessionManager.bootstrap(validate:refresh:)` before showing internal screens:

- if there is no snapshot, the result is `.missing`;
- if the access token is valid, the manager validates the session;
- if the access token is expired or inside the refresh leeway, the manager refreshes it first;
- if validation succeeds, the result is `.valid`;
- if validation or refresh fails because the network is unavailable, the result is `.offline` and the saved snapshot is preserved;
- if validation or refresh fails with a definitive auth error, the result is `.invalidated` and Keychain is cleared.

Before authenticated API calls, use `validAccessToken(refresh:)`. Concurrent callers share a single refresh task, so rotated refresh tokens do not race each other.

## Separation From Web Login

Native apps must not use Pair cookies, `sid`, `PHPSESSID`, or `user_remembers` records. Mobile uses only Bearer tokens in `api_tokens`.

App login must not close or renew web login. Web login must not revoke mobile tokens except for account deactivation or explicit revocation.

## iOS Example

```swift
import PairMobileKit

struct AppUser: Codable, Sendable {
    let id: Int
    let email: String
    let name: String
}

let client = PairAPIClient(
    apiBaseURL: URL(string: "https://example.test/api/v1")!
)
let auth = PairAuthService<AppUser>(client: client)

let session = try await auth.login(
    email: "mario@example.test",
    password: "password"
)

let store = PairKeychainStore<PairStoredAuthSession<AppUser, String>>(
    service: "it.example.app"
)
let manager = PairAuthSessionManager(store: store)
await manager.save(PairStoredAuthSession(session: session, context: "crotone"))

let bootstrap = await manager.bootstrap { saved in
    client.setBearerToken(saved.accessToken)
    let response: PairCurrentUserResponse<AppUser> = try await auth.currentAuthentication()

    return PairStoredAuthSession(
        user: response.user,
        accessToken: saved.accessToken,
        refreshToken: saved.refreshToken,
        expiresAt: saved.expiresAt,
        context: saved.context
    )
} refresh: { expired in
    guard let refreshToken = expired.refreshToken else {
        throw PairAPIError.server(statusCode: 401, payload: nil)
    }

    let refreshed = try await auth.refresh(refreshToken: refreshToken)

    return PairStoredAuthSession(
        session: refreshed,
        context: expired.context
    )
}
```

## Adoption Rules

1. Use `PairURLSessionTransport` or an equivalent cookie-free transport.
2. Do not expose a `remember me` toggle in native apps.
3. Store the auth snapshot only in Keychain.
4. Gate internal screens behind `PairAuthSessionManager.bootstrap(validate:refresh:)`.
5. Use `validAccessToken(refresh:)` before authenticated API calls.
6. Clear Keychain on logout, account deactivation, or definitive auth failure.
7. Preserve Keychain snapshots for network and offline errors.
8. Keep application models out of `PairMobileKit`.

## Required Checks

Every mobile stack change must pass:

```sh
DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer swift test --package-path mobile/ios/PairMobileKit
```

Pair CI runs this command on macOS, separately from the PHP matrix on Ubuntu.
