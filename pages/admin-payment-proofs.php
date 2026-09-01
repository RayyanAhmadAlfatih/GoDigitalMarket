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

function admin_payment_proofs_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_payment_proofs_filter(string $key, int $max = 100): string
{
    return payment_proof_clean((string)($_GET[$key] ?? ''), $max);
}

function admin_payment_proofs_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));
    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_payment_proofs_days(): int
{
    $range = admin_payment_proofs_range();
    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }
    return max(1, min(3650, (int)$range));
}

function admin_payment_proofs_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_payment_proofs_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));
    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_payment_proofs_filters(): array
{
    $range = admin_payment_proofs_range();
    $filters = array_filter([
        'status' => admin_payment_proofs_filter('status'),
        'payment_method' => admin_payment_proofs_filter('payment_method'),
        'order_ref' => admin_payment_proofs_filter('order_ref'),
        'product_title' => admin_payment_proofs_filter('product_title'),
        'search' => admin_payment_proofs_filter('search', 140),
    ], static fn($v): bool => $v !== '' && $v !== null && $v !== false);

    if ($range === 'all') {
        $filters['_all_time'] = true;
    }
    if ($range === 'year') {
        $year = admin_payment_proofs_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
        $filters['_year'] = $year;
    }
    if ($range === 'custom') {
        $from = admin_payment_proofs_date_input('date_from');
        $to = admin_payment_proofs_date_input('date_to');
        if ($from !== '') {
            $filters['_start_ts'] = strtotime($from . ' 00:00:00') ?: 0;
        }
        if ($to !== '') {
            $filters['_end_ts'] = strtotime($to . ' 23:59:59') ?: time();
        }
    }
    return $filters;
}

function admin_payment_proofs_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_payment_proofs_range(),
        'year' => admin_payment_proofs_selected_year(),
        'date_from' => admin_payment_proofs_date_input('date_from'),
        'date_to' => admin_payment_proofs_date_input('date_to'),
        'status' => admin_payment_proofs_filter('status'),
        'payment_method' => admin_payment_proofs_filter('payment_method'),
        'order_ref' => admin_payment_proofs_filter('order_ref'),
        'product_title' => admin_payment_proofs_filter('product_title'),
        'search' => admin_payment_proofs_filter('search', 140),
    ], $extra);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/payment-proofs' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_payment_proofs_range_label(): string
{
    $range = admin_payment_proofs_range();
    if ($range === 'all') {
        return 'Semua data sejak bukti pembayaran aktif';
    }
    if ($range === 'year') {
        return 'Tahun ' . admin_payment_proofs_selected_year();
    }
    if ($range === 'custom') {
        $from = admin_payment_proofs_date_input('date_from');
        $to = admin_payment_proofs_date_input('date_to');
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
    return admin_payment_proofs_days() . ' hari terakhir';
}

function admin_payment_proofs_export_csv(array $proofs): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="payment-proofs-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['time', 'status', 'order_ref', 'invoice_number', 'payer_name', 'payer_phone', 'payer_email', 'amount', 'payment_method', 'payment_channel', 'product_title', 'note', 'admin_note', 'file_path']);
    foreach ($proofs as $proof) {
        fputcsv($out, [
            (string)($proof['time'] ?? ''),
            (string)($proof['status'] ?? ''),
            (string)($proof['order_ref'] ?? ''),
            (string)($proof['invoice_number'] ?? ''),
            (string)($proof['payer_name'] ?? ''),
            (string)($proof['payer_phone'] ?? ''),
            (string)($proof['payer_email'] ?? ''),
            (string)($proof['amount'] ?? ''),
            (string)($proof['payment_method'] ?? ''),
            (string)($proof['payment_channel'] ?? ''),
            (string)($proof['product_title'] ?? ''),
            (string)($proof['note'] ?? ''),
            (string)($proof['admin_note'] ?? ''),
            (string)($proof['file_path'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

if ($action === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/payment-proofs');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_payment_proofs_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'payment_proofs']);
            }
            redirect_302('admin/payment-proofs');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'update_payment_proof') {
        $proofId = payment_proof_clean((string)($_POST['id'] ?? ''), 80);
        $status = payment_proof_clean((string)($_POST['status'] ?? ''), 60);
        $note = payment_proof_multiline_clean((string)($_POST['admin_note'] ?? ''), 500);
        $orderPaymentStatus = order_normalize_payment_status((string)($_POST['order_payment_status'] ?? ''));
        $syncOrderPayment = !empty($_POST['sync_order_payment']);
        if ($syncOrderPayment && $status === 'Lunas') {
            $orderPaymentStatus = 'Lunas';
        } elseif ($syncOrderPayment && $status === 'DP Masuk') {
            $orderPaymentStatus = 'DP Masuk';
        }
        $proof = payment_proof_find_by_id($proofId);
        $ok = $proof ? payment_proof_update_status($proofId, $status, $note) : false;
        if ($ok && $proof && $syncOrderPayment && !empty($proof['order_id'])) {
            $order = order_find_by_id((string)$proof['order_id']);
            if ($order) {
                order_update_status(
                    (string)$proof['order_id'],
                    (string)($order['status'] ?? 'Diproses'),
                    (string)($order['status_note'] ?? ''),
                    $orderPaymentStatus,
                    trim((string)($order['payment_note'] ?? '') . "\n" . 'Bukti pembayaran ' . $status . ': ' . $note),
                    []
                );
            }
        }
        if ($ok) {
            if (function_exists('conversion_store_event')) {
                conversion_store_event(conversion_normalize_event([
                    'source' => 'admin-payment-proofs',
                    'type' => 'payment_proof_reviewed',
                    'channel' => 'payment',
                    'intent' => 'payment-proof-' . slugify($status),
                    'label' => (string)($proof['order_ref'] ?? $proofId),
                    'page_path' => current_uri(),
                    'target_url' => url('admin/payment-proofs'),
                ]));
            }
            if (function_exists('transaction_store_event')) {
                transaction_store_event([
                    'category' => 'payment-proof',
                    'action' => 'payment_proof_reviewed',
                    'target_type' => 'payment_proof',
                    'target_id' => $proofId,
                    'target_ref' => (string)($proof['order_ref'] ?? $proofId),
                    'before' => ['status' => (string)($proof['status'] ?? 'Menunggu Review')],
                    'after' => ['status' => $status, 'order_payment_status' => $orderPaymentStatus],
                    'note' => $note,
                ]);
            }
            if (function_exists('marketing_integration_dispatch_buyer')) {
                $paidStatuses = function_exists('marketing_integration_paid_statuses') ? marketing_integration_paid_statuses() : ['DP Masuk', 'Lunas', 'Valid'];
                $isPaidProof = in_array($status, $paidStatuses, true) || in_array($orderPaymentStatus, $paidStatuses, true);
                if ($isPaidProof) {
                    $buyerPayload = [];
                    $buyerOrderId = (string)($proof['order_id'] ?? '');
                    if ($buyerOrderId !== '' && function_exists('order_find_by_id')) {
                        $buyerOrder = order_find_by_id($buyerOrderId);
                        if ($buyerOrder) {
                            $buyerPayload = $buyerOrder;
                        }
                    }
                    if ((string)($buyerPayload['buyer_synced_at'] ?? '') !== '') {
                        $isPaidProof = false;
                    }
                    $buyerPayload = array_merge($buyerPayload, [
                        'id' => (string)($proof['order_id'] ?? $proofId),
                        'ref' => (string)($proof['order_ref'] ?? ($buyerPayload['ref'] ?? $proofId)),
                        'order_ref' => (string)($proof['order_ref'] ?? ''),
                        'name' => (string)($buyerPayload['name'] ?? $proof['payer_name'] ?? ''),
                        'phone' => (string)($buyerPayload['phone'] ?? $proof['payer_phone'] ?? ''),
                        'email' => (string)($buyerPayload['email'] ?? $proof['payer_email'] ?? ''),
                        'payer_name' => (string)($proof['payer_name'] ?? ''),
                        'payer_phone' => (string)($proof['payer_phone'] ?? ''),
                        'payer_email' => (string)($proof['payer_email'] ?? ''),
                        'product_title' => (string)($buyerPayload['product_title'] ?? $proof['product_title'] ?? ''),
                        'payment_status' => $orderPaymentStatus,
                        'proof_status' => $status,
                        'consent_contact' => (string)($buyerPayload['consent_contact'] ?? 'yes'),
                    ]);
                    $sentBuyer = $isPaidProof ? marketing_integration_dispatch_buyer($buyerPayload, ['trigger' => 'payment_proof_review', 'proof_id' => $proofId]) : false;
                    if ($sentBuyer && $buyerOrderId !== '' && function_exists('order_read_statuses') && function_exists('order_write_statuses')) {
                        $buyerStatuses = order_read_statuses();
                        if (is_array($buyerStatuses[$buyerOrderId] ?? null)) {
                            $buyerStatuses[$buyerOrderId]['buyer_synced_at'] = date('c');
                            $buyerStatuses[$buyerOrderId]['buyer_synced_source'] = 'payment_proof_review';
                            order_write_statuses($buyerStatuses);
                        }
                    }
                }
            }
            redirect_302('admin/payment-proofs?updated=1');
        }
        $error = 'Status bukti pembayaran belum bisa diperbarui.';
    }
}

if (!empty($_GET['updated'])) {
    $message = 'Status bukti pembayaran berhasil diperbarui.';
}

$loggedIn = admin_payment_proofs_logged_in();
$filters = admin_payment_proofs_filters();
$proofs = $loggedIn ? payment_proof_read_all(admin_payment_proofs_days(), $filters, 10000) : [];
$summary = $loggedIn ? payment_proof_summary(admin_payment_proofs_days(), $filters) : [];

if ($loggedIn && $action === 'export') {
    admin_payment_proofs_export_csv($proofs);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Bukti Pembayaran - Admin',
    'description' => 'Dashboard admin untuk review upload bukti pembayaran manual.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-payment-proofs-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Payment Proof</div>
                <h1>Bukti Pembayaran</h1>
                <p>Pantau bukti transfer/QRIS manual yang dikirim customer dari halaman invoice, lalu verifikasi dan update status pembayaran order.</p>
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
                    <p>Masukkan password admin untuk membuka dashboard bukti pembayaran.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php
                    $activeRange = admin_payment_proofs_range();
                    $rangeOptions = ['7'=>'7 hari','14'=>'14 hari','30'=>'30 hari','60'=>'60 hari','90'=>'90 hari','180'=>'180 hari','365'=>'1 tahun','year'=>'Pilih tahun','all'=>'Semua waktu','custom'=>'Custom tanggal'];
                ?>
                <style>
                    .admin-payment-proofs-shell .admin-proof-range-links{display:flex!important;flex-wrap:wrap!important;gap:10px!important;align-items:center!important;margin:10px 0 14px!important}.admin-payment-proofs-shell .admin-proof-range-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;padding:10px 14px!important;border:1px solid #dbeafe!important;border-radius:999px!important;background:#fff!important;color:#0f172a!important;text-decoration:none!important;font-weight:850!important;font-size:.84rem!important}.admin-payment-proofs-shell .admin-proof-range-link.is-active{background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff)!important;color:var(--admin-primary-dark)!important;border-color:rgba(15,118,110,.48)!important}.admin-payment-proofs-shell .admin-proof-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:14px!important;margin:16px 0!important}.admin-payment-proofs-shell .admin-proof-card{padding:16px!important;border:1px solid #dbeafe!important;border-radius:20px!important;background:#fff!important;box-shadow:0 12px 28px rgba(15,23,42,.06)!important}.admin-payment-proofs-shell .admin-proof-card strong{display:block!important;font-size:1.5rem!important;color:#0f172a!important}.admin-payment-proofs-shell .admin-proof-list{display:grid!important;gap:14px!important}.admin-payment-proofs-shell .admin-proof-item{padding:16px!important;border:1px solid #e2e8f0!important;border-radius:22px!important;background:#fff!important;box-shadow:0 12px 28px rgba(15,23,42,.05)!important}.admin-payment-proofs-shell .admin-proof-item__head{display:flex!important;justify-content:space-between!important;gap:12px!important;align-items:flex-start!important}.admin-payment-proofs-shell .admin-proof-item__head strong{display:block!important;font-size:1.05rem!important;color:#0f172a!important}.admin-payment-proofs-shell .admin-proof-item__head span{display:block!important;color:#64748b!important;font-size:.84rem!important}.admin-payment-proofs-shell .admin-proof-badge{display:inline-flex!important;padding:6px 10px!important;border-radius:999px!important;background:color-mix(in srgb,var(--secondary-light) 50%,#ffffff)!important;color:var(--primary)!important;font-weight:850!important;font-size:.76rem!important}.admin-payment-proofs-shell .admin-proof-body{display:grid!important;gap:6px!important;margin:12px 0!important;color:#334155!important}.admin-payment-proofs-shell .admin-proof-actions{display:flex!important;flex-wrap:wrap!important;gap:8px!important;margin:12px 0!important}.admin-payment-proofs-shell .admin-proof-actions .admin-btn--soft,.admin-payment-proofs-shell .admin-proof-actions .admin-btn--ghost{background:#fff!important;color:var(--admin-primary)!important;border:1px solid color-mix(in srgb,var(--admin-primary) 28%,#ffffff)!important;box-shadow:0 8px 18px rgba(15,23,42,.05)!important}.admin-payment-proofs-shell .admin-proof-actions .admin-btn--soft:hover,.admin-payment-proofs-shell .admin-proof-actions .admin-btn--ghost:hover{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff)!important;color:var(--admin-primary-dark)!important}.admin-payment-proofs-shell .admin-proof-file-meta{padding:10px 12px!important;border:1px solid #dbeafe!important;border-radius:14px!important;background:#f8fbff!important;color:#334155!important}.admin-payment-proofs-shell .admin-proof-file-meta strong{color:#0f172a!important}.admin-payment-proofs-shell .admin-proof-form{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:10px!important;margin-top:12px!important}.admin-payment-proofs-shell .admin-proof-form textarea{grid-column:span 4!important;min-height:72px!important}.admin-payment-proofs-shell .admin-proof-form button{justify-self:start!important}.admin-payment-proofs-shell .admin-filter-form{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:12px!important}.admin-payment-proofs-shell .admin-filter-form .wide{grid-column:1/-1!important}.admin-payment-proofs-shell input,.admin-payment-proofs-shell select,.admin-payment-proofs-shell textarea{width:100%!important;border:1px solid #dbe3ef!important;border-radius:14px!important;padding:10px 12px!important;background:#fff!important;color:#0f172a!important}@media(max-width:900px){.admin-payment-proofs-shell .admin-proof-grid,.admin-payment-proofs-shell .admin-filter-form,.admin-payment-proofs-shell .admin-proof-form{grid-template-columns:1fr!important}.admin-payment-proofs-shell .admin-proof-form textarea{grid-column:span 1!important}.admin-payment-proofs-shell .admin-proof-item__head{display:grid!important}}
                </style>

                <div class="admin-toolbar admin-page-actions">
                    <div>
                        <span class="admin-badge">Export</span>
                        <h2>Action Bukti Pembayaran</h2>
                    </div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_payment_proofs_current_url(['action' => 'export'])); ?>">Export CSV</a>
                    </div>
                </div>

                <section class="admin-card">
                    <h2>Filter Bukti Pembayaran</h2>
                    <form method="get" class="admin-filter-form">
                        <div class="wide">
                            <strong>Rentang Data</strong>
                            <div class="admin-proof-range-links">
                                <?php foreach ($rangeOptions as $value => $label): ?>
                                    <a class="admin-proof-range-link <?= $activeRange === $value ? 'is-active' : ''; ?>" href="<?= esc(admin_payment_proofs_current_url(['range'=>$value])); ?>"><?= esc($label); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <label>Tahun<input type="number" name="year" min="2024" max="2100" value="<?= esc(admin_payment_proofs_selected_year()); ?>"></label>
                        <label>Dari<input type="date" name="date_from" value="<?= esc(admin_payment_proofs_date_input('date_from')); ?>"></label>
                        <label>Sampai<input type="date" name="date_to" value="<?= esc(admin_payment_proofs_date_input('date_to')); ?>"></label>
                        <label>Status<select name="status"><option value="">Semua status</option><?php foreach (payment_proof_allowed_statuses() as $status): ?><option value="<?= esc($status); ?>" <?= admin_payment_proofs_filter('status')===$status?'selected':''; ?>><?= esc($status); ?></option><?php endforeach; ?></select></label>
                        <label>Metode<select name="payment_method"><option value="">Semua metode</option><?php foreach (payment_proof_methods() as $method): ?><option value="<?= esc($method); ?>" <?= admin_payment_proofs_filter('payment_method')===$method?'selected':''; ?>><?= esc($method); ?></option><?php endforeach; ?></select></label>
                        <label>No. Order<input type="text" name="order_ref" value="<?= esc(admin_payment_proofs_filter('order_ref')); ?>" placeholder="ORD-..."></label>
                        <label>Produk<input type="text" name="product_title" value="<?= esc(admin_payment_proofs_filter('product_title')); ?>" placeholder="Nama produk"></label>
                        <label>Search<input type="search" name="search" value="<?= esc(admin_payment_proofs_filter('search', 140)); ?>" placeholder="Nama, nomor, invoice..."></label>
                        <input type="hidden" name="range" value="<?= esc($activeRange); ?>">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan Filter</button>
                    </form>
                    <p class="admin-muted">Rentang aktif: <?= esc(admin_payment_proofs_range_label()); ?></p>
                </section>

                <div class="admin-proof-grid">
                    <div class="admin-proof-card"><span>Total Bukti</span><strong><?= esc((string)($summary['total'] ?? 0)); ?></strong></div>
                    <div class="admin-proof-card"><span>Hari Ini</span><strong><?= esc((string)($summary['today'] ?? 0)); ?></strong></div>
                    <div class="admin-proof-card"><span>Menunggu Review</span><strong><?= esc((string)($summary['pending'] ?? 0)); ?></strong></div>
                    <div class="admin-proof-card"><span>Total Nominal Dilaporkan</span><strong><?= esc(rupiah((int)($summary['amount'] ?? 0))); ?></strong></div>
                </div>

                <section class="admin-card">
                    <h2>Bukti Pembayaran Terbaru</h2>
                    <div class="admin-proof-list">
                        <?php foreach ($proofs as $proof): ?>
                            <?php
                                $fileUrl = payment_proof_file_url($proof);
                                $waPhone = order_phone_for_whatsapp((string)($proof['payer_phone'] ?? ''));
                                $linkedOrder = !empty($proof['order_id']) && function_exists('order_find_by_id') ? order_find_by_id((string)$proof['order_id']) : null;
                                $linkedPaymentStatus = is_array($linkedOrder)
                                    ? order_normalize_payment_status((string)($linkedOrder['payment_status'] ?? 'Belum Ditagih'))
                                    : 'Belum Ditagih';
                            ?>
                            <article class="admin-proof-item">
                                <div class="admin-proof-item__head">
                                    <div>
                                        <strong><?= esc((string)($proof['order_ref'] ?? '-')); ?> · <?= esc((string)($proof['invoice_number'] ?? '-')); ?></strong>
                                        <span><?= esc(date('d M Y H:i', (int)($proof['_ts'] ?? time()))); ?> · <?= esc((string)($proof['payer_name'] ?? '-')); ?></span>
                                    </div>
                                    <em class="admin-proof-badge"><?= esc((string)($proof['status'] ?? 'Menunggu Review')); ?></em>
                                </div>
                                <div class="admin-proof-body">
                                    <p><b>Produk:</b> <?= esc((string)($proof['product_title'] ?? '-')); ?></p>
                                    <p><b>Kontak:</b> <?= esc((string)($proof['payer_phone'] ?? '-')); ?><?= !empty($proof['payer_email']) ? ' · <b>Email:</b> ' . esc((string)$proof['payer_email']) : ''; ?></p>
                                    <p><b>Nominal:</b> <?= esc(rupiah((int)($proof['amount'] ?? 0))); ?> · <b>Metode:</b> <?= esc((string)($proof['payment_method'] ?? '-')); ?><?= !empty($proof['payment_channel']) ? ' · ' . esc((string)$proof['payment_channel']) : ''; ?></p>
                                    <?php if (!empty($proof['note'])): ?><p><b>Catatan customer:</b><br><?= nl2br(esc((string)$proof['note'])); ?></p><?php endif; ?>
                                    <?php if (!empty($proof['admin_note'])): ?><p><b>Catatan admin:</b><br><?= nl2br(esc((string)$proof['admin_note'])); ?></p><?php endif; ?>
                                    <?php if ($fileUrl): ?>
                                        <p class="admin-proof-file-meta"><strong>File bukti:</strong> <?= esc((string)($proof['file_original_name'] ?? basename((string)($proof['file_path'] ?? 'bukti-pembayaran')))); ?><?= !empty($proof['file_size']) ? ' · ' . esc(number_format(((int)$proof['file_size']) / 1024, 1, ',', '.')) . ' KB' : ''; ?></p>
                                    <?php else: ?>
                                        <p class="admin-proof-file-meta"><strong>File bukti:</strong> metadata file tidak tersedia. Cek record upload atau penyimpanan file.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-proof-actions">
                                    <?php if ($fileUrl): ?><a class="admin-btn admin-btn--soft" href="<?= esc($fileUrl); ?>" target="_blank" rel="noopener">Lihat Bukti Upload</a><?php endif; ?>
                                    <?php if (!empty($proof['order_id'])): ?><a class="admin-btn admin-btn--soft" href="<?= url('admin/orders?search=' . rawurlencode((string)($proof['order_ref'] ?? ''))); ?>">Buka Order</a><?php endif; ?>
                                    <?php if ($waPhone): ?><a class="admin-btn admin-btn--primary" href="<?= esc('https://wa.me/' . $waPhone . '?text=' . rawurlencode(payment_proof_whatsapp_message($proof))); ?>" target="_blank" rel="nofollow noopener">Chat WhatsApp</a><?php endif; ?>
                                </div>
                                <form method="post" class="admin-proof-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="update_payment_proof">
                                    <input type="hidden" name="id" value="<?= esc((string)($proof['id'] ?? '')); ?>">
                                    <label>Status Review<select name="status"><?php foreach (payment_proof_allowed_statuses() as $status): ?><option value="<?= esc($status); ?>" <?= ((string)($proof['status'] ?? 'Menunggu Review'))===$status?'selected':''; ?>><?= esc($status); ?></option><?php endforeach; ?></select></label>
                                    <label>Status Pembayaran Order<select name="order_payment_status"><?php foreach (order_allowed_payment_statuses() as $orderPayStatus): ?><option value="<?= esc($orderPayStatus); ?>" <?= $linkedPaymentStatus === $orderPayStatus ? 'selected' : ''; ?>><?= esc($orderPayStatus); ?></option><?php endforeach; ?></select></label>
                                    <label><input type="checkbox" name="sync_order_payment" value="1"> Sinkron ke order<small style="display:block;margin-top:.35rem;color:#64748b">Jika review dipilih Lunas atau DP Masuk, status pembayaran Order otomatis disamakan saat sinkron aktif.</small></label>
                                    <textarea name="admin_note" placeholder="Catatan review bukti pembayaran"><?= esc((string)($proof['admin_note'] ?? '')); ?></textarea>
                                    <button class="admin-btn admin-btn--primary" type="submit">Update Review</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$proofs): ?>
                            <div class="admin-card admin-empty-card"><h3>Belum ada bukti pembayaran sesuai filter.</h3><p>Bukti pembayaran akan muncul setelah customer mengupload dari halaman invoice publik.</p></div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
