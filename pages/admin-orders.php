<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$action = (string)($_GET['action'] ?? 'inbox');
$message = '';
$error = '';

if ($action === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/orders');
}

function admin_orders_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_orders_filter(string $key, int $max = 80): string
{
    return order_clean((string)($_GET[$key] ?? ''), $max);
}

function admin_orders_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));
    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_orders_days(): int
{
    $range = admin_orders_range();
    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }
    return max(1, min(3650, (int)$range));
}

function admin_orders_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_orders_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));
    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_orders_filters(): array
{
    $range = admin_orders_range();
    $filters = array_filter([
        'status' => admin_orders_filter('status'),
        'source' => admin_orders_filter('source'),
        'category' => admin_orders_filter('category'),
        'location' => admin_orders_filter('location'),
        'need' => admin_orders_filter('need'),
        'product_title' => admin_orders_filter('product_title'),
        'payment_method' => admin_orders_filter('payment_method'),
        'payment_status' => admin_orders_filter('payment_status'),
        'fulfillment_status' => admin_orders_filter('fulfillment_status'),
        'search' => admin_orders_filter('search', 120),
    ], static fn($v): bool => $v !== '' && $v !== null && $v !== false);

    if ($range === 'all') {
        $filters['_all_time'] = true;
    }
    if ($range === 'year') {
        $year = admin_orders_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
        $filters['_year'] = $year;
    }
    if ($range === 'custom') {
        $from = admin_orders_date_input('date_from');
        $to = admin_orders_date_input('date_to');
        if ($from !== '') {
            $filters['_start_ts'] = strtotime($from . ' 00:00:00') ?: 0;
            $filters['_date_from'] = $from;
        }
        if ($to !== '') {
            $filters['_end_ts'] = strtotime($to . ' 23:59:59') ?: time();
            $filters['_date_to'] = $to;
        }
    }
    return $filters;
}

function admin_orders_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_orders_range(),
        'year' => admin_orders_selected_year(),
        'date_from' => admin_orders_date_input('date_from'),
        'date_to' => admin_orders_date_input('date_to'),
        'status' => admin_orders_filter('status'),
        'source' => admin_orders_filter('source'),
        'category' => admin_orders_filter('category'),
        'location' => admin_orders_filter('location'),
        'need' => admin_orders_filter('need'),
        'product_title' => admin_orders_filter('product_title'),
        'payment_method' => admin_orders_filter('payment_method'),
        'payment_status' => admin_orders_filter('payment_status'),
        'fulfillment_status' => admin_orders_filter('fulfillment_status'),
        'search' => admin_orders_filter('search', 120),
    ], $extra);

    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/orders' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_orders_range_label(): string
{
    $range = admin_orders_range();
    if ($range === 'all') {
        return 'Semua data sejak order aktif';
    }
    if ($range === 'year') {
        return 'Tahun ' . admin_orders_selected_year();
    }
    if ($range === 'custom') {
        $from = admin_orders_date_input('date_from');
        $to = admin_orders_date_input('date_to');
        if ($from !== '' && $to !== '') {
            return date('d M Y', strtotime($from)) . ' - ' . date('d M Y', strtotime($to));
        }
        if ($from !== '') {
            return 'Mulai ' . date('d M Y', strtotime($from));
        }
        if ($to !== '') {
            return 'Sampai ' . date('d M Y', strtotime($to));
        }
        return 'Custom tanggal belum dipilih';
    }
    return admin_orders_days() . ' hari terakhir';
}

function admin_orders_export_csv(array $orders): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="order-drafts-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['ref', 'time', 'status', 'payment_method', 'payment_status', 'commerce_shipping_policy', 'commerce_payment_policy', 'commerce_allowed_gateways', 'commerce_preorder_eta', 'invoice_number', 'invoice_total', 'invoice_due_date', 'invoice_payment_channel', 'invoice_payment_profile', 'name', 'phone', 'email', 'product_title', 'quantity', 'price', 'need', 'location', 'shipping_method', 'shipping_origin', 'shipping_origin_id', 'shipping_origin_code', 'shipping_rule_name', 'shipping_eta', 'shipping_quote_source', 'shipping_provider', 'shipping_courier', 'shipping_service', 'shipping_cache_status', 'fulfillment_status', 'shipping_carrier_actual', 'shipping_service_actual', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at', 'fulfillment_note', 'internal_note', 'shipping_cost', 'shipping_handling_fee', 'shipping_discount', 'shipping_total', 'subtotal', 'address_line', 'province', 'city', 'district', 'postal_code', 'planned_date', 'source', 'category', 'intent', 'product_url', 'page_path', 'message', 'payment_note', 'invoice_payment_instruction', 'invoice_public_note']);
    foreach ($orders as $item) {
        fputcsv($out, [
            function_exists('order_public_reference') ? order_public_reference($item) : (string)($item['id'] ?? ''),
            (string)($item['time'] ?? ''),
            (string)($item['status'] ?? ''),
            (string)($item['payment_method'] ?? ''),
            (string)($item['payment_status'] ?? ''),
            (string)($item['commerce_shipping_policy_label'] ?? $item['commerce_shipping_policy'] ?? ''),
            (string)($item['commerce_payment_policy_label'] ?? $item['commerce_payment_policy'] ?? ''),
            (string)($item['commerce_allowed_gateways'] ?? ''),
            (string)($item['commerce_preorder_eta'] ?? ''),
            order_invoice_number($item),
            (string)order_invoice_total($item),
            (string)($item['invoice_due_date'] ?? ''),
            order_invoice_payment_channel($item),
            function_exists('payment_order_profile_id') ? payment_order_profile_id($item) : (string)($item['invoice_payment_profile'] ?? ''),
            (string)($item['name'] ?? ''),
            (string)($item['phone'] ?? ''),
            (string)($item['email'] ?? ''),
            (string)($item['product_title'] ?? ''),
            (string)($item['quantity'] ?? ''),
            (string)($item['price'] ?? ''),
            (string)($item['need'] ?? ''),
            (string)($item['location'] ?? ''),
            (string)($item['shipping_method'] ?? ''),
            (string)($item['shipping_origin'] ?? ''),
            (string)($item['shipping_origin_id'] ?? ''),
            (string)($item['shipping_origin_code'] ?? ''),
            (string)($item['shipping_rule_name'] ?? ''),
            (string)($item['shipping_eta'] ?? ''),
            (string)($item['shipping_quote_source'] ?? ''),
            (string)($item['shipping_provider'] ?? ''),
            (string)($item['shipping_courier'] ?? ''),
            (string)($item['shipping_service'] ?? ''),
            (string)($item['shipping_cache_status'] ?? ''),
            (string)($item['fulfillment_status'] ?? ''),
            (string)($item['shipping_carrier'] ?? ''),
            (string)($item['shipping_service_actual'] ?? ''),
            (string)($item['shipping_tracking_number'] ?? ''),
            (string)($item['shipping_tracking_url'] ?? ''),
            (string)($item['shipped_at'] ?? ''),
            (string)($item['delivered_at'] ?? ''),
            (string)($item['fulfillment_note'] ?? ''),
            (string)($item['internal_note'] ?? ''),
            (string)($item['shipping_cost'] ?? ''),
            (string)($item['shipping_handling_fee'] ?? ''),
            (string)($item['shipping_discount'] ?? ''),
            (string)($item['shipping_total'] ?? ''),
            (string)($item['subtotal'] ?? ''),
            (string)($item['address_line'] ?? ''),
            (string)($item['province'] ?? ''),
            (string)($item['city'] ?? ''),
            (string)($item['district'] ?? ''),
            (string)($item['postal_code'] ?? ''),
            (string)($item['planned_date'] ?? ''),
            (string)($item['source'] ?? ''),
            (string)($item['category'] ?? ''),
            (string)($item['intent'] ?? ''),
            (string)($item['product_url'] ?? ''),
            (string)($item['page_path'] ?? ''),
            (string)($item['message'] ?? ''),
            (string)($item['payment_note'] ?? ''),
            order_invoice_payment_instruction($item),
            order_invoice_public_note($item),
        ]);
    }
    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_orders_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'orders']);
            }
            redirect_302('admin/orders');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'update_status') {
        $orderIdForUpdate = (string)($_POST['id'] ?? '');
        $currentOrderForUpdate = function_exists('order_find_by_id') ? order_find_by_id($orderIdForUpdate) : null;
        $txValidation = function_exists('transaction_validate_order_update')
            ? transaction_validate_order_update(
                $currentOrderForUpdate,
                (string)($_POST['status'] ?? ''),
                (string)($_POST['payment_status'] ?? ''),
                (string)($_POST['payment_note'] ?? '')
            )
            : ['ok' => true, 'errors' => [], 'warnings' => []];
        if (empty($txValidation['ok'])) {
            $error = implode(' ', (array)($txValidation['errors'] ?? ['Update tidak lolos validasi transaksi.']));
        } else {
            $ok = order_update_status(
                $orderIdForUpdate,
                (string)($_POST['status'] ?? ''),
                (string)($_POST['note'] ?? ''),
                (string)($_POST['payment_status'] ?? ''),
                (string)($_POST['payment_note'] ?? ''),
                [
                    'invoice_number' => (string)($_POST['invoice_number'] ?? ''),
                    'invoice_total' => (string)($_POST['invoice_total'] ?? ''),
                    'invoice_due_date' => (string)($_POST['invoice_due_date'] ?? ''),
                    'invoice_payment_channel' => (string)($_POST['invoice_payment_channel'] ?? ''),
                    'invoice_payment_profile' => (string)($_POST['invoice_payment_profile'] ?? ''),
                    'invoice_payment_instruction' => (string)($_POST['invoice_payment_instruction'] ?? ''),
                    'invoice_public_note' => (string)($_POST['invoice_public_note'] ?? ''),
                    'fulfillment_status' => (string)($_POST['fulfillment_status'] ?? ''),
                    'shipping_carrier' => (string)($_POST['shipping_carrier'] ?? ''),
                    'shipping_service_actual' => (string)($_POST['shipping_service_actual'] ?? ''),
                    'shipping_tracking_number' => (string)($_POST['shipping_tracking_number'] ?? ''),
                    'shipping_tracking_url' => (string)($_POST['shipping_tracking_url'] ?? ''),
                    'shipped_at' => (string)($_POST['shipped_at'] ?? ''),
                    'delivered_at' => (string)($_POST['delivered_at'] ?? ''),
                    'fulfillment_note' => (string)($_POST['fulfillment_note'] ?? ''),
                    'internal_note' => (string)($_POST['internal_note'] ?? ''),
                ]
            );
            if ($ok) {
                if (function_exists('transaction_store_event')) {
                    transaction_store_event([
                        'category' => 'order',
                        'action' => 'order_status_updated',
                        'target_type' => 'order',
                        'target_id' => $orderIdForUpdate,
                        'target_ref' => $currentOrderForUpdate && function_exists('order_public_reference') ? order_public_reference($currentOrderForUpdate) : $orderIdForUpdate,
                        'before' => [
                            'status' => (string)($currentOrderForUpdate['status'] ?? ''),
                            'payment_status' => (string)($currentOrderForUpdate['payment_status'] ?? ''),
                            'invoice_total' => (string)($currentOrderForUpdate['invoice_total'] ?? ''),
                            'invoice_due_date' => (string)($currentOrderForUpdate['invoice_due_date'] ?? ''),
                        ],
                        'after' => [
                            'status' => (string)($_POST['status'] ?? ''),
                            'payment_status' => (string)($_POST['payment_status'] ?? ''),
                            'invoice_total' => (string)($_POST['invoice_total'] ?? ''),
                            'invoice_due_date' => (string)($_POST['invoice_due_date'] ?? ''),
                            'fulfillment_status' => (string)($_POST['fulfillment_status'] ?? ''),
                            'tracking_number' => (string)($_POST['shipping_tracking_number'] ?? ''),
                        ],
                        'note' => (string)($_POST['note'] ?? '') . "
" . (string)($_POST['payment_note'] ?? ''),
                        'warnings' => (array)($txValidation['warnings'] ?? []),
                    ]);
                }
                redirect_302('admin/orders?updated=1');
            }
            $error = 'Status order belum bisa diperbarui.';
        }
    } elseif (($_POST['form_action'] ?? '') === 'send_public_link_email') {
        $id = order_clean((string)($_POST['id'] ?? ''), 80);
        $kind = order_clean((string)($_POST['link_kind'] ?? 'status'), 20);
        $orderForEmail = $id !== '' && function_exists('order_find_by_id') ? order_find_by_id($id) : null;
        if (!$orderForEmail) {
            $error = 'Order tidak ditemukan untuk kirim email.';
        } elseif (!function_exists('notification_send_order_public_link')) {
            $error = 'Fungsi email link publik belum tersedia.';
        } elseif (notification_send_order_public_link($orderForEmail, $kind === 'invoice' ? 'invoice' : 'status')) {
            redirect_302('admin/orders?email_sent=1');
        } else {
            $error = 'Email belum terkirim. Cek konfigurasi email atau log notifikasi.';
        }
    } elseif (($_POST['form_action'] ?? '') === 'add_followup') {
        $ok = crm_store_followup([
            'target_type' => 'order',
            'target_id' => (string)($_POST['target_id'] ?? ''),
            'target_ref' => (string)($_POST['target_ref'] ?? ''),
            'target_name' => (string)($_POST['target_name'] ?? ''),
            'phone' => (string)($_POST['phone'] ?? ''),
            'email' => (string)($_POST['email'] ?? ''),
            'subject' => (string)($_POST['subject'] ?? ''),
            'priority' => (string)($_POST['priority'] ?? 'Normal'),
            'outcome' => (string)($_POST['outcome'] ?? 'Catatan'),
            'note' => (string)($_POST['followup_note'] ?? ''),
            'next_followup_date' => (string)($_POST['next_followup_date'] ?? ''),
            'next_followup_time' => (string)($_POST['next_followup_time'] ?? ''),
            'source' => 'admin-orders',
        ]);
        if ($ok) {
            redirect_302('admin/orders?followup_logged=1');
        }
        $error = 'Catatan follow-up belum bisa disimpan. Pastikan catatan atau hasil follow-up sudah diisi.';
    }
}

$loggedIn = admin_orders_logged_in();
$filters = admin_orders_filters();
$orders = $loggedIn ? order_read_all(admin_orders_days(), $filters, 10000) : [];
$summary = $loggedIn ? order_summary(admin_orders_days(), $filters) : [];
$fulfillmentSummary = $loggedIn && function_exists('order_fulfillment_summary') ? order_fulfillment_summary($orders) : [];

if ($loggedIn && $action === 'export') {
    admin_orders_export_csv($orders);
}

if (!empty($_GET['updated'])) {
    $message = 'Status order berhasil diperbarui.';
}
if (!empty($_GET['followup_logged'])) {
    $message = 'Catatan follow-up berhasil disimpan.';
}
if (!empty($_GET['email_sent'])) {
    $message = 'Link publik berhasil diproses untuk dikirim via email. Cek Riwayat Email untuk status detail.';
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Order Draft - Admin',
    'description' => 'Inbox admin untuk membaca order draft dan calon pesanan pelanggan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-orders-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Order Draft</div>
                <h1>Simple Order & Checkout Dashboard</h1>
                <p>Pantau calon pesanan, siapkan invoice manual, kirim instruksi pembayaran, dan kelola status order sebelum nanti masuk integrasi payment/QRIS otomatis.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--danger"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka order dashboard.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php
                    $activeRange = admin_orders_range();
                    $rangeOptions = [
                        '7' => '7 hari',
                        '14' => '14 hari',
                        '30' => '30 hari',
                        '60' => '60 hari',
                        '90' => '90 hari',
                        '180' => '180 hari',
                        '365' => '1 tahun',
                        'year' => 'Pilih tahun',
                        'all' => 'Semua waktu',
                        'custom' => 'Custom tanggal',
                    ];
                ?>
                <style>
                    .admin-orders-shell .admin-order-range-field{grid-column:1/-1!important;}
                    .admin-orders-shell .admin-order-range-links{display:flex!important;flex-wrap:wrap!important;gap:10px!important;align-items:center!important;margin-top:10px!important;}
                    .admin-orders-shell .admin-order-range-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;padding:10px 14px!important;border:1px solid #dbeafe!important;border-radius:999px!important;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;font-size:.84rem!important;font-weight:850!important;line-height:1!important;text-decoration:none!important;white-space:nowrap!important;}
                    .admin-orders-shell .admin-order-range-link:hover,.admin-orders-shell .admin-order-range-link.is-active{border-color:rgba(15,118,110,.48)!important;background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff)!important;color:var(--admin-primary-dark)!important;-webkit-text-fill-color:var(--admin-primary-dark)!important;}
                    .admin-orders-shell .admin-order-range-help{display:block!important;margin-top:8px!important;color:#64748b!important;font-size:.78rem!important;}
                    .admin-orders-shell .admin-order-range-note{grid-column:1/-1!important;padding:12px 14px!important;border:1px solid var(--border)!important;background:color-mix(in srgb,var(--bg) 82%,#ffffff)!important;border-radius:16px!important;color:var(--primary-dark)!important;font-size:.86rem!important;}
                    .admin-orders-shell .admin-order-payment-panel{margin:18px 0!important;display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:14px!important;}
                    .admin-orders-shell .admin-order-payment-panel .admin-card{margin:0!important;}
                    .admin-orders-shell .admin-order-pipeline{display:grid!important;gap:10px!important;}
                    .admin-orders-shell .admin-order-pipeline-row{display:grid!important;grid-template-columns:minmax(120px,1fr) minmax(120px,2fr) 48px!important;gap:10px!important;align-items:center!important;color:#0f172a!important;font-size:.86rem!important;}
                    .admin-orders-shell .admin-order-pipeline-bar{height:12px!important;border-radius:999px!important;background:#e2e8f0!important;overflow:hidden!important;}
                    .admin-orders-shell .admin-order-pipeline-bar span{display:block!important;height:100%!important;border-radius:999px!important;background:var(--admin-primary)!important;}
                    .admin-orders-shell .admin-order-badge{display:inline-flex!important;align-items:center!important;padding:5px 9px!important;border-radius:999px!important;background:color-mix(in srgb,var(--secondary-light) 50%,#ffffff)!important;border:1px solid var(--border)!important;color:var(--primary)!important;font-size:.76rem!important;font-weight:800!important;margin:3px 6px 3px 0!important;}
                    .admin-orders-shell .admin-public-link-box{margin-top:14px!important;padding:14px!important;border:1px solid #dbeafe!important;border-radius:18px!important;background:#f8fbff!important;display:grid!important;gap:10px!important;}.admin-orders-shell .admin-public-link-box h3{margin:0!important;font-size:1rem!important;color:#0f172a!important;}.admin-orders-shell .admin-public-link-actions{display:flex!important;flex-wrap:wrap!important;gap:8px!important;align-items:center!important;}.admin-orders-shell .admin-public-link-actions form{display:inline-flex!important;margin:0!important;}.admin-orders-shell .admin-public-link-url{display:grid!important;gap:6px!important;font-size:.78rem!important;color:#475569!important;}.admin-orders-shell .admin-public-link-url code{display:block!important;padding:8px 10px!important;border-radius:12px!important;background:#fff!important;border:1px solid #e2e8f0!important;word-break:break-all!important;color:#0f172a!important;}
                    .admin-orders-shell .admin-order-invoice-box{margin-top:14px!important;padding:14px!important;border:1px dashed var(--border)!important;border-radius:18px!important;background:#f8fffd!important;display:grid!important;gap:8px!important;}
                    .admin-orders-shell .admin-payment-proof-box{margin-top:14px!important;padding:14px!important;border:1px solid color-mix(in srgb,var(--admin-primary) 22%,#ffffff)!important;border-radius:18px!important;background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff)!important;display:grid!important;gap:10px!important;}.admin-orders-shell .admin-payment-proof-box h3{margin:0!important;font-size:1rem!important;color:var(--admin-primary-dark)!important;}.admin-orders-shell .admin-payment-proof-row{display:grid!important;grid-template-columns:1.4fr .9fr .9fr auto!important;gap:8px!important;align-items:center!important;padding:9px 10px!important;border-radius:14px!important;background:#fff!important;border:1px solid color-mix(in srgb,var(--admin-primary) 10%,#ffffff)!important;color:#334155!important;font-size:.84rem!important;}.admin-orders-shell .admin-payment-proof-row strong{color:#0f172a!important;}.admin-orders-shell .admin-payment-proof-empty{margin:0!important;color:var(--admin-primary-dark)!important;font-size:.86rem!important;}@media(max-width:900px){.admin-orders-shell .admin-payment-proof-row{grid-template-columns:1fr!important;}}
                    .admin-orders-shell .admin-order-invoice-box h3{margin:0!important;font-size:1rem!important;color:#0f172a!important;}
                    .admin-orders-shell .admin-order-invoice-meta{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important;}
                    .admin-orders-shell .admin-order-invoice-meta span{display:block!important;padding:10px!important;border-radius:14px!important;background:#fff!important;border:1px solid #e2e8f0!important;color:#475569!important;font-size:.78rem!important;}
                    .admin-orders-shell .admin-order-invoice-meta strong{display:block!important;color:#0f172a!important;font-size:.94rem!important;margin-top:3px!important;}
                    .admin-orders-shell .admin-order-invoice-fields{grid-column:1/-1!important;display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:10px!important;margin-top:8px!important;}
                    .admin-orders-shell .admin-order-invoice-fields textarea{grid-column:span 3!important;min-height:82px!important;}
                    .admin-orders-shell .admin-order-card__actions{display:flex!important;flex-wrap:wrap!important;gap:8px!important;margin-top:12px!important;}
                    .admin-orders-shell .admin-fulfillment-box{margin-top:14px!important;padding:14px!important;border:1px solid #bae6fd!important;border-radius:18px!important;background:#f0f9ff!important;display:grid!important;gap:10px!important;}.admin-orders-shell .admin-fulfillment-box h3{margin:0!important;color:#0f172a!important;font-size:1rem!important;}.admin-orders-shell .admin-fulfillment-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:8px!important;}.admin-orders-shell .admin-fulfillment-grid span{display:block!important;border:1px solid #dbeafe!important;border-radius:14px!important;background:#fff!important;padding:9px!important;color:#475569!important;font-size:.78rem!important;}.admin-orders-shell .admin-fulfillment-grid strong{display:block!important;color:#0f172a!important;font-size:.88rem!important;margin-top:3px!important;}.admin-orders-shell .admin-order-history{margin-top:12px!important;display:grid!important;gap:8px!important;}.admin-orders-shell .admin-order-history div{border-left:3px solid var(--admin-primary)!important;background:#fff!important;border-radius:12px!important;padding:9px 10px!important;color:#475569!important;font-size:.82rem!important;}.admin-orders-shell .admin-order-history strong{display:block!important;color:#0f172a!important;}.admin-orders-shell .admin-order-fulfillment-fields{grid-column:1/-1!important;display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:10px!important;margin-top:8px!important;}.admin-orders-shell .admin-order-fulfillment-fields textarea{grid-column:span 2!important;min-height:86px!important;}.admin-orders-shell .admin-digital-access-box{margin-top:14px!important;padding:14px!important;border:1px solid #bbf7d0!important;border-radius:18px!important;background:#f0fdf4!important;color:#14532d!important;}.admin-orders-shell .admin-digital-access-box h3{margin:0 0 6px!important;color:#14532d!important;font-size:1rem!important;}.admin-orders-shell .admin-digital-access-box code{display:block;margin-top:8px;padding:8px;border-radius:12px;background:#fff!important;border:1px solid #bbf7d0!important;word-break:break-all!important;color:#0f172a!important;}
                    .admin-orders-shell .admin-wa-composer{grid-column:1/-1!important;margin-top:14px!important;padding:16px!important;border:1px solid #bae6fd!important;border-radius:20px!important;background:linear-gradient(135deg,#f8fffd,#eff6ff)!important;display:grid!important;gap:12px!important;}
                    .admin-orders-shell .admin-wa-composer__head{display:flex!important;justify-content:space-between!important;gap:14px!important;align-items:flex-start!important;}
                    .admin-orders-shell .admin-wa-composer__head h3{margin:0 0 4px!important;color:#0f172a!important;font-size:1rem!important;}
                    .admin-orders-shell .admin-wa-composer__head p{margin:0!important;color:#475569!important;font-size:.86rem!important;line-height:1.55!important;}
                    .admin-orders-shell .admin-wa-composer__head span{display:inline-flex!important;padding:6px 10px!important;border-radius:999px!important;background:#dcfce7!important;color:var(--primary)!important;font-size:.76rem!important;font-weight:900!important;white-space:nowrap!important;}
                    .admin-orders-shell .admin-wa-composer label{display:grid!important;gap:7px!important;color:#0f2f25!important;font-weight:850!important;}
                    .admin-orders-shell .admin-wa-composer select,.admin-orders-shell .admin-wa-composer textarea{width:100%!important;border:1px solid #cbd5e1!important;border-radius:14px!important;padding:.82rem .95rem!important;background:#fff!important;color:#0f172a!important;font:inherit!important;}
                    .admin-orders-shell .admin-wa-composer textarea{min-height:190px!important;line-height:1.55!important;resize:vertical!important;}
                    .admin-orders-shell .admin-wa-composer__hint{display:block!important;color:#64748b!important;font-size:.8rem!important;}
                    .admin-orders-shell .admin-wa-composer__tools{display:flex!important;flex-wrap:wrap!important;gap:8px!important;align-items:center!important;}
                    .admin-orders-shell .admin-wa-composer__placeholders{padding:10px 12px!important;border-radius:14px!important;background:#fff!important;border:1px dashed #cbd5e1!important;color:#475569!important;font-size:.78rem!important;line-height:1.6!important;}
                    .admin-orders-shell .admin-wa-composer__placeholders code{padding:2px 5px!important;border-radius:6px!important;background:#f1f5f9!important;color:#0f172a!important;font-size:.76rem!important;}
                    .admin-orders-shell .admin-wa-composer--empty{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff)!important;border-color:color-mix(in srgb,var(--admin-primary) 22%,#ffffff)!important;color:var(--admin-primary-dark)!important;}
                    @media(max-width:900px){.admin-orders-shell .admin-order-payment-panel,.admin-orders-shell .admin-order-invoice-fields,.admin-orders-shell .admin-order-invoice-meta,.admin-orders-shell .admin-fulfillment-grid,.admin-orders-shell .admin-order-fulfillment-fields{grid-template-columns:1fr!important;}.admin-orders-shell .admin-order-invoice-fields textarea,.admin-orders-shell .admin-order-fulfillment-fields textarea{grid-column:1!important;}}
                    @media(max-width:680px){.admin-orders-shell .admin-order-range-links{overflow-x:auto!important;flex-wrap:nowrap!important;padding-bottom:6px!important;}.admin-orders-shell .admin-order-pipeline-row{grid-template-columns:1fr!important;}}
                </style>
                <form class="admin-card admin-order-filter" method="get">
                    <input type="hidden" name="range" value="<?= esc($activeRange); ?>">
                    <div class="admin-order-range-field">
                        <span class="admin-lead-range-title" id="orderRangeLegend">Rentang Data</span>
                        <div class="admin-order-range-links" role="group" aria-labelledby="orderRangeLegend">
                            <?php foreach ($rangeOptions as $rangeValue => $rangeLabel): ?>
                                <a class="admin-order-range-link <?= $activeRange === $rangeValue ? 'is-active' : ''; ?>" href="<?= esc(admin_orders_current_url([
                                    'range' => $rangeValue,
                                    'date_from' => $rangeValue === 'custom' ? admin_orders_date_input('date_from') : null,
                                    'date_to' => $rangeValue === 'custom' ? admin_orders_date_input('date_to') : null,
                                ])); ?>"><?= esc($rangeLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <small class="admin-order-range-help">Klik rentang data. Untuk Tahun atau Custom tanggal, atur field tambahan lalu klik Terapkan.</small>
                    </div>
                    <div class="admin-order-range-note">Rentang aktif: <strong><?= esc(admin_orders_range_label()); ?></strong></div>
                    <div class="admin-order-quick-filters" aria-label="Filter cepat order commerce">
                        <a class="admin-order-range-link <?= admin_orders_filter('payment_status') === 'Menunggu Pembayaran' ? 'is-active' : ''; ?>" href="<?= esc(admin_orders_current_url(['payment_status' => 'Menunggu Pembayaran'])); ?>">Belum Bayar</a>
                        <a class="admin-order-range-link <?= admin_orders_filter('payment_status') === 'DP Masuk' ? 'is-active' : ''; ?>" href="<?= esc(admin_orders_current_url(['payment_status' => 'DP Masuk'])); ?>">DP Masuk</a>
                        <a class="admin-order-range-link <?= admin_orders_filter('payment_status') === 'Lunas' ? 'is-active' : ''; ?>" href="<?= esc(admin_orders_current_url(['payment_status' => 'Lunas'])); ?>">Lunas / Akses Aktif</a>
                        <a class="admin-order-range-link <?= admin_orders_filter('fulfillment_status') === 'Siap Dikirim' ? 'is-active' : ''; ?>" href="<?= esc(admin_orders_current_url(['fulfillment_status' => 'Siap Dikirim'])); ?>">Siap Dikirim</a>
                        <a class="admin-order-range-link" href="<?= esc(url('admin/payment-reminders')); ?>">Reminder Pembayaran</a>
                    </div>
                    <label>Tahun
                        <input type="number" name="year" min="2020" max="<?= esc(date('Y')); ?>" value="<?= esc(admin_orders_selected_year()); ?>">
                    </label>
                    <label>Dari Tanggal
                        <input type="date" name="date_from" value="<?= esc(admin_orders_date_input('date_from')); ?>">
                    </label>
                    <label>Sampai Tanggal
                        <input type="date" name="date_to" value="<?= esc(admin_orders_date_input('date_to')); ?>">
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="">Semua status</option>
                            <?php foreach (order_allowed_statuses() as $status): ?>
                                <option value="<?= esc($status); ?>" <?= admin_orders_filter('status') === $status ? 'selected' : ''; ?>><?= esc($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Status Pembayaran
                        <select name="payment_status">
                            <option value="">Semua status pembayaran</option>
                            <?php foreach (order_allowed_payment_statuses() as $paymentStatus): ?>
                                <option value="<?= esc($paymentStatus); ?>" <?= admin_orders_filter('payment_status') === $paymentStatus ? 'selected' : ''; ?>><?= esc($paymentStatus); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Status Fulfillment
                        <select name="fulfillment_status">
                            <option value="">Semua fulfillment</option>
                            <?php foreach (function_exists('order_allowed_fulfillment_statuses') ? order_allowed_fulfillment_statuses() : ['Belum Diproses'] as $fulfillmentStatus): ?>
                                <option value="<?= esc($fulfillmentStatus); ?>" <?= admin_orders_filter('fulfillment_status') === $fulfillmentStatus ? 'selected' : ''; ?>><?= esc($fulfillmentStatus); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Preferensi Pembayaran
                        <select name="payment_method">
                            <option value="">Semua metode</option>
                            <?php foreach (order_payment_methods() as $paymentMethod): ?>
                                <option value="<?= esc($paymentMethod); ?>" <?= admin_orders_filter('payment_method') === $paymentMethod ? 'selected' : ''; ?>><?= esc($paymentMethod); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Produk
                        <input name="product_title" value="<?= esc(admin_orders_filter('product_title')); ?>" placeholder="Nama produk...">
                    </label>
                    <label>Kebutuhan
                        <input name="need" value="<?= esc(admin_orders_filter('need')); ?>" placeholder="Booking, survey, stok...">
                    </label>
                    <label>Lokasi
                        <input name="location" value="<?= esc(admin_orders_filter('location')); ?>" placeholder="Area layanan atau kota...">
                    </label>
                    <label>Pencarian
                        <input name="search" value="<?= esc(admin_orders_filter('search', 120)); ?>" placeholder="Nama, nomor, catatan...">
                    </label>
                    <div class="admin-lead-filter__actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn" href="<?= url('admin/orders'); ?>">Reset</a>
                    </div>
                </form>

                <div class="admin-lead-stats admin-order-stats">
                    <div class="admin-lead-stat-card"><span>Total Order</span><strong><?= esc((string)($summary['total'] ?? 0)); ?></strong><small>Sesuai filter aktif</small></div>
                    <div class="admin-lead-stat-card"><span>Hari Ini</span><strong><?= esc((string)($summary['today'] ?? 0)); ?></strong><small>Order masuk hari ini</small></div>
                    <div class="admin-lead-stat-card"><span>Baru</span><strong><?= esc((string)($summary['new'] ?? 0)); ?></strong><small>Belum diproses</small></div>
                    <div class="admin-lead-stat-card"><span>Siap Ditagih</span><strong><?= esc((string)($summary['payment_ready'] ?? 0)); ?></strong><small>Order menunggu pembayaran</small></div>
                    <div class="admin-lead-stat-card"><span>DP/Lunas</span><strong><?= esc((string)($summary['paid_like'] ?? 0)); ?></strong><small>Payment status sudah masuk</small></div>
                    <div class="admin-lead-stat-card"><span>Siap Kirim</span><strong><?= esc((string)($fulfillmentSummary['ready_to_ship'] ?? 0)); ?></strong><small>Fulfillment perlu ditindaklanjuti</small></div>
                    <div class="admin-lead-stat-card"><span>Dikirim</span><strong><?= esc((string)($fulfillmentSummary['shipped'] ?? 0)); ?></strong><small>Order punya status pengiriman</small></div>
                    <div class="admin-lead-stat-card"><span>Estimasi Nilai</span><strong><?= esc(rupiah((int)($summary['gross_estimate'] ?? 0))); ?></strong><small>Harga x jumlah, jika data harga ada</small></div>
                </div>

                <?php
                    $statusCounts = (array)($summary['by_status'] ?? []);
                    $statusMax = max(1, ...array_values($statusCounts ?: [1]));
                ?>
                <div class="admin-order-payment-panel">
                    <section class="admin-card admin-lead-panel">
                        <h2>Pipeline Order</h2>
                        <div class="admin-order-pipeline">
                            <?php foreach ($statusCounts as $label => $count): ?>
                                <div class="admin-order-pipeline-row"><span><?= esc((string)$label); ?></span><div class="admin-order-pipeline-bar"><span style="width:<?= esc((string)max(4, min(100, round(((int)$count / $statusMax) * 100)))); ?>%"></span></div><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (!$statusCounts): ?><p class="admin-muted">Belum ada data pipeline.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Status Pembayaran</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($summary['by_payment_status'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_payment_status'])): ?><p class="admin-muted">Belum ada data pembayaran.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Fulfillment</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($fulfillmentSummary['by_fulfillment_status'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($fulfillmentSummary['by_fulfillment_status'])): ?><p class="admin-muted">Belum ada data fulfillment.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Preferensi Pembayaran</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($summary['by_payment_method'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_payment_method'])): ?><p class="admin-muted">Belum ada data metode.</p><?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="admin-card admin-lead-panel">
                    <h2>Invoice & Payment Instruction</h2>
                    <p>Sistem ini menambahkan invoice draft manual. Admin bisa menyiapkan nomor invoice, nominal, batas pembayaran, instruksi transfer/QRIS manual, lalu follow up pelanggan via WhatsApp.</p>
                    <span class="admin-order-badge">Invoice draft manual</span>
                    <span class="admin-order-badge">Instruksi pembayaran bisa dikirim</span>
                    <span class="admin-order-badge">Printable invoice untuk admin</span>
                    <span class="admin-order-badge">Tetap belum auto payment gateway</span>
                    <span class="admin-order-badge">Reminder H+1/H+2 via dashboard</span>
                    <span class="admin-order-badge">Fulfillment + resi manual</span>
                    <span class="admin-order-badge">Timeline status untuk customer</span>
                </section>

                <?php
                    $orderFollowupSummary = function_exists('crm_summary') ? crm_summary(0, ['_all_time' => true, 'target_type' => 'order']) : [];
                ?>
                <div class="admin-followup-snapshot">
                    <div><strong><?= esc((string)($orderFollowupSummary['today'] ?? 0)); ?></strong><span>Follow-up order hari ini</span></div>
                    <div><strong><?= esc((string)($orderFollowupSummary['overdue'] ?? 0)); ?></strong><span>Follow-up terlambat</span></div>
                    <div><strong><?= esc((string)($orderFollowupSummary['hot'] ?? 0)); ?></strong><span>Order prioritas tinggi</span></div>
                    <a class="admin-btn admin-btn--ghost" href="<?= url('admin/followups?target_type=order&due=today'); ?>">Lihat Follow-up</a>
                </div>

                <div class="admin-lead-grid-secondary admin-order-grid">
                    <section class="admin-card admin-lead-panel">
                        <h2>Produk Teratas</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($summary['by_product'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_product'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Lokasi Teratas</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($summary['by_location'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_location'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="admin-card admin-lead-panel admin-order-list">
                    <div class="admin-lead-panel__head">
                        <div>
                            <h2>Order Draft Terbaru</h2>
                            <p>Kelola calon pesanan yang masuk dari form produk. Setelah dikonfirmasi, admin bisa follow up lewat WhatsApp.</p>
                        </div>
                    </div>

                    <div class="admin-order-cards">
                        <?php foreach ($orders as $item): ?>
                            <article class="admin-order-card admin-order-card--<?= esc(slugify((string)($item['status'] ?? 'baru'))); ?>">
                                <div class="admin-order-card__head">
                                    <div>
                                        <strong><?= esc((string)($item['product_title'] ?? 'Order Produk')); ?></strong>
                                        <span><?= esc(date('d M Y H:i', (int)($item['_ts'] ?? time()))); ?> · <?= esc((string)($item['name'] ?? '-')); ?></span>
                                    </div>
                                    <em><?= esc((string)($item['status'] ?? 'Baru')); ?></em>
                                </div>
                                <div class="admin-order-card__body">
                                    <p><b>No. Order:</b> <?= esc(function_exists('order_public_reference') ? order_public_reference($item) : (string)($item['id'] ?? '-')); ?></p>
                                    <p><b>Kontak:</b> <?= esc((string)($item['phone'] ?? '-')); ?><?= !empty($item['email']) ? ' · <b>Email:</b> ' . esc((string)$item['email']) : ''; ?></p>
                                    <p><b>Jumlah:</b> <?= esc((string)($item['quantity'] ?? '1')); ?> · <b>Harga:</b> <?= esc(rupiah((int)($item['price'] ?? 0))); ?></p>
                                    <p><b>Pembayaran:</b> <?= esc((string)($item['payment_method'] ?? 'Belum Memilih')); ?> · <b>Status:</b> <?= esc((string)($item['payment_status'] ?? 'Belum Ditagih')); ?></p>
                                    <?php if (!empty($item['commerce_shipping_policy_label']) || !empty($item['commerce_payment_policy_label']) || !empty($item['commerce_preorder_enabled'])): ?>
                                        <div class="admin-commerce-snapshot">
                                            <?php if (!empty($item['commerce_shipping_policy_label'])): ?><span>Ongkir: <?= esc((string)$item['commerce_shipping_policy_label']); ?></span><?php endif; ?>
                                            <?php if (!empty($item['commerce_payment_policy_label'])): ?><span>Payment: <?= esc((string)$item['commerce_payment_policy_label']); ?></span><?php endif; ?>
                                            <?php if (!empty($item['commerce_allowed_gateways'])): ?><span>Gateway: <?= esc((string)$item['commerce_allowed_gateways']); ?></span><?php endif; ?>
                                            <?php if (($item['commerce_preorder_enabled'] ?? '') === 'yes'): ?><span>Pre-order<?= !empty($item['commerce_preorder_eta']) ? ': ' . esc((string)$item['commerce_preorder_eta']) : ''; ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php
                                        $digitalRecord = function_exists('digital_delivery_record_for_order') ? digital_delivery_record_for_order($item) : null;
                                        $digitalStatus = function_exists('digital_delivery_public_status') ? digital_delivery_public_status($item) : ['state' => '', 'message' => '', 'url' => ''];
                                    ?>
                                    <?php if (in_array((string)($digitalStatus['state'] ?? ''), ['active', 'pending', 'expired'], true)): ?>
                                        <div class="admin-digital-access-box">
                                            <h3>Digital Delivery</h3>
                                            <p><?= esc((string)($digitalStatus['message'] ?? 'Akses digital mengikuti status pembayaran.')); ?></p>
                                            <?php if (!empty($digitalStatus['url'])): ?><code><?= esc((string)$digitalStatus['url']); ?></code><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <p><b>Kebutuhan:</b> <?= esc((string)($item['need'] ?? '-')); ?></p>
                                    <p><b>Lokasi:</b> <?= esc((string)($item['location'] ?? '-')); ?></p>
                                    <?php if (!empty($item['shipping_method'])): ?><p><b>Pengiriman:</b> <?= esc((string)$item['shipping_method']); ?></p><?php endif; ?>
                                    <?php if (!empty($item['shipping_origin'])): ?><p><b>Asal Kirim:</b> <?= esc((string)$item['shipping_origin']); ?><?= !empty($item['shipping_origin_code']) ? ' · Kode: ' . esc((string)$item['shipping_origin_code']) : ''; ?></p><?php endif; ?>
                                    <?php if (!empty($item['shipping_rule_name']) || !empty($item['shipping_total'])): ?><p><b>Ongkir:</b> <?= esc(!empty($item['shipping_total']) ? rupiah((int)$item['shipping_total']) : 'Konfirmasi admin'); ?><?= !empty($item['shipping_rule_name']) ? ' · Layanan: ' . esc((string)$item['shipping_rule_name']) : ''; ?><?= !empty($item['shipping_eta']) ? ' · ETA: ' . esc((string)$item['shipping_eta']) : ''; ?><?= !empty($item['shipping_quote_source']) ? ' · Sumber: ' . esc((string)$item['shipping_quote_source']) : ''; ?></p><?php endif; ?>
                                    <?php if (function_exists('checkout_shipping_address') && checkout_shipping_address($item) !== '-'): ?><p><b>Alamat:</b> <?= esc(checkout_shipping_address($item)); ?></p><?php endif; ?>
                                    <?php $tracking = function_exists('order_tracking_summary') ? order_tracking_summary($item) : []; ?>
                                    <?php if (!empty($tracking['fulfillment_status']) || !empty($tracking['tracking_number']) || !empty($tracking['carrier'])): ?>
                                        <div class="admin-fulfillment-box">
                                            <h3>Fulfillment & Resi</h3>
                                            <div class="admin-fulfillment-grid">
                                                <span>Status<strong><?= esc((string)($tracking['fulfillment_status'] ?? 'Belum Diproses')); ?></strong></span>
                                                <span>Kurir/Ekspedisi<strong><?= esc((string)($tracking['carrier'] ?? '-')); ?></strong></span>
                                                <span>Layanan<strong><?= esc((string)($tracking['service'] ?? '-')); ?></strong></span>
                                                <span>No. Resi<strong><?= esc((string)($tracking['tracking_number'] ?? '-')); ?></strong></span>
                                                <?php if (!empty($tracking['shipped_at'])): ?><span>Dikirim<strong><?= esc((string)$tracking['shipped_at']); ?></strong></span><?php endif; ?>
                                                <?php if (!empty($tracking['delivered_at'])): ?><span>Diterima<strong><?= esc((string)$tracking['delivered_at']); ?></strong></span><?php endif; ?>
                                            </div>
                                            <?php if (!empty($tracking['tracking_url'])): ?><p><a href="<?= esc((string)$tracking['tracking_url']); ?>" target="_blank" rel="nofollow noopener">Buka link tracking ekspedisi</a></p><?php endif; ?>
                                            <?php if (!empty($tracking['note'])): ?><p><b>Catatan Fulfillment:</b><br><?= nl2br(esc((string)$tracking['note'])); ?></p><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['planned_date'])): ?><p><b>Rencana tanggal:</b> <?= esc(date('d M Y', strtotime((string)$item['planned_date']))); ?></p><?php endif; ?>
                                    <?php if (!empty($item['message'])): ?><p><b>Catatan:</b><br><?= nl2br(esc((string)$item['message'])); ?></p><?php endif; ?>
                                    <p><b>Sumber:</b> <?= esc((string)($item['source'] ?? '-')); ?> · <?= esc((string)($item['page_path'] ?? '-')); ?></p>
                                    <?php if (!empty($item['product_url'])): ?><p><a href="<?= esc((string)$item['product_url']); ?>" target="_blank" rel="noopener">Buka halaman produk</a></p><?php endif; ?>
                                    <?php if (!empty($item['product_slug'])): ?><p><a href="<?= esc(order_checkout_url((string)$item['product_slug'], ['source' => 'admin-follow-up'])); ?>" target="_blank" rel="noopener">Buka link checkout produk</a></p><?php endif; ?>
                                    <p><a href="<?= url('admin/payment-settings'); ?>">Kelola metode transfer/QRIS manual</a></p>
                                    <?php if (!empty($item['status_note'])): ?><p><b>Catatan Admin:</b><br><?= nl2br(esc((string)$item['status_note'])); ?></p><?php endif; ?>
                                    <?php if (!empty($item['payment_note'])): ?><p><b>Catatan Pembayaran:</b><br><?= nl2br(esc((string)$item['payment_note'])); ?></p><?php endif; ?>
                                    <?php if (!empty($item['internal_note'])): ?><p><b>Catatan Internal:</b><br><?= nl2br(esc((string)$item['internal_note'])); ?></p><?php endif; ?>
                                    <?php $historyItems = function_exists('order_status_history') ? order_status_history($item, 5) : []; ?>
                                    <?php if ($historyItems): ?>
                                        <div class="admin-order-history"><strong>Timeline Update Terakhir</strong>
                                            <?php foreach ($historyItems as $history): ?>
                                                <div><strong><?= esc(date('d M Y H:i', strtotime((string)($history['time'] ?? 'now')) ?: time())); ?> · <?= esc((string)($history['status'] ?? '-')); ?><?= !empty($history['fulfillment_status']) ? ' · ' . esc((string)$history['fulfillment_status']) : ''; ?></strong><?= !empty($history['note']) ? nl2br(esc((string)$history['note'])) : 'Update status/order.'; ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php
                                        $invoiceNumber = order_invoice_number($item);
                                        $invoiceTotal = order_invoice_total($item);
                                        $invoiceDueDate = (string)($item['invoice_due_date'] ?? order_invoice_default_due_date());
                                        $invoiceChannel = order_invoice_payment_channel($item);
                                        $invoicePaymentProfile = function_exists('payment_order_profile_id') ? payment_order_profile_id($item) : (string)($item['invoice_payment_profile'] ?? '');
                                        $invoiceInstruction = order_invoice_payment_instruction($item);
                                        $invoiceNote = order_invoice_public_note($item);
                                    ?>
                                    <div class="admin-order-invoice-box">
                                        <h3>Draft Invoice Manual</h3>
                                        <div class="admin-order-invoice-meta">
                                            <span>No. Invoice<strong><?= esc($invoiceNumber); ?></strong></span>
                                            <span>Total<strong><?= esc($invoiceTotal > 0 ? rupiah($invoiceTotal) : 'Belum ditentukan'); ?></strong></span>
                                            <span>Jatuh Tempo<strong><?= esc($invoiceDueDate !== '' ? date('d M Y', strtotime($invoiceDueDate)) : '-'); ?></strong></span>
                                        </div>
                                        <p><b>Channel:</b> <?= esc($invoiceChannel); ?></p>
                                        <?php if ($invoicePaymentProfile !== '' && function_exists('payment_profile_label')): ?><p><b>Profil:</b> <?= esc(payment_profile_label($invoicePaymentProfile)); ?></p><?php endif; ?>
                                        <p><b>Instruksi:</b><br><?= nl2br(esc($invoiceInstruction)); ?></p>
                                    </div>
                                    <?php $orderProofs = function_exists('payment_proofs_for_order') ? payment_proofs_for_order($item, 4) : []; ?>
                                    <div class="admin-payment-proof-box">
                                        <h3>Bukti Pembayaran Customer</h3>
                                        <?php foreach ($orderProofs as $proof): ?>
                                            <div class="admin-payment-proof-row">
                                                <span><strong><?= esc((string)($proof['payer_name'] ?? '-')); ?></strong><br><?= esc(date('d M Y H:i', (int)($proof['_ts'] ?? time()))); ?></span>
                                                <span><?= esc(rupiah((int)($proof['amount'] ?? 0))); ?></span>
                                                <span><?= esc((string)($proof['status'] ?? 'Menunggu Review')); ?></span>
                                                <?php $proofFileUrl = payment_proof_file_url($proof); ?>
                                                <span><?php if ($proofFileUrl): ?><a href="<?= esc($proofFileUrl); ?>" target="_blank" rel="noopener">Lihat bukti</a><?php endif; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (!$orderProofs): ?><p class="admin-payment-proof-empty">Belum ada bukti pembayaran untuk order ini.</p><?php endif; ?>
                                        <p><a href="<?= esc(url('admin/payment-proofs?order_ref=' . rawurlencode(order_public_reference($item)))); ?>">Buka dashboard bukti pembayaran</a></p>
                                    </div>
                                    <?php
                                        $publicStatusUrl = function_exists('order_status_url') ? order_status_url($item) : '';
                                        $publicInvoiceUrl = function_exists('order_public_invoice_url') ? order_public_invoice_url($item) : '';
                                        $statusWaMessage = function_exists('order_status_whatsapp_message') ? order_status_whatsapp_message($item) : ('Status order ' . order_public_reference($item));
                                        $invoiceWaMessage = function_exists('order_invoice_confirmation_whatsapp_message') ? order_invoice_confirmation_whatsapp_message($item) : ('Invoice ' . $invoiceNumber);
                                    ?>
                                    <div class="admin-public-link-box">
                                        <h3>Link Publik Customer</h3>
                                        <p class="admin-muted">Kirim link status order atau invoice ke customer via WhatsApp/email. Link memakai token dan halaman dibuat noindex.</p>
                                        <div class="admin-public-link-url">
                                            <span>Status Order</span>
                                            <code><?= esc($publicStatusUrl); ?></code>
                                            <span>Invoice</span>
                                            <code><?= esc($publicInvoiceUrl); ?></code>
                                        </div>
                                        <div class="admin-public-link-actions">
                                            <?php if (!empty($item['phone'])): ?>
                                                <a class="admin-btn admin-btn--ghost" href="<?= esc(wa_link($statusWaMessage)); ?>" target="_blank" rel="nofollow noopener" <?= function_exists('conversion_link_attrs') ? conversion_link_attrs(['source'=>'Admin Order Public Status','type'=>'whatsapp-public-status','channel'=>'whatsapp','intent'=>'send-order-status-link','label'=>order_public_reference($item)]) : ''; ?>>WA Link Status</a>
                                                <a class="admin-btn admin-btn--ghost" href="<?= esc(wa_link($invoiceWaMessage)); ?>" target="_blank" rel="nofollow noopener" <?= function_exists('conversion_link_attrs') ? conversion_link_attrs(['source'=>'Admin Order Public Invoice','type'=>'whatsapp-public-invoice','channel'=>'whatsapp','intent'=>'send-invoice-link','label'=>$invoiceNumber]) : ''; ?>>WA Link Invoice</a>
                                            <?php endif; ?>
                                            <?php if (!empty($item['email'])): ?>
                                                <form method="post">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="form_action" value="send_public_link_email">
                                                    <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                                    <input type="hidden" name="link_kind" value="status">
                                                    <button class="admin-btn admin-btn--ghost" type="submit">Email Link Status</button>
                                                </form>
                                                <form method="post">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="form_action" value="send_public_link_email">
                                                    <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                                    <input type="hidden" name="link_kind" value="invoice">
                                                    <button class="admin-btn admin-btn--ghost" type="submit">Email Link Invoice</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="admin-muted">Email customer belum diisi, tombol email tidak ditampilkan.</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <form method="post" class="admin-order-status-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="update_status">
                                    <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                    <select name="status">
                                        <?php foreach (order_allowed_statuses() as $status): ?>
                                            <option value="<?= esc($status); ?>" <?= ((string)($item['status'] ?? 'Baru')) === $status ? 'selected' : ''; ?>><?= esc($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="payment_status">
                                        <?php foreach (order_allowed_payment_statuses() as $paymentStatus): ?>
                                            <option value="<?= esc($paymentStatus); ?>" <?= ((string)($item['payment_status'] ?? 'Belum Ditagih')) === $paymentStatus ? 'selected' : ''; ?>><?= esc($paymentStatus); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="note" placeholder="Catatan admin singkat" value="<?= esc((string)($item['status_note'] ?? '')); ?>">
                                    <input type="text" name="payment_note" placeholder="Catatan pembayaran/invoice" value="<?= esc((string)($item['payment_note'] ?? '')); ?>">
                                    <div class="admin-order-invoice-fields">
                                        <input type="text" name="invoice_number" placeholder="No. invoice" value="<?= esc($invoiceNumber); ?>">
                                        <input type="number" name="invoice_total" min="0" step="1000" placeholder="Nominal invoice" value="<?= esc((string)$invoiceTotal); ?>">
                                        <input type="date" name="invoice_due_date" value="<?= esc($invoiceDueDate); ?>">
                                        <?php
                                            $paymentProfilePayload = [];
                                            if (function_exists('payment_profiles') && function_exists('payment_instruction_from_profile')) {
                                                foreach (payment_profiles() as $profileId => $profileData) {
                                                    $paymentProfilePayload[(string)$profileId] = [
                                                        'label' => (string)($profileData['label'] ?? $profileId),
                                                        'instruction' => payment_instruction_from_profile($profileData),
                                                    ];
                                                }
                                            }
                                            $paymentProfilePayloadEncoded = base64_encode(json_encode($paymentProfilePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
                                        ?>
                                        <input class="js-invoice-channel" type="text" name="invoice_payment_channel" placeholder="Transfer / QRIS / Tunai" value="<?= esc($invoiceChannel); ?>">
                                        <select class="js-payment-profile" name="invoice_payment_profile" aria-label="Profil pembayaran manual" data-payment-profiles="<?= esc($paymentProfilePayloadEncoded); ?>">
                                            <?php foreach (function_exists('payment_profile_options') ? payment_profile_options() : ['' => 'Pilih Metode Manual'] as $profileId => $profileLabel): ?>
                                                <option value="<?= esc((string)$profileId); ?>" <?= (string)$profileId === $invoicePaymentProfile ? 'selected' : ''; ?>><?= esc((string)$profileLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea class="js-invoice-instruction" name="invoice_payment_instruction" placeholder="Instruksi pembayaran untuk pelanggan"><?= esc($invoiceInstruction); ?></textarea>
                                        <textarea name="invoice_public_note" placeholder="Catatan invoice untuk pelanggan"><?= esc($invoiceNote); ?></textarea>
                                    </div>
                                    <div class="admin-order-fulfillment-fields">
                                        <select name="fulfillment_status" aria-label="Status fulfillment">
                                            <?php foreach (function_exists('order_allowed_fulfillment_statuses') ? order_allowed_fulfillment_statuses() : ['Belum Diproses'] as $fulfillmentStatus): ?>
                                                <option value="<?= esc($fulfillmentStatus); ?>" <?= ((string)($item['fulfillment_status'] ?? 'Belum Diproses')) === $fulfillmentStatus ? 'selected' : ''; ?>><?= esc($fulfillmentStatus); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="shipping_carrier" placeholder="Kurir/Ekspedisi aktual" value="<?= esc((string)($item['shipping_carrier'] ?? $item['shipping_courier'] ?? '')); ?>">
                                        <input type="text" name="shipping_service_actual" placeholder="Layanan aktual" value="<?= esc((string)($item['shipping_service_actual'] ?? $item['shipping_service_label'] ?? $item['shipping_service'] ?? '')); ?>">
                                        <input type="text" name="shipping_tracking_number" placeholder="Nomor resi / kode tracking" value="<?= esc((string)($item['shipping_tracking_number'] ?? '')); ?>">
                                        <input type="url" name="shipping_tracking_url" placeholder="Link tracking opsional" value="<?= esc((string)($item['shipping_tracking_url'] ?? '')); ?>">
                                        <input type="text" name="shipped_at" placeholder="Tanggal kirim, contoh 2026-05-31" value="<?= esc((string)($item['shipped_at'] ?? '')); ?>">
                                        <input type="text" name="delivered_at" placeholder="Tanggal diterima opsional" value="<?= esc((string)($item['delivered_at'] ?? '')); ?>">
                                        <textarea name="fulfillment_note" placeholder="Catatan fulfillment untuk customer"><?= esc((string)($item['fulfillment_note'] ?? '')); ?></textarea>
                                        <textarea name="internal_note" placeholder="Catatan internal admin, tidak tampil ke customer"><?= esc((string)($item['internal_note'] ?? '')); ?></textarea>
                                    </div>
                                    <button class="admin-btn admin-btn--primary" type="submit">Update</button>
                                    <a class="admin-btn admin-btn--ghost" href="<?= esc(url('admin/order-invoice?id=' . rawurlencode((string)($item['id'] ?? '')))); ?>" target="_blank" rel="noopener">Lihat Invoice</a>
                                    <?php if (!empty($item['phone'])): ?>
                                        <?php
                                            $waPhone = order_phone_for_whatsapp((string)$item['phone']);
                                            $templateCatalog = order_whatsapp_followup_templates();
                                            $templateMessages = order_whatsapp_template_messages($item);
                                            $defaultTemplateKey = ((string)($item['payment_status'] ?? '') === 'Menunggu Pembayaran') ? 'payment_reminder' : 'followup_order';
                                            if (!isset($templateMessages[$defaultTemplateKey])) {
                                                $defaultTemplateKey = 'followup_order';
                                            }
                                            $defaultCustomMessage = (string)($templateMessages[$defaultTemplateKey] ?? order_render_whatsapp_template('followup_order', $item));
                                            $templatePayload = base64_encode(json_encode($templateMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
                                            $templateDescriptions = [];
                                            foreach ($templateCatalog as $templateKey => $templateInfo) {
                                                $templateDescriptions[(string)$templateKey] = (string)($templateInfo['description'] ?? '');
                                            }
                                            $templateDescriptionPayload = base64_encode(json_encode($templateDescriptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
                                        ?>
                                        <div class="admin-wa-composer" data-wa-composer data-wa-phone="<?= esc($waPhone); ?>" data-wa-templates="<?= esc($templatePayload); ?>" data-wa-descriptions="<?= esc($templateDescriptionPayload); ?>">
                                            <div class="admin-wa-composer__head">
                                                <div>
                                                    <h3>Custom WhatsApp Follow-up</h3>
                                                    <p>Pilih template, edit pesan sesuai kebutuhan customer, lalu buka WhatsApp. Pesan belum terkirim otomatis sebelum admin menekan kirim di WhatsApp.</p>
                                                </div>
                                                <span><?= esc(order_public_reference($item)); ?></span>
                                            </div>
                                            <label>Template Follow-up
                                                <select class="js-wa-template" aria-label="Template follow-up WhatsApp">
                                                    <?php foreach ($templateCatalog as $templateKey => $templateInfo): ?>
                                                        <option value="<?= esc((string)$templateKey); ?>" <?= $templateKey === $defaultTemplateKey ? 'selected' : ''; ?>><?= esc((string)($templateInfo['label'] ?? $templateKey)); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <small class="admin-wa-composer__hint js-wa-template-desc"><?= esc((string)($templateCatalog[$defaultTemplateKey]['description'] ?? '')); ?></small>
                                            <label>Pesan WhatsApp Custom
                                                <textarea class="js-wa-message" rows="10" maxlength="1800"><?= esc($defaultCustomMessage); ?></textarea>
                                            </label>
                                            <div class="admin-wa-composer__tools">
                                                <button type="button" class="admin-btn admin-btn--ghost js-wa-regenerate">Generate dari Template</button>
                                                <button type="button" class="admin-btn admin-btn--ghost js-wa-copy">Salin Pesan</button>
                                                <a class="admin-btn admin-btn--primary js-wa-open" href="<?= esc('https://wa.me/' . $waPhone . '?text=' . rawurlencode($defaultCustomMessage)); ?>" target="_blank" rel="nofollow noopener" <?= function_exists('conversion_link_attrs') ? conversion_link_attrs(['source' => 'Admin Order Custom WA', 'type' => 'whatsapp-followup', 'channel' => 'whatsapp', 'intent' => 'order-followup', 'label' => (string)($item['product_title'] ?? 'Order')]) : ''; ?>>Chat WhatsApp Custom</a>
                                            </div>
                                            <div class="admin-wa-composer__placeholders">
                                                Placeholder tersedia: <code>{name}</code>, <code>{order_ref}</code>, <code>{invoice_no}</code>, <code>{product}</code>, <code>{need}</code>, <code>{location}</code>, <code>{invoice_total}</code>, <code>{invoice_due_date}</code>, <code>{fulfillment_status}</code>, <code>{tracking_number}</code>, <code>{tracking_url}</code>, <code>{digital_access_url}</code>, <code>{digital_instructions}</code>.
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="admin-wa-composer admin-wa-composer--empty">
                                            <strong>WhatsApp follow-up belum tersedia.</strong>
                                            <p>Order ini belum memiliki nomor WhatsApp/telepon valid.</p>
                                        </div>
                                    <?php endif; ?>
                                </form>
                                <?php
                                    $orderRecentFollowups = function_exists('crm_recent_for_target') ? crm_recent_for_target('order', (string)($item['id'] ?? ''), 5) : [];
                                    $orderSuggestedPriority = function_exists('crm_temperature_from_order') ? crm_temperature_from_order($item) : 'Normal';
                                ?>
                                <div class="admin-crm-panel">
                                    <div class="admin-crm-panel__head">
                                        <div>
                                            <h3>Mini CRM Follow-up</h3>
                                            <p>Catat hasil follow-up, tentukan temperatur lead, dan jadwalkan reminder berikutnya agar order tidak terlewat.</p>
                                        </div>
                                        <span class="admin-crm-temp admin-crm-temp--<?= esc(crm_status_class($orderSuggestedPriority)); ?>"><?= esc($orderSuggestedPriority); ?></span>
                                    </div>
                                    <form method="post" class="admin-crm-form">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="form_action" value="add_followup">
                                        <input type="hidden" name="target_id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                        <input type="hidden" name="target_ref" value="<?= esc(order_public_reference($item)); ?>">
                                        <input type="hidden" name="target_name" value="<?= esc((string)($item['name'] ?? '')); ?>">
                                        <input type="hidden" name="phone" value="<?= esc((string)($item['phone'] ?? '')); ?>">
                                        <input type="hidden" name="email" value="<?= esc((string)($item['email'] ?? '')); ?>">
                                        <input type="hidden" name="subject" value="<?= esc((string)($item['product_title'] ?? $item['need'] ?? 'Order')); ?>">
                                        <label>Prioritas / Temperatur Lead
                                            <select name="priority">
                                                <?php foreach (array_keys(crm_priorities()) as $priority): ?>
                                                    <option value="<?= esc($priority); ?>" <?= $priority === $orderSuggestedPriority ? 'selected' : ''; ?>><?= esc($priority); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Hasil Follow-up
                                            <select name="outcome">
                                                <?php foreach (array_keys(crm_outcomes()) as $outcome): ?>
                                                    <option value="<?= esc($outcome); ?>"><?= esc($outcome); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Tanggal Follow-up Berikutnya
                                            <input type="date" name="next_followup_date" value="<?= esc(date('Y-m-d', strtotime('+1 day'))); ?>">
                                        </label>
                                        <label>Jam
                                            <input type="time" name="next_followup_time" value="09:00">
                                        </label>
                                        <label class="admin-crm-form__note">Catatan Follow-up
                                            <textarea name="followup_note" rows="4" placeholder="Contoh: invoice sudah dikirim, customer minta dihubungi ulang besok, atau menunggu konfirmasi keluarga."></textarea>
                                        </label>
                                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Follow-up</button>
                                    </form>
                                    <div class="admin-crm-history">
                                        <h4>Riwayat Terakhir</h4>
                                        <?php foreach ($orderRecentFollowups as $followup): ?>
                                            <div><strong><?= esc((string)($followup['outcome'] ?? 'Catatan')); ?></strong><span><?= esc(date('d M Y H:i', (int)($followup['_ts'] ?? time()))); ?> · <?= esc((string)($followup['priority'] ?? 'Normal')); ?> · <?= esc(crm_next_label($followup)); ?></span><?php if (!empty($followup['note'])): ?><p><?= nl2br(esc((string)$followup['note'])); ?></p><?php endif; ?></div>
                                        <?php endforeach; ?>
                                        <?php if (!$orderRecentFollowups): ?><p class="admin-muted">Belum ada riwayat follow-up untuk order ini.</p><?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$orders): ?>
                            <div class="admin-card admin-empty-card">
                                <h3>Belum ada order sesuai filter.</h3>
                                <p>Coba kirim form order dari halaman detail produk, atau ubah filter rentang data. Tombol <strong>Chat WhatsApp</strong> akan muncul otomatis pada setiap order yang memiliki nomor valid.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
(function () {
    function decodePayload(payload) {
        try {
            return JSON.parse(atob(payload || 'e30='));
        } catch (error) {
            return {};
        }
    }

    function updateWaLink(composer) {
        const phone = (composer.getAttribute('data-wa-phone') || '').replace(/\D+/g, '');
        const textarea = composer.querySelector('.js-wa-message');
        const link = composer.querySelector('.js-wa-open');
        if (!phone || !textarea || !link) {
            return;
        }
        link.href = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(textarea.value.trim());
    }


    function decodePaymentProfiles(payload) {
        try { return JSON.parse(atob(payload || 'e30=')); } catch (error) { return {}; }
    }

    document.querySelectorAll('.js-payment-profile').forEach(function (select) {
        const profiles = decodePaymentProfiles(select.getAttribute('data-payment-profiles'));
        const form = select.closest('form');
        if (!form) { return; }
        const channel = form.querySelector('.js-invoice-channel');
        const instruction = form.querySelector('.js-invoice-instruction');
        select.addEventListener('change', function () {
            const key = select.value;
            if (!key || !profiles[key]) { return; }
            if (channel && profiles[key].label) {
                channel.value = profiles[key].label;
            }
            if (instruction && profiles[key].instruction) {
                instruction.value = profiles[key].instruction;
            }
        });
    });

    document.querySelectorAll('[data-wa-composer]').forEach(function (composer) {
        const templates = decodePayload(composer.getAttribute('data-wa-templates'));
        const descriptions = decodePayload(composer.getAttribute('data-wa-descriptions'));
        const select = composer.querySelector('.js-wa-template');
        const textarea = composer.querySelector('.js-wa-message');
        const desc = composer.querySelector('.js-wa-template-desc');
        const regenerate = composer.querySelector('.js-wa-regenerate');
        const copy = composer.querySelector('.js-wa-copy');

        if (select) {
            select.addEventListener('change', function () {
                const key = select.value;
                if (desc) {
                    desc.textContent = descriptions[key] || '';
                }
            });
        }

        if (regenerate && select && textarea) {
            regenerate.addEventListener('click', function () {
                const key = select.value;
                if (templates[key]) {
                    textarea.value = templates[key];
                    updateWaLink(composer);
                    textarea.focus();
                }
            });
        }

        if (textarea) {
            textarea.addEventListener('input', function () {
                updateWaLink(composer);
            });
        }

        if (copy && textarea) {
            copy.addEventListener('click', function () {
                const text = textarea.value.trim();
                if (!text) {
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        copy.textContent = 'Tersalin';
                        setTimeout(function () { copy.textContent = 'Salin Pesan'; }, 1600);
                    }).catch(function () {
                        textarea.select();
                        document.execCommand('copy');
                    });
                } else {
                    textarea.select();
                    document.execCommand('copy');
                }
            });
        }

        updateWaLink(composer);
    });
})();
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
