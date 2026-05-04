import Foundation

/// Result of startup session restoration.
public enum PairSessionBootstrapResult<Snapshot: Sendable>: Sendable {
	case missing
	case restored(Snapshot)
	case invalidated
	case offline(Snapshot)
}

/// Coordinates Keychain and remote validation without imposing application models.
public actor PairSessionBootstrapper<Snapshot: Codable & Sendable> {
	private let store: PairKeychainStore<Snapshot>

	/// Initializes the bootstrapper with the app's persistent store.
	public init(store: PairKeychainStore<Snapshot>) {
		self.store = store
	}

	/// Restores the saved snapshot and validates it with a closure provided by the host app.
	public func bootstrap(
		validate: @Sendable (Snapshot) async throws -> Snapshot
	) async -> PairSessionBootstrapResult<Snapshot> {
		guard let snapshot = await store.load() else {
			return .missing
		}

		do {
			let validatedSnapshot = try await validate(snapshot)
			await store.save(validatedSnapshot)

			return .restored(validatedSnapshot)
		} catch {
			if let apiError = error as? PairAPIError, apiError.isAuthenticationFailure {
				await store.clear()
				return .invalidated
			}

			return .offline(snapshot)
		}
	}
}
