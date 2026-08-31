<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DIGITAL PRODUCT DELIVERY CENTER - U-Growth
|--------------------------------------------------------------------------
| Lightweight file-based access delivery for e-book, template, course,
| link access, bundle, and other digital products. It is designed to work
| on shared hosting and integrates with order/payment status without forcing
| a member-area database.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('digital_delivery_clean')) {
 function digital_delivery_clean(string $value, int $max = 160): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('digital_delivery_multiline_clean')) {
 function digital_delivery_multiline_clean(string $value, int $max = 1600): string
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

if (!function_exists('digital_delivery_url_clean')) {
 function digital_delivery_url_clean(string $value, int $max = 520): string
 {
 $value = trim(strip_tags($value));
 if ($value === '') {
 return '';
 }
 if (!preg_match('#^https?://#i', $value) && !str_starts_with($value, '/') && !str_starts_with($value, 'assets/')) {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('digital_delivery_settings_file')) {
 function digital_delivery_settings_file(): string
 {
 return STORAGE_PATH . '/digital-delivery-settings.json';
 }
}

if (!function_exists('digital_delivery_access_file')) {
 function digital_delivery_access_file(): string
 {
 return STORAGE_PATH . '/digital-delivery-access.json';
 }
}

if (!function_exists('digital_delivery_log_file')) {
 function digital_delivery_log_file(?int $timestamp = null): string
 {
 $timestamp = $timestamp ?: time();
 return LOGS_PATH . '/digital-delivery-' . date('Y-m', $timestamp) . '.jsonl';
 }
}

if (!function_exists('digital_delivery_default_settings')) {
 function digital_delivery_default_settings(): array
 {
 return [
 'enabled' => true,
 'auto_issue_when_paid' => true,
 'paid_statuses' => ['Lunas'],
 'issue_on_dp' => false,
 'default_access_days' => 30,
 'default_download_limit' => 5,
 'show_access_on_order_status' => true,
 'show_access_on_invoice' => true,
 'require_order_token' => true,
 'public_note' => 'Akses digital akan aktif setelah pembayaran dikonfirmasi. Simpan link akses ini dan jangan dibagikan ke pihak lain.',
 'pending_note' => 'Akses digital belum aktif. Jika sudah membayar, silakan tunggu admin memverifikasi pembayaran atau hubungi admin.',
 'customer_message_template' => "Halo {name}, akses digital untuk pesanan Anda sudah aktif.\n\nNo. Order: {order_ref}\nProduk: {product}\nLink akses: {access_url}\n\nCatatan akses:\n{instructions}",
 ];
 }
}

if (!function_exists('digital_delivery_read_settings')) {
 function digital_delivery_read_settings(): array
 {
 $defaults = digital_delivery_default_settings();
 $file = digital_delivery_settings_file();
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
 $settings['default_access_days'] = max(1, min(3650, (int)($settings['default_access_days'] ?? 30)));
 $settings['default_download_limit'] = max(0, min(9999, (int)($settings['default_download_limit'] ?? 5)));
 return $settings;
 }
}

if (!function_exists('digital_delivery_write_settings')) {
 function digital_delivery_write_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $defaults = digital_delivery_default_settings();
 $payload = [
 'enabled' => !empty($settings['enabled']),
 'auto_issue_when_paid' => !empty($settings['auto_issue_when_paid']),
 'paid_statuses' => array_values(array_filter(array_map('strval', (array)($settings['paid_statuses'] ?? $defaults['paid_statuses'])))),
 'issue_on_dp' => !empty($settings['issue_on_dp']),
 'default_access_days' => max(1, min(3650, (int)($settings['default_access_days'] ?? $defaults['default_access_days']))),
 'default_download_limit' => max(0, min(9999, (int)($settings['default_download_limit'] ?? $defaults['default_download_limit']))),
 'show_access_on_order_status' => !empty($settings['show_access_on_order_status']),
 'show_access_on_invoice' => !empty($settings['show_access_on_invoice']),
 'require_order_token' => !empty($settings['require_order_token']),
 'public_note' => digital_delivery_multiline_clean((string)($settings['public_note'] ?? $defaults['public_note']), 900),
 'pending_note' => digital_delivery_multiline_clean((string)($settings['pending_note'] ?? $defaults['pending_note']), 900),
 'customer_message_template' => digital_delivery_multiline_clean((string)($settings['customer_message_template'] ?? $defaults['customer_message_template']), 1600),
 ];
 if (!$payload['paid_statuses']) {
 $payload['paid_statuses'] = ['Lunas'];
 }
 return @file_put_contents(digital_delivery_settings_file(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('digital_delivery_read_access_records')) {
 function digital_delivery_read_access_records(): array
 {
 $file = digital_delivery_access_file();
 if (!is_file($file)) {
 return [];
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return is_array($data) ? $data : [];
 }
}

if (!function_exists('digital_delivery_write_access_records')) {
 function digital_delivery_write_access_records(array $records): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 return @file_put_contents(digital_delivery_access_file(), json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('digital_delivery_log_event')) {
 function digital_delivery_log_event(array $event): void
 {
 if (!is_dir(LOGS_PATH)) {
 @mkdir(LOGS_PATH, 0775, true);
 }
 $event['created_at'] = (string)($event['created_at'] ?? date('c'));
 @file_put_contents(digital_delivery_log_file(), json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
 }
}

if (!function_exists('digital_delivery_product_for_order')) {
 function digital_delivery_product_for_order(array $order): ?array
 {
 $slug = digital_delivery_clean((string)($order['product_slug'] ?? ''), 160);
 if ($slug !== '' && function_exists('get_product_by_slug')) {
 $product = get_product_by_slug($slug);
 if (is_array($product)) {
 return $product;
 }
 }
 return null;
 }
}

if (!function_exists('digital_delivery_product_is_digital')) {
 function digital_delivery_product_is_digital(?array $product, array $order = []): bool
 {
 if ($product && function_exists('product_is_digital') && product_is_digital($product)) {
 return true;
 }
 $shippingPolicy = strtolower((string)($order['commerce_shipping_policy'] ?? $order['commerce_shipping_policy_mode'] ?? ''));
 if (str_contains($shippingPolicy, 'digital') || str_contains($shippingPolicy, 'not_required')) {
 return true;
 }
 $type = strtolower((string)($product['item_type_key'] ?? $product['item_type'] ?? $product['digital_delivery_type'] ?? $order['category'] ?? ''));
 return in_array($type, ['digital', 'ebook', 'e-book', 'course', 'template', 'zip', 'bundle digital'], true);
 }
}

if (!function_exists('digital_delivery_product_assets')) {
 function digital_delivery_product_assets(?array $product, array $order = []): array
 {
 $product = is_array($product) ? $product : [];
 $fileUrl = digital_delivery_url_clean((string)($product['digital_file_url'] ?? ''), 520);
 $accessUrl = digital_delivery_url_clean((string)($product['digital_access_url'] ?? ''), 520);
 $instructions = digital_delivery_multiline_clean((string)($product['digital_instructions'] ?? ''), 1600);
 $mode = digital_delivery_clean((string)($product['digital_access_mode'] ?? 'after_payment'), 80);
 $type = digital_delivery_clean((string)($product['digital_delivery_type'] ?? ($product['item_type_key'] ?? 'digital')), 80);
 return [
 'product_slug' => digital_delivery_clean((string)($product['slug'] ?? $order['product_slug'] ?? ''), 160),
 'product_title' => digital_delivery_clean((string)($product['title'] ?? $product['name'] ?? $order['product_title'] ?? 'Produk Digital'), 180),
 'delivery_type' => $type !== '' ? $type : 'digital',
 'access_mode' => $mode !== '' ? $mode : 'after_payment',
 'file_url' => $fileUrl,
 'access_url' => $accessUrl,
 'instructions' => $instructions !== '' ? $instructions : 'Ikuti instruksi pada halaman akses. Jika link belum tampil, hubungi admin dengan nomor order.',
 ];
 }
}

if (!function_exists('digital_delivery_order_can_issue')) {
 function digital_delivery_order_can_issue(array $order): array
 {
 $settings = digital_delivery_read_settings();
 if (empty($settings['enabled'])) {
 return ['ok' => false, 'reason' => 'Digital Delivery Center sedang nonaktif.'];
 }
 $product = digital_delivery_product_for_order($order);
 if (!digital_delivery_product_is_digital($product, $order)) {
 return ['ok' => false, 'reason' => 'Order ini bukan produk digital.'];
 }
 $assets = digital_delivery_product_assets($product, $order);
 if ((string)$assets['file_url'] === '' && (string)$assets['access_url'] === '' && (string)$assets['instructions'] === '') {
 return ['ok' => false, 'reason' => 'Produk digital belum memiliki link/file/instruksi akses.'];
 }
 $paymentStatus = (string)($order['payment_status'] ?? 'Belum Ditagih');
 $paidStatuses = (array)($settings['paid_statuses'] ?? ['Lunas']);
 if (!in_array($paymentStatus, $paidStatuses, true)) {
 return ['ok' => false, 'reason' => 'Pembayaran belum memenuhi aturan rilis akses digital.'];
 }
 return ['ok' => true, 'product' => $product, 'assets' => $assets, 'settings' => $settings];
 }
}

if (!function_exists('digital_delivery_public_access_url')) {
 function digital_delivery_public_access_url(array $record, array $order = []): string
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
 return url('digital-access?' . http_build_query($query));
 }
}

if (!function_exists('digital_delivery_download_url')) {
 function digital_delivery_download_url(array $record): string
 {
 return url('digital-download?' . http_build_query(['access' => (string)($record['access_token'] ?? '')]));
 }
}

if (!function_exists('digital_delivery_issue_for_order')) {
 function digital_delivery_issue_for_order(array $order, string $source = 'manual'): array
 {
 $can = digital_delivery_order_can_issue($order);
 if (empty($can['ok'])) {
 return ['ok' => false, 'message' => (string)($can['reason'] ?? 'Akses digital belum bisa dibuat.')];
 }

 $settings = (array)$can['settings'];
 $assets = (array)$can['assets'];
 $orderId = digital_delivery_clean((string)($order['id'] ?? ''), 100);
 if ($orderId === '') {
 return ['ok' => false, 'message' => 'ID order tidak valid.'];
 }

 $records = digital_delivery_read_access_records();
 $existing = is_array($records[$orderId] ?? null) ? $records[$orderId] : [];
 $token = (string)($existing['access_token'] ?? '');
 if ($token === '') {
 $token = bin2hex(random_bytes(24));
 }
 $issuedAt = (string)($existing['issued_at'] ?? date('c'));
 $expiresAt = date('c', strtotime('+' . (int)$settings['default_access_days'] . ' days'));
 $record = array_merge($existing, [
 'order_id' => $orderId,
 'order_ref' => function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? ''),
 'order_token' => function_exists('order_public_token') ? order_public_token($order) : (string)($order['public_token'] ?? ''),
 'customer_name' => digital_delivery_clean((string)($order['name'] ?? ''), 120),
 'customer_email' => digital_delivery_clean((string)($order['email'] ?? ''), 160),
 'customer_phone' => digital_delivery_clean((string)($order['phone'] ?? ''), 40),
 'product_slug' => (string)$assets['product_slug'],
 'product_title' => (string)$assets['product_title'],
 'delivery_type' => (string)$assets['delivery_type'],
 'access_mode' => (string)$assets['access_mode'],
 'file_url' => (string)$assets['file_url'],
 'access_url' => (string)$assets['access_url'],
 'instructions' => (string)$assets['instructions'],
 'access_token' => $token,
 'status' => 'active',
 'source' => digital_delivery_clean($source, 80),
 'download_count' => (int)($existing['download_count'] ?? 0),
 'download_limit' => (int)$settings['default_download_limit'],
 'issued_at' => $issuedAt,
 'expires_at' => $expiresAt,
 'last_opened_at' => (string)($existing['last_opened_at'] ?? ''),
 'last_downloaded_at' => (string)($existing['last_downloaded_at'] ?? ''),
 'updated_at' => date('c'),
 ]);
 $record['public_url'] = digital_delivery_public_access_url($record, $order);
 $records[$orderId] = $record;
 $ok = digital_delivery_write_access_records($records);
 if ($ok) {
 digital_delivery_log_event([
 'id' => 'ddl_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
 'action' => !empty($existing) ? 'access_refreshed' : 'access_issued',
 'source' => $source,
 'order_id' => $orderId,
 'order_ref' => (string)$record['order_ref'],
 'product_title' => (string)$record['product_title'],
 'status' => 'active',
 ]);
 if (function_exists('activity_log_record')) {
 activity_log_record('digital_access_issued', 'order', $orderId, 'Akses produk digital dibuat/diperbarui.', ['source' => $source, 'product' => (string)$record['product_title']]);
 }
 }
 return $ok ? ['ok' => true, 'record' => $record, 'message' => 'Akses digital aktif.'] : ['ok' => false, 'message' => 'Akses digital belum bisa disimpan. Cek permission storage.'];
 }
}

if (!function_exists('digital_delivery_maybe_issue_for_order')) {
 function digital_delivery_maybe_issue_for_order(array|string $orderOrId, string $source = 'auto'): array
 {
 $settings = digital_delivery_read_settings();
 if (empty($settings['enabled']) || empty($settings['auto_issue_when_paid'])) {
 return ['ok' => false, 'message' => 'Auto issue nonaktif.'];
 }
 $order = is_array($orderOrId) ? $orderOrId : (function_exists('order_find_by_id') ? order_find_by_id((string)$orderOrId) : null);
 if (!$order) {
 return ['ok' => false, 'message' => 'Order tidak ditemukan.'];
 }
 return digital_delivery_issue_for_order($order, $source);
 }
}

if (!function_exists('digital_delivery_record_for_order')) {
 function digital_delivery_record_for_order(array|string $orderOrId): ?array
 {
 $orderId = is_array($orderOrId) ? (string)($orderOrId['id'] ?? '') : (string)$orderOrId;
 $orderId = digital_delivery_clean($orderId, 100);
 if ($orderId === '') {
 return null;
 }
 $records = digital_delivery_read_access_records();
 return is_array($records[$orderId] ?? null) ? $records[$orderId] : null;
 }
}

if (!function_exists('digital_delivery_record_by_token')) {
 function digital_delivery_record_by_token(string $token): ?array
 {
 $token = digital_delivery_clean($token, 120);
 if ($token === '') {
 return null;
 }
 foreach (digital_delivery_read_access_records() as $record) {
 if (is_array($record) && hash_equals((string)($record['access_token'] ?? ''), $token)) {
 return $record;
 }
 }
 return null;
 }
}

if (!function_exists('digital_delivery_record_is_expired')) {
 function digital_delivery_record_is_expired(array $record): bool
 {
 $expires = strtotime((string)($record['expires_at'] ?? ''));
 return $expires !== false && $expires > 0 && $expires < time();
 }
}

if (!function_exists('digital_delivery_record_download_allowed')) {
 function digital_delivery_record_download_allowed(array $record): bool
 {
 if (digital_delivery_record_is_expired($record)) {
 return false;
 }
 if ((string)($record['status'] ?? '') !== 'active') {
 return false;
 }
 $limit = (int)($record['download_limit'] ?? 0);
 if ($limit <= 0) {
 return true;
 }
 return (int)($record['download_count'] ?? 0) < $limit;
 }
}

if (!function_exists('digital_delivery_touch_open')) {
 function digital_delivery_touch_open(string $token): void
 {
 $records = digital_delivery_read_access_records();
 foreach ($records as $id => $record) {
 if (is_array($record) && hash_equals((string)($record['access_token'] ?? ''), $token)) {
 $records[$id]['last_opened_at'] = date('c');
 digital_delivery_write_access_records($records);
 return;
 }
 }
 }
}

if (!function_exists('digital_delivery_touch_download')) {
 function digital_delivery_touch_download(string $token): ?array
 {
 $records = digital_delivery_read_access_records();
 foreach ($records as $id => $record) {
 if (!is_array($record) || !hash_equals((string)($record['access_token'] ?? ''), $token)) {
 continue;
 }
 if (!digital_delivery_record_download_allowed($record)) {
 return null;
 }
 $records[$id]['download_count'] = (int)($record['download_count'] ?? 0) + 1;
 $records[$id]['last_downloaded_at'] = date('c');
 digital_delivery_write_access_records($records);
 digital_delivery_log_event([
 'id' => 'ddl_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
 'action' => 'download_clicked',
 'order_id' => (string)($record['order_id'] ?? ''),
 'order_ref' => (string)($record['order_ref'] ?? ''),
 'product_title' => (string)($record['product_title'] ?? ''),
 'download_count' => (int)$records[$id]['download_count'],
 ]);
 return $records[$id];
 }
 return null;
 }
}

if (!function_exists('digital_delivery_public_status')) {
 function digital_delivery_public_status(array $order): array
 {
 $record = digital_delivery_record_for_order($order);
 if ($record) {
 return [
 'state' => digital_delivery_record_is_expired($record) ? 'expired' : ((string)($record['status'] ?? 'active')),
 'record' => $record,
 'url' => digital_delivery_public_access_url($record, $order),
 'message' => digital_delivery_record_is_expired($record) ? 'Akses digital sudah kedaluwarsa. Hubungi admin untuk perpanjangan.' : 'Akses digital sudah aktif.',
 ];
 }
 $can = digital_delivery_order_can_issue($order);
 return [
 'state' => 'pending',
 'record' => null,
 'url' => '',
 'message' => (string)($can['reason'] ?? digital_delivery_read_settings()['pending_note'] ?? 'Akses digital belum aktif.'),
 ];
 }
}

if (!function_exists('digital_delivery_whatsapp_message')) {
 function digital_delivery_whatsapp_message(array $order, array $record): string
 {
 $settings = digital_delivery_read_settings();
 $template = (string)($settings['customer_message_template'] ?? '');
 if ($template === '') {
 $template = (string)digital_delivery_default_settings()['customer_message_template'];
 }
 $values = [
 '{name}' => (string)($order['name'] ?? $record['customer_name'] ?? ''),
 '{order_ref}' => function_exists('order_public_reference') ? order_public_reference($order) : (string)($record['order_ref'] ?? ''),
 '{product}' => (string)($record['product_title'] ?? $order['product_title'] ?? 'Produk digital'),
 '{access_url}' => digital_delivery_public_access_url($record, $order),
 '{instructions}' => (string)($record['instructions'] ?? ''),
 '{site_name}' => SITE_NAME,
 ];
 return strtr($template, $values);
 }
}

if (!function_exists('digital_delivery_summary')) {
 function digital_delivery_summary(): array
 {
 $records = digital_delivery_read_access_records();
 $active = 0;
 $expired = 0;
 $downloads = 0;
 $recent = [];
 foreach ($records as $record) {
 if (!is_array($record)) {
 continue;
 }
 if (digital_delivery_record_is_expired($record)) {
 $expired++;
 } elseif ((string)($record['status'] ?? 'active') === 'active') {
 $active++;
 }
 $downloads += (int)($record['download_count'] ?? 0);
 $recent[] = $record;
 }
 usort($recent, static fn(array $a, array $b): int => strtotime((string)($b['updated_at'] ?? $b['issued_at'] ?? '')) <=> strtotime((string)($a['updated_at'] ?? $a['issued_at'] ?? '')));
 return [
 'total' => count($records),
 'active' => $active,
 'expired' => $expired,
 'downloads' => $downloads,
 'recent' => array_slice($recent, 0, 25),
 ];
 }
}
