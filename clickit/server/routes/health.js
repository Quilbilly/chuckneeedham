import { Router } from "express";
import { getCameraStatus } from "../services/camera/index.js";
import { getSettings } from "../settings.js";
import { config } from "../config.js";

const router = Router();

router.get("/health", async (_req, res) => {
  const [camera, settings] = await Promise.all([getCameraStatus(), getSettings()]);
  res.json({
    ok: true,
    app: "ClickIt",
    version: "0.1.0",
    camera,
    storage: config.storageProvider,
    email: config.emailTransport,
    eventName: settings.eventName,
  });
});

export default router;
