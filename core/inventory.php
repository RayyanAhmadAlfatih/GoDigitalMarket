<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| INVENTORY & PRODUCT AVAILABILITY CONTROL - 
|--------------------------------------------------------------------------
| Lightweight stock helper for shared hosting. The engine keeps stock rules
| product-level, calculates reserved/committed quantity from order logs, and
| gives admins a practical action list without requiring a database migration.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('inventory_clean')) {
 function inventory_clean(mixed $value, int $max = 160): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('inventory_multiline_clean')) {
 function inventory_multiline_clean(mixed $value, int $max = 900): string
 {
 $value = trim(strip_tags((string)$value));
 $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
 $value = preg_replace('/[ \t]+/', ' ', (string)$value);
 $value = preg_replace('/\n{3,}/', "\n\n", (string)$value);
 return function_exists('mb_substr') ? mb_substr((string)$value, 0, $max) : substr((string)$value, 0, $max);
 }
}

if (!function_exists('inventory_int')) {
 function inventory_int(mixed $value, int $default = 0): int
 {
 if (is_int($value)) {
 return max(0, $value);
 }
 $clean = preg_replace('/[^0-9]/', '', (string)$value) ?: '';
 if ($clean === '') {
 return max(0, $default);
 }
 return max(0, (int)$clean);
 }
}

if (!function_exists('inventory_product_seo_extras')) {
 function inventory_product_seo_extras(array $product): array
 {
 $seo = $product['seo'] ?? [];
 if (is_string($seo)) {
 $decoded = json_decode($seo, true);
 $seo = is_array($decoded) ? $decoded : [];
 }
 return is_array($seo) ? $seo : [];
 }
}

if (!function_exists('inventory_product_value')) {
 function inventory_product_value(array $product, string $key, mixed $default = null): mixed
 {
 if (array_key_exists($key, $product)) {
 return $product[$key];
 }
 $seo = inventory_product_seo_extras($product);
 return array_key_exists($key, $seo) ? $seo[$key] : $default;
 }
}

if (!function_exists('inventory_product_is_stock_item')) {
 function inventory_product_is_stock_item(array $product): bool
 {
 if (function_exists('product_is_digital') && product_is_digital($product)) {
 return !empty(inventory_product_value($product, 'stock_tracking_enabled', false));
 }
 if (function_exists('product_is_service_like') && product_is_service_like($product)) {
 return !empty(inventory_product_value($product, 'stock_tracking_enabled', false));
 }
 return true;
 }
}

if (!function_exists('inventory_tracking_enabled')) {
 function inventory_tracking_enabled(array $product): bool
 {
 $enabled = inventory_product_value($product, 'stock_tracking_enabled', null);
 if ($enabled !== null && $enabled !== '') {
 return !empty($enabled) && inventory_product_is_stock_item($product);
 }

 $status = (string)($product['stock_status'] ?? 'in_stock');
 $stock = inventory_product_stock_total($product);
 return inventory_product_is_stock_item($product) && ($stock > 0 || in_array($status, ['in_stock', 'preorder', 'out_of_stock'], true));
 }
}

if (!function_exists('inventory_product_stock_total')) {
 function inventory_product_stock_total(array $product): int
 {
 return inventory_int(inventory_product_value($product, 'stock', 0), 0);
 }
}

if (!function_exists('inventory_product_manual_reserved')) {
 function inventory_product_manual_reserved(array $product): int
 {
 return inventory_int(inventory_product_value($product, 'stock_reserved_manual', 0), 0);
 }
}

if (!function_exists('inventory_product_low_threshold')) {
 function inventory_product_low_threshold(array $product): int
 {
 return max(0, inventory_int(inventory_product_value($product, 'stock_low_threshold', 3), 3));
 }
}

if (!function_exists('inventory_product_allow_backorder')) {
 function inventory_product_allow_backorder(array $product): bool
 {
 return !empty(inventory_product_value($product, 'stock_allow_backorder', false));
 }
}

if (!function_exists('inventory_product_auto_status')) {
 function inventory_product_auto_status(array $product): bool
 {
 return !empty(inventory_product_value($product, 'stock_auto_status', true));
 }
}

if (!function_exists('inventory_product_note')) {
 function inventory_product_note(array $product): string
 {
 return inventory_multiline_clean(inventory_product_value($product, 'stock_note', ''), 500);
 }
}

if (!function_exists('inventory_order_product_matches')) {
 function inventory_order_product_matches(array $order, array $product): bool
 {
 $slug = trim((string)($product['slug'] ?? ''));
 $sku = trim((string)($product['sku'] ?? ''));
 $title = trim((string)($product['title'] ?? ''));
 $orderSlug = trim((string)($order['product_slug'] ?? ''));
 $orderSku = trim((string)($order['sku'] ?? $order['product_sku'] ?? ''));
 $orderTitle = trim((string)($order['product_title'] ?? ''));

 if ($slug !== '' && $orderSlug !== '' && $slug === $orderSlug) {
 return true;
 }
 if ($sku !== '' && $orderSku !== '' && strcasecmp($sku, $orderSku) === 0) {
 return true;
 }
 return $title !== '' && $orderTitle !== '' && strcasecmp($title, $orderTitle) === 0;
 }
}

if (!function_exists('inventory_order_quantity')) {
 function inventory_order_quantity(array $order): int
 {
 return max(1, min(9999, (int)($order['quantity'] ?? 1)));
 }
}

if (!function_exists('inventory_order_is_cancelled')) {
 function inventory_order_is_cancelled(array $order): bool
 {
 $status = (string)($order['status'] ?? '');
 $payment = (string)($order['payment_status'] ?? '');
 return in_array($status, ['Batal', 'Spam'], true) || in_array($payment, ['Refund'], true);
 }
}

if (!function_exists('inventory_order_is_paid_like')) {
 function inventory_order_is_paid_like(array $order): bool
 {
 return in_array((string)($order['payment_status'] ?? ''), ['DP Masuk', 'Lunas'], true);
 }
}

if (!function_exists('inventory_order_is_reserved_like')) {
 function inventory_order_is_reserved_like(array $order): bool
 {
 if (inventory_order_is_cancelled($order)) {
 return false;
 }
 if (inventory_order_is_paid_like($order)) {
 return false;
 }
 $status = (string)($order['status'] ?? 'Baru');
 return in_array($status, ['Baru', 'Diproses', 'Menunggu Pembayaran', 'Deal'], true)
 || (string)($order['payment_status'] ?? '') === 'Menunggu Pembayaran';
 }
}

if (!function_exists('inventory_order_is_committed_like')) {
 function inventory_order_is_committed_like(array $order): bool
 {
 if (inventory_order_is_cancelled($order)) {
 return false;
 }
 if (inventory_order_is_paid_like($order)) {
 return true;
 }
 return in_array((string)($order['status'] ?? ''), ['Deal', 'Dikirim', 'Selesai'], true);
 }
}

if (!function_exists('inventory_product_flow')) {
 function inventory_product_flow(array $product, array $orders): array
 {
 $reserved = inventory_product_manual_reserved($product);
 $committed = 0;
 $openOrders = 0;
 $paidOrders = 0;
 foreach ($orders as $order) {
 if (!inventory_order_product_matches($order, $product)) {
 continue;
 }
 $qty = inventory_order_quantity($order);
 if (inventory_order_is_reserved_like($order)) {
 $reserved += $qty;
 $openOrders++;
 }
 if (inventory_order_is_committed_like($order)) {
 $committed += $qty;
 $paidOrders++;
 }
 }
 return [
 'reserved' => $reserved,
 'committed' => $committed,
 'open_orders' => $openOrders,
 'paid_orders' => $paidOrders,
 ];
 }
}

if (!function_exists('inventory_product_summary')) {
 function inventory_product_summary(array $product, ?array $orders = null): array
 {
 $orders = $orders ?? (function_exists('order_read_all') ? order_read_all(0, ['_all_time' => true], 50000) : []);
 $tracking = inventory_tracking_enabled($product);
 $stock = inventory_product_stock_total($product);
 $threshold = inventory_product_low_threshold($product);
 $flow = $tracking ? inventory_product_flow($product, $orders) : ['reserved' => 0, 'committed' => 0, 'open_orders' => 0, 'paid_orders' => 0];
 $available = $tracking ? max(0, $stock - (int)$flow['reserved'] - (int)$flow['committed']) : 0;
 $allowBackorder = inventory_product_allow_backorder($product);
 $status = (string)($product['stock_status'] ?? 'in_stock');

 if (!$tracking) {
 $key = 'untracked';
 $label = 'Tidak dilacak';
 } elseif ($status === 'preorder' || $allowBackorder) {
 $key = 'preorder';
 $label = $available > 0 ? 'Tersedia + Pre-order' : 'Pre-order';
 } elseif ($stock <= 0 || $available <= 0 || $status === 'out_of_stock') {
 $key = 'out';
 $label = 'Habis / Perlu Restock';
 } elseif ($available <= $threshold) {
 $key = 'low';
 $label = 'Stok Menipis';
 } elseif ($status === 'contact_admin') {
 $key = 'contact';
 $label = 'Konfirmasi Admin';
 } else {
 $key = 'ok';
 $label = 'Aman';
 }

 return [
 'tracking' => $tracking,
 'stock_total' => $stock,
 'reserved' => (int)$flow['reserved'],
 'committed' => (int)$flow['committed'],
 'available' => $available,
 'threshold' => $threshold,
 'allow_backorder' => $allowBackorder,
 'auto_status' => inventory_product_auto_status($product),
 'status_key' => $key,
 'status_label' => $label,
 'open_orders' => (int)$flow['open_orders'],
 'paid_orders' => (int)$flow['paid_orders'],
 'note' => inventory_product_note($product),
 ];
 }
}

if (!function_exists('inventory_product_action')) {
 function inventory_product_action(array $product, array $summary): string
 {
 $title = (string)($product['title'] ?? 'Produk');
 return match ((string)$summary['status_key']) {
 'out' => 'Restock atau ubah status ' . $title . ' ke Pre-order/Hubungi Admin agar checkout tidak over-selling.',
 'low' => 'Stok ' . $title . ' mulai menipis. Siapkan restock, naikkan threshold, atau aktifkan pre-order.',
 'preorder' => 'Pastikan estimasi pre-order dan follow-up buyer untuk ' . $title . ' sudah jelas.',
 'contact' => 'Pastikan admin cepat merespons calon pembeli yang bertanya stok ' . $title . '.',
 'untracked' => 'Aktifkan tracking stok untuk ' . $title . ' jika item ini punya kuota/stok terbatas.',
 default => 'Stok ' . $title . ' aman untuk dijual.',
 };
 }
}

if (!function_exists('inventory_dashboard')) {
 function inventory_dashboard(): array
 {
 $products = function_exists('all_products') ? all_products() : (function_exists('product_managed_products') ? product_managed_products() : []);
 $orders = function_exists('order_read_all') ? order_read_all(0, ['_all_time' => true], 50000) : [];
 $rows = [];
 $stats = [
 'total' => 0,
 'tracked' => 0,
 'ok' => 0,
 'low' => 0,
 'out' => 0,
 'preorder' => 0,
 'untracked' => 0,
 'reserved' => 0,
 'committed' => 0,
 'available' => 0,
 ];

 foreach ($products as $product) {
 if (($product['status'] ?? 'published') === 'archived') {
 continue;
 }
 $summary = inventory_product_summary($product, $orders);
 $rows[] = ['product' => $product, 'summary' => $summary, 'action' => inventory_product_action($product, $summary)];
 $stats['total']++;
 $stats[$summary['status_key']] = ($stats[$summary['status_key']] ?? 0) + 1;
 if (!empty($summary['tracking'])) {
 $stats['tracked']++;
 }
 $stats['reserved'] += (int)$summary['reserved'];
 $stats['committed'] += (int)$summary['committed'];
 $stats['available'] += (int)$summary['available'];
 }

 usort($rows, static function (array $a, array $b): int {
 $rank = ['out' => 1, 'low' => 2, 'preorder' => 3, 'contact' => 4, 'ok' => 5, 'untracked' => 6];
 $ak = (string)($a['summary']['status_key'] ?? 'ok');
 $bk = (string)($b['summary']['status_key'] ?? 'ok');
 $rankCompare = ($rank[$ak] ?? 99) <=> ($rank[$bk] ?? 99);
 if ($rankCompare !== 0) {
 return $rankCompare;
 }
 return ((int)($a['summary']['available'] ?? 0)) <=> ((int)($b['summary']['available'] ?? 0));
 });

 $actions = [];
 foreach ($rows as $row) {
 if (in_array((string)($row['summary']['status_key'] ?? ''), ['out', 'low', 'preorder', 'contact'], true)) {
 $actions[] = $row['action'];
 }
 if (count($actions) >= 8) {
 break;
 }
 }

 return ['stats' => $stats, 'rows' => $rows, 'actions' => $actions, 'orders' => $orders];
 }
}

if (!function_exists('inventory_validate_order_payload')) {
 function inventory_validate_order_payload(array $payload): array
 {
 $errors = [];
 if (empty($payload['product_slug']) || !function_exists('get_product_by_slug')) {
 return $errors;
 }
 $product = get_product_by_slug((string)$payload['product_slug']);
 if (!$product || !inventory_tracking_enabled($product)) {
 return $errors;
 }
 $quantity = max(1, min(999, (int)($payload['quantity'] ?? 1)));
 $summary = inventory_product_summary($product);
 if (!empty($summary['allow_backorder']) || (string)($product['stock_status'] ?? '') === 'preorder') {
 return $errors;
 }
 if ($quantity > (int)$summary['available']) {
 $errors[] = 'Stok/kuota produk ini tersisa ' . (int)$summary['available'] . '. Silakan kurangi jumlah atau hubungi admin.';
 }
 return $errors;
 }
}

if (!function_exists('inventory_order_snapshot')) {
 function inventory_order_snapshot(array $order): array
 {
 if (empty($order['product_slug']) || !function_exists('get_product_by_slug')) {
 return $order;
 }
 $product = get_product_by_slug((string)$order['product_slug']);
 if (!$product) {
 return $order;
 }
 $summary = inventory_product_summary($product);
 $order['inventory_tracking'] = !empty($summary['tracking']) ? 'yes' : 'no';
 $order['inventory_stock_total'] = (string)(int)$summary['stock_total'];
 $order['inventory_available_at_order'] = (string)(int)$summary['available'];
 $order['inventory_reserved_at_order'] = (string)(int)$summary['reserved'];
 $order['inventory_status_at_order'] = (string)$summary['status_label'];
 return $order;
 }
}

if (!function_exists('inventory_status_badge_class')) {
 function inventory_status_badge_class(string $key): string
 {
 return match ($key) {
 'out' => 'admin-badge admin-badge--danger',
 'low' => 'admin-badge admin-badge--warning',
 'preorder' => 'admin-badge admin-badge--info',
 'untracked' => 'admin-badge admin-badge--muted',
 default => 'admin-badge',
 };
 }
}
