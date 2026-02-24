(function (global) {
	"use strict";

	class PairPasskey {

		/**
		 * Start login ceremony and return server publicKey options.
		 * @param {{url?: string, username?: string|null, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object>}
		 */
		static async beginLogin(options = {}) {

			const url = options.url || "/api/passkey/login/options";
			const username = options.username ?? null;

			const payload = {};

			if (typeof username === "string" && username.trim() !== "") {
				payload.username = username.trim();
			}

			const data = await this.requestJson(url, {
				method: "POST",
				body: payload,
				requestOptions: options.requestOptions || {}
			});

			return data && data.publicKey ? data.publicKey : data;

		}

		/**
		 * Start registration ceremony and return server publicKey options.
		 * @param {{url?: string, displayName?: string|null, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object>}
		 */
		static async beginRegistration(options = {}) {

			const url = options.url || "/api/passkey/register/options";
			const displayName = options.displayName ?? null;
			const payload = {};

			if (typeof displayName === "string" && displayName.trim() !== "") {
				payload.displayName = displayName.trim();
			}

			const data = await this.requestJson(url, {
				method: "POST",
				body: payload,
				requestOptions: options.requestOptions || {}
			});

			return data && data.publicKey ? data.publicKey : data;

		}

		/**
		 * Convert a base64url string to ArrayBuffer.
		 * @param {string} value
		 * @returns {ArrayBuffer}
		 */
		static bufferFromBase64Url(value) {

			const input = String(value || "").trim();

			if (!input) {
				return new Uint8Array(0).buffer;
			}

			const padded = (input + "===".slice((input.length + 3) % 4)).replace(/-/g, "+").replace(/_/g, "/");
			const binary = atob(padded);
			const bytes = new Uint8Array(binary.length);

			for (let i = 0; i < binary.length; i += 1) {
				bytes[i] = binary.charCodeAt(i);
			}

			return bytes.buffer;

		}

		/**
		 * Creates a credential using navigator.credentials.create.
		 * @param {Object} publicKeyOptions
		 * @returns {Promise<Object>} Serialized credential
		 */
		static async createCredential(publicKeyOptions) {

			this.ensureSupported();

			const prepared = this.prepareCreationOptions(publicKeyOptions);
			const credential = await navigator.credentials.create({ publicKey: prepared });

			return this.serializeCredential(credential);

		}

		/**
		 * Finalize login by posting assertion payload to backend.
		 * @param {{url?: string, credential: Object, username?: string|null, timezone?: string|null, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object>}
		 */
		static async finishLogin(options = {}) {

			const url = options.url || "/api/passkey/login/verify";

			if (!options.credential || typeof options.credential !== "object") {
				throw new Error("Missing passkey credential payload.");
			}

			const payload = {
				credential: options.credential,
				timezone: options.timezone || this.getDefaultTimeZone()
			};

			if (typeof options.username === "string" && options.username.trim() !== "") {
				payload.username = options.username.trim();
			}

			return this.requestJson(url, {
				method: "POST",
				body: payload,
				requestOptions: options.requestOptions || {}
			});

		}

		/**
		 * Finalize registration by posting attestation payload to backend.
		 * @param {{url?: string, credential: Object, label?: string|null, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object>}
		 */
		static async finishRegistration(options = {}) {

			const url = options.url || "/api/passkey/register/verify";

			if (!options.credential || typeof options.credential !== "object") {
				throw new Error("Missing passkey credential payload.");
			}

			const payload = {
				credential: options.credential
			};

			if (typeof options.label === "string" && options.label.trim() !== "") {
				payload.label = options.label.trim();
			}

			return this.requestJson(url, {
				method: "POST",
				body: payload,
				requestOptions: options.requestOptions || {}
			});

		}

		/**
		 * Get assertion by using navigator.credentials.get.
		 * @param {Object} publicKeyOptions
		 * @returns {Promise<Object>} Serialized credential
		 */
		static async getAssertion(publicKeyOptions) {

			this.ensureSupported();

			const prepared = this.prepareRequestOptions(publicKeyOptions);
			const credential = await navigator.credentials.get({ publicKey: prepared });

			return this.serializeCredential(credential);

		}

		/**
		 * Returns browser time zone or UTC fallback.
		 * @returns {string}
		 */
		static getDefaultTimeZone() {

			try {
				const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
				return (typeof tz === "string" && tz.trim() !== "") ? tz : "UTC";
			} catch (_error) {
				return "UTC";
			}

		}

		/**
		 * Check if WebAuthn is supported.
		 * @returns {boolean}
		 */
		static isSupported() {

			return !!(
				global &&
				global.isSecureContext &&
				global.PublicKeyCredential &&
				navigator &&
				navigator.credentials &&
				typeof navigator.credentials.create === "function" &&
				typeof navigator.credentials.get === "function"
			);

		}

		/**
		 * High-level login helper.
		 * @param {{optionsUrl?: string, verifyUrl?: string, username?: string|null, timezone?: string|null, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object>}
		 */
		static async login(options = {}) {

			const publicKey = await this.beginLogin({
				url: options.optionsUrl || "/api/passkey/login/options",
				username: options.username ?? null,
				requestOptions: options.requestOptions || {}
			});

			const credential = await this.getAssertion(publicKey);

			return this.finishLogin({
				url: options.verifyUrl || "/api/passkey/login/verify",
				credential,
				username: options.username ?? null,
				timezone: options.timezone || this.getDefaultTimeZone(),
				requestOptions: options.requestOptions || {}
			});

		}

		/**
		 * List active passkeys for current authenticated user.
		 * @param {{url?: string, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object|Array>}
		 */
		static async list(options = {}) {

			const url = options.url || "/api/passkey/list";

			return this.requestJson(url, {
				method: "GET",
				requestOptions: options.requestOptions || {}
			});

		}

		/**
		 * Prepare creation options converting base64url fields to ArrayBuffer.
		 * @param {Object} options
		 * @returns {Object}
		 */
		static prepareCreationOptions(options = {}) {

			const publicKey = { ...(options || {}) };

			if (typeof publicKey.challenge === "string") {
				publicKey.challenge = this.bufferFromBase64Url(publicKey.challenge);
			}

			if (publicKey.user && typeof publicKey.user.id === "string") {
				publicKey.user = { ...publicKey.user, id: this.bufferFromBase64Url(publicKey.user.id) };
			}

			if (Array.isArray(publicKey.excludeCredentials)) {
				publicKey.excludeCredentials = publicKey.excludeCredentials.map((item) => {
					if (!item || typeof item !== "object") {
						return item;
					}

					return {
						...item,
						id: typeof item.id === "string" ? this.bufferFromBase64Url(item.id) : item.id
					};
				});
			}

			return publicKey;

		}

		/**
		 * Prepare request options converting base64url fields to ArrayBuffer.
		 * @param {Object} options
		 * @returns {Object}
		 */
		static prepareRequestOptions(options = {}) {

			const publicKey = { ...(options || {}) };

			if (typeof publicKey.challenge === "string") {
				publicKey.challenge = this.bufferFromBase64Url(publicKey.challenge);
			}

			if (Array.isArray(publicKey.allowCredentials)) {
				publicKey.allowCredentials = publicKey.allowCredentials.map((item) => {
					if (!item || typeof item !== "object") {
						return item;
					}

					return {
						...item,
						id: typeof item.id === "string" ? this.bufferFromBase64Url(item.id) : item.id
					};
				});
			}

			return publicKey;

		}

		/**
		 * Request JSON helper with strict error propagation.
		 * @param {string} url
		 * @param {{method?: string, body?: Object|null, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object|Array|null>}
		 */
		static async requestJson(url, options = {}) {

			const method = String(options.method || "GET").toUpperCase();
			const body = options.body ?? null;
			const requestOptions = options.requestOptions || {};
			const headers = {
				...(requestOptions.headers || {})
			};

			if (body !== null && method !== "GET" && method !== "HEAD") {
				headers["Content-Type"] = headers["Content-Type"] || "application/json";
			}

			const response = await fetch(url, {
				method,
				credentials: requestOptions.credentials || "same-origin",
				...requestOptions,
				headers,
				body: (body !== null && method !== "GET" && method !== "HEAD") ? JSON.stringify(body) : undefined
			});

			return this.readJsonResponse(response);

		}

		/**
		 * High-level registration helper.
		 * @param {{optionsUrl?: string, verifyUrl?: string, displayName?: string|null, label?: string|null, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object>}
		 */
		static async register(options = {}) {

			const publicKey = await this.beginRegistration({
				url: options.optionsUrl || "/api/passkey/register/options",
				displayName: options.displayName ?? null,
				requestOptions: options.requestOptions || {}
			});

			const credential = await this.createCredential(publicKey);

			return this.finishRegistration({
				url: options.verifyUrl || "/api/passkey/register/verify",
				credential,
				label: options.label ?? null,
				requestOptions: options.requestOptions || {}
			});

		}

		/**
		 * Parse JSON response and throw descriptive Error for non-2xx.
		 * @param {Response} response
		 * @returns {Promise<Object|Array|null>}
		 */
		static async readJsonResponse(response) {

			let payload = null;

			try {
				payload = await response.json();
			} catch (_error) {
				payload = null;
			}

			if (!response.ok) {
				const error = new Error(
					(payload && (payload.error || payload.message)) ? (payload.error || payload.message) : "Passkey request failed."
				);
				error.status = response.status;
				error.payload = payload;
				throw error;
			}

			return payload;

		}

		/**
		 * Revoke a passkey by ID.
		 * @param {{id: number|string, urlBase?: string, requestOptions?: RequestInit}} options
		 * @returns {Promise<Object|Array|null>}
		 */
		static async revoke(options = {}) {

			const id = parseInt(String(options.id || ""), 10);

			if (!Number.isFinite(id) || id < 1) {
				throw new Error("Invalid passkey ID.");
			}

			const urlBase = options.urlBase || "/api/passkey/revoke";
			const url = `${urlBase}/${id}`;

			return this.requestJson(url, {
				method: "DELETE",
				requestOptions: options.requestOptions || {}
			});

		}

		/**
		 * Serialize a WebAuthn credential for backend transport.
		 * @param {PublicKeyCredential} credential
		 * @returns {Object}
		 */
		static serializeCredential(credential) {

			if (!credential || typeof credential !== "object") {
				throw new Error("Credential is missing.");
			}

			const response = credential.response || {};
			const serialized = {
				id: credential.id,
				rawId: this.toBase64Url(credential.rawId),
				type: credential.type,
				authenticatorAttachment: credential.authenticatorAttachment || null,
				clientExtensionResults: typeof credential.getClientExtensionResults === "function"
					? credential.getClientExtensionResults()
					: {},
				response: {}
			};

			if (response.clientDataJSON) {
				serialized.response.clientDataJSON = this.toBase64Url(response.clientDataJSON);
			}

			if (response.attestationObject) {
				serialized.response.attestationObject = this.toBase64Url(response.attestationObject);
			}

			if (response.authenticatorData) {
				serialized.response.authenticatorData = this.toBase64Url(response.authenticatorData);
			}

			if (response.signature) {
				serialized.response.signature = this.toBase64Url(response.signature);
			}

			if (response.userHandle) {
				serialized.response.userHandle = this.toBase64Url(response.userHandle);
			}

			if (typeof response.getPublicKey === "function") {
				const publicKeyBuffer = response.getPublicKey();
				if (publicKeyBuffer) {
					serialized.response.publicKey = this.toBase64Url(publicKeyBuffer);
				}
			}

			if (typeof response.getPublicKeyAlgorithm === "function") {
				serialized.response.publicKeyAlgorithm = response.getPublicKeyAlgorithm();
			}

			if (typeof response.getAuthenticatorData === "function") {
				const authData = response.getAuthenticatorData();
				if (authData) {
					serialized.response.authenticatorData = this.toBase64Url(authData);
				}
			}

			if (typeof response.getTransports === "function") {
				serialized.response.transports = response.getTransports();
			}

			return serialized;

		}

		/**
		 * Convert ArrayBuffer-like data to base64url string.
		 * @param {ArrayBuffer|Uint8Array|DataView} value
		 * @returns {string}
		 */
		static toBase64Url(value) {

			if (!value) {
				return "";
			}

			let bytes;

			if (value instanceof ArrayBuffer) {
				bytes = new Uint8Array(value);
			} else if (ArrayBuffer.isView(value)) {
				bytes = new Uint8Array(value.buffer, value.byteOffset, value.byteLength);
			} else {
				throw new Error("Unsupported buffer type.");
			}

			let binary = "";

			for (let i = 0; i < bytes.length; i += 1) {
				binary += String.fromCharCode(bytes[i]);
			}

			return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");

		}

		/**
		 * Throws when browser does not support WebAuthn.
		 */
		static ensureSupported() {

			if (!this.isSupported()) {
				throw new Error("Passkey/WebAuthn is not supported in this browser or context.");
			}

		}

	}

	global.PairPasskey = PairPasskey;
})(window);
