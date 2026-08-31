<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (function_exists('page_cache_abort')) { page_cache_abort(); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Method tidak valid.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_csrf();

    $payload = $_POST;
    $estimate = shipping_estimate($payload);
    $public = shipping_estimate_public_payload($estimate);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => 'Estimasi ongkir berhasil dihitung.',
        'estimate' => $public,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage() ?: 'Estimasi ongkir belum bisa dihitung.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
