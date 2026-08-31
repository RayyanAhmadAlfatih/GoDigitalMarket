<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RENEWAL, UPGRADE & CUSTOMER LIFETIME VALUE - U-Growth
|--------------------------------------------------------------------------
| Customer lifecycle layer for membership renewals, license/support renewal,
| upgrade opportunities, and customer value segmentation. File-based and
| shared-hosting friendly.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('renewal_clv_clean')) {
 function renewal_clv_clean(string $value, int $max = 180): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 return $value === '' ? '' : (function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max));
 }
}

if (!function_exists('renewal_clv_settings_file')) {
 function renewal_clv_settings_file(): string
 {
 return STORAGE_PATH . '/renewal-clv-settings.json';
 }
}

if (!function_exists('renewal_clv_default_settings')) {
 function renewal_clv_default_settings(): array
 {
 return [
 'enabled' => true,
 'renewal_window_days' => 14,
 'winback_after_days' => 7,
 'clv_high_threshold' => 1000000,
 'clv_medium_threshold' => 250000,
 'upgrade_min_paid_orders' => 2,
 'upgrade_min_revenue' => 300000,
 'renewal_template' => "Halo {name}, akses {product} akan berakhir pada {expires_at}.\n\nBoleh kami bantu perpanjang agar akses tetap aktif?\nLink perpanjangan: {renewal_url}\nOrder: {order_ref}",
 'upgrade_template' => "Halo {name}, terima kasih sudah menjadi customer {site_name}.\n\nKarena Kakak sudah pernah membeli {product}, kami punya rekomendasi upgrade/produk lanjutan yang mungkin cocok.\nSilakan balas pesan ini jika ingin dibantu pilih paket terbaik.",
 'winback_template' => "Halo {name}, akses {product} sudah berakhir.\n\nJika masih ingin melanjutkan, kami bisa bantu aktifkan kembali akses/layanan Kakak.\nLink perpanjangan: {renewal_url}",
 ];
 }
}

if (!function_exists('renewal_clv_read_settings')) {
 function renewal_clv_read_settings(): array
 {
 $defaults = renewal_clv_default_settings();
 $file = renewal_clv_settings_file();
 if (!is_file($file)) {
 return $defaults;
 }
 $data = json_decode((string)@file_get_contents($file), true);
 if (!is_array($data)) {
 return $defaults;
 }
 $settings = array_merge($defaults, $data);
 $settings['renewal_window_days'] = max(1, min(120, (int)($settings['renewal_window_days'] ?? 14)));
 $settings['winback_after_days'] = max(1, min(365, (int)($settings['winback_after_days'] ?? 7)));
 $settings['clv_high_threshold'] = max(0, (int)($settings['clv_high_threshold'] ?? 1000000));
 $settings['clv_medium_threshold'] = max(0, (int)($settings['clv_medium_threshold'] ?? 250000));
 $settings['upgrade_min_paid_orders'] = max(1, min(50, (int)($settings['upgrade_min_paid_orders'] ?? 2)));
 $settings['upgrade_min_revenue'] = max(0, (int)($settings['upgrade_min_revenue'] ?? 300000));
 return $settings;
 }
}

if (!function_exists('renewal_clv_write_settings')) {
 function renewal_clv_write_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $defaults = renewal_clv_default_settings();
 $payload = [
 'enabled' => !empty($settings['enabled']),
 'renewal_window_days' => max(1, min(120, (int)($settings['renewal_window_days'] ?? $defaults['renewal_window_days']))),
 'winback_after_days' => max(1, min(365, (int)($settings['winback_after_days'] ?? $defaults['winback_after_days']))),
 'clv_high_threshold' => max(0, (int)preg_replace('/[^0-9]/', '', (string)($settings['clv_high_threshold'] ?? $defaults['clv_high_threshold']))),
 'clv_medium_threshold' => max(0, (int)preg_replace('/[^0-9]/', '', (string)($settings['clv_medium_threshold'] ?? $defaults['clv_medium_threshold']))),
 'upgrade_min_paid_orders' => max(1, min(50, (int)($settings['upgrade_min_paid_orders'] ?? $defaults['upgrade_min_paid_orders']))),
 'upgrade_min_revenue' => max(0, (int)preg_replace('/[^0-9]/', '', (string)($settings['upgrade_min_revenue'] ?? $defaults['upgrade_min_revenue']))),
 'renewal_template' => trim(strip_tags((string)($settings['renewal_template'] ?? $defaults['renewal_template']))),
 'upgrade_template' => trim(strip_tags((string)($settings['upgrade_template'] ?? $defaults['upgrade_template']))),
 'winback_template' => trim(strip_tags((string)($settings['winback_template'] ?? $defaults['winback_template']))),
 ];
 return @file_put_contents(renewal_clv_settings_file(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('renewal_clv_digits')) {
 function renewal_clv_digits(string $value): string
 {
 return preg_replace('/\D+/', '', $value) ?: '';
 }
}

if (!function_exists('renewal_clv_customer_key')) {
 function renewal_clv_customer_key(array $order): string
 {
 $email = strtolower(trim((string)($order['email'] ?? '')));
 if ($email !== '') {
 return 'email:' . $email;
 }
 $phone = renewal_clv_digits((string)($order['phone'] ?? ''));
 if ($phone !== '') {
 return 'phone:' . $phone;
 }
 return 'order:' . renewal_clv_clean((string)($order['id'] ?? uniqid('guest_', true)), 120);
 }
}

if (!function_exists('renewal_clv_paid_statuses')) {
 function renewal_clv_paid_statuses(): array
 {
 return ['DP Masuk', 'Lunas', 'Tidak Perlu Payment'];
 }
}

if (!function_exists('renewal_clv_is_paid_order')) {
 function renewal_clv_is_paid_order(array $order): bool
 {
 return in_array((string)($order['payment_status'] ?? ''), renewal_clv_paid_statuses(), true);
 }
}

if (!function_exists('renewal_clv_order_value')) {
 function renewal_clv_order_value(array $order): int
 {
 if (function_exists('order_invoice_total')) {
 return max(0, (int)order_invoice_total($order));
 }
 $subtotal = (int)($order['subtotal'] ?? 0);
 if ($subtotal <= 0) {
 $subtotal = max(0, (int)($order['price'] ?? 0)) * max(1, (int)($order['quantity'] ?? 1));
 }
 return $subtotal + max(0, (int)($order['shipping_total'] ?? 0));
 }
}

if (!function_exists('renewal_clv_segment')) {
 function renewal_clv_segment(int $revenue, int $paidOrders, array $settings = []): string
 {
 $settings = $settings ?: renewal_clv_read_settings();
 if ($revenue >= (int)$settings['clv_high_threshold'] || $paidOrders >= 5) {
 return 'VIP / High Value';
 }
 if ($revenue >= (int)$settings['clv_medium_threshold'] || $paidOrders >= 2) {
 return 'Repeat / Warm Customer';
 }
 if ($paidOrders >= 1) {
 return 'First Buyer';
 }
 return 'Prospect / Belum Bayar';
 }
}

if (!function_exists('renewal_clv_profiles')) {
 function renewal_clv_profiles(int $days = 365): array
 {
 $settings = renewal_clv_read_settings();
 $orders = function_exists('order_read_all') ? order_read_all($days, [], 50000) : [];
 $profiles = [];
 foreach ($orders as $order) {
 if (!is_array($order)) {
 continue;
 }
 $key = renewal_clv_customer_key($order);
 if (!isset($profiles[$key])) {
 $profiles[$key] = [
 'key' => $key,
 'name' => renewal_clv_clean((string)($order['name'] ?? 'Customer'), 120) ?: 'Customer',
 'email' => strtolower(renewal_clv_clean((string)($order['email'] ?? ''), 160)),
 'phone' => renewal_clv_clean((string)($order['phone'] ?? ''), 50),
 'orders' => 0,
 'paid_orders' => 0,
 'revenue' => 0,
 'pipeline' => 0,
 'first_order_at' => (string)($order['time'] ?? ''),
 'last_order_at' => (string)($order['time'] ?? ''),
 'products' => [],
 'last_product' => renewal_clv_clean((string)($order['product_title'] ?? 'Produk'), 160),
 'last_order_ref' => function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? ''),
 'last_order_url' => function_exists('order_status_url') ? order_status_url($order) : '',
 ];
 }
 $profiles[$key]['orders']++;
 $value = renewal_clv_order_value($order);
 if (renewal_clv_is_paid_order($order)) {
 $profiles[$key]['paid_orders']++;
 $profiles[$key]['revenue'] += $value;
 } else {
 $profiles[$key]['pipeline'] += $value;
 }
 $product = renewal_clv_clean((string)($order['product_title'] ?? 'Produk'), 160);
 if ($product !== '') {
 $profiles[$key]['products'][$product] = ($profiles[$key]['products'][$product] ?? 0) + 1;
 $profiles[$key]['last_product'] = $product;
 }
 $profiles[$key]['last_order_at'] = (string)($order['time'] ?? $profiles[$key]['last_order_at']);
 $profiles[$key]['last_order_ref'] = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '');
 $profiles[$key]['last_order_url'] = function_exists('order_status_url') ? order_status_url($order) : '';
 }

 $subscriptionRows = function_exists('subscription_read_records') ? subscription_read_records() : [];
 foreach ($subscriptionRows as $row) {
 if (!is_array($row)) {
 continue;
 }
 $key = 'email:' . strtolower(trim((string)($row['customer_email'] ?? '')));
 if ($key === 'email:') {
 $phone = renewal_clv_digits((string)($row['customer_phone'] ?? ''));
 $key = $phone !== '' ? 'phone:' . $phone : 'sub:' . (string)($row['id'] ?? uniqid('', true));
 }
 if (!isset($profiles[$key])) {
 $profiles[$key] = [
 'key' => $key,
 'name' => renewal_clv_clean((string)($row['customer_name'] ?? 'Customer'), 120) ?: 'Customer',
 'email' => strtolower(renewal_clv_clean((string)($row['customer_email'] ?? ''), 160)),
 'phone' => renewal_clv_clean((string)($row['customer_phone'] ?? ''), 50),
 'orders' => 0,
 'paid_orders' => 0,
 'revenue' => 0,
 'pipeline' => 0,
 'first_order_at' => (string)($row['started_at'] ?? ''),
 'last_order_at' => (string)($row['updated_at'] ?? $row['started_at'] ?? ''),
 'products' => [],
 'last_product' => renewal_clv_clean((string)($row['product_title'] ?? 'Membership'), 160),
 'last_order_ref' => (string)($row['order_ref'] ?? ''),
 'last_order_url' => '',
 ];
 }
 $status = function_exists('subscription_status') ? subscription_status($row) : (string)($row['status'] ?? 'active');
 $profiles[$key]['subscriptions'][] = array_merge($row, ['computed_status' => $status, 'renewal_url' => function_exists('subscription_renewal_url') ? subscription_renewal_url($row) : '']);
 if ($status === 'active' || $status === 'lifetime') {
 $profiles[$key]['active_subscriptions'] = (int)($profiles[$key]['active_subscriptions'] ?? 0) + 1;
 } elseif ($status === 'grace') {
 $profiles[$key]['grace_subscriptions'] = (int)($profiles[$key]['grace_subscriptions'] ?? 0) + 1;
 } elseif ($status === 'expired') {
 $profiles[$key]['expired_subscriptions'] = (int)($profiles[$key]['expired_subscriptions'] ?? 0) + 1;
 }
 }

 $licenseRows = function_exists('license_manager_read_records') ? license_manager_read_records() : [];
 foreach ($licenseRows as $license) {
 if (!is_array($license)) {
 continue;
 }
 $key = 'email:' . strtolower(trim((string)($license['customer_email'] ?? '')));
 if ($key === 'email:') {
 $key = 'license:' . (string)($license['license_key'] ?? uniqid('', true));
 }
 if (!isset($profiles[$key])) {
 $profiles[$key] = [
 'key' => $key,
 'name' => renewal_clv_clean((string)($license['customer_name'] ?? 'Customer'), 120) ?: 'Customer',
 'email' => strtolower(renewal_clv_clean((string)($license['customer_email'] ?? ''), 160)),
 'phone' => renewal_clv_clean((string)($license['customer_phone'] ?? ''), 50),
 'orders' => 0,
 'paid_orders' => 0,
 'revenue' => 0,
 'pipeline' => 0,
 'first_order_at' => (string)($license['created_at'] ?? ''),
 'last_order_at' => (string)($license['updated_at'] ?? $license['created_at'] ?? ''),
 'products' => [],
 'last_product' => renewal_clv_clean((string)($license['product_title'] ?? 'Lisensi'), 160),
 'last_order_ref' => (string)($license['order_ref'] ?? ''),
 'last_order_url' => '',
 ];
 }
 $profiles[$key]['licenses'][] = $license;
 $profiles[$key]['license_count'] = (int)($profiles[$key]['license_count'] ?? 0) + 1;
 }

 foreach ($profiles as &$profile) {
 $profile['products'] = array_slice($profile['products'], 0, 8, true);
 $profile['segment'] = renewal_clv_segment((int)$profile['revenue'], (int)$profile['paid_orders'], $settings);
 $profile['aov'] = (int)$profile['paid_orders'] > 0 ? (int)floor((int)$profile['revenue'] / (int)$profile['paid_orders']) : 0;
 $profile['active_subscriptions'] = (int)($profile['active_subscriptions'] ?? 0);
 $profile['grace_subscriptions'] = (int)($profile['grace_subscriptions'] ?? 0);
 $profile['expired_subscriptions'] = (int)($profile['expired_subscriptions'] ?? 0);
 $profile['license_count'] = (int)($profile['license_count'] ?? 0);
 }
 unset($profile);

 usort($profiles, static fn(array $a, array $b): int => ((int)$b['revenue'] <=> (int)$a['revenue']) ?: ((int)$b['paid_orders'] <=> (int)$a['paid_orders']));
 return $profiles;
 }
}

if (!function_exists('renewal_clv_format_template')) {
 function renewal_clv_format_template(string $template, array $context): string
 {
 $replacements = [];
 foreach ($context as $key => $value) {
 if (is_scalar($value)) {
 $replacements['{' . $key . '}'] = (string)$value;
 }
 }
 $replacements['{site_name}'] = defined('SITE_NAME') ? SITE_NAME : 'Website';
 return strtr($template, $replacements);
 }
}

if (!function_exists('renewal_clv_opportunities')) {
 function renewal_clv_opportunities(int $days = 365): array
 {
 $settings = renewal_clv_read_settings();
 $profiles = renewal_clv_profiles($days);
 $opportunities = [];
 $renewalWindow = (int)$settings['renewal_window_days'];
 $winbackAfter = (int)$settings['winback_after_days'];

 foreach ($profiles as $profile) {
 foreach ((array)($profile['subscriptions'] ?? []) as $subscription) {
 if (!is_array($subscription)) {
 continue;
 }
 $status = (string)($subscription['computed_status'] ?? 'active');
 $daysLeft = function_exists('subscription_days_left') ? subscription_days_left($subscription) : null;
 $type = 'renewal';
 $priority = 60;
 $title = 'Follow-up renewal membership';
 $template = (string)$settings['renewal_template'];
 if ($status === 'expired') {
 $type = 'winback';
 $priority = 82;
 $title = 'Winback akses expired';
 $template = (string)$settings['winback_template'];
 } elseif ($status === 'grace') {
 $type = 'renewal_grace';
 $priority = 90;
 $title = 'Renewal masa grace';
 } elseif ($daysLeft !== null && $daysLeft <= $renewalWindow) {
 $priority = $daysLeft <= 3 ? 88 : 72;
 $title = 'Reminder renewal H' . (string)$daysLeft;
 } else {
 continue;
 }
 if ($status === 'expired' && $daysLeft !== null && abs($daysLeft) < $winbackAfter) {
 $priority = 76;
 }
 $context = [
 'name' => (string)($profile['name'] ?? 'Kak'),
 'product' => (string)($subscription['product_title'] ?? $profile['last_product'] ?? 'produk'),
 'expires_at' => !empty($subscription['expires_at']) ? date('d M Y', strtotime((string)$subscription['expires_at'])) : 'Lifetime',
 'renewal_url' => (string)($subscription['renewal_url'] ?? ''),
 'order_ref' => (string)($subscription['order_ref'] ?? $profile['last_order_ref'] ?? ''),
 ];
 $opportunities[] = [
 'type' => $type,
 'priority' => $priority,
 'title' => $title,
 'customer' => $profile,
 'subscription' => $subscription,
 'message' => renewal_clv_format_template($template, $context),
 'wa_url' => function_exists('wa_link') ? wa_link(renewal_clv_format_template($template, $context)) : '',
 'amount_hint' => (int)($profile['aov'] ?? 0),
 'days_left' => $daysLeft,
 ];
 }

 if ((int)($profile['paid_orders'] ?? 0) >= (int)$settings['upgrade_min_paid_orders'] || (int)($profile['revenue'] ?? 0) >= (int)$settings['upgrade_min_revenue']) {
 $context = [
 'name' => (string)($profile['name'] ?? 'Kak'),
 'product' => (string)($profile['last_product'] ?? 'produk sebelumnya'),
 'order_ref' => (string)($profile['last_order_ref'] ?? ''),
 ];
 $message = renewal_clv_format_template((string)$settings['upgrade_template'], $context);
 $opportunities[] = [
 'type' => 'upgrade',
 'priority' => (string)($profile['segment'] ?? '') === 'VIP / High Value' ? 78 : 64,
 'title' => 'Rekomendasi upgrade / cross-sell',
 'customer' => $profile,
 'message' => $message,
 'wa_url' => function_exists('wa_link') ? wa_link($message) : '',
 'amount_hint' => (int)($profile['aov'] ?? 0),
 'days_left' => null,
 ];
 }
 }

 usort($opportunities, static fn(array $a, array $b): int => ((int)$b['priority'] <=> (int)$a['priority']) ?: ((int)($b['amount_hint'] ?? 0) <=> (int)($a['amount_hint'] ?? 0)));
 return $opportunities;
 }
}

if (!function_exists('renewal_clv_summary')) {
 function renewal_clv_summary(int $days = 365): array
 {
 $profiles = renewal_clv_profiles($days);
 $opportunities = renewal_clv_opportunities($days);
 $revenue = 0;
 $pipeline = 0;
 $paidOrders = 0;
 $vip = $repeat = $firstBuyer = $prospect = 0;
 foreach ($profiles as $profile) {
 $revenue += (int)($profile['revenue'] ?? 0);
 $pipeline += (int)($profile['pipeline'] ?? 0);
 $paidOrders += (int)($profile['paid_orders'] ?? 0);
 $segment = (string)($profile['segment'] ?? '');
 if ($segment === 'VIP / High Value') { $vip++; }
 elseif ($segment === 'Repeat / Warm Customer') { $repeat++; }
 elseif ($segment === 'First Buyer') { $firstBuyer++; }
 else { $prospect++; }
 }
 $renewalCount = count(array_filter($opportunities, static fn(array $row): bool => in_array((string)($row['type'] ?? ''), ['renewal', 'renewal_grace', 'winback'], true)));
 $upgradeCount = count(array_filter($opportunities, static fn(array $row): bool => (string)($row['type'] ?? '') === 'upgrade'));
 return [
 'profiles' => $profiles,
 'opportunities' => $opportunities,
 'customer_count' => count($profiles),
 'revenue' => $revenue,
 'pipeline' => $pipeline,
 'paid_orders' => $paidOrders,
 'avg_clv' => count($profiles) > 0 ? (int)floor($revenue / count($profiles)) : 0,
 'renewal_count' => $renewalCount,
 'upgrade_count' => $upgradeCount,
 'segments' => [
 'VIP / High Value' => $vip,
 'Repeat / Warm Customer' => $repeat,
 'First Buyer' => $firstBuyer,
 'Prospect / Belum Bayar' => $prospect,
 ],
 ];
 }
}
