# ClickIt

Tethered photo booth for a computer paired with a **Sony ILCE-7RM5**.

Guests tap **Take my picture**, ClickIt counts down, captures a set of photos, uploads them, and emails a private download link.

## Quick start

```bash
cd clickit
cp .env.example .env
npm install
npm run dev
```

Open:

- Booth kiosk: [http://localhost:8787/booth/](http://localhost:8787/booth/)
- Admin: [http://localhost:8787/admin](http://localhost:8787/admin) (token `dev-admin-token`)

Default camera mode is **mock** so you can build UI/workflows without the Sony body attached.

## MVP included

- Attract screen → countdown → multi-shot capture → review/retake → email → download page
- Configurable countdown, photo count, interval, branding, consent
- Session store on disk + local photo storage
- Email delivery via JSON transport (logs message) or SMTP
- Admin settings, session list, resend email, basic stats
- Camera provider interface with **mock** + **Sony stub**

## Architecture

```
clickit/
  booth/                 # Kiosk + admin UI
  public/download/       # Guest download pages
  server/
    routes/              # booth, admin, download, health
    services/
      camera/            # mockCamera + sonyCamera stub
      email.js
      store.js
    settings.js
  data/                  # local sessions + uploads (gitignored)
```

Flow:

1. Booth UI creates a session
2. UI runs countdown, then asks API to capture
3. Camera provider returns JPEG frames (mock or future Sony SDK)
4. Photos saved under `data/uploads/<sessionId>/`
5. Guest submits email → ClickIt sends `/d/<token>` link
6. Download page serves the set until expiry

## Sony ILCE-7RM5

Set `CAMERA_PROVIDER=sony` only after the SDK bridge exists.

Planned integration:

- Sony Camera Remote SDK (USB tether)
- Sidecar or native addon exposing: connect, live view JPEG, still capture
- Map those calls onto `server/services/camera/sonyCamera.js`

Until then, keep `CAMERA_PROVIDER=mock`.

## Email

- `EMAIL_TRANSPORT=json` (default): prints/stores message JSON — great for local demos
- `EMAIL_TRANSPORT=smtp`: use `SMTP_*` vars and `EMAIL_FROM`

## Admin

Protect `/api/admin/*` with `ADMIN_TOKENS`. The admin UI stores the token in `localStorage` for the operator machine only.

## Next build slices

See [ROADMAP.md](./ROADMAP.md) for the full feature backlog (printer, offline queue, Electron kiosk shell, S3/R2, QR, overlays, etc.).
