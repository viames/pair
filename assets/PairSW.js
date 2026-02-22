/* PairSW - default service worker for Pair apps (no build step). */

const SW_VERSION = "pair-sw-v3";
const STATIC_CACHE = `${SW_VERSION}-static`;
const RUNTIME_CACHE = `${SW_VERSION}-runtime`;

const STORAGE_DB = "pair-pwa-storage-db";
const STORAGE_DB_VERSION = 2;
const STORE_SYNC = "pair-pwa-sync-store";
const STORE_CACHE_META = "pair-pwa-cache-meta";

const DEFAULT_SYNC_TAG = "pair-sync-default";

const currentUrl = new URL(self.location.href);
const PWA_OPTIONS = parsePwaOptions(currentUrl.searchParams.get("pwa"));

const CACHE_CONFIG = normalizeCacheConfig(PWA_OPTIONS.cache || {});
const SYNC_CONFIG = normalizeSyncConfig(PWA_OPTIONS.sync || {});
const PUSH_CONFIG = normalizePushConfig(PWA_OPTIONS.push || {});

const OFFLINE_FALLBACK =
  currentUrl.searchParams.get("offline") ||
  (typeof PWA_OPTIONS.offlineFallback === "string" ? PWA_OPTIONS.offlineFallback : "") ||
  "/offline.html";

const ASSETS_BASE = currentUrl.pathname.includes("/")
  ? currentUrl.pathname.substring(0, currentUrl.pathname.lastIndexOf("/"))
  : "";

const EXTRA_PRECACHE_ASSETS = Array.isArray(PWA_OPTIONS.precache)
  ? PWA_OPTIONS.precache.filter((item) => typeof item === "string" && item.trim())
  : [];

const PRECACHE_ASSETS = dedupeList([
  "/",
  OFFLINE_FALLBACK,
  `${ASSETS_BASE}/PairUI.js`,
  `${ASSETS_BASE}/PairPWA.js`,
  `${ASSETS_BASE}/PairRouter.js`,
  `${ASSETS_BASE}/PairSkeleton.js`,
  `${ASSETS_BASE}/PairDevice.js`,
  ...EXTRA_PRECACHE_ASSETS,
].filter(Boolean));

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE_ASSETS))
      .catch(() => Promise.resolve())
  );

  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key !== STATIC_CACHE && key !== RUNTIME_CACHE)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;

  if (request.method === "GET") {
    const url = new URL(request.url);
    const strategy = resolveCacheStrategy(request, url);
    const fallbackPath = request.mode === "navigate" ? OFFLINE_FALLBACK : null;

    event.respondWith(executeCacheStrategy(strategy, request, fallbackPath));
    return;
  }

  if (shouldQueueMutation(request)) {
    event.respondWith(networkWithQueue(request));
  }
});

self.addEventListener("sync", (event) => {
  if (!isSyncTag(event.tag)) return;

  event.waitUntil(flushQueuedRequests(event.tag).catch(() => 0));
});

self.addEventListener("message", (event) => {
  const data = event.data || {};

  if (data.type === "PAIR_SW_SKIP_WAITING") {
    self.skipWaiting();
    return;
  }

  if (data.type === "PAIR_SW_PRELOAD") {
    const urls = data.payload && Array.isArray(data.payload.urls) ? data.payload.urls : [];
    event.waitUntil(preloadUrls(urls));
    return;
  }

  if (data.type === "PAIR_SW_FLUSH_QUEUE") {
    const tag = data.payload && data.payload.tag ? String(data.payload.tag) : DEFAULT_SYNC_TAG;
    event.waitUntil(flushQueuedRequests(tag).catch(() => 0));
    return;
  }

  if (data.type === "PAIR_SW_QUEUE_REQUEST") {
    event.waitUntil(queueRequestFromPayload(data.payload || {}).catch(() => false));
  }
});

self.addEventListener("push", (event) => {
  const payload = parsePushPayload(event);
  const options = buildPushNotificationOptions(payload);
  const title = normalizeOptionalString(payload.title) || PUSH_CONFIG.defaultTitle || "Notification";

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  const targetUrl = getNotificationTargetUrl(event.notification);
  event.notification.close();

  event.waitUntil(handleNotificationClick(targetUrl));
});

function acceptedQueueResponse() {
  return new Response(JSON.stringify({ queued: true, offline: true }), {
    status: 202,
    headers: { "Content-Type": "application/json" },
  });
}

async function broadcastMessage(type, payload = {}) {
  const clients = await self.clients.matchAll({ type: "window", includeUncontrolled: true });

  for (const client of clients) {
    client.postMessage({ type, payload });
  }
}

async function cacheFirst(request, fallbackPath = null) {
  const cached = await readRuntimeCache(request, CACHE_CONFIG.maxRuntimeAgeSeconds);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    await cacheRuntimeResponse(request, response);
    return response;
  } catch (_error) {
    const cachedFallback = await readRuntimeCache(request, CACHE_CONFIG.maxRuntimeAgeSeconds);
    if (cachedFallback) return cachedFallback;

    return offlineResponse(fallbackPath);
  }
}

async function cacheRuntimeResponse(request, response) {
  if (!isCacheableResponse(response)) return;

  const cache = await caches.open(RUNTIME_CACHE);
  await cache.put(request, response.clone());

  await putCacheMeta(request.url, Date.now()).catch(() => false);
  await trimRuntimeCache(CACHE_CONFIG.maxRuntimeEntries).catch(() => false);
}

function dedupeList(list) {
  return Array.from(new Set(list));
}

async function deleteCacheMeta(url) {
  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_CACHE_META, "readwrite");
    const store = tx.objectStore(STORE_CACHE_META);
    store.delete(url);

    tx.oncomplete = () => {
      db.close();
      resolve(true);
    };

    tx.onerror = () => {
      db.close();
      reject(tx.error);
    };
  });
}

async function deleteQueuedRequest(id) {
  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_SYNC, "readwrite");
    const store = tx.objectStore(STORE_SYNC);
    store.delete(id);

    tx.oncomplete = () => {
      db.close();
      resolve(true);
    };

    tx.onerror = () => {
      db.close();
      reject(tx.error);
    };
  });
}

function decodeBase64Url(value) {
  if (!value || typeof value !== "string") return null;

  const normalized = value.replace(/-/g, "+").replace(/_/g, "/");
  const padding = "=".repeat((4 - (normalized.length % 4)) % 4);

  try {
    const binary = atob(normalized + padding);
    let escaped = "";

    for (let i = 0; i < binary.length; i += 1) {
      const code = binary.charCodeAt(i).toString(16).padStart(2, "0");
      escaped += `%${code}`;
    }

    return decodeURIComponent(escaped);
  } catch (_error) {
    return null;
  }
}

function parsePushPayload(event) {
  if (!event || !event.data) return {};

  try {
    const payload = event.data.json();
    return payload && typeof payload === "object" ? payload : {};
  } catch (_jsonError) {
    try {
      const text = event.data.text();
      if (!text) return {};

      try {
        const payload = JSON.parse(text);
        return payload && typeof payload === "object" ? payload : { body: String(payload || "") };
      } catch (_parseError) {
        return { body: text };
      }
    } catch (_textError) {
      return {};
    }
  }
}

function buildPushNotificationOptions(payload) {
  const source = payload && typeof payload === "object" ? payload : {};
  const data = source.data && typeof source.data === "object" && !Array.isArray(source.data)
    ? { ...source.data }
    : {};

  const actionUrl = normalizeNotificationUrl(source.url || source.click_action || data.url || null);
  if (actionUrl) {
    data.url = actionUrl;
  }

  const options = {
    body: normalizeOptionalString(source.body) || "",
    data,
  };

  const icon = resolveNotificationAsset(source.icon) || resolveNotificationAsset(PUSH_CONFIG.icon);
  const badge = resolveNotificationAsset(source.badge) || resolveNotificationAsset(PUSH_CONFIG.badge);
  const image = resolveNotificationAsset(source.image) || resolveNotificationAsset(PUSH_CONFIG.image);
  const tag = normalizeOptionalString(source.tag);

  if (icon) options.icon = icon;
  if (badge) options.badge = badge;
  if (image) options.image = image;
  if (tag) options.tag = tag;

  const requireInteraction = normalizeBoolean(source.requireInteraction, PUSH_CONFIG.requireInteraction);
  const renotify = normalizeBoolean(source.renotify, PUSH_CONFIG.renotify);
  const silent = normalizeBoolean(source.silent, PUSH_CONFIG.silent);
  const vibrate = normalizeVibratePattern(source.vibrate) || PUSH_CONFIG.vibrate;
  const actions = normalizeNotificationActions(source.actions);

  if (typeof requireInteraction === "boolean") options.requireInteraction = requireInteraction;
  if (typeof renotify === "boolean") options.renotify = renotify;
  if (typeof silent === "boolean") options.silent = silent;
  if (Array.isArray(vibrate) && vibrate.length) options.vibrate = vibrate;
  if (actions.length) options.actions = actions;

  return options;
}

function normalizeNotificationActions(actions) {
  if (!Array.isArray(actions)) return [];

  return actions
    .map((item) => {
      if (!item || typeof item !== "object") return null;

      const action = normalizeOptionalString(item.action);
      const title = normalizeOptionalString(item.title);
      if (!action || !title) return null;

      const icon = resolveNotificationAsset(item.icon);
      const entry = { action, title };
      if (icon) entry.icon = icon;

      return entry;
    })
    .filter(Boolean);
}

function getNotificationTargetUrl(notification) {
  if (!notification || typeof notification !== "object") return null;

  const data = notification.data && typeof notification.data === "object" ? notification.data : {};
  return normalizeNotificationUrl(data.url || null);
}

async function handleNotificationClick(targetUrl) {
  const clientList = await clients.matchAll({ type: "window", includeUncontrolled: true });

  if (targetUrl) {
    for (const client of clientList) {
      if (client.url === targetUrl && "focus" in client) {
        return client.focus();
      }
    }

    for (const client of clientList) {
      if (!("navigate" in client) || !("focus" in client)) continue;

      try {
        await client.navigate(targetUrl);
        return client.focus();
      } catch (_error) {
        // try next client
      }
    }

    if (clients.openWindow) {
      return clients.openWindow(targetUrl);
    }

    return undefined;
  }

  if (clientList.length && "focus" in clientList[0]) {
    return clientList[0].focus();
  }

  return undefined;
}

function normalizeNotificationUrl(urlValue) {
  const value = normalizeOptionalString(urlValue);
  if (!value) return null;

  try {
    const url = new URL(value, self.location.origin);
    if (url.origin !== self.location.origin) {
      return null;
    }

    return url.href;
  } catch (_error) {
    return null;
  }
}

function resolveNotificationAsset(value) {
  const asset = normalizeOptionalString(value);
  if (!asset) return null;

  try {
    return new URL(asset, self.registration.scope).href;
  } catch (_error) {
    return null;
  }
}

function normalizeOptionalString(value) {
  if (typeof value !== "string") return "";

  const trimmed = value.trim();
  return trimmed.length ? trimmed : "";
}

function normalizeBoolean(value, fallback) {
  if (typeof value === "boolean") return value;
  if (typeof fallback === "boolean") return fallback;
  return undefined;
}

function normalizeVibratePattern(value) {
  if (!Array.isArray(value)) return null;

  const pattern = value
    .map((item) => Number(item))
    .filter((item) => Number.isFinite(item) && item >= 0)
    .map((item) => Math.round(item));

  return pattern.length ? pattern : null;
}

async function executeCacheStrategy(strategy, request, fallbackPath = null) {
  switch (strategy) {
    case "cache-first":
      return cacheFirst(request, fallbackPath);

    case "stale-while-revalidate":
      return staleWhileRevalidate(request, fallbackPath);

    case "network-first":
    default:
      return networkFirst(request, fallbackPath);
  }
}

async function findQueuedRequestByFingerprint(fingerprint, tag) {
  if (!fingerprint) return null;

  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_SYNC, "readonly");
    const store = tx.objectStore(STORE_SYNC);
    const index = store.index("fingerprint");
    const request = index.getAll(fingerprint);

    request.onsuccess = () => {
      db.close();

      const items = Array.isArray(request.result) ? request.result : [];
      const now = Date.now();

      const found = items.find((item) => {
        if (item.tag !== tag) return false;
        if (item.expiresAt && item.expiresAt < now) return false;
        return true;
      }) || null;

      resolve(found);
    };

    request.onerror = () => {
      db.close();
      reject(request.error);
    };
  });
}

async function flushQueuedRequests(tag = DEFAULT_SYNC_TAG) {
  let queue = [];

  try {
    queue = await getQueuedRequests(tag);
  } catch (_error) {
    return 0;
  }

  let flushed = 0;
  const now = Date.now();

  queue.sort((a, b) => {
    const aNext = a.nextAttemptAt || a.createdAt || 0;
    const bNext = b.nextAttemptAt || b.createdAt || 0;
    return aNext - bNext;
  });

  for (const item of queue) {
    if (item.expiresAt && item.expiresAt < now) {
      await deleteQueuedRequest(item.id).catch(() => false);
      continue;
    }

    if (item.nextAttemptAt && item.nextAttemptAt > now) {
      continue;
    }

    const delivered = await replayQueuedRequest(item);

    if (delivered) {
      await deleteQueuedRequest(item.id).catch(() => false);
      flushed += 1;
      continue;
    }

    await markQueuedRequestFailed(item).catch(() => false);
  }

  if (flushed > 0) {
    await broadcastMessage("PAIR_SW_SYNC_FLUSHED", { tag, flushed });
  }

  return flushed;
}

async function getCacheMeta(url) {
  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_CACHE_META, "readonly");
    const store = tx.objectStore(STORE_CACHE_META);
    const request = store.get(url);

    request.onsuccess = () => {
      db.close();

      const row = request.result;
      resolve(row && typeof row.cachedAt === "number" ? row.cachedAt : null);
    };

    request.onerror = () => {
      db.close();
      reject(request.error);
    };
  });
}

async function getQueuedRequests(tag = DEFAULT_SYNC_TAG) {
  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_SYNC, "readonly");
    const store = tx.objectStore(STORE_SYNC);
    const request = store.getAll();

    request.onsuccess = () => {
      db.close();

      const all = Array.isArray(request.result) ? request.result : [];
      resolve(all.filter((item) => item.tag === tag));
    };

    request.onerror = () => {
      db.close();
      reject(request.error);
    };
  });
}

function headersToObject(headers) {
  const out = {};

  if (!headers || typeof headers.forEach !== "function") {
    return out;
  }

  headers.forEach((value, key) => {
    out[key] = value;
  });

  return out;
}

function isCacheableResponse(response) {
  if (!response) return false;

  return response.ok || response.type === "opaque";
}

function isSyncTag(tag) {
  return typeof tag === "string" && tag.length > 0;
}

async function markQueuedRequestFailed(item) {
  const attempts = (item.attempts || 0) + 1;

  if (attempts >= SYNC_CONFIG.maxAttempts) {
    await deleteQueuedRequest(item.id);
    return false;
  }

  const retryDelayMs = nextRetryDelayMs(attempts);

  item.attempts = attempts;
  item.nextAttemptAt = Date.now() + retryDelayMs;
  item.updatedAt = Date.now();

  await updateQueuedRequest(item);
  return true;
}

async function networkFirst(request, fallbackPath = null) {
  try {
    const response = await fetch(request);
    await cacheRuntimeResponse(request, response);
    return response;
  } catch (_error) {
    const cached = await readRuntimeCache(request, CACHE_CONFIG.maxRuntimeAgeSeconds);
    if (cached) return cached;

    return offlineResponse(fallbackPath);
  }
}

async function networkWithQueue(request) {
  try {
    return await fetch(request.clone());
  } catch (_error) {
    const queued = await queueRequestFromRequest(request, DEFAULT_SYNC_TAG).catch(() => false);

    if (queued) {
      await registerSync(DEFAULT_SYNC_TAG).catch(() => false);

      await broadcastMessage("PAIR_SW_SYNC_QUEUED", {
        url: request.url,
        method: request.method,
        tag: DEFAULT_SYNC_TAG,
      });
    }

    return acceptedQueueResponse();
  }
}

function nextRetryDelayMs(attempt) {
  const delays = SYNC_CONFIG.retryDelaysSeconds;
  const index = Math.min(Math.max(attempt - 1, 0), delays.length - 1);
  const seconds = Number(delays[index] || 30);

  return Math.max(1, seconds) * 1000;
}

function normalizeBodyForReplay(body, headers) {
  if (body == null) return null;

  if (typeof body === "string") {
    return body;
  }

  if (typeof body === "object") {
    if (!headers["Content-Type"] && !headers["content-type"]) {
      headers["Content-Type"] = "application/json";
    }

    try {
      return JSON.stringify(body);
    } catch (_error) {
      return null;
    }
  }

  return String(body);
}

function normalizePayloadBody(payload, headers) {
  if (!payload || payload.body == null) {
    return null;
  }

  if (typeof payload.body === "string") {
    return payload.body;
  }

  if (typeof payload.body === "object") {
    if (!headers["content-type"] && !headers["Content-Type"]) {
      headers["content-type"] = "application/json";
    }

    try {
      return JSON.stringify(payload.body);
    } catch (_error) {
      return null;
    }
  }

  return String(payload.body);
}

function normalizeStrategy(value, fallback) {
  const allowed = ["network-first", "cache-first", "stale-while-revalidate"];
  const normalized = String(value || "").trim().toLowerCase();

  return allowed.includes(normalized) ? normalized : fallback;
}

function normalizeApplyTo(value) {
  const allowed = ["all", "navigate", "api", "asset"];
  const normalized = String(value || "").trim().toLowerCase();

  return allowed.includes(normalized) ? normalized : "all";
}

function normalizeCacheConfig(config) {
  return {
    pageStrategy: normalizeStrategy(config.pageStrategy, "network-first"),
    apiStrategy: normalizeStrategy(config.apiStrategy, "network-first"),
    assetStrategy: normalizeStrategy(config.assetStrategy, "stale-while-revalidate"),
    maxRuntimeEntries: normalizeNumber(config.maxRuntimeEntries, 300, 10, 5000),
    maxRuntimeAgeSeconds: normalizeNumber(config.maxRuntimeAgeSeconds, 604800, 0, 31536000),
    rules: normalizeCacheRules(config.rules),
  };
}

function normalizeCacheRules(rules) {
  if (!Array.isArray(rules)) return [];

  const normalized = [];

  for (const rule of rules) {
    if (!rule || typeof rule !== "object") continue;

    const strategy = normalizeStrategy(rule.strategy, "network-first");
    const applyTo = normalizeApplyTo(rule.applyTo);
    const prefix = typeof rule.prefix === "string" ? rule.prefix.trim() : "";
    const regexString = typeof rule.regex === "string" ? rule.regex.trim() : "";

    let regex = null;

    if (!prefix && regexString) {
      try {
        regex = new RegExp(regexString);
      } catch (_error) {
        regex = null;
      }
    }

    if (!prefix && !regex) continue;

    normalized.push({ prefix, regex, strategy, applyTo });
  }

  return normalized;
}

function normalizeNumber(value, fallback, min, max) {
  const parsed = Number(value);

  if (!Number.isFinite(parsed)) {
    return fallback;
  }

  const rounded = Math.round(parsed);

  if (rounded < min) return min;
  if (rounded > max) return max;

  return rounded;
}

function normalizeSyncConfig(config) {
  return {
    enabled: config.enabled !== false,
    maxQueueEntries: normalizeNumber(config.maxQueueEntries, 250, 10, 5000),
    maxBodyBytes: normalizeNumber(config.maxBodyBytes, 262144, 1024, 2097152),
    maxAgeSeconds: normalizeNumber(config.maxAgeSeconds, 86400, 60, 604800),
    maxAttempts: normalizeNumber(config.maxAttempts, 5, 1, 20),
    retryDelaysSeconds: normalizeRetryDelays(config.retryDelaysSeconds),
  };
}

function normalizePushConfig(config) {
  const defaultVibrate = normalizeVibratePattern(config.vibrate);

  return {
    defaultTitle: normalizeOptionalString(config.defaultTitle) || "Notification",
    icon: normalizeOptionalString(config.icon),
    badge: normalizeOptionalString(config.badge),
    image: normalizeOptionalString(config.image),
    requireInteraction: typeof config.requireInteraction === "boolean" ? config.requireInteraction : undefined,
    renotify: typeof config.renotify === "boolean" ? config.renotify : undefined,
    silent: typeof config.silent === "boolean" ? config.silent : undefined,
    vibrate: defaultVibrate || undefined,
  };
}

function normalizeRetryDelays(delays) {
  if (!Array.isArray(delays) || !delays.length) {
    return [30, 120, 600, 1800, 3600];
  }

  const normalized = delays
    .map((item) => Number(item))
    .filter((item) => Number.isFinite(item) && item > 0)
    .map((item) => Math.round(item));

  if (!normalized.length) {
    return [30, 120, 600, 1800, 3600];
  }

  return normalized;
}

function parsePwaOptions(encoded) {
  const decoded = decodeBase64Url(encoded || "");
  if (!decoded) return {};

  try {
    const parsed = JSON.parse(decoded);
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch (_error) {
    return {};
  }
}

async function openStorageDatabase() {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === "undefined") {
      reject(new Error("IndexedDB is not available in this browser."));
      return;
    }

    const request = indexedDB.open(STORAGE_DB, STORAGE_DB_VERSION);

    request.onupgradeneeded = () => {
      const db = request.result;
      const tx = request.transaction;

      let syncStore;

      if (!db.objectStoreNames.contains(STORE_SYNC)) {
        syncStore = db.createObjectStore(STORE_SYNC, { keyPath: "id", autoIncrement: true });
      } else if (tx) {
        syncStore = tx.objectStore(STORE_SYNC);
      }

      if (syncStore) {
        if (!syncStore.indexNames.contains("tag")) {
          syncStore.createIndex("tag", "tag", { unique: false });
        }

        if (!syncStore.indexNames.contains("fingerprint")) {
          syncStore.createIndex("fingerprint", "fingerprint", { unique: false });
        }
      }

      if (!db.objectStoreNames.contains(STORE_CACHE_META)) {
        const cacheStore = db.createObjectStore(STORE_CACHE_META, { keyPath: "url" });
        cacheStore.createIndex("cachedAt", "cachedAt", { unique: false });
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function offlineResponse(fallbackPath = null) {
  if (fallbackPath) {
    const fallback = await caches.match(fallbackPath);
    if (fallback) return fallback;
  }

  return new Response("Offline", {
    status: 503,
    statusText: "Offline",
    headers: { "Content-Type": "text/plain" },
  });
}

async function preloadUrls(urls) {
  if (!urls.length) return;

  const cache = await caches.open(RUNTIME_CACHE);

  await Promise.all(urls.map(async (url) => {
    try {
      const request = new Request(url, { method: "GET", credentials: "same-origin" });
      const response = await fetch(request);

      if (isCacheableResponse(response)) {
        await cache.put(request, response.clone());
        await putCacheMeta(request.url, Date.now()).catch(() => false);
      }
    } catch (_error) {
      // ignore individual preload errors
    }
  }));

  await trimRuntimeCache(CACHE_CONFIG.maxRuntimeEntries).catch(() => false);
}

async function putCacheMeta(url, cachedAt) {
  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_CACHE_META, "readwrite");
    const store = tx.objectStore(STORE_CACHE_META);
    store.put({ url, cachedAt });

    tx.oncomplete = () => {
      db.close();
      resolve(true);
    };

    tx.onerror = () => {
      db.close();
      reject(tx.error);
    };
  });
}

async function putQueuedRequest(item) {
  const existing = await findQueuedRequestByFingerprint(item.fingerprint, item.tag).catch(() => null);

  if (existing) {
    existing.updatedAt = Date.now();
    existing.expiresAt = Math.max(existing.expiresAt || 0, item.expiresAt || 0);
    existing.body = item.body;
    existing.headers = item.headers;

    await updateQueuedRequest(existing);
    return true;
  }

  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_SYNC, "readwrite");
    const store = tx.objectStore(STORE_SYNC);
    store.add(item);

    tx.oncomplete = async () => {
      db.close();
      await trimQueueEntries(SYNC_CONFIG.maxQueueEntries).catch(() => false);
      resolve(true);
    };

    tx.onerror = () => {
      db.close();
      reject(tx.error);
    };
  });
}

async function queueRequestFromPayload(payload) {
  if (!payload || !payload.url) return false;

  const url = new URL(String(payload.url), self.location.origin);
  if (url.origin !== self.location.origin) return false;

  const method = String(payload.method || "POST").toUpperCase();
  const headers = typeof payload.headers === "object" && payload.headers ? payload.headers : {};
  const body = normalizePayloadBody(payload, headers);

  if (bodyByteLength(body) > SYNC_CONFIG.maxBodyBytes) {
    return false;
  }

  const fingerprint = hashString(`${method}|${url.href}|${body || ""}`);
  ensureIdempotencyHeader(headers, fingerprint);

  const now = Date.now();

  const item = {
    url: url.href,
    method,
    headers,
    body,
    credentials: "same-origin",
    createdAt: now,
    updatedAt: now,
    attempts: 0,
    nextAttemptAt: now,
    expiresAt: now + (SYNC_CONFIG.maxAgeSeconds * 1000),
    tag: String(payload.tag || DEFAULT_SYNC_TAG),
    fingerprint,
  };

  try {
    const queued = await putQueuedRequest(item);
    if (!queued) return false;

    await registerSync(item.tag);

    await broadcastMessage("PAIR_SW_SYNC_QUEUED", {
      url: item.url,
      method: item.method,
      tag: item.tag,
    });

    return true;
  } catch (_error) {
    return false;
  }
}

async function queueRequestFromRequest(request, tag = DEFAULT_SYNC_TAG) {
  const method = String(request.method || "POST").toUpperCase();
  const headers = headersToObject(request.headers);

  let body = null;

  if (method !== "GET" && method !== "HEAD") {
    try {
      body = await request.clone().text();
    } catch (_error) {
      body = null;
    }
  }

  if (bodyByteLength(body) > SYNC_CONFIG.maxBodyBytes) {
    return false;
  }

  const fingerprint = hashString(`${method}|${request.url}|${body || ""}`);
  ensureIdempotencyHeader(headers, fingerprint);

  const now = Date.now();

  const item = {
    url: request.url,
    method,
    headers,
    body,
    credentials: request.credentials || "same-origin",
    createdAt: now,
    updatedAt: now,
    attempts: 0,
    nextAttemptAt: now,
    expiresAt: now + (SYNC_CONFIG.maxAgeSeconds * 1000),
    tag,
    fingerprint,
  };

  await putQueuedRequest(item);
  return true;
}

async function readRuntimeCache(request, maxAgeSeconds = 0) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cached = await cache.match(request);

  if (!cached) return null;

  if (maxAgeSeconds > 0) {
    try {
      const cachedAt = await getCacheMeta(request.url);

      if (cachedAt && Date.now() - cachedAt > (maxAgeSeconds * 1000)) {
        await cache.delete(request);
        await deleteCacheMeta(request.url).catch(() => false);
        return null;
      }
    } catch (_error) {
      // keep cached response when metadata is unavailable
    }
  }

  return cached;
}

async function registerSync(tag) {
  if (!self.registration || !("sync" in self.registration)) {
    return false;
  }

  try {
    await self.registration.sync.register(tag);
    return true;
  } catch (_error) {
    return false;
  }
}

async function replayQueuedRequest(item) {
  const method = String(item.method || "POST").toUpperCase();
  const headers = typeof item.headers === "object" && item.headers ? { ...item.headers } : {};

  headers["X-Pair-Replay"] = "1";

  if (!headers["Idempotency-Key"] && !headers["idempotency-key"]) {
    ensureIdempotencyHeader(headers, item.fingerprint || hashString(`${method}|${item.url}|${item.body || ""}`));
  }

  const init = {
    method,
    headers,
    credentials: item.credentials || "same-origin",
    cache: "no-store",
  };

  if (method !== "GET" && method !== "HEAD" && item.body != null) {
    init.body = normalizeBodyForReplay(item.body, headers);
  }

  try {
    const response = await fetch(item.url, init);

    if (response.ok) {
      return true;
    }

    // invalid requests should not stay forever in queue
    if (response.status >= 400 && response.status < 500) {
      return true;
    }

    return false;
  } catch (_error) {
    return false;
  }
}

function resolveCacheStrategy(request, url) {
  const type =
    request.mode === "navigate"
      ? "navigate"
      : (url.pathname.startsWith("/api/") ? "api" : "asset");

  for (const rule of CACHE_CONFIG.rules) {
    if (rule.applyTo !== "all" && rule.applyTo !== type) {
      continue;
    }

    if (rule.prefix && url.pathname.startsWith(rule.prefix)) {
      return rule.strategy;
    }

    if (rule.regex && rule.regex.test(url.pathname)) {
      return rule.strategy;
    }
  }

  if (type === "navigate") return CACHE_CONFIG.pageStrategy;
  if (type === "api") return CACHE_CONFIG.apiStrategy;

  return CACHE_CONFIG.assetStrategy;
}

function shouldQueueMutation(request) {
  if (!SYNC_CONFIG.enabled) {
    return false;
  }

  const method = String(request.method || "GET").toUpperCase();
  if (method === "GET" || method === "HEAD") return false;

  const headerValue = request.headers.get("X-Pair-Background-Sync");
  if (headerValue === "1" || headerValue === "true") {
    return true;
  }

  const url = new URL(request.url);
  return url.origin === self.location.origin && url.pathname.startsWith("/api/");
}

async function staleWhileRevalidate(request, fallbackPath = null) {
  const cached = await readRuntimeCache(request, CACHE_CONFIG.maxRuntimeAgeSeconds);

  const fetchPromise = fetch(request)
    .then(async (response) => {
      await cacheRuntimeResponse(request, response);
      return response;
    })
    .catch(() => null);

  if (cached) {
    fetchPromise.then(() => {});
    return cached;
  }

  const response = await fetchPromise;
  if (response) return response;

  return offlineResponse(fallbackPath);
}

function bodyByteLength(body) {
  if (body == null) return 0;

  let text;

  if (typeof body === "string") {
    text = body;
  } else if (typeof body === "object") {
    try {
      text = JSON.stringify(body);
    } catch (_error) {
      text = "";
    }
  } else {
    text = String(body);
  }

  if (typeof TextEncoder !== "undefined") {
    return new TextEncoder().encode(text).length;
  }

  return text.length;
}

function ensureIdempotencyHeader(headers, fingerprint) {
  if (!headers || typeof headers !== "object") return;

  if (headers["Idempotency-Key"] || headers["idempotency-key"]) {
    return;
  }

  headers["Idempotency-Key"] = `pair-${fingerprint}`;
}

function hashString(value) {
  let hash = 2166136261;

  for (let i = 0; i < value.length; i += 1) {
    hash ^= value.charCodeAt(i);
    hash +=
      (hash << 1) +
      (hash << 4) +
      (hash << 7) +
      (hash << 8) +
      (hash << 24);
  }

  return (hash >>> 0).toString(16);
}

async function trimQueueEntries(maxEntries) {
  if (!maxEntries || maxEntries < 1) return;

  const db = await openStorageDatabase();

  const all = await new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_SYNC, "readonly");
    const store = tx.objectStore(STORE_SYNC);
    const request = store.getAll();

    request.onsuccess = () => resolve(Array.isArray(request.result) ? request.result : []);
    request.onerror = () => reject(request.error);
  });

  db.close();

  if (all.length <= maxEntries) return;

  all.sort((a, b) => {
    const aTime = a.updatedAt || a.createdAt || 0;
    const bTime = b.updatedAt || b.createdAt || 0;
    return aTime - bTime;
  });

  const removeCount = all.length - maxEntries;

  for (let i = 0; i < removeCount; i += 1) {
    const item = all[i];
    if (!item || typeof item.id === "undefined") continue;

    await deleteQueuedRequest(item.id).catch(() => false);
  }
}

async function trimRuntimeCache(maxEntries) {
  if (!maxEntries || maxEntries < 1) return;

  const cache = await caches.open(RUNTIME_CACHE);
  const keys = await cache.keys();

  if (keys.length <= maxEntries) return;

  const removeCount = keys.length - maxEntries;

  for (let i = 0; i < removeCount; i += 1) {
    const key = keys[i];
    await cache.delete(key);
    await deleteCacheMeta(key.url).catch(() => false);
  }
}

async function updateQueuedRequest(item) {
  const db = await openStorageDatabase();

  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_SYNC, "readwrite");
    const store = tx.objectStore(STORE_SYNC);
    store.put(item);

    tx.oncomplete = () => {
      db.close();
      resolve(true);
    };

    tx.onerror = () => {
      db.close();
      reject(tx.error);
    };
  });
}
