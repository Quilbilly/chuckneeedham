<?php
/**
 * Hero image API — scans hero_images/ on disk.
 * Drop jpg/jpeg/png/webp files in that folder; this endpoint lists them for hero.js.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

$root = __DIR__ . '/hero_images';
$allowed = ['jpg', 'jpeg', 'png', 'webp'];

if (!is_dir($root)) {
    echo json_encode(['images' => []]);
    exit;
}

$files = scandir($root);
if ($files === false) {
    echo json_encode(['images' => [], 'error' => 'Unable to read hero_images']);
    exit;
}

natcasesort($files);

$images = [];
foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        continue;
    }

    $images[] = [
        'src' => 'hero_images/' . str_replace('%2F', '/', rawurlencode($file)),
        'alt' => '',
    ];
}

echo json_encode([
    'images' => array_values($images),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
