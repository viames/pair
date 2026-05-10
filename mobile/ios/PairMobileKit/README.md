# PairMobileKit

Reusable Swift Package for iOS and macOS apps that talk to Pair APIs.

It includes:

- JSON API client with Bearer auth;
- cookie-free `URLSession` transport;
- login and registration with `remember_me=true`;
- Keychain persistence that can migrate during supported device transfers;
- raw Keychain storage for apps with synchronous session services;
- verified startup auth bootstrap;
- refresh-aware session management with single-flight token refresh;
- cookie-free memory and disk image caching.

The package does not include domain models. Each app defines its own `User`, tenant, and application context types.

## Quick Start

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
let session = try await auth.login(email: "mario@example.test", password: "password")

let store = PairKeychainStore<PairStoredAuthSession<AppUser, String>>(
    service: "it.example.app"
)
await store.save(PairStoredAuthSession(session: session, context: "crotone"))
```

Pass custom `JSONEncoder` and `JSONDecoder` instances when a project needs a specific Keychain date encoding format.

Apps that already own a synchronous session service can use `PairKeychainDataStore` directly and keep their existing account names and payload formats.

## Managed Auth Sessions

`PairAuthSession` expects mobile auth responses with `access_token`, optional `refresh_token`, and either `expires_at` or `expires_in`. `PairStoredAuthSession` stores the same token metadata with the user snapshot and optional application context.

Pair v4's standard backend endpoint is `POST /auth/refresh`; call it through `PairAuthService.refresh(refreshToken:)`.

Use `PairAuthSessionManager` when the app needs a verified startup gate and robust refresh handling:

```swift
let manager = PairAuthSessionManager(store: store)

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
    client.setBearerToken(refreshed.accessToken)

    return PairStoredAuthSession(session: refreshed, context: expired.context)
}
```

`bootstrap` returns `.valid`, `.missing`, `.offline`, or `.invalidated`. Network and offline errors preserve the local snapshot. Authentication failures clear it.

Before authenticated API calls, ask the manager for a token:

```swift
let tokenResult = await manager.validAccessToken { expired in
    guard let refreshToken = expired.refreshToken else {
        throw PairAPIError.server(statusCode: 401, payload: nil)
    }

    let refreshed = try await auth.refresh(refreshToken: refreshToken)

    return PairStoredAuthSession(session: refreshed, context: expired.context)
}
```

If several callers request a token while it is expired, the manager runs one refresh operation and all callers await the same result. Persisted refresh tokens are replaced only after a successful refresh, so backends that rotate refresh tokens do not trigger competing local writes.

When logging out, pass the saved refresh token when available:

```swift
try await auth.logout(refreshToken: snapshot.refreshToken)
await manager.clear()
```

## Extra Payload

Login and registration payloads accept primitive or nested extra fields. `remember_me` is always overwritten with `true`, even if an adapter tries to pass it as `false`.

```swift
let session = try await auth.login(
    email: "mario@example.test",
    password: "password",
    extraPayload: [
        "tenant": "crotone",
        "metadata": [
            "source": "ios"
        ]
    ]
)
```

## Local Verification

```sh
DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer swift test
```
