(function () {
  "use strict";

  var statusEl = document.getElementById("galleryStatus");
  var grid = document.getElementById("galleryGrid");
  var filters = document.getElementById("albumFilters");
  var lightbox = document.getElementById("lightbox");
  var lightboxImg = document.getElementById("lightboxImg");
  var lightboxCaption = document.getElementById("lightboxCaption");
  var allPhotos = [];
  var visible = [];
  var currentAlbum = "all";
  var currentIndex = 0;

  function setStatus(msg) {
    statusEl.hidden = !msg;
    statusEl.textContent = msg || "";
  }

  function renderFilters(albums) {
    if (!albums.length) {
      filters.hidden = true;
      return;
    }
    filters.hidden = false;
    filters.innerHTML = "";

    var allBtn = document.createElement("button");
    allBtn.type = "button";
    allBtn.textContent = "All";
    allBtn.setAttribute("aria-pressed", currentAlbum === "all" ? "true" : "false");
    allBtn.addEventListener("click", function () {
      currentAlbum = "all";
      updateFilters();
      renderGrid();
    });
    filters.appendChild(allBtn);

    albums.forEach(function (album) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = album.title + " (" + album.count + ")";
      btn.dataset.album = album.slug;
      btn.setAttribute("aria-pressed", currentAlbum === album.slug ? "true" : "false");
      btn.addEventListener("click", function () {
        currentAlbum = album.slug;
        updateFilters();
        renderGrid();
      });
      filters.appendChild(btn);
    });
  }

  function updateFilters() {
    Array.prototype.forEach.call(filters.querySelectorAll("button"), function (btn) {
      var slug = btn.dataset.album || "all";
      btn.setAttribute("aria-pressed", slug === currentAlbum ? "true" : "false");
    });
  }

  function renderGrid() {
    visible = allPhotos.filter(function (p) {
      return currentAlbum === "all" || p.album === currentAlbum;
    });

    grid.innerHTML = "";
    if (!visible.length) {
      grid.hidden = true;
      setStatus("No photos in this album yet. Add image files under gallery/albums/.");
      return;
    }

    setStatus("");
    grid.hidden = false;

    visible.forEach(function (photo, i) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "gallery-card";
      btn.style.animationDelay = Math.min(i * 0.03, 0.6) + "s";
      btn.setAttribute("aria-label", photo.alt || "Open photo");

      var img = document.createElement("img");
      img.src = photo.src;
      img.alt = photo.alt || "";
      img.loading = i < 9 ? "eager" : "lazy";
      img.decoding = "async";

      var meta = document.createElement("span");
      meta.className = "gallery-card__meta";
      meta.textContent = photo.caption || "";

      btn.appendChild(img);
      btn.appendChild(meta);
      btn.addEventListener("click", function () {
        openLightbox(i);
      });
      grid.appendChild(btn);
    });
  }

  function openLightbox(index) {
    currentIndex = index;
    var photo = visible[currentIndex];
    if (!photo) return;
    lightboxImg.src = photo.src;
    lightboxImg.alt = photo.alt || "";
    lightboxCaption.textContent = photo.caption || "";
    lightbox.hidden = false;
    document.body.style.overflow = "hidden";
  }

  function closeLightbox() {
    lightbox.hidden = true;
    lightboxImg.removeAttribute("src");
    document.body.style.overflow = "";
  }

  function step(delta) {
    if (!visible.length) return;
    currentIndex = (currentIndex + delta + visible.length) % visible.length;
    openLightbox(currentIndex);
  }

  document.getElementById("lightboxClose").addEventListener("click", closeLightbox);
  document.getElementById("lightboxPrev").addEventListener("click", function () {
    step(-1);
  });
  document.getElementById("lightboxNext").addEventListener("click", function () {
    step(1);
  });

  lightbox.addEventListener("click", function (e) {
    if (e.target === lightbox) closeLightbox();
  });

  document.addEventListener("keydown", function (e) {
    if (lightbox.hidden) return;
    if (e.key === "Escape") closeLightbox();
    if (e.key === "ArrowLeft") step(-1);
    if (e.key === "ArrowRight") step(1);
  });

  fetch("gallery-api.php", { credentials: "same-origin" })
    .then(function (res) {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    })
    .then(function (data) {
      allPhotos = data.photos || [];
      renderFilters(data.albums || []);
      renderGrid();
    })
    .catch(function () {
      setStatus("Could not load the gallery. Check that gallery-api.php is reachable on the host.");
    });
})();
