import path from "node:path";
import { fileURLToPath } from "node:url";
import dotenv from "dotenv";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "..");

dotenv.config({ path: path.join(root, ".env") });

function int(name, fallback) {
  const raw = process.env[name];
  if (raw === undefined || raw === "") return fallback;
  const n = Number.parseInt(raw, 10);
  return Number.isFinite(n) ? n : fallback;
}

function list(name, fallback = []) {
  const raw = process.env[name];
  if (!raw) return fallback;
  return raw.split(",").map((s) => s.trim()).filter(Boolean);
}

export const config = {
  root,
  port: int("PORT", 8787),
  host: process.env.HOST || "0.0.0.0",
  publicBaseUrl: (process.env.PUBLIC_BASE_URL || "http://localhost:8787").replace(/\/$/, ""),
  cameraProvider: process.env.CAMERA_PROVIDER || "mock",
  storageProvider: process.env.STORAGE_PROVIDER || "local",
  emailTransport: process.env.EMAIL_TRANSPORT || "json",
  smtp: {
    host: process.env.SMTP_HOST || "",
    port: int("SMTP_PORT", 587),
    user: process.env.SMTP_USER || "",
    pass: process.env.SMTP_PASS || "",
  },
  emailFrom: process.env.EMAIL_FROM || "ClickIt <noreply@example.com>",
  adminTokens: list("ADMIN_TOKENS", ["dev-admin-token"]),
  downloadLinkHours: int("DOWNLOAD_LINK_HOURS", 168),
  paths: {
    data: path.join(root, "data"),
    sessions: path.join(root, "data", "sessions"),
    uploads: path.join(root, "data", "uploads"),
    tmp: path.join(root, "data", "tmp"),
    settings: path.join(root, "data", "settings.json"),
    booth: path.join(root, "booth"),
    public: path.join(root, "public"),
  },
  defaults: {
    countdownSeconds: int("DEFAULT_COUNTDOWN_SECONDS", 3),
    photoCount: int("DEFAULT_PHOTO_COUNT", 3),
    intervalMs: int("DEFAULT_INTERVAL_MS", 1500),
    eventName: process.env.EVENT_NAME || "ClickIt Event",
    brandAccent: "#F5A623",
    allowRetake: true,
    requireEmailConsent: true,
    attractTagline: "Step up. Smile. Walk away with your photos.",
  },
};
