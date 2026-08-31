<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$filename = basename(str_replace(['..', '\\', '/'], '', (string)($_GET['file'] ?? '')));
$path = function_exists('custom_form_upload_absolute_path') ? custom_form_upload_absolute_path($filename) : null;

if (!$path) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'File tidak ditemukan.';
    exit;
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
    'zip' => 'application/zip',
];
$mime = $mimeMap[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
readfile($path);
exit;
