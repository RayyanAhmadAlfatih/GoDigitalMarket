<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';

$respond = static function (array $data, int $status = 200) use ($wantsJson): void {
    http_response_code($status);
    if ($wantsJson || (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    return;
}

if (!function_exists('order_store') || !order_enabled()) {
    $respond(['ok' => false, 'message' => 'Form pemesanan sedang tidak aktif.'], 503);
}

if (!verify_csrf()) {
    $respond(['ok' => false, 'message' => 'Sesi form sudah kedaluwarsa. Refresh halaman lalu coba lagi.'], 419);
}

if (order_is_rate_limited()) {
    $respond(['ok' => false, 'message' => 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'], 429);
}

$errors = order_validate_payload($_POST);
if ($errors) {
    $respond(['ok' => false, 'message' => implode(' ', $errors)], 422);
}

$order = order_normalize_payload($_POST);
$stored = order_store($order);

if (!$stored) {
    $respond(['ok' => false, 'message' => 'Order belum bisa disimpan. Coba lagi beberapa saat.'], 500);
}

order_touch_rate_limit();

$gatewayResult = null;
if (function_exists('payment_gateway_create_charge_for_order')) {
    $gatewayCheck = function_exists('payment_gateway_order_can_create_charge') ? payment_gateway_order_can_create_charge($order) : ['allowed' => false];
    if (!empty($gatewayCheck['allowed'])) {
        $gatewayResult = payment_gateway_create_charge_for_order($order);
        if (!empty($gatewayResult['ok']) && !empty($gatewayResult['payment_url'])) {
            $freshOrder = function_exists('order_find_by_id') ? order_find_by_id((string)$order['id']) : null;
            if (is_array($freshOrder)) {
                $order = $freshOrder;
            }
        }
    }
}

if (function_exists('notification_send_order_created')) {
    notification_send_order_created($order);
}

if (function_exists('marketing_integration_dispatch_order')) {
    marketing_integration_dispatch_order($order);
}

if (function_exists('conversion_store_event')) {
    conversion_store_event(conversion_normalize_event([
        'source' => (string)($order['source'] ?? 'product-order-form'),
        'type' => 'order_submit',
        'channel' => 'checkout',
        'category' => (string)($order['category'] ?? ''),
        'location' => (string)($order['location'] ?? ''),
        'intent' => (string)($order['intent'] ?? 'order-draft'),
        'label' => (string)($order['label'] ?? 'Order Draft'),
        'page_path' => (string)($order['page_path'] ?? ''),
        'target_url' => url('order-submit'),
        'event_id' => (string)($_POST['server_event_id'] ?? ''),
    ]));
}

$successUrl = function_exists('order_success_url') ? order_success_url($order) : url('order-success?ref=' . rawurlencode((string)$order['id']));
$redirectUrl = $successUrl;
$message = 'Terima kasih, data pemesanan awal sudah masuk. Anda akan diarahkan ke halaman konfirmasi order.';
if (is_array($gatewayResult) && !empty($gatewayResult['ok']) && !empty($gatewayResult['payment_url'])) {
    $redirectUrl = (string)$gatewayResult['payment_url'];
    $message = 'Order masuk. Anda akan diarahkan ke halaman pembayaran resmi provider.';
}

$respond([
    'ok' => true,
    'message' => $message,
    'id' => (string)$order['id'],
    'ref' => function_exists('order_public_reference') ? order_public_reference($order) : (string)$order['id'],
    'redirect_url' => $redirectUrl,
    'order_success_url' => $successUrl,
    'payment_gateway' => is_array($gatewayResult) ? [
        'ok' => !empty($gatewayResult['ok']),
        'provider' => (string)($gatewayResult['provider'] ?? ''),
        'message' => (string)($gatewayResult['message'] ?? ''),
    ] : null,
]);
