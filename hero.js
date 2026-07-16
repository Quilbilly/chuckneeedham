(function () {
  "use strict";

  var media = document.getElementById("heroMedia");
  if (!media) {
    return;
  }

  var INTERVAL_MS = 7000;
  var FADE_MS = 1400;
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  fetch("hero-api.php", { credentials: "same-origin" })
    .then(function (res) {
      if (!res.ok) {
        throw new Error("hero-api " + res.status);
      }
      return res.json();
    })
    .then(function (data) {
      var images = (data && data.images) || [];
      if (!images.length) {
        return;
      }
      start(images);
    })
    .catch(function () {
      /* Keep the CSS gradient plane as fallback. */
    });

  function makeSlide(eager) {
    var img = document.createElement("img");
    img.className = "hero__slide";
    img.alt = "";
    img.decoding = "async";
    img.loading = eager ? "eager" : "lazy";
    media.appendChild(img);
    return img;
  }

  function waitForLoad(img) {
    return new Promise(function (resolve) {
      if (img.complete && img.naturalWidth) {
        resolve();
        return;
      }
      var done = function () {
        img.removeEventListener("load", done);
        img.removeEventListener("error", done);
        resolve();
      };
      img.addEventListener("load", done);
      img.addEventListener("error", done);
    });
  }

  function start(images) {
    var front = makeSlide(true);
    var back = makeSlide(false);
    var index = 0;
    var showingFront = true;

    front.src = images[0].src;
    media.classList.add("is-ready");

    waitForLoad(front).then(function () {
      front.classList.add("is-active");
    });

    if (images.length < 2 || reduceMotion) {
      return;
    }

    /* Prefetch the next frame into the hidden layer. */
    back.src = images[1].src;

    setInterval(function () {
      var nextIndex = (index + 1) % images.length;
      var current = showingFront ? front : back;
      var next = showingFront ? back : front;

      function advance() {
        current.classList.remove("is-active");
        next.classList.add("is-active");
        showingFront = !showingFront;
        index = nextIndex;

        var following = (nextIndex + 1) % images.length;
        window.setTimeout(function () {
          var hidden = showingFront ? back : front;
          if (hidden.getAttribute("src") !== images[following].src) {
            hidden.src = images[following].src;
          }
        }, FADE_MS);
      }

      if (next.getAttribute("src") !== images[nextIndex].src) {
        next.src = images[nextIndex].src;
      }

      waitForLoad(next).then(advance);
    }, INTERVAL_MS);
  }
})();
