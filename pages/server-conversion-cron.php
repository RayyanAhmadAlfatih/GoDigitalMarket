<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$token = function_exists('server_conversion_cron_token_from_request')
    ? server_conversion_cron_token_from_request()
    : (string)($_GET['token'] ?? '');
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : null;

$result = function_exists('server_conversion_process_cron_request')
    ? server_conversion_process_cron_request($token, $limit)
    : ['ok' => false, 'status' => 'unavailable', 'message' => 'Server conversion cron engine belum tersedia.', 'http_status' => 503];

$httpStatus = (int)($result['http_status'] ?? (!empty($result['ok']) ? 200 : 400));
http_response_code($httpStatus >= 100 && $httpStatus <= 599 ? $httpStatus : 400);

unset($result['http_status']);
echo json_encode([
    'time' => date('c'),
    'feature' => 'server_conversion_scheduled_retry',
] + $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

exit;
