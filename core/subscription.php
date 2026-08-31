<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MEMBERSHIP SUBSCRIPTION & EXPIRY REMINDER - U-Growth
|--------------------------------------------------------------------------
| Lightweight subscription records for membership, course, license, support,
| and software/template update plans. Renewal is manual-friendly first.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('subscription_clean')) {
 function subscription_clean(string $value, int $max = 180): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 return $value === '' ? '' : (function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max));
 }
}

if (!function_exists('subscription_settings_file')) {
 function subscription_settings_file(): string
 {
 return STORAGE_PATH . '/subscription-settings.json';
 }
}

if (!function_exists('subscription_records_file')) {
 function subscription_records_file(): string
 {
 return STORAGE_PATH . '/subscription-records.json';
 }
}

if (!function_exists('subscription_log_file')) {
 function subscription_log_file(?int $timestamp = null): string
 {
 return LOGS_PATH . '/subscription-' . date('Y-m', $timestamp ?: time()) . '.jsonl';
 }
}

if (!function_exists('subscription_default_settings')) {
 function subscription_default_settings(): array
 {
 return [
 'enabled' => true,
 'auto_create_after_paid' => true,
 'default_cycle' => 'monthly',
 'default_duration_days' => 30,
 'default_grace_days' => 3,
 'reminder_days' => [7, 3, 1, 0, -1],
 'show_on_member_area' => true,
 'renewal_message_template' => "Halo {name}, masa berlangganan {product} akan berakhir pada {expires_at}.\n\nPerpanjang akses di sini: {renewal_url}\nOrder: {order_ref}",
 ];
 }
}

if (!function_exists('subscription_read_settings')) {
 function subscription_read_settings(): array
 {
 $defaults = subscription_default_settings();
 $file = subscription_settings_file();
 if (!is_file($file)) {
 return $defaults;
 }
 $data = json_decode((string)@file_get_contents($file), true);
 if (!is_array($data)) {
 return $defaults;
 }
 $settings = array_merge($defaults, $data);
 $settings['default_duration_days'] = max(1, min(3650, (int)($settings['default_duration_days'] ?? 30)));
 $settings['default_grace_days'] = max(0, min(90, (int)($settings['default_grace_days'] ?? 3)));
 $settings['reminder_days'] = array_values(array_unique(array_map('intval', (array)($settings['reminder_days'] ?? $defaults['reminder_days']))));
 sort($settings['reminder_days']);
 return $settings;
 }
}

if (!function_exists('subscription_write_settings')) {
 function subscription_write_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $defaults = subscription_default_settings();
 $reminderRaw = $settings['reminder_days'] ?? $defaults['reminder_days'];
 if (is_string($reminderRaw)) {
 $reminderRaw = preg_split('/[,\s]+/', $reminderRaw) ?: [];
 }
 $reminders = array_values(array_unique(array_map('intval', (array)$reminderRaw)));
 sort($reminders);
 $payload = [
 'enabled' => !empty($settings['enabled']),
 'auto_create_after_paid' => !empty($settings['auto_create_after_paid']),
 'default_cycle' => subscription_clean((string)($settings['default_cycle'] ?? $defaults['default_cycle']), 80),
 'default_duration_days' => max(1, min(3650, (int)($settings['default_duration_days'] ?? $defaults['default_duration_days']))),
 'default_grace_days' => max(0, min(90, (int)($settings['default_grace_days'] ?? $defaults['default_grace_days']))),
 'reminder_days' => $reminders ?: $defaults['reminder_days'],
 'show_on_member_area' => !empty($settings['show_on_member_area']),
 'renewal_message_template' => trim(strip_tags((string)($settings['renewal_message_template'] ?? $defaults['renewal_message_template']))),
 ];
 return @file_put_contents(subscription_settings_file(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('subscription_read_records')) {
 function subscription_read_records(): array
 {
 $file = subscription_records_file();
 if (!is_file($file)) {
 return [];
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return is_array($data) ? $data : [];
 }
}

if (!function_exists('subscription_write_records')) {
 function subscription_write_records(array $records): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 return @file_put_contents(subscription_records_file(), json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('subscription_log_event')) {
 function subscription_log_event(array $event): void
 {
 if (!is_dir(LOGS_PATH)) {
 @mkdir(LOGS_PATH, 0775, true);
 }
 $event['created_at'] = (string)($event['created_at'] ?? date('c'));
 @file_put_contents(subscription_log_file(), json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
 }
}

if (!function_exists('subscription_cycle_options')) {
 function subscription_cycle_options(): array
 {
 return [
 'none' => 'Sekali beli / bukan subscription',
 'monthly' => 'Bulanan',
 'three_months' => '3 Bulanan',
 'six_months' => '6 Bulanan',
 'yearly' => 'Tahunan',
 'lifetime' => 'Lifetime',
 'custom' => 'Custom hari',
 ];
 }
}

if (!function_exists('subscription_duration_for_cycle')) {
 function subscription_duration_for_cycle(string $cycle, int $customDays = 0): int
 {
 return match ($cycle) {
 'monthly' => 30,
 'three_months' => 90,
 'six_months' => 180,
 'yearly' => 365,
 'lifetime' => 0,
 'custom' => max(1, $customDays),
 default => 0,
 };
 }
}

if (!function_exists('subscription_product_config')) {
 function subscription_product_config(?array $product, array $settings = []): array
 {
 $product = is_array($product) ? $product : [];
 $settings = $settings ?: subscription_read_settings();
 $enabled = !empty($product['subscription_enabled']) || strtolower((string)($product['license_type'] ?? '')) === 'subscription';
 $cycle = (string)($product['subscription_billing_cycle'] ?? ($enabled ? $settings['default_cycle'] : 'none'));
 if (!array_key_exists($cycle, subscription_cycle_options())) {
 $cycle = 'monthly';
 }
 $duration = (int)($product['subscription_duration_days'] ?? 0);
 $duration = $duration > 0 ? $duration : subscription_duration_for_cycle($cycle, (int)($settings['default_duration_days'] ?? 30));
 return [
 'enabled' => $enabled && $cycle !== 'none',
 'cycle' => $cycle,
 'duration_days' => $duration,
 'grace_days' => max(0, (int)($product['subscription_grace_days'] ?? $settings['default_grace_days'] ?? 3)),
 'renewal_mode' => subscription_clean((string)($product['subscription_renewal_mode'] ?? 'manual_reminder'), 80),
 'note' => trim(strip_tags((string)($product['subscription_note'] ?? ''))),
 ];
 }
}

if (!function_exists('subscription_record_id')) {
 function subscription_record_id(string $orderId, string $productSlug): string
 {
 return 'sub_' . substr(hash('sha256', $orderId . '|' . $productSlug), 0, 24);
 }
}

if (!function_exists('subscription_issue_for_order')) {
 function subscription_issue_for_order(array $order, array $memberRecord = []): array
 {
 $settings = subscription_read_settings();
 if (empty($settings['enabled']) || empty($settings['auto_create_after_paid'])) {
 return ['ok' => false, 'message' => 'Subscription Center sedang nonaktif.'];
 }
 $product = function_exists('member_access_product_for_order') ? member_access_product_for_order($order) : null;
 $config = subscription_product_config($product, $settings);
 if (empty($config['enabled'])) {
 return ['ok' => false, 'message' => 'Produk ini bukan subscription/membership.'];
 }
 $orderId = subscription_clean((string)($order['id'] ?? $memberRecord['order_id'] ?? ''), 120);
 $productSlug = subscription_clean((string)($memberRecord['product_slug'] ?? $order['product_slug'] ?? $product['slug'] ?? ''), 160);
 if ($orderId === '') {
 return ['ok' => false, 'message' => 'Order tidak valid.'];
 }
 $id = subscription_record_id($orderId, $productSlug);
 $records = subscription_read_records();
 $existing = is_array($records[$id] ?? null) ? $records[$id] : [];
 $duration = (int)$config['duration_days'];
 $started = (string)($existing['started_at'] ?? date('c'));
 $expires = $duration > 0 ? date('c', strtotime('+' . $duration . ' days', strtotime($started) ?: time())) : '';
 $record = array_merge($existing, [
 'id' => $id,
 'order_id' => $orderId,
 'order_ref' => (string)($memberRecord['order_ref'] ?? (function_exists('order_public_reference') ? order_public_reference($order) : ($order['ref'] ?? ''))),
 'customer_name' => subscription_clean((string)($order['name'] ?? $memberRecord['customer_name'] ?? ''), 160),
 'customer_email' => strtolower(subscription_clean((string)($order['email'] ?? $memberRecord['customer_email'] ?? ''), 160)),
 'customer_phone' => subscription_clean((string)($order['phone'] ?? $memberRecord['customer_phone'] ?? ''), 50),
 'product_slug' => $productSlug,
 'product_title' => (string)($memberRecord['product_title'] ?? $order['product_title'] ?? $product['title'] ?? 'Membership'),
 'cycle' => (string)$config['cycle'],
 'duration_days' => $duration,
 'grace_days' => (int)$config['grace_days'],
 'renewal_mode' => (string)$config['renewal_mode'],
 'status' => 'active',
 'started_at' => $started,
 'expires_at' => $expires,
 'grace_until' => ($expires !== '' && (int)$config['grace_days'] > 0) ? date('c', strtotime('+' . (int)$config['grace_days'] . ' days', strtotime($expires) ?: time())) : '',
 'member_access_token' => (string)($memberRecord['access_token'] ?? ''),
 'note' => (string)$config['note'],
 'created_at' => (string)($existing['created_at'] ?? date('c')),
 'updated_at' => date('c'),
 ]);
 $records[$id] = $record;
 subscription_write_records($records);
 subscription_log_event(['type' => 'subscription_issue', 'subscription_id' => $id, 'order_id' => $orderId]);
 return ['ok' => true, 'message' => 'Subscription/membership aktif.', 'record' => $record];
 }
}

if (!function_exists('subscription_status')) {
 function subscription_status(array $record): string
 {
 if ((string)($record['status'] ?? 'active') !== 'active') {
 return (string)$record['status'];
 }
 if (empty($record['expires_at'])) {
 return 'lifetime';
 }
 $expires = strtotime((string)$record['expires_at']) ?: 0;
 $grace = strtotime((string)($record['grace_until'] ?? '')) ?: 0;
 if ($expires >= time()) {
 return 'active';
 }
 if ($grace >= time()) {
 return 'grace';
 }
 return 'expired';
 }
}

if (!function_exists('subscription_days_left')) {
 function subscription_days_left(array $record): ?int
 {
 if (empty($record['expires_at'])) {
 return null;
 }
 $expires = strtotime((string)$record['expires_at']);
 if (!$expires) {
 return null;
 }
 return (int)floor(($expires - time()) / 86400);
 }
}

if (!function_exists('subscription_records_by_email')) {
 function subscription_records_by_email(string $email): array
 {
 $email = strtolower(trim($email));
 if ($email === '') {
 return [];
 }
 return array_values(array_filter(subscription_read_records(), static fn($row): bool => is_array($row) && strtolower((string)($row['customer_email'] ?? '')) === $email));
 }
}

if (!function_exists('subscription_renewal_url')) {
 function subscription_renewal_url(array $record): string
 {
 $slug = (string)($record['product_slug'] ?? '');
 return url('checkout' . ($slug !== '' ? '?produk=' . rawurlencode($slug) . '&renewal=' . rawurlencode((string)($record['id'] ?? '')) : ''));
 }
}

if (!function_exists('subscription_reminder_candidates')) {
 function subscription_reminder_candidates(): array
 {
 $settings = subscription_read_settings();
 $reminders = array_map('intval', (array)($settings['reminder_days'] ?? [7,3,1,0,-1]));
 $rows = [];
 foreach (subscription_read_records() as $record) {
 if (!is_array($record) || empty($record['expires_at'])) {
 continue;
 }
 $days = subscription_days_left($record);
 if ($days === null) {
 continue;
 }
 if (in_array($days, $reminders, true) || $days < 0) {
 $record['days_left'] = $days;
 $record['computed_status'] = subscription_status($record);
 $record['renewal_url'] = subscription_renewal_url($record);
 $rows[] = $record;
 }
 }
 usort($rows, static fn($a, $b): int => ($a['days_left'] ?? 999) <=> ($b['days_left'] ?? 999));
 return $rows;
 }
}

if (!function_exists('subscription_summary')) {
 function subscription_summary(): array
 {
 $records = subscription_read_records();
 $active = $expired = $grace = $lifetime = 0;
 foreach ($records as $record) {
 if (!is_array($record)) {
 continue;
 }
 $status = subscription_status($record);
 if ($status === 'active') { $active++; }
 elseif ($status === 'grace') { $grace++; }
 elseif ($status === 'expired') { $expired++; }
 elseif ($status === 'lifetime') { $lifetime++; }
 }
 return ['total' => count($records), 'active' => $active, 'grace' => $grace, 'expired' => $expired, 'lifetime' => $lifetime, 'reminders' => subscription_reminder_candidates()];
 }
}
