(function () {
  "use strict";

  var statusEl = document.getElementById("galleryStatus");
  var albumGrid = document.getElementById("albumGrid");
  var photoGrid = document.getElementById("galleryGrid");
  var albumNav = document.getElementById("albumNav");
  var albumBack = document.getElementById("albumBack");
  var albumDesc = document.getElementById("albumDesc");
  var heading = document.getElementById("galleryHeading");
  var lede = document.getElementById("galleryLede");
  var lightbox = document.getElementById("lightbox");
  var lightboxImg = document.getElementById("lightboxImg");
  var lightboxCaption = document.getElementById("lightboxCaption");
  var lightboxInfo = document.getElementById("lightboxInfo");
  var lightboxInfoPanel = document.getElementById("lightboxInfoPanel");
  var lightboxInfoStatus = document.getElementById("lightboxInfoStatus");
  var lightboxInfoList = document.getElementById("lightboxInfoList");

  var allAlbums = [];
  var allPhotos = [];
  var visible = [];
  var currentAlbum = null;
  var currentIndex = 0;
  var infoOpen = false;
  var infoCache = {};

  function setStatus(msg) {
    statusEl.hidden = !msg;
    statusEl.textContent = msg || "";
  }

  function albumFromHash() {
    var hash = window.location.hash || "";
    var match = hash.match(/^#album\/([^/]+)$/);
    return match ? decodeURIComponent(match[1]) : null;
  }

  function setHash(slug) {
    if (slug) {
      window.location.hash = "album/" + encodeURIComponent(slug);
    } else if (window.location.hash) {
      history.replaceState(null, "", window.location.pathname + window.location.search);
    }
  }

  function findAlbum(slug) {
    for (var i = 0; i < allAlbums.length; i++) {
      if (allAlbums[i].slug === slug) return allAlbums[i];
    }
    return null;
  }

  function showAlbums() {
    currentAlbum = null;
    infoOpen = false;
    closeLightbox();
    heading.textContent = "Photographs";
    lede.textContent = "Albums of places and moments. Open an album, then click a photo to view it larger.";
    albumNav.hidden = true;
    albumDesc.hidden = true;
    photoGrid.hidden = true;
    photoGrid.innerHTML = "";

    if (!allAlbums.length) {
      albumGrid.hidden = true;
      setStatus("No albums yet. Add folders with photos under gallery/albums/.");
      return;
    }

    setStatus("");
    albumGrid.hidden = false;
    albumGrid.innerHTML = "";

    allAlbums.forEach(function (album, i) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "album-card";
      btn.style.animationDelay = Math.min(i * 0.04, 0.5) + "s";

      var img = document.createElement("img");
      img.src = album.cover;
      img.alt = "";
      img.loading = i < 6 ? "eager" : "lazy";
      img.decoding = "async";

      var body = document.createElement("span");
      body.className = "album-card__body";
      var title = document.createElement("span");
      title.className = "album-card__title";
      title.textContent = album.title;
      var count = document.createElement("span");
      count.className = "album-card__count";
      count.textContent = album.count + (album.count === 1 ? " photo" : " photos");
      body.appendChild(title);
      body.appendChild(count);

      btn.appendChild(img);
      btn.appendChild(body);
      btn.addEventListener("click", function () {
        openAlbum(album.slug, true);
      });
      albumGrid.appendChild(btn);
    });
  }

  function openAlbum(slug, pushHash) {
    var album = findAlbum(slug);
    if (!album) {
      showAlbums();
      return;
    }
    currentAlbum = slug;
    if (pushHash) setHash(slug);

    heading.textContent = album.title;
    lede.textContent = album.description || "Click a photo to view it larger.";
    albumNav.hidden = false;
    if (album.description) {
      albumDesc.hidden = false;
      albumDesc.textContent = album.description;
    } else {
      albumDesc.hidden = true;
      albumDesc.textContent = "";
    }

    albumGrid.hidden = true;
    albumGrid.innerHTML = "";

    visible = allPhotos.filter(function (p) {
      return p.album === slug;
    });

    photoGrid.innerHTML = "";
    if (!visible.length) {
      photoGrid.hidden = true;
      setStatus("This album has no photos yet.");
      return;
    }

    setStatus("");
    photoGrid.hidden = false;

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

      btn.appendChild(img);
      if (photo.caption) {
        var meta = document.createElement("span");
        meta.className = "gallery-card__meta";
        meta.textContent = photo.caption;
        btn.appendChild(meta);
      }

      btn.addEventListener("click", function () {
        openLightbox(i);
      });
      photoGrid.appendChild(btn);
    });
  }

  function resetInfoPanel() {
    infoOpen = false;
    lightboxInfoPanel.hidden = true;
    lightboxInfo.setAttribute("aria-expanded", "false");
    lightboxInfoList.innerHTML = "";
    lightboxInfoStatus.hidden = true;
    lightboxInfoStatus.textContent = "";
  }

  function openLightbox(index) {
    currentIndex = index;
    var photo = visible[currentIndex];
    if (!photo) return;
    lightboxImg.src = photo.src;
    lightboxImg.alt = photo.alt || "";
    lightboxCaption.textContent = photo.caption || "";
    resetInfoPanel();
    if (photo.has_meta) {
      lightboxInfo.hidden = false;
    } else {
      lightboxInfo.hidden = true;
    }
    lightbox.hidden = false;
    document.body.style.overflow = "hidden";
  }

  function closeLightbox() {
    lightbox.hidden = true;
    lightboxImg.removeAttribute("src");
    resetInfoPanel();
    document.body.style.overflow = "";
  }

  function step(delta) {
    if (!visible.length) return;
    currentIndex = (currentIndex + delta + visible.length) % visible.length;
    openLightbox(currentIndex);
  }

  function renderInfoFields(fields) {
    lightboxInfoList.innerHTML = "";
    fields.forEach(function (field) {
      var dt = document.createElement("dt");
      dt.textContent = field.label;
      var dd = document.createElement("dd");
      dd.textContent = field.value;
      lightboxInfoList.appendChild(dt);
      lightboxInfoList.appendChild(dd);
    });
  }

  function toggleInfo() {
    var photo = visible[currentIndex];
    if (!photo || !photo.has_meta) return;

    if (infoOpen) {
      infoOpen = false;
      lightboxInfoPanel.hidden = true;
      lightboxInfo.setAttribute("aria-expanded", "false");
      return;
    }

    infoOpen = true;
    lightboxInfoPanel.hidden = false;
    lightboxInfo.setAttribute("aria-expanded", "true");
    lightboxInfoStatus.hidden = false;
    lightboxInfoStatus.textContent = "Loading…";
    lightboxInfoList.innerHTML = "";

    var cacheKey = photo.id;
    if (infoCache[cacheKey]) {
      lightboxInfoStatus.hidden = true;
      renderInfoFields(infoCache[cacheKey]);
      return;
    }

    fetch("gallery-meta.php?id=" + encodeURIComponent(photo.id), { credentials: "same-origin" })
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json();
      })
      .then(function (data) {
        if (!infoOpen) return;
        var fields = (data && data.fields) || [];
        if (data.caption) {
          fields = [{ label: "Caption", value: data.caption }].concat(fields);
        }
        if (!fields.length) {
          lightboxInfoStatus.hidden = false;
          lightboxInfoStatus.textContent = "No metadata available for this photo.";
          return;
        }
        infoCache[cacheKey] = fields;
        lightboxInfoStatus.hidden = true;
        renderInfoFields(fields);
      })
      .catch(function () {
        if (!infoOpen) return;
        lightboxInfoStatus.hidden = false;
        lightboxInfoStatus.textContent = "Could not load photo info.";
      });
  }

  albumBack.addEventListener("click", function () {
    setHash(null);
    showAlbums();
  });

  document.getElementById("lightboxClose").addEventListener("click", closeLightbox);
  document.getElementById("lightboxPrev").addEventListener("click", function () {
    step(-1);
  });
  document.getElementById("lightboxNext").addEventListener("click", function () {
    step(1);
  });
  lightboxInfo.addEventListener("click", function (e) {
    e.stopPropagation();
    toggleInfo();
  });
  lightboxInfoPanel.addEventListener("click", function (e) {
    e.stopPropagation();
  });

  lightbox.addEventListener("click", function (e) {
    if (e.target === lightbox) closeLightbox();
  });

  document.addEventListener("keydown", function (e) {
    if (lightbox.hidden) return;
    if (e.key === "Escape") {
      if (infoOpen) {
        infoOpen = false;
        lightboxInfoPanel.hidden = true;
        lightboxInfo.setAttribute("aria-expanded", "false");
      } else {
        closeLightbox();
      }
    }
    if (e.key === "ArrowLeft") step(-1);
    if (e.key === "ArrowRight") step(1);
  });

  window.addEventListener("hashchange", function () {
    var slug = albumFromHash();
    if (slug) openAlbum(slug, false);
    else showAlbums();
  });

  fetch("gallery-api.php", { credentials: "same-origin" })
    .then(function (res) {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    })
    .then(function (data) {
      allAlbums = data.albums || [];
      allPhotos = data.photos || [];
      var slug = albumFromHash();
      if (slug && findAlbum(slug)) openAlbum(slug, false);
      else showAlbums();
    })
    .catch(function () {
      setStatus("Could not load the gallery. Check that gallery-api.php is reachable on the host.");
    });
})();
