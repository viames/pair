(function (global) {
  "use strict";

  class PairDevice {
    static attachStream(videoElement, stream, options = {}) {
      if (!videoElement) {
        throw new Error("A video element is required.");
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

    static getCurrentPosition(options = {}) {
      if (!this.supports.geolocation) {
        return Promise.reject(new Error("Geolocation API is not supported in this browser."));
      }

      return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
      });
    }

    static async openCamera(constraints = { video: true, audio: false }) {
      if (!this.supports.camera) {
        throw new Error("Camera API is not supported in this browser.");
      }

      return navigator.mediaDevices.getUserMedia(constraints);
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
        throw new Error("Web Bluetooth is not supported in this browser.");
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
