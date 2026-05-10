# PairMobileAndroid

Reusable Android library for apps that talk to Pair APIs.

It includes:

- JSON API client with Bearer auth;
- OkHttp transport with cookies disabled and HTTP cache enabled;
- login, registration, refresh, and logout with `remember_me=true`;
- short-lived access-token sessions with optional persistent refresh tokens;
- migratable session storage based on private `SharedPreferences`;
- verified startup session bootstrap with single-flight refresh;
- remote image bytes and bitmap loading through the shared HTTP cache.

The library does not include domain models or UI. Android apps should keep their own Compose, XML, navigation, and feature models.

## Quick Start

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

## Extra Payload

Login and registration payloads accept a `JsonObject` for project fields. `remember_me` is always overwritten with `true`, even if an adapter tries to pass it as `false`.

```kotlin
val session = pair.auth.login(
    email = "mario@example.test",
    password = "password",
    extraPayload = pairJsonPayload {
        put("tenant", "crotone")
        putJsonObject("metadata") {
            put("source", "android")
        }
    }
)
```

## Session Restore

```kotlin
when (val result = pair.bootstrapWithCurrentUser()) {
    PairAuthSessionManagerResult.Missing -> openLogin()
    is PairAuthSessionManagerResult.Valid -> openApp(result.session)
    is PairAuthSessionManagerResult.Offline -> openApp(result.session)
    PairAuthSessionManagerResult.Invalidated -> openLogin()
}
```

`PairAuthSession` expects mobile auth responses with `access_token`, optional `refresh_token`, and either `expires_at` or `expires_in`. `PairStoredAuthSession` stores the same token metadata with the user snapshot and optional application context.

Use `PairAuthSessionManager.validAccessToken(refresh:)` before authenticated API calls. If the access token is expired or near expiration, concurrent callers wait for the same refresh operation instead of rotating the refresh token multiple times.

## Storage Strategy

The default store uses app-private `SharedPreferences`, not Android Keystore encryption. This is intentional: hardware-backed Keystore values normally do not survive device transfer, while Pair mobile sessions are expected to remain available when Android backup and restore are enabled by the host app.

Projects that prefer a device-only token can provide their own `PairSessionStore` implementation.

## Local Verification

```sh
./gradlew testDebugUnitTest
```
