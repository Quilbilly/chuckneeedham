Photo gallery
=============

Albums = folders under gallery/albums/<album-slug>/
Each album can have album.json (title, description, captions).

Public pages
------------
  gallery.html          album cards → photo grid → lightbox
  gallery-api.php       JSON catalog
  gallery-meta.php      EXIF / file info for the Info button

Admin
-----
  https://chuckneedham.com/admin.php

  Gallery tab: captions, delete photo, delete album
  Feedback tab: read / delete public feedback

  Password (first visit) → ~/private/chuck-gallery/config.php
  Feedback data → ~/private/chuck-feedback/messages.json

  Deletes affect the live site only. Tell Cursor to remove the same
  paths from GitHub so the next publish does not restore them.

Adding photos
-------------
1. Create gallery/albums/my-trip/
2. Drop jpg / jpeg / png / webp / gif files in
3. Ask Cursor to publish (GitHub backup + live deploy)

Info button
-----------
In the lightbox, Info loads dimensions, file size, and EXIF when present
(camera, lens, exposure, GPS, etc.).
