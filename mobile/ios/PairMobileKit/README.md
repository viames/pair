# PairMobileKit

Reusable Swift Package for iOS and macOS apps that talk to Pair APIs.

It includes:

- JSON API client with Bearer auth;
- cookie-free `URLSession` transport;
- login and registration with `remember_me=true`;
- Keychain persistence that can migrate during supported device transfers;
- raw Keychain storage for apps with synchronous session services;
- startup session bootstrap;
- cookie-free memory and disk image caching.

The package does not include domain models. Each app defines its own `User`, tenant, and default-context types.

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
await store.save(PairStoredAuthSession(session: session, defaultContext: "crotone"))
```

Pass custom `JSONEncoder` and `JSONDecoder` instances when adopting an existing snapshot format, for example ISO 8601 `Date` fields already stored by an app.

Apps that already own a synchronous session service can use `PairKeychainDataStore` directly and keep their existing account names and payload formats.

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
