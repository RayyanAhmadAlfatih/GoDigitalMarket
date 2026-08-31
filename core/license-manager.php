<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DOMAIN-LOCKED LICENSE MANAGER - U-Growth
|--------------------------------------------------------------------------
| Supports local license activation for UMKM that are not ready with a
| central server, and central/hybrid mode for software/template sellers.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('license_manager_clean')) {
 function license_manager_clean(string $value, int $max = 220): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 return $value === '' ? '' : (function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max));
 }
}

if (!function_exists('license_manager_settings_file')) {
 function license_manager_settings_file(): string
 {
 return STORAGE_PATH . '/license-manager-settings.json';
 }
}

if (!function_exists('license_manager_records_file')) {
 function license_manager_records_file(): string
 {
 return STORAGE_PATH . '/license-activations.json';
 }
}

if (!function_exists('license_manager_log_file')) {
 function license_manager_log_file(?int $timestamp = null): string
 {
 return LOGS_PATH . '/license-manager-' . date('Y-m', $timestamp ?: time()) . '.jsonl';
 }
}

if (!function_exists('license_manager_default_settings')) {
 function license_manager_default_settings(): array
 {
 return [
 'enabled' => true,
 'mode' => 'hybrid', // local, central, hybrid
 'domain_lock_enabled' => true,
 'local_fallback_enabled' => true,
 'central_base_url' => '',
 'central_api_key' => '',
 'central_product_id' => '',
 'request_timeout_seconds' => 8,
 'local_activation_limit_default' => 1,
 'cache_verify_minutes' => 720,
 'allow_reset_by_admin' => true,
 'public_api_note' => 'Gunakan endpoint ini untuk aktivasi/verifikasi lisensi software/template. Mode hybrid akan mencoba server pusat dulu, lalu fallback lokal jika diizinkan.',
 ];
 }
}

if (!function_exists('license_manager_read_settings')) {
 function license_manager_read_settings(): array
 {
 $defaults = license_manager_default_settings();
 $file = license_manager_settings_file();
 if (!is_file($file)) {
 return $defaults;
 }
 $data = json_decode((string)@file_get_contents($file), true);
 if (!is_array($data)) {
 return $defaults;
 }
 $settings = array_merge($defaults, $data);
 if (!in_array((string)$settings['mode'], ['local', 'central', 'hybrid'], true)) {
 $settings['mode'] = 'hybrid';
 }
 $settings['request_timeout_seconds'] = max(2, min(30, (int)($settings['request_timeout_seconds'] ?? 8)));
 $settings['local_activation_limit_default'] = max(1, min(100, (int)($settings['local_activation_limit_default'] ?? 1)));
 $settings['cache_verify_minutes'] = max(5, min(10080, (int)($settings['cache_verify_minutes'] ?? 720)));
 return $settings;
 }
}

if (!function_exists('license_manager_write_settings')) {
 function license_manager_write_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $defaults = license_manager_default_settings();
 $mode = (string)($settings['mode'] ?? $defaults['mode']);
 if (!in_array($mode, ['local', 'central', 'hybrid'], true)) {
 $mode = 'hybrid';
 }
 $payload = [
 'enabled' => !empty($settings['enabled']),
 'mode' => $mode,
 'domain_lock_enabled' => !empty($settings['domain_lock_enabled']),
 'local_fallback_enabled' => !empty($settings['local_fallback_enabled']),
 'central_base_url' => rtrim(trim((string)($settings['central_base_url'] ?? '')), '/'),
 'central_api_key' => trim((string)($settings['central_api_key'] ?? '')),
 'central_product_id' => license_manager_clean((string)($settings['central_product_id'] ?? ''), 120),
 'request_timeout_seconds' => max(2, min(30, (int)($settings['request_timeout_seconds'] ?? $defaults['request_timeout_seconds']))),
 'local_activation_limit_default' => max(1, min(100, (int)($settings['local_activation_limit_default'] ?? $defaults['local_activation_limit_default']))),
 'cache_verify_minutes' => max(5, min(10080, (int)($settings['cache_verify_minutes'] ?? $defaults['cache_verify_minutes']))),
 'allow_reset_by_admin' => !empty($settings['allow_reset_by_admin']),
 'public_api_note' => trim(strip_tags((string)($settings['public_api_note'] ?? $defaults['public_api_note']))),
 ];
 return @file_put_contents(license_manager_settings_file(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('license_manager_read_records')) {
 function license_manager_read_records(): array
 {
 $file = license_manager_records_file();
 if (!is_file($file)) {
 return [];
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return is_array($data) ? $data : [];
 }
}

if (!function_exists('license_manager_write_records')) {
 function license_manager_write_records(array $records): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 return @file_put_contents(license_manager_records_file(), json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('license_manager_log_event')) {
 function license_manager_log_event(array $event): void
 {
 if (!is_dir(LOGS_PATH)) {
 @mkdir(LOGS_PATH, 0775, true);
 }
 $event['created_at'] = (string)($event['created_at'] ?? date('c'));
 @file_put_contents(license_manager_log_file(), json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
 }
}

if (!function_exists('license_manager_normalize_key')) {
 function license_manager_normalize_key(string $key): string
 {
 return strtoupper(preg_replace('/[^A-Z0-9\-]/', '', trim($key)) ?: '');
 }
}

if (!function_exists('license_manager_normalize_domain')) {
 function license_manager_normalize_domain(string $domain): string
 {
 $domain = strtolower(trim($domain));
 $domain = preg_replace('#^https?://#', '', $domain);
 $domain = preg_replace('#/.*$#', '', (string)$domain);
 $domain = preg_replace('/:\d+$/', '', (string)$domain);
 $domain = preg_replace('/^www\./', '', (string)$domain);
 return preg_match('/^[a-z0-9.-]+$/', (string)$domain) ? (string)$domain : '';
 }
}

if (!function_exists('license_manager_record_from_member_access')) {
 function license_manager_record_from_member_access(array $memberRecord): ?array
 {
 $license = is_array($memberRecord['license'] ?? null) ? $memberRecord['license'] : [];
 $key = license_manager_normalize_key((string)($license['key'] ?? ''));
 if ($key === '') {
 return null;
 }
 $records = license_manager_read_records();
 $existing = is_array($records[$key] ?? null) ? $records[$key] : [];
 $record = array_merge($existing, [
 'license_key' => $key,
 'status' => (string)($existing['status'] ?? 'active'),
 'source' => (string)($existing['source'] ?? 'member_area'),
 'order_id' => (string)($memberRecord['order_id'] ?? $existing['order_id'] ?? ''),
 'order_ref' => (string)($memberRecord['order_ref'] ?? $existing['order_ref'] ?? ''),
 'customer_email' => strtolower((string)($memberRecord['customer_email'] ?? $existing['customer_email'] ?? '')),
 'product_slug' => (string)($memberRecord['product_slug'] ?? $existing['product_slug'] ?? ''),
 'product_title' => (string)($memberRecord['product_title'] ?? $existing['product_title'] ?? ''),
 'license_type' => (string)($license['type'] ?? $existing['license_type'] ?? 'single_site'),
 'activation_limit' => max(1, (int)($license['activation_limit'] ?? $existing['activation_limit'] ?? 1)),
 'validation_mode' => (string)($license['validation_mode'] ?? $existing['validation_mode'] ?? 'global'),
 'domain_lock' => !empty($license['domain_lock']) || !empty($existing['domain_lock']),
 'central_product_id' => (string)($license['central_product_id'] ?? $existing['central_product_id'] ?? ''),
 'domains' => is_array($existing['domains'] ?? null) ? $existing['domains'] : [],
 'expires_at' => (string)($license['expires_at'] ?? $existing['expires_at'] ?? ''),
 'created_at' => (string)($existing['created_at'] ?? date('c')),
 'updated_at' => date('c'),
 ]);
 $records[$key] = $record;
 license_manager_write_records($records);
 return $record;
 }
}

if (!function_exists('license_manager_central_request')) {
 function license_manager_central_request(string $action, array $payload, array $settings = []): array
 {
 $settings = $settings ?: license_manager_read_settings();
 $base = rtrim((string)($settings['central_base_url'] ?? ''), '/');
 if ($base === '') {
 return ['ok' => false, 'message' => 'URL server lisensi pusat belum diisi.', 'source' => 'central'];
 }
 $url = $base . '/license/' . trim($action, '/');
 $payload['product_id'] = (string)($settings['central_product_id'] ?? $payload['product_id'] ?? '');
 $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
 $headers = [
 'Content-Type: application/json',
 'Accept: application/json',
 ];
 if ((string)($settings['central_api_key'] ?? '') !== '') {
 $headers[] = 'Authorization: Bearer ' . (string)$settings['central_api_key'];
 $headers[] = 'X-License-Api-Key: ' . (string)$settings['central_api_key'];
 }
 $timeout = (int)($settings['request_timeout_seconds'] ?? 8);
 $response = false;
 $httpCode = 0;
 if (function_exists('curl_init')) {
 $ch = curl_init($url);
 curl_setopt_array($ch, [
 CURLOPT_RETURNTRANSFER => true,
 CURLOPT_POST => true,
 CURLOPT_POSTFIELDS => $body,
 CURLOPT_HTTPHEADER => $headers,
 CURLOPT_TIMEOUT => $timeout,
 CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
 ]);
 $response = curl_exec($ch);
 $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
 curl_close($ch);
 } else {
 $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
 $response = @file_get_contents($url, false, $ctx);
 $httpCode = is_array($http_response_header ?? null) && preg_match('/\s(\d{3})\s/', (string)($http_response_header[0] ?? ''), $m) ? (int)$m[1] : 0;
 }
 if (!is_string($response) || $response === '') {
 return ['ok' => false, 'message' => 'Server lisensi pusat tidak merespons.', 'source' => 'central', 'http_code' => $httpCode];
 }
 $decoded = json_decode($response, true);
 if (!is_array($decoded)) {
 return ['ok' => false, 'message' => 'Respons server lisensi pusat tidak valid.', 'source' => 'central', 'http_code' => $httpCode];
 }
 $decoded['source'] = $decoded['source'] ?? 'central';
 $decoded['http_code'] = $httpCode;
 return $decoded;
 }
}

if (!function_exists('license_manager_local_activate')) {
 function license_manager_local_activate(string $licenseKey, string $domain, array $payload = []): array
 {
 $settings = license_manager_read_settings();
 $licenseKey = license_manager_normalize_key($licenseKey);
 $domain = license_manager_normalize_domain($domain);
 if ($licenseKey === '' || $domain === '') {
 return ['ok' => false, 'message' => 'License key atau domain tidak valid.', 'source' => 'local'];
 }
 $records = license_manager_read_records();
 $record = is_array($records[$licenseKey] ?? null) ? $records[$licenseKey] : null;
 if (!$record) {
 if (empty($payload['allow_create_local'])) {
 return ['ok' => false, 'message' => 'License key belum terdaftar di sistem lisensi lokal.', 'source' => 'local', 'status' => 'not_found'];
 }
 $record = [
 'license_key' => $licenseKey,
 'status' => 'active',
 'source' => 'manual_local',
 'activation_limit' => (int)($settings['local_activation_limit_default'] ?? 1),
 'domains' => [],
 'created_at' => date('c'),
 ];
 }
 if ((string)($record['status'] ?? 'active') !== 'active') {
 return ['ok' => false, 'message' => 'Lisensi tidak aktif.', 'source' => 'local', 'status' => (string)$record['status']];
 }
 if (!empty($record['expires_at']) && strtotime((string)$record['expires_at']) < time()) {
 $record['status'] = 'expired';
 $records[$licenseKey] = $record;
 license_manager_write_records($records);
 return ['ok' => false, 'message' => 'Lisensi sudah expired.', 'source' => 'local', 'status' => 'expired'];
 }
 $domains = is_array($record['domains'] ?? null) ? $record['domains'] : [];
 $limit = max(1, (int)($record['activation_limit'] ?? $settings['local_activation_limit_default'] ?? 1));
 if (!isset($domains[$domain]) && !empty($settings['domain_lock_enabled']) && count($domains) >= $limit) {
 return ['ok' => false, 'message' => 'Lisensi sudah terkunci untuk domain lain.', 'source' => 'local', 'status' => 'domain_limit_reached', 'domains' => array_keys($domains)];
 }
 $domains[$domain] = array_merge(is_array($domains[$domain] ?? null) ? $domains[$domain] : [], [
 'domain' => $domain,
 'site_url' => license_manager_clean((string)($payload['site_url'] ?? ''), 260),
 'activated_at' => (string)($domains[$domain]['activated_at'] ?? date('c')),
 'last_verified_at' => date('c'),
 'status' => 'active',
 ]);
 $record['domains'] = $domains;
 $record['last_domain'] = $domain;
 $record['updated_at'] = date('c');
 $records[$licenseKey] = $record;
 license_manager_write_records($records);
 license_manager_log_event(['type' => 'activate_local', 'license_key' => $licenseKey, 'domain' => $domain, 'status' => 'active']);
 return ['ok' => true, 'message' => 'Lisensi aktif untuk domain ini.', 'source' => 'local', 'status' => 'active', 'license_key' => $licenseKey, 'domain' => $domain, 'activation_limit' => $limit, 'domains' => array_keys($domains), 'expires_at' => (string)($record['expires_at'] ?? '')];
 }
}

if (!function_exists('license_manager_local_verify')) {
 function license_manager_local_verify(string $licenseKey, string $domain): array
 {
 $licenseKey = license_manager_normalize_key($licenseKey);
 $domain = license_manager_normalize_domain($domain);
 $records = license_manager_read_records();
 $record = is_array($records[$licenseKey] ?? null) ? $records[$licenseKey] : null;
 if (!$record || $domain === '') {
 return ['ok' => false, 'message' => 'Lisensi tidak ditemukan.', 'source' => 'local'];
 }
 if ((string)($record['status'] ?? 'active') !== 'active') {
 return ['ok' => false, 'message' => 'Lisensi tidak aktif.', 'source' => 'local', 'status' => (string)$record['status']];
 }
 if (!empty($record['expires_at']) && strtotime((string)$record['expires_at']) < time()) {
 return ['ok' => false, 'message' => 'Lisensi sudah expired.', 'source' => 'local', 'status' => 'expired'];
 }
 $domains = is_array($record['domains'] ?? null) ? $record['domains'] : [];
 if (!isset($domains[$domain])) {
 return ['ok' => false, 'message' => 'Domain ini belum terdaftar untuk lisensi tersebut.', 'source' => 'local', 'status' => 'domain_mismatch', 'domains' => array_keys($domains)];
 }
 $domains[$domain]['last_verified_at'] = date('c');
 $record['domains'] = $domains;
 $record['updated_at'] = date('c');
 $records[$licenseKey] = $record;
 license_manager_write_records($records);
 license_manager_log_event(['type' => 'verify_local', 'license_key' => $licenseKey, 'domain' => $domain, 'status' => 'valid']);
 return ['ok' => true, 'message' => 'Lisensi valid.', 'source' => 'local', 'status' => 'active', 'license_key' => $licenseKey, 'domain' => $domain, 'expires_at' => (string)($record['expires_at'] ?? '')];
 }
}

if (!function_exists('license_manager_activate')) {
 function license_manager_activate(string $licenseKey, string $domain, array $payload = []): array
 {
 $settings = license_manager_read_settings();
 if (empty($settings['enabled'])) {
 return ['ok' => false, 'message' => 'License Manager sedang nonaktif.'];
 }
 $mode = (string)($settings['mode'] ?? 'hybrid');
 $central = ['ok' => false];
 if (in_array($mode, ['central', 'hybrid'], true)) {
 $central = license_manager_central_request('activate', array_merge($payload, ['license_key' => $licenseKey, 'domain' => $domain]), $settings);
 if (!empty($central['ok'])) {
 license_manager_log_event(['type' => 'activate_central', 'license_key' => license_manager_normalize_key($licenseKey), 'domain' => license_manager_normalize_domain($domain), 'status' => 'active']);
 return $central;
 }
 if ($mode === 'central' || empty($settings['local_fallback_enabled'])) {
 return $central;
 }
 }
 $local = license_manager_local_activate($licenseKey, $domain, $payload);
 $local['central_attempt'] = $central;
 return $local;
 }
}

if (!function_exists('license_manager_verify')) {
 function license_manager_verify(string $licenseKey, string $domain, array $payload = []): array
 {
 $settings = license_manager_read_settings();
 if (empty($settings['enabled'])) {
 return ['ok' => false, 'message' => 'License Manager sedang nonaktif.'];
 }
 $mode = (string)($settings['mode'] ?? 'hybrid');
 $central = ['ok' => false];
 if (in_array($mode, ['central', 'hybrid'], true)) {
 $central = license_manager_central_request('verify', array_merge($payload, ['license_key' => $licenseKey, 'domain' => $domain]), $settings);
 if (!empty($central['ok'])) {
 return $central;
 }
 if ($mode === 'central' || empty($settings['local_fallback_enabled'])) {
 return $central;
 }
 }
 $local = license_manager_local_verify($licenseKey, $domain);
 $local['central_attempt'] = $central;
 return $local;
 }
}

if (!function_exists('license_manager_reset_domain')) {
 function license_manager_reset_domain(string $licenseKey, string $domain = ''): bool
 {
 $settings = license_manager_read_settings();
 if (empty($settings['allow_reset_by_admin'])) {
 return false;
 }
 $key = license_manager_normalize_key($licenseKey);
 $domain = license_manager_normalize_domain($domain);
 $records = license_manager_read_records();
 if (!is_array($records[$key] ?? null)) {
 return false;
 }
 if ($domain !== '') {
 unset($records[$key]['domains'][$domain]);
 } else {
 $records[$key]['domains'] = [];
 }
 $records[$key]['updated_at'] = date('c');
 $ok = license_manager_write_records($records);
 if ($ok) {
 license_manager_log_event(['type' => 'reset_domain', 'license_key' => $key, 'domain' => $domain]);
 }
 return $ok;
 }
}

if (!function_exists('license_manager_summary')) {
 function license_manager_summary(): array
 {
 $records = license_manager_read_records();
 $active = 0;
 $expired = 0;
 $domains = 0;
 $recent = [];
 foreach ($records as $record) {
 if (!is_array($record)) {
 continue;
 }
 if (!empty($record['expires_at']) && strtotime((string)$record['expires_at']) < time()) {
 $expired++;
 } elseif ((string)($record['status'] ?? 'active') === 'active') {
 $active++;
 }
 $domains += count((array)($record['domains'] ?? []));
 $recent[] = $record;
 }
 usort($recent, static fn($a, $b): int => strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? '')));
 return ['total' => count($records), 'active' => $active, 'expired' => $expired, 'domains' => $domains, 'recent' => array_slice($recent, 0, 12)];
 }
}
