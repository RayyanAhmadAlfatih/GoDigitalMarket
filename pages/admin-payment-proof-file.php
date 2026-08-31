<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

if (empty($_SESSION['admin_articles_logged_in'])) {
    http_response_code(403);
    echo 'Forbidden';
    return;
}

$relative = payment_proof_clean((string)($_GET['file'] ?? ''), 240);
$path = function_exists('payment_proof_file_absolute_path') ? payment_proof_file_absolute_path($relative) : null;
if (!$path || !is_file($path)) {
    http_response_code(404);
    echo 'File not found';
    return;
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = (string)finfo_file($finfo, $path);
        finfo_close($finfo);
    }
}
if ($mime === '' && function_exists('mime_content_type')) {
    $mime = (string)mime_content_type($path);
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
