import Foundation

/// Normalized error payload returned by Pair API endpoints.
public struct PairAPIErrorPayload: Codable, Equatable, Sendable {
	public let code: String?
	public let message: String?
	public let details: [String: [String]]?

	/// Initializes the backend error payload.
	public init(code: String? = nil, message: String? = nil, details: [String: [String]]? = nil) {
		self.code = code
		self.message = message
		self.details = details
	}
}

/// Common client errors for Pair-based native apps.
public enum PairAPIError: Error, Equatable, Sendable {
	case invalidBaseURL
	case invalidResponse
	case server(statusCode: Int, payload: PairAPIErrorPayload?)
	case decoding
}

public extension PairAPIError {

	/// Indicates whether the backend rejected the current Bearer session.
	var isAuthenticationFailure: Bool {
		if case .server(let statusCode, _) = self {
			return statusCode == 401
		}

		return false
	}
}

struct PairAPIErrorEnvelope: Decodable {
	let error: PairAPIErrorPayload?
}
