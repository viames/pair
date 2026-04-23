/*!
 * PairUI - Lightweight client helpers for server-rendered apps (Pair Framework)
 *
 * Goals:
 * - No build step
 * - Progressive enhancement
 * - Declarative directives via data-*
 * - Simple reactive store + DOM bindings
 * - Safe parsing (no eval)
 *
 * This is NOT a virtual-DOM framework.
 */

(function (global) {
  "use strict";

  const PairUI = {};
  PairUI.version = "0.4.1";

  // expose under both window.PairUI and window.Pair.UI
  global.Pair = global.Pair || {};
  global.Pair.UI = PairUI;
  global.PairUI = PairUI;

  // ---------------------------------------------------------------------------
  // Utilities
  // ---------------------------------------------------------------------------

  PairUI.ready = (fn) => {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  };

  // DOM selection
  PairUI.qs = (sel, root = document) => root.querySelector(sel);
  PairUI.qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // nearest form root from an element
  PairUI.formRoot = (el, fallback = document) => {
    const safeFallback = fallback || document;
    if (!el || typeof el.closest !== "function") return safeFallback;
    return el.closest("form") || safeFallback;
  };

  // contextual helpers to avoid repeating root + selector boilerplate
  PairUI.ctx = (el, fallback = document) => {
    const root = PairUI.formRoot(el, fallback);
    return {
      root,
      qs: (sel, scope = root) => PairUI.qs(sel, scope || root),
      qsa: (sel, scope = root) => PairUI.qsa(sel, scope || root)
    };
  };

  // event helpers
  PairUI.on = (el, type, handler, opts) => el.addEventListener(type, handler, opts);
  PairUI.off = (el, type, handler, opts) => el.removeEventListener(type, handler, opts);

  // event delegation: PairUI.delegate(document, "click", "[data-x]", (e, el) => {})
  PairUI.delegate = (root, type, selector, handler, opts) => {
    const listener = (e) => {
      const target = e.target && e.target.closest ? e.target.closest(selector) : null;
      if (!target || !root.contains(target)) return;
      handler(e, target);
    };
    root.addEventListener(type, listener, opts);
    return () => root.removeEventListener(type, listener, opts);
  };

  // custom event emitter, returns the event object
  PairUI.emit = (el, name, detail = {}, opts = {}) => {
    const ev = new CustomEvent(name, { detail, bubbles: true, cancelable: true, ...opts });
    el.dispatchEvent(ev);
    return ev;
  };

  // debounce function calls, delaying execution until wait ms have passed since last call
  PairUI.debounce = (fn, wait = 150) => {
    let t = null;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  };

  // throttle function calls, ensuring at most one call per wait ms
  PairUI.throttle = (fn, wait = 150) => {
    let last = 0;
    let t = null;
    return function (...args) {
      const now = Date.now();
      const remaining = wait - (now - last);
      if (remaining <= 0) {
        last = now;
        fn.apply(this, args);
      } else if (!t) {
        t = setTimeout(() => {
          t = null;
          last = Date.now();
          fn.apply(this, args);
        }, remaining);
      }
    };
  };

  // simple HTML escape
  PairUI.escapeHtml = (s) => String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

  // set/unset class
  PairUI.setClass = (el, className, enabled) => {
    if (!className) return;
    el.classList.toggle(className, !!enabled);
  };

  const RAW_HTML_TOKEN = Symbol("PairUI.rawHtml");
  const loadingStateCache = new WeakMap();
  const persistConfig = {
    cookiePrefix: "",
    days: 30,
    path: "/",
    sameSite: "Lax",
    secure: null,
  };

  /**
   * Returns true when the value is a plain object.
   * @param {*} value
   * @returns {boolean}
   */
  function isPlainObject(value) {
    return Object.prototype.toString.call(value) === "[object Object]";
  }

  /**
   * Resolve a selector, element or collection into an array of elements.
   * @param {*} input
   * @param {*} root
   * @returns {Array}
   */
  function resolveElements(input, root = document) {
    if (!input) return [];
    if (typeof input === "string") return PairUI.qsa(input, root);
    if (Array.isArray(input)) return input.filter(Boolean);
    if (typeof input.length === "number" && typeof input !== "string") return Array.from(input).filter(Boolean);
    return [input];
  }

  /**
   * Append a key/value pair to URLSearchParams or FormData, supporting arrays.
   * @param {*} target
   * @param {*} key
   * @param {*} value
   * @returns {void}
   */
  function appendKeyValue(target, key, value) {
    if (value === undefined) return;

    if (Array.isArray(value)) {
      for (const item of value) appendKeyValue(target, key, item);
      return;
    }

    if (value == null) {
      target.append(key, "");
      return;
    }

    if (typeof Date !== "undefined" && value instanceof Date) {
      target.append(key, value.toISOString());
      return;
    }

    if (
      typeof FormData !== "undefined"
      && target instanceof FormData
      && typeof Blob !== "undefined"
      && value instanceof Blob
    ) {
      target.append(key, value);
      return;
    }

    if (isPlainObject(value)) {
      target.append(key, JSON.stringify(value));
      return;
    }

    target.append(key, String(value));
  }

  /**
   * Returns the UTF-8 byte length of a string for PHP-like serialization.
   * @param {*} value
   * @returns {number}
   */
  function getUtf8ByteLength(value) {
    const stringValue = String(value ?? "");
    if (typeof TextEncoder === "function") {
      return new TextEncoder().encode(stringValue).length;
    }

    return unescape(encodeURIComponent(stringValue)).length;
  }

  /**
   * Extract a readable error message from Error objects or HTTP payloads.
   * @param {*} error
   * @param {*} fallback
   * @returns {string}
   */
  function getErrorMessage(error, fallback = getClientMessage("UNEXPECTED_ERROR", "Unexpected error")) {
    if (error && typeof error === "object") {
      if (error.payload && typeof error.payload === "object") {
        if (typeof error.payload.message === "string" && error.payload.message.trim()) {
          return error.payload.message.trim();
        }

        if (typeof error.payload.error === "string" && error.payload.error.trim()) {
          return error.payload.error.trim();
        }
      }

      if (typeof error.payload === "string" && error.payload.trim()) {
        return error.payload.trim();
      }

      if (typeof error.message === "string" && error.message.trim()) {
        return error.message.trim();
      }
    }

    if (typeof error === "string" && error.trim()) {
      return error.trim();
    }

    return String(fallback ?? getClientMessage("UNEXPECTED_ERROR", "Unexpected error"));
  }

  /**
   * Return a translated client message from server-injected PairMessages.
   * @param {string} key
   * @param {string} fallback
   * @returns {string}
   */
  function getClientMessage(key, fallback) {
    const messages = global.PairMessages || {};
    const message = messages && typeof messages[key] === "string" ? messages[key].trim() : "";

    return message || fallback;
  }

  /**
   * Read and decode a cookie value by name.
   * @param {string} cookieName
   * @returns {?string}
   */
  function readCookieValue(cookieName) {
    const cookies = document.cookie ? document.cookie.split("; ") : [];
    const prefix = encodeURIComponent(cookieName) + "=";

    for (const cookie of cookies) {
      if (cookie.startsWith(prefix)) {
        return decodeURIComponent(cookie.slice(prefix.length));
      }
    }

    return null;
  }

  /**
   * Returns the merged persistent-state configuration.
   * @param {*} options
   * @returns {Object}
   */
  function getPersistSettings(options = {}) {
    const globalConfig = isPlainObject(global.PairUIPersistConfig) ? global.PairUIPersistConfig : {};
    return { ...persistConfig, ...globalConfig, ...(options || {}) };
  }

  const VALID_TOAST_TYPES = ["info", "success", "warning", "error", "question", "progress"];
  const toastDefaults = {
    close: true,
    closeOnEscape: true,
    driver: "izitoast",
    progressBar: true,
    timeout: 5000,
  };

  /**
   * Returns the normalized toast driver identifier.
   * @param {*} driver
   * @returns {string}
   */
  function normalizeToastDriver(driver) {
    const normalized = String(driver ?? "").trim().toLowerCase();

    if (["sweetalert", "sweetalert2", "swal"].includes(normalized)) {
      return "sweetalert";
    }

    return "izitoast";
  }

  /**
   * Returns the normalized toast type.
   * @param {*} type
   * @returns {string}
   */
  function normalizeToastType(type) {
    const normalized = String(type ?? "").trim().toLowerCase();
    return VALID_TOAST_TYPES.includes(normalized) ? normalized : "info";
  }

  /**
   * Returns the merged toast configuration exposed by the server.
   * @returns {Object}
   */
  function getToastSettings() {
    const globalConfig = isPlainObject(global.PairToastConfig) ? global.PairToastConfig : {};
    return {
      ...toastDefaults,
      ...globalConfig,
      driver: normalizeToastDriver(globalConfig.driver ?? toastDefaults.driver),
    };
  }

  /**
   * Maps all supported toast position aliases to a canonical Pair value.
   * @param {*} position
   * @returns {string}
   */
  function getCanonicalToastPosition(position) {
    const normalized = String(position ?? "")
      .trim()
      .toLowerCase()
      .replaceAll("_", "-")
      .replaceAll(" ", "-");

    switch (normalized) {
      case "top-left":
      case "topleft":
      case "top-start":
      case "topstart":
        return "topLeft";
      case "top-center":
      case "topcenter":
      case "top":
        return "topCenter";
      case "bottom-left":
      case "bottomleft":
      case "bottom-start":
      case "bottomstart":
        return "bottomLeft";
      case "bottom-center":
      case "bottomcenter":
      case "bottom":
        return "bottomCenter";
      case "center":
        return "center";
      default:
        return "topRight";
    }
  }

  /**
   * Returns the driver-specific position token for the provided toast position.
   * @param {*} position
   * @param {string} driver
   * @returns {string}
   */
  function normalizeToastPosition(position, driver) {
    const canonical = getCanonicalToastPosition(position);

    if (normalizeToastDriver(driver) === "sweetalert") {
      switch (canonical) {
        case "topLeft":
          return "top-start";
        case "topCenter":
          return "top";
        case "bottomLeft":
          return "bottom-start";
        case "bottomCenter":
          return "bottom";
        case "center":
          return "center";
        default:
          return "top-end";
      }
    }

    switch (canonical) {
      case "topLeft":
        return "topLeft";
      case "topCenter":
        return "topCenter";
      case "bottomLeft":
        return "bottomLeft";
      case "bottomCenter":
        return "bottomCenter";
      case "center":
        return "center";
      default:
        return "topRight";
    }
  }

  /**
   * Returns the normalized timeout value.
   * @param {*} timeout
   * @param {*} fallback
   * @returns {number|boolean}
   */
  function normalizeToastTimeout(timeout, fallback) {
    if (timeout === false) {
      return false;
    }

    const parsed = Number(timeout);
    return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
  }

  /**
   * Removes keys with undefined values from a plain object.
   * @param {Object} payload
   * @returns {Object}
   */
  function compactObject(payload) {
    const out = {};

    Object.keys(payload || {}).forEach((key) => {
      if (payload[key] !== undefined) {
        out[key] = payload[key];
      }
    });

    return out;
  }

  /**
   * Normalizes toast options shared by both supported client drivers.
   * @param {*} options
   * @returns {Object}
   */
  function normalizeToastOptions(options = {}) {
    const settings = getToastSettings();
    const rawOptions = isPlainObject(options) ? options : {};
    const rawIcon = typeof rawOptions.icon === "string" ? rawOptions.icon.trim() : "";
    const standardIcon = VALID_TOAST_TYPES.includes(rawIcon.toLowerCase()) ? rawIcon.toLowerCase() : "";

    return {
      balloon: typeof rawOptions.balloon === "boolean" ? rawOptions.balloon : undefined,
      className: typeof rawOptions.class === "string" && rawOptions.class.trim()
        ? rawOptions.class.trim()
        : (typeof rawOptions.className === "string" && rawOptions.className.trim() ? rawOptions.className.trim() : undefined),
      close: typeof rawOptions.close === "boolean"
        ? rawOptions.close
        : (typeof rawOptions.showCloseButton === "boolean" ? rawOptions.showCloseButton : !!settings.close),
      closeOnEscape: typeof rawOptions.closeOnEscape === "boolean"
        ? rawOptions.closeOnEscape
        : !!settings.closeOnEscape,
      customIcon: rawIcon && !standardIcon ? rawIcon : "",
      displayMode: Number.isFinite(Number(rawOptions.displayMode)) ? Number(rawOptions.displayMode) : undefined,
      driver: normalizeToastDriver(rawOptions.driver ?? settings.driver),
      id: typeof rawOptions.id === "string" && rawOptions.id.trim() ? rawOptions.id.trim() : undefined,
      image: typeof rawOptions.image === "string" && rawOptions.image.trim()
        ? rawOptions.image.trim()
        : (typeof rawOptions.imageUrl === "string" && rawOptions.imageUrl.trim() ? rawOptions.imageUrl.trim() : undefined),
      layout: Number.isFinite(Number(rawOptions.layout)) ? Number(rawOptions.layout) : undefined,
      maxWidth: rawOptions.maxWidth ?? rawOptions.width,
      message: String(rawOptions.message ?? rawOptions.text ?? ""),
      overlay: typeof rawOptions.overlay === "boolean" ? rawOptions.overlay : undefined,
      position: typeof rawOptions.position === "string" && rawOptions.position.trim()
        ? rawOptions.position.trim()
        : (typeof settings.position === "string" && settings.position.trim() ? settings.position.trim() : undefined),
      progressBar: typeof rawOptions.progressBar === "boolean"
        ? rawOptions.progressBar
        : (typeof rawOptions.timerProgressBar === "boolean" ? rawOptions.timerProgressBar : !!settings.progressBar),
      theme: typeof rawOptions.theme === "string" && rawOptions.theme.trim() ? rawOptions.theme.trim() : undefined,
      timeout: normalizeToastTimeout(rawOptions.timeout ?? rawOptions.timer, settings.timeout),
      title: String(rawOptions.title ?? ""),
      type: normalizeToastType(rawOptions.type ?? standardIcon),
    };
  }

  /**
   * Shows a toast through iziToast when the library is available.
   * @param {Object} options
   * @returns {boolean}
   */
  function showIziToast(options) {
    if (!global.iziToast || typeof global.iziToast.show !== "function") {
      return false;
    }

    const method = typeof global.iziToast[options.type] === "function"
      ? global.iziToast[options.type].bind(global.iziToast)
      : global.iziToast.show.bind(global.iziToast);

    method(compactObject({
      balloon: options.balloon,
      class: options.className,
      close: options.close,
      closeOnEscape: options.closeOnEscape,
      displayMode: options.displayMode,
      icon: options.customIcon || undefined,
      id: options.id,
      image: options.image,
      layout: options.layout,
      maxWidth: options.maxWidth,
      message: options.message,
      overlay: options.overlay,
      position: typeof options.position === "string" && options.position.trim()
        ? normalizeToastPosition(options.position, "izitoast")
        : undefined,
      progressBar: options.progressBar,
      theme: options.theme,
      timeout: options.timeout,
      title: options.title,
    }));

    return true;
  }

  /**
   * Shows a toast through SweetAlert2 when the library is available.
   * @param {Object} options
   * @returns {boolean}
   */
  function showSweetAlertToast(options) {
    if (!global.Swal || typeof global.Swal.fire !== "function") {
      return false;
    }

    const payload = compactObject({
      allowEscapeKey: options.closeOnEscape,
      customClass: options.className ? { popup: options.className } : undefined,
      icon: options.customIcon ? undefined : (options.type === "progress" ? "info" : options.type),
      iconHtml: options.customIcon ? `<i class="${PairUI.escapeHtml(options.customIcon)}"></i>` : undefined,
      imageUrl: options.image,
      position: typeof options.position === "string" && options.position.trim()
        ? normalizeToastPosition(options.position, "sweetalert")
        : undefined,
      showCloseButton: options.close,
      showConfirmButton: false,
      text: options.message,
      timer: options.timeout === false ? undefined : options.timeout,
      timerProgressBar: options.progressBar,
      title: options.title,
      toast: true,
      width: options.maxWidth,
    });

    if (options.id) {
      payload.didOpen = (toastNode) => {
        if (toastNode) {
          toastNode.id = options.id;
        }
      };
    }

    global.Swal.fire(payload);
    return true;
  }

  /**
   * Renders a toast notification using the configured driver, with a graceful fallback.
   * @param {*} options
   * @returns {boolean}
   */
  function renderToast(options) {
    const normalized = normalizeToastOptions(options);
    const preferredDriver = normalized.driver;
    const fallbackDriver = preferredDriver === "sweetalert" ? "izitoast" : "sweetalert";
    const renderers = {
      izitoast: showIziToast,
      sweetalert: showSweetAlertToast,
    };

    if (renderers[preferredDriver](normalized)) {
      return true;
    }

    if (renderers[fallbackDriver](normalized)) {
      return true;
    }

    console.error("No supported toast library is available for PairUI.toast().");
    return false;
  }

  /**
   * Builds driver-agnostic toast options from the shorthand helper signatures.
   * @param {string} type
   * @param {*} titleOrOptions
   * @param {*} message
   * @param {*} options
   * @returns {Object}
   */
  function buildToastHelperPayload(type, titleOrOptions, message = "", options = {}) {
    if (isPlainObject(titleOrOptions)) {
      return { type, ...titleOrOptions };
    }

    return {
      ...(isPlainObject(options) ? options : {}),
      message,
      title: titleOrOptions,
      type,
    };
  }

  /**
   * Driver-aware toast helpers shared across the application.
   */
  PairUI.toast = {
    configure(options = {}) {
      global.PairToastConfig = {
        ...getToastSettings(),
        ...(isPlainObject(options) ? options : {}),
      };
      global.PairToastConfig.driver = normalizeToastDriver(global.PairToastConfig.driver);
      return this;
    },

    error(titleOrOptions, message = "", options = {}) {
      return renderToast(buildToastHelperPayload("error", titleOrOptions, message, options));
    },

    getConfig() {
      return { ...getToastSettings() };
    },

    getDriver() {
      return getToastSettings().driver;
    },

    info(titleOrOptions, message = "", options = {}) {
      return renderToast(buildToastHelperPayload("info", titleOrOptions, message, options));
    },

    question(titleOrOptions, message = "", options = {}) {
      return renderToast(buildToastHelperPayload("question", titleOrOptions, message, options));
    },

    show(options = {}) {
      return renderToast(options);
    },

    success(titleOrOptions, message = "", options = {}) {
      return renderToast(buildToastHelperPayload("success", titleOrOptions, message, options));
    },

    warning(titleOrOptions, message = "", options = {}) {
      return renderToast(buildToastHelperPayload("warning", titleOrOptions, message, options));
    },
  };

  /**
   * Mark an interpolation as already-safe HTML for PairUI.html templates.
   * @param {*} value
   * @returns {Object}
   */
  PairUI.raw = (value) => ({
    [RAW_HTML_TOKEN]: true,
    value: String(value ?? ""),
  });

  /**
   * Build escaped HTML fragments while allowing explicit raw sections.
   * @param {TemplateStringsArray} strings
   * @param {...*} values
   * @returns {string}
   */
  PairUI.html = (strings, ...values) => {
    const renderValue = (value) => {
      if (Array.isArray(value)) {
        return value.map(renderValue).join("");
      }

      if (value && value[RAW_HTML_TOKEN]) {
        return String(value.value ?? "");
      }

      return PairUI.escapeHtml(value ?? "");
    };

    let out = "";

    for (let i = 0; i < strings.length; i++) {
      out += strings[i];
      if (i < values.length) out += renderValue(values[i]);
    }

    return out;
  };

  /**
   * Submit the nearest form using requestSubmit when available.
   * @param {*} elOrForm
   * @returns {boolean}
   */
  PairUI.submit = (elOrForm) => {
    const form = elOrForm instanceof HTMLFormElement ? elOrForm : PairUI.formRoot(elOrForm, null);
    if (!(form instanceof HTMLFormElement)) return false;

    if (typeof form.requestSubmit === "function") {
      form.requestSubmit();
    } else {
      form.submit();
    }

    return true;
  };

  /**
   * Run async work while toggling busy state on a target element.
   * @param {*} target
   * @param {*} task
   * @param {*} options
   * @returns {Promise<*>}
   */
  PairUI.withLoading = async (target, task, options = {}) => {
    const runner = typeof task === "function" ? task : () => task;
    if (!target) return Promise.resolve().then(() => runner(null));

    const settings = {
      className: "is-loading",
      disable: true,
      ariaBusy: true,
      iconSelector: null,
      loadingIconClass: null,
      textSelector: null,
      text: null,
      ...options,
    };

    const icon = settings.iconSelector ? PairUI.qs(settings.iconSelector, target) : null;
    const label = settings.textSelector ? PairUI.qs(settings.textSelector, target) : null;

    loadingStateCache.set(target, {
      disabled: "disabled" in target ? !!target.disabled : null,
      ariaBusy: typeof target.getAttribute === "function" ? target.getAttribute("aria-busy") : null,
      iconClassName: icon ? icon.className : null,
      labelText: label ? label.textContent : null,
    });

    if (settings.disable && "disabled" in target) {
      target.disabled = true;
    }

    if (settings.ariaBusy && typeof target.setAttribute === "function") {
      target.setAttribute("aria-busy", "true");
    }

    if (settings.className && target.classList) {
      target.classList.add(settings.className);
    }

    if (label && settings.text != null) {
      label.textContent = String(settings.text);
    }

    if (icon && settings.loadingIconClass) {
      icon.className = settings.loadingIconClass;
    }

    try {
      return await Promise.resolve().then(() => runner(target));
    } finally {
      const snapshot = loadingStateCache.get(target);

      if (settings.disable && snapshot && snapshot.disabled != null && "disabled" in target) {
        target.disabled = snapshot.disabled;
      }

      if (settings.ariaBusy && typeof target.removeAttribute === "function") {
        if (snapshot && snapshot.ariaBusy != null) {
          target.setAttribute("aria-busy", snapshot.ariaBusy);
        } else {
          target.removeAttribute("aria-busy");
        }
      }

      if (settings.className && target.classList) {
        target.classList.remove(settings.className);
      }

      if (icon && snapshot && snapshot.iconClassName != null) {
        icon.className = snapshot.iconClassName;
      }

      if (label && snapshot && snapshot.labelText != null) {
        label.textContent = snapshot.labelText;
      }

      loadingStateCache.delete(target);
    }
  };

  /**
   * Convenience wrapper around withLoading for task-first call sites.
   * @param {*} task
   * @param {*} options
   * @returns {Promise<*>}
   */
  PairUI.run = (task, options = {}) => {
    const { target = null, ...rest } = options || {};
    return PairUI.withLoading(target, task, rest);
  };

  // ---------------------------------------------------------------------------
  // DOM Caching (Prevent Over-rendering)
  // ---------------------------------------------------------------------------
  
  const domCache = new WeakMap();

  /**
   * Smart DOM updater with caching to avoid redundant updates.
   * @param {*} el 
   * @param {*} type 
   * @param {*} key 
   * @param {*} value 
   * @returns 
   */
  function smartUpdate(el, type, key, value) {

    // get or init cache for this element
    let elCache = domCache.get(el);
    if (!elCache) {
      elCache = {};
      domCache.set(el, elCache);
    }

    // create a unique cache key (e.g., "text", "attr:href", "style:display")
    const cacheKey = key ? `${type}:${key}` : type;

    // if value hasn't changed, strictly skip DOM operation
    if (elCache[cacheKey] === value) return;

    // update cache
    elCache[cacheKey] = value;

    // update DOM
    if (type === "text") {
      el.textContent = value ?? "";
    } else if (type === "html") {
      el.innerHTML = value ?? "";
    } else if (type === "display") {
      el.style.display = value ? "" : "none";
    } else if (type === "class") {
      PairUI.setClass(el, key, !!value);
    } else if (type === "attr") {
      if (value === false || value == null) el.removeAttribute(key);
      else el.setAttribute(key, String(value));
    } else if (type === "prop") {
      el[key] = value;
    } else if (type === "style") {
      el.style[key] = (value == null) ? "" : String(value);
    }
  }

  // ---------------------------------------------------------------------------
  // Plugin system
  // ---------------------------------------------------------------------------

  PairUI._plugins = [];
  PairUI.use = (plugin, options = {}) => {
    if (!plugin) return PairUI;

    // avoid double install
    if (PairUI._plugins.includes(plugin)) return PairUI;
    PairUI._plugins.push(plugin);

    // plugin can be function(PairUI, options) or { install(PairUI, options) }
    if (typeof plugin === "function") plugin(PairUI, options);
    else if (typeof plugin.install === "function") plugin.install(PairUI, options);

    return PairUI;
  };

  // ---------------------------------------------------------------------------
  // Path helpers (dot + [index] support)
  // ---------------------------------------------------------------------------

  /**
   * Parse path string into array of keys.
   * @param {*} path 
   * @returns 
   */
  function parsePath(path) {

    // supports: a.b.c, items[0].name, items.0.name
    if (typeof path !== "string") return [];

    const out = [];
    const re = /[^.[\]]+|\[(\d+)\]/g;
    let m;

    // extract keys
    while ((m = re.exec(path))) {
      out.push(m[1] !== undefined ? Number(m[1]) : m[0]);
    }

    return out;
  }

  /**
   * Get value by path.
   * @param {*} obj 
   * @param {*} path 
   * @returns 
   */
  function getByPath(obj, path) {

    // parse keys
    const keys = Array.isArray(path) ? path : parsePath(path);

    let cur = obj;

    // traverse keys
    for (const k of keys) {
      if (cur == null) return undefined;
      cur = cur[k];
    }
    return cur;
  }

  /**
   * Set value by path, creating intermediate objects/arrays as needed.
   * @param {*} obj 
   * @param {*} path 
   * @param {*} value 
   * @returns 
   */
  function setByPath(obj, path, value) {

    // parse keys
    const keys = Array.isArray(path) ? path : parsePath(path);
    if (!keys.length) return false;

    let cur = obj;

    // traverse to the parent of the last key
    for (let i = 0; i < keys.length - 1; i++) {
      const k = keys[i];

      // create intermediate object/array if needed
      if (cur[k] == null || (typeof cur[k] !== "object" && typeof cur[k] !== "function")) {
        const nextKey = keys[i + 1];
        cur[k] = typeof nextKey === "number" ? [] : {};
      }
      cur = cur[k];
    }

    // set the value at the last key
    cur[keys[keys.length - 1]] = value;
    return true;
  }

  /**
   * Evaluate expression with optional negation and scope support.
   * @param {*} expr 
   * @param {*} state 
   * @param {*} scope 
   * @returns 
   */
  function evalExpr(expr, state, scope) {

    // null/undefined check
    if (expr == null) return undefined;
    const raw = String(expr).trim();
    if (!raw) return undefined;

    let neg = false;
    let s = raw;

    // handle negation
    if (s.startsWith("!")) {
      neg = true;
      s = s.slice(1).trim();
    }

    let val;

    // scope support: "item.name" or "index" etc.
    if (scope && typeof scope === "object") {

      // direct match in scope
      if (Object.prototype.hasOwnProperty.call(scope, s)) {

        val = scope[s];
      
      } else {
    
        // try dotted path with scope root
        const dot = s.indexOf(".");
        if (dot > 0) {
          const head = s.slice(0, dot);

          // head exists in scope
          if (Object.prototype.hasOwnProperty.call(scope, head)) {
            val = getByPath(scope[head], s.slice(dot + 1));
          // try head as index in scope (for arrays)
          } else {
            val = getByPath(state, s);
          }

        // fallback to state
        } else {
          val = getByPath(state, s);
        }

      
      }
    
    } else {
    
      val = getByPath(state, s);
    
    }

    return neg ? !val : val;
  }

  // ---------------------------------------------------------------------------
  // Safe argument parsing for data-on (no eval)
  // ---------------------------------------------------------------------------
  //
  // Syntax:
  //   data-on="click:save($user.id, 'ok', 12, true)"
  //   data-on="click:remove($index)"
  //
  // Rules:
  // - `$something` means expression/path resolved via store.get(expr, scope)
  // - literals: numbers, true/false/null, quoted strings ('...' or "...")
  // - bare tokens (without $ and without quotes) are treated as strings
  //

  /**
   * Split argument string by commas, respecting quotes.
   * @param {*} argStr 
   * @returns 
   */
  function splitArgs(argStr) {

    // trim and check empty
    const s = String(argStr || "").trim();
    if (!s) return [];

    const out = [];
    let cur = "";
    let quote = null;

    // simple state machine
    for (let i = 0; i < s.length; i++) {
      const ch = s[i];

      // inside quotes
      if (quote) {
        cur += ch;
        if (ch === quote && s[i - 1] !== "\\") quote = null;
        continue;
      }

      // start quote
      if (ch === "'" || ch === '"') {
        quote = ch;
        cur += ch;
        continue;
      }

      // argument separator
      if (ch === ",") {
        out.push(cur.trim());
        cur = "";
        continue;
      }

      cur += ch;
    }

    // last arg
    if (cur.trim()) out.push(cur.trim());
    return out;
  }

  /**
   * Parse literal token: 'string', 12, true, false, null, bareword => string.
   * @param {*} token 
   * @returns 
   */
  function parseLiteral(token) {
    const t = String(token).trim();
    if (!t) return "";

    // quoted string
    if ((t.startsWith("'") && t.endsWith("'")) || (t.startsWith('"') && t.endsWith('"'))) {
      const q = t[0];
      // unescape minimal (\" and \')
      return t.slice(1, -1).replaceAll("\\".concat(q), q).replaceAll("\\\\", "\\");
    }

    // booleans / null
    if (t === "true") return true;
    if (t === "false") return false;
    if (t === "null") return null;

    // number
    if (/^-?\d+(\.\d+)?$/.test(t)) return Number(t);

    // bare token => string
    return t;
  }

  /**
   * Parse handler spec: "save($user.id, 'ok', 12)" => { name: "save", args: [...] }.
   * @param {*} spec 
   * @returns 
   */
  function parseHandlerSpec(spec) {

    // "save" or "save(...)" => { name, argsTokens[] }
    const s = String(spec || "").trim();
    if (!s) return null;

    // find open parenthesis
    const open = s.indexOf("(");
    if (open === -1) return { name: s, args: [] };

    // find matching close parenthesis
    const close = s.lastIndexOf(")");
    if (close === -1 || close < open) return { name: s.slice(0, open).trim(), args: [] };

    // extract name and args
    const name = s.slice(0, open).trim();
    const inside = s.slice(open + 1, close).trim();

    return { name, args: splitArgs(inside) };
  }

  // ---------------------------------------------------------------------------
  // Reactive store (deep proxy) + effects
  // ---------------------------------------------------------------------------

  PairUI.createStore = function createStore(initial = {}, getters = {}) {

    const listeners = new Set();

    // debounce flag
    let scheduled = false;

    // notify all listeners (debounced via microtask)
    const notify = () => {
      if (scheduled) return;
      scheduled = true;
      queueMicrotask(() => {
        scheduled = false;
        listeners.forEach((fn) => fn());
      });
    };

    // cache for reactive proxies
    const proxyCache = new WeakMap();

    // deep reactive proxy
    const makeReactive = (obj) => {
      if (obj == null || typeof obj !== "object") return obj;
      if (proxyCache.has(obj)) return proxyCache.get(obj);

      const p = new Proxy(obj, {
        get(target, prop) {
          const v = target[prop];
          return makeReactive(v);
        },
        set(target, prop, value) {
          if (Object.is(target[prop], value)) {
            return true;
          }

          target[prop] = value;
          notify();
          return true;
        },
        deleteProperty(target, prop) {
          if (!Object.prototype.hasOwnProperty.call(target, prop)) {
            return true;
          }

          delete target[prop];
          notify();
          return true;
        },
      });

      proxyCache.set(obj, p);
      return p;
    };

    // clone initial state
    const raw = (typeof structuredClone === "function")
      ? structuredClone(initial)
      : JSON.parse(JSON.stringify(initial));

    // define Getters (computed) on the raw object before proxying
    // note: Getters will be read-only in the state
    if (getters && typeof getters === "object") {
        for (const [key, fn] of Object.entries(getters)) {
            if (typeof fn === "function") {
                Object.defineProperty(raw, key, {
                    get: () => fn(store.state),
                    enumerable: true,
                    configurable: true
                });
            }
        }
    }

    // make reactive proxy
    const state = makeReactive(raw);

    const store = {
      state,

      subscribe(fn) {
        listeners.add(fn);
        return () => listeners.delete(fn);
      },

      // register an effect (runs on state changes)
      effect(fn, { immediate = true } = {}) {
        const run = () => fn(store);
        if (immediate) run();
        return store.subscribe(run);
      },

      // get/set by path with scope support
      get(path, scope) {
        return evalExpr(path, store.state, scope);
      },

      set(path, value) {
        if (Object.is(getByPath(raw, path), value)) {
          return true;
        }

        const ok = setByPath(raw, path, value);
        if (ok) notify();
        return ok;
      },

      // merge object into state
      patch(obj) {
        if (!obj || typeof obj !== "object") return;
        let changed = false;

        for (const [key, value] of Object.entries(obj)) {
          if (!Object.is(raw[key], value)) {
            raw[key] = value;
            changed = true;
          }
        }

        if (changed) notify();
      },

      actions: Object.create(null),

      // register an action
      action(name, fn) {
        if (name && typeof fn === "function") store.actions[name] = fn;
      },

      // expose notify for manual triggering
      notify,
    };

    return store;
  };

  // ---------------------------------------------------------------------------
  // DOM Binding ("directives" via data-*)
  // ---------------------------------------------------------------------------

  // Supported directives:
  // - data-text="path"
  // - data-html="path"
  // - data-show="path|!path"
  // - data-if="path|!path"          -> remove/insert node with placeholder
  // - data-class="a:flag b:!flag2"
  // - data-attr="href:url title:user.name"
  // - data-prop="disabled:loading checked:isChecked"
  // - data-style="opacity:op display:displayValue"
  // - data-model="path"
  // - data-on="click:inc, change:save($user.id)"
  // - data-each="items" + <template> ... and scope vars item/index

  /**
   * Parse "key:expr" pairs from a string.
   * @param {*} str 
   * @returns 
   */
  function parsePairs(str) {
    // "a:b c:d" or "a:b, c:d" or multiline
    if (!str) return [];
    const parts = String(str)
      // split also on spaces before the next "key:" token without breaking values
      .split(/[\n,]+|\s+(?=[A-Za-z0-9_-]+\s*:)/)
      .map(s => s.trim())
      .filter(Boolean);

    const out = [];
    for (const p of parts) {
      const idx = p.indexOf(":");
      if (idx === -1) continue;
      out.push({ k: p.slice(0, idx).trim(), v: p.slice(idx + 1).trim() });
    }
    return out;
  }

  /**
   * Bind data-model (two-way binding).
   * @param {*} el 
   * @param {*} store 
   * @param {*} path 
   * @param {*} scope 
   * @returns 
   */
  function bindModel(el, store, path, scope) {
    // note: data-model expects a real store path (not scope-only).
    const tag = el.tagName.toLowerCase();
    const type = (el.getAttribute("type") || "").toLowerCase();
    const isRadio = (tag === "input" && type === "radio");
    const isCheck = (tag === "input" && type === "checkbox");

    const readFromState = () => {
      const v = store.get(path, scope);
      if (isCheck) {
        el.checked = !!v;
      } else if (isRadio) {
        // compare value strings for radio buttons
        el.checked = (String(v) === String(el.value));
      } else {
        el.value = (v ?? "");
      }
    };

    const writeToState = () => {
      let v;
      if (isCheck) {
        v = !!el.checked;
      } else if (isRadio) {
         // only update state if this radio is the one checked
         if (el.checked) v = el.value;
         else return; 
      } else if (tag === "input" && type === "number") {
        const n = el.value === "" ? null : Number(el.value);
        v = Number.isNaN(n) ? null : n;
      } else {
        v = el.value;
      }
      store.set(path, v);
    };

    readFromState();

    const evt = (tag === "select" || isCheck || isRadio) ? "change" : "input";
    el.addEventListener(evt, writeToState);

    return () => el.removeEventListener(evt, writeToState);
  }

  /**
   * Bind data-on events.
   * @param {*} el 
   * @param {*} store 
   * @param {*} spec 
   * @param {*} scope 
   * @returns 
   */
  function bindEvents(el, store, spec, scope) {
    // data-on="click:inc, keyup:search($query)"
    const pairs = parsePairs(spec);
    const unsubs = [];

    // for each event: create handler
    for (const { k: evt, v: handlerSpec } of pairs) {
      const parsed = parseHandlerSpec(handlerSpec);
      if (!parsed || !parsed.name) continue;

      const handler = (event) => {
        const fn = store.actions[parsed.name];
        if (typeof fn !== "function") return;

        const args = parsed.args.map((tok) => {
          const t = String(tok).trim();
          if (t.startsWith("$")) {
            const expr = t.slice(1).trim();
            return store.get(expr, scope);
          }
          return parseLiteral(t);
        });

        fn({
          event,
          el,
          store,
          scope: scope || null,
          args,
          value: el.value,
        });
      };

      el.addEventListener(evt, handler);
      unsubs.push(() => el.removeEventListener(evt, handler));
    }

    return () => unsubs.forEach((u) => u());
  }

  // data-if placeholder management
  const ifCache = new WeakMap();

  /**
   * Apply data-if directive (remove/insert element).
   * @param {*} el 
   * @param {*} store 
   * @param {*} scope 
   * @returns 
   */
  function applyIf(el, store, scope) {
    const expr = el.dataset.if;
    if (expr == null) return;

    const shouldShow = !!store.get(expr, scope);

    // if element is currently in DOM
    if (el.isConnected) {
      if (!shouldShow) {
        const placeholder = document.createComment("pair-if");
        const parent = el.parentNode;
        ifCache.set(el, { placeholder, parent, nextSibling: el.nextSibling });
        parent.replaceChild(placeholder, el);
      }
      return;
    }

    // element is not connected: maybe hidden due to data-if
    const cached = ifCache.get(el);
    if (!cached) return;

    // re-insert if should show
    if (shouldShow) {
      const { placeholder, parent } = cached;
      if (placeholder && parent && placeholder.parentNode === parent) {
        parent.replaceChild(el, placeholder);
        ifCache.delete(el);
      }
    }
  }

  /**
   * Render directives on a single element.
   * @param {*} el 
   * @param {*} store 
   * @param {*} scope 
   * @returns 
   */
  function renderElement(el, store, scope) {
    // data-if first (may remove/insert)
    if (el.dataset.if != null) {
      applyIf(el, store, scope);
      // if removed, skip other directives
      if (!el.isConnected) return;
    }

    // data-text sse smartUpdate for all simple directives to avoid DOM thrashing
    if (el.dataset.text != null) 
      smartUpdate(el, "text", null, store.get(el.dataset.text, scope));

    // data-html
    if (el.dataset.html != null)
      smartUpdate(el, "html", null, store.get(el.dataset.html, scope));

    // data-show
    if (el.dataset.show != null) 
      smartUpdate(el, "display", null, !!store.get(el.dataset.show, scope));

    // data-class
    if (el.dataset.class != null) {
      const pairs = parsePairs(el.dataset.class);
      for (const { k: className, v: expr } of pairs) {
        smartUpdate(el, "class", className, store.get(expr, scope));
      }
    }

    // data-attr
    if (el.dataset.attr != null) {
      const pairs = parsePairs(el.dataset.attr);
      for (const { k: attr, v: expr } of pairs) {
        smartUpdate(el, "attr", attr, store.get(expr, scope));
      }
    }

    // data-prop
    if (el.dataset.prop != null) {
      const pairs = parsePairs(el.dataset.prop);
      for (const { k: prop, v: expr } of pairs) {
        smartUpdate(el, "prop", prop, store.get(expr, scope));
      }
    }

    // data-style
    if (el.dataset.style != null) {
      const pairs = parsePairs(el.dataset.style);
      for (const { k: cssProp, v: expr } of pairs) {
        smartUpdate(el, "style", cssProp, store.get(expr, scope));
      }
    }
  }

  /**
   * Setup data-each rendering.
   * @param {*} container 
   * @param {*} store 
   * @returns 
   */
  function setupEach(container, store) {
    const path = container.dataset.each;
    const itemVar = container.dataset.eachItem || "item";
    const indexVar = container.dataset.eachIndex || "index";

    // find template
    const tpl = container.querySelector("template");
    if (!tpl) return null;

    // clear rendered items (except template)
    const clearRendered = () => {
      for (const child of Array.from(container.children)) {
        if (child !== tpl) child.remove();
      }
    };

    // render function
    const renderList = () => {
      if (!container.isConnected) return; // safety check
      clearRendered();

      const items = store.get(path);
      if (!Array.isArray(items)) return;

      // document fragment for better performance
      const frag = document.createDocumentFragment();

      // render each item
      for (let i = 0; i < items.length; i++) {
        const scope = { [itemVar]: items[i], [indexVar]: i };

        // clone template
        const instance = tpl.content.cloneNode(true);

        // render directives inside instance (including data-if)
        const renderNodes = instance.querySelectorAll(
          "[data-if],[data-text],[data-html],[data-show],[data-class],[data-attr],[data-prop],[data-style]"
        );
        for (const n of renderNodes) renderElement(n, store, scope);

        // bind events inside instance
        const onNodes = instance.querySelectorAll("[data-on]");
        for (const n of onNodes) bindEvents(n, store, n.dataset.on, scope);

        frag.appendChild(instance);
      }

      container.appendChild(frag);
    };

    return renderList;
  }

  // ---------------------------------------------------------------------------
  // Mount
  // ---------------------------------------------------------------------------

  PairUI.mount = function mount(root, store, options = {}) {
    const r = root || document;

    // gather all nodes that need rendering
    const renderNodes = PairUI.qsa(
      "[data-if],[data-text],[data-html],[data-show],[data-class],[data-attr],[data-prop],[data-style]",
      r
    );

    // setup data-model
    const modelNodes = PairUI.qsa("[data-model]", r);
    const modelUnsubs = modelNodes.map(el => bindModel(el, store, el.dataset.model));

    // setup data-on
    const onNodes = PairUI.qsa("[data-on]", r);
    const onUnsubs = onNodes.map(el => bindEvents(el, store, el.dataset.on, null));

    // setup data-each
    const eachNodes = PairUI.qsa("[data-each]", r);
    const eachRenders = [];
    for (const c of eachNodes) {
      const fn = setupEach(c, store);
      if (typeof fn === "function") eachRenders.push(fn);
    }

    const render = () => {
      // safety: Filter out detached nodes to prevent errors and leaks
      for (const el of renderNodes) {
         if (el.isConnected) renderElement(el, store, null);
      }
      // re-render lists
      for (const fn of eachRenders) fn();
    };

    render();
    const unsub = store.subscribe(render);

    return () => {
      unsub();
      modelUnsubs.forEach((u) => u());
      onUnsubs.forEach((u) => u());
    };
  };

  // ---------------------------------------------------------------------------
  // Fetch helper
  // ---------------------------------------------------------------------------

  PairUI.http = {
    /**
     * Convert query params into a query string.
     * @param {*} params
     * @returns {string}
     */
    buildQuery(params) {
      if (params == null) return "";
      if (typeof params === "string") return params.replace(/^\?/, "");
      if (params instanceof URLSearchParams) return params.toString();

      const search = new URLSearchParams();
      const entries = isPlainObject(params) ? Object.entries(params) : [];
      for (const [key, value] of entries) appendKeyValue(search, key, value);
      return search.toString();
    },

    /**
     * Append query params to a URL without losing existing query strings.
     * @param {string} url
     * @param {*} params
     * @returns {string}
     */
    buildUrl(url, params) {
      const queryString = this.buildQuery(params);
      if (!queryString) return String(url);

      const rawUrl = String(url);
      const hashIndex = rawUrl.indexOf("#");
      const baseUrl = hashIndex === -1 ? rawUrl : rawUrl.slice(0, hashIndex);
      const hash = hashIndex === -1 ? "" : rawUrl.slice(hashIndex);

      return baseUrl + (baseUrl.includes("?") ? "&" : "?") + queryString + hash;
    },

    /**
     * Convert forms, FormData and plain objects into FormData instances.
     * @param {*} formElOrObj
     * @returns {FormData}
     */
    formData(formElOrObj) {
      if (formElOrObj instanceof FormData) return formElOrObj;
      if (formElOrObj instanceof HTMLFormElement) return new FormData(formElOrObj);

      const body = new FormData();

      if (formElOrObj instanceof URLSearchParams) {
        for (const [key, value] of formElOrObj.entries()) {
          body.append(key, value);
        }

        return body;
      }

      const obj = formElOrObj || {};
      for (const [key, value] of Object.entries(obj)) appendKeyValue(body, key, value);
      return body;
    },

    /**
     * Parse a fetch Response according to the expected payload type.
     * @param {Response} res
     * @param {string} expect
     * @returns {Promise<*>}
     */
    async parseResponse(res, expect = "auto") {
      if (expect === "response") {
        if (!res.ok) {
          const err = new Error(`HTTP ${res.status}`);
          err.status = res.status;
          err.response = res;
          throw err;
        }

        return res;
      }

      const contentType = res.headers.get("content-type") || "";
      const wantsJson = expect === "json" || (expect === "auto" && contentType.includes("application/json"));

      let payload;
      if (res.status === 204) {
        payload = null;
      } else if (wantsJson) {
        payload = await res.json().catch(() => null);
      } else if (expect === "blob") {
        payload = await res.blob();
      } else {
        payload = await res.text();
      }

      if (!res.ok) {
        const err = new Error(getErrorMessage({ payload }, `HTTP ${res.status}`));
        err.status = res.status;
        err.payload = payload;
        err.response = res;
        throw err;
      }

      return payload;
    },

    /**
     * Perform an HTTP request with default AJAX headers and helper payload options.
     * @param {string} url
     * @param {*} opts
     * @returns {Promise<*>}
     */
    async request(url, opts = {}) {
      const {
        query,
        json,
        form,
        expect = "auto",
        ...fetchOptions
      } = opts || {};

      const headers = new Headers(fetchOptions.headers || {});
      if (!headers.has("X-Requested-With")) {
        headers.set("X-Requested-With", "XMLHttpRequest");
      }

      let body = fetchOptions.body;
      if (json !== undefined) {
        body = JSON.stringify(json ?? {});
        if (!headers.has("Content-Type")) {
          headers.set("Content-Type", "application/json");
        }
      } else if (form !== undefined) {
        body = this.formData(form);
      }

      const requestUrl = query != null ? this.buildUrl(url, query) : url;
      const res = await fetch(requestUrl, {
        credentials: "same-origin",
        ...fetchOptions,
        headers,
        body,
      });

      return this.parseResponse(res, expect);
    },

    get(url, opts) {
      return this.request(url, { method: "GET", ...(opts || {}) });
    },

    postJson(url, data, opts = {}) {
      return this.request(url, {
        method: "POST",
        json: data,
        ...opts,
      });
    },

    postForm(url, formElOrObj, opts = {}) {
      return this.request(url, {
        method: "POST",
        form: formElOrObj,
        ...opts,
      });
    },
  };

  // helpers to read/write Pair-style persistent state cookies from the client
  PairUI.persist = {
    /**
     * Shared persistent-state configuration used by PairUI.persist helpers.
     */
    config: persistConfig,

    /**
     * Merge runtime configuration into the persistent-state defaults.
     * @param {*} options
     * @returns {Object}
     */
    configure(options = {}) {
      Object.assign(persistConfig, options || {});
      return getPersistSettings();
    },

    /**
     * Build the cookie name using Pair's ucfirst naming convention.
     * @param {string} name
     * @param {*} options
     * @returns {string}
     */
    getCookieName(name, options = {}) {
      const settings = getPersistSettings(options);
      const rawName = String(name ?? "").trim();
      return String(settings.cookiePrefix || "") + rawName.charAt(0).toUpperCase() + rawName.slice(1);
    },

    /**
     * Serialize scalar values using PHP's cookie format for Pair persistent state.
     * @param {*} value
     * @returns {string}
     */
    serialize(value) {
      if (value === null) return "N;";
      if (value === true || value === false) return `b:${value ? 1 : 0};`;
      if (typeof value === "number" && Number.isFinite(value)) {
        return Number.isInteger(value) ? `i:${value};` : `d:${String(value)};`;
      }

      const stringValue = String(value ?? "");
      return `s:${getUtf8ByteLength(stringValue)}:"${stringValue}";`;
    },

    /**
     * Parse simple scalar values serialized by Pair persistent-state cookies.
     * @param {*} serialized
     * @returns {*}
     */
    deserialize(serialized) {
      const value = String(serialized ?? "");

      if (value === "N;") return null;

      let match = /^b:(0|1);$/.exec(value);
      if (match) return match[1] === "1";

      match = /^i:(-?\d+);$/.exec(value);
      if (match) return Number(match[1]);

      match = /^d:(-?\d+(?:\.\d+)?(?:E[+-]?\d+)?);$/i.exec(value);
      if (match) return Number(match[1]);

      match = /^s:\d+:"([\s\S]*)";$/.exec(value);
      if (match) return match[1];

      return value;
    },

    /**
     * Read the raw serialized cookie value for a persistent state key.
     * @param {string} name
     * @param {*} options
     * @returns {?string}
     */
    getRaw(name, options = {}) {
      return readCookieValue(this.getCookieName(name, options));
    },

    /**
     * Read and deserialize a persistent state key from document.cookie.
     * @param {string} name
     * @param {*} options
     * @returns {*}
     */
    get(name, options = {}) {
      const raw = this.getRaw(name, options);
      return raw == null ? null : this.deserialize(raw);
    },

    /**
     * Set a Pair-style persistent state cookie from the browser.
     * @param {string} name
     * @param {*} value
     * @param {*} options
     * @returns {string}
     */
    set(name, value, options = {}) {
      const settings = getPersistSettings(options);
      const cookieName = this.getCookieName(name, settings);
      const expires = new Date(Date.now() + (Number(settings.days || 30) * 86400000));
      let cookie = `${encodeURIComponent(cookieName)}=${encodeURIComponent(this.serialize(value))}; path=${settings.path || "/"}; expires=${expires.toUTCString()}; SameSite=${settings.sameSite || "Lax"}`;

      const isSecure = settings.secure == null
        ? global.location && global.location.protocol === "https:"
        : !!settings.secure;

      if (isSecure) {
        cookie += "; Secure";
      }

      document.cookie = cookie;
      return cookieName;
    },

    /**
     * Remove a persistent state cookie immediately.
     * @param {string} name
     * @param {*} options
     * @returns {string}
     */
    unset(name, options = {}) {
      const settings = getPersistSettings(options);
      const cookieName = this.getCookieName(name, settings);
      let cookie = `${encodeURIComponent(cookieName)}=; path=${settings.path || "/"}; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=${settings.sameSite || "Lax"}`;

      const isSecure = settings.secure == null
        ? global.location && global.location.protocol === "https:"
        : !!settings.secure;

      if (isSecure) {
        cookie += "; Secure";
      }

      document.cookie = cookie;
      return cookieName;
    },

    /**
     * Bind form controls to persistent state cookies with optional reload.
     * @param {*} selector
     * @param {string} stateName
     * @param {*} options
     * @returns {Function}
     */
    bind(selector, stateName, options = {}) {
      const settings = { event: "change", reload: true, root: document, ...options };
      const elements = resolveElements(selector, settings.root);
      const handleChange = (event) => {
        const element = event.currentTarget;
        let value = typeof settings.getValue === "function"
          ? settings.getValue(element, event)
          : element.value;

        if (typeof settings.normalize === "function") {
          value = settings.normalize(value, element, event);
        }

        const shouldUnset = typeof settings.shouldUnset === "function"
          ? settings.shouldUnset(value, element, event)
          : value == null || value === "" || Number.isNaN(value);

        if (shouldUnset) {
          this.unset(stateName, settings);
        } else {
          this.set(stateName, value, settings);
        }

        if (typeof settings.afterChange === "function") {
          settings.afterChange(value, element, event);
        }

        if (settings.reload) {
          global.location.reload();
        }
      };

      for (const element of elements) {
        PairUI.on(element, settings.event, handleChange);
      }

      return () => {
        for (const element of elements) {
          PairUI.off(element, settings.event, handleChange);
        }
      };
    },
  };

  // ---------------------------------------------------------------------------
  // Island helper
  // ---------------------------------------------------------------------------

  PairUI.island = function island(selector, factory) {
    const roots = PairUI.qsa(selector);
    const unsubs = [];
    for (const root of roots) {
      const cleanup = factory(root);
      if (typeof cleanup === "function") unsubs.push(cleanup);
    }
    return () => unsubs.forEach((u) => u());
  };

  // ---------------------------------------------------------------------------
  // Convenience "createApp"
  // ---------------------------------------------------------------------------

  // Example:
  // const app = PairUI.createApp({
  //   root: document,
  //   state: { count: 0 },
  //   getters: { doubleCount: (state) => state.count * 2 },
  //   actions: { inc: ({store}) => store.state.count++ }
  // });
  PairUI.createApp = function createApp({ root = document, state = {}, getters = {}, actions = {}, plugins = [] } = {}) {
    for (const p of plugins) PairUI.use(p);

    const store = PairUI.createStore(state, getters);

    for (const [name, fn] of Object.entries(actions || {})) {
      store.action(name, fn);
    }

    const unmount = PairUI.mount(root, store);
    return { store, unmount };
  };

})(typeof window !== "undefined" ? window : globalThis);

/* ---------------------------------------------------------------------------
USAGE EXAMPLES
------------------------------------------------------------------------------

1) data-if (remove/insert node)

HTML:
  <div data-if="isLogged">
    <b data-text="user.name"></b>
  </div>
  <div data-if="!isLogged">Please log in</div>

2) data-on with safe args (no eval)

HTML:
  <button data-on="click:save($user.id, 'profile')">Save</button>

JS action receives { args: [resolvedUserId, "profile"] }.

3) data-each with scope vars item/index + events

HTML:
  <ul data-each="items" data-each-item="item" data-each-index="index">
    <template>
      <li>
        <span data-text="index"></span> -
        <b data-text="item.name"></b>
        <button data-on="click:remove($index)">Remove</button>
      </li>
    </template>
  </ul>

JS:
  const store = PairUI.createStore({ items: [{name:"A"},{name:"B"}] });

  store.action("remove", ({ store, args }) => {
    const idx = args[0];
    store.state.items.splice(idx, 1);
  });

  PairUI.mount(document, store);

4) createApp

PairUI.createApp({
  root: document,
  state: { count: 0, user: { id: 10, name: "Marino" } },
  actions: {
    inc: ({ store }) => { store.state.count += 1; },
    save: async ({ store, args }) => {
      const [userId, kind] = args;
      await PairUI.http.postJson("/api/save", { userId, kind });
    }
  }
});

5) run async work with a loading state

await PairUI.withLoading(button, async () => {
  await PairUI.http.postForm("/api/save", form);
}, {
  iconSelector: "i",
  loadingIconClass: "icon-spinner is-spinning"
});

6) emit a custom event

const changed = PairUI.emit(form, "pair:filters-changed", { page: 1 });
if (changed.defaultPrevented) {
  return;
}

7) persist a filter and reload

PairUI.persist.configure({ cookiePrefix: "app_" });
PairUI.persist.bind("select.category-filter", "categoryFilter", {
  normalize(value) {
    return value === "" ? null : parseInt(value, 10);
  }
});

8) build escaped HTML

const optionsHtml = PairUI.html`
  ${rows.map((row) => PairUI.html`<option value="${row.id}">${row.label}</option>`)}
`;

--------------------------------------------------------------------------- */
