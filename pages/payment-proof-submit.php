<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';

$respond = static function (array $data, int $status = 200) use ($wantsJson): void {
    http_response_code($status);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (!empty($data['redirect_url']) && $status >= 200 && $status < 300) {
        header('Location: ' . (string)$data['redirect_url']);
        exit;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>Upload Bukti Pembayaran</title><body style="font-family:Arial,sans-serif;padding:24px"><h1>' . ($status >= 400 ? 'Upload belum berhasil' : 'Upload berhasil') . '</h1><p>' . esc((string)($data['message'] ?? 'Terjadi kendala.')) . '</p><p><a href="' . esc((string)($_SERVER['HTTP_REFERER'] ?? url('invoice'))) . '">Kembali ke invoice</a></p></body>';
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    return;
}

if (!function_exists('payment_proof_store') || !payment_proof_enabled()) {
    $respond(['ok' => false, 'message' => 'Upload bukti pembayaran sedang tidak aktif.'], 503);
}

if (!verify_csrf()) {
    $respond(['ok' => false, 'message' => 'Sesi form sudah kedaluwarsa. Refresh halaman invoice lalu coba lagi.'], 419);
}

if (payment_proof_is_rate_limited()) {
    $respond(['ok' => false, 'message' => 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'], 429);
}

$ref = order_clean((string)($_POST['ref'] ?? ''), 80);
$token = order_clean((string)($_POST['token'] ?? ''), 80);
$order = function_exists('order_find_by_reference') ? order_find_by_reference($ref, $token) : null;
$file = is_array($_FILES['proof_file'] ?? null) ? $_FILES['proof_file'] : [];

$errors = payment_proof_validate_payload($_POST, $file, is_array($order) ? $order : null);
if ($errors) {
    $respond(['ok' => false, 'message' => implode(' ', $errors)], 422);
}

$proofId = 'pf_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
$fileInfo = payment_proof_store_file($file, $proofId);
if (empty($fileInfo['ok'])) {
    $respond(['ok' => false, 'message' => (string)($fileInfo['message'] ?? 'File bukti pembayaran belum bisa disimpan.')], 500);
}

$proof = payment_proof_normalize_payload(array_merge($_POST, ['proof_id' => $proofId]), $fileInfo, $order);
$proof['id'] = $proofId;

if (!payment_proof_store($proof)) {
    $respond(['ok' => false, 'message' => 'Bukti pembayaran belum bisa dicatat. Coba lagi beberapa saat.'], 500);
}

payment_proof_touch_rate_limit();

if (function_exists('conversion_store_event')) {
    conversion_store_event(conversion_normalize_event([
        'source' => 'public-invoice',
        'type' => 'payment_proof_submitted',
        'channel' => 'payment',
        'category' => (string)($order['category'] ?? ''),
        'location' => (string)($order['location'] ?? ''),
        'intent' => 'payment-proof-submit',
        'label' => (string)($proof['order_ref'] ?? $ref),
        'page_path' => (string)($proof['page_path'] ?? ''),
        'target_url' => url('payment-proof-submit'),
        'event_id' => (string)($_POST['server_event_id'] ?? ''),
    ]));
}

$redirect = order_public_invoice_url($order) . '&proof=success';
$respond([
    'ok' => true,
    'message' => 'Bukti pembayaran berhasil dikirim. Admin akan melakukan pengecekan manual.',
    'id' => (string)$proof['id'],
    'redirect_url' => $redirect,
]);
