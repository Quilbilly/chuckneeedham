<?php
require_once __DIR__ . '/feedback-lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$flash = $_SESSION['feedback_flash'] ?? null;
unset($_SESSION['feedback_flash']);
$error = '';
$csrf = gallery_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    $name = gallery_clean_text((string) ($_POST['name'] ?? ''), 80);
    $email = gallery_clean_text((string) ($_POST['email'] ?? ''), 120);
    $body = feedback_clean_body((string) ($_POST['message'] ?? ''), 4000);

    if (!gallery_csrf_ok(is_string($token) ? $token : null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($honeypot !== '') {
        $_SESSION['feedback_flash'] = 'thankyou';
        header('Location: feedback.php');
        exit;
    } elseif ($name === '' || $body === '') {
        $error = 'Please include your name and a message.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address does not look valid.';
    } elseif (feedback_rate_limited()) {
        $error = 'Too many messages from this connection. Please try again later.';
    } else {
        try {
            $data = feedback_load();
            $data['messages'][] = [
                'id' => feedback_new_id(),
                'name' => $name,
                'email' => $email,
                'body' => $body,
                'createdAt' => gmdate('c'),
                'ipHash' => hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|chuck-feedback'),
            ];
            feedback_save($data);
            $_SESSION['feedback_flash'] = 'thankyou';
            header('Location: feedback.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Sorry — your message could not be saved just now. Please try again shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback — Chuck Needham</title>
  <meta name="description" content="Send feedback about chuckneedham.com.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Schibsted+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body class="page-inner">

  <nav class="site-nav site-nav--solid" aria-label="Main">
    <a class="site-nav__brand" href="index.html">Chuck Needham</a>
    <div class="site-nav__links">
      <a href="about.html">About</a>
      <a href="gallery.html">Gallery</a>
      <a href="feedback.php" aria-current="page">Feedback</a>
    </div>
  </nav>

  <header class="page-hero">
    <p class="page-hero__eyebrow">Feedback</p>
    <h1>Say hello</h1>
    <p class="page-hero__lede">Thoughts on the site, photo captions, or anything else — I read every note.</p>
  </header>

  <main class="wrap">
    <?php if ($flash === 'thankyou'): ?>
      <p class="feedback-banner feedback-banner--ok" role="status">Thank you. Your message was received.</p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <p class="feedback-banner feedback-banner--err" role="alert"><?php echo gallery_h($error); ?></p>
    <?php endif; ?>

    <form class="feedback-form" method="post" action="feedback.php" novalidate>
      <input type="hidden" name="csrf" value="<?php echo gallery_h($csrf); ?>">
      <p class="feedback-hp" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </p>
      <label class="feedback-field">
        <span>Your name</span>
        <input type="text" name="name" required maxlength="80" value="<?php echo gallery_h($_POST['name'] ?? ''); ?>">
      </label>
      <label class="feedback-field">
        <span>Email <em>(optional)</em></span>
        <input type="email" name="email" maxlength="120" value="<?php echo gallery_h($_POST['email'] ?? ''); ?>">
      </label>
      <label class="feedback-field">
        <span>Message</span>
        <textarea name="message" required maxlength="4000" rows="7"><?php echo gallery_h($_POST['message'] ?? ''); ?></textarea>
      </label>
      <div class="feedback-actions">
        <button type="submit" class="btn btn--solid">Send feedback</button>
      </div>
    </form>
  </main>

  <footer class="site-footer">
    <p>&copy; <span id="year"></span> Chuck Needham</p>
    <p><a href="index.html">Home</a> · <a href="about.html">About</a> · <a href="gallery.html">Gallery</a></p>
  </footer>
  <script>document.getElementById("year").textContent = new Date().getFullYear();</script>
</body>
</html>
