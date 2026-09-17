# Pair Mobile Android Stack

This document describes the reusable native Android stack for apps that talk to Pair APIs. The goal matches the iOS stack: avoid rebuilding auth, session handling, networking, and image loading differently in every project.

This is not a mechanical port of `PairMobileKit`. Android gets Android-native defaults: OkHttp for transport and cache, Kotlin coroutines for async work, Kotlin serialization for app models, private `SharedPreferences` for migratable sessions, and no imposed UI framework.

## Boundaries

Pair provides:

- stable API contracts for login, registration, `/auth/me`, and logout;
- built-in Pair v4 mobile auth endpoints in `Pair\Api\ApiController`;
- short-lived Bearer access tokens and optional persistent refresh tokens;
- Bearer tokens stored in `api_tokens`;
- mobile `remember_me` enabled by default;
- mobile sessions separated from cookies, PHP sessions, and web remember-me records;
- Android library `PairMobileAndroid` in `mobile/android/PairMobileAndroid`.

Each app provides:

- domain-specific models, such as municipality, venue, customer, or tenant;
- native Android UI and navigation;
- city, tenant, or default-context management;
- application endpoints beyond authentication.

## Android Library

The library lives at:

```text
mobile/android/PairMobileAndroid
```

Main components:

- `PairOkHttpClientFactory`: OkHttp client with cookies disabled, HTTP cache, conservative timeouts, and shared cache locations.
- `PairOkHttpTransport`: transport adapter used by the Pair API client.
- `PairApiClient`: JSON and binary client with Bearer auth, protected application headers, `data` envelopes, and 401 invalidation.
- `PairAuthService`: login, registration, refresh, and logout with `remember_me=true` forced and not exposed to users.
- `PairAuthSession` and `PairStoredAuthSession`: token metadata, user snapshot, expiration, and optional app context.
- `PairSharedPreferencesSessionStore`: migratable session store using private app preferences.
- `PairAuthSessionManager`: startup bootstrap, token refresh, and single-flight refresh coalescing.
- `PairRemoteImageClient`: remote image bytes and bitmap loading through the shared HTTP cache.
- `PairMobileStack`: convenience facade that wires the default components for common apps.

## Build Compatibility

`PairMobileAndroid` currently uses Gradle 8.14.5, Android Gradle Plugin 8.13.2, Kotlin 2.2.20, JDK 17, `compileSdk 35`, and `minSdk 23`. AGP 8.13 supports API levels through 36.1 and requires Gradle 8.13 or newer.

Applications that include this checkout as a Gradle subproject should align their Gradle, AGP, Kotlin, and JDK versions with the library. A host may use a newer `compileSdk`, but it must not use one lower than the library value. Toolchain changes must pass both the standalone library checks and the consuming application's build.

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

Applications can override `mobileAuthUserSnapshot()`, `mobileAuthContext()`, and `mobileAuthRegisterUser()` in their API controller. The default registration hook returns `NOT_IMPLEMENTED` so each product owns its signup validation and consent requirements. See `docs/MOBILE_AUTH_APP_SETUP.md` for the end-to-end application guide.

## Session and Device Transfer

The default Android store writes the session snapshot to app-private `SharedPreferences`. The snapshot includes the access token, optional refresh token, access-token expiration, user snapshot, and optional app context. This keeps implementation lightweight and allows the snapshot to participate in Android backup and device-transfer flows when the host app allows backup.

The default store deliberately does not use Android Keystore encryption because hardware-backed keys normally do not migrate to another phone. Projects that prefer device-only storage can implement `PairSessionStore` and keep the rest of the stack unchanged.

Startup must run through `PairAuthSessionManager.bootstrap(validate:refresh:)` before showing internal screens:

- if there is no snapshot, the result is `Missing`;
- if the access token is valid, the manager validates the session;
- if the access token is expired or inside the refresh leeway, the manager refreshes it first;
- if validation succeeds, the result is `Valid`;
- if validation or refresh fails because the network is unavailable, the result is `Offline` and the saved snapshot is preserved;
- if validation or refresh fails with a definitive auth error, the result is `Invalidated` and local storage is cleared.

Before authenticated API calls, use `validAccessToken(refresh:)`. Concurrent callers share one refresh operation, so rotated refresh tokens do not race each other.

## Separation From Web Login

Native apps must not use Pair cookies, `sid`, `PHPSESSID`, or `user_remembers` records. Mobile uses only Bearer tokens in `api_tokens`.

App login must not close or renew web login. Web login must not revoke mobile tokens except for account deactivation or explicit revocation.

## Example

```kotlin
import kotlinx.serialization.Serializable
import dev.pair.mobile.android.PairMobileStack

@Serializable
data class AppUser(
    val id: Int,
    val email: String,
    val name: String
)

val pair = PairMobileStack.create(
    context = applicationContext,
    apiBaseUrl = "https://example.test/api/v1",
    userSerializer = AppUser.serializer()
)

val session = pair.auth.login(
    email = "mario@example.test",
    password = "password"
)

pair.sessionManager.save(pair.storedSession(session = session, context = "crotone"))
```

## Application Headers and Binary Transfers

`PairApiClient.send()` and `sendData()` accept application-owned headers such as `Idempotency-Key`. The client reserves `Accept`, `Authorization`, `Content-Type`, and `Cookie`: callers cannot replace the Bearer token, re-enable cookie auth, or disguise the request representation through `additionalHeaders`.

Use `sendRawData()` for a byte body such as multipart form data when the successful response remains a standard Pair `data` envelope. Use `sendRawResponse()` for binary success responses such as PDFs. Both paths preserve Pair error decoding and definitive authentication invalidation.

The host app remains responsible for building multipart bodies, validating selected files, checking response media types and signatures, and decoding domain-specific models.

## Adoption Rules

1. Use `PairOkHttpClientFactory` or an equivalent cookie-free transport.
2. Do not expose a `remember me` toggle in native apps.
3. Store the auth snapshot only in the configured `PairSessionStore`.
4. Gate internal screens behind `PairAuthSessionManager.bootstrap(validate:refresh:)`.
5. Use `validAccessToken(refresh:)` before authenticated API calls.
6. Clear the session store on logout, account deactivation, or definitive auth failure.
7. Preserve session snapshots for network and offline errors.
8. Keep application models out of `PairMobileAndroid`.
9. Keep UI decisions in the host Android app.

## Required Checks

Every Android stack change must pass:

```sh
./gradlew testDebugUnitTest
./gradlew lintDebug
./gradlew assembleRelease
```

Pair CI runs these commands on Ubuntu, separately from the PHP matrix and the iOS mobile job.

See also: `docs/MOBILE_AUTH_APP_SETUP.md`, `docs/MOBILE_IOS_STACK.md`, `Pair\Api\ApiController`, and `Pair\Models\ApiToken`.
