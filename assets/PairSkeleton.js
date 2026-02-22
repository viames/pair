(function (global) {
  "use strict";

  class PairSkeleton {
    static autoBind({
      startEvents = ["pair:router:loading", "pair:pwa:background-refresh-start"],
      stopEvents = ["pair:router:navigated", "pair:pwa:background-refresh-success", "pair:pwa:background-refresh-error"],
      withStyles = true,
      root = document,
    } = {}) {
      if (withStyles) {
        this.ensureStyles();
      }

      for (const ev of startEvents) {
        window.addEventListener(ev, () => this.show(root));
      }

      for (const ev of stopEvents) {
        window.addEventListener(ev, () => this.hide(root));
      }
    }

    static defaultCss() {
      return [
        "[data-skeleton]{position:relative;overflow:hidden;background:#f1f3f5;color:transparent;}",
        "[data-skeleton].pair-skeleton-active::after{",
        "content:\"\";position:absolute;inset:0;transform:translateX(-100%);",
        "background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);",
        "animation:pair-skeleton-shimmer 1.2s infinite;}",
        "@keyframes pair-skeleton-shimmer{100%{transform:translateX(100%);}}",
        "@media (prefers-reduced-motion: reduce){[data-skeleton].pair-skeleton-active::after{animation:none;}}",
      ].join("\n");
    }

    static ensureStyles(cssText = null) {
      if (document.getElementById("pair-skeleton-style")) return;

      const style = document.createElement("style");
      style.id = "pair-skeleton-style";
      style.textContent = cssText || this.defaultCss();
      document.head.appendChild(style);
    }

    static hide(root = document) {
      this._each(root, (el) => {
        el.removeAttribute("aria-busy");
        el.removeAttribute("data-skeleton-active");
        el.classList.remove("pair-skeleton-active");
      });
    }

    static show(root = document) {
      this._each(root, (el) => {
        el.setAttribute("aria-busy", "true");
        el.setAttribute("data-skeleton-active", "true");
        el.classList.add("pair-skeleton-active");
      });
    }

    static wrapPromise(promise, root = document) {
      this.show(root);
      return Promise.resolve(promise).finally(() => this.hide(root));
    }

    static _each(root, callback) {
      const nodes = Array.from(root.querySelectorAll("[data-skeleton]"));
      for (const el of nodes) {
        callback(el);
      }
    }
  }

  PairSkeleton.version = "0.2.0";

  global.Pair = global.Pair || {};
  global.Pair.Skeleton = PairSkeleton;
  global.PairSkeleton = PairSkeleton;
})(window);
