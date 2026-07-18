<?php
require_once __DIR__ . '/gallery-lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = gallery_config();
$flash = $_SESSION['gallery_flash'] ?? null;
unset($_SESSION['gallery_flash']);
$error = '';
$needsSetup = gallery_needs_setup();
$albumSlug = isset($_GET['album']) ? (string) $_GET['album'] : '';

if (isset($_GET['logout'])) {
    unset($_SESSION['gallery_admin']);
    header('Location: gallery-admin.php');
    exit;
}

if ($needsSetup && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup') {
    $token = $_POST['csrf'] ?? '';
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if (!gallery_csrf_ok(is_string($token) ? $token : null)) {
        $error = 'Session expired. Please try again.';
    } elseif (strlen($password) < 8) {
        $error = 'Choose a password with at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords did not match.';
    } else {
        try {
            gallery_write_config([
                'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'site_url' => 'https://chuckneedham.com',
            ]);
            $_SESSION['gallery_admin'] = true;
            session_regenerate_id(true);
            $_SESSION['gallery_flash'] = 'Gallery admin is ready. You can edit album titles and photo captions.';
            header('Location: gallery-admin.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Could not save settings. On the server, ensure /home/chuckneedham/private is writable.';
        }
    }
}

if (!$needsSetup && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $password = (string) ($_POST['password'] ?? '');
    $hash = (string) ($config['admin_password_hash'] ?? '');
    if ($hash !== '' && password_verify($password, $hash)) {
        $_SESSION['gallery_admin'] = true;
        session_regenerate_id(true);
        header('Location: gallery-admin.php');
        exit;
    }
    $error = 'Incorrect password.';
}

if (!$needsSetup && gallery_admin_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_album') {
    $token = $_POST['csrf'] ?? '';
    $slug = gallery_safe_album_slug((string) ($_POST['album'] ?? ''));
    if (!gallery_csrf_ok(is_string($token) ? $token : null)) {
        $error = 'Session expired. Please try again.';
    } elseif ($slug === null) {
        $error = 'Album not found.';
    } else {
        try {
            $meta = gallery_load_album_meta($slug);
            $meta['title'] = gallery_clean_text((string) ($_POST['title'] ?? ''), 120);
            if ($meta['title'] === '') {
                $meta['title'] = gallery_pretify_slug($slug);
            }
            $meta['description'] = gallery_clean_text((string) ($_POST['description'] ?? ''), 500);
            $captionsIn = $_POST['captions'] ?? [];
            $captions = [];
            if (is_array($captionsIn)) {
                foreach (gallery_list_album_files($slug) as $file) {
                    if (!isset($captionsIn[$file])) {
                        continue;
                    }
                    $cap = gallery_clean_text((string) $captionsIn[$file], 300);
                    if ($cap !== '') {
                        $captions[$file] = $cap;
                    }
                }
            }
            $meta['captions'] = $captions;
            gallery_save_album_meta($slug, $meta);
            $_SESSION['gallery_flash'] = 'Saved captions and album details for “' . $meta['title'] . '”.';
            header('Location: gallery-admin.php?album=' . rawurlencode($slug));
            exit;
        } catch (Throwable $e) {
            $error = 'Could not save album.json. Check folder permissions on gallery/albums/' . ($slug ?: '') . '.';
        }
    }
}

$loggedIn = !$needsSetup && gallery_admin_logged_in();
$catalog = $loggedIn ? gallery_catalog() : ['albums' => [], 'photos' => []];
$csrf = gallery_csrf_token();

$editAlbum = null;
$editFiles = [];
$editMeta = null;
if ($loggedIn && $albumSlug !== '') {
    $safe = gallery_safe_album_slug($albumSlug);
    if ($safe !== null) {
        $editAlbum = $safe;
        $editMeta = gallery_load_album_meta($safe);
        $editFiles = gallery_list_album_files($safe);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gallery admin — Chuck Needham</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Schibsted+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <style>
    .admin-wrap { max-width: 52rem; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .admin-card { background: var(--bg-soft); border: 1px solid var(--line); padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 2px; }
    .admin-card h2 { font-family: var(--font-display); font-weight: 400; font-size: 1.6rem; margin-bottom: 0.75rem; }
    .admin-flash { background: rgba(196, 122, 58, 0.15); border: 1px solid var(--accent); color: var(--ink); padding: 0.75rem 1rem; margin-bottom: 1.25rem; }
    .admin-error { background: rgba(180, 60, 50, 0.2); border: 1px solid #c45; color: var(--ink); padding: 0.75rem 1rem; margin-bottom: 1.25rem; }
    .admin-field { margin-bottom: 1rem; }
    .admin-field label { display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.35rem; }
    .admin-field input, .admin-field textarea {
      width: 100%; background: var(--bg); border: 1px solid var(--line); color: var(--ink);
      padding: 0.6rem 0.75rem; font-family: var(--font-body); font-size: 1rem; border-radius: 2px;
    }
    .admin-field textarea { min-height: 4rem; resize: vertical; }
    .admin-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
    .admin-album-list { list-style: none; }
    .admin-album-list li { margin-bottom: 0.5rem; }
    .admin-album-list a { color: var(--ink); text-decoration: none; border-bottom: 1px solid var(--line); }
    .admin-album-list a:hover { color: var(--accent); border-color: var(--accent); }
    .admin-photo-row {
      display: grid; grid-template-columns: 7rem 1fr; gap: 1rem; align-items: start;
      padding: 1rem 0; border-top: 1px solid var(--line);
    }
    .admin-photo-row img { width: 7rem; height: 5rem; object-fit: cover; background: var(--bg); }
    .admin-muted { color: var(--muted); font-size: 0.9rem; }
    .admin-top { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
  </style>
</head>
<body class="page-inner">
  <nav class="site-nav site-nav--solid" aria-label="Main">
    <a class="site-nav__brand" href="index.html">Chuck Needham</a>
    <div class="site-nav__links">
      <a href="gallery.html">Gallery</a>
      <a href="gallery-admin.php" aria-current="page">Admin</a>
    </div>
  </nav>

  <div class="admin-wrap">
    <div class="admin-top">
      <h1 style="font-family: var(--font-display); font-weight: 400; font-size: 2.2rem;">Gallery admin</h1>
      <?php if ($loggedIn): ?>
        <p class="admin-muted"><a href="gallery-admin.php?logout=1">Log out</a></p>
      <?php endif; ?>
    </div>

    <?php if ($flash): ?>
      <p class="admin-flash"><?php echo gallery_h((string) $flash); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
      <p class="admin-error"><?php echo gallery_h($error); ?></p>
    <?php endif; ?>

    <?php if ($needsSetup): ?>
      <div class="admin-card">
        <h2>One-time setup</h2>
        <p class="admin-muted">Create an admin password. It is stored hashed under <code>~/private/chuck-gallery/</code> (not in the public site folder).</p>
        <form method="post">
          <input type="hidden" name="action" value="setup">
          <input type="hidden" name="csrf" value="<?php echo gallery_h($csrf); ?>">
          <div class="admin-field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
          </div>
          <div class="admin-field">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
          </div>
          <div class="admin-actions">
            <button type="submit" class="btn btn--solid">Create admin</button>
          </div>
        </form>
      </div>

    <?php elseif (!$loggedIn): ?>
      <div class="admin-card">
        <h2>Log in</h2>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <div class="admin-field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
          </div>
          <div class="admin-actions">
            <button type="submit" class="btn btn--solid">Log in</button>
          </div>
        </form>
      </div>

    <?php elseif ($editAlbum === null): ?>
      <div class="admin-card">
        <h2>Albums</h2>
        <p class="admin-muted">Albums are folders under <code>gallery/albums/</code>. Pick one to edit its title, description, and photo captions.</p>
        <?php if (empty($catalog['albums'])): ?>
          <p class="admin-muted">No albums with photos yet. Add image files under <code>gallery/albums/&lt;album-name&gt;/</code> and refresh.</p>
        <?php else: ?>
          <ul class="admin-album-list">
            <?php foreach ($catalog['albums'] as $album): ?>
              <li>
                <a href="gallery-admin.php?album=<?php echo rawurlencode($album['slug']); ?>">
                  <?php echo gallery_h($album['title']); ?>
                  <span class="admin-muted">(<?php echo (int) $album['count']; ?>)</span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <p class="admin-muted" style="margin-bottom: 1rem;"><a href="gallery-admin.php">← All albums</a> · <a href="gallery.html#album/<?php echo rawurlencode($editAlbum); ?>">View on site</a></p>
      <form method="post" class="admin-card">
        <input type="hidden" name="action" value="save_album">
        <input type="hidden" name="csrf" value="<?php echo gallery_h($csrf); ?>">
        <input type="hidden" name="album" value="<?php echo gallery_h($editAlbum); ?>">
        <h2><?php echo gallery_h($editMeta['title']); ?></h2>
        <div class="admin-field">
          <label for="title">Album title</label>
          <input type="text" id="title" name="title" value="<?php echo gallery_h($editMeta['title']); ?>" maxlength="120" required>
        </div>
        <div class="admin-field">
          <label for="description">Album description</label>
          <textarea id="description" name="description" maxlength="500"><?php echo gallery_h($editMeta['description']); ?></textarea>
        </div>

        <h2 style="margin-top: 1.5rem;">Photo captions</h2>
        <p class="admin-muted">Leave a caption blank to hide it on the public gallery.</p>
        <?php foreach ($editFiles as $file): ?>
          <?php
            $cap = gallery_caption_for($editMeta, $file);
            $src = gallery_photo_src($editAlbum, $file);
          ?>
          <div class="admin-photo-row">
            <img src="<?php echo gallery_h($src); ?>" alt="">
            <div class="admin-field" style="margin: 0;">
              <label for="cap-<?php echo gallery_h($file); ?>"><?php echo gallery_h($file); ?></label>
              <textarea id="cap-<?php echo gallery_h($file); ?>" name="captions[<?php echo gallery_h($file); ?>]" maxlength="300" rows="2"><?php echo gallery_h($cap); ?></textarea>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="admin-actions">
          <button type="submit" class="btn btn--solid">Save album</button>
          <a class="btn btn--ghost" href="gallery-admin.php">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
