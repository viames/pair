class PairPush {

	/**
	 * Check if Push Notifications are supported.
	 * @returns {boolean}
	 */
	static isSupported() {
		return 'serviceWorker' in navigator && 'PushManager' in window;
	}

	/**
	 * Register the service worker.
	 * @param {string} swUrl
	 * @returns {Promise<ServiceWorkerRegistration>}
	 */
	static async registerServiceWorker(swUrl = '/sw.js') {
		if (!this.isSupported()) {
			throw new Error(this.message('WEB_PUSH_UNSUPPORTED', 'The Web Push API is not supported by this browser.'));
		}
		return navigator.serviceWorker.register(swUrl);
	}

	/**
	 * Return a translated client message from server-injected PairMessages.
	 * @param {string} key
	 * @param {string} fallback
	 * @returns {string}
	 */
	static message(key, fallback) {
		const messages = window.PairMessages || {};
		const message = messages && typeof messages[key] === 'string' ? messages[key].trim() : '';

		return message || fallback;
	}

	/**
	 * Get the current notification permission.
	 * @returns {Promise<string>}
	 */
	static async getPermission() {
		return Notification.permission;
	}

	/**
	 * Request notification permission from the user.
	 * @returns {Promise<string>}
	 */
	static async requestPermission() {
		return Notification.requestPermission();
	}

	/**
	 * Subscribe to push notifications.
	 * @param {*} param0 
	 * @returns {Promise<PushSubscription>}
	 */
	static async subscribe({ vapidPublicKey, subscribeUrl = '/push/subscribe', swUrl = '/sw.js' }) {
		if (!this.isSupported()) {
			throw new Error(this.message('WEB_PUSH_UNSUPPORTED', 'The Web Push API is not supported by this browser.'));
		}

		if (!vapidPublicKey) {
			throw new Error(this.message('VAPID_PUBLIC_KEY_REQUIRED', 'VAPID public key is required.'));
		}

		const registration = await this.registerServiceWorker(swUrl);
		const applicationServerKey = this.#urlBase64ToUint8Array(vapidPublicKey);

		const subscription = await registration.pushManager.subscribe({
			userVisibleOnly: true,
			applicationServerKey,
		});

		await fetch(subscribeUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ subscription }),
		});

		return subscription;
	}

	/**
	 * Unsubscribe from push notifications.
	 * @param {string} unsubscribeUrl
	 * @returns {Promise<boolean>}
	 */
	static async unsubscribe({ unsubscribeUrl = '/push/unsubscribe', swUrl = '/sw.js' } = {}) {
		if (!this.isSupported()) {
			return false;
		}

		const registration = await this.registerServiceWorker(swUrl);
		const subscription = await registration.pushManager.getSubscription();

		if (!subscription) {
			return false;
		}

		await fetch(unsubscribeUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ endpoint: subscription.endpoint }),
		});

		return subscription.unsubscribe();
	}

	/**
	 * Convert a URL-safe base64 string to a Uint8Array.
	 * @param {string} base64String 
	 * @returns {Uint8Array}
	 */
	static #urlBase64ToUint8Array(base64String) {
		const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
		const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		const rawData = atob(base64);
		const outputArray = new Uint8Array(rawData.length);

		for (let i = 0; i < rawData.length; i += 1) {
			outputArray[i] = rawData.charCodeAt(i);
		}

		return outputArray;
	}
}

window.PairPush = PairPush;
