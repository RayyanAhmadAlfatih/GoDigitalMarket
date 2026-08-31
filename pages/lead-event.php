<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LIGHTWEIGHT LEAD EVENT ENDPOINT
|--------------------------------------------------------------------------
| Receives anonymous CTA click events from the frontend. This endpoint is
| intentionally simple: it does not store names, phone numbers, or message
| contents. It only helps the owner understand which pages/CTA positions send
| users toward WhatsApp or conversion pages.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (function_exists('page_cache_abort')) { page_cache_abort(); }

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    return;
}

if (function_exists('public_endpoint_body_too_large') && public_endpoint_body_too_large(16 * 1024)) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'message' => 'Payload terlalu besar.']);
    return;
}

if (function_exists('public_endpoint_is_rate_limited') && public_endpoint_is_rate_limited('lead-event', 300, 3600, 0)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Terlalu banyak event.']);
    return;
}
if (function_exists('public_endpoint_touch_rate_limit')) {
    public_endpoint_touch_rate_limit('lead-event', 3600);
}

if (!function_exists('conversion_store_event')) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'stored' => false]);
    return;
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$event = conversion_normalize_event($payload);
$stored = conversion_store_event($event);

echo json_encode([
    'ok' => true,
    'stored' => $stored,
]);
