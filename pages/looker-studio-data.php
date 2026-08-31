<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (function_exists('page_cache_abort')) {
    page_cache_abort();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
header('X-Content-Type-Options: nosniff', true);

function looker_studio_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{"ok":false}';
    exit;
}

function looker_studio_request_token(): string
{
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim($m[1]);
    }
    foreach (['HTTP_X_UGROWTH_LOOKER_TOKEN', 'HTTP_X_UGROWTH_TOKEN'] as $header) {
        $value = trim((string)($_SERVER[$header] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    $queryToken = trim((string)($_GET['token'] ?? ''));
    if ($queryToken !== '') {
        return $queryToken;
    }
    return '';
}

if (!function_exists('looker_studio_report')) {
    looker_studio_json_response(['ok' => false, 'message' => 'Looker Studio connector belum tersedia.'], 503);
}

$expectedToken = looker_studio_connector_token();
if ($expectedToken === '') {
    looker_studio_json_response(['ok' => false, 'message' => 'Token Looker Studio belum dikonfigurasi di U-Growth.'], 503);
}

if (!looker_studio_connector_enabled()) {
    looker_studio_json_response(['ok' => false, 'message' => 'Koneksi langsung Looker Studio belum diaktifkan di dashboard.'], 403);
}

$givenToken = looker_studio_request_token();
if ($givenToken === '' || !hash_equals($expectedToken, $givenToken)) {
    looker_studio_json_response(['ok' => false, 'message' => 'Token Looker Studio tidak valid.'], 401);
}

$action = strtolower(trim((string)($_GET['action'] ?? 'data')));
$source = preg_replace('/[^a-zA-Z0-9_]+/', '', trim((string)($_GET['source'] ?? 'orders'))) ?: 'orders';
$limit = max(1, min(5000, (int)($_GET['limit'] ?? 1000)));
$fields = [];
if (!empty($_GET['fields'])) {
    $fields = array_values(array_filter(array_map(static function (string $field): string {
        return preg_replace('/[^a-zA-Z0-9_]+/', '', trim($field)) ?: '';
    }, explode(',', (string)$_GET['fields']))));
}

if ($action === 'status') {
    looker_studio_json_response(['ok' => true, 'report' => looker_studio_report()]);
}

if ($action === 'sources') {
    looker_studio_json_response(['ok' => true, 'sources' => looker_studio_sources_payload()]);
}

if ($action === 'blueprints') {
    looker_studio_json_response(['ok' => true, 'blueprints' => function_exists('looker_studio_dashboard_blueprints') ? looker_studio_dashboard_blueprints() : []]);
}

if ($action === 'readiness') {
    looker_studio_json_response(['ok' => true, 'readiness' => function_exists('looker_studio_visual_readiness') ? looker_studio_visual_readiness() : []]);
}

if (!looker_studio_source_meta($source)) {
    looker_studio_json_response(['ok' => false, 'message' => 'Sumber data tidak dikenali.', 'source' => $source], 404);
}

$rows = looker_studio_rows($source, $limit, $fields);
$schema = $fields ? array_values(array_filter(looker_studio_schema($source), static fn(array $field): bool => in_array((string)($field['name'] ?? ''), $fields, true))) : looker_studio_schema($source);

if ($action === 'schema') {
    looker_studio_json_response([
        'ok' => true,
        'source' => $source,
        'schema' => $schema,
        'records' => count($rows),
    ]);
}

if ($action === 'preview') {
    looker_studio_json_response(function_exists('looker_studio_source_preview') ? looker_studio_source_preview($source, min(10, $limit)) : [
        'ok' => true,
        'source' => $source,
        'schema' => $schema,
        'rows' => array_slice($rows, 0, min(10, $limit)),
        'records' => count($rows),
    ]);
}

looker_studio_json_response([
    'ok' => true,
    'source' => $source,
    'schema' => $schema,
    'rows' => $rows,
    'records' => count($rows),
    'request_id' => substr(hash('sha256', $source . '|' . implode(',', $fields) . '|' . count($rows) . '|' . date('YmdHi')), 0, 12),
    'exported_at' => date(DATE_ATOM),
]);
