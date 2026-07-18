Photo gallery
=============

Albums = folders under gallery/albums/<album-slug>/
Each album can have album.json (title, description, captions).

Public pages
------------
  gallery.html          album cards → photo grid → lightbox
  gallery-api.php       JSON catalog
  gallery-meta.php      EXIF / file info for the Info button

Admin (captions)
----------------
  https://chuckneedham.com/gallery-admin.php

  First visit: create a password (stored hashed in
  /home/chuckneedham/private/chuck-gallery/config.php — outside the web root).

  Captions / album titles saved by admin also live under
  /home/chuckneedham/private/chuck-gallery/albums/<slug>.json
  so a normal site deploy does not wipe them.

Adding photos
-------------
1. Create gallery/albums/my-trip/
2. Drop jpg / jpeg / png / webp / gif files in
3. Optionally add album.json, or use gallery-admin.php after deploy
4. Publish

Info button
-----------
In the lightbox, Info loads dimensions, file size, and EXIF when present
(camera, lens, exposure, GPS, etc.).
