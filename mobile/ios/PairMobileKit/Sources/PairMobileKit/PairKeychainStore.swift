import Foundation

/// Generic Keychain store for Codable snapshots that can migrate between old and new phones.
public actor PairKeychainStore<Value: Codable & Sendable> {
	private let account: String
	private let dataStore: PairKeychainDataStore
	private let encoder: JSONEncoder
	private let decoder: JSONDecoder

	/// Prepares the store with namespace and account values dedicated to the host app.
	public init(
		service: String,
		account: String = "primary-session",
		encoder: JSONEncoder = JSONEncoder(),
		decoder: JSONDecoder = JSONDecoder()
	) {
		self.account = account
		self.dataStore = PairKeychainDataStore(service: service)
		self.encoder = encoder
		self.decoder = decoder
	}

	/// Reads the saved snapshot, returning `nil` when Keychain has no valid data.
	public func load() -> Value? {
		guard let data = dataStore.load(account: account),
		      let value = try? decoder.decode(Value.self, from: data) else {
			return nil
		}

		return value
	}

	/// Saves or replaces the current snapshot in Keychain.
	public func save(_ value: Value) {
		guard let data = try? encoder.encode(value) else {
			return
		}

		dataStore.save(data, account: account)
	}

	/// Removes the persisted snapshot after logout, deactivation, or invalid token responses.
	public func clear() {
		dataStore.clear(account: account)
	}
}
