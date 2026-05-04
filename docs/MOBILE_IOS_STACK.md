# Pair Mobile iOS Stack

This document describes the reusable native iOS stack for apps that talk to Pair APIs. The goal is to avoid rebuilding login, session handling, networking, and image caching differently in every project. The Android counterpart is documented in `docs/MOBILE_ANDROID_STACK.md`.

## Boundaries

Pair provides:

- stable API contracts for login, registration, `/auth/me`, and logout;
- Bearer tokens stored in `api_tokens`;
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
- `PairJSONValue`: nested JSON extra payloads without custom request types for small project fields.
- `PairKeychainStore`: Codable Keychain store using an attribute that can migrate to a new phone.
- `PairSessionBootstrapper`: startup restore handling valid, revoked, and offline sessions.
- `PairRemoteImageCache` and `PairCachedRemoteImage`: cookie-free memory and disk image cache.

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

Apps store the session snapshot in Keychain with `kSecAttrAccessibleAfterFirstUnlock`. This allows the token to be included in backup and device-transfer flows supported by the system. Apps that previously used a `ThisDeviceOnly` attribute can migrate by reading the snapshot and writing it again through `PairKeychainStore`.

Apps that already persist snapshots with a custom JSON format should pass matching `JSONEncoder` and `JSONDecoder` instances to `PairKeychainStore`, so device-transfer support does not require changing existing stored payloads. Apps with synchronous session services can use `PairKeychainDataStore` directly and keep their existing account names and payload formats.

Startup validation must call `/auth/me`:

- if it returns 200, the snapshot is refreshed;
- if it returns 401, Keychain is cleared and the user returns to login;
- if the network is unavailable, the app can open the saved context and retry later.

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
await store.save(
    PairStoredAuthSession(session: session, defaultContext: "crotone")
)
```

## Adoption Rules

1. Use `PairURLSessionTransport` or an equivalent cookie-free transport.
2. Do not expose a `remember me` toggle in native apps.
3. Store the Bearer token only in Keychain.
4. Validate the session at startup with `/auth/me`.
5. Clear Keychain on logout, account deactivation, or 401.
6. Keep application models out of `PairMobileKit`.

## Required Checks

Every mobile stack change must pass:

```sh
DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer swift test --package-path mobile/ios/PairMobileKit
```

Pair CI runs this command on macOS, separately from the PHP matrix on Ubuntu.
