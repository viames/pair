# PairMobileAndroid

Reusable Android library for apps that talk to Pair APIs.

It includes:

- JSON API client with Bearer auth;
- OkHttp transport with cookies disabled and HTTP cache enabled;
- login and registration with `remember_me=true`;
- migratable session storage based on private `SharedPreferences`;
- startup session bootstrap;
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

pair.sessionStore.save(
    pair.storedSession(session = session, defaultContext = "crotone")
)
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
    PairSessionBootstrapResult.Missing -> Unit
    is PairSessionBootstrapResult.Restored -> openApp(result.snapshot)
    PairSessionBootstrapResult.Invalidated -> openLogin()
    is PairSessionBootstrapResult.Offline -> openApp(result.snapshot)
}
```

## Storage Strategy

The default store uses app-private `SharedPreferences`, not Android Keystore encryption. This is intentional: hardware-backed Keystore values normally do not survive device transfer, while Pair mobile sessions are expected to remain available when Android backup and restore are enabled by the host app.

Projects that prefer a device-only token can provide their own `PairSessionStore` implementation.

## Local Verification

```sh
./gradlew testDebugUnitTest
```

