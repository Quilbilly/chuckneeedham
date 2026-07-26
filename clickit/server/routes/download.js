import { Router } from "express";
import fs from "node:fs/promises";
import path from "node:path";
import { getSessionByToken } from "../services/store.js";
import { photoFilePath } from "../services/store.js";

const router = Router();

router.get("/download/:token", async (req, res) => {
  const session = await getSessionByToken(req.params.token);
  if (!session) return res.status(404).json({ error: "Link not found" });
  if (session.expiresAt && new Date(session.expiresAt).getTime() < Date.now()) {
    return res.status(410).json({ error: "This download link has expired" });
  }

  res.json({
    eventPhotos: session.photos?.length || 0,
    expiresAt: session.expiresAt,
    photos: (session.photos || []).map((p) => ({
      filename: p.filename,
      index: p.index,
      url: `/api/download/${session.token}/photos/${encodeURIComponent(p.filename)}`,
    })),
  });
});

router.get("/download/:token/photos/:filename", async (req, res) => {
  const session = await getSessionByToken(req.params.token);
  if (!session) return res.status(404).json({ error: "Link not found" });
  if (session.expiresAt && new Date(session.expiresAt).getTime() < Date.now()) {
    return res.status(410).json({ error: "This download link has expired" });
  }

  const safe = path.basename(req.params.filename);
  const abs = photoFilePath(session.id, safe);
  try {
    await fs.access(abs);
    res.set("Content-Disposition", `attachment; filename="${safe}"`);
    return res.sendFile(abs);
  } catch {
    return res.status(404).json({ error: "Photo not found" });
  }
});

export default router;
