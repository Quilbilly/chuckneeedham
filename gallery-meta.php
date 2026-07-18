<?php
require_once __DIR__ . '/gallery-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=120');

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$album = isset($_GET['album']) ? (string) $_GET['album'] : '';
$file = isset($_GET['file']) ? (string) $_GET['file'] : '';

if ($id !== '' && strpos($id, '/') !== false) {
    $parts = explode('/', $id, 2);
    $album = $parts[0];
    // id is album/basename without extension — resolve to real file
    $base = $parts[1];
    $slug = gallery_safe_album_slug($album);
    if ($slug === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }
    $resolved = null;
    foreach (gallery_list_album_files($slug) as $candidate) {
        if (pathinfo($candidate, PATHINFO_FILENAME) === $base) {
            $resolved = $candidate;
            break;
        }
    }
    if ($resolved === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }
    $file = $resolved;
}

$result = gallery_read_photo_meta($album, $file);
if (empty($result['ok'])) {
    http_response_code(404);
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
