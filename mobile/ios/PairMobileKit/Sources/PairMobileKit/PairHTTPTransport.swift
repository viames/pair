import Foundation

/// HTTP transport that host apps and tests can replace.
public protocol PairHTTPTransport: Sendable {
	func perform(_ request: URLRequest) async throws -> (Data, HTTPURLResponse)
}

/// URLSession transport for native apps: no cookies, HTTP cache, and conservative timeouts.
public final class PairURLSessionTransport: PairHTTPTransport, @unchecked Sendable {
	public static let shared = PairURLSessionTransport()

	private let session: URLSession

	/// Creates the transport with an injectable session for tests or host apps.
	public init(session: URLSession? = nil) {
		self.session = session ?? Self.makeSession()
	}

	/// Performs a request and guarantees that the response is HTTP.
	public func perform(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
		let (data, response) = try await session.data(for: request)

		guard let httpResponse = response as? HTTPURLResponse else {
			throw PairAPIError.invalidResponse
		}

		return (data, httpResponse)
	}

	/// Builds a URLSession isolated from web cookies and suited for persistent Bearer sessions.
	private static func makeSession() -> URLSession {
		let configuration = URLSessionConfiguration.default
		configuration.urlCache = URLCache(
			memoryCapacity: 32 * 1024 * 1024,
			diskCapacity: 160 * 1024 * 1024,
			diskPath: "pair.mobile.api-cache"
		)
		configuration.requestCachePolicy = .useProtocolCachePolicy
		configuration.timeoutIntervalForRequest = 30
		configuration.timeoutIntervalForResource = 90
		configuration.waitsForConnectivity = true
		configuration.httpCookieAcceptPolicy = .never
		configuration.httpCookieStorage = nil
		configuration.httpShouldSetCookies = false

		return URLSession(configuration: configuration)
	}
}
