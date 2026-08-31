<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$checks = [
    'storage_writable' => is_writable(STORAGE_PATH),
    'cache_writable' => is_writable(CACHE_PATH),
    'logs_writable' => is_writable(LOGS_PATH),
];

$status = ($checks['storage_writable'] && $checks['cache_writable'] && $checks['logs_writable']) ? 'ok' : 'attention';

$verboseAllowed = !empty($_ENV['PUBLIC_HEALTH_VERBOSE'])
    || (function_exists('admin_auth_is_logged_in') && admin_auth_is_logged_in());

$response = [
    'status' => $status,
    'time' => date('c'),
];

if ($verboseAllowed) {
    $response['site'] = SITE_NAME;
    $response['checks'] = array_merge($checks, [
        'products' => count(all_products()),
        'articles' => count(all_articles()),
    ]);
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
