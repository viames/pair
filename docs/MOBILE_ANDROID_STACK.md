# Pair Mobile Android Stack

This document describes the reusable native Android stack for apps that talk to Pair APIs. The goal matches the iOS stack: avoid rebuilding auth, session handling, networking, and image loading differently in every project.

This is not a mechanical port of `PairMobileKit`. Android gets Android-native defaults: OkHttp for transport and cache, Kotlin coroutines for async work, Kotlin serialization for app models, private `SharedPreferences` for migratable sessions, and no imposed UI framework.

## Boundaries

Pair provides:

- stable API contracts for login, registration, `/auth/me`, and logout;
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
- `PairApiClient`: JSON client with Bearer auth, `data` envelopes, and 401 invalidation.
- `PairAuthService`: login and registration with `remember_me=true` forced and not exposed to users.
- `PairSharedPreferencesSessionStore`: migratable session store using private app preferences.
- `PairSessionBootstrapper`: startup restore handling valid, revoked, and offline sessions.
- `PairRemoteImageClient`: remote image bytes and bitmap loading through the shared HTTP cache.
- `PairMobileStack`: convenience facade that wires the default components for common apps.

## Minimum API Contract

Auth endpoints must use JSON and respond with a `data` envelope.

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
    "token": "plain-token-only-once"
  }
}
```

The plain token is shown only in the login or registration response. The backend stores only the hash.

## Session and Device Transfer

The default Android store writes the session snapshot to app-private `SharedPreferences`. This keeps implementation lightweight and allows the snapshot to participate in Android backup and device-transfer flows when the host app allows backup.

The default store deliberately does not use Android Keystore encryption because hardware-backed keys normally do not migrate to another phone. Projects that prefer device-only storage can implement `PairSessionStore` and keep the rest of the stack unchanged.

Startup validation must call `/auth/me`:

- if it returns 200, the snapshot is refreshed;
- if it returns 401, the store is cleared and the user returns to login;
- if the network is unavailable, the app can open the saved context and retry later.

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

pair.sessionStore.save(
    pair.storedSession(session = session, defaultContext = "crotone")
)
```

## Adoption Rules

1. Use `PairOkHttpClientFactory` or an equivalent cookie-free transport.
2. Do not expose a `remember me` toggle in native apps.
3. Store the Bearer token only in the configured `PairSessionStore`.
4. Validate the session at startup with `/auth/me`.
5. Clear the session store on logout, account deactivation, or 401.
6. Keep application models out of `PairMobileAndroid`.
7. Keep UI decisions in the host Android app.

## Required Checks

Every Android stack change must pass:

```sh
./gradlew testDebugUnitTest
```

Pair CI runs this command on Ubuntu, separately from the PHP matrix and the iOS mobile job.

