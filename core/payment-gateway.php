<?php

declare(strict_types=1);

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| PAYMENT GATEWAY REAL CHARGE BRIDGE - Template
|--------------------------------------------------------------------------
| Lightweight file-based bridge for Indonesian payment gateways. This layer
| stores provider configuration, creates hosted payment links/tokens, logs
| inbound webhook attempts, verifies callback signatures/tokens where possible,
| and maps verified payment events into existing order payment statuses.
|--------------------------------------------------------------------------
*/

if (!function_exists('payment_gateway_settings_file')) {
 function payment_gateway_settings_file(): string
 {
 return STORAGE_PATH . '/payment-gateway-settings.json';
 }
}

if (!function_exists('payment_gateway_transaction_file')) {
 function payment_gateway_transaction_file(): string
 {
 return STORAGE_PATH . '/payment-gateway-transactions.json';
 }
}

if (!function_exists('payment_gateway_log_file')) {
 function payment_gateway_log_file(?int $timestamp = null): string
 {
 $timestamp = $timestamp ?: time();
 return LOGS_PATH . '/payment-gateway-webhooks-' . date('Y-m', $timestamp) . '.jsonl';
 }
}

if (!function_exists('payment_gateway_log_files')) {
 function payment_gateway_log_files(int $days = 3650): array
 {
 $files = glob(LOGS_PATH . '/payment-gateway-webhooks-*.jsonl') ?: [];
 $cutoff = time() - max(1, $days) * 86400;
 $files = array_values(array_filter($files, static function (string $file) use ($cutoff): bool {
 return is_file($file) && (int)@filemtime($file) >= $cutoff;
 }));
 rsort($files);
 return $files;
 }
}

if (!function_exists('payment_gateway_clean')) {
 function payment_gateway_clean(string $value, int $max = 160): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('payment_gateway_multiline_clean')) {
 function payment_gateway_multiline_clean(string $value, int $max = 1200): string
 {
 $value = trim(strip_tags($value));
 $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
 $value = preg_replace('/[ \t]+/', ' ', (string)$value);
 $value = preg_replace('/\n{3,}/', "\n\n", (string)$value);
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('payment_gateway_secret_clean')) {
 function payment_gateway_secret_clean(string $value, int $max = 260): string
 {
 $value = trim($value);
 $value = preg_replace('/[\x00-\x1F\x7F]/', '', (string)$value) ?: '';
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('payment_gateway_slug')) {
 function payment_gateway_slug(string $value): string
 {
 $value = strtolower(payment_gateway_clean($value, 80));
 $value = preg_replace('/[^a-z0-9\-]+/', '-', $value) ?: '';
 $value = trim($value, '-');
 return $value !== '' ? $value : 'manual';
 }
}

if (!function_exists('payment_gateway_mask_secret')) {
 function payment_gateway_mask_secret(string $secret): string
 {
 $secret = trim($secret);
 if ($secret === '') {
 return '';
 }
 $last = function_exists('mb_substr') ? mb_substr($secret, -4) : substr($secret, -4);
 return '••••••••' . $last;
 }
}

if (!function_exists('payment_gateway_provider_definitions')) {
 function payment_gateway_provider_definitions(): array
 {
 return [
 'midtrans' => [
 'label' => 'Midtrans',
 'description' => 'Snap hosted payment page: server key untuk create token/redirect URL, client key, merchant ID, dan notification URL.',
 'secret_fields' => ['server_key', 'client_key', 'webhook_secret'],
 'public_fields' => ['merchant_id'],
 'webhook_path' => 'payment-gateway/webhook/midtrans',
 ],
 'xendit' => [
 'label' => 'Xendit',
 'description' => 'Invoice/payment link: secret key untuk create invoice_url, public key, callback token, dan webhook invoice/payment.',
 'secret_fields' => ['secret_key', 'public_key', 'callback_token'],
 'public_fields' => ['business_id'],
 'webhook_path' => 'payment-gateway/webhook/xendit',
 ],
 'flip' => [
 'label' => 'Flip',
 'description' => 'Accept Payment bill/link: secret key untuk create bill/payment link, token validasi, dan webhook payment.',
 'secret_fields' => ['secret_key', 'validation_token', 'webhook_secret'],
 'public_fields' => ['merchant_id'],
 'webhook_path' => 'payment-gateway/webhook/flip',
 ],
 ];
 }
}

if (!function_exists('payment_gateway_default_provider')) {
 function payment_gateway_default_provider(string $key): array
 {
 $defs = payment_gateway_provider_definitions();
 $def = $defs[$key] ?? [];
 return [
 'provider' => $key,
 'label' => (string)($def['label'] ?? ucfirst($key)),
 'enabled' => false,
 'mode' => 'sandbox',
 'auto_update_order' => false,
 'webhook_enabled' => true,
 'webhook_path' => (string)($def['webhook_path'] ?? ('payment-gateway/webhook/' . $key)),
 'merchant_id' => '',
 'business_id' => '',
 'server_key' => '',
 'client_key' => '',
 'secret_key' => '',
 'public_key' => '',
 'webhook_secret' => '',
 'callback_token' => '',
 'validation_token' => '',
 'note' => '',
 ];
 }
}

if (!function_exists('payment_gateway_default_settings')) {
 function payment_gateway_default_settings(): array
 {
 $providers = [];
 foreach (array_keys(payment_gateway_provider_definitions()) as $key) {
 $providers[$key] = payment_gateway_default_provider($key);
 }

 return [
 'version' => 'payment-gateway-bridge',
 'updated_at' => '',
 'enabled' => strtolower((string)($_ENV['PAYMENT_GATEWAY_ENABLED'] ?? 'false')) === 'true',
 'default_provider' => payment_gateway_slug((string)($_ENV['PAYMENT_GATEWAY_PROVIDER'] ?? 'midtrans')),
 'currency' => 'IDR',
 'default_expiry_hours' => max(1, min(168, (int)($_ENV['PAYMENT_GATEWAY_DEFAULT_EXPIRY_HOURS'] ?? 24))),
 'auto_update_order_default' => false,
 'safe_mode' => true,
 'public_note' => 'Payment gateway dapat membuat payment link/token untuk produk yang mengizinkan pembayaran otomatis. Manual transfer tetap menjadi fallback.',
 'providers' => $providers,
 ];
 }
}

if (!function_exists('payment_gateway_normalize_provider')) {
 function payment_gateway_normalize_provider(string $key, array $provider): array
 {
 $default = payment_gateway_default_provider($key);
 $provider = array_replace_recursive($default, $provider);
 $mode = payment_gateway_clean((string)($provider['mode'] ?? 'sandbox'), 20);
 if (!in_array($mode, ['sandbox', 'production'], true)) {
 $mode = 'sandbox';
 }

 return [
 'provider' => $key,
 'label' => payment_gateway_clean((string)($provider['label'] ?? $default['label']), 80) ?: $default['label'],
 'enabled' => !empty($provider['enabled']),
 'mode' => $mode,
 'auto_update_order' => !empty($provider['auto_update_order']),
 'webhook_enabled' => !empty($provider['webhook_enabled']),
 'webhook_path' => payment_gateway_clean((string)($provider['webhook_path'] ?? $default['webhook_path']), 120) ?: $default['webhook_path'],
 'merchant_id' => payment_gateway_clean((string)($provider['merchant_id'] ?? ''), 120),
 'business_id' => payment_gateway_clean((string)($provider['business_id'] ?? ''), 120),
 'server_key' => payment_gateway_secret_clean((string)($provider['server_key'] ?? ''), 260),
 'client_key' => payment_gateway_secret_clean((string)($provider['client_key'] ?? ''), 260),
 'secret_key' => payment_gateway_secret_clean((string)($provider['secret_key'] ?? ''), 260),
 'public_key' => payment_gateway_secret_clean((string)($provider['public_key'] ?? ''), 260),
 'webhook_secret' => payment_gateway_secret_clean((string)($provider['webhook_secret'] ?? ''), 260),
 'callback_token' => payment_gateway_secret_clean((string)($provider['callback_token'] ?? ''), 260),
 'validation_token' => payment_gateway_secret_clean((string)($provider['validation_token'] ?? ''), 260),
 'note' => payment_gateway_multiline_clean((string)($provider['note'] ?? ''), 600),
 ];
 }
}

if (!function_exists('payment_gateway_normalize_settings')) {
 function payment_gateway_normalize_settings(array $settings): array
 {
 $default = payment_gateway_default_settings();
 $settings = array_replace_recursive($default, $settings);
 $providers = [];
 foreach (array_keys(payment_gateway_provider_definitions()) as $key) {
 $providers[$key] = payment_gateway_normalize_provider($key, is_array($settings['providers'][$key] ?? null) ? $settings['providers'][$key] : []);
 }
 $defaultProvider = payment_gateway_slug((string)($settings['default_provider'] ?? 'midtrans'));
 if (!isset($providers[$defaultProvider])) {
 $defaultProvider = 'midtrans';
 }

 return [
 'version' => 'payment-gateway-bridge',
 'updated_at' => payment_gateway_clean((string)($settings['updated_at'] ?? ''), 40),
 'enabled' => !empty($settings['enabled']),
 'default_provider' => $defaultProvider,
 'currency' => 'IDR',
 'default_expiry_hours' => max(1, min(168, (int)($settings['default_expiry_hours'] ?? 24))),
 'auto_update_order_default' => !empty($settings['auto_update_order_default']),
 'safe_mode' => !empty($settings['safe_mode']),
 'public_note' => payment_gateway_multiline_clean((string)($settings['public_note'] ?? $default['public_note']), 800),
 'providers' => $providers,
 ];
 }
}

if (!function_exists('payment_gateway_read_settings')) {
 function payment_gateway_read_settings(): array
 {
 $file = payment_gateway_settings_file();
 if (!is_file($file)) {
 return payment_gateway_normalize_settings(payment_gateway_default_settings());
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return payment_gateway_normalize_settings(is_array($data) ? $data : []);
 }
}

if (!function_exists('payment_gateway_write_settings')) {
 function payment_gateway_write_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $settings = payment_gateway_normalize_settings($settings);
 $settings['updated_at'] = date('c');
 $ok = @file_put_contents(
 payment_gateway_settings_file(),
 json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
 LOCK_EX
 ) !== false;
 if ($ok && function_exists('activity_log_record')) {
 $enabledProviders = [];
 foreach ((array)$settings['providers'] as $key => $provider) {
 if (!empty($provider['enabled'])) {
 $enabledProviders[] = $key;
 }
 }
 activity_log_record('update', 'payment_gateway_settings', null, 'Payment gateway foundation settings diperbarui.', [
 'gateway_enabled' => !empty($settings['enabled']),
 'default_provider' => (string)($settings['default_provider'] ?? ''),
 'enabled_providers' => $enabledProviders,
 ]);
 }
 return $ok;
 }
}

if (!function_exists('payment_gateway_provider')) {
 function payment_gateway_provider(string $providerKey): array
 {
 $settings = payment_gateway_read_settings();
 $key = payment_gateway_slug($providerKey);
 $provider = is_array($settings['providers'][$key] ?? null) ? $settings['providers'][$key] : [];
 return payment_gateway_normalize_provider($key, $provider);
 }
}

if (!function_exists('payment_gateway_webhook_url')) {
 function payment_gateway_webhook_url(string $providerKey = ''): string
 {
 $providerKey = payment_gateway_slug($providerKey ?: 'midtrans');
 $provider = payment_gateway_provider($providerKey);
 $path = payment_gateway_clean((string)($provider['webhook_path'] ?? ('payment-gateway/webhook/' . $providerKey)), 120);
 return url($path !== '' ? $path : ('payment-gateway/webhook/' . $providerKey));
 }
}

if (!function_exists('payment_gateway_summary')) {
 function payment_gateway_summary(): array
 {
 $settings = payment_gateway_read_settings();
 $providers = [];
 $enabledCount = 0;
 $configuredCount = 0;
 foreach ((array)$settings['providers'] as $key => $provider) {
 $hasSecret = false;
 foreach (['server_key', 'client_key', 'secret_key', 'public_key', 'webhook_secret', 'callback_token', 'validation_token'] as $field) {
 if (trim((string)($provider[$field] ?? '')) !== '') {
 $hasSecret = true;
 break;
 }
 }
 if (!empty($provider['enabled'])) {
 $enabledCount++;
 }
 if ($hasSecret || trim((string)($provider['merchant_id'] ?? $provider['business_id'] ?? '')) !== '') {
 $configuredCount++;
 }
 $providers[$key] = [
 'label' => (string)($provider['label'] ?? ucfirst((string)$key)),
 'enabled' => !empty($provider['enabled']),
 'mode' => (string)($provider['mode'] ?? 'sandbox'),
 'configured' => $hasSecret,
 'webhook_enabled' => !empty($provider['webhook_enabled']),
 'webhook_url' => payment_gateway_webhook_url((string)$key),
 ];
 }
 $logs = payment_gateway_read_webhook_events(30, 1000);
 return [
 'enabled' => !empty($settings['enabled']),
 'default_provider' => (string)($settings['default_provider'] ?? 'midtrans'),
 'enabled_count' => $enabledCount,
 'configured_count' => $configuredCount,
 'providers' => $providers,
 'webhook_events_30d' => count($logs),
 'last_event_at' => (string)($logs[0]['created_at'] ?? ''),
 ];
 }
}

if (!function_exists('payment_gateway_extract_headers')) {
 function payment_gateway_extract_headers(): array
 {
 $headers = [];
 if (function_exists('getallheaders')) {
 foreach ((array)getallheaders() as $key => $value) {
 $headers[strtolower((string)$key)] = (string)$value;
 }
 }
 foreach ($_SERVER as $key => $value) {
 if (!str_starts_with((string)$key, 'HTTP_')) {
 continue;
 }
 $name = strtolower(str_replace('_', '-', substr((string)$key, 5)));
 $headers[$name] = (string)$value;
 }
 return $headers;
 }
}

if (!function_exists('payment_gateway_sanitize_payload')) {
 function payment_gateway_sanitize_payload(array $payload): array
 {
 $blocked = ['server_key', 'client_key', 'secret_key', 'api_key', 'authorization', 'token', 'password'];
 $out = [];
 foreach ($payload as $key => $value) {
 $lower = strtolower((string)$key);
 $sensitive = false;
 foreach ($blocked as $needle) {
 if (str_contains($lower, $needle) && !in_array($lower, ['callback_token', 'signature_key'], true)) {
 $sensitive = true;
 break;
 }
 }
 if ($sensitive) {
 $out[$key] = '[masked]';
 } elseif (is_array($value)) {
 $out[$key] = payment_gateway_sanitize_payload($value);
 } elseif (is_scalar($value) || $value === null) {
 $string = (string)$value;
 $out[$key] = strlen($string) > 500 ? substr($string, 0, 500) . '…' : $value;
 }
 }
 return $out;
 }
}

if (!function_exists('payment_gateway_payload_value')) {
 function payment_gateway_payload_value(array $payload, array $keys): string
 {
 foreach ($keys as $key) {
 if (isset($payload[$key]) && is_scalar($payload[$key])) {
 $value = payment_gateway_clean((string)$payload[$key], 160);
 if ($value !== '') {
 return $value;
 }
 }
 }
 return '';
 }
}

if (!function_exists('payment_gateway_extract_reference')) {
 function payment_gateway_extract_reference(array $payload): string
 {
 return payment_gateway_payload_value($payload, [
 'order_id',
 'external_id',
 'reference_id',
 'payment_id',
 'merchant_ref',
 'invoice_id',
 'invoice_number',
 'id',
 ]);
 }
}

if (!function_exists('payment_gateway_extract_status')) {
 function payment_gateway_extract_status(array $payload): string
 {
 return strtolower(payment_gateway_payload_value($payload, [
 'transaction_status',
 'status',
 'payment_status',
 'event',
 'event_type',
 'state',
 ]));
 }
}

if (!function_exists('payment_gateway_extract_amount')) {
 function payment_gateway_extract_amount(array $payload): int
 {
 $value = payment_gateway_payload_value($payload, ['gross_amount', 'amount', 'paid_amount', 'total_amount', 'price']);
 return (int)(preg_replace('/[^0-9]/', '', $value) ?: 0);
 }
}

if (!function_exists('payment_gateway_map_payment_status')) {
 function payment_gateway_map_payment_status(string $gatewayStatus): string
 {
 $status = strtolower(trim($gatewayStatus));
 $paid = ['settlement', 'capture', 'paid', 'success', 'succeeded', 'completed', 'completed_payment'];
 $pending = ['pending', 'unpaid', 'waiting', 'waiting_payment', 'requires_action'];
 $failed = ['deny', 'denied', 'cancel', 'cancelled', 'expired', 'expire', 'failure', 'failed', 'void'];
 if (in_array($status, $paid, true)) {
 return 'Lunas';
 }
 if (in_array($status, $pending, true)) {
 return 'Menunggu Pembayaran';
 }
 if (in_array($status, $failed, true)) {
 return 'Belum Ditagih';
 }
 return '';
 }
}

if (!function_exists('payment_gateway_verify_webhook')) {
 function payment_gateway_verify_webhook(string $providerKey, array $payload, array $headers, string $rawBody): array
 {
 $provider = payment_gateway_provider($providerKey);
 $providerKey = payment_gateway_slug($providerKey);

 if (empty($provider['webhook_enabled'])) {
 return ['verified' => false, 'status' => 'disabled', 'message' => 'Webhook provider belum aktif.'];
 }

 if ($providerKey === 'midtrans') {
 $serverKey = (string)($provider['server_key'] ?? '');
 $orderId = (string)($payload['order_id'] ?? '');
 $statusCode = (string)($payload['status_code'] ?? '');
 $grossAmount = (string)($payload['gross_amount'] ?? '');
 $signature = (string)($payload['signature_key'] ?? '');
 if ($serverKey === '' || $orderId === '' || $statusCode === '' || $grossAmount === '' || $signature === '') {
 return ['verified' => false, 'status' => 'missing_signature_data', 'message' => 'Data signature Midtrans belum lengkap atau server key belum disimpan.'];
 }
 $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
 return [
 'verified' => hash_equals($expected, $signature),
 'status' => hash_equals($expected, $signature) ? 'verified' : 'invalid_signature',
 'message' => hash_equals($expected, $signature) ? 'Signature Midtrans valid.' : 'Signature Midtrans tidak cocok.',
 ];
 }

 if ($providerKey === 'xendit') {
 $token = (string)($provider['callback_token'] ?? '');
 $sent = (string)($headers['x-callback-token'] ?? $headers['x-xendit-callback-token'] ?? '');
 if ($token === '' || $sent === '') {
 return ['verified' => false, 'status' => 'missing_callback_token', 'message' => 'Callback token Xendit belum lengkap.'];
 }
 return [
 'verified' => hash_equals($token, $sent),
 'status' => hash_equals($token, $sent) ? 'verified' : 'invalid_callback_token',
 'message' => hash_equals($token, $sent) ? 'Callback token Xendit valid.' : 'Callback token Xendit tidak cocok.',
 ];
 }

 if ($providerKey === 'flip') {
 $token = (string)($provider['validation_token'] ?? $provider['webhook_secret'] ?? '');
 $sent = (string)($headers['x-callback-token'] ?? $headers['x-flip-token'] ?? $headers['x-flip-signature'] ?? '');
 if ($token === '' || $sent === '') {
 return ['verified' => false, 'status' => 'missing_validation_token', 'message' => 'Token validasi Flip belum lengkap.'];
 }
 return [
 'verified' => hash_equals($token, $sent),
 'status' => hash_equals($token, $sent) ? 'verified' : 'invalid_validation_token',
 'message' => hash_equals($token, $sent) ? 'Token validasi Flip cocok.' : 'Token validasi Flip tidak cocok.',
 ];
 }

 return ['verified' => false, 'status' => 'unsupported_provider', 'message' => 'Provider belum dikenal.'];
 }
}

if (!function_exists('payment_gateway_read_transactions')) {
 function payment_gateway_read_transactions(): array
 {
 $file = payment_gateway_transaction_file();
 if (!is_file($file)) {
 return [];
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return is_array($data) ? $data : [];
 }
}

if (!function_exists('payment_gateway_write_transactions')) {
 function payment_gateway_write_transactions(array $transactions): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 return @file_put_contents(
 payment_gateway_transaction_file(),
 json_encode(array_values($transactions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
 LOCK_EX
 ) !== false;
 }
}

if (!function_exists('payment_gateway_upsert_transaction')) {
 function payment_gateway_upsert_transaction(array $event): bool
 {
 $rows = payment_gateway_read_transactions();
 $key = (string)($event['provider'] ?? '') . '|' . (string)($event['reference'] ?? '') . '|' . (string)($event['gateway_status'] ?? '');
 $found = false;
 foreach ($rows as $index => $row) {
 $rowKey = (string)($row['provider'] ?? '') . '|' . (string)($row['reference'] ?? '') . '|' . (string)($row['gateway_status'] ?? '');
 if ($rowKey === $key) {
 $rows[$index] = array_merge($row, $event, ['updated_at' => date('c')]);
 $found = true;
 break;
 }
 }
 if (!$found) {
 $event['created_at'] = $event['created_at'] ?? date('c');
 $rows[] = $event;
 }
 if (count($rows) > 2000) {
 $rows = array_slice($rows, -2000);
 }
 return payment_gateway_write_transactions($rows);
 }
}

if (!function_exists('payment_gateway_log_webhook_event')) {
 function payment_gateway_log_webhook_event(array $event): bool
 {
 if (!is_dir(LOGS_PATH)) {
 @mkdir(LOGS_PATH, 0775, true);
 }
 $event['created_at'] = $event['created_at'] ?? date('c');
 $event['ip'] = payment_gateway_clean((string)($_SERVER['REMOTE_ADDR'] ?? ''), 80);
 $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
 return @file_put_contents(payment_gateway_log_file(), $line, FILE_APPEND | LOCK_EX) !== false;
 }
}

if (!function_exists('payment_gateway_read_webhook_events')) {
 function payment_gateway_read_webhook_events(int $days = 30, int $max = 200): array
 {
 $rows = [];
 foreach (payment_gateway_log_files($days) as $file) {
 $handle = @fopen($file, 'rb');
 if (!$handle) {
 continue;
 }
 while (($line = fgets($handle)) !== false) {
 $data = json_decode(trim($line), true);
 if (is_array($data)) {
 $data['_ts'] = strtotime((string)($data['created_at'] ?? '')) ?: (int)@filemtime($file);
 $rows[] = $data;
 }
 }
 fclose($handle);
 }
 usort($rows, static fn(array $a, array $b): int => (int)($b['_ts'] ?? 0) <=> (int)($a['_ts'] ?? 0));
 return array_slice($rows, 0, max(1, $max));
 }
}

if (!function_exists('payment_gateway_process_webhook')) {
 function payment_gateway_process_webhook(string $providerKey, string $rawBody, array $headers = []): array
 {
 $providerKey = payment_gateway_slug($providerKey);
 $settings = payment_gateway_read_settings();
 $provider = is_array($settings['providers'][$providerKey] ?? null) ? $settings['providers'][$providerKey] : payment_gateway_default_provider($providerKey);
 $decoded = json_decode($rawBody, true);
 $payload = is_array($decoded) ? $decoded : [];
 $reference = payment_gateway_extract_reference($payload);
 $gatewayStatus = payment_gateway_extract_status($payload);
 $amount = payment_gateway_extract_amount($payload);
 $verify = payment_gateway_verify_webhook($providerKey, $payload, $headers, $rawBody);
 $mappedStatus = payment_gateway_map_payment_status($gatewayStatus);
 $orderUpdated = false;
 $orderFound = false;
 $expectedOrderAmount = null;
 $amountMatchesOrder = null;

 if (!empty($verify['verified']) && !empty($provider['auto_update_order']) && $reference !== '' && $mappedStatus !== '' && function_exists('order_update_status')) {
 $order = function_exists('payment_gateway_find_order_by_reference') ? payment_gateway_find_order_by_reference($reference) : (function_exists('order_find_by_reference') ? order_find_by_reference($reference, '') : null);
 if (is_array($order)) {
 $orderFound = true;

 if ($mappedStatus === 'Lunas') {
 $expectedOrderAmount = function_exists('payment_gateway_order_amount')
     ? payment_gateway_order_amount($order)
     : 0;
 $amountMatchesOrder = $expectedOrderAmount > 0 && $amount > 0 && $amount === $expectedOrderAmount;

 if (!$amountMatchesOrder) {
 $verify['status'] = 'verified_amount_mismatch';
 $verify['message'] = 'Signature/token valid, tetapi nominal webhook tidak cocok dengan total order. Order tidak ditandai lunas.';
 }
 }

 if ($mappedStatus !== 'Lunas' || $amountMatchesOrder === true) {
 $currentStatus = (string)($order['status'] ?? 'Menunggu Pembayaran');
 if ($mappedStatus === 'Lunas') {
 $currentStatus = in_array($currentStatus, ['Baru', 'Menunggu Pembayaran'], true) ? 'Deal' : $currentStatus;
 }
 $gatewayExtra = [
 'gateway_provider' => $providerKey,
 'gateway_provider_label' => (string)($provider['label'] ?? ucfirst($providerKey)),
 'gateway_reference' => $reference,
 'gateway_status' => $gatewayStatus,
 'gateway_amount' => (string)$amount,
 ];
 if ($mappedStatus === 'Lunas') {
 $gatewayExtra['gateway_paid_at'] = date('c');
 }
 $orderUpdated = order_update_status(
 (string)($order['id'] ?? ''),
 $currentStatus,
 'Update otomatis dari webhook payment gateway ' . (string)($provider['label'] ?? $providerKey) . '.',
 $mappedStatus,
 'Gateway status: ' . $gatewayStatus,
 $gatewayExtra
 );
 }
 }
 }

 $event = [
 'id' => 'pgw_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
 'provider' => $providerKey,
 'provider_label' => (string)($provider['label'] ?? ucfirst($providerKey)),
 'mode' => (string)($provider['mode'] ?? 'sandbox'),
 'verified' => !empty($verify['verified']),
 'signature_status' => (string)($verify['status'] ?? 'unknown'),
 'message' => (string)($verify['message'] ?? ''),
 'reference' => $reference,
 'gateway_status' => $gatewayStatus,
 'mapped_payment_status' => $mappedStatus,
 'amount' => $amount,
 'expected_order_amount' => $expectedOrderAmount,
 'amount_matches_order' => $amountMatchesOrder,
 'order_found' => $orderFound,
 'order_updated' => $orderUpdated,
 'payload_hash' => hash('sha256', $rawBody),
 'payload_preview' => payment_gateway_sanitize_payload($payload),
 'created_at' => date('c'),
 ];

 payment_gateway_log_webhook_event($event);
 payment_gateway_upsert_transaction($event);

 if (function_exists('activity_log_record')) {
 activity_log_record('webhook', 'payment_gateway', $providerKey, 'Webhook payment gateway diterima.', [
 'provider' => $providerKey,
 'verified' => !empty($event['verified']),
 'reference' => $reference,
 'gateway_status' => $gatewayStatus,
 'order_updated' => $orderUpdated,
 ]);
 }

 return [
 'ok' => true,
 'event' => $event,
 ];
 }
}

/*
|--------------------------------------------------------------------------
| PAYMENT GATEWAY REAL CHARGE BRIDGE - 
|--------------------------------------------------------------------------
| Creates hosted payment links/tokens from an existing order for supported
| gateways. API keys stay server-side; public pages only receive redirect
| links. Manual payment remains the fallback if provider config is missing.
|--------------------------------------------------------------------------
*/


if (!function_exists('payment_gateway_find_order_by_reference')) {
 function payment_gateway_find_order_by_reference(string $reference): ?array
 {
 $reference = payment_gateway_clean($reference, 120);
 if ($reference === '') {
 return null;
 }
 if (function_exists('order_read_all')) {
 $orders = order_read_all(0, ['_all_time' => true], 50000);
 foreach ($orders as $order) {
 if ((string)($order['gateway_reference'] ?? '') === $reference) {
 return $order;
 }
 if (function_exists('order_public_reference') && order_public_reference($order) === $reference) {
 return $order;
 }
 }
 }
 $trimmed = preg_replace('/-(MID|XEN|FLI)$/i', '', $reference) ?: $reference;
 if ($trimmed !== $reference && function_exists('order_find_by_reference')) {
 return order_find_by_reference($trimmed, '');
 }
 return function_exists('order_find_by_reference') ? order_find_by_reference($reference, '') : null;
 }
}

if (!function_exists('payment_gateway_base_endpoint')) {
 function payment_gateway_base_endpoint(string $providerKey, string $mode = 'sandbox'): string
 {
 $providerKey = payment_gateway_slug($providerKey);
 $mode = $mode === 'production' ? 'production' : 'sandbox';
 if ($providerKey === 'midtrans') {
 return $mode === 'production' ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
 }
 if ($providerKey === 'xendit') {
 return 'https://api.xendit.co/v2/invoices';
 }
 if ($providerKey === 'flip') {
 return $mode === 'production' ? 'https://bigflip.id/big_api/v2/pwf/bill' : 'https://bigflip.id/big_sandbox_api/v2/pwf/bill';
 }
 return '';
 }
}

if (!function_exists('payment_gateway_order_reference')) {
 function payment_gateway_order_reference(array $order, string $providerKey = ''): string
 {
 $ref = function_exists('order_public_reference') ? order_public_reference($order) : payment_gateway_clean((string)($order['ref'] ?? $order['id'] ?? ''), 80);
 $providerKey = payment_gateway_slug($providerKey ?: (string)($order['gateway_provider'] ?? ''));
 if ($providerKey !== '' && !str_contains($ref, strtoupper($providerKey))) {
 return $ref . '-' . strtoupper(substr($providerKey, 0, 3));
 }
 return $ref;
 }
}

if (!function_exists('payment_gateway_order_amount')) {
 function payment_gateway_order_amount(array $order): int
 {
 if (function_exists('order_invoice_total')) {
 return max(0, (int)order_invoice_total($order));
 }
 $subtotal = ((int)($order['price'] ?? 0)) * max(1, (int)($order['quantity'] ?? 1));
 return max(0, $subtotal + (int)($order['shipping_total'] ?? 0));
 }
}

if (!function_exists('payment_gateway_order_description')) {
 function payment_gateway_order_description(array $order): string
 {
 $product = payment_gateway_clean((string)($order['product_title'] ?? 'Pesanan'), 90) ?: 'Pesanan';
 $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? '');
 return payment_gateway_clean(SITE_NAME . ' - ' . $product . ($ref !== '' ? ' #' . $ref : ''), 160);
 }
}

if (!function_exists('payment_gateway_return_url')) {
 function payment_gateway_return_url(array $order, string $status = 'finish'): string
 {
 $query = function_exists('order_public_query') ? order_public_query($order) : ['ref' => (string)($order['ref'] ?? '')];
 $query['payment'] = payment_gateway_slug($status ?: 'finish');
 return url('payment-return?' . http_build_query($query));
 }
}

if (!function_exists('payment_gateway_pay_url')) {
 function payment_gateway_pay_url(array $order, string $providerKey = ''): string
 {
 $query = function_exists('order_public_query') ? order_public_query($order) : ['ref' => (string)($order['ref'] ?? '')];
 if ($providerKey !== '') {
 $query['provider'] = payment_gateway_slug($providerKey);
 }
 return url('payment-gateway/pay?' . http_build_query($query));
 }
}

if (!function_exists('payment_gateway_is_gateway_method')) {
 function payment_gateway_is_gateway_method(string $method): bool
 {
 $method = strtolower($method);
 return str_contains($method, 'otomatis') || str_contains($method, 'gateway') || str_contains($method, 'payment') || str_contains($method, 'online');
 }
}

if (!function_exists('payment_gateway_configured')) {
 function payment_gateway_configured(string $providerKey, array $provider): bool
 {
 $providerKey = payment_gateway_slug($providerKey);
 if (empty($provider['enabled'])) {
 return false;
 }
 if ($providerKey === 'midtrans') {
 return trim((string)($provider['server_key'] ?? '')) !== '';
 }
 if ($providerKey === 'xendit') {
 return trim((string)($provider['secret_key'] ?? '')) !== '';
 }
 if ($providerKey === 'flip') {
 return trim((string)($provider['secret_key'] ?? '')) !== '';
 }
 return false;
 }
}

if (!function_exists('payment_gateway_allowed_providers_for_order')) {
 function payment_gateway_allowed_providers_for_order(array $order): array
 {
 $allowed = [];
 if (!empty($order['product_slug']) && function_exists('get_product_by_slug') && function_exists('commerce_policy_normalize_product')) {
 $product = get_product_by_slug((string)$order['product_slug']);
 if (is_array($product)) {
 $policy = commerce_policy_normalize_product($product);
 foreach ((array)($policy['allowed_payment_gateways'] ?? []) as $key) {
 $key = payment_gateway_slug((string)$key);
 if (in_array($key, ['midtrans', 'xendit', 'flip'], true)) {
 $allowed[] = $key;
 }
 }
 }
 }
 return array_values(array_unique($allowed));
 }
}

if (!function_exists('payment_gateway_select_provider_for_order')) {
 function payment_gateway_select_provider_for_order(array $order, string $requestedProvider = ''): string
 {
 $settings = payment_gateway_read_settings();
 $providers = is_array($settings['providers'] ?? null) ? $settings['providers'] : [];
 $allowed = payment_gateway_allowed_providers_for_order($order);
 $method = strtolower((string)($order['payment_method'] ?? ''));
 $candidates = [];

 $requestedProvider = payment_gateway_slug($requestedProvider);
 if ($requestedProvider !== '' && isset($providers[$requestedProvider])) {
 $candidates[] = $requestedProvider;
 }
 foreach (['midtrans', 'xendit', 'flip'] as $key) {
 if (str_contains($method, $key)) {
 $candidates[] = $key;
 }
 }
 foreach ($allowed as $key) {
 $candidates[] = $key;
 }
 $candidates[] = (string)($settings['default_provider'] ?? 'midtrans');
 $candidates[] = 'midtrans';
 $candidates[] = 'xendit';
 $candidates[] = 'flip';

 foreach (array_values(array_unique($candidates)) as $key) {
 $key = payment_gateway_slug((string)$key);
 if (!isset($providers[$key])) {
 continue;
 }
 if ($allowed && !in_array($key, $allowed, true)) {
 continue;
 }
 $provider = payment_gateway_normalize_provider($key, is_array($providers[$key]) ? $providers[$key] : []);
 if (payment_gateway_configured($key, $provider)) {
 return $key;
 }
 }
 return '';
 }
}

if (!function_exists('payment_gateway_order_can_create_charge')) {
 function payment_gateway_order_can_create_charge(array $order): array
 {
 $settings = payment_gateway_read_settings();
 if (empty($settings['enabled'])) {
 return ['allowed' => false, 'reason' => 'Payment gateway global belum aktif.'];
 }
 $amount = payment_gateway_order_amount($order);
 if ($amount <= 0) {
 return ['allowed' => false, 'reason' => 'Total order belum valid untuk payment gateway.'];
 }
 $paymentStatus = strtolower((string)($order['payment_status'] ?? ''));
 if (str_contains($paymentStatus, 'lunas')) {
 return ['allowed' => false, 'reason' => 'Order sudah lunas.'];
 }
 $policy = (string)($order['commerce_payment_policy'] ?? 'global');
 $method = (string)($order['payment_method'] ?? '');
 if (in_array($policy, ['manual_only', 'cod_only', 'consult_first'], true)) {
 return ['allowed' => false, 'reason' => 'Produk ini disetel untuk pembayaran manual/COD/konsultasi.'];
 }
 if (!in_array($policy, ['gateway_only', 'manual_gateway'], true) && !payment_gateway_is_gateway_method($method)) {
 return ['allowed' => false, 'reason' => 'Customer belum memilih metode pembayaran otomatis.'];
 }
 if ($policy === 'manual_gateway' && !payment_gateway_is_gateway_method($method)) {
 return ['allowed' => false, 'reason' => 'Produk ini mendukung manual/gateway, tetapi customer memilih pembayaran manual.'];
 }
 return ['allowed' => true, 'reason' => 'Payment gateway bisa dibuat.'];
 }
}

if (!function_exists('payment_gateway_http_request')) {
 function payment_gateway_http_request(string $url, string $method, array|string $payload, array $headers = [], int $timeout = 20): array
 {
 $method = strtoupper($method ?: 'POST');
 $body = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$payload;
 $headerLines = $headers;
 if (!array_filter($headerLines, static fn($h): bool => str_starts_with(strtolower((string)$h), 'content-length:'))) {
 $headerLines[] = 'Content-Length: ' . strlen((string)$body);
 }

 if (function_exists('curl_init')) {
 $ch = curl_init($url);
 curl_setopt_array($ch, [
 CURLOPT_RETURNTRANSFER => true,
 CURLOPT_CUSTOMREQUEST => $method,
 CURLOPT_POSTFIELDS => $body,
 CURLOPT_HTTPHEADER => $headerLines,
 CURLOPT_TIMEOUT => $timeout,
 CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
 ]);
 $responseBody = (string)curl_exec($ch);
 $error = curl_error($ch);
 $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
 curl_close($ch);
 $decoded = json_decode($responseBody, true);
 return [
 'ok' => $status >= 200 && $status < 300 && is_array($decoded),
 'status_code' => $status,
 'body' => is_array($decoded) ? $decoded : [],
 'raw' => strlen($responseBody) > 1200 ? substr($responseBody, 0, 1200) . '…' : $responseBody,
 'error' => $error,
 ];
 }

 $context = stream_context_create([
 'http' => [
 'method' => $method,
 'header' => implode("\r\n", $headerLines),
 'content' => $body,
 'timeout' => $timeout,
 'ignore_errors' => true,
 ],
 ]);
 $responseBody = (string)@file_get_contents($url, false, $context);
 $status = 0;
 foreach (($http_response_header ?? []) as $line) {
 if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$line, $m)) {
 $status = (int)$m[1];
 break;
 }
 }
 $decoded = json_decode($responseBody, true);
 return [
 'ok' => $status >= 200 && $status < 300 && is_array($decoded),
 'status_code' => $status,
 'body' => is_array($decoded) ? $decoded : [],
 'raw' => strlen($responseBody) > 1200 ? substr($responseBody, 0, 1200) . '…' : $responseBody,
 'error' => $responseBody === '' ? 'Tidak ada response dari provider.' : '',
 ];
 }
}

if (!function_exists('payment_gateway_item_details')) {
 function payment_gateway_item_details(array $order): array
 {
 $qty = max(1, (int)($order['quantity'] ?? 1));
 $unit = max(0, (int)($order['price'] ?? 0));
 $items = [];
 if ($unit > 0) {
 $items[] = [
 'id' => payment_gateway_clean((string)($order['product_slug'] ?? 'item'), 50) ?: 'item',
 'price' => $unit,
 'quantity' => $qty,
 'name' => payment_gateway_clean((string)($order['product_title'] ?? 'Pesanan'), 50) ?: 'Pesanan',
 ];
 }
 $shipping = max(0, (int)($order['shipping_total'] ?? 0));
 if ($shipping > 0) {
 $items[] = [
 'id' => 'shipping',
 'price' => $shipping,
 'quantity' => 1,
 'name' => 'Ongkir',
 ];
 }
 return $items;
 }
}

if (!function_exists('payment_gateway_create_midtrans_charge')) {
 function payment_gateway_create_midtrans_charge(array $order, array $provider, array $settings): array
 {
 $serverKey = (string)($provider['server_key'] ?? '');
 if ($serverKey === '') {
 return ['ok' => false, 'message' => 'Server key Midtrans belum diisi.'];
 }
 $reference = payment_gateway_order_reference($order, 'midtrans');
 $amount = payment_gateway_order_amount($order);
 $payload = [
 'transaction_details' => [
 'order_id' => $reference,
 'gross_amount' => $amount,
 ],
 'customer_details' => [
 'first_name' => payment_gateway_clean((string)($order['name'] ?? 'Customer'), 60) ?: 'Customer',
 'email' => filter_var((string)($order['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$order['email'] : '',
 'phone' => payment_gateway_clean((string)($order['phone'] ?? ''), 30),
 ],
 'item_details' => payment_gateway_item_details($order),
 'callbacks' => [
 'finish' => payment_gateway_return_url($order, 'finish'),
 ],
 'expiry' => [
 'unit' => 'hour',
 'duration' => max(1, min(168, (int)($settings['default_expiry_hours'] ?? 24))),
 ],
 ];
 $endpoint = payment_gateway_base_endpoint('midtrans', (string)($provider['mode'] ?? 'sandbox'));
 $response = payment_gateway_http_request($endpoint, 'POST', $payload, [
 'Accept: application/json',
 'Content-Type: application/json',
 'Authorization: Basic ' . base64_encode($serverKey . ':'),
 ]);
 $body = is_array($response['body'] ?? null) ? $response['body'] : [];
 if (empty($response['ok']) || empty($body['redirect_url'])) {
 return ['ok' => false, 'message' => 'Midtrans belum mengembalikan redirect URL.', 'provider_response' => $response, 'payload' => $payload];
 }
 return [
 'ok' => true,
 'provider' => 'midtrans',
 'provider_label' => 'Midtrans',
 'reference' => $reference,
 'amount' => $amount,
 'payment_url' => (string)$body['redirect_url'],
 'token' => (string)($body['token'] ?? ''),
 'gateway_status' => 'created',
 'raw_id' => (string)($body['token'] ?? $reference),
 'provider_response' => payment_gateway_sanitize_payload($body),
 ];
 }
}

if (!function_exists('payment_gateway_create_xendit_charge')) {
 function payment_gateway_create_xendit_charge(array $order, array $provider, array $settings): array
 {
 $secretKey = (string)($provider['secret_key'] ?? '');
 if ($secretKey === '') {
 return ['ok' => false, 'message' => 'Secret key Xendit belum diisi.'];
 }
 $reference = payment_gateway_order_reference($order, 'xendit');
 $amount = payment_gateway_order_amount($order);
 $duration = max(3600, min(604800, (int)($settings['default_expiry_hours'] ?? 24) * 3600));
 $payload = [
 'external_id' => $reference,
 'amount' => $amount,
 'description' => payment_gateway_order_description($order),
 'invoice_duration' => $duration,
 'currency' => 'IDR',
 'success_redirect_url' => payment_gateway_return_url($order, 'success'),
 'failure_redirect_url' => payment_gateway_return_url($order, 'failed'),
 ];
 if (filter_var((string)($order['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
 $payload['payer_email'] = (string)$order['email'];
 }
 $endpoint = payment_gateway_base_endpoint('xendit', (string)($provider['mode'] ?? 'sandbox'));
 $response = payment_gateway_http_request($endpoint, 'POST', $payload, [
 'Accept: application/json',
 'Content-Type: application/json',
 'Authorization: Basic ' . base64_encode($secretKey . ':'),
 ]);
 $body = is_array($response['body'] ?? null) ? $response['body'] : [];
 if (empty($response['ok']) || empty($body['invoice_url'])) {
 return ['ok' => false, 'message' => 'Xendit belum mengembalikan invoice_url.', 'provider_response' => $response, 'payload' => $payload];
 }
 return [
 'ok' => true,
 'provider' => 'xendit',
 'provider_label' => 'Xendit',
 'reference' => $reference,
 'amount' => $amount,
 'payment_url' => (string)$body['invoice_url'],
 'token' => '',
 'gateway_status' => strtolower((string)($body['status'] ?? 'created')),
 'raw_id' => (string)($body['id'] ?? $reference),
 'provider_response' => payment_gateway_sanitize_payload($body),
 ];
 }
}

if (!function_exists('payment_gateway_create_flip_charge')) {
 function payment_gateway_create_flip_charge(array $order, array $provider, array $settings): array
 {
 $secretKey = (string)($provider['secret_key'] ?? '');
 if ($secretKey === '') {
 return ['ok' => false, 'message' => 'Secret key Flip belum diisi.'];
 }
 $reference = payment_gateway_order_reference($order, 'flip');
 $amount = payment_gateway_order_amount($order);
 $payload = http_build_query([
 'title' => payment_gateway_order_description($order),
 'type' => 'SINGLE',
 'amount' => $amount,
 'step' => 1,
 'sender_name' => payment_gateway_clean((string)($order['name'] ?? 'Customer'), 80),
 'sender_email' => filter_var((string)($order['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$order['email'] : '',
 'sender_phone_number' => payment_gateway_clean((string)($order['phone'] ?? ''), 30),
 'redirect_url' => payment_gateway_return_url($order, 'success'),
 'is_address_required' => 0,
 'is_phone_number_required' => 0,
 ]);
 $endpoint = payment_gateway_base_endpoint('flip', (string)($provider['mode'] ?? 'sandbox'));
 $response = payment_gateway_http_request($endpoint, 'POST', $payload, [
 'Accept: application/json',
 'Content-Type: application/x-www-form-urlencoded',
 'Authorization: Basic ' . base64_encode($secretKey . ':'),
 ]);
 $body = is_array($response['body'] ?? null) ? $response['body'] : [];
 $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
 $paymentUrl = (string)($data['link_url'] ?? $data['payment_url'] ?? $data['payment_link'] ?? $data['url'] ?? '');
 if (empty($response['ok']) || $paymentUrl === '') {
 return ['ok' => false, 'message' => 'Flip belum mengembalikan payment link.', 'provider_response' => $response, 'payload' => ['form_encoded' => true]];
 }
 return [
 'ok' => true,
 'provider' => 'flip',
 'provider_label' => 'Flip',
 'reference' => $reference,
 'amount' => $amount,
 'payment_url' => $paymentUrl,
 'token' => '',
 'gateway_status' => strtolower((string)($data['status'] ?? 'created')),
 'raw_id' => (string)($data['id'] ?? $data['bill_id'] ?? $reference),
 'provider_response' => payment_gateway_sanitize_payload($data),
 ];
 }
}

if (!function_exists('payment_gateway_persist_charge_result')) {
 function payment_gateway_persist_charge_result(array $order, array $result): bool
 {
 $reference = (string)($result['reference'] ?? '');
 $provider = (string)($result['provider'] ?? '');
 $statusRow = [
 'gateway_provider' => $provider,
 'gateway_provider_label' => (string)($result['provider_label'] ?? ucfirst($provider)),
 'gateway_reference' => $reference,
 'gateway_payment_url' => (string)($result['payment_url'] ?? ''),
 'gateway_token' => (string)($result['token'] ?? ''),
 'gateway_status' => (string)($result['gateway_status'] ?? 'created'),
 'gateway_mode' => (string)($result['mode'] ?? ''),
 'gateway_transaction_id' => (string)($result['raw_id'] ?? ''),
 'gateway_amount' => (string)max(0, (int)($result['amount'] ?? 0)),
 'gateway_created_at' => date('c'),
 'gateway_error' => '',
 'invoice_number' => function_exists('order_invoice_number') ? order_invoice_number($order) : $reference,
 'invoice_total' => (string)max(0, (int)($result['amount'] ?? 0)),
 'invoice_due_date' => function_exists('order_invoice_default_due_date') ? order_invoice_default_due_date() : date('Y-m-d', strtotime('+1 day')),
 'invoice_payment_channel' => (string)($result['provider_label'] ?? ucfirst($provider)),
 'invoice_payment_instruction' => 'Silakan klik tombol Bayar Sekarang untuk menyelesaikan pembayaran melalui ' . (string)($result['provider_label'] ?? ucfirst($provider)) . '.',
 'invoice_public_note' => 'Status pembayaran otomatis akan diperbarui setelah provider mengirim webhook/callback yang valid. Jika status belum berubah, silakan hubungi admin dengan nomor order.',
 ];
 $updated = false;
 if (function_exists('order_update_status')) {
 $updated = order_update_status(
 (string)($order['id'] ?? ''),
 (string)($order['status'] ?? 'Baru'),
 'Payment link otomatis dibuat melalui ' . (string)$statusRow['gateway_provider_label'] . '.',
 'Menunggu Pembayaran',
 'Menunggu pembayaran via ' . (string)$statusRow['gateway_provider_label'] . '.',
 $statusRow
 );
 }
 payment_gateway_upsert_transaction([
 'id' => 'pgc_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
 'provider' => $provider,
 'provider_label' => (string)$statusRow['gateway_provider_label'],
 'mode' => (string)($result['mode'] ?? ''),
 'verified' => true,
 'signature_status' => 'created_by_server',
 'message' => 'Payment charge/link dibuat dari server website.',
 'reference' => $reference,
 'gateway_status' => (string)($result['gateway_status'] ?? 'created'),
 'mapped_payment_status' => 'Menunggu Pembayaran',
 'amount' => (int)($result['amount'] ?? 0),
 'order_found' => true,
 'order_updated' => $updated,
 'payment_url' => (string)($result['payment_url'] ?? ''),
 'gateway_transaction_id' => (string)($result['raw_id'] ?? ''),
 'payload_preview' => payment_gateway_sanitize_payload((array)($result['provider_response'] ?? [])),
 'created_at' => date('c'),
 ]);
 return $updated;
 }
}

if (!function_exists('payment_gateway_store_charge_error')) {
 function payment_gateway_store_charge_error(array $order, string $providerKey, string $message): void
 {
 if (function_exists('order_update_status')) {
 order_update_status(
 (string)($order['id'] ?? ''),
 (string)($order['status'] ?? 'Baru'),
 'Payment gateway gagal dibuat. Order tetap masuk dan bisa diproses manual.',
 (string)($order['payment_status'] ?? 'Belum Ditagih'),
 'Gateway error: ' . $message,
 [
 'gateway_provider' => payment_gateway_slug($providerKey),
 'gateway_error' => payment_gateway_clean($message, 240),
 'gateway_created_at' => date('c'),
 ]
 );
 }
 }
}

if (!function_exists('payment_gateway_create_charge_for_order')) {
 function payment_gateway_create_charge_for_order(array $order, string $providerKey = ''): array
 {
 $can = payment_gateway_order_can_create_charge($order);
 if (empty($can['allowed'])) {
 return ['ok' => false, 'message' => (string)($can['reason'] ?? 'Payment gateway tidak tersedia untuk order ini.')];
 }
 $settings = payment_gateway_read_settings();
 $providerKey = payment_gateway_select_provider_for_order($order, $providerKey);
 if ($providerKey === '') {
 return ['ok' => false, 'message' => 'Belum ada provider payment gateway aktif dan terkonfigurasi untuk order ini.'];
 }
 $provider = payment_gateway_provider($providerKey);
 $result = match ($providerKey) {
 'midtrans' => payment_gateway_create_midtrans_charge($order, $provider, $settings),
 'xendit' => payment_gateway_create_xendit_charge($order, $provider, $settings),
 'flip' => payment_gateway_create_flip_charge($order, $provider, $settings),
 default => ['ok' => false, 'message' => 'Provider payment gateway belum didukung.'],
 };
 $result['provider'] = (string)($result['provider'] ?? $providerKey);
 $result['provider_label'] = (string)($result['provider_label'] ?? ($provider['label'] ?? ucfirst($providerKey)));
 $result['mode'] = (string)($provider['mode'] ?? 'sandbox');
 if (!empty($result['ok'])) {
 payment_gateway_persist_charge_result($order, $result);
 return $result;
 }
 payment_gateway_store_charge_error($order, $providerKey, (string)($result['message'] ?? 'Provider gagal membuat payment link.'));
 return $result;
 }
}

if (!function_exists('payment_gateway_existing_payment_url')) {
 function payment_gateway_existing_payment_url(array $order): string
 {
 $url = payment_gateway_clean((string)($order['gateway_payment_url'] ?? ''), 500);
 return preg_match('#^https?://#i', $url) ? $url : '';
 }
}

if (!function_exists('payment_gateway_public_payment_box')) {
 function payment_gateway_public_payment_box(array $order): string
 {
 $url = payment_gateway_existing_payment_url($order);
 $provider = payment_gateway_clean((string)($order['gateway_provider_label'] ?? $order['gateway_provider'] ?? 'Payment Gateway'), 80) ?: 'Payment Gateway';
 $error = payment_gateway_clean((string)($order['gateway_error'] ?? ''), 240);
 $can = payment_gateway_order_can_create_charge($order);
 $payUrl = function_exists('payment_gateway_pay_url') ? payment_gateway_pay_url($order, (string)($order['gateway_provider'] ?? '')) : '';
 ob_start();
 ?>
 <div class="payment-gateway-public-box--template">
 <h2>Pembayaran Otomatis</h2>
 <?php if ($url !== ''): ?>
 <p>Payment link <?= esc($provider); ?> sudah tersedia. Klik tombol di bawah untuk membuka halaman pembayaran resmi provider.</p>
 <a class="cta" href="<?= esc($url); ?>" target="_blank" rel="nofollow noopener">Bayar Sekarang via <?= esc($provider); ?></a>
 <?php elseif (!empty($can['allowed']) && $payUrl !== ''): ?>
 <p>Produk ini mendukung pembayaran otomatis. Klik tombol di bawah untuk membuat payment link resmi provider.</p>
 <a class="cta" href="<?= esc($payUrl); ?>" rel="nofollow">Buat & Buka Link Pembayaran</a>
 <?php else: ?>
 <p>Pembayaran otomatis belum tersedia untuk order ini. Admin tetap bisa memproses pembayaran manual/QRIS/transfer.</p>
 <?php endif; ?>
 <?php if ($error !== ''): ?><p class="payment-gateway-public-error--template">Catatan gateway: <?= esc($error); ?></p><?php endif; ?>
 </div>
 <?php
 return (string)ob_get_clean();
 }
}
