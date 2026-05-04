import Foundation

/// Pair mobile auth service: `remember_me` is always enabled and not exposed to users.
public struct PairAuthService<User: Codable & Sendable>: Sendable {
	private let client: PairAPIClient

	/// Initializes the service with the API client shared by the host app.
	public init(client: PairAPIClient) {
		self.client = client
	}

	/// Performs email/password login and stores the Bearer token in the client.
	@discardableResult
	public func login(
		email: String,
		password: String,
		extraPayload: [String: PairJSONValue] = [:]
	) async throws -> PairAuthSession<User> {
		let payload = PairLoginRequest(
			email: email,
			password: password,
			extraPayload: extraPayload
		)
		let session: PairAuthSession<User> = try await client.sendData(
			path: "auth/login",
			method: "POST",
			body: payload
		)

		client.setBearerToken(session.token)
		return session
	}

	/// Performs mobile registration and stores the Bearer token in the client.
	@discardableResult
	public func register(
		name: String,
		email: String,
		password: String,
		privacyAccepted: Bool,
		extraPayload: [String: PairJSONValue] = [:]
	) async throws -> PairAuthSession<User> {
		let payload = PairRegisterRequest(
			name: name,
			email: email,
			password: password,
			privacyAccepted: privacyAccepted,
			extraPayload: extraPayload
		)
		let session: PairAuthSession<User> = try await client.sendData(
			path: "auth/register",
			method: "POST",
			body: payload
		)

		client.setBearerToken(session.token)
		return session
	}

	/// Reads the current `/auth/me` payload while letting the project define the exact response shape.
	public func currentAuthentication<Response: Decodable & Sendable>(as responseType: Response.Type = Response.self) async throws -> Response {
		try await client.sendData(path: "auth/me")
	}

	/// Revokes the current token and clears the local Bearer token.
	@discardableResult
	public func logout<Response: Decodable & Sendable>(as responseType: Response.Type) async throws -> Response {
		let response: Response = try await client.sendData(
			path: "auth/logout",
			method: "POST",
			body: PairEmptyBody()
		)

		client.setBearerToken(nil)
		return response
	}

	/// Revokes the current token when the backend returns an empty or ignorable payload.
	public func logout() async throws {
		let _: PairEmptyResponse = try await logout(as: PairEmptyResponse.self)
	}
}

private struct PairLoginRequest: Encodable {
	let email: String
	let password: String
	let extraPayload: [String: PairJSONValue]

	/// Encodes login while forcing `remember_me=true` after project-specific extensions.
	func encode(to encoder: Encoder) throws {
		var container = encoder.container(keyedBy: PairDynamicCodingKey.self)
		try container.encode(email, forKey: PairDynamicCodingKey("email"))
		try container.encode(password, forKey: PairDynamicCodingKey("password"))

		for (key, value) in extraPayload where key != "remember_me" {
			try container.encode(value, forKey: PairDynamicCodingKey(key))
		}

		try container.encode(true, forKey: PairDynamicCodingKey("remember_me"))
	}
}

private struct PairRegisterRequest: Encodable {
	let name: String
	let email: String
	let password: String
	let privacyAccepted: Bool
	let extraPayload: [String: PairJSONValue]

	/// Encodes registration while forcing `remember_me=true` after project-specific extensions.
	func encode(to encoder: Encoder) throws {
		var container = encoder.container(keyedBy: PairDynamicCodingKey.self)
		try container.encode(name, forKey: PairDynamicCodingKey("name"))
		try container.encode(email, forKey: PairDynamicCodingKey("email"))
		try container.encode(password, forKey: PairDynamicCodingKey("password"))
		try container.encode(privacyAccepted, forKey: PairDynamicCodingKey("privacy_accepted"))

		for (key, value) in extraPayload where key != "remember_me" {
			try container.encode(value, forKey: PairDynamicCodingKey(key))
		}

		try container.encode(true, forKey: PairDynamicCodingKey("remember_me"))
	}
}

private struct PairDynamicCodingKey: CodingKey {
	let stringValue: String
	let intValue: Int?

	/// Creates a dynamic key for extensible JSON payloads.
	init(_ stringValue: String) {
		self.stringValue = stringValue
		self.intValue = nil
	}

	/// Creates a dynamic key from a string value.
	init?(stringValue: String) {
		self.init(stringValue)
	}

	/// Creates a dynamic key from an integer index when Codable requires it.
	init?(intValue: Int) {
		self.stringValue = String(intValue)
		self.intValue = intValue
	}
}
