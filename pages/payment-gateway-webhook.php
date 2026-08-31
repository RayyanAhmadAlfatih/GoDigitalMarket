<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (function_exists('page_cache_abort')) { page_cache_abort(); }

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$provider = payment_gateway_slug((string)($_GET['provider'] ?? $_GET['gateway'] ?? ''));
if ($provider === 'manual') {
    $provider = payment_gateway_slug((string)($_SERVER['HTTP_X_PAYMENT_PROVIDER'] ?? 'midtrans'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'ok' => true,
        'message' => 'Payment gateway webhook endpoint ready.',
        'provider' => $provider,
        'note' => 'Gunakan POST JSON dari provider payment gateway.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (function_exists('public_endpoint_body_too_large') && public_endpoint_body_too_large(512 * 1024)) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'message' => 'Payload terlalu besar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (function_exists('public_endpoint_is_rate_limited') && public_endpoint_is_rate_limited('payment-gateway-webhook-' . $provider, 600, 3600, 0)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Terlalu banyak request webhook.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (function_exists('public_endpoint_touch_rate_limit')) {
    public_endpoint_touch_rate_limit('payment-gateway-webhook-' . $provider, 3600);
}

$settings = payment_gateway_read_settings();
if (empty($settings['enabled'])) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Payment gateway bridge belum aktif.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rawBody = (string)file_get_contents('php://input');
if (trim($rawBody) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payload kosong.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$decoded = json_decode($rawBody, true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payload JSON tidak valid.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$result = payment_gateway_process_webhook($provider, $rawBody, payment_gateway_extract_headers());
$event = is_array($result['event'] ?? null) ? $result['event'] : [];

http_response_code(!empty($event['verified']) ? 200 : 202);
echo json_encode([
    'ok' => true,
    'accepted' => true,
    'verified' => !empty($event['verified']),
    'provider' => (string)($event['provider'] ?? $provider),
    'signature_status' => (string)($event['signature_status'] ?? 'unknown'),
    'reference' => (string)($event['reference'] ?? ''),
    'order_updated' => !empty($event['order_updated']),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
