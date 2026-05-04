import Foundation

/// Standard Pair endpoint envelope for responses that return `data`.
public struct PairDataEnvelope<Value: Decodable & Sendable>: Decodable, Sendable {
	public let data: Value
}

/// Standard mobile login and registration response.
public struct PairAuthSession<User: Codable & Sendable>: Codable, Sendable {
	public let user: User
	public let token: String

	/// Initializes a mobile session with the application user and Bearer token.
	public init(user: User, token: String) {
		self.user = user
		self.token = token
	}
}

/// Standard `/auth/me` response when the project exposes only the current user.
public struct PairCurrentUserResponse<User: Codable & Sendable>: Codable, Sendable {
	public let user: User

	/// Initializes the current-user payload.
	public init(user: User) {
		self.user = user
	}
}

/// Minimal response for logout or mutations without application payload.
public struct PairEmptyResponse: Codable, Equatable, Sendable {
	public init() {}
}

/// Full snapshot to store in Keychain, with optional context defined by the host app.
public struct PairStoredAuthSession<User: Codable & Sendable, Context: Codable & Sendable>: Codable, Sendable {
	public let session: PairAuthSession<User>
	public let defaultContext: Context?

	/// Initializes the persistent mobile session snapshot.
	public init(session: PairAuthSession<User>, defaultContext: Context?) {
		self.session = session
		self.defaultContext = defaultContext
	}
}
