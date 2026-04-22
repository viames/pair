/* PairLogBar - dependency-free controls for Pair request inspector. */

(function (global) {
  "use strict";

  /**
   * Read a plain cookie value by name.
   * @param {string} name
   * @returns {string}
   */
  function readCookie(name) {
    const prefix = encodeURIComponent(name) + "=";
    const parts = document.cookie ? document.cookie.split("; ") : [];

    for (const part of parts) {
      if (part.indexOf(prefix) === 0) {
        return decodeURIComponent(part.slice(prefix.length));
      }
    }

    return "";
  }

  /**
   * Store a small LogBar preference cookie.
   * @param {string} name
   * @param {string} value
   */
  function writeCookie(name, value) {
    document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + "; path=/; SameSite=Lax";
  }

  /**
   * Return the closest LogBar root for a child element.
   * @param {Element} element
   * @returns {?Element}
   */
  function logbarRoot(element) {
    return element && typeof element.closest === "function" ? element.closest("#logbar[data-logbar-root]") : null;
  }

  /**
   * Apply the selected tab to one LogBar instance.
   * @param {Element} logbar
   * @param {string} tabName
   */
  function setActiveTab(logbar, tabName) {
    logbar.querySelectorAll("[data-logbar-tab]").forEach(function (pane) {
      pane.hidden = pane.getAttribute("data-logbar-tab") !== tabName;
    });

    logbar.querySelectorAll("[data-logbar-tab-button]").forEach(function (button) {
      const active = button.getAttribute("data-logbar-tab-button") === tabName;

      button.classList.toggle("active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
    });
  }

  /**
   * Apply text, type, and query filters to visible rows.
   * @param {Element} logbar
   */
  function applyFilters(logbar) {
    const searchControl = logbar.querySelector("[data-logbar-search]");
    const typeControl = logbar.querySelector("[data-logbar-type-filter]");
    const queriesOnlyControl = logbar.querySelector("[data-logbar-queries-only]");
    const warningsOnlyControl = logbar.querySelector("[data-logbar-warnings-only]");
    const duplicatesOnlyControl = logbar.querySelector("[data-logbar-duplicates-only]");
    const body = logbar.querySelector(".logbar-body");
    const search = ((searchControl && searchControl.value) || "").toLowerCase();
    const type = (typeControl && typeControl.value) || "";
    const queriesOnly = !!(queriesOnlyControl && queriesOnlyControl.checked);
    const warningsOnly = !!(warningsOnlyControl && warningsOnlyControl.checked);
    const duplicatesOnly = !!(duplicatesOnlyControl && duplicatesOnlyControl.checked);
    const queryRowsVisible = !body || body.classList.contains("logbar-show-queries") || body.classList.contains("show-queries");

    logbar.querySelectorAll("[data-logbar-row]").forEach(function (row) {
      const rowType = row.getAttribute("data-logbar-type") || "";
      const text = (row.getAttribute("data-logbar-text") || "").toLowerCase();
      let hidden = false;

      if (search && text.indexOf(search) === -1) hidden = true;
      if (type && rowType !== type) hidden = true;
      if (queriesOnly && rowType !== "query") hidden = true;
      if (warningsOnly && rowType !== "warning" && rowType !== "error") hidden = true;
      if (!queryRowsVisible && rowType === "query") hidden = true;

      row.hidden = hidden;
    });

    logbar.querySelectorAll("[data-logbar-query-group]").forEach(function (group) {
      const text = (group.getAttribute("data-logbar-text") || "").toLowerCase();
      let hidden = false;

      if (search && text.indexOf(search) === -1) hidden = true;
      if (type && type !== "query") hidden = true;
      if (warningsOnly) hidden = true;
      if (duplicatesOnly && group.getAttribute("data-logbar-duplicate") !== "1") hidden = true;

      group.hidden = hidden;
    });
  }

  /**
   * Toggle one LogBar body and persist the visibility cookie.
   * @param {Element} logbar
   */
  function toggleEvents(logbar) {
    const body = logbar.querySelector(".logbar-body");
    const toggle = logbar.querySelector("#toggle-events");

    if (!body || !toggle) return;

    const isHidden = body.classList.toggle("hidden");

    toggle.classList.toggle("expanded", !isHidden);
    toggle.setAttribute("aria-expanded", isHidden ? "false" : "true");
    toggle.textContent = isHidden ? "Show details" : "Hide details";
    writeCookie("LogBarShowEvents", isHidden ? "0" : "1");
  }

  /**
   * Toggle query rows and persist the query visibility cookie.
   * @param {Element} logbar
   */
  function toggleQueries(logbar) {
    const body = logbar.querySelector(".logbar-body");
    const queryToggle = logbar.querySelector("[data-logbar-query-toggle]");

    if (!body || !queryToggle) return;

    const showQueries = body.classList.toggle("logbar-show-queries");

    // Keep the legacy class in sync for older injected AJAX rows and custom themes.
    body.classList.toggle("show-queries", showQueries);
    queryToggle.classList.toggle("active", showQueries);
    writeCookie("LogBarShowQueries", showQueries ? "1" : "0");
    applyFilters(logbar);
  }

  /**
   * Initialize one rendered LogBar without binding per-node listeners.
   * @param {Element} logbar
   */
  function initLogBar(logbar) {
    if (logbar.getAttribute("data-logbar-ready") === "1") {
      applyFilters(logbar);
      return;
    }

    const body = logbar.querySelector(".logbar-body");
    const toggle = logbar.querySelector("#toggle-events");
    const queryToggle = logbar.querySelector("[data-logbar-query-toggle]");

    logbar.setAttribute("data-logbar-ready", "1");

    if (!readCookie("LogBarShowEvents") && toggle && body) {
      toggle.textContent = body.classList.contains("hidden") ? "Show details" : "Hide details";
      toggle.setAttribute("aria-expanded", body.classList.contains("hidden") ? "false" : "true");
    }

    if (body && queryToggle) {
      const showQueries = body.classList.contains("logbar-show-queries") || body.classList.contains("show-queries");

      body.classList.toggle("logbar-show-queries", showQueries);
      body.classList.toggle("show-queries", showQueries);
      queryToggle.classList.toggle("active", showQueries);
    }

    setActiveTab(logbar, "overview");
    applyFilters(logbar);
  }

  /**
   * Initialize every LogBar currently available in the document.
   */
  function initAll() {
    document.querySelectorAll("#logbar[data-logbar-root]").forEach(initLogBar);
  }

  /**
   * Handle delegated LogBar clicks.
   * @param {MouseEvent} event
   */
  function handleClick(event) {
    const target = event.target && typeof event.target.closest === "function" ? event.target.closest("#toggle-events, [data-logbar-tab-button], [data-logbar-query-toggle]") : null;
    const logbar = logbarRoot(target);

    if (!target || !logbar) return;

    if (target.id === "toggle-events") {
      event.preventDefault();
      toggleEvents(logbar);
      return;
    }

    if (target.hasAttribute("data-logbar-query-toggle")) {
      event.preventDefault();
      toggleQueries(logbar);
      return;
    }

    if (target.hasAttribute("data-logbar-tab-button")) {
      event.preventDefault();
      setActiveTab(logbar, target.getAttribute("data-logbar-tab-button") || "overview");
      applyFilters(logbar);
    }
  }

  /**
   * Handle delegated filter changes and text search input.
   * @param {Event} event
   */
  function handleFilterEvent(event) {
    const target = event.target && typeof event.target.closest === "function" ? event.target.closest("[data-logbar-search], [data-logbar-type-filter], [data-logbar-queries-only], [data-logbar-warnings-only], [data-logbar-duplicates-only]") : null;
    const logbar = logbarRoot(target);

    if (!target || !logbar) return;

    applyFilters(logbar);
  }

  document.addEventListener("click", handleClick);
  document.addEventListener("input", handleFilterEvent);
  document.addEventListener("change", handleFilterEvent);
  window.addEventListener("pair:router:navigated", initAll);

  global.PairLogBar = {
    initAll: initAll
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }
})(window);
