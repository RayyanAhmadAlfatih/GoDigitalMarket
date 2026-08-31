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

if (!function_exists('inquiry_store') || !inquiry_enabled()) {
    $respond(['ok' => false, 'message' => 'Form inquiry sedang tidak aktif.'], 503);
}

if (!verify_csrf()) {
    $respond(['ok' => false, 'message' => 'Sesi form sudah kedaluwarsa. Refresh halaman lalu coba lagi.'], 419);
}

if (inquiry_is_rate_limited()) {
    $respond(['ok' => false, 'message' => 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'], 429);
}

$errors = inquiry_validate_payload($_POST);
if ($errors) {
    $respond(['ok' => false, 'message' => implode(' ', $errors)], 422);
}

$inquiry = inquiry_normalize_payload($_POST);
$stored = inquiry_store($inquiry);

if (!$stored) {
    $respond(['ok' => false, 'message' => 'Inquiry belum bisa disimpan. Coba lagi beberapa saat.'], 500);
}

inquiry_touch_rate_limit();

if (function_exists('notification_send_inquiry_created')) {
    notification_send_inquiry_created($inquiry);
}

if (function_exists('marketing_integration_dispatch_inquiry')) {
    marketing_integration_dispatch_inquiry($inquiry);
}

if (function_exists('conversion_store_event')) {
    conversion_store_event(conversion_normalize_event([
        'source' => (string)($inquiry['source'] ?? 'form-inquiry'),
        'type' => 'form_submit',
        'channel' => 'form',
        'category' => (string)($inquiry['category'] ?? ''),
        'location' => (string)($inquiry['location'] ?? ''),
        'intent' => (string)($inquiry['intent'] ?? 'inquiry'),
        'label' => (string)($inquiry['label'] ?? 'Form Inquiry'),
        'cta_deployment_id' => (string)($_POST['cta_deployment_id'] ?? ''),
        'offer_variant_id' => (string)($_POST['offer_variant_id'] ?? ''),
        'cta_placement' => (string)($_POST['cta_placement'] ?? ''),
        'page_path' => (string)($inquiry['page_path'] ?? ''),
        'target_url' => url('inquiry-submit'),
        'event_id' => (string)($_POST['server_event_id'] ?? ''),
    ]));
}

$successMessage = inquiry_clean((string)($_POST['lp_success_text'] ?? ''), 220);
if ($successMessage === '') {
    $successMessage = 'Terima kasih, inquiry sudah masuk. Tim kami akan menindaklanjuti melalui kontak yang Anda isi.';
}

$respond([
    'ok' => true,
    'message' => $successMessage,
    'id' => (string)$inquiry['id'],
]);
