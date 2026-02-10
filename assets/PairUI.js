/*!
 * PairUI - Lightweight client helpers for server-rendered apps (Pair Framework)
 *
 * Goals:
 * - No build step
 * - Progressive enhancement
 * - Vue-like directives via data-*
 * - Simple reactive store + DOM bindings
 * - Safe parsing (no eval)
 *
 * This is NOT a virtual-DOM framework.
 */

(function (global) {
  "use strict";

  const PairUI = {};
  PairUI.version = "0.3.0";

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
          target[prop] = value;
          notify();
          return true;
        },
        deleteProperty(target, prop) {
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
        const ok = setByPath(raw, path, value);
        if (ok) notify();
        return ok;
      },

      // merge object into state
      patch(obj) {
        if (!obj || typeof obj !== "object") return;
        Object.assign(raw, obj);
        notify();
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
      .split(/[,\n]+/)
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
    async request(url, opts = {}) {
      const res = await fetch(url, {
        credentials: "same-origin",
        ...opts,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          ...(opts.headers || {}),
        },
      });

      const contentType = res.headers.get("content-type") || "";
      const isJson = contentType.includes("application/json");

      const payload = isJson ? await res.json().catch(() => null) : await res.text();

      if (!res.ok) {
        const err = new Error(`HTTP ${res.status}`);
        err.status = res.status;
        err.payload = payload;
        throw err;
      }
      return payload;
    },

    get(url, opts) {
      return this.request(url, { method: "GET", ...(opts || {}) });
    },

    postJson(url, data, opts = {}) {
      return this.request(url, {
        method: "POST",
        body: JSON.stringify(data ?? {}),
        headers: { "Content-Type": "application/json", ...(opts.headers || {}) },
        ...opts,
      });
    },

    postForm(url, formElOrObj, opts = {}) {
      let body;
      if (formElOrObj instanceof HTMLFormElement) body = new FormData(formElOrObj);
      else {
        body = new FormData();
        const obj = formElOrObj || {};
        for (const k of Object.keys(obj)) body.append(k, obj[k]);
      }

      return this.request(url, {
        method: "POST",
        body,
        ...opts,
      });
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

--------------------------------------------------------------------------- */