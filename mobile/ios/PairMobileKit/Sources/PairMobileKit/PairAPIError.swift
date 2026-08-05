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

	private enum CodingKeys: String, CodingKey {
		case error
		case code
		case message
		case details
	}

	/// Decodes both the nested mobile envelope and Pair's established flat API error payload.
	init(from decoder: Decoder) throws {
		let container = try decoder.container(keyedBy: CodingKeys.self)

		if let nested = try? container.decode(PairAPIErrorPayload.self, forKey: .error) {
			error = nested
			return
		}

		let code = try container.decodeIfPresent(String.self, forKey: .code)
		let message = try container.decodeIfPresent(String.self, forKey: .message)
			?? container.decodeIfPresent(String.self, forKey: .error)
		let details = try container.decodeIfPresent([String: [String]].self, forKey: .details)

		error = nil == code && nil == message && nil == details
			? nil
			: PairAPIErrorPayload(code: code, message: message, details: details)
	}
}
