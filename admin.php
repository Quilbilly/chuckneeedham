<?php
require_once __DIR__ . '/gallery-lib.php';
require_once __DIR__ . '/feedback-lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = gallery_config();
$flash = $_SESSION['gallery_flash'] ?? null;
unset($_SESSION['gallery_flash']);
$error = '';
$needsSetup = gallery_needs_setup();
$section = isset($_GET['section']) ? (string) $_GET['section'] : 'gallery';
if (!in_array($section, ['gallery', 'feedback'], true)) {
    $section = 'gallery';
}
$albumSlug = isset($_GET['album']) ? (string) $_GET['album'] : '';

if (isset($_GET['logout'])) {
    unset($_SESSION['gallery_admin']);
    header('Location: admin.php');
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
            $_SESSION['gallery_flash'] = 'Admin is ready. You can manage the gallery and site feedback.';
            header('Location: admin.php');
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
        header('Location: admin.php');
        exit;
    }
    $error = 'Incorrect password.';
}

if (!$needsSetup && gallery_admin_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $token = $_POST['csrf'] ?? '';
    if (!gallery_csrf_ok(is_string($token) ? $token : null)) {
        $error = 'Session expired. Please try again.';
    } elseif ($action === 'save_album') {
        $slug = gallery_safe_album_slug((string) ($_POST['album'] ?? ''));
        if ($slug === null) {
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
                $_SESSION['gallery_flash'] = 'Saved album details for “' . $meta['title'] . '”.';
                header('Location: admin.php?section=gallery&album=' . rawurlencode($slug));
                exit;
            } catch (Throwable $e) {
                $error = 'Could not save album details.';
            }
        }
    } elseif ($action === 'delete_photo') {
        $slug = (string) ($_POST['album'] ?? '');
        $file = (string) ($_POST['file'] ?? '');
        try {
            gallery_delete_photo($slug, $file);
            $_SESSION['gallery_flash'] = 'Deleted photo on the live site. Ask Cursor to remove it from GitHub so the next publish does not restore it.';
            header('Location: admin.php?section=gallery&album=' . rawurlencode($slug));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $section = 'gallery';
            $albumSlug = $slug;
        }
    } elseif ($action === 'delete_album') {
        $slug = (string) ($_POST['album'] ?? '');
        $confirm = (string) ($_POST['confirm_slug'] ?? '');
        if ($confirm !== $slug) {
            $error = 'Type the album folder name exactly to confirm deletion.';
            $section = 'gallery';
            $albumSlug = $slug;
        } else {
            try {
                $title = gallery_load_album_meta($slug)['title'] ?? $slug;
                gallery_delete_album($slug);
                $_SESSION['gallery_flash'] = 'Deleted album “' . $title . '” on the live site. Ask Cursor to remove it from GitHub so the next publish does not restore it.';
                header('Location: admin.php?section=gallery');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $section = 'gallery';
                $albumSlug = $slug;
            }
        }
    } elseif ($action === 'delete_feedback') {
        $id = (string) ($_POST['id'] ?? '');
        if ($id === '' || !feedback_delete($id)) {
            $error = 'Feedback item not found.';
            $section = 'feedback';
        } else {
            $_SESSION['gallery_flash'] = 'Feedback message deleted.';
            header('Location: admin.php?section=feedback');
            exit;
        }
    }
}

$loggedIn = !$needsSetup && gallery_admin_logged_in();
$catalog = $loggedIn ? gallery_catalog() : ['albums' => [], 'photos' => []];
$csrf = gallery_csrf_token();
$feedbackItems = $loggedIn ? feedback_sorted(feedback_load()) : [];

$editAlbum = null;
$editFiles = [];
$editMeta = null;
if ($loggedIn && $section === 'gallery' && $albumSlug !== '') {
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
  <meta name="robots" content="noindex, nofollow">
  <title>Admin — Chuck Needham</title>
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
    .admin-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; align-items: center; }
    .admin-album-list { list-style: none; }
    .admin-album-list li { margin-bottom: 0.65rem; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: baseline; }
    .admin-album-list a { color: var(--ink); text-decoration: none; border-bottom: 1px solid var(--line); }
    .admin-album-list a:hover { color: var(--accent); border-color: var(--accent); }
    .admin-photo-row {
      display: grid; grid-template-columns: 7rem 1fr auto; gap: 1rem; align-items: start;
      padding: 1rem 0; border-top: 1px solid var(--line);
    }
    @media (max-width: 640px) {
      .admin-photo-row { grid-template-columns: 7rem 1fr; }
      .admin-photo-row .admin-photo-del { grid-column: 1 / -1; }
    }
    .admin-photo-row img { width: 7rem; height: 5rem; object-fit: cover; background: var(--bg); }
    .admin-muted { color: var(--muted); font-size: 0.9rem; }
    .admin-top { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .admin-tabs { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .admin-tabs a {
      font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--muted); text-decoration: none; padding: 0.4rem 0.75rem; border: 1px solid var(--line); border-radius: 2px;
    }
    .admin-tabs a[aria-current="page"] { color: var(--ink); border-color: var(--accent); }
    .btn--danger {
      background: transparent; color: #e8a0a0; border: 1px solid rgba(196, 80, 70, 0.55);
      font-family: var(--font-body); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.06em;
      text-transform: uppercase; padding: 0.45rem 0.85rem; cursor: pointer; border-radius: 2px;
    }
    .btn--danger:hover { background: rgba(180, 60, 50, 0.2); color: #fff; }
    .admin-danger-zone { border-color: rgba(196, 80, 70, 0.45); margin-top: 1.5rem; }
    .admin-feedback-item { border-top: 1px solid var(--line); padding: 1.1rem 0; }
    .admin-feedback-item:first-of-type { border-top: none; padding-top: 0; }
    .admin-feedback-meta { color: var(--muted); font-size: 0.85rem; margin-bottom: 0.5rem; }
    .admin-feedback-body { white-space: pre-wrap; color: var(--ink); margin-bottom: 0.75rem; }
  </style>
</head>
<body class="page-inner">
  <nav class="site-nav site-nav--solid" aria-label="Main">
    <a class="site-nav__brand" href="index.html">Chuck Needham</a>
    <div class="site-nav__links">
      <a href="gallery.html">Gallery</a>
      <a href="feedback.php">Feedback</a>
      <a href="admin.php" aria-current="page">Admin</a>
    </div>
  </nav>

  <div class="admin-wrap">
    <div class="admin-top">
      <h1 style="font-family: var(--font-display); font-weight: 400; font-size: 2.2rem;">Site admin</h1>
      <?php if ($loggedIn): ?>
        <p class="admin-muted"><a href="admin.php?logout=1">Log out</a></p>
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
        <p class="admin-muted">Create an admin password. Stored hashed under <code>~/private/chuck-gallery/</code> (outside the public site).</p>
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

    <?php else: ?>
      <nav class="admin-tabs" aria-label="Admin sections">
        <a href="admin.php?section=gallery" <?php echo $section === 'gallery' ? 'aria-current="page"' : ''; ?>>Gallery</a>
        <a href="admin.php?section=feedback" <?php echo $section === 'feedback' ? 'aria-current="page"' : ''; ?>>
          Feedback<?php echo $feedbackItems ? ' (' . count($feedbackItems) . ')' : ''; ?>
        </a>
      </nav>

      <?php if ($section === 'feedback'): ?>
        <div class="admin-card">
          <h2>Feedback</h2>
          <p class="admin-muted">Messages from the public feedback form. Emails are only visible here.</p>
          <?php if (!$feedbackItems): ?>
            <p class="admin-muted">No feedback yet.</p>
          <?php else: ?>
            <?php foreach ($feedbackItems as $item): ?>
              <article class="admin-feedback-item">
                <p class="admin-feedback-meta">
                  <strong><?php echo gallery_h((string) ($item['name'] ?? '')); ?></strong>
                  <?php if (!empty($item['email'])): ?>
                    · <a href="mailto:<?php echo gallery_h((string) $item['email']); ?>"><?php echo gallery_h((string) $item['email']); ?></a>
                  <?php endif; ?>
                  · <?php echo gallery_h((string) ($item['createdAt'] ?? '')); ?>
                </p>
                <div class="admin-feedback-body"><?php echo gallery_h((string) ($item['body'] ?? '')); ?></div>
                <form method="post" onsubmit="return confirm('Delete this feedback message?');">
                  <input type="hidden" name="action" value="delete_feedback">
                  <input type="hidden" name="csrf" value="<?php echo gallery_h($csrf); ?>">
                  <input type="hidden" name="id" value="<?php echo gallery_h((string) ($item['id'] ?? '')); ?>">
                  <button type="submit" class="btn--danger">Delete</button>
                </form>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      <?php elseif ($editAlbum === null): ?>
        <div class="admin-card">
          <h2>Albums</h2>
          <p class="admin-muted">Open an album to edit captions or delete photos. New photos are published through Cursor / GitHub.</p>
          <?php if (empty($catalog['albums'])): ?>
            <p class="admin-muted">No albums yet.</p>
          <?php else: ?>
            <ul class="admin-album-list">
              <?php foreach ($catalog['albums'] as $album): ?>
                <li>
                  <a href="admin.php?section=gallery&amp;album=<?php echo rawurlencode($album['slug']); ?>">
                    <?php echo gallery_h($album['title']); ?>
                    <span class="admin-muted">(<?php echo (int) $album['count']; ?>)</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <p class="admin-muted" style="margin-bottom: 1rem;">
          <a href="admin.php?section=gallery">← All albums</a>
          · <a href="gallery.html#album/<?php echo rawurlencode($editAlbum); ?>">View on site</a>
        </p>

        <form method="post" class="admin-card" id="albumMetaForm">
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
          <div class="admin-actions">
            <button type="submit" class="btn btn--solid">Save album details</button>
          </div>
        </form>

        <div class="admin-card">
          <h2>Photos</h2>
          <p class="admin-muted">Edit captions below, then save. Delete removes the file from the live site only.</p>
          <form method="post" id="captionsForm">
            <input type="hidden" name="action" value="save_album">
            <input type="hidden" name="csrf" value="<?php echo gallery_h($csrf); ?>">
            <input type="hidden" name="album" value="<?php echo gallery_h($editAlbum); ?>">
            <input type="hidden" name="title" value="<?php echo gallery_h($editMeta['title']); ?>">
            <input type="hidden" name="description" value="<?php echo gallery_h($editMeta['description']); ?>">
          </form>
          <?php foreach ($editFiles as $file): ?>
            <?php
              $cap = gallery_caption_for($editMeta, $file);
              $src = gallery_photo_src($editAlbum, $file);
            ?>
            <div class="admin-photo-row">
              <img src="<?php echo gallery_h($src); ?>" alt="">
              <div class="admin-field" style="margin: 0;">
                <label for="cap-<?php echo gallery_h($file); ?>"><?php echo gallery_h($file); ?></label>
                <textarea form="captionsForm" id="cap-<?php echo gallery_h($file); ?>" name="captions[<?php echo gallery_h($file); ?>]" maxlength="300" rows="2"><?php echo gallery_h($cap); ?></textarea>
              </div>
              <form method="post" class="admin-photo-del" onsubmit="return confirm('Delete this photo from the live site?');">
                <input type="hidden" name="action" value="delete_photo">
                <input type="hidden" name="csrf" value="<?php echo gallery_h($csrf); ?>">
                <input type="hidden" name="album" value="<?php echo gallery_h($editAlbum); ?>">
                <input type="hidden" name="file" value="<?php echo gallery_h($file); ?>">
                <button type="submit" class="btn--danger">Delete photo</button>
              </form>
            </div>
          <?php endforeach; ?>
          <div class="admin-actions">
            <button type="submit" form="captionsForm" class="btn btn--solid">Save captions</button>
            <a class="btn btn--ghost" href="admin.php?section=gallery">Back</a>
          </div>
        </div>

        <div class="admin-card admin-danger-zone">
          <h2>Delete album</h2>
          <p class="admin-muted">Removes the whole album folder from the live site. Type the folder name <strong><?php echo gallery_h($editAlbum); ?></strong> to confirm.</p>
          <form method="post" onsubmit="return confirm('Permanently delete this entire album from the live site?');">
            <input type="hidden" name="action" value="delete_album">
            <input type="hidden" name="csrf" value="<?php echo gallery_h($csrf); ?>">
            <input type="hidden" name="album" value="<?php echo gallery_h($editAlbum); ?>">
            <div class="admin-field">
              <label for="confirm_slug">Confirm folder name</label>
              <input type="text" id="confirm_slug" name="confirm_slug" required autocomplete="off">
            </div>
            <button type="submit" class="btn--danger">Delete album</button>
          </form>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
