<?php
/**
 * Site feedback — stored outside the public web tree.
 */

require_once __DIR__ . '/gallery-lib.php';

function feedback_private_dir(): string
{
    $serverParent = '/home/chuckneedham/private';
    $server = $serverParent . '/chuck-feedback';
    if (is_dir($serverParent)) {
        if (!is_dir($server)) {
            @mkdir($server, 0700, true);
        }
        if (is_dir($server)) {
            return $server;
        }
    }
    $local = __DIR__ . '/private-data/feedback';
    if (!is_dir($local)) {
        @mkdir($local, 0700, true);
    }
    return $local;
}

function feedback_data_path(): string
{
    return feedback_private_dir() . '/messages.json';
}

function feedback_load(): array
{
    $path = feedback_data_path();
    if (!is_file($path)) {
        return ['messages' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !isset($data['messages']) || !is_array($data['messages'])) {
        return ['messages' => []];
    }
    return $data;
}

function feedback_save(array $data): void
{
    $path = feedback_data_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Could not encode feedback.');
    }
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not write feedback.');
    }
    if (!rename($tmp, $path)) {
        throw new RuntimeException('Could not save feedback.');
    }
    @chmod($path, 0600);
}

function feedback_new_id(): string
{
    return bin2hex(random_bytes(8));
}

function feedback_clean_body(string $text, int $max = 4000): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = trim($text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max);
    }
    return substr($text, 0, $max);
}

function feedback_rate_limited(): bool
{
    $path = feedback_private_dir() . '/rate.json';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $ip . '|chuck-feedback');
    $now = time();
    $window = 600; // 10 minutes
    $max = 5;

    $data = ['hits' => []];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) {
            $data = $decoded;
        }
    }

    $recent = [];
    foreach ($data['hits'] as $hitKey => $times) {
        if (!is_array($times)) {
            continue;
        }
        $kept = array_values(array_filter($times, static function ($t) use ($now, $window) {
            return is_int($t) && ($now - $t) < $window;
        }));
        if ($kept) {
            $recent[$hitKey] = $kept;
        }
    }

    $mine = $recent[$key] ?? [];
    if (count($mine) >= $max) {
        $data['hits'] = $recent;
        @file_put_contents($path, json_encode($data) . "\n", LOCK_EX);
        return true;
    }

    $mine[] = $now;
    $recent[$key] = $mine;
    $data['hits'] = $recent;
    @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT) . "\n", LOCK_EX);
    @chmod($path, 0600);
    return false;
}

function feedback_delete(string $id): bool
{
    $data = feedback_load();
    $before = count($data['messages']);
    $data['messages'] = array_values(array_filter($data['messages'], static function ($m) use ($id) {
        return !is_array($m) || (($m['id'] ?? '') !== $id);
    }));
    if (count($data['messages']) === $before) {
        return false;
    }
    feedback_save($data);
    return true;
}

function feedback_sorted(array $data): array
{
    $messages = $data['messages'] ?? [];
    usort($messages, static function ($a, $b) {
        return strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
    });
    return $messages;
}
