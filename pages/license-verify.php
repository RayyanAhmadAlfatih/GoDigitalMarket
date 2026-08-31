<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (function_exists('page_cache_abort')) { page_cache_abort(); }

header('Content-Type: application/json; charset=utf-8');

if (function_exists('public_endpoint_body_too_large') && public_endpoint_body_too_large(32 * 1024)) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'message' => 'Payload terlalu besar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (function_exists('public_endpoint_is_rate_limited') && public_endpoint_is_rate_limited('license-verify', 180, 3600, 0)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Terlalu banyak request. Coba lagi beberapa saat.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (function_exists('public_endpoint_touch_rate_limit')) {
    public_endpoint_touch_rate_limit('license-verify', 3600);
}

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['POST', 'GET'], true)) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = (string)file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = array_merge($_GET, $_POST);
}

$licenseKey = (string)($body['license_key'] ?? $body['key'] ?? '');
$domain = (string)($body['domain'] ?? $body['site_domain'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
$result = function_exists('license_manager_verify') ? license_manager_verify($licenseKey, $domain, [
    'site_url' => (string)($body['site_url'] ?? ''),
    'product_slug' => (string)($body['product_slug'] ?? ''),
    'version' => (string)($body['version'] ?? ''),
]) : ['ok' => false, 'message' => 'License Manager belum tersedia.'];

http_response_code(!empty($result['ok']) ? 200 : 422);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
