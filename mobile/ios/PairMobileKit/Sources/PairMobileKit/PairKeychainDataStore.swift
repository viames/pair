import Foundation
import Security

/// Synchronous Keychain data store for apps that cannot adopt the async Codable store directly.
public final class PairKeychainDataStore: @unchecked Sendable {
	private let service: String
	private let itemAccessibility: CFString

	/// Prepares a Keychain namespace for the host app.
	public init(service: String, itemAccessibility: CFString = kSecAttrAccessibleAfterFirstUnlock) {
		self.service = service
		self.itemAccessibility = itemAccessibility
	}

	/// Reads raw Keychain item data when present and migrates the item accessibility.
	public func load(account: String) -> Data? {
		let query = baseQuery(account: account).merging([
			kSecReturnData as String: kCFBooleanTrue as Any,
			kSecMatchLimit as String: kSecMatchLimitOne,
		]) { current, _ in current }

		var result: AnyObject?
		let status = SecItemCopyMatching(query as CFDictionary, &result)

		guard status == errSecSuccess, let data = result as? Data else {
			return nil
		}

		save(data, account: account)

		return data
	}

	/// Saves or replaces raw Keychain item data.
	public func save(_ data: Data, account: String) {
		let query = baseQuery(account: account)
		let values = [
			kSecAttrAccessible as String: itemAccessibility,
			kSecValueData as String: data,
		] as [String: Any]

		let updateStatus = SecItemUpdate(query as CFDictionary, values as CFDictionary)

		if updateStatus == errSecSuccess {
			return
		}

		if updateStatus != errSecItemNotFound {
			SecItemDelete(query as CFDictionary)
		}

		let attributes = query.merging(values) { current, _ in current }
		SecItemAdd(attributes as CFDictionary, nil)
	}

	/// Removes the Keychain item for the supplied account.
	public func clear(account: String) {
		SecItemDelete(baseQuery(account: account) as CFDictionary)
	}

	/// Builds the shared query used to read, write, and delete a Keychain item.
	private func baseQuery(account: String) -> [String: Any] {
		[
			kSecClass as String: kSecClassGenericPassword,
			kSecAttrService as String: service,
			kSecAttrAccount as String: account,
		]
	}
}
