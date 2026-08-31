<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Unified Sales Report & Commerce Insight
|--------------------------------------------------------------------------
| Lightweight commerce insight layer for shared-hosting UMKM websites.
| It reads existing order, payment, recovery, subscription, member, and
| license records without changing the storage model.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('commerce_insight_allowed_ranges')) {
 function commerce_insight_allowed_ranges(): array
 {
 return ['7', '30', '90', '365', 'all'];
 }
}

if (!function_exists('commerce_insight_range_from_request')) {
 function commerce_insight_range_from_request(array $input = []): array
 {
 $range = (string)($input['range'] ?? '30');
 if (!in_array($range, commerce_insight_allowed_ranges(), true)) {
 $range = '30';
 }

 $days = $range === 'all' ? 0 : max(1, (int)$range);

 return [
 'range' => $range,
 'days' => $days,
 'label' => $range === 'all' ? 'Semua data' : $range . ' hari terakhir',
 ];
 }
}

if (!function_exists('commerce_insight_order_total')) {
 function commerce_insight_order_total(array $order): int
 {
 if (function_exists('order_invoice_total')) {
 return max(0, (int)order_invoice_total($order));
 }

 $subtotal = (int)($order['subtotal'] ?? 0);
 if ($subtotal <= 0) {
 $subtotal = ((int)($order['price'] ?? 0)) * max(1, (int)($order['quantity'] ?? 1));
 }

 return max(0, $subtotal + (int)($order['shipping_total'] ?? 0));
 }
}

if (!function_exists('commerce_insight_payment_is_paid')) {
 function commerce_insight_payment_is_paid(array $order): bool
 {
 $status = strtolower(trim((string)($order['payment_status'] ?? '')));
 $orderStatus = strtolower(trim((string)($order['status'] ?? '')));
 $gatewayStatus = strtolower(trim((string)($order['gateway_status'] ?? '')));

 return in_array($status, ['lunas', 'dp masuk', 'paid', 'settlement', 'capture', 'sukses'], true)
 || in_array($orderStatus, ['selesai', 'deal', 'lunas'], true)
 || in_array($gatewayStatus, ['paid', 'settlement', 'capture', 'succeeded', 'success'], true);
 }
}

if (!function_exists('commerce_insight_order_is_closed')) {
 function commerce_insight_order_is_closed(array $order): bool
 {
 $status = strtolower(trim((string)($order['status'] ?? '')));
 return in_array($status, ['selesai', 'batal', 'cancelled', 'cancel', 'spam'], true);
 }
}

if (!function_exists('commerce_insight_payment_channel')) {
 function commerce_insight_payment_channel(array $order): string
 {
 $method = strtolower(trim((string)($order['payment_method'] ?? '')));
 $provider = strtolower(trim((string)($order['gateway_provider'] ?? '')));
 $channel = strtolower(trim((string)($order['invoice_payment_channel'] ?? '')));

 $combined = $method . ' ' . $provider . ' ' . $channel;
 if (str_contains($combined, 'midtrans')) {
 return 'Midtrans';
 }
 if (str_contains($combined, 'xendit')) {
 return 'Xendit';
 }
 if (str_contains($combined, 'flip')) {
 return 'Flip';
 }
 if (str_contains($combined, 'gateway') || str_contains($combined, 'payment link') || str_contains($combined, 'otomatis')) {
 return 'Payment Gateway';
 }
 if (str_contains($combined, 'cod')) {
 return 'COD';
 }
 if (str_contains($combined, 'qris')) {
 return 'QRIS Manual';
 }
 if (str_contains($combined, 'transfer') || str_contains($combined, 'manual')) {
 return 'Transfer Manual';
 }

 return $method !== '' ? ucwords(str_replace(['_', '-'], ' ', $method)) : 'Belum dipilih';
 }
}

if (!function_exists('commerce_insight_bucket_add')) {
 function commerce_insight_bucket_add(array &$bucket, string $key, int $orders = 1, int $value = 0, int $quantity = 0): void
 {
 $key = trim($key) !== '' ? trim($key) : 'Tidak diketahui';
 if (!isset($bucket[$key])) {
 $bucket[$key] = ['orders' => 0, 'value' => 0, 'quantity' => 0];
 }
 $bucket[$key]['orders'] += max(0, $orders);
 $bucket[$key]['value'] += max(0, $value);
 $bucket[$key]['quantity'] += max(0, $quantity);
 }
}

if (!function_exists('commerce_insight_sort_bucket')) {
 function commerce_insight_sort_bucket(array $bucket, string $by = 'value', int $limit = 10): array
 {
 uasort($bucket, static function (array $a, array $b) use ($by): int {
 return ((int)($b[$by] ?? 0)) <=> ((int)($a[$by] ?? 0));
 });

 return array_slice($bucket, 0, max(1, $limit), true);
 }
}

if (!function_exists('commerce_insight_daily_seed')) {
 function commerce_insight_daily_seed(int $days): array
 {
 $days = max(1, min(120, $days));
 $rows = [];
 for ($i = $days - 1; $i >= 0; $i--) {
 $date = date('Y-m-d', strtotime('-' . $i . ' days'));
 $rows[$date] = [
 'date' => $date,
 'orders' => 0,
 'paid_orders' => 0,
 'revenue' => 0,
 'pipeline' => 0,
 'shipping' => 0,
 ];
 }
 return $rows;
 }
}

if (!function_exists('commerce_insight_action_plan')) {
 function commerce_insight_action_plan(array $summary): array
 {
 $actions = [];
 $orders = (int)($summary['totals']['orders'] ?? 0);
 $paidOrders = (int)($summary['totals']['paid_orders'] ?? 0);
 $unpaid = (int)($summary['totals']['unpaid_orders'] ?? 0);
 $hotRecovery = (int)($summary['recovery']['hot'] ?? 0);
 $recoveryValue = (int)($summary['recovery']['value'] ?? 0);
 $gatewayErrors = (int)($summary['payment']['gateway_errors'] ?? 0);
 $expiredSubs = (int)($summary['subscription']['expired'] ?? 0);
 $dueSubs = (int)($summary['subscription']['due'] ?? 0);
 $licensesNeed = (int)($summary['license']['needs_attention'] ?? 0);
 $fulfillmentPending = (int)($summary['fulfillment']['pending'] ?? 0);

 if ($hotRecovery > 0 || $recoveryValue > 0) {
 $actions[] = [
 'priority' => 'Sangat tinggi',
 'title' => 'Kejar checkout panas hari ini',
 'note' => 'Ada calon pembeli dengan intent tinggi yang belum closing. Prioritaskan follow-up WhatsApp sebelum mereka dingin.',
 'metric' => $hotRecovery . ' hot lead · potensi ' . (function_exists('rupiah') ? rupiah($recoveryValue) : (string)$recoveryValue),
 'url' => function_exists('url') ? url('admin/checkout-recovery') : 'admin/checkout-recovery',
 ];
 }

 if ($unpaid > 0) {
 $actions[] = [
 'priority' => 'Tinggi',
 'title' => 'Reminder order belum bayar',
 'note' => 'Kirim reminder pembayaran dan bantu customer yang bingung memilih metode bayar.',
 'metric' => $unpaid . ' order belum lunas',
 'url' => function_exists('url') ? url('admin/payment-reminders') : 'admin/payment-reminders',
 ];
 }

 if ($gatewayErrors > 0) {
 $actions[] = [
 'priority' => 'Tinggi',
 'title' => 'Cek payment gateway error',
 'note' => 'Ada order gateway yang gagal membuat link/token. Cek API key, mode sandbox/production, dan callback URL.',
 'metric' => $gatewayErrors . ' error gateway',
 'url' => function_exists('url') ? url('admin/payment-gateway') : 'admin/payment-gateway',
 ];
 }

 if ($fulfillmentPending > 0) {
 $actions[] = [
 'priority' => 'Normal',
 'title' => 'Rapikan pengiriman yang belum selesai',
 'note' => 'Order fisik yang sudah siap bayar perlu dipacking/dikirim agar customer experience tetap bagus.',
 'metric' => $fulfillmentPending . ' fulfillment pending',
 'url' => function_exists('url') ? url('admin/orders') : 'admin/orders',
 ];
 }

 if ($dueSubs > 0 || $expiredSubs > 0) {
 $actions[] = [
 'priority' => 'Normal',
 'title' => 'Follow-up renewal membership',
 'note' => 'Subscription yang hampir habis atau expired bisa menjadi revenue repeat order.',
 'metric' => $dueSubs . ' jatuh tempo · ' . $expiredSubs . ' expired',
 'url' => function_exists('url') ? url('admin/subscriptions') : 'admin/subscriptions',
 ];
 }

 if ($licensesNeed > 0) {
 $actions[] = [
 'priority' => 'Normal',
 'title' => 'Review lisensi domain',
 'note' => 'Ada lisensi yang butuh perhatian, misalnya revoked/suspended/expired atau aktivasi domain bermasalah.',
 'metric' => $licensesNeed . ' lisensi perlu dicek',
 'url' => function_exists('url') ? url('admin/license-manager') : 'admin/license-manager',
 ];
 }

 if ($orders > 0 && $paidOrders <= 0) {
 $actions[] = [
 'priority' => 'Tinggi',
 'title' => 'Naikkan closing dari order ke transfer',
 'note' => 'Order sudah ada, tapi belum ada payment valid. Coba aktifkan reminder dan perjelas instruksi pembayaran.',
 'metric' => '0 order lunas dari ' . $orders . ' order',
 'url' => function_exists('url') ? url('admin/form-checkout') : 'admin/form-checkout',
 ];
 }

 if (!$actions) {
 $actions[] = [
 'priority' => 'Aman',
 'title' => 'Operasional commerce stabil',
 'note' => 'Belum ada masalah besar di periode ini. Lanjut optimasi produk terlaris, konten SEO, dan campaign repeat order.',
 'metric' => 'Tidak ada action kritis',
 'url' => function_exists('url') ? url('admin/produk') : 'admin/produk',
 ];
 }

 return array_slice($actions, 0, 8);
 }
}

if (!function_exists('commerce_insight_summary')) {
 function commerce_insight_summary(int $days = 30, array $filters = []): array
 {
 $days = max(0, min(3650, $days));
 $orders = function_exists('order_read_all')
 ? order_read_all($days, $days > 0 ? [] : ['_all_time' => true], 50000)
 : [];

 $ordersCount = 0;
 $paidOrders = 0;
 $unpaidOrders = 0;
 $closedOrders = 0;
 $gross = 0;
 $paidRevenue = 0;
 $pipeline = 0;
 $shippingRevenue = 0;
 $shippingDiscount = 0;
 $digitalOrders = 0;
 $licenseOrders = 0;
 $subscriptionOrders = 0;
 $gatewayOrders = 0;
 $manualOrders = 0;
 $gatewayErrors = 0;
 $fulfillmentPending = 0;
 $fulfillmentDone = 0;

 $productBucket = [];
 $paymentBucket = [];
 $shippingBucket = [];
 $originBucket = [];
 $statusBucket = [];
 $paymentStatusBucket = [];
 $daily = $days > 0 ? commerce_insight_daily_seed(min(120, max(7, $days))) : [];

 foreach ($orders as $order) {
 if (!is_array($order)) {
 continue;
 }

 $ordersCount++;
 $total = commerce_insight_order_total($order);
 $gross += $total;
 $qty = max(1, (int)($order['quantity'] ?? 1));
 $paid = commerce_insight_payment_is_paid($order);
 $closed = commerce_insight_order_is_closed($order);

 if ($paid) {
 $paidOrders++;
 $paidRevenue += $total;
 } elseif (!$closed) {
 $unpaidOrders++;
 $pipeline += $total;
 }

 if ($closed) {
 $closedOrders++;
 }

 $shippingRevenue += max(0, (int)($order['shipping_total'] ?? 0));
 $shippingDiscount += max(0, (int)($order['shipping_discount'] ?? 0));

 $channel = commerce_insight_payment_channel($order);
 if (in_array($channel, ['Midtrans', 'Xendit', 'Flip', 'Payment Gateway'], true)) {
 $gatewayOrders++;
 } else {
 $manualOrders++;
 }

 if (trim((string)($order['gateway_error'] ?? '')) !== '') {
 $gatewayErrors++;
 }

 $productType = strtolower((string)($order['product_type'] ?? $order['type'] ?? ''));
 $deliveryType = strtolower((string)($order['digital_delivery_type'] ?? $order['member_access_type'] ?? ''));
 $accessMode = strtolower((string)($order['access_mode'] ?? ''));
 if (str_contains($productType, 'digital') || str_contains($deliveryType, 'digital') || str_contains($accessMode, 'digital') || str_contains((string)($order['commerce_shipping_policy'] ?? ''), 'no_shipping')) {
 $digitalOrders++;
 }
 if (trim((string)($order['license_key'] ?? '')) !== '' || str_contains($accessMode, 'license')) {
 $licenseOrders++;
 }
 if (str_contains($accessMode, 'subscription') || str_contains(strtolower((string)($order['subscription_cycle'] ?? '')), 'month') || str_contains(strtolower((string)($order['subscription_cycle'] ?? '')), 'year')) {
 $subscriptionOrders++;
 }

 $fulfillmentStatus = strtolower(trim((string)($order['fulfillment_status'] ?? '')));
 if (in_array($fulfillmentStatus, ['dikirim', 'terkirim', 'tidak_perlu_pengiriman', 'tidak perlu pengiriman'], true)) {
 $fulfillmentDone++;
 } elseif (!in_array((string)($order['commerce_shipping_policy'] ?? ''), ['no_shipping', 'digital'], true) && !$closed) {
 $fulfillmentPending++;
 }

 commerce_insight_bucket_add($productBucket, (string)($order['product_title'] ?? 'Produk'), 1, $total, $qty);
 commerce_insight_bucket_add($paymentBucket, $channel, 1, $total, $qty);
 commerce_insight_bucket_add($shippingBucket, (string)($order['shipping_provider'] ?? $order['shipping_courier'] ?? $order['shipping_method'] ?? 'Belum dipilih'), 1, (int)($order['shipping_total'] ?? 0), $qty);
 commerce_insight_bucket_add($originBucket, (string)($order['shipping_origin'] ?? $order['shipping_origin_city'] ?? 'Origin global/default'), 1, $total, $qty);
 commerce_insight_bucket_add($statusBucket, (string)($order['status'] ?? 'Baru'), 1, $total, $qty);
 commerce_insight_bucket_add($paymentStatusBucket, (string)($order['payment_status'] ?? 'Belum bayar'), 1, $total, $qty);

 $dateKey = date('Y-m-d', (int)($order['_ts'] ?? time()));
 if (isset($daily[$dateKey])) {
 $daily[$dateKey]['orders']++;
 if ($paid) {
 $daily[$dateKey]['paid_orders']++;
 $daily[$dateKey]['revenue'] += $total;
 } elseif (!$closed) {
 $daily[$dateKey]['pipeline'] += $total;
 }
 $daily[$dateKey]['shipping'] += max(0, (int)($order['shipping_total'] ?? 0));
 }
 }

 $proofSummary = function_exists('payment_proof_summary') ? payment_proof_summary($days ?: 3650, []) : [];
 $recoveryCandidates = function_exists('checkout_recovery_candidates') ? checkout_recovery_candidates($days ?: 3650, [], 10000) : [];
 $recoverySummary = function_exists('checkout_recovery_summary') ? checkout_recovery_summary($recoveryCandidates) : [];
 $memberSummary = function_exists('member_access_summary') ? member_access_summary() : [];
 $buyerSummary = function_exists('buyer_account_summary') ? buyer_account_summary() : [];
 $licenseSummary = function_exists('license_manager_summary') ? license_manager_summary() : [];
 $subscriptionSummary = function_exists('subscription_summary') ? subscription_summary() : [];

 $licenseNeedsAttention = (int)($licenseSummary['expired'] ?? 0) + (int)($licenseSummary['revoked'] ?? 0) + (int)($licenseSummary['suspended'] ?? 0);
 $subscriptionDue = is_array($subscriptionSummary['reminders'] ?? null) ? count((array)$subscriptionSummary['reminders']) : 0;

 $summary = [
 'range' => [
 'days' => $days,
 'label' => $days > 0 ? $days . ' hari terakhir' : 'Semua data',
 ],
 'totals' => [
 'orders' => $ordersCount,
 'paid_orders' => $paidOrders,
 'unpaid_orders' => $unpaidOrders,
 'closed_orders' => $closedOrders,
 'gross_order_value' => $gross,
 'paid_revenue' => $paidRevenue,
 'unpaid_pipeline' => $pipeline,
 'average_order_value' => $ordersCount > 0 ? (int)round($gross / $ordersCount) : 0,
 'paid_rate' => $ordersCount > 0 ? round(($paidOrders / $ordersCount) * 100, 1) : 0,
 ],
 'shipping' => [
 'revenue' => $shippingRevenue,
 'discount' => $shippingDiscount,
 'by_provider' => commerce_insight_sort_bucket($shippingBucket, 'orders', 8),
 'by_origin' => commerce_insight_sort_bucket($originBucket, 'orders', 8),
 ],
 'payment' => [
 'gateway_orders' => $gatewayOrders,
 'manual_orders' => $manualOrders,
 'gateway_errors' => $gatewayErrors,
 'proofs' => (int)($proofSummary['total'] ?? $proofSummary['proofs'] ?? 0),
 'pending_proofs' => (int)($proofSummary['pending'] ?? $proofSummary['pending_proofs'] ?? 0),
 'by_channel' => commerce_insight_sort_bucket($paymentBucket, 'orders', 8),
 ],
 'digital' => [
 'digital_orders' => $digitalOrders,
 'license_orders' => $licenseOrders,
 'subscription_orders' => $subscriptionOrders,
 'member_access_total' => (int)($memberSummary['total'] ?? 0),
 'member_access_active' => (int)($memberSummary['active'] ?? 0),
 'buyer_accounts' => (int)($buyerSummary['total'] ?? 0),
 ],
 'license' => [
 'total' => (int)($licenseSummary['total'] ?? 0),
 'active' => (int)($licenseSummary['active'] ?? 0),
 'needs_attention' => $licenseNeedsAttention,
 'domain_locked' => (int)($licenseSummary['domain_locked'] ?? 0),
 ],
 'subscription' => [
 'total' => (int)($subscriptionSummary['total'] ?? 0),
 'active' => (int)($subscriptionSummary['active'] ?? 0),
 'grace' => (int)($subscriptionSummary['grace'] ?? 0),
 'expired' => (int)($subscriptionSummary['expired'] ?? 0),
 'due' => $subscriptionDue,
 ],
 'recovery' => [
 'count' => (int)($recoverySummary['total'] ?? count($recoveryCandidates)),
 'hot' => (int)($recoverySummary['hot'] ?? $recoverySummary['high_priority'] ?? 0),
 'value' => (int)($recoverySummary['potential_revenue'] ?? $recoverySummary['value'] ?? 0),
 ],
 'fulfillment' => [
 'pending' => $fulfillmentPending,
 'done' => $fulfillmentDone,
 ],
 'leaderboards' => [
 'products' => commerce_insight_sort_bucket($productBucket, 'value', 10),
 'statuses' => commerce_insight_sort_bucket($statusBucket, 'orders', 10),
 'payment_statuses' => commerce_insight_sort_bucket($paymentStatusBucket, 'orders', 10),
 ],
 'daily' => array_values($daily),
 'recent_orders' => array_slice($orders, 0, 10),
 ];

 $summary['actions'] = commerce_insight_action_plan($summary);

 return $summary;
 }
}

if (!function_exists('commerce_insight_export_summary_csv')) {
 function commerce_insight_export_summary_csv(array $summary): void
 {
 header('Content-Type: text/csv; charset=UTF-8');
 header('Content-Disposition: attachment; filename="commerce-insight-summary-' . date('Ymd-His') . '.csv"');
 $out = fopen('php://output', 'wb');
 fputcsv($out, ['metric', 'value']);
 foreach ((array)($summary['totals'] ?? []) as $key => $value) {
 fputcsv($out, [$key, $value]);
 }
 foreach ((array)($summary['payment'] ?? []) as $key => $value) {
 if (!is_array($value)) {
 fputcsv($out, ['payment_' . $key, $value]);
 }
 }
 foreach ((array)($summary['shipping'] ?? []) as $key => $value) {
 if (!is_array($value)) {
 fputcsv($out, ['shipping_' . $key, $value]);
 }
 }
 fclose($out);
 exit;
 }
}

if (!function_exists('commerce_insight_export_actions_csv')) {
 function commerce_insight_export_actions_csv(array $summary): void
 {
 header('Content-Type: text/csv; charset=UTF-8');
 header('Content-Disposition: attachment; filename="commerce-insight-actions-' . date('Ymd-His') . '.csv"');
 $out = fopen('php://output', 'wb');
 fputcsv($out, ['priority', 'title', 'metric', 'note', 'url']);
 foreach ((array)($summary['actions'] ?? []) as $action) {
 if (!is_array($action)) {
 continue;
 }
 fputcsv($out, [
 (string)($action['priority'] ?? ''),
 (string)($action['title'] ?? ''),
 (string)($action['metric'] ?? ''),
 (string)($action['note'] ?? ''),
 (string)($action['url'] ?? ''),
 ]);
 }
 fclose($out);
 exit;
 }
}
