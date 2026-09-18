# PairMobileAndroid

Reusable Android library for apps that talk to Pair APIs.

It includes:

- JSON API client with Bearer auth;
- protected application headers for idempotent mutations;
- multipart request bodies and binary responses;
- OkHttp transport with cookies disabled and HTTP cache enabled;
- login, registration, refresh, and logout with `remember_me=true`;
- short-lived access-token sessions with optional persistent refresh tokens;
- migratable session storage based on private `SharedPreferences`;
- verified startup session bootstrap with single-flight refresh;
- native passkey login and account management through Android Credential Manager;
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

Coroutine cancellation remains distinct from connectivity and authentication failures. `PairOkHttpTransport`, `bootstrap(validate:refresh:)`, and `validAccessToken(refresh:)` propagate `CancellationException`; they never translate it to `Transport`, `Offline`, or `Invalidated`, and an existing stored snapshot is left available for the next task.

## Passkeys

The library includes AndroidX Credentials 1.6.0 and exposes the standard Pair passkey endpoints through `PairPasskeyService`. Supply an Activity context to `PairPasskeyClient` so Credential Manager can present its system UI.

```kotlin
val passkeys = PairPasskeyService(
    client = pair.client,
    userSerializer = AppUser.serializer(),
    passkeyClient = PairPasskeyClient(activity)
)

val session = passkeys.login(deviceName = "Android")
val registered = passkeys.register(displayName = session.user.name, label = "Android")
val active = passkeys.passkeys()
passkeys.revoke(registered.id)
```

The relying-party domain must publish `/.well-known/assetlinks.json` with the installed package name and the SHA-256 fingerprint of its production signing certificate. Add the matching `android:apk-key-hash:` value to `PASSKEY_ALLOWED_ORIGINS` on the Pair backend.

## Application Headers and Binary Transfers

`send()` and `sendData()` accept application headers such as `Idempotency-Key`. The client keeps `Accept`, `Authorization`, `Content-Type`, and `Cookie` under its own control, so custom values cannot replace the Bearer token, re-enable cookie auth, or disguise the request representation.

```kotlin
val saved = pair.client.sendData(
    path = "orders",
    method = "POST",
    body = orderPayload,
    deserializer = Order.serializer(),
    additionalHeaders = mapOf("Idempotency-Key" to operationId)
)
```

Use `sendRawData()` for a byte body such as multipart form data whose successful response is a standard Pair `data` envelope:

```kotlin
val uploaded = pair.client.sendRawData(
    path = "documents",
    method = "POST",
    body = multipartBytes,
    contentType = "multipart/form-data; boundary=$boundary",
    deserializer = Document.serializer(),
    additionalHeaders = mapOf("Idempotency-Key" to operationId)
)
```

Use `sendRawResponse()` when a successful response is binary. HTTP errors still use the normal Pair error handling and invalidate an expired Bearer session when appropriate.

```kotlin
val pdf = pair.client.sendRawResponse(
    path = "invoices/$invoiceId/document",
    accept = "application/pdf"
)
```

## Storage Strategy

The default store uses app-private `SharedPreferences`, not Android Keystore encryption. This is intentional: hardware-backed Keystore values normally do not survive device transfer, while Pair mobile sessions are expected to remain available when Android backup and restore are enabled by the host app.

Projects that prefer a device-only token can provide their own `PairSessionStore` implementation.

## Build Compatibility

The source module currently uses Gradle 8.14.5, Android Gradle Plugin 8.13.2, Kotlin 2.2.20, JDK 17, `compileSdk 35`, and `minSdk 23`. AGP 8.13 supports API levels through 36.1 and requires Gradle 8.13 or newer, so the checked-in wrapper is within the supported range.

When a host application includes this checkout as a Gradle subproject, keep its Gradle, AGP, Kotlin, and JDK toolchain aligned with the library. The host `compileSdk` must not be lower than the library value, and `minSdk 23` remains the minimum supported Android version. Verify toolchain changes both in this standalone module and in every source-including host application.

## Local Verification

```sh
./gradlew testDebugUnitTest
./gradlew lintDebug
./gradlew assembleRelease
```
