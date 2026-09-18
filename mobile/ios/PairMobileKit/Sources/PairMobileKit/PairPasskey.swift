import AuthenticationServices
import Foundation

#if canImport(UIKit)
import UIKit
#elseif canImport(AppKit)
import AppKit
#endif

/// Errors raised before a passkey credential reaches the Pair backend.
public enum PairPasskeyError: Error, LocalizedError, Sendable {
	case invalidCredential
	case invalidServerOptions
	case missingPresentationAnchor
	case operationInProgress
	case unsupportedCredential

	public var errorDescription: String? {
		switch self {
		case .invalidCredential:
			return "The passkey credential is invalid."
		case .invalidServerOptions:
			return "The passkey options returned by the server are invalid."
		case .missingPresentationAnchor:
			return "The passkey request cannot be presented right now."
		case .operationInProgress:
			return "Another passkey request is already in progress."
		case .unsupportedCredential:
			return "The credential provider returned an unsupported credential."
		}
	}
}

private struct PairPasskeyCredentialBox: @unchecked Sendable {
	let credential: any ASAuthorizationCredential
}

/// Descriptor used by WebAuthn allow and exclude credential lists.
public struct PairPasskeyDescriptor: Codable, Equatable, Sendable {
	public let type: String
	public let id: String
}

/// Standard Pair response for a native passkey authentication challenge.
public struct PairPasskeyAuthenticationOptions: Decodable, Sendable {
	public let flowID: String
	public let publicKey: PublicKey

	public struct PublicKey: Decodable, Sendable {
		public let challenge: String
		public let rpId: String
		public let userVerification: String
		public let allowCredentials: [PairPasskeyDescriptor]
	}

	private enum CodingKeys: String, CodingKey {
		case flowID = "flow_id"
		case publicKey
	}
}

/// Standard Pair response for a native passkey registration challenge.
public struct PairPasskeyRegistrationOptions: Decodable, Sendable {
	public let flowID: String
	public let publicKey: PublicKey

	public struct AuthenticatorSelection: Decodable, Sendable {
		public let residentKey: String
		public let userVerification: String
	}

	public struct PublicKey: Decodable, Sendable {
		public let challenge: String
		public let rp: RelyingParty
		public let user: User
		public let attestation: String
		public let authenticatorSelection: AuthenticatorSelection
		public let excludeCredentials: [PairPasskeyDescriptor]
	}

	public struct RelyingParty: Decodable, Sendable {
		public let id: String
		public let name: String
	}

	public struct User: Decodable, Sendable {
		public let id: String
		public let name: String
		public let displayName: String
	}

	private enum CodingKeys: String, CodingKey {
		case flowID = "flow_id"
		case publicKey
	}
}

/// Assertion payload accepted by Pair's native passkey verification endpoint.
public struct PairPasskeyAssertionCredential: Encodable, Sendable {
	public let id: String
	public let type = "public-key"
	public let response: Response

	public struct Response: Encodable, Sendable {
		public let clientDataJSON: String
		public let authenticatorData: String
		public let signature: String
		public let userHandle: String?
	}

	/// Builds the backend payload from an AuthenticationServices assertion.
	init(assertion: ASAuthorizationPlatformPublicKeyCredentialAssertion) {
		id = assertion.credentialID.pairPasskeyBase64URL
		response = Response(
			clientDataJSON: assertion.rawClientDataJSON.pairPasskeyBase64URL,
			authenticatorData: assertion.rawAuthenticatorData.pairPasskeyBase64URL,
			signature: assertion.signature.pairPasskeyBase64URL,
			userHandle: assertion.userID.isEmpty ? nil : assertion.userID.pairPasskeyBase64URL
		)
	}
}

/// Registration payload accepted by Pair's native passkey verification endpoint.
public struct PairPasskeyRegistrationCredential: Encodable, Sendable {
	public let id: String
	public let type = "public-key"
	public let response: Response

	public struct Response: Encodable, Sendable {
		public let clientDataJSON: String
		public let attestationObject: String
		public let transports: [String]
	}

	/// Builds the backend payload from an AuthenticationServices registration.
	init(
		registration: ASAuthorizationPlatformPublicKeyCredentialRegistration,
		attestationObject: Data
	) {
		id = registration.credentialID.pairPasskeyBase64URL
		response = Response(
			clientDataJSON: registration.rawClientDataJSON.pairPasskeyBase64URL,
			attestationObject: attestationObject.pairPasskeyBase64URL,
			transports: registration.attachment == .platform ? ["internal"] : []
		)
	}
}

/// Privacy-minimal passkey metadata returned by Pair account management.
public struct PairPasskey: Codable, Equatable, Identifiable, Sendable {
	public let id: Int
	public let label: String?
	public let createdAt: String?
	public let lastUsedAt: String?
	public let transports: [String]

	private enum CodingKeys: String, CodingKey {
		case id
		case label
		case createdAt = "created_at"
		case lastUsedAt = "last_used_at"
		case transports
	}
}

/// Presents platform passkey requests while leaving API routing and application UI to the host app.
@MainActor
public final class PairPasskeyAuthorizationCoordinator: NSObject {
	public typealias PresentationAnchorProvider = @MainActor () -> ASPresentationAnchor?

	private let apiBaseURL: URL
	private let presentationAnchorProvider: PresentationAnchorProvider
	private var activeController: ASAuthorizationController?
	private var activePresentationAnchor: ASPresentationAnchor?
	private var continuation: CheckedContinuation<PairPasskeyCredentialBox, Error>?

	/// Creates a coordinator bound to the API host that owns the relying-party domain.
	public init(
		apiBaseURL: URL,
		presentationAnchorProvider: PresentationAnchorProvider? = nil
	) {
		self.apiBaseURL = apiBaseURL
		self.presentationAnchorProvider = presentationAnchorProvider ?? Self.defaultPresentationAnchor
	}

	/// Presents a discoverable passkey assertion and returns Pair's credential payload.
	public func authenticate(options: PairPasskeyAuthenticationOptions.PublicKey) async throws -> PairPasskeyAssertionCredential {
		guard options.userVerification == "required",
			Self.isTrustedRelyingParty(options.rpId, apiBaseURL: apiBaseURL),
			let challenge = Data(pairPasskeyBase64URL: options.challenge, maximumByteCount: 1_024) else {
			throw PairPasskeyError.invalidServerOptions
		}

		let provider = ASAuthorizationPlatformPublicKeyCredentialProvider(relyingPartyIdentifier: options.rpId)
		let request = provider.createCredentialAssertionRequest(challenge: challenge)
		request.userVerificationPreference = .required
		request.allowedCredentials = try options.allowCredentials.map(Self.platformDescriptor)

		let credential = try await authorize(request)

		guard let assertion = credential as? ASAuthorizationPlatformPublicKeyCredentialAssertion else {
			throw PairPasskeyError.unsupportedCredential
		}

		return PairPasskeyAssertionCredential(assertion: assertion)
	}

	/// Presents passkey creation and returns the native attestation payload expected by Pair.
	public func register(options: PairPasskeyRegistrationOptions.PublicKey) async throws -> PairPasskeyRegistrationCredential {
		guard options.authenticatorSelection.userVerification == "required",
			options.authenticatorSelection.residentKey == "required",
			options.attestation == "none",
			Self.isTrustedRelyingParty(options.rp.id, apiBaseURL: apiBaseURL),
			let challenge = Data(pairPasskeyBase64URL: options.challenge, maximumByteCount: 1_024),
			let userID = Data(pairPasskeyBase64URL: options.user.id, maximumByteCount: 4_096),
			!userID.isEmpty,
			!options.user.name.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
			throw PairPasskeyError.invalidServerOptions
		}

		let provider = ASAuthorizationPlatformPublicKeyCredentialProvider(relyingPartyIdentifier: options.rp.id)
		let request = provider.createCredentialRegistrationRequest(
			challenge: challenge,
			name: options.user.name,
			userID: userID
		)
		request.displayName = options.user.displayName
		request.userVerificationPreference = .required
		request.attestationPreference = .none

		if #available(iOS 17.4, macOS 14.4, *) {
			request.excludedCredentials = try options.excludeCredentials.map(Self.platformDescriptor)
		}

		let credential = try await authorize(request)

		guard let registration = credential as? ASAuthorizationPlatformPublicKeyCredentialRegistration,
			let attestationObject = registration.rawAttestationObject,
			!attestationObject.isEmpty else {
			throw PairPasskeyError.invalidCredential
		}

		return PairPasskeyRegistrationCredential(
			registration: registration,
			attestationObject: attestationObject
		)
	}

	/// Cancel the current platform sheet and resume its task exactly once.
	private func cancelActiveAuthorization() {
		guard continuation != nil else {
			return
		}

		activeController?.cancel()
		finish(.failure(CancellationError()))
	}

	/// Return a foreground application window on supported Apple platforms.
	private static func defaultPresentationAnchor() -> ASPresentationAnchor? {
		#if canImport(UIKit)
		return UIApplication.shared.connectedScenes
			.compactMap { $0 as? UIWindowScene }
			.filter { $0.activationState == .foregroundActive }
			.flatMap(\.windows)
			.first(where: \.isKeyWindow)
		#elseif canImport(AppKit)
		return NSApplication.shared.keyWindow
		#else
		return nil
		#endif
	}

	/// Resume and release the active controller, continuation, and anchor.
	private func finish(_ result: Result<PairPasskeyCredentialBox, Error>) {
		let continuation = continuation
		self.continuation = nil
		activeController = nil
		activePresentationAnchor = nil
		continuation?.resume(with: result)
	}

	/// Validate that a relying party is the API host or one of its parent domains.
	static func isTrustedRelyingParty(_ relyingPartyID: String, apiBaseURL: URL) -> Bool {
		let normalized = relyingPartyID.lowercased()
		let labels = normalized.split(separator: ".", omittingEmptySubsequences: false)

		guard !normalized.isEmpty,
			!labels.contains(where: { $0.isEmpty || $0.hasPrefix("-") || $0.hasSuffix("-") }),
			normalized.unicodeScalars.allSatisfy({
				CharacterSet(charactersIn: "abcdefghijklmnopqrstuvwxyz0123456789.-").contains($0)
			}),
			let host = apiBaseURL.host?.lowercased() else {
			return false
		}

		return host == normalized || host.hasSuffix(".\(normalized)")
	}

	/// Convert one WebAuthn descriptor into the AuthenticationServices representation.
	private static func platformDescriptor(_ descriptor: PairPasskeyDescriptor) throws -> ASAuthorizationPlatformPublicKeyCredentialDescriptor {
		guard descriptor.type == "public-key",
			let identifier = Data(pairPasskeyBase64URL: descriptor.id, maximumByteCount: 4_096) else {
			throw PairPasskeyError.invalidServerOptions
		}

		return ASAuthorizationPlatformPublicKeyCredentialDescriptor(credentialID: identifier)
	}

	/// Present exactly one AuthenticationServices request with cancellation propagation.
	private func authorize(_ request: ASAuthorizationRequest) async throws -> any ASAuthorizationCredential {
		guard continuation == nil else {
			throw PairPasskeyError.operationInProgress
		}
		guard let anchor = presentationAnchorProvider() else {
			throw PairPasskeyError.missingPresentationAnchor
		}

		let box = try await withTaskCancellationHandler {
			try await withCheckedThrowingContinuation { continuation in
				self.continuation = continuation
				activePresentationAnchor = anchor
				let controller = ASAuthorizationController(authorizationRequests: [request])
				controller.delegate = self
				controller.presentationContextProvider = self
				activeController = controller
				controller.performRequests()
			}
		} onCancel: {
			Task { @MainActor [weak self] in
				self?.cancelActiveAuthorization()
			}
		}

		return box.credential
	}
}

extension PairPasskeyAuthorizationCoordinator: ASAuthorizationControllerDelegate {
	public func authorizationController(
		controller: ASAuthorizationController,
		didCompleteWithAuthorization authorization: ASAuthorization
	) {
		finish(.success(PairPasskeyCredentialBox(credential: authorization.credential)))
	}

	public func authorizationController(
		controller: ASAuthorizationController,
		didCompleteWithError error: Error
	) {
		if let authorizationError = error as? ASAuthorizationError, authorizationError.code == .canceled {
			finish(.failure(CancellationError()))
		} else {
			finish(.failure(error))
		}
	}
}

extension PairPasskeyAuthorizationCoordinator: ASAuthorizationControllerPresentationContextProviding {
	public func presentationAnchor(for controller: ASAuthorizationController) -> ASPresentationAnchor {
		activePresentationAnchor ?? ASPresentationAnchor()
	}
}

/// End-to-end client for Pair's standard native passkey login and account-management endpoints.
@MainActor
public final class PairPasskeyService<User: Codable & Sendable> {
	private let client: PairAPIClient
	private let coordinator: PairPasskeyAuthorizationCoordinator

	/// Creates a service using the API client shared by the host application.
	public init(client: PairAPIClient, coordinator: PairPasskeyAuthorizationCoordinator) {
		self.client = client
		self.coordinator = coordinator
	}

	/// Authenticate with a discoverable passkey and store the returned Bearer token.
	@discardableResult
	public func login(deviceName: String? = nil) async throws -> PairAuthSession<User> {
		let options: PairPasskeyAuthenticationOptions = try await client.sendData(
			path: "auth/passkey/options",
			method: "POST",
			body: PairEmptyBody()
		)
		let credential = try await coordinator.authenticate(options: options.publicKey)
		let session: PairAuthSession<User> = try await client.sendData(
			path: "auth/passkey/verify",
			method: "POST",
			body: PairPasskeyLoginVerificationRequest(
				flowID: options.flowID,
				credential: credential,
				deviceName: deviceName
			)
		)

		client.setBearerToken(session.accessToken)
		return session
	}

	/// List the current user's active passkeys.
	public func passkeys() async throws -> [PairPasskey] {
		let response: PairPasskeyList = try await client.sendData(path: "auth/passkeys")
		return response.items
	}

	/// Create a discoverable passkey for the current Bearer-authenticated user.
	@discardableResult
	public func register(displayName: String, label: String? = nil) async throws -> PairPasskey {
		let options: PairPasskeyRegistrationOptions = try await client.sendData(
			path: "auth/passkeys/options",
			method: "POST",
			body: PairPasskeyRegistrationOptionsRequest(displayName: displayName)
		)
		let credential = try await coordinator.register(options: options.publicKey)
		let result: PairPasskeyRegistrationResult = try await client.sendData(
			path: "auth/passkeys/verify",
			method: "POST",
			body: PairPasskeyRegistrationVerificationRequest(
				flowID: options.flowID,
				credential: credential,
				label: label
			)
		)

		return result.passkey
	}

	/// Revoke one passkey owned by the current user.
	public func revoke(id: Int) async throws {
		let _: PairEmptyResponse = try await client.sendData(
			path: "auth/passkeys/\(id)",
			method: "DELETE"
		)
	}
}

private struct PairPasskeyList: Decodable, Sendable {
	let items: [PairPasskey]
}

private struct PairPasskeyLoginVerificationRequest: Encodable, Sendable {
	let flowID: String
	let credential: PairPasskeyAssertionCredential
	let deviceName: String?

	private enum CodingKeys: String, CodingKey {
		case flowID = "flow_id"
		case credential
		case deviceName = "device_name"
	}
}

private struct PairPasskeyRegistrationOptionsRequest: Encodable, Sendable {
	let displayName: String

	private enum CodingKeys: String, CodingKey {
		case displayName = "display_name"
	}
}

private struct PairPasskeyRegistrationResult: Decodable, Sendable {
	let passkey: PairPasskey
}

private struct PairPasskeyRegistrationVerificationRequest: Encodable, Sendable {
	let flowID: String
	let credential: PairPasskeyRegistrationCredential
	let label: String?

	private enum CodingKeys: String, CodingKey {
		case flowID = "flow_id"
		case credential
		case label
	}
}

private extension Data {
	/// Decode bounded, unpadded WebAuthn base64url.
	init?(pairPasskeyBase64URL value: String, maximumByteCount: Int) {
		let value = value.trimmingCharacters(in: .whitespacesAndNewlines)

		guard !value.isEmpty,
			value.count <= maximumByteCount * 2,
			value.unicodeScalars.allSatisfy({
				CharacterSet(charactersIn: "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_").contains($0)
			}),
			value.count % 4 != 1 else {
			return nil
		}

		let standard = value
			.replacingOccurrences(of: "-", with: "+")
			.replacingOccurrences(of: "_", with: "/")
		let padding = String(repeating: "=", count: (4 - standard.count % 4) % 4)

		guard let decoded = Data(base64Encoded: standard + padding), decoded.count <= maximumByteCount else {
			return nil
		}

		self = decoded
	}

	/// Encode bytes as unpadded WebAuthn base64url.
	var pairPasskeyBase64URL: String {
		base64EncodedString()
			.replacingOccurrences(of: "+", with: "-")
			.replacingOccurrences(of: "/", with: "_")
			.replacingOccurrences(of: "=", with: "")
	}
}
