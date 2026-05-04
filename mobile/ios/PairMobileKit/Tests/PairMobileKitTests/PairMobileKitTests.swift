import Foundation
import XCTest
@testable import PairMobileKit

final class PairMobileKitTests: XCTestCase {

	func testLoginForcesRememberMeAndStoresBearerToken() async throws {
		let transport = MockTransport()
		transport.enqueue(
			statusCode: 200,
			body: #"{"data":{"user":{"id":7,"email":"mario@example.test","name":"Mario Rossi"},"token":"plain-token"}}"#
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
		XCTAssertEqual(session.token, "plain-token")
		XCTAssertEqual(client.currentBearerToken(), "plain-token")
	}

	func testRegisterForcesRememberMe() async throws {
		let transport = MockTransport()
		transport.enqueue(
			statusCode: 201,
			body: #"{"data":{"user":{"id":8,"email":"luisa@example.test","name":"Luisa Bianchi"},"token":"register-token"}}"#
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
			session: PairAuthSession(
				user: TestUser(id: 11, email: "keychain@example.test", name: "Key Chain"),
				token: "stored-token"
			),
			defaultContext: TestContext(slug: "crotone")
		)

		await store.clear()
		await store.save(snapshot)

		let restored = await store.load()
		XCTAssertEqual(restored?.session.token, "stored-token")
		XCTAssertEqual(restored?.defaultContext?.slug, "crotone")

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

	func testSessionBootstrapperHandlesRestoredInvalidatedAndOfflineStates() async throws {
		let service = "test.pair.bootstrap.\(UUID().uuidString)"
		let store = PairKeychainStore<PairStoredAuthSession<TestUser, TestContext>>(service: service)
		let bootstrapper = PairSessionBootstrapper(store: store)
		let snapshot = PairStoredAuthSession(
			session: PairAuthSession(
				user: TestUser(id: 12, email: "boot@example.test", name: "Boot"),
				token: "boot-token"
			),
			defaultContext: TestContext(slug: "abano-terme")
		)

		await store.save(snapshot)
		let restored = await bootstrapper.bootstrap { saved in
			PairStoredAuthSession(
				session: PairAuthSession(user: saved.session.user, token: "fresh-token"),
				defaultContext: saved.defaultContext
			)
		}
		if case .restored(let value) = restored {
			XCTAssertEqual(value.session.token, "fresh-token")
		} else {
			XCTFail("Expected validated restoration.")
		}

		await store.save(snapshot)
		let invalidated = await bootstrapper.bootstrap { _ in
			throw PairAPIError.server(statusCode: 401, payload: nil)
		}
		if case .invalidated = invalidated {
			let clearedSnapshot = await store.load()
			XCTAssertNil(clearedSnapshot)
		} else {
			XCTFail("Expected session invalidation.")
		}

		await store.save(snapshot)
		let offline = await bootstrapper.bootstrap { _ in
			throw URLError(.notConnectedToInternet)
		}
		if case .offline(let value) = offline {
			XCTAssertEqual(value.session.token, "boot-token")
		} else {
			XCTFail("Expected offline restoration.")
		}

		await store.clear()
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
