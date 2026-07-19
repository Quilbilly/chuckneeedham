<?php
/**
 * Shared gallery helpers — albums on disk, captions in album.json, admin auth private.
 */

function gallery_albums_root(): string
{
    return __DIR__ . '/gallery/albums';
}

function gallery_allowed_ext(): array
{
    // Web formats only — convert TIFF/RAW offline; keep originals under gallery/_originals/
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'];
}

function gallery_private_dir(): string
{
    $serverParent = '/home/chuckneedham/private';
    $server = $serverParent . '/chuck-gallery';
    if (is_dir($serverParent)) {
        if (!is_dir($server)) {
            @mkdir($server, 0700, true);
        }
        if (is_dir($server)) {
            return $server;
        }
    }
    $local = __DIR__ . '/private-data';
    if (!is_dir($local)) {
        @mkdir($local, 0700, true);
    }
    return $local;
}

function gallery_config_path(): string
{
    return gallery_private_dir() . '/config.php';
}

function gallery_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $defaults = [
        'admin_password_hash' => '',
        'site_url' => 'https://chuckneedham.com',
    ];
    $path = gallery_config_path();
    $loaded = is_file($path) ? (include $path) : [];
    if (!is_array($loaded)) {
        $loaded = [];
    }
    $config = array_merge($defaults, $loaded);
    return $config;
}

function gallery_write_config(array $config): void
{
    $path = gallery_config_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $export = var_export([
        'admin_password_hash' => (string) ($config['admin_password_hash'] ?? ''),
        'site_url' => (string) ($config['site_url'] ?? 'https://chuckneedham.com'),
    ], true);
    $php = "<?php\n// Auto-written by gallery-admin.php — keep outside the public web tree.\nreturn " . $export . ";\n";
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $php, LOCK_EX) === false) {
        throw new RuntimeException('Could not write config.');
    }
    if (!rename($tmp, $path)) {
        throw new RuntimeException('Could not save config.');
    }
    @chmod($path, 0600);
}

function gallery_needs_setup(): bool
{
    $config = gallery_config();
    return trim((string) ($config['admin_password_hash'] ?? '')) === '';
}

function gallery_admin_logged_in(): bool
{
    return !empty($_SESSION['gallery_admin']);
}

function gallery_csrf_token(): string
{
    if (empty($_SESSION['gallery_csrf'])) {
        $_SESSION['gallery_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['gallery_csrf'];
}

function gallery_csrf_ok(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['gallery_csrf'])
        && hash_equals($_SESSION['gallery_csrf'], $token);
}

function gallery_clean_text(string $text, int $max = 500): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max);
    }
    return substr($text, 0, $max);
}

function gallery_pretify_slug(string $slug): string
{
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

function gallery_safe_album_slug(string $slug): ?string
{
    $slug = trim($slug);
    if ($slug === '' || $slug === '.' || $slug === '..' || strpos($slug, '/') !== false || strpos($slug, '\\') !== false) {
        return null;
    }
    // Allow & for album names like Mom&Dad_Wedding
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._&-]{0,80}$/', $slug)) {
        return null;
    }
    $dir = gallery_albums_root() . '/' . $slug;
    if (!is_dir($dir)) {
        return null;
    }
    return $slug;
}

function gallery_safe_filename(string $file): ?string
{
    $file = basename($file);
    if ($file === '' || $file === '.' || $file === '..' || $file === 'album.json') {
        return null;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, gallery_allowed_ext(), true)) {
        return null;
    }
    return $file;
}

function gallery_load_album_meta(string $slug): array
{
    $meta = [
        'slug' => $slug,
        'title' => gallery_pretify_slug($slug),
        'description' => '',
        'captions' => [],
    ];
    $metaFile = gallery_albums_root() . '/' . $slug . '/album.json';
    if (is_file($metaFile)) {
        $decoded = json_decode((string) file_get_contents($metaFile), true);
        if (is_array($decoded)) {
            if (!empty($decoded['title'])) {
                $meta['title'] = (string) $decoded['title'];
            }
            if (isset($decoded['description'])) {
                $meta['description'] = (string) $decoded['description'];
            }
            if (!empty($decoded['captions']) && is_array($decoded['captions'])) {
                $meta['captions'] = $decoded['captions'];
            }
        }
    }

    // Admin edits live outside the web tree so deploys do not wipe captions.
    $privateFile = gallery_private_dir() . '/albums/' . $slug . '.json';
    if (is_file($privateFile)) {
        $decoded = json_decode((string) file_get_contents($privateFile), true);
        if (is_array($decoded)) {
            if (!empty($decoded['title'])) {
                $meta['title'] = (string) $decoded['title'];
            }
            if (isset($decoded['description'])) {
                $meta['description'] = (string) $decoded['description'];
            }
            if (isset($decoded['captions']) && is_array($decoded['captions'])) {
                $meta['captions'] = $decoded['captions'];
            }
        }
    }

    return $meta;
}

function gallery_save_album_meta(string $slug, array $meta): void
{
    $dir = gallery_albums_root() . '/' . $slug;
    if (!is_dir($dir)) {
        throw new RuntimeException('Album not found.');
    }
    $payload = [
        'title' => (string) ($meta['title'] ?? gallery_pretify_slug($slug)),
        'description' => (string) ($meta['description'] ?? ''),
        'captions' => is_array($meta['captions'] ?? null) ? $meta['captions'] : [],
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Could not encode album metadata.');
    }

    $privateDir = gallery_private_dir() . '/albums';
    if (!is_dir($privateDir)) {
        mkdir($privateDir, 0700, true);
    }
    $path = $privateDir . '/' . $slug . '.json';
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not write album metadata.');
    }
    if (!rename($tmp, $path)) {
        throw new RuntimeException('Could not save album metadata.');
    }
    @chmod($path, 0600);

    // Keep a copy in the album folder for local/dev convenience (optional).
    $publicPath = $dir . '/album.json';
    $publicTmp = $publicPath . '.tmp';
    if (@file_put_contents($publicTmp, $json . "\n", LOCK_EX) !== false) {
        @rename($publicTmp, $publicPath);
    }
}

function gallery_list_album_files(string $slug): array
{
    $dir = gallery_albums_root() . '/' . $slug;
    $files = [];
    $scan = scandir($dir);
    if ($scan === false) {
        return $files;
    }
    natcasesort($scan);
    foreach ($scan as $file) {
        $safe = gallery_safe_filename($file);
        if ($safe === null) {
            continue;
        }
        $files[] = $safe;
    }
    return array_values($files);
}

function gallery_photo_src(string $slug, string $file): string
{
    return 'gallery/albums/' . rawurlencode($slug) . '/' . str_replace('%2F', '/', rawurlencode($file));
}

function gallery_caption_for(array $meta, string $file): string
{
    $captions = $meta['captions'] ?? [];
    if (isset($captions[$file])) {
        return (string) $captions[$file];
    }
    $base = pathinfo($file, PATHINFO_FILENAME);
    if (isset($captions[$base])) {
        return (string) $captions[$base];
    }
    return '';
}

function gallery_catalog(): array
{
    $root = gallery_albums_root();
    $albums = [];
    $photos = [];
    if (!is_dir($root)) {
        return ['albums' => [], 'photos' => []];
    }
    $dirs = scandir($root);
    if ($dirs === false) {
        return ['albums' => [], 'photos' => [], 'error' => 'Unable to read albums'];
    }
    natcasesort($dirs);
    foreach ($dirs as $slug) {
        if ($slug === '.' || $slug === '..') {
            continue;
        }
        if (!is_dir($root . '/' . $slug)) {
            continue;
        }
        $meta = gallery_load_album_meta($slug);
        $files = gallery_list_album_files($slug);
        if (!$files) {
            continue;
        }
        $cover = gallery_photo_src($slug, $files[0]);
        foreach ($files as $file) {
            $caption = gallery_caption_for($meta, $file);
            $id = $slug . '/' . pathinfo($file, PATHINFO_FILENAME);
            $src = gallery_photo_src($slug, $file);
            $photos[] = [
                'id' => $id,
                'album' => $slug,
                'file' => $file,
                'src' => $src,
                'caption' => $caption,
                'alt' => $caption !== '' ? $caption : ($meta['title'] . ' — ' . $file),
                // Info always available (file size / dimensions); EXIF added when present
                'has_meta' => true,
            ];
        }
        $albums[] = [
            'slug' => $slug,
            'title' => $meta['title'],
            'description' => $meta['description'],
            'count' => count($files),
            'cover' => $cover,
        ];
    }
    return [
        'albums' => array_values($albums),
        'photos' => $photos,
    ];
}

function gallery_file_may_have_exif(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'tiff', 'tif'], true) && is_file($path);
}

/**
 * Extract readable photo metadata: file info + essentially all useful EXIF.
 */
function gallery_read_photo_meta(string $slug, string $file): array
{
    $slug = gallery_safe_album_slug($slug);
    $file = gallery_safe_filename($file);
    if ($slug === null || $file === null) {
        return ['ok' => false, 'error' => 'Not found'];
    }
    $path = gallery_albums_root() . '/' . $slug . '/' . $file;
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'Not found'];
    }

    $meta = gallery_load_album_meta($slug);
    $out = [
        'ok' => true,
        'file' => $file,
        'album' => $slug,
        'album_title' => $meta['title'],
        'caption' => gallery_caption_for($meta, $file),
        'fields' => [],
    ];

    $seen = [];
    $add = static function (array &$fields, array &$seen, string $label, string $value) {
        $value = trim($value);
        if ($value === '') {
            return;
        }
        $key = strtolower($label . '|' . $value);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $fields[] = ['label' => $label, 'value' => $value];
    };

    $size = @getimagesize($path, $info);
    if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
        $add($out['fields'], $seen, 'Dimensions', $size[0] . ' × ' . $size[1]);
    }
    $bytes = @filesize($path);
    if ($bytes !== false) {
        $add($out['fields'], $seen, 'File size', gallery_format_bytes((int) $bytes));
    }
    $mtime = @filemtime($path);
    if ($mtime) {
        $add($out['fields'], $seen, 'File date', gmdate('Y-m-d H:i', $mtime) . ' UTC');
    }

    // Preferred human labels for common tags (shown first when present)
    $preferred = [
        'Make' => 'Camera make',
        'Model' => 'Camera model',
        'DateTimeOriginal' => 'Taken',
        'DateTimeDigitized' => 'Digitized',
        'DateTime' => 'Modified (EXIF)',
        'ExposureTime' => 'Exposure',
        'FNumber' => 'Aperture',
        'ISOSpeedRatings' => 'ISO',
        'PhotographicSensitivity' => 'ISO',
        'FocalLength' => 'Focal length',
        'FocalLengthIn35mmFilm' => 'Focal length (35mm eq.)',
        'LensModel' => 'Lens',
        'LensMake' => 'Lens make',
        'LensSpecification' => 'Lens specification',
        'UndefinedTag:0xA434' => 'Lens', // common LensModel tag alias in some PHP builds
        'ExposureProgram' => 'Exposure program',
        'MeteringMode' => 'Metering',
        'Flash' => 'Flash',
        'WhiteBalance' => 'White balance',
        'ExposureBiasValue' => 'Exposure bias',
        'ExposureMode' => 'Exposure mode',
        'SceneCaptureType' => 'Scene',
        'Sharpness' => 'Sharpness',
        'Contrast' => 'Contrast',
        'Saturation' => 'Saturation',
        'DigitalZoomRatio' => 'Digital zoom',
        'Software' => 'Software',
        'Artist' => 'Artist',
        'Copyright' => 'Copyright',
        'ImageDescription' => 'Description',
        'UserComment' => 'Comment',
        'Orientation' => 'Orientation',
        'XResolution' => 'X resolution',
        'YResolution' => 'Y resolution',
        'ResolutionUnit' => 'Resolution unit',
        'ColorSpace' => 'Color space',
        'ExifImageWidth' => 'EXIF width',
        'ExifImageLength' => 'EXIF height',
        'BrightnessValue' => 'Brightness',
        'MaxApertureValue' => 'Max aperture',
        'SubjectDistance' => 'Subject distance',
        'LightSource' => 'Light source',
        'SensingMethod' => 'Sensor',
        'FileSource' => 'File source',
        'SceneType' => 'Scene type',
        'CustomRendered' => 'Custom rendered',
        'GainControl' => 'Gain control',
        'BodySerialNumber' => 'Body serial',
        'LensSerialNumber' => 'Lens serial',
        'CameraOwnerName' => 'Owner',
    ];

    $skipKeys = [
        'THUMBNAIL' => true,
        'MakerNote' => true,
        'ComponentsConfiguration' => true,
        'ExifVersion' => true,
        'FlashPixVersion' => true,
        'InteroperabilityIndex' => true,
        'InteroperabilityVersion' => true,
        'HTML' => true,
        'Html' => true,
        'MimeType' => true,
        'SectionsFound' => true,
        'FileName' => true,
        'FileDateTime' => true,
        'FileSize' => true,
        'FileType' => true,
        'COMPUTED' => true,
        'Height' => true,
        'Width' => true,
        'IsColor' => true,
        'Is Color' => true,
        'ByteOrderMotorola' => true,
        'Byte Order Motorola' => true,
        'Compression' => true,
        'JPEGInterchangeFormat' => true,
        'JPEGInterchange Format' => true,
        'JPEGInterchangeFormatLength' => true,
        'JPEGInterchange Format Length' => true,
        'SubIFD' => true,
        'Sub IFD' => true,
        'NewSubfileType' => true,
        'New Sub File' => true,
        'YCbCrCoefficients' => true,
        'YCb Cr Coefficients' => true,
        'YCbCrPositioning' => true,
        'YCb Cr Positioning' => true,
        'PrimaryChromaticities' => true,
        'Primary Chromaticities' => true,
        'WhitePoint' => true,
        'White Point' => true,
        'ExifOffset' => true,
        'Exif Offset' => true,
    ];

    if (function_exists('exif_read_data') && gallery_file_may_have_exif($path)) {
        $exif = @exif_read_data($path, null, true);
        if (is_array($exif)) {
            // Priority pass: preferred tags in order
            foreach ($preferred as $key => $label) {
                foreach (['IFD0', 'EXIF', 'COMPUTED', 'WINXP'] as $section) {
                    if (!isset($exif[$section][$key])) {
                        continue;
                    }
                    $value = gallery_format_exif_value($key, $exif[$section][$key]);
                    $add($out['fields'], $seen, $label, $value);
                    break;
                }
            }

            // GPS summary
            if (!empty($exif['GPS']['GPSLatitude']) && !empty($exif['GPS']['GPSLongitude'])) {
                $lat = gallery_gps_to_deg($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef'] ?? 'N');
                $lon = gallery_gps_to_deg($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef'] ?? 'E');
                if ($lat !== null && $lon !== null) {
                    $add($out['fields'], $seen, 'GPS', sprintf('%.6f, %.6f', $lat, $lon));
                }
            }
            if (!empty($exif['GPS']['GPSAltitude'])) {
                $alt = gallery_format_exif_value('GPSAltitude', $exif['GPS']['GPSAltitude']);
                $ref = $exif['GPS']['GPSAltitudeRef'] ?? 0;
                if ($alt !== '') {
                    $add($out['fields'], $seen, 'Altitude', $alt . ((string) $ref === '1' ? ' m below sea level' : ' m'));
                }
            }

            // Walk remaining sections/keys
            foreach ($exif as $section => $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                if ($section === 'THUMBNAIL' || $section === 'MAKERNOTE' || $section === 'COMPUTED') {
                    continue;
                }
                foreach ($entries as $key => $raw) {
                    if (!is_string($key)) {
                        continue;
                    }
                    if (isset($skipKeys[$key]) || isset($preferred[$key])) {
                        continue;
                    }
                    // Skip binary / huge blobs and encoded maker notes
                    if (is_string($raw) && (strlen($raw) > 400 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $raw))) {
                        continue;
                    }
                    if (is_array($raw) && count($raw) > 32) {
                        continue;
                    }
                    if (stripos($key, 'MakerNote') !== false || stripos($key, 'UndefinedTag') === 0 && $key !== 'UndefinedTag:0xA434') {
                        // Keep LensModel alias only; skip other undefined binary-ish tags unless short
                        if ($key !== 'UndefinedTag:0xA434') {
                            $probe = gallery_format_exif_value($key, $raw);
                            if ($probe === '' || strlen($probe) > 80) {
                                continue;
                            }
                        }
                    }
                    $label = gallery_humanize_exif_key($key);
                    if ($section === 'GPS' && in_array($key, ['GPSLatitude', 'GPSLongitude', 'GPSLatitudeRef', 'GPSLongitudeRef', 'GPSAltitude', 'GPSAltitudeRef'], true)) {
                        continue; // already summarized
                    }
                    $value = gallery_format_exif_value($key, $raw);
                    if ($section !== 'IFD0' && $section !== 'EXIF' && $section !== 'COMPUTED' && $section !== 'GPS' && $section !== 'WINXP') {
                        $label = $section . ': ' . $label;
                    }
                    $add($out['fields'], $seen, $label, $value);
                }
            }
        }
    }

    // IPTC from getimagesize APP13 if present
    if (!empty($info['APP13']) && function_exists('iptcparse')) {
        $iptc = @iptcparse($info['APP13']);
        if (is_array($iptc)) {
            $iptcMap = [
                '2#005' => 'IPTC title',
                '2#025' => 'Keywords',
                '2#080' => 'IPTC author',
                '2#116' => 'IPTC copyright',
                '2#120' => 'IPTC caption',
            ];
            foreach ($iptcMap as $code => $label) {
                if (empty($iptc[$code][0])) {
                    continue;
                }
                $add($out['fields'], $seen, $label, gallery_format_exif_value($label, $iptc[$code][0]));
            }
        }
    }

    $out['has_fields'] = count($out['fields']) > 0;
    return $out;
}

function gallery_humanize_exif_key(string $key): string
{
    $key = preg_replace('/^UndefinedTag:/', '', $key) ?? $key;
    $key = str_replace('_', ' ', $key);
    $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key) ?? $key;
    return ucfirst(trim($key));
}

function gallery_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

function gallery_format_exif_value(string $key, $raw): string
{
    if (is_array($raw)) {
        if (in_array($key, ['ISOSpeedRatings', 'PhotographicSensitivity'], true) && isset($raw[0])) {
            return (string) $raw[0];
        }
        // LensSpecification often 4 rationals
        if ($key === 'LensSpecification') {
            $parts = [];
            foreach ($raw as $part) {
                $parts[] = gallery_format_exif_value('FNumber', $part);
            }
            return implode(' / ', array_filter($parts));
        }
        $raw = implode(' ', array_map('strval', $raw));
    }
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    // Decode UTF-16 UserComment prefix UNICODE\0…
    if ($key === 'UserComment' && strncmp($raw, 'UNICODE', 7) === 0) {
        $raw = trim(substr($raw, 7));
    }

    if (in_array($key, ['FNumber', 'MaxApertureValue'], true)) {
        $num = gallery_exif_rational_to_float($raw);
        if ($num !== null && $num > 0) {
            if ($key === 'MaxApertureValue') {
                // APEX aperture → f-number ≈ 2^(value/2)
                $f = pow(2, $num / 2);
                return 'ƒ/' . rtrim(rtrim(number_format($f, 1, '.', ''), '0'), '.');
            }
            return 'ƒ/' . rtrim(rtrim(number_format($num, 1, '.', ''), '0'), '.');
        }
    }
    if (in_array($key, ['FocalLength', 'FocalLengthIn35mmFilm'], true)) {
        if (is_numeric($raw)) {
            return round((float) $raw) . ' mm';
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $raw, $m) && (int) $m[2] > 0) {
            return round((int) $m[1] / (int) $m[2]) . ' mm';
        }
    }
    if ($key === 'ExposureTime' || $key === 'ShutterSpeedValue') {
        $num = gallery_exif_rational_to_float($raw);
        if ($num !== null && $num > 0) {
            if ($key === 'ShutterSpeedValue') {
                // APEX shutter → seconds = 2^(-value)
                $sec = pow(2, -$num);
            } else {
                $sec = $num;
            }
            if ($sec > 0 && $sec < 1) {
                return '1/' . max(1, (int) round(1 / $sec)) . ' s';
            }
            return rtrim(rtrim(number_format($sec, 2, '.', ''), '0'), '.') . ' s';
        }
    }
    if ($key === 'ExposureBiasValue') {
        $num = gallery_exif_rational_to_float($raw);
        if ($num !== null) {
            return sprintf('%+.1f EV', $num);
        }
    }
    if ($key === 'DigitalZoomRatio') {
        $num = gallery_exif_rational_to_float($raw);
        if ($num !== null) {
            return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.') . '×';
        }
    }
    if (in_array($key, ['DateTimeOriginal', 'DateTime', 'DateTimeDigitized'], true) && preg_match('/^\d{4}:\d{2}:\d{2}/', $raw)) {
        return str_replace(':', '-', substr($raw, 0, 10)) . substr($raw, 10);
    }

    $enums = [
        'ExposureProgram' => [0 => 'Not defined', 1 => 'Manual', 2 => 'Normal', 3 => 'Aperture priority', 4 => 'Shutter priority', 5 => 'Creative', 6 => 'Action', 7 => 'Portrait', 8 => 'Landscape'],
        'MeteringMode' => [0 => 'Unknown', 1 => 'Average', 2 => 'Center-weighted', 3 => 'Spot', 4 => 'Multi-spot', 5 => 'Pattern', 6 => 'Partial'],
        'WhiteBalance' => [0 => 'Auto', 1 => 'Manual'],
        'ExposureMode' => [0 => 'Auto', 1 => 'Manual', 2 => 'Auto bracket'],
        'ColorSpace' => [1 => 'sRGB', 65535 => 'Uncalibrated'],
        'ResolutionUnit' => [2 => 'inches', 3 => 'cm'],
        'Orientation' => [1 => 'Normal', 3 => 'Rotate 180', 6 => 'Rotate 90 CW', 8 => 'Rotate 90 CCW'],
        'SceneCaptureType' => [0 => 'Standard', 1 => 'Landscape', 2 => 'Portrait', 3 => 'Night'],
    ];
    if (isset($enums[$key]) && is_numeric($raw) && isset($enums[$key][(int) $raw])) {
        return $enums[$key][(int) $raw];
    }

    // Strip control chars
    $raw = preg_replace('/[\x00-\x1F\x7F]/', '', $raw) ?? $raw;
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($raw, 0, 240);
    }
    return substr($raw, 0, 240);
}

function gallery_exif_rational_to_float(string $raw): ?float
{
    if (is_numeric($raw)) {
        return (float) $raw;
    }
    if (preg_match('/^(-?\d+)\/(\d+)$/', $raw, $m) && (int) $m[2] > 0) {
        return (float) $m[1] / (float) $m[2];
    }
    return null;
}

function gallery_gps_to_deg($coord, string $ref): ?float
{
    if (!is_array($coord) || count($coord) < 3) {
        return null;
    }
    $parts = [];
    foreach (array_slice($coord, 0, 3) as $part) {
        if (is_string($part) && preg_match('/^(\d+)\/(\d+)$/', $part, $m) && (int) $m[2] > 0) {
            $parts[] = (int) $m[1] / (int) $m[2];
        } elseif (is_numeric($part)) {
            $parts[] = (float) $part;
        } else {
            return null;
        }
    }
    $deg = $parts[0] + ($parts[1] / 60) + ($parts[2] / 3600);
    $ref = strtoupper($ref);
    if ($ref === 'S' || $ref === 'W') {
        $deg *= -1;
    }
    return $deg;
}

function gallery_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gallery_delete_photo(string $slug, string $file): void
{
    $slug = gallery_safe_album_slug($slug);
    $file = gallery_safe_filename($file);
    if ($slug === null || $file === null) {
        throw new RuntimeException('Photo not found.');
    }
    $path = gallery_albums_root() . '/' . $slug . '/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException('Photo not found.');
    }
    if (!@unlink($path)) {
        throw new RuntimeException('Could not delete photo file.');
    }

    $meta = gallery_load_album_meta($slug);
    if (isset($meta['captions'][$file])) {
        unset($meta['captions'][$file]);
    }
    $base = pathinfo($file, PATHINFO_FILENAME);
    if (isset($meta['captions'][$base])) {
        unset($meta['captions'][$base]);
    }
    // Only persist private meta if album still has files or meta already existed
    if (gallery_list_album_files($slug) || is_file(gallery_private_dir() . '/albums/' . $slug . '.json')) {
        gallery_save_album_meta($slug, $meta);
    }
}

function gallery_delete_album(string $slug): void
{
    $slug = gallery_safe_album_slug($slug);
    if ($slug === null) {
        throw new RuntimeException('Album not found.');
    }
    $dir = gallery_albums_root() . '/' . $slug;
    $scan = scandir($dir);
    if ($scan === false) {
        throw new RuntimeException('Could not read album folder.');
    }
    foreach ($scan as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('Could not delete ' . $entry);
        }
        if (is_dir($path)) {
            throw new RuntimeException('Album contains unexpected subfolders.');
        }
    }
    if (!@rmdir($dir)) {
        throw new RuntimeException('Could not remove album folder.');
    }
    $privateMeta = gallery_private_dir() . '/albums/' . $slug . '.json';
    if (is_file($privateMeta)) {
        @unlink($privateMeta);
    }
}
