/* PairLogBar - dependency-free controls for Pair request inspector. */

(function (global) {
  "use strict";

  /* Bootstrap 5 and Bulma expose stable named viewport ranges that the LogBar can show without framework JavaScript. */
  const BOOTSTRAP_BREAKPOINTS = [
    { name: "xs", min: 0 },
    { name: "sm", min: 576 },
    { name: "md", min: 768 },
    { name: "lg", min: 992 },
    { name: "xl", min: 1200 },
    { name: "xxl", min: 1400 }
  ];
  const BULMA_BREAKPOINTS = [
    { name: "mobile", min: 0 },
    { name: "tablet", min: 769 },
    { name: "desktop", min: 1024 },
    { name: "widescreen", min: 1216 },
    { name: "fullhd", min: 1408 }
  ];
  let breakpointFrame = 0;

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
   * Return the viewport width used for framework breakpoint detection.
   * @returns {number}
   */
  function viewportWidth() {
    const documentWidth = document.documentElement ? document.documentElement.clientWidth : 0;

    return Math.max(documentWidth || 0, global.innerWidth || 0);
  }

  /**
   * Resolve a breakpoint name from sorted min-width definitions.
   * @param {number} width
   * @param {Array<{name: string, min: number}>} breakpoints
   * @returns {string}
   */
  function breakpointForWidth(width, breakpoints) {
    let current = breakpoints[0] ? breakpoints[0].name : "";

    breakpoints.forEach(function (breakpoint) {
      if (width >= breakpoint.min) {
        current = breakpoint.name;
      }
    });

    return current;
  }

  /**
   * Return the active framework breakpoint name for the current LogBar UI.
   * @param {string} ui
   * @param {number} width
   * @returns {string}
   */
  function breakpointForUi(ui, width) {
    if (ui === "bootstrap") {
      return breakpointForWidth(width, BOOTSTRAP_BREAKPOINTS);
    }

    if (ui === "bulma") {
      return breakpointForWidth(width, BULMA_BREAKPOINTS);
    }

    return "";
  }

  /**
   * Update one rendered breakpoint context item from the current viewport.
   * @param {Element} logbar
   */
  function updateBreakpoint(logbar) {
    const metric = logbar.querySelector("[data-logbar-breakpoint]");
    const value = metric ? metric.querySelector(".logbar-context-value") : null;
    const breakpoint = metric ? breakpointForUi(logbar.getAttribute("data-logbar-ui") || "", viewportWidth()) : "";

    if (!metric || !value || !breakpoint) return;

    value.textContent = breakpoint;
    metric.setAttribute("data-logbar-current-breakpoint", breakpoint);
  }

  /**
   * Show short visual feedback after a copy action succeeds.
   * @param {Element} button
   */
  function showCopyFeedback(button) {
    button.classList.add("copied");
    global.setTimeout(function () {
      button.classList.remove("copied");
    }, 1200);
  }

  /**
   * Copy text with the Clipboard API and fall back for older local debug pages.
   * @param {string} value
   * @returns {Promise<void>}
   */
  function copyText(value) {
    if (global.navigator && global.navigator.clipboard && typeof global.navigator.clipboard.writeText === "function") {
      return global.navigator.clipboard.writeText(value);
    }

    return new Promise(function (resolve, reject) {
      const field = document.createElement("textarea");

      // The fallback needs a selectable element, but it should never affect LogBar layout.
      field.value = value;
      field.setAttribute("readonly", "");
      field.style.left = "-9999px";
      field.style.position = "fixed";
      document.body.appendChild(field);
      field.select();

      try {
        if (document.execCommand("copy")) {
          resolve();
        } else {
          reject(new Error("Copy command was not accepted."));
        }
      } catch (error) {
        reject(error);
      } finally {
        document.body.removeChild(field);
      }
    });
  }

  /**
   * Copy a runtime context value such as the request correlation ID.
   * @param {Element} button
   */
  function copyContextValue(button) {
    const value = button.getAttribute("data-logbar-copy-value") || "";

    if (!value) return;

    copyText(value).then(function () {
      showCopyFeedback(button);
    }).catch(function () {
      button.blur();
    });
  }

  /**
   * Update every LogBar breakpoint metric currently available in the document.
   */
  function updateAllBreakpoints() {
    document.querySelectorAll("#logbar[data-logbar-root]").forEach(updateBreakpoint);
  }

  /**
   * Schedule a single breakpoint refresh for resize bursts.
   */
  function scheduleBreakpointUpdate() {
    if (breakpointFrame) return;

    const refresh = function () {
      breakpointFrame = 0;
      updateAllBreakpoints();
    };

    breakpointFrame = global.requestAnimationFrame ? global.requestAnimationFrame(refresh) : global.setTimeout(refresh, 16);
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

    // Mirror the active tab on the root so CSS can adapt tab-specific controls.
    ["overview", "timeline", "queries", "events"].forEach(function (name) {
      logbar.classList.toggle("logbar-tab-" + name, name === tabName);
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
   * Open the first visible query group after a finding filters the query pane.
   * @param {Element} logbar
   */
  function openFirstVisibleQueryGroup(logbar) {
    const groups = logbar.querySelectorAll("[data-logbar-query-group]");

    for (const group of groups) {
      if (!group.hidden) {
        group.setAttribute("open", "");
        return;
      }
    }
  }

  /**
   * Apply one automatic finding as a tab and filter shortcut.
   * @param {Element} logbar
   * @param {Element} button
   */
  function applyFindingAction(logbar, button) {
    const tabName = button.getAttribute("data-logbar-finding-tab") || "overview";
    const safeTabName = logbar.querySelector('[data-logbar-tab="' + tabName + '"]') ? tabName : "overview";
    const searchControl = logbar.querySelector("[data-logbar-search]");
    const typeControl = logbar.querySelector("[data-logbar-type-filter]");
    const queriesOnlyControl = logbar.querySelector("[data-logbar-queries-only]");
    const warningsOnlyControl = logbar.querySelector("[data-logbar-warnings-only]");
    const duplicatesOnlyControl = logbar.querySelector("[data-logbar-duplicates-only]");

    setActiveTab(logbar, safeTabName);

    // Reset manual filters before applying the finding so old filters cannot hide the target rows.
    if (searchControl) searchControl.value = button.getAttribute("data-logbar-finding-search") || "";
    if (typeControl) typeControl.value = button.getAttribute("data-logbar-finding-type") || "";
    if (queriesOnlyControl) queriesOnlyControl.checked = button.getAttribute("data-logbar-finding-queries-only") === "1";
    if (warningsOnlyControl) warningsOnlyControl.checked = button.getAttribute("data-logbar-finding-warnings-only") === "1";
    if (duplicatesOnlyControl) duplicatesOnlyControl.checked = button.getAttribute("data-logbar-finding-duplicates-only") === "1";

    applyFilters(logbar);

    if (button.getAttribute("data-logbar-finding-open-query") === "1") {
      openFirstVisibleQueryGroup(logbar);
    }
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
    updateBreakpoint(logbar);
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
    const target = event.target && typeof event.target.closest === "function" ? event.target.closest("#toggle-events, [data-logbar-tab-button], [data-logbar-query-toggle], [data-logbar-copy-value], [data-logbar-finding-action]") : null;
    const logbar = logbarRoot(target);

    if (!target || !logbar) return;

    if (target.hasAttribute("data-logbar-finding-action")) {
      event.preventDefault();
      applyFindingAction(logbar, target);
      return;
    }

    if (target.hasAttribute("data-logbar-copy-value")) {
      event.preventDefault();
      copyContextValue(target);
      return;
    }

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
  window.addEventListener("resize", scheduleBreakpointUpdate);
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
