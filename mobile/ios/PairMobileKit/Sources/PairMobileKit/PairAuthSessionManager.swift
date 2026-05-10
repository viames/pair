import Foundation

/// Bootstrap and token result for managed mobile authentication sessions.
public enum PairAuthSessionManagerResult<Session: Sendable>: Sendable {
	case valid(Session)
	case missing
	case offline(Session)
	case invalidated
}

/// Access-token result for callers that need an authorization header value.
public enum PairAccessTokenResult<Session: Sendable>: Sendable {
	case valid(accessToken: String, session: Session)
	case missing
	case offline(Session)
	case invalidated
}

/// Coordinates persistent mobile sessions, startup validation, and single-flight token refresh.
public actor PairAuthSessionManager<User: Codable & Sendable, Context: Codable & Sendable> {
	public typealias Session = PairStoredAuthSession<User, Context>

	private let store: PairKeychainStore<Session>
	private let refreshLeeway: TimeInterval
	private let now: @Sendable () -> Date
	private var refreshTask: Task<Session, Error>?

	/// Initializes the manager with a Keychain store and the pre-expiration refresh window.
	public init(
		store: PairKeychainStore<Session>,
		refreshLeeway: TimeInterval = 60,
		now: @escaping @Sendable () -> Date = Date.init
	) {
		self.store = store
		self.refreshLeeway = max(refreshLeeway, 0)
		self.now = now
	}

	/// Reads the persisted session snapshot.
	public func load() async -> Session? {
		await store.load()
	}

	/// Saves a newly authenticated or externally migrated session snapshot.
	public func save(_ session: Session) async {
		await store.save(session)
	}

	/// Clears the persisted session snapshot.
	public func clear() async {
		await store.clear()
	}

	/// Restores a session, refreshes stale tokens, and validates the result before app-private UI is shown.
	public func bootstrap(
		validate: @Sendable @escaping (Session) async throws -> Session,
		refresh: @Sendable @escaping (Session) async throws -> Session
	) async -> PairAuthSessionManagerResult<Session> {
		guard let session = await store.load() else {
			return .missing
		}

		do {
			let tokenSession = try await sessionWithValidAccessToken(session, refresh: refresh)
			let validatedSession = try await validate(tokenSession)

			await store.save(validatedSession)

			return .valid(validatedSession)
		} catch {
			if isDefinitiveAuthenticationFailure(error) {
				await store.clear()
				return .invalidated
			}

			return .offline(await store.load() ?? session)
		}
	}

	/// Returns a usable access token, refreshing once for all concurrent callers when needed.
	public func validAccessToken(
		refresh: @Sendable @escaping (Session) async throws -> Session
	) async -> PairAccessTokenResult<Session> {
		guard let session = await store.load() else {
			return .missing
		}

		do {
			let tokenSession = try await sessionWithValidAccessToken(session, refresh: refresh)

			return .valid(accessToken: tokenSession.accessToken, session: tokenSession)
		} catch {
			if isDefinitiveAuthenticationFailure(error) {
				await store.clear()
				return .invalidated
			}

			return .offline(session)
		}
	}

	/// Forces a token refresh and shares that refresh with concurrent callers.
	public func refreshAccessToken(
		refresh: @Sendable @escaping (Session) async throws -> Session
	) async -> PairAccessTokenResult<Session> {
		guard let session = await store.load() else {
			return .missing
		}

		do {
			let refreshedSession = try await singleFlightRefresh(session, refresh: refresh)

			return .valid(accessToken: refreshedSession.accessToken, session: refreshedSession)
		} catch {
			if isDefinitiveAuthenticationFailure(error) {
				await store.clear()
				return .invalidated
			}

			return .offline(session)
		}
	}

	/// Returns the input session when its token is usable or refreshes it through the shared task.
	private func sessionWithValidAccessToken(
		_ session: Session,
		refresh: @Sendable @escaping (Session) async throws -> Session
	) async throws -> Session {
		if isAccessTokenUsable(session) {
			return session
		}

		guard session.refreshToken != nil else {
			throw PairAuthSessionManagerInternalError.missingRefreshToken
		}

		return try await singleFlightRefresh(session, refresh: refresh)
	}

	/// Runs one refresh operation and lets later callers await the same result.
	private func singleFlightRefresh(
		_ session: Session,
		refresh: @Sendable @escaping (Session) async throws -> Session
	) async throws -> Session {
		if let refreshTask {
			return try await refreshTask.value
		}

		let task = Task<Session, Error> {
			try await refresh(session)
		}

		refreshTask = task

		do {
			let refreshedSession = try await task.value

			await store.save(refreshedSession)
			refreshTask = nil

			return refreshedSession
		} catch {
			refreshTask = nil
			throw error
		}
	}

	/// Checks whether the access token remains outside the pre-expiration refresh window.
	private func isAccessTokenUsable(_ session: Session) -> Bool {
		session.expiresAt.timeIntervalSince(now()) > refreshLeeway
	}

	/// Classifies only explicit auth failures as reasons to remove a local snapshot.
	private func isDefinitiveAuthenticationFailure(_ error: Error) -> Bool {
		if let apiError = error as? PairAPIError {
			return apiError.isAuthenticationFailure
		}

		if case PairAuthSessionManagerInternalError.missingRefreshToken = error {
			return true
		}

		return false
	}
}

private enum PairAuthSessionManagerInternalError: Error {
	case missingRefreshToken
}
