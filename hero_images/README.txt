Hero background photos
======================

Drop image files in this folder. The home page hero rotates through them.

Supported: jpg, jpeg, png, webp

How it works
------------
  hero-api.php scans this folder and returns JSON.
  hero.js fetches that list and crossfades full-bleed backgrounds.
  No rebuild step — add files, deploy, done.

Adding photos
-------------
1. Copy web-sized images into hero_images/ (long edge ~1600–2400px is plenty)
2. Prefer JPEG or WebP; keep each file reasonably sized for a homepage
3. Commit, then publish when ready (.\deploy.ps1)

Notes
-----
  - Filenames sort naturally (case-insensitive)
  - prefers-reduced-motion: visitors see the first image only
  - This folder is included in ICDSoft sparse checkout (same as gallery/)
  - Gallery albums stay under gallery/albums/ — do not mix the two
