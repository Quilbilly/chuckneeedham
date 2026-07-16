<?php
/**
 * Gallery catalog API — scans gallery/albums/ on disk.
 * Each subfolder is an album. Optional album.json for title/description/captions.
 *
 * Photos stay as files (not MySQL). That scales better for large galleries,
 * serves faster via the web server, and survives simple git deploys.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

$root = __DIR__ . '/gallery/albums';
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($root)) {
    echo json_encode(['albums' => [], 'photos' => []]);
    exit;
}

$albums = [];
$photos = [];

$dirs = scandir($root);
if ($dirs === false) {
    echo json_encode(['albums' => [], 'photos' => [], 'error' => 'Unable to read albums']);
    exit;
}

natcasesort($dirs);

foreach ($dirs as $slug) {
    if ($slug === '.' || $slug === '..') {
        continue;
    }
    $dir = $root . '/' . $slug;
    if (!is_dir($dir)) {
        continue;
    }

    $meta = [
        'slug' => $slug,
        'title' => pretify_slug($slug),
        'description' => '',
        'captions' => [],
    ];

    $metaFile = $dir . '/album.json';
    if (is_file($metaFile)) {
        $raw = file_get_contents($metaFile);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (!empty($decoded['title'])) {
                $meta['title'] = (string) $decoded['title'];
            }
            if (!empty($decoded['description'])) {
                $meta['description'] = (string) $decoded['description'];
            }
            if (!empty($decoded['captions']) && is_array($decoded['captions'])) {
                $meta['captions'] = $decoded['captions'];
            }
        }
    }

    $files = scandir($dir);
    if ($files === false) {
        continue;
    }
    natcasesort($files);

    $count = 0;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'album.json') {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }

        $id = $slug . '/' . pathinfo($file, PATHINFO_FILENAME);
        $caption = '';
        if (isset($meta['captions'][$file])) {
            $caption = (string) $meta['captions'][$file];
        } elseif (isset($meta['captions'][pathinfo($file, PATHINFO_FILENAME)])) {
            $caption = (string) $meta['captions'][pathinfo($file, PATHINFO_FILENAME)];
        }

        $src = 'gallery/albums/' . rawurlencode($slug) . '/' . str_replace('%2F', '/', rawurlencode($file));
        $photos[] = [
            'id' => $id,
            'album' => $slug,
            'src' => $src,
            'caption' => $caption !== '' ? $caption : $meta['title'],
            'alt' => $caption !== '' ? $caption : ($meta['title'] . ' — ' . $file),
        ];
        $count++;
    }

    if ($count > 0) {
        unset($meta['captions']);
        $meta['count'] = $count;
        $albums[] = $meta;
    }
}

echo json_encode([
    'albums' => array_values($albums),
    'photos' => $photos,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

function pretify_slug($slug)
{
    $s = str_replace(['-', '_'], ' ', $slug);
    return ucwords($s);
}
