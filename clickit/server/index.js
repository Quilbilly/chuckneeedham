import express from "express";
import cors from "cors";
import path from "node:path";
import { config } from "./config.js";
import { initStore, getSessionByToken } from "./services/store.js";
import { getSettings } from "./settings.js";
import healthRoutes from "./routes/health.js";
import boothRoutes from "./routes/booth.js";
import adminRoutes from "./routes/admin.js";
import downloadRoutes from "./routes/download.js";
import { adminAuth } from "./middleware/adminAuth.js";

await initStore();

const app = express();
app.use(cors());
app.use(express.json({ limit: "2mb" }));

app.use("/api", healthRoutes);
app.use("/api", boothRoutes);
app.use("/api/admin", adminAuth, adminRoutes);
app.use("/api", downloadRoutes);

app.use("/booth", express.static(config.paths.booth));
app.use("/public", express.static(config.paths.public));

app.get("/", (_req, res) => {
  res.redirect("/booth/");
});

app.get("/admin", (_req, res) => {
  res.sendFile(path.join(config.paths.booth, "admin.html"));
});

app.get("/d/:token", async (req, res) => {
  const session = await getSessionByToken(req.params.token);
  if (!session) {
    res.status(404).sendFile(path.join(config.paths.public, "download", "missing.html"));
    return;
  }
  if (session.expiresAt && new Date(session.expiresAt).getTime() < Date.now()) {
    res.status(410).sendFile(path.join(config.paths.public, "download", "expired.html"));
    return;
  }
  res.sendFile(path.join(config.paths.public, "download", "index.html"));
});

app.get("/api/meta", async (_req, res) => {
  const settings = await getSettings();
  res.json({
    name: "ClickIt",
    eventName: settings.eventName,
  });
});

app.use((err, _req, res, _next) => {
  console.error(err);
  res.status(500).json({ error: err.message || "Server error" });
});

app.listen(config.port, config.host, () => {
  console.log(`ClickIt listening on http://${config.host}:${config.port}`);
  console.log(`Booth UI: ${config.publicBaseUrl}/booth/`);
  console.log(`Admin UI: ${config.publicBaseUrl}/admin`);
  console.log(`Camera provider: ${config.cameraProvider}`);
});
