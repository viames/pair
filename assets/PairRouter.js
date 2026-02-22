(function (global) {
  "use strict";

  class PairRouter {
    static start(config = {}) {
      if (this._started) return;

      const defaults = {
        viewSelector: "[data-pair-router-view]",
        linkSelector: "a[href]",
        activeClass: "is-loading",
        preloadOnHover: true,
        timeoutMs: 15000,
      };

      this._config = { ...defaults, ...config };

      document.addEventListener("click", (event) => this._onClick(event));
      document.addEventListener("mouseover", (event) => this._onHover(event));
      window.addEventListener("popstate", () => this.navigate(location.href, { pushState: false }));

      this._started = true;
    }

    static async navigate(url, { pushState = true } = {}) {
      if (!this._config) {
        this.start();
      }

      const view = document.querySelector(this._config.viewSelector);
      if (!view) {
        location.href = url;
        return false;
      }

      window.dispatchEvent(new CustomEvent("pair:router:loading", { detail: { url } }));
      view.classList.add(this._config.activeClass);

      try {
        const html = await this._fetchHtml(url);
        const parsed = new DOMParser().parseFromString(html, "text/html");
        const incomingView = parsed.querySelector(this._config.viewSelector);

        if (!incomingView) {
          location.href = url;
          return false;
        }

        view.innerHTML = incomingView.innerHTML;

        if (parsed.title) {
          document.title = parsed.title;
        }

        if (pushState) {
          history.pushState({}, "", url);
        }

        window.dispatchEvent(new CustomEvent("pair:router:navigated", { detail: { url } }));
        return true;
      } catch (error) {
        window.dispatchEvent(new CustomEvent("pair:router:error", { detail: { url, error } }));
        location.href = url;
        return false;
      } finally {
        view.classList.remove(this._config.activeClass);
      }
    }

    static _isInternalLink(anchor) {
      if (!anchor || !anchor.href) return false;
      if (anchor.target && anchor.target !== "_self") return false;
      if (anchor.hasAttribute("download")) return false;
      if (anchor.hasAttribute("data-router-ignore")) return false;
      if (anchor.getAttribute("rel") === "external") return false;

      const url = new URL(anchor.href, location.origin);
      return url.origin === location.origin;
    }

    static async _fetchHtml(url) {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), this._config.timeoutMs);

      try {
        const response = await fetch(url, {
          headers: { "X-Requested-With": "PairRouter" },
          credentials: "same-origin",
          signal: controller.signal,
        });

        if (!response.ok) {
          throw new Error(`Request failed: ${response.status}`);
        }

        return response.text();
      } finally {
        clearTimeout(timeout);
      }
    }

    static _onClick(event) {
      if (!this._config) return;

      if (event.defaultPrevented || event.button !== 0) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      if (!event.target || typeof event.target.closest !== "function") return;

      const anchor = event.target.closest(this._config.linkSelector);
      if (!this._isInternalLink(anchor)) return;

      const url = new URL(anchor.href, location.origin);
      if (url.href === location.href) return;

      event.preventDefault();
      this.navigate(url.href);
    }

    static _onHover(event) {
      if (!this._config || !this._config.preloadOnHover) return;
      if (!event.target || typeof event.target.closest !== "function") return;

      const anchor = event.target.closest(this._config.linkSelector);
      if (!this._isInternalLink(anchor)) return;

      const url = new URL(anchor.href, location.origin);
      if (url.href === location.href) return;
      if (this._preloadedUrls.has(url.href)) return;

      this._preloadedUrls.add(url.href);

      if (global.PairPWA && typeof global.PairPWA.preload === "function") {
        global.PairPWA.preload([url.pathname + url.search]);
      }
    }
  }

  PairRouter.version = "0.2.0";
  PairRouter._config = null;
  PairRouter._preloadedUrls = new Set();
  PairRouter._started = false;

  global.Pair = global.Pair || {};
  global.Pair.Router = PairRouter;
  global.PairRouter = PairRouter;
})(window);
