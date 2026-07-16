Photo gallery (file-based)
==========================

Decision: photos live as files on disk, not in MySQL.

Why:
  - Extensive galleries are mostly binary data; MySQL is a poor fit for image blobs
  - The web server serves JPG/WebP efficiently with caching
  - Deploys stay simple (same git push flow as suzieq)
  - Albums are just folders — easy to add hundreds of photos

Layout
------
  gallery/albums/<album-slug>/
    album.json     optional title, description, per-file captions
    photo-01.jpg
    photo-02.webp
    ...

Supported extensions: jpg, jpeg, png, webp, gif

album.json example
------------------
{
  "title": "Pacific Northwest",
  "description": "Trips around Washington and Oregon.",
  "captions": {
    "rainier-sunrise.jpg": "Sunrise on Mount Rainier",
    "ferry-crossing.jpg": "Crossing to Bainbridge"
  }
}

How the site loads them
-----------------------
  gallery-api.php scans gallery/albums/ and returns JSON.
  gallery.html + gallery.js render the grid and lightbox.

Adding photos
-------------
1. Create a folder under gallery/albums/ (e.g. travel-2024)
2. Drop image files in
3. Optionally add album.json
4. Commit, then run .\deploy.ps1 (or push to GitHub — Actions can publish)

Tips for large libraries
------------------------
  - Prefer WebP or reasonably sized JPEG (long edge ~1600–2400px for web)
  - Keep originals elsewhere; publish web-sized copies here
  - GitHub soft-warns around large files; very large binaries may need Git LFS later
