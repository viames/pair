import Foundation
import XCTest
@testable import PairMobileKit

final class PairMobileKitTests: XCTestCase {

	func testLoginForcesRememberMeAndStoresBearerToken() async throws {
		let transport = MockTransport()
		transport.enqueue(
			statusCode: 200,
			body: #"{"data":{"user":{"id":7,"email":"mario@example.test","name":"Mario Rossi"},"access_token":"plain-token","refresh_token":"refresh-token","expires_in":3600}}"#
		)
		let client = PairAPIClient(apiBaseURL: URL(string: "https://example.test/api/v1")!, transport: transport)
		let auth = PairAuthService<TestUser>(client: client)

		let session = try await auth.login(
			email: "mario@example.test",
			password: "password",
			extraPayload: ["remember_me": false, "tenant": "crotone"]
		)

		let request = try XCTUnwrap(transport.requests.first)
		let body = try decodedBody(request)
		XCTAssertEqual(request.url?.absoluteString, "https://example.test/api/v1/auth/login")
		XCTAssertEqual(body["email"] as? String, "mario@example.test")
		XCTAssertEqual(body["remember_me"] as? Bool, true)
		XCTAssertEqual(body["tenant"] as? String, "crotone")
		XCTAssertEqual(session.accessToken, "plain-token")
		XCTAssertEqual(session.refreshToken, "refresh-token")
		XCTAssertEqual(client.currentBearerToken(), "plain-token")
	}

	func testRegisterForcesRememberMe() async throws {
		let transport = MockTransport()
		transport.enqueue(
			statusCode: 201,
			body: #"{"data":{"user":{"id":8,"email":"luisa@example.test","name":"Luisa Bianchi"},"access_token":"register-token","expires_in":3600}}"#
		)
		let client = PairAPIClient(apiBaseURL: URL(string: "https://example.test/api/v1")!, transport: transport)
		let auth = PairAuthService<TestUser>(client: client)

		_ = try await auth.register(
			name: "Luisa Bianchi",
			email: "luisa@example.test",
			password: "password",
			privacyAccepted: true,
			extraPayload: ["remember_me": false]
		)

		let request = try XCTUnwrap(transport.requests.first)
		let body = try decodedBody(request)
		XCTAssertEqual(body["privacy_accepted"] as? Bool, true)
		XCTAssertEqual(body["remember_me"] as? Bool, true)
		XCTAssertEqual(client.currentBearerToken(), "register-token")
	}

	func testRefreshPostsRefreshTokenAndStoresBearerToken() async throws {
		let transport = MockTransport()
		transport.enqueue(
			statusCode: 200,
			body: #"{"data":{"user":{"id":9,"email":"refresh@example.test","name":"Refresh User"},"access_token":"new-access-token","refresh_token":"new-refresh-token","expires_in":900}}"#
		)
		let client = PairAPIClient(apiBaseURL: URL(string: "https://example.test/api/v1")!, transport: transport)
		let auth = PairAuthService<TestUser>(client: client)

		let session = try await auth.refresh(refreshToken: "old-refresh-token")

		let request = try XCTUnwrap(transport.requests.first)
		let body = try decodedBody(request)
		XCTAssertEqual(request.url?.absoluteString, "https://example.test/api/v1/auth/refresh")
		XCTAssertEqual(body["refresh_token"] as? String, "old-refresh-token")
		XCTAssertEqual(session.accessToken, "new-access-token")
		XCTAssertEqual(session.refreshToken, "new-refresh-token")
		XCTAssertEqual(client.currentBearerToken(), "new-access-token")
	}

	func testLogoutCanPostRefreshTokenAndClearBearerToken() async throws {
		let transport = MockTransport()
		transport.enqueue(statusCode: 200, body: #"{"data":{}}"#)
		let client = PairAPIClient(
			apiBaseURL: URL(string: "https://example.test/api/v1")!,
			bearerToken: "access-token",
			transport: transport
		)
		let auth = PairAuthService<TestUser>(client: client)

		try await auth.logout(refreshToken: "refresh-token")

		let request = try XCTUnwrap(transport.requests.first)
		let body = try decodedBody(request)
		XCTAssertEqual(request.url?.absoluteString, "https://example.test/api/v1/auth/logout")
		XCTAssertEqual(body["refresh_token"] as? String, "refresh-token")
		XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer access-token")
		XCTAssertNil(client.currentBearerToken())
	}

	func testAPIClientBuildsQueryAuthorizationAndDoesNotSetCookies() async throws {
		let transport = MockTransport()
		transport.enqueue(statusCode: 200, body: #"{"data":{"id":42}}"#)
		let client = PairAPIClient(
			apiBaseURL: URL(string: "https://example.test/api/v1")!,
			bearerToken: "existing-token",
			transport: transport
		)

		let response: TestIdentifier = try await client.sendData(
			path: "records",
			queryItems: [
				URLQueryItem(name: "q", value: "due parole"),
				URLQueryItem(name: "page", value: "2"),
			]
		)

		let request = try XCTUnwrap(transport.requests.first)
		let components = try XCTUnwrap(URLComponents(url: try XCTUnwrap(request.url), resolvingAgainstBaseURL: false))
		XCTAssertEqual(response.id, 42)
		XCTAssertEqual(components.path, "/api/v1/records")
		XCTAssertEqual(components.queryItems?.first(where: { $0.name == "q" })?.value, "due parole")
		XCTAssertEqual(components.queryItems?.first(where: { $0.name == "page" })?.value, "2")
		XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer existing-token")
		XCTAssertNil(request.value(forHTTPHeaderField: "Cookie"))
	}

	func testPairJSONValueSupportsNestedExtraPayloads() throws {
		let payload: [String: PairJSONValue] = [
			"tenant": "crotone",
			"attempt": 2,
			"tags": ["mobile", "ios"],
			"metadata": [
				"remember_me": false,
				"score": 3.5,
			],
		]
		let data = try JSONEncoder().encode(payload)
		let object = try XCTUnwrap(JSONSerialization.jsonObject(with: data) as? [String: Any])
		let metadata = try XCTUnwrap(object["metadata"] as? [String: Any])

		XCTAssertEqual(object["tenant"] as? String, "crotone")
		XCTAssertEqual(object["attempt"] as? Int, 2)
		XCTAssertEqual(object["tags"] as? [String], ["mobile", "ios"])
		XCTAssertEqual(metadata["remember_me"] as? Bool, false)
		XCTAssertEqual(metadata["score"] as? Double, 3.5)
	}

	func testUnauthorizedResponseNotifiesInvalidationHandler() async throws {
		let transport = MockTransport()
		let recorder = InvalidationRecorder()
		transport.enqueue(
			statusCode: 401,
			body: #"{"error":{"code":"AUTH_SESSION_EXPIRED","message":"Sessione scaduta"}}"#
		)
		let client = PairAPIClient(
			apiBaseURL: URL(string: "https://example.test/api/v1")!,
			transport: transport,
			authenticationInvalidationHandler: { error in
				recorder.record(error)
			}
		)

		do {
			let _: PairDataEnvelope<TestUser> = try await client.send(path: "auth/me")
			XCTFail("The request should fail with 401.")
		} catch let error as PairAPIError {
			XCTAssertTrue(error.isAuthenticationFailure)
		}

		XCTAssertEqual(recorder.count, 1)
	}

	func testKeychainStoreSavesLoadsMigratesAndClearsSnapshot() async throws {
		let service = "test.pair.mobile.\(UUID().uuidString)"
		let store = PairKeychainStore<PairStoredAuthSession<TestUser, TestContext>>(service: service)
		let snapshot = PairStoredAuthSession(
			user: TestUser(id: 11, email: "keychain@example.test", name: "Key Chain"),
			accessToken: "stored-token",
			refreshToken: "stored-refresh-token",
			expiresAt: managedNow.addingTimeInterval(600),
			context: TestContext(slug: "crotone")
		)

		await store.clear()
		await store.save(snapshot)

		let restored = await store.load()
		XCTAssertEqual(restored?.accessToken, "stored-token")
		XCTAssertEqual(restored?.refreshToken, "stored-refresh-token")
		XCTAssertEqual(restored?.context?.slug, "crotone")

		await store.clear()
		let clearedSnapshot = await store.load()
		XCTAssertNil(clearedSnapshot)
	}

	func testKeychainStoreUsesCustomCoders() async throws {
		let service = "test.pair.mobile.coders.\(UUID().uuidString)"
		let encoder = JSONEncoder()
		encoder.dateEncodingStrategy = .iso8601

		let decoder = JSONDecoder()
		decoder.dateDecodingStrategy = .iso8601

		let store = PairKeychainStore<TestDatedSnapshot>(
			service: service,
			encoder: encoder,
			decoder: decoder
		)
		let snapshot = TestDatedSnapshot(token: "dated-token", expiresAt: Date(timeIntervalSince1970: 1_811_337_600))

		await store.clear()
		await store.save(snapshot)

		let restored = await store.load()
		XCTAssertEqual(restored?.token, "dated-token")
		XCTAssertEqual(restored?.expiresAt.timeIntervalSince1970 ?? 0, snapshot.expiresAt.timeIntervalSince1970, accuracy: 0.001)

		await store.clear()
	}

	func testKeychainDataStoreSavesLoadsAndClearsRawData() throws {
		let service = "test.pair.mobile.raw.\(UUID().uuidString)"
		let account = "raw-session"
		let store = PairKeychainDataStore(service: service)
		let data = try XCTUnwrap("raw-token".data(using: .utf8))

		store.clear(account: account)
		store.save(data, account: account)

		let restored = store.load(account: account)
		XCTAssertEqual(String(data: try XCTUnwrap(restored), encoding: .utf8), "raw-token")

		store.clear(account: account)
		XCTAssertNil(store.load(account: account))
	}

	func testAuthSessionManagerBootstrapWithMissingSnapshot() async throws {
		let (store, manager) = makeAuthSessionManager()

		await store.clear()

		let result = await manager.bootstrap { session in
			XCTFail("Validation should not run without a snapshot.")
			return session
		} refresh: { session in
			XCTFail("Refresh should not run without a snapshot.")
			return session
		}

		if case .missing = result {
			let storedSession = await store.load()
			XCTAssertNil(storedSession)
		} else {
			XCTFail("Expected a missing bootstrap result.")
		}
	}

	func testAuthSessionManagerBootstrapWithValidSession() async throws {
		let (store, manager) = makeAuthSessionManager()
		let snapshot = makeManagedSnapshot(accessToken: "valid-token", expiresAt: managedNow.addingTimeInterval(600))

		await store.save(snapshot)

		let result = await manager.bootstrap { session in
			PairStoredAuthSession(
				user: session.user,
				accessToken: "validated-token",
				refreshToken: session.refreshToken,
				expiresAt: session.expiresAt,
				context: session.context
			)
		} refresh: { session in
			XCTFail("Refresh should not run for a valid token.")
			return session
		}

		if case .valid(let session) = result {
			let storedSession = await store.load()
			XCTAssertEqual(session.accessToken, "validated-token")
			XCTAssertEqual(storedSession?.accessToken, "validated-token")
		} else {
			XCTFail("Expected a valid bootstrap result.")
		}

		await store.clear()
	}

	func testAuthSessionManagerBootstrapRefreshesExpiredSessionBeforeValidation() async throws {
		let (store, manager) = makeAuthSessionManager()
		let recorder = RefreshRecorder()
		let snapshot = makeManagedSnapshot(accessToken: "expired-token", expiresAt: managedNow.addingTimeInterval(-1))

		await store.save(snapshot)

		let result = await manager.bootstrap { session in
			XCTAssertEqual(session.accessToken, "bootstrap-refreshed-token")

			return session
		} refresh: { session in
			try await recorder.refresh(
				session,
				accessToken: "bootstrap-refreshed-token",
				refreshToken: "bootstrap-rotated-refresh",
				expiresAt: managedNow.addingTimeInterval(900)
			)
		}

		if case .valid(let session) = result {
			let refreshCalls = await recorder.callCount()
			let storedSession = await store.load()
			XCTAssertEqual(session.accessToken, "bootstrap-refreshed-token")
			XCTAssertEqual(refreshCalls, 1)
			XCTAssertEqual(storedSession?.accessToken, "bootstrap-refreshed-token")
			XCTAssertEqual(storedSession?.refreshToken, "bootstrap-rotated-refresh")
		} else {
			XCTFail("Expected bootstrap to refresh and validate the session.")
		}

		await store.clear()
	}

	func testAuthSessionManagerBootstrapOfflinePreservesSnapshot() async throws {
		let (store, manager) = makeAuthSessionManager()
		let snapshot = makeManagedSnapshot(accessToken: "offline-token", expiresAt: managedNow.addingTimeInterval(600))

		await store.save(snapshot)

		let result = await manager.bootstrap { _ in
			throw URLError(.notConnectedToInternet)
		} refresh: { session in
			XCTFail("Refresh should not run for a valid token.")
			return session
		}

		if case .offline(let session) = result {
			let storedSession = await store.load()
			XCTAssertEqual(session.accessToken, "offline-token")
			XCTAssertEqual(storedSession?.accessToken, "offline-token")
		} else {
			XCTFail("Expected an offline bootstrap result.")
		}

		await store.clear()
	}

	func testAuthSessionManagerBootstrapAuthFailureClearsSnapshot() async throws {
		let (store, manager) = makeAuthSessionManager()
		let snapshot = makeManagedSnapshot(accessToken: "rejected-token", expiresAt: managedNow.addingTimeInterval(600))

		await store.save(snapshot)

		let result = await manager.bootstrap { _ in
			throw PairAPIError.server(statusCode: 401, payload: nil)
		} refresh: { session in
			XCTFail("Refresh should not run for a valid token.")
			return session
		}

		if case .invalidated = result {
			let storedSession = await store.load()
			XCTAssertNil(storedSession)
		} else {
			XCTFail("Expected an invalidated bootstrap result.")
		}
	}

	func testAuthSessionManagerValidTokenDoesNotCallRefresh() async throws {
		let (store, manager) = makeAuthSessionManager()
		let recorder = RefreshRecorder()
		let snapshot = makeManagedSnapshot(accessToken: "still-valid", expiresAt: managedNow.addingTimeInterval(600))

		await store.save(snapshot)

		let result = await manager.validAccessToken { session in
			try await recorder.refresh(session, accessToken: "unused-token", expiresAt: managedNow.addingTimeInterval(900))
		}

		if case .valid(let accessToken, _) = result {
			let refreshCalls = await recorder.callCount()
			XCTAssertEqual(accessToken, "still-valid")
			XCTAssertEqual(refreshCalls, 0)
		} else {
			XCTFail("Expected a valid token result.")
		}

		await store.clear()
	}

	func testAuthSessionManagerExpiredTokenCallsRefresh() async throws {
		let (store, manager) = makeAuthSessionManager()
		let recorder = RefreshRecorder()
		let snapshot = makeManagedSnapshot(accessToken: "expired-token", expiresAt: managedNow.addingTimeInterval(-1))

		await store.save(snapshot)

		let result = await manager.validAccessToken { session in
			try await recorder.refresh(
				session,
				accessToken: "refreshed-token",
				refreshToken: "rotated-refresh-token",
				expiresAt: managedNow.addingTimeInterval(900)
			)
		}

		if case .valid(let accessToken, let session) = result {
			let refreshCalls = await recorder.callCount()
			let storedSession = await store.load()
			XCTAssertEqual(accessToken, "refreshed-token")
			XCTAssertEqual(session.refreshToken, "rotated-refresh-token")
			XCTAssertEqual(refreshCalls, 1)
			XCTAssertEqual(storedSession?.accessToken, "refreshed-token")
			XCTAssertEqual(storedSession?.refreshToken, "rotated-refresh-token")
		} else {
			XCTFail("Expected a refreshed token result.")
		}

		await store.clear()
	}

	func testAuthSessionManagerConcurrentRefreshesAreCoalesced() async throws {
		let (store, manager) = makeAuthSessionManager()
		let recorder = RefreshRecorder(delayNanoseconds: 50_000_000)
		let snapshot = makeManagedSnapshot(accessToken: "expired-token", expiresAt: managedNow.addingTimeInterval(-1))

		await store.save(snapshot)

		async let first = manager.validAccessToken { session in
			try await recorder.refresh(session, accessToken: "shared-token", expiresAt: managedNow.addingTimeInterval(900))
		}
		async let second = manager.validAccessToken { session in
			try await recorder.refresh(session, accessToken: "shared-token", expiresAt: managedNow.addingTimeInterval(900))
		}
		async let third = manager.validAccessToken { session in
			try await recorder.refresh(session, accessToken: "shared-token", expiresAt: managedNow.addingTimeInterval(900))
		}

		let results = await [first, second, third]

		for result in results {
			if case .valid(let accessToken, _) = result {
				XCTAssertEqual(accessToken, "shared-token")
			} else {
				XCTFail("Expected every caller to receive the shared refreshed token.")
			}
		}

		let refreshCalls = await recorder.callCount()
		let storedSession = await store.load()
		XCTAssertEqual(refreshCalls, 1)
		XCTAssertEqual(storedSession?.accessToken, "shared-token")

		await store.clear()
	}

	func testAuthSessionManagerRefreshNetworkFailureKeepsSnapshot() async throws {
		let (store, manager) = makeAuthSessionManager()
		let snapshot = makeManagedSnapshot(accessToken: "expired-token", expiresAt: managedNow.addingTimeInterval(-1))

		await store.save(snapshot)

		let result = await manager.validAccessToken { _ in
			throw URLError(.timedOut)
		}

		if case .offline(let session) = result {
			let storedSession = await store.load()
			XCTAssertEqual(session.accessToken, "expired-token")
			XCTAssertEqual(storedSession?.accessToken, "expired-token")
		} else {
			XCTFail("Expected an offline token result.")
		}

		await store.clear()
	}

	func testAuthSessionManagerRefreshAuthFailureClearsSnapshot() async throws {
		let (store, manager) = makeAuthSessionManager()
		let snapshot = makeManagedSnapshot(accessToken: "expired-token", expiresAt: managedNow.addingTimeInterval(-1))

		await store.save(snapshot)

		let result = await manager.validAccessToken { _ in
			throw PairAPIError.server(statusCode: 401, payload: nil)
		}

		if case .invalidated = result {
			let storedSession = await store.load()
			XCTAssertNil(storedSession)
		} else {
			XCTFail("Expected an invalidated token result.")
		}
	}

	func testAuthSessionDecodesRefreshTokenAndExpiresIn() throws {
		let data = Data(#"{"user":{"id":17,"email":"token@example.test","name":"Token"},"access_token":"access-token","refresh_token":"refresh-token","expires_in":120}"#.utf8)
		let session = try JSONDecoder().decode(PairAuthSession<TestUser>.self, from: data)

		XCTAssertEqual(session.accessToken, "access-token")
		XCTAssertEqual(session.refreshToken, "refresh-token")
		XCTAssertNotNil(session.expiresAt)
	}
}

private struct TestUser: Codable, Equatable, Sendable {
	let id: Int
	let email: String
	let name: String
}

private struct TestContext: Codable, Equatable, Sendable {
	let slug: String
}

private struct TestIdentifier: Decodable, Equatable, Sendable {
	let id: Int
}

private struct TestDatedSnapshot: Codable, Equatable, Sendable {
	let token: String
	let expiresAt: Date
}

private let managedNow = Date(timeIntervalSince1970: 1_811_337_600)

private typealias ManagedStore = PairKeychainStore<PairStoredAuthSession<TestUser, TestContext>>
private typealias ManagedManager = PairAuthSessionManager<TestUser, TestContext>

/// Creates a manager and Keychain store with an isolated service for each test.
private func makeAuthSessionManager() -> (ManagedStore, ManagedManager) {
	let store = ManagedStore(service: "test.pair.auth-manager.\(UUID().uuidString)")
	let manager = ManagedManager(store: store, refreshLeeway: 60, now: { managedNow })

	return (store, manager)
}

/// Creates a managed auth snapshot with a default refresh token and context.
private func makeManagedSnapshot(
	accessToken: String,
	refreshToken: String? = "refresh-token",
	expiresAt: Date = managedNow.addingTimeInterval(600)
) -> PairStoredAuthSession<TestUser, TestContext> {
	PairStoredAuthSession(
		user: TestUser(id: 15, email: "managed@example.test", name: "Managed"),
		accessToken: accessToken,
		refreshToken: refreshToken,
		expiresAt: expiresAt,
		context: TestContext(slug: "crotone")
	)
}

private actor RefreshRecorder {
	private let delayNanoseconds: UInt64
	private var calls = 0

	/// Initializes the recorder with an optional artificial refresh delay.
	init(delayNanoseconds: UInt64 = 0) {
		self.delayNanoseconds = delayNanoseconds
	}

	/// Returns the number of refresh operations performed.
	func callCount() -> Int {
		calls
	}

	/// Records a refresh and returns a rotated session snapshot.
	func refresh(
		_ session: PairStoredAuthSession<TestUser, TestContext>,
		accessToken: String,
		refreshToken: String? = "refresh-token",
		expiresAt: Date
	) async throws -> PairStoredAuthSession<TestUser, TestContext> {
		calls += 1

		if delayNanoseconds > 0 {
			try await Task.sleep(nanoseconds: delayNanoseconds)
		}

		return PairStoredAuthSession(
			user: session.user,
			accessToken: accessToken,
			refreshToken: refreshToken,
			expiresAt: expiresAt,
			context: session.context
		)
	}
}

private final class MockTransport: PairHTTPTransport, @unchecked Sendable {
	private let lock = NSLock()
	private var queuedResponses: [(Int, Data)] = []
	private(set) var requests: [URLRequest] = []

	/// Queues a fake HTTP response for the next request.
	func enqueue(statusCode: Int, body: String) {
		lock.withPairLock {
			queuedResponses.append((statusCode, Data(body.utf8)))
		}
	}

	/// Records the request and returns the queued response.
	func perform(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
		let response = try lock.withPairLock {
			requests.append(request)

			if queuedResponses.isEmpty {
				throw PairAPIError.invalidResponse
			}

			return queuedResponses.removeFirst()
		}

		let httpResponse = HTTPURLResponse(
			url: request.url!,
			statusCode: response.0,
			httpVersion: nil,
			headerFields: ["Content-Type": "application/json"]
		)!

		return (response.1, httpResponse)
	}
}

private final class InvalidationRecorder: @unchecked Sendable {
	private let lock = NSLock()
	private var errors: [PairAPIError] = []

	var count: Int {
		lock.withPairLock {
			errors.count
		}
	}

	/// Records a session invalidation error.
	func record(_ error: PairAPIError) {
		lock.withPairLock {
			errors.append(error)
		}
	}
}

private func decodedBody(_ request: URLRequest) throws -> [String: Any] {
	let data = try XCTUnwrap(request.httpBody)
	let object = try JSONSerialization.jsonObject(with: data)

	return try XCTUnwrap(object as? [String: Any])
}
