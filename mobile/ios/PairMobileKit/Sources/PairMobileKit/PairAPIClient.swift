import Foundation

/// Base API client for Pair mobile projects with Bearer tokens and `data` response envelopes.
public final class PairAPIClient: @unchecked Sendable {
	private let apiBaseURL: URL
	private let transport: PairHTTPTransport
	private let authenticationInvalidationHandler: (@Sendable (PairAPIError) -> Void)?
	private let tokenLock = NSLock()
	private var bearerToken: String?

	/// Initializes the client with an API base URL, an optional token, and a reusable transport.
	public init(
		apiBaseURL: URL,
		bearerToken: String? = nil,
		transport: PairHTTPTransport = PairURLSessionTransport.shared,
		authenticationInvalidationHandler: (@Sendable (PairAPIError) -> Void)? = nil
	) {
		self.apiBaseURL = apiBaseURL
		self.bearerToken = bearerToken
		self.transport = transport
		self.authenticationInvalidationHandler = authenticationInvalidationHandler
	}

	/// Updates the Bearer token used by subsequent requests.
	public func setBearerToken(_ token: String?) {
		tokenLock.withPairLock {
			bearerToken = token
		}
	}

	/// Reads the current Bearer token in a thread-safe way.
	public func currentBearerToken() -> String? {
		tokenLock.withPairLock {
			bearerToken
		}
	}

	/// Creates a derived client for another API host while keeping token, transport, and invalidation handler.
	public func client(for apiBaseURL: URL) -> PairAPIClient {
		PairAPIClient(
			apiBaseURL: apiBaseURL,
			bearerToken: currentBearerToken(),
			transport: transport,
			authenticationInvalidationHandler: authenticationInvalidationHandler
		)
	}

	/// Sends a JSON request and decodes the expected response.
	public func send<Value: Decodable & Sendable, Body: Encodable>(
		path: String,
		method: String = "GET",
		queryItems: [URLQueryItem] = [],
		body: Body? = Optional<PairEmptyBody>.none
	) async throws -> Value {
		let request = try makeRequest(path: path, method: method, queryItems: queryItems, body: body)
		let (data, response) = try await transport.perform(request)

		guard (200...299).contains(response.statusCode) else {
			let apiError = PairAPIError.server(
				statusCode: response.statusCode,
				payload: try? JSONDecoder().decode(PairAPIErrorEnvelope.self, from: data).error
			)
			notifyAuthenticationInvalidationIfNeeded(apiError)
			throw apiError
		}

		do {
			return try JSONDecoder().decode(Value.self, from: data)
		} catch {
			throw PairAPIError.decoding
		}
	}

	/// Sends a JSON request and returns the Pair envelope `data` field directly.
	public func sendData<Value: Decodable & Sendable, Body: Encodable>(
		path: String,
		method: String = "GET",
		queryItems: [URLQueryItem] = [],
		body: Body? = Optional<PairEmptyBody>.none
	) async throws -> Value {
		let envelope: PairDataEnvelope<Value> = try await send(
			path: path,
			method: method,
			queryItems: queryItems,
			body: body
		)

		return envelope.data
	}

	/// Builds a JSON request with Bearer authorization when available.
	private func makeRequest<Body: Encodable>(
		path: String,
		method: String,
		queryItems: [URLQueryItem],
		body: Body?
	) throws -> URLRequest {
		let normalizedBase = apiBaseURL.absoluteString.hasSuffix("/") ? apiBaseURL : apiBaseURL.appendingPathComponent("")
		let url = normalizedBase.appendingPathComponent(path)

		guard var components = URLComponents(url: url, resolvingAgainstBaseURL: false) else {
			throw PairAPIError.invalidBaseURL
		}

		if !queryItems.isEmpty {
			components.queryItems = queryItems
		}

		guard let finalURL = components.url else {
			throw PairAPIError.invalidBaseURL
		}

		var request = URLRequest(url: finalURL)
		request.httpMethod = method
		request.setValue("application/json", forHTTPHeaderField: "Accept")

		if let bearerToken = currentBearerToken() {
			request.setValue("Bearer \(bearerToken)", forHTTPHeaderField: "Authorization")
		}

		if let body {
			request.httpBody = try JSONEncoder().encode(body)
			request.setValue("application/json", forHTTPHeaderField: "Content-Type")
		}

		return request
	}

	/// Notifies the host app when the backend invalidates the Bearer session.
	private func notifyAuthenticationInvalidationIfNeeded(_ error: PairAPIError) {
		if error.isAuthenticationFailure {
			authenticationInvalidationHandler?(error)
		}
	}
}

extension NSLock {

	/// Executes a block under lock and returns the result.
	func withPairLock<Value>(_ operation: () throws -> Value) rethrows -> Value {
		lock()
		defer {
			unlock()
		}

		return try operation()
	}
}

public struct PairEmptyBody: Encodable, Sendable {
	public init() {}
}
