<?php
require_once __DIR__ . '/gallery-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30');

echo json_encode(gallery_catalog(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
