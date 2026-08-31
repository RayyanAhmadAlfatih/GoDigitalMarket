<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MEMBER AREA, COURSE & LICENSE ACCESS CENTER - U-Growth
|--------------------------------------------------------------------------
| Account-less member access layer for digital products, courses, templates,
| licenses, and personal-brand digital assets. It is lightweight and works on
| shared hosting by using token-protected records in storage JSON.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('member_access_clean')) {
 function member_access_clean(string $value, int $max = 180): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('member_access_multiline_clean')) {
 function member_access_multiline_clean(string $value, int $max = 2400): string
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

if (!function_exists('member_access_settings_file')) {
 function member_access_settings_file(): string
 {
 return STORAGE_PATH . '/member-area-settings.json';
 }
}

if (!function_exists('member_access_records_file')) {
 function member_access_records_file(): string
 {
 return STORAGE_PATH . '/member-area-access.json';
 }
}

if (!function_exists('member_access_log_file')) {
 function member_access_log_file(?int $timestamp = null): string
 {
 return LOGS_PATH . '/member-area-' . date('Y-m', $timestamp ?: time()) . '.jsonl';
 }
}

if (!function_exists('member_access_default_settings')) {
 function member_access_default_settings(): array
 {
 return [
 'enabled' => true,
 'auto_enroll_when_paid' => true,
 'paid_statuses' => ['Lunas'],
 'show_on_invoice' => true,
 'show_on_order_status' => true,
 'default_member_access_days' => 365,
 'default_license_duration_days' => 365,
 'license_prefix' => 'UGR',
 'login_hint' => 'Masukkan email dan nomor order, atau gunakan link akses dari invoice/status order.',
 'public_note' => 'Area member ini berisi akses course, file digital, template, lisensi, dan instruksi produk digital yang sudah aktif.',
 'pending_note' => 'Akses member akan aktif setelah pembayaran dikonfirmasi admin atau payment gateway berhasil.',
 'customer_message_template' => "Halo {name}, akses member untuk produk digital Anda sudah aktif.\n\nOrder: {order_ref}\nProduk: {product}\nMember Area: {member_url}\nLisensi: {license_key}\n\nInstruksi:\n{instructions}",
 ];
 }
}

if (!function_exists('member_access_read_settings')) {
 function member_access_read_settings(): array
 {
 $defaults = member_access_default_settings();
 $file = member_access_settings_file();
 if (!is_file($file)) {
 return $defaults;
 }
 $data = json_decode((string)@file_get_contents($file), true);
 if (!is_array($data)) {
 return $defaults;
 }
 $settings = array_merge($defaults, $data);
 $settings['paid_statuses'] = array_values(array_filter(array_map('strval', (array)($settings['paid_statuses'] ?? []))));
 if (!$settings['paid_statuses']) {
 $settings['paid_statuses'] = ['Lunas'];
 }
 $settings['default_member_access_days'] = max(1, min(3650, (int)($settings['default_member_access_days'] ?? 365)));
 $settings['default_license_duration_days'] = max(0, min(3650, (int)($settings['default_license_duration_days'] ?? 365)));
 $settings['license_prefix'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($settings['license_prefix'] ?? 'UGR')) ?: 'UGR');
 return $settings;
 }
}

if (!function_exists('member_access_write_settings')) {
 function member_access_write_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $defaults = member_access_default_settings();
 $payload = [
 'enabled' => !empty($settings['enabled']),
 'auto_enroll_when_paid' => !empty($settings['auto_enroll_when_paid']),
 'paid_statuses' => array_values(array_filter(array_map('strval', (array)($settings['paid_statuses'] ?? $defaults['paid_statuses'])))),
 'show_on_invoice' => !empty($settings['show_on_invoice']),
 'show_on_order_status' => !empty($settings['show_on_order_status']),
 'default_member_access_days' => max(1, min(3650, (int)($settings['default_member_access_days'] ?? $defaults['default_member_access_days']))),
 'default_license_duration_days' => max(0, min(3650, (int)($settings['default_license_duration_days'] ?? $defaults['default_license_duration_days']))),
 'license_prefix' => strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($settings['license_prefix'] ?? $defaults['license_prefix'])) ?: 'UGR'),
 'login_hint' => member_access_multiline_clean((string)($settings['login_hint'] ?? $defaults['login_hint']), 500),
 'public_note' => member_access_multiline_clean((string)($settings['public_note'] ?? $defaults['public_note']), 900),
 'pending_note' => member_access_multiline_clean((string)($settings['pending_note'] ?? $defaults['pending_note']), 900),
 'customer_message_template' => member_access_multiline_clean((string)($settings['customer_message_template'] ?? $defaults['customer_message_template']), 1800),
 ];
 if (!$payload['paid_statuses']) {
 $payload['paid_statuses'] = ['Lunas'];
 }
 return @file_put_contents(member_access_settings_file(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('member_access_read_records')) {
 function member_access_read_records(): array
 {
 $file = member_access_records_file();
 if (!is_file($file)) {
 return [];
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return is_array($data) ? $data : [];
 }
}

if (!function_exists('member_access_write_records')) {
 function member_access_write_records(array $records): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 return @file_put_contents(member_access_records_file(), json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('member_access_log_event')) {
 function member_access_log_event(array $event): void
 {
 if (!is_dir(LOGS_PATH)) {
 @mkdir(LOGS_PATH, 0775, true);
 }
 $event['created_at'] = (string)($event['created_at'] ?? date('c'));
 @file_put_contents(member_access_log_file(), json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
 }
}

if (!function_exists('member_access_product_for_order')) {
 function member_access_product_for_order(array $order): ?array
 {
 $slug = member_access_clean((string)($order['product_slug'] ?? ''), 160);
 if ($slug !== '' && function_exists('get_product_by_slug')) {
 $product = get_product_by_slug($slug);
 if (is_array($product)) {
 return $product;
 }
 }
 return null;
 }
}

if (!function_exists('member_access_product_enabled')) {
 function member_access_product_enabled(?array $product, array $order = []): bool
 {
 if (!$product) {
 return false;
 }
 $deliveryType = strtolower((string)($product['digital_delivery_type'] ?? $product['item_type_key'] ?? ''));
 $accessMode = strtolower((string)($product['digital_access_mode'] ?? ''));
 if (!empty($product['member_area_enabled']) || !empty($product['license_enabled'])) {
 return true;
 }
 if ($accessMode === 'member_area') {
 return true;
 }
 return in_array($deliveryType, ['course', 'e-course', 'ecourse', 'video', 'template', 'software', 'license', 'bundle', 'digital'], true)
 && ((function_exists('product_is_digital') && product_is_digital($product)) || $deliveryType !== 'digital');
 }
}

if (!function_exists('member_access_parse_course_modules')) {
 function member_access_parse_course_modules(string $raw): array
 {
 $modules = [];
 foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
 $line = trim($line);
 if ($line === '') {
 continue;
 }
 $parts = array_map('trim', explode('|', $line));
 $title = member_access_clean((string)($parts[0] ?? ''), 140);
 if ($title === '') {
 continue;
 }
 $url = trim((string)($parts[1] ?? ''));
 if ($url !== '' && !preg_match('#^https?://#i', $url) && !str_starts_with($url, '/')) {
 $url = '';
 }
 $modules[] = [
 'title' => $title,
 'url' => member_access_clean($url, 520),
 'duration' => member_access_clean((string)($parts[2] ?? ''), 80),
 'note' => member_access_clean((string)($parts[3] ?? ''), 180),
 'status' => 'locked_until_opened',
 ];
 if (count($modules) >= 80) {
 break;
 }
 }
 return $modules;
 }
}

if (!function_exists('member_access_product_course_modules')) {
 function member_access_product_course_modules(?array $product): array
 {
 $product = is_array($product) ? $product : [];
 $modules = $product['course_modules'] ?? [];
 if (is_string($modules)) {
 $decoded = json_decode($modules, true);
 $modules = is_array($decoded) ? $decoded : member_access_parse_course_modules($modules);
 }
 if (is_array($modules) && $modules) {
 $clean = [];
 foreach ($modules as $module) {
 if (!is_array($module)) {
 continue;
 }
 $title = member_access_clean((string)($module['title'] ?? ''), 140);
 if ($title === '') {
 continue;
 }
 $clean[] = [
 'title' => $title,
 'url' => member_access_clean((string)($module['url'] ?? ''), 520),
 'duration' => member_access_clean((string)($module['duration'] ?? ''), 80),
 'note' => member_access_clean((string)($module['note'] ?? ''), 180),
 'status' => member_access_clean((string)($module['status'] ?? 'available'), 50),
 ];
 }
 if ($clean) {
 return $clean;
 }
 }
 $raw = member_access_multiline_clean((string)($product['course_modules_raw'] ?? ''), 5000);
 if ($raw !== '') {
 return member_access_parse_course_modules($raw);
 }
 $type = strtolower((string)($product['digital_delivery_type'] ?? $product['item_type_key'] ?? ''));
 if (in_array($type, ['course', 'e-course', 'ecourse', 'video'], true)) {
 return [[
 'title' => 'Mulai Belajar / Buka Instruksi Course',
 'url' => member_access_clean((string)($product['digital_access_url'] ?? ''), 520),
 'duration' => member_access_clean((string)($product['age'] ?? ''), 80),
 'note' => 'Materi course mengikuti instruksi akses produk.',
 'status' => 'available',
 ]];
 }
 return [];
 }
}

if (!function_exists('member_access_product_license_config')) {
 function member_access_product_license_config(?array $product, array $settings = []): array
 {
 $product = is_array($product) ? $product : [];
 $settings = $settings ?: member_access_read_settings();
 $enabled = !empty($product['license_enabled']) || in_array(strtolower((string)($product['digital_delivery_type'] ?? '')), ['software', 'license'], true);
 $duration = max(0, (int)($product['license_duration_days'] ?? $settings['default_license_duration_days'] ?? 365));
 return [
 'enabled' => $enabled,
 'type' => member_access_clean((string)($product['license_type'] ?? ($enabled ? 'single_site' : 'none')), 80),
 'seats' => max(1, (int)($product['license_seats'] ?? 1)),
 'activation_limit' => max(1, (int)($product['license_activation_limit'] ?? 1)),
 'duration_days' => $duration,
 'note' => member_access_multiline_clean((string)($product['license_note'] ?? ''), 900),
 'validation_mode' => member_access_clean((string)($product['license_validation_mode'] ?? 'global'), 80),
 'domain_lock' => !empty($product['license_domain_lock']),
 'central_product_id' => member_access_clean((string)($product['central_license_product_id'] ?? ''), 140),
 ];
 }
}

if (!function_exists('member_access_generate_license_key')) {
 function member_access_generate_license_key(string $prefix, string $orderId = ''): string
 {
 $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'UGR');
 $hash = strtoupper(substr(hash('sha256', $orderId . '|' . random_bytes(16) . '|' . microtime(true)), 0, 16));
 return $prefix . '-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4) . '-' . substr($hash, 12, 4);
 }
}

if (!function_exists('member_access_public_url')) {
 function member_access_public_url(array $record, array $order = []): string
 {
 $query = ['access' => (string)($record['access_token'] ?? '')];
 $ref = $order ? (function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? '')) : (string)($record['order_ref'] ?? '');
 if ($ref !== '') {
 $query['ref'] = $ref;
 }
 $orderToken = $order ? (function_exists('order_public_token') ? order_public_token($order) : (string)($order['public_token'] ?? '')) : (string)($record['order_token'] ?? '');
 if ($orderToken !== '') {
 $query['token'] = $orderToken;
 }
 return url('member-area?' . http_build_query($query));
 }
}

if (!function_exists('member_access_can_issue_for_order')) {
 function member_access_can_issue_for_order(array $order): array
 {
 $settings = member_access_read_settings();
 if (empty($settings['enabled'])) {
 return ['ok' => false, 'reason' => 'Member Area sedang nonaktif.'];
 }
 $product = member_access_product_for_order($order);
 if (!member_access_product_enabled($product, $order)) {
 return ['ok' => false, 'reason' => 'Produk ini belum disiapkan untuk member area/course/lisensi.'];
 }
 $paymentStatus = (string)($order['payment_status'] ?? 'Belum Ditagih');
 $paidStatuses = (array)($settings['paid_statuses'] ?? ['Lunas']);
 if (!in_array($paymentStatus, $paidStatuses, true)) {
 return ['ok' => false, 'reason' => 'Pembayaran belum memenuhi aturan pembukaan akses member.'];
 }
 return ['ok' => true, 'product' => $product, 'settings' => $settings];
 }
}

if (!function_exists('member_access_issue_for_order')) {
 function member_access_issue_for_order(array $order, string $source = 'manual'): array
 {
 $can = member_access_can_issue_for_order($order);
 if (empty($can['ok'])) {
 return ['ok' => false, 'message' => (string)($can['reason'] ?? 'Akses member belum bisa dibuat.')];
 }
 $product = (array)$can['product'];
 $settings = (array)$can['settings'];
 $orderId = member_access_clean((string)($order['id'] ?? ''), 120);
 if ($orderId === '') {
 return ['ok' => false, 'message' => 'ID order tidak valid.'];
 }

 $records = member_access_read_records();
 $existing = is_array($records[$orderId] ?? null) ? $records[$orderId] : [];
 $token = (string)($existing['access_token'] ?? '');
 if ($token === '') {
 $token = bin2hex(random_bytes(24));
 }
 $licenseConfig = member_access_product_license_config($product, $settings);
 $license = is_array($existing['license'] ?? null) ? $existing['license'] : [];
 if (!empty($licenseConfig['enabled']) && (string)($license['key'] ?? '') === '') {
 $license['key'] = member_access_generate_license_key((string)($settings['license_prefix'] ?? 'UGR'), $orderId);
 }
 if (!empty($licenseConfig['enabled'])) {
 $license = array_merge($license, [
 'enabled' => true,
 'type' => (string)$licenseConfig['type'],
 'seats' => (int)$licenseConfig['seats'],
 'activation_limit' => (int)$licenseConfig['activation_limit'],
 'activation_count' => (int)($license['activation_count'] ?? 0),
 'duration_days' => (int)$licenseConfig['duration_days'],
 'expires_at' => (int)$licenseConfig['duration_days'] > 0 ? date('c', strtotime('+' . (int)$licenseConfig['duration_days'] . ' days')) : '',
 'note' => (string)$licenseConfig['note'],
 'validation_mode' => (string)($licenseConfig['validation_mode'] ?? 'global'),
 'domain_lock' => !empty($licenseConfig['domain_lock']),
 'central_product_id' => (string)($licenseConfig['central_product_id'] ?? ''),
 ]);
 }

 $durationDays = (int)($product['access_duration_days'] ?? 0);
 if ($durationDays <= 0) {
 $durationDays = (int)($settings['default_member_access_days'] ?? 365);
 }
 $digitalRecord = function_exists('digital_delivery_record_for_order') ? digital_delivery_record_for_order($order) : null;
 $record = array_merge($existing, [
 'order_id' => $orderId,
 'order_ref' => function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? ''),
 'order_token' => function_exists('order_public_token') ? order_public_token($order) : (string)($order['public_token'] ?? ''),
 'customer_name' => member_access_clean((string)($order['name'] ?? ''), 120),
 'customer_email' => strtolower(member_access_clean((string)($order['email'] ?? ''), 160)),
 'customer_phone' => member_access_clean((string)($order['phone'] ?? ''), 50),
 'product_slug' => (string)($product['slug'] ?? $order['product_slug'] ?? ''),
 'product_title' => (string)($product['title'] ?? $order['product_title'] ?? 'Produk Digital'),
 'product_type' => member_access_clean((string)($product['digital_delivery_type'] ?? $product['item_type_key'] ?? 'digital'), 80),
 'access_token' => $token,
 'status' => 'active',
 'source' => member_access_clean($source, 80),
 'course_modules' => member_access_product_course_modules($product),
 'course_progress' => is_array($existing['course_progress'] ?? null) ? $existing['course_progress'] : [],
 'instructions' => member_access_multiline_clean((string)($product['digital_instructions'] ?? ''), 2000),
 'digital_access_url' => (string)($product['digital_access_url'] ?? ''),
 'digital_file_url' => (string)($product['digital_file_url'] ?? ''),
 'digital_delivery_url' => is_array($digitalRecord) ? (string)($digitalRecord['public_url'] ?? '') : '',
 'license' => $license,
 'issued_at' => (string)($existing['issued_at'] ?? date('c')),
 'expires_at' => date('c', strtotime('+' . max(1, $durationDays) . ' days')),
 'last_opened_at' => (string)($existing['last_opened_at'] ?? ''),
 'updated_at' => date('c'),
 ]);
 $record['public_url'] = member_access_public_url($record, $order);
 $records[$orderId] = $record;
 $ok = member_access_write_records($records);
 if ($ok) {
 member_access_log_event([
 'id' => 'ma_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
 'action' => !empty($existing) ? 'member_access_refreshed' : 'member_access_issued',
 'source' => $source,
 'order_id' => $orderId,
 'order_ref' => (string)$record['order_ref'],
 'product_title' => (string)$record['product_title'],
 'has_license' => !empty($license['enabled']),
 ]);
 if (function_exists('buyer_account_upsert_from_order')) {
 buyer_account_upsert_from_order($order, $record);
 }
 if (function_exists('license_manager_record_from_member_access')) {
 license_manager_record_from_member_access($record);
 }
 if (function_exists('subscription_issue_for_order')) {
 subscription_issue_for_order($order, $record);
 }
 if (function_exists('activity_log_record')) {
 activity_log_record('member_access_issued', 'order', $orderId, 'Akses member/course/lisensi dibuat atau diperbarui.', ['source' => $source, 'product' => (string)$record['product_title']]);
 }
 }
 return $ok ? ['ok' => true, 'record' => $record, 'message' => 'Akses member aktif.'] : ['ok' => false, 'message' => 'Akses member belum bisa disimpan. Cek permission storage.'];
 }
}

if (!function_exists('member_access_maybe_issue_for_order')) {
 function member_access_maybe_issue_for_order(array|string $orderOrId, string $source = 'auto'): array
 {
 $settings = member_access_read_settings();
 if (empty($settings['enabled']) || empty($settings['auto_enroll_when_paid'])) {
 return ['ok' => false, 'message' => 'Auto enroll member nonaktif.'];
 }
 $order = is_array($orderOrId) ? $orderOrId : (function_exists('order_find_by_id') ? order_find_by_id((string)$orderOrId) : null);
 if (!$order) {
 return ['ok' => false, 'message' => 'Order tidak ditemukan.'];
 }
 return member_access_issue_for_order($order, $source);
 }
}

if (!function_exists('member_access_record_for_order')) {
 function member_access_record_for_order(array|string $orderOrId): ?array
 {
 $orderId = is_array($orderOrId) ? (string)($orderOrId['id'] ?? '') : (string)$orderOrId;
 $orderId = member_access_clean($orderId, 120);
 if ($orderId === '') {
 return null;
 }
 $records = member_access_read_records();
 return is_array($records[$orderId] ?? null) ? $records[$orderId] : null;
 }
}

if (!function_exists('member_access_record_by_token')) {
 function member_access_record_by_token(string $token): ?array
 {
 $token = member_access_clean($token, 120);
 if ($token === '') {
 return null;
 }
 foreach (member_access_read_records() as $record) {
 if (is_array($record) && hash_equals((string)($record['access_token'] ?? ''), $token)) {
 return $record;
 }
 }
 return null;
 }
}

if (!function_exists('member_access_records_by_email')) {
 function member_access_records_by_email(string $email, string $ref = ''): array
 {
 $email = strtolower(member_access_clean($email, 160));
 $ref = member_access_clean($ref, 100);
 if ($email === '') {
 return [];
 }
 $records = [];
 foreach (member_access_read_records() as $record) {
 if (!is_array($record)) {
 continue;
 }
 if (strtolower((string)($record['customer_email'] ?? '')) !== $email) {
 continue;
 }
 if ($ref !== '' && strcasecmp((string)($record['order_ref'] ?? ''), $ref) !== 0) {
 continue;
 }
 $records[] = $record;
 }
 usort($records, static fn(array $a, array $b): int => strtotime((string)($b['updated_at'] ?? '')) <=> strtotime((string)($a['updated_at'] ?? '')));
 return $records;
 }
}

if (!function_exists('member_access_record_is_expired')) {
 function member_access_record_is_expired(array $record): bool
 {
 $expires = strtotime((string)($record['expires_at'] ?? ''));
 return $expires !== false && $expires > 0 && $expires < time();
 }
}

if (!function_exists('member_access_touch_open')) {
 function member_access_touch_open(string $token): void
 {
 $records = member_access_read_records();
 foreach ($records as $id => $record) {
 if (is_array($record) && hash_equals((string)($record['access_token'] ?? ''), $token)) {
 $records[$id]['last_opened_at'] = date('c');
 member_access_write_records($records);
 return;
 }
 }
 }
}

if (!function_exists('member_access_public_status')) {
 function member_access_public_status(array $order): array
 {
 $settings = member_access_read_settings();
 $record = member_access_record_for_order($order);
 if ($record) {
 if (member_access_record_is_expired($record)) {
 return ['state' => 'expired', 'url' => '', 'message' => 'Akses member sudah kedaluwarsa. Hubungi admin jika perlu diperpanjang.'];
 }
 if ((string)($record['status'] ?? 'active') !== 'active') {
 return ['state' => 'inactive', 'url' => '', 'message' => 'Akses member sedang nonaktif.'];
 }
 return ['state' => 'active', 'url' => (string)($record['public_url'] ?? member_access_public_url($record, $order)), 'message' => 'Akses member/course/lisensi sudah aktif.'];
 }
 $can = member_access_can_issue_for_order($order);
 if (!empty($can['ok'])) {
 return ['state' => 'ready', 'url' => '', 'message' => 'Akses member siap dibuat. Hubungi admin jika link belum muncul.'];
 }
 $product = member_access_product_for_order($order);
 if (member_access_product_enabled($product, $order)) {
 return ['state' => 'pending', 'url' => '', 'message' => (string)($settings['pending_note'] ?? 'Akses member akan aktif setelah pembayaran dikonfirmasi.')];
 }
 return ['state' => '', 'url' => '', 'message' => ''];
 }
}

if (!function_exists('member_access_summary')) {
 function member_access_summary(): array
 {
 $records = member_access_read_records();
 $active = 0;
 $course = 0;
 $license = 0;
 $expired = 0;
 $recent = [];
 foreach ($records as $record) {
 if (!is_array($record)) {
 continue;
 }
 if (member_access_record_is_expired($record)) {
 $expired++;
 } elseif ((string)($record['status'] ?? 'active') === 'active') {
 $active++;
 }
 if (!empty($record['course_modules'])) {
 $course++;
 }
 if (!empty($record['license']['enabled'])) {
 $license++;
 }
 $recent[] = $record;
 }
 usort($recent, static fn(array $a, array $b): int => strtotime((string)($b['updated_at'] ?? $b['issued_at'] ?? '')) <=> strtotime((string)($a['updated_at'] ?? $a['issued_at'] ?? '')));
 return [
 'total' => count($records),
 'active' => $active,
 'course' => $course,
 'license' => $license,
 'expired' => $expired,
 'recent' => array_slice($recent, 0, 30),
 ];
 }
}
