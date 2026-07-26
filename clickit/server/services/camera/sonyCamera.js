/**
 * Sony ILCE-7RM5 provider stub.
 *
 * Intended integration path:
 * - Sony Camera Remote SDK (C/C++) via a native addon or sidecar process
 * - USB tether, live view frames, still capture callbacks
 *
 * Until the SDK bridge is installed, this provider reports disconnected and
 * explains how to enable it.
 */
export function createSonyCamera() {
  let connected = false;

  return {
    id: "sony",
    label: "Sony ILCE-7RM5",

    async getStatus() {
      return {
        provider: "sony",
        connected,
        capturing: false,
        model: "ILCE-7RM5",
        batteryPercent: null,
        message: connected
          ? "Ready"
          : "Sony Camera Remote SDK bridge not configured. Set CAMERA_PROVIDER=mock for local UI work, or install the SDK sidecar.",
      };
    },

    async connect() {
      // Placeholder: launch/ping SDK sidecar, claim USB device, start live view.
      connected = false;
      const err = new Error(
        "Sony provider is stubbed. Wire Sony Camera Remote SDK before using CAMERA_PROVIDER=sony."
      );
      err.code = "SONY_SDK_MISSING";
      throw err;
    },

    async disconnect() {
      connected = false;
      return this.getStatus();
    },

    async getLiveFrame() {
      const err = new Error("Sony live view unavailable (SDK not wired)");
      err.code = "SONY_SDK_MISSING";
      throw err;
    },

    async capturePhoto() {
      const err = new Error("Sony capture unavailable (SDK not wired)");
      err.code = "SONY_SDK_MISSING";
      throw err;
    },
  };
}
