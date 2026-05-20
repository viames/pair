import Foundation

/// Standard Pair endpoint envelope for responses that return `data`.
public struct PairDataEnvelope<Value: Decodable & Sendable>: Decodable, Sendable {
	public let data: Value
}

/// Standard mobile login and registration response.
public struct PairAuthSession<User: Codable & Sendable>: Codable, Sendable {
	public let user: User
	public let accessToken: String
	public let refreshToken: String?
	public let expiresAt: Date

	/// Initializes a mobile session with the application user and token metadata.
	public init(user: User, accessToken: String, refreshToken: String? = nil, expiresAt: Date) {
		self.user = user
		self.accessToken = accessToken
		self.refreshToken = refreshToken
		self.expiresAt = expiresAt
	}

	/// Initializes a mobile session by calculating expiration from an `expires_in` value.
	public init(user: User, accessToken: String, refreshToken: String? = nil, expiresIn: TimeInterval, issuedAt: Date = Date()) {
		self.init(
			user: user,
			accessToken: accessToken,
			refreshToken: refreshToken,
			expiresAt: issuedAt.addingTimeInterval(expiresIn)
		)
	}

	/// Decodes mobile token responses from snake-case Pair API fields.
	public init(from decoder: Decoder) throws {
		let container = try decoder.container(keyedBy: CodingKeys.self)

		user = try container.decode(User.self, forKey: .user)
		accessToken = try container.decode(String.self, forKey: .accessToken)
		refreshToken = try container.decodeIfPresent(String.self, forKey: .refreshToken)

		if let decodedExpiresAt = try Self.decodeDate(from: container, forKey: .expiresAt) {
			expiresAt = decodedExpiresAt
		} else if let expiresIn = try Self.decodeTimeInterval(from: container, forKey: .expiresIn) {
			expiresAt = Date().addingTimeInterval(expiresIn)
		} else {
			throw DecodingError.keyNotFound(
				CodingKeys.expiresAt,
				DecodingError.Context(codingPath: decoder.codingPath, debugDescription: "Missing access-token expiration.")
			)
		}
	}

	/// Encodes the session using stable snake-case token metadata keys.
	public func encode(to encoder: Encoder) throws {
		var container = encoder.container(keyedBy: CodingKeys.self)

		try container.encode(user, forKey: .user)
		try container.encode(accessToken, forKey: .accessToken)
		try container.encodeIfPresent(refreshToken, forKey: .refreshToken)
		try container.encode(expiresAt, forKey: .expiresAt)
	}

	/// Reads expiration dates from stored snapshots and backend JSON formats.
	private static func decodeDate(
		from container: KeyedDecodingContainer<CodingKeys>,
		forKey key: CodingKeys
	) throws -> Date? {
		if let seconds = try? container.decodeIfPresent(Double.self, forKey: key) {
			return seconds > 1_000_000_000
				? Date(timeIntervalSince1970: seconds)
				: Date(timeIntervalSinceReferenceDate: seconds)
		}

		if let string = try? container.decodeIfPresent(String.self, forKey: key),
		   let date = Self.decodeDateString(string) {
			return date
		}

		return try? container.decodeIfPresent(Date.self, forKey: key)
	}

	/// Reads duration values from numeric or string `expires_in` response fields.
	private static func decodeTimeInterval(
		from container: KeyedDecodingContainer<CodingKeys>,
		forKey key: CodingKeys
	) throws -> TimeInterval? {
		if let value = try? container.decodeIfPresent(Double.self, forKey: key) {
			return value
		}

		if let string = try? container.decodeIfPresent(String.self, forKey: key) {
			return TimeInterval(string)
		}

		return nil
	}

	/// Parses ISO 8601 dates, including fractional seconds commonly returned by APIs.
	private static func decodeDateString(_ value: String) -> Date? {
		let formatter = ISO8601DateFormatter()
		formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]

		if let date = formatter.date(from: value) {
			return date
		}

		formatter.formatOptions = [.withInternetDateTime]

		return formatter.date(from: value)
	}

	private enum CodingKeys: String, CodingKey {
		case user
		case accessToken = "access_token"
		case refreshToken = "refresh_token"
		case expiresAt = "expires_at"
		case expiresIn = "expires_in"
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
	public let user: User
	public let accessToken: String
	public let refreshToken: String?
	public let expiresAt: Date
	public let context: Context?

	/// Initializes the persistent mobile auth snapshot.
	public init(
		user: User,
		accessToken: String,
		refreshToken: String? = nil,
		expiresAt: Date,
		context: Context? = nil
	) {
		self.user = user
		self.accessToken = accessToken
		self.refreshToken = refreshToken
		self.expiresAt = expiresAt
		self.context = context
	}

	/// Creates a persistent snapshot from a freshly returned auth session.
	public init(session: PairAuthSession<User>, context: Context? = nil) {
		self.init(
			user: session.user,
			accessToken: session.accessToken,
			refreshToken: session.refreshToken,
			expiresAt: session.expiresAt,
			context: context
		)
	}
}
