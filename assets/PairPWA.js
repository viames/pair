(function (global) {
  "use strict";

  class PairPWA {
    static canInstall() {
      return !!this._installPromptEvent;
    }

    static async fetchWithQueue(url, options = {}, queueOptions = {}) {
      try {
        return await fetch(url, options);
      } catch (error) {
        const method = String(options.method || "GET").toUpperCase();
        const queueOnFail = queueOptions.queueOnFail !== false;

        if (!queueOnFail || method === "GET" || method === "HEAD") {
          throw error;
        }

        const queued = await this.queueRequest({
          url,
          method,
          headers: options.headers || {},
          body: options.body != null ? options.body : null,
          tag: queueOptions.tag || "pair-sync-default",
        });

        if (!queued) {
          throw error;
        }

        return new Response(JSON.stringify({ queued: true, offline: true }), {
          status: 202,
          headers: { "Content-Type": "application/json" },
        });
      }
    }

    static async flushSyncQueue(tag = "pair-sync-default") {
      return this._postMessage({
        type: "PAIR_SW_FLUSH_QUEUE",
        payload: { tag },
      });
    }

    static isSupported() {
      return "serviceWorker" in navigator;
    }

    static async init(config = {}) {
      const defaults = {
        swUrl: "/assets/PairSW.js",
        scope: "/",
        registerServiceWorker: true,
        checkOnline: true,
        emitEvents: true,
        watchInstallPrompt: true,
        reloadOnControllerChange: false,
        swOfflineFallback: null,
        serviceWorkerConfig: null,
        backgroundRefresh: null,
      };

      this._config = { ...defaults, ...config };

      if (this._config.checkOnline) {
        this._watchOnlineStatus();
      }

      if (this._config.watchInstallPrompt) {
        this._watchInstallPrompt();
      }

      let registration = null;

      if (this._config.registerServiceWorker && this.isSupported()) {
        const swUrl = this._buildServiceWorkerUrl(
          this._config.swUrl,
          this._config.swOfflineFallback,
          this._config.serviceWorkerConfig
        );

        registration = await this.registerServiceWorker(swUrl, this._config.scope);
        this._watchServiceWorkerUpdates(registration);
      } else if (this.isSupported()) {
        registration = await navigator.serviceWorker.getRegistration();
        if (registration) {
          this._watchServiceWorkerUpdates(registration);
        }
      }

      if (this._config.backgroundRefresh && typeof this._config.backgroundRefresh === "object") {
        this.startBackgroundRefresh(this._config.backgroundRefresh);
      }

      this._emit("pair:pwa:ready", {
        registration,
        online: navigator.onLine,
        installAvailable: this.canInstall(),
      });

      return registration;
    }

    static async preload(urls = []) {
      if (!Array.isArray(urls) || !urls.length) return false;

      return this._postMessage({
        type: "PAIR_SW_PRELOAD",
        payload: { urls },
      });
    }

    static async promptInstall() {
      if (!this._installPromptEvent) {
        return { outcome: "unavailable", platform: null };
      }

      const installEvent = this._installPromptEvent;
      installEvent.prompt();
      const choice = await installEvent.userChoice;
      this._installPromptEvent = null;

      this._emit("pair:pwa:install-prompt-result", { choice });

      return {
        outcome: choice && choice.outcome ? choice.outcome : "unknown",
        platform: choice && choice.platform ? choice.platform : null,
      };
    }

    static async queueRequest({
      url,
      method = "POST",
      headers = {},
      body = null,
      tag = "pair-sync-default",
    } = {}) {
      if (!url) return false;

      const normalizedHeaders = this._serializeHeaders(headers);

      const payload = {
        url: String(url),
        method: String(method || "POST").toUpperCase(),
        headers: normalizedHeaders,
        body: this._normalizeBody(body),
        tag: String(tag || "pair-sync-default"),
      };

      const queued = await this._postMessage({
        type: "PAIR_SW_QUEUE_REQUEST",
        payload,
      });

      if (queued) {
        this._emit("pair:pwa:sync-queued", payload);
      }

      return queued;
    }

    static async registerServiceWorker(swUrl = "/assets/PairSW.js", scope = "/") {
      if (!this.isSupported()) {
        throw new Error("Service Worker is not supported in this browser.");
      }

      const registration = await navigator.serviceWorker.register(swUrl, { scope });
      await navigator.serviceWorker.ready;

      this._emit("pair:pwa:sw-registered", { registration });
      return registration;
    }

    static startBackgroundRefresh({
      url,
      intervalMs = 60000,
      options = {},
      onlyWhenVisible = true,
      onSuccess = null,
      onError = null,
    } = {}) {
      if (!url) {
        throw new Error("A refresh URL is required.");
      }

      this.stopBackgroundRefresh();

      const run = async () => {
        if (!navigator.onLine) return;
        if (onlyWhenVisible && document.visibilityState !== "visible") return;

        try {
          this._emit("pair:pwa:background-refresh-start", { url });

          const response = await fetch(url, {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            ...options,
          });

          this._emit("pair:pwa:background-refresh-success", { response, url });
          if (typeof onSuccess === "function") onSuccess(response);
        } catch (error) {
          this._emit("pair:pwa:background-refresh-error", { error, url });
          if (typeof onError === "function") onError(error);
        }
      };

      this._backgroundTimer = setInterval(run, intervalMs);
      run();

      return () => this.stopBackgroundRefresh();
    }

    static stopBackgroundRefresh() {
      if (this._backgroundTimer) {
        clearInterval(this._backgroundTimer);
        this._backgroundTimer = null;
      }
    }

    static async skipWaiting() {
      return this._postMessage({ type: "PAIR_SW_SKIP_WAITING" });
    }

    static async updateServiceWorker() {
      if (!this.isSupported()) return false;

      const registration = await navigator.serviceWorker.getRegistration();
      if (!registration) return false;

      await registration.update();
      return true;
    }

    static _buildServiceWorkerUrl(swUrl, offlineFallback = null, serviceWorkerConfig = null) {
      try {
        const url = new URL(swUrl, global.location.origin);

        if (offlineFallback) {
          url.searchParams.set("offline", String(offlineFallback));
        }

        if (serviceWorkerConfig && typeof serviceWorkerConfig === "object") {
          const encodedConfig = this._encodeConfig(serviceWorkerConfig);
          if (encodedConfig) {
            url.searchParams.set("pwa", encodedConfig);
          }
        }

        return `${url.pathname}${url.search}`;
      } catch (_error) {
        return swUrl;
      }
    }

    static _emit(name, detail) {
      if (this._config && this._config.emitEvents === false) return;
      window.dispatchEvent(new CustomEvent(name, { detail }));
    }

    static _encodeConfig(value) {
      try {
        const json = JSON.stringify(value);

        const utf8 = encodeURIComponent(json).replace(/%([0-9A-F]{2})/g, (_match, p1) => {
          return String.fromCharCode(parseInt(p1, 16));
        });

        return btoa(utf8)
          .replace(/\+/g, "-")
          .replace(/\//g, "_")
          .replace(/=+$/g, "");
      } catch (_error) {
        return null;
      }
    }

    static _normalizeBody(body) {
      if (body == null) return null;

      if (typeof body === "string") {
        return body;
      }

      if (typeof URLSearchParams !== "undefined" && body instanceof URLSearchParams) {
        return body.toString();
      }

      if (typeof FormData !== "undefined" && body instanceof FormData) {
        const formData = {};
        body.forEach((value, key) => {
          formData[key] = typeof value === "string" ? value : String(value);
        });
        return formData;
      }

      if (typeof body === "object") {
        return body;
      }

      return String(body);
    }

    static async _postMessage(message) {
      if (!this.isSupported()) return false;

      const registration = await navigator.serviceWorker.getRegistration();
      if (!registration) return false;

      const target =
        navigator.serviceWorker.controller ||
        registration.active ||
        registration.waiting ||
        registration.installing;

      if (!target) {
        return false;
      }

      target.postMessage(message);
      return true;
    }

    static _serializeHeaders(headers) {
      if (!headers) return {};

      if (typeof Headers !== "undefined" && headers instanceof Headers) {
        const obj = {};
        headers.forEach((value, key) => {
          obj[key] = value;
        });
        return obj;
      }

      if (Array.isArray(headers)) {
        const obj = {};
        for (const pair of headers) {
          if (!Array.isArray(pair) || pair.length < 2) continue;
          obj[String(pair[0])] = String(pair[1]);
        }
        return obj;
      }

      if (typeof headers === "object") {
        const obj = {};
        for (const key of Object.keys(headers)) {
          obj[key] = String(headers[key]);
        }
        return obj;
      }

      return {};
    }

    static _watchInstallPrompt() {
      if (this._watchInstallBound) return;
      this._watchInstallBound = true;

      window.addEventListener("beforeinstallprompt", (event) => {
        event.preventDefault();
        this._installPromptEvent = event;
        this._emit("pair:pwa:install-available", {});
      });

      window.addEventListener("appinstalled", () => {
        this._installPromptEvent = null;
        this._emit("pair:pwa:installed", {});
      });
    }

    static _watchOnlineStatus() {
      if (this._watchOnlineBound) return;
      this._watchOnlineBound = true;

      window.addEventListener("online", () => this._emit("pair:pwa:online", { online: true }));
      window.addEventListener("offline", () => this._emit("pair:pwa:offline", { online: false }));
    }

    static _watchServiceWorkerUpdates(registration) {
      if (!registration) return;
      if (this._watchServiceWorkerBound) return;
      this._watchServiceWorkerBound = true;

      registration.addEventListener("updatefound", () => {
        const worker = registration.installing;
        if (!worker) return;

        worker.addEventListener("statechange", () => {
          if (worker.state === "installed") {
            const hasWaiting = !!registration.waiting;
            this._emit("pair:pwa:update-ready", { registration, hasWaiting });
          }
        });
      });

      navigator.serviceWorker.addEventListener("controllerchange", () => {
        this._emit("pair:pwa:controller-changed", {});

        if (this._config && this._config.reloadOnControllerChange) {
          location.reload();
        }
      });

      navigator.serviceWorker.addEventListener("message", (event) => {
        const data = event.data || {};
        if (!data.type) return;
        this._emit("pair:pwa:sw-message", data);
      });
    }
  }

  PairPWA.version = "0.3.0";
  PairPWA._backgroundTimer = null;
  PairPWA._config = null;
  PairPWA._installPromptEvent = null;
  PairPWA._watchInstallBound = false;
  PairPWA._watchOnlineBound = false;
  PairPWA._watchServiceWorkerBound = false;

  global.Pair = global.Pair || {};
  global.Pair.PWA = PairPWA;
  global.PairPWA = PairPWA;
})(window);
