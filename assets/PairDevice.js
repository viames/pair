(function (global) {
  "use strict";

  class PairDevice {
    /**
     * Attach a MediaStream to a video element and start playback.
     */
    static attachStream(videoElement, stream, options = {}) {
      if (!videoElement) {
        throw new Error(this.message("VIDEO_ELEMENT_REQUIRED", "A video element is required."));
      }

      const autoplay = options.autoplay !== false;
      const muted = options.muted !== false;
      const playsInline = options.playsInline !== false;

      videoElement.srcObject = stream;
      videoElement.autoplay = autoplay;
      videoElement.muted = muted;
      videoElement.playsInline = playsInline;

      return videoElement.play().catch(() => undefined);
    }

    /**
     * Return the current browser geolocation position.
     */
    static getCurrentPosition(options = {}) {
      if (!this.supports.geolocation) {
        return Promise.reject(new Error(this.message("GEOLOCATION_UNSUPPORTED", "The Geolocation API is not supported by this browser.")));
      }

      return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
      });
    }

    /**
     * Open the camera through getUserMedia.
     */
    static async openCamera(constraints = { video: true, audio: false }) {
      if (!this.supports.camera) {
        throw new Error(this.message("CAMERA_UNSUPPORTED", "The Camera API is not supported by this browser."));
      }

      return navigator.mediaDevices.getUserMedia(constraints);
    }

    /**
     * Return a translated client message from server-injected PairMessages.
     */
    static message(key, fallback) {
      const messages = global.PairMessages || {};
      const message = messages && typeof messages[key] === "string" ? messages[key].trim() : "";

      return message || fallback;
    }

    static async queryPermission(name) {
      if (!this.supports.permissions) {
        return "unsupported";
      }

      try {
        const result = await navigator.permissions.query({ name });
        return result.state;
      } catch (_error) {
        return "unknown";
      }
    }

    static async requestBluetooth(options = { acceptAllDevices: true }) {
      if (!this.supports.bluetooth) {
        throw new Error(this.message("WEB_BLUETOOTH_UNSUPPORTED", "The Web Bluetooth API is not supported by this browser."));
      }

      return navigator.bluetooth.requestDevice(options);
    }

    static stopCamera(stream) {
      if (!stream || !stream.getTracks) return;

      for (const track of stream.getTracks()) {
        track.stop();
      }
    }

    static stopStreamFromVideo(videoElement) {
      if (!videoElement || !videoElement.srcObject) return;

      this.stopCamera(videoElement.srcObject);
      videoElement.srcObject = null;
    }

    static vibrate(pattern = 100) {
      if (!this.supports.vibration) return false;
      return navigator.vibrate(pattern);
    }

    static async watchPermission(name, callback) {
      if (!this.supports.permissions) {
        return null;
      }

      const status = await navigator.permissions.query({ name });
      if (typeof callback === "function") {
        callback(status.state);
      }

      const listener = () => {
        if (typeof callback === "function") {
          callback(status.state);
        }
      };

      status.addEventListener("change", listener);

      return () => {
        status.removeEventListener("change", listener);
      };
    }
  }

  PairDevice.version = "0.2.0";
  PairDevice.supports = {
    camera: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
    bluetooth: !!navigator.bluetooth,
    geolocation: !!navigator.geolocation,
    permissions: !!navigator.permissions,
    vibration: !!navigator.vibrate,
  };

  global.Pair = global.Pair || {};
  global.Pair.Device = PairDevice;
  global.PairDevice = PairDevice;
})(window);
