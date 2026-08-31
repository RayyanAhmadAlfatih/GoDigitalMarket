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
    redirect_302('admin/payment-reminders');
}

function admin_payment_reminders_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_payment_reminders_filter(string $key, int $max = 80): string
{
    return payment_reminder_clean((string)($_GET[$key] ?? ''), $max);
}

function admin_payment_reminders_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));
    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_payment_reminders_days(): int
{
    $range = admin_payment_reminders_range();
    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }
    return max(1, min(3650, (int)$range));
}

function admin_payment_reminders_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_payment_reminders_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));
    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_payment_reminders_filters(): array
{
    $range = admin_payment_reminders_range();
    $filters = array_filter([
        'status' => admin_payment_reminders_filter('status'),
        'payment_status' => admin_payment_reminders_filter('payment_status'),
        'payment_method' => admin_payment_reminders_filter('payment_method'),
        'location' => admin_payment_reminders_filter('location'),
        'product_title' => admin_payment_reminders_filter('product_title'),
        'search' => admin_payment_reminders_filter('search', 120),
        'stage' => admin_payment_reminders_filter('stage', 60),
    ], static fn($v): bool => $v !== '' && $v !== null && $v !== false);

    if ((string)($_GET['include_completed'] ?? '') !== '') {
        $filters['include_completed'] = true;
    }
    if ($range === 'all') {
        $filters['_all_time'] = true;
    }
    if ($range === 'year') {
        $year = admin_payment_reminders_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
        $filters['_year'] = $year;
    }
    if ($range === 'custom') {
        $from = admin_payment_reminders_date_input('date_from');
        $to = admin_payment_reminders_date_input('date_to');
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

function admin_payment_reminders_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_payment_reminders_range(),
        'year' => admin_payment_reminders_selected_year(),
        'date_from' => admin_payment_reminders_date_input('date_from'),
        'date_to' => admin_payment_reminders_date_input('date_to'),
        'status' => admin_payment_reminders_filter('status'),
        'payment_status' => admin_payment_reminders_filter('payment_status'),
        'payment_method' => admin_payment_reminders_filter('payment_method'),
        'location' => admin_payment_reminders_filter('location'),
        'product_title' => admin_payment_reminders_filter('product_title'),
        'search' => admin_payment_reminders_filter('search', 120),
        'stage' => admin_payment_reminders_filter('stage', 60),
        'include_completed' => (string)($_GET['include_completed'] ?? '') !== '' ? '1' : null,
    ], $extra);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/payment-reminders' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_payment_reminders_range_label(): string
{
    $range = admin_payment_reminders_range();
    if ($range === 'all') {
        return 'Semua invoice/order sejak data tersedia';
    }
    if ($range === 'year') {
        return 'Tahun ' . admin_payment_reminders_selected_year();
    }
    if ($range === 'custom') {
        $from = admin_payment_reminders_date_input('date_from');
        $to = admin_payment_reminders_date_input('date_to');
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
    return admin_payment_reminders_days() . ' hari terakhir';
}

function admin_payment_reminders_export_csv(array $items): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="payment-reminders-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['order_ref', 'invoice_number', 'stage', 'priority', 'due_date', 'days_to_due', 'days_overdue', 'payment_status', 'name', 'phone', 'email', 'product_title', 'invoice_total', 'last_reminder_at', 'last_reminder_channel', 'last_reminder_status']);
    foreach ($items as $item) {
        $meta = (array)($item['_reminder'] ?? []);
        $last = (array)($item['_last_reminder'] ?? []);
        fputcsv($out, [
            order_public_reference($item),
            order_invoice_number($item),
            (string)($meta['stage'] ?? ''),
            (string)($meta['priority'] ?? ''),
            (string)($meta['due_date'] ?? ''),
            (string)($meta['days_to_due'] ?? ''),
            (string)($meta['days_overdue'] ?? ''),
            (string)($item['payment_status'] ?? ''),
            (string)($item['name'] ?? ''),
            (string)($item['phone'] ?? ''),
            (string)($item['email'] ?? ''),
            (string)($item['product_title'] ?? ''),
            (string)order_invoice_total($item),
            (string)($last['time'] ?? ''),
            (string)($last['channel'] ?? ''),
            (string)($last['status'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_payment_reminders_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'payment_reminders']);
            }
            redirect_302('admin/payment-reminders');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'log_reminder') {
        $id = payment_reminder_clean((string)($_POST['id'] ?? ''), 80);
        $channel = payment_reminder_clean((string)($_POST['channel'] ?? 'whatsapp'), 30) ?: 'whatsapp';
        $order = $id !== '' && function_exists('order_find_by_id') ? order_find_by_id($id) : null;
        if (!$order) {
            $error = 'Order tidak ditemukan.';
        } else {
            $meta = payment_reminder_meta($order);
            payment_reminder_store_event([
                'order_id' => (string)($order['id'] ?? ''),
                'order_ref' => order_public_reference($order),
                'invoice_number' => order_invoice_number($order),
                'stage' => (string)($meta['stage'] ?? ''),
                'stage_key' => (string)($meta['stage_key'] ?? ''),
                'channel' => $channel,
                'status' => 'sent_manual',
                'note' => payment_reminder_multiline_clean((string)($_POST['note'] ?? ''), 500),
            ]);
            if (function_exists('conversion_store_event')) {
                conversion_store_event(['source' => 'Admin Payment Reminder', 'type' => 'payment-reminder', 'channel' => $channel, 'intent' => 'payment-reminder', 'label' => order_public_reference($order)]);
            }
            if (function_exists('transaction_store_event')) {
                transaction_store_event([
                    'category' => 'payment-reminder',
                    'action' => 'payment_reminder_logged',
                    'target_type' => 'order',
                    'target_id' => (string)($order['id'] ?? ''),
                    'target_ref' => order_public_reference($order),
                    'after' => ['stage' => (string)($meta['stage'] ?? ''), 'channel' => $channel],
                    'note' => payment_reminder_multiline_clean((string)($_POST['note'] ?? ''), 500),
                ]);
            }
            redirect_302('admin/payment-reminders?reminder_logged=1');
        }
    } elseif (($_POST['form_action'] ?? '') === 'send_email_reminder') {
        $id = payment_reminder_clean((string)($_POST['id'] ?? ''), 80);
        $order = $id !== '' && function_exists('order_find_by_id') ? order_find_by_id($id) : null;
        if (!$order) {
            $error = 'Order tidak ditemukan untuk kirim email reminder.';
        } elseif (payment_reminder_send_email($order)) {
            redirect_302('admin/payment-reminders?email_sent=1');
        } else {
            $error = 'Email reminder belum terkirim. Cek email customer, konfigurasi email, atau log notifikasi.';
        }
    }
}

$loggedIn = admin_payment_reminders_logged_in();
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
$stageOptions = [
    '' => 'Semua tahap reminder',
    'h_plus_1' => 'H+1 sejak invoice',
    'h_plus_2' => 'H+2 sejak invoice',
    'h_plus_3' => 'H+3 sejak invoice',
    'due_tomorrow' => 'H-1 jatuh tempo',
    'due_today' => 'Jatuh tempo hari ini',
    'expired' => 'Invoice kadaluarsa',
];

$filters = $loggedIn ? admin_payment_reminders_filters() : [];
$items = $loggedIn ? payment_reminder_candidates(admin_payment_reminders_days(), $filters, 10000) : [];
$summary = $loggedIn ? payment_reminder_summary($items) : [];
$events = $loggedIn ? payment_reminder_read_events(0, ['_all_time' => true], 80) : [];

if ($loggedIn && $action === 'export') {
    admin_payment_reminders_export_csv($items);
}

if (isset($_GET['reminder_logged'])) {
    $message = 'Reminder berhasil dicatat.';
}
if (isset($_GET['email_sent'])) {
    $message = 'Email reminder berhasil diproses. Cek log email untuk status terkirim/log/disabled.';
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Reminder Pembayaran - Admin',
    'description' => 'Dashboard reminder pembayaran manual untuk admin.',
    'robots' => 'noindex, nofollow',
]);

$pageTitle = 'Reminder Pembayaran';
require COMPONENTS_PATH . '/layout/head.php';
require COMPONENTS_PATH . '/layout/header.php';
?>

<main class="admin-page admin-lead-dashboard admin-payment-reminder-page">
    <section class="admin-lead-shell admin-payment-reminder-shell">
        <div class="container">
            <div class="admin-lead-header">
                <div>
                    <span class="eyebrow">Payment Reminder</span>
                    <h1>Reminder Pembayaran Manual</h1>
                    <p>Pantau invoice H+1, H+2, mendekati jatuh tempo, sampai kadaluarsa. Cocok untuk follow-up transfer/QRIS manual sebelum masuk payment gateway otomatis.</p>
                </div>
            </div>

            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <form method="post" class="admin-card admin-login-card">
                    <?= csrf_field(); ?>
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka reminder pembayaran.</p>
                    <label>Password Admin
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>
                    <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                </form>
            <?php else: ?>
                <style>
                    .admin-payment-reminder-shell .admin-reminder-range-field{grid-column:1/-1!important;display:grid!important;gap:10px!important;margin-bottom:4px!important}.admin-payment-reminder-shell .admin-reminder-range-links{display:flex!important;flex-wrap:wrap!important;gap:8px!important}.admin-payment-reminder-shell .admin-reminder-range-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;padding:8px 12px!important;border-radius:999px!important;border:1px solid #cbd5e1!important;background:#fff!important;color:#0f172a!important;text-decoration:none!important;font-weight:850!important;font-size:.82rem!important}.admin-payment-reminder-shell .admin-reminder-range-link.is-active{background:var(--admin-primary)!important;border-color:var(--admin-primary)!important;color:#fff!important}.admin-payment-reminder-shell .admin-reminder-note{grid-column:1/-1!important;padding:10px 12px!important;border-radius:14px!important;background:var(--admin-soft)!important;border:1px solid var(--border)!important;color:var(--admin-primary-dark)!important}.admin-payment-reminder-shell .admin-reminder-stats{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:12px!important;margin:18px 0!important}.admin-payment-reminder-shell .admin-reminder-card{display:grid!important;gap:10px!important;padding:16px!important;border-radius:20px!important;border:1px solid #e2e8f0!important;background:#fff!important;box-shadow:0 12px 34px rgba(15,23,42,.06)!important}.admin-payment-reminder-shell .admin-reminder-card span{color:#64748b!important;font-size:.82rem!important;font-weight:800!important}.admin-payment-reminder-shell .admin-reminder-card strong{font-size:1.65rem!important;color:#0f172a!important}.admin-payment-reminder-shell .admin-reminder-panels{display:grid!important;grid-template-columns:1.1fr .9fr!important;gap:14px!important;margin:18px 0!important}.admin-payment-reminder-shell .admin-reminder-rank{display:grid!important;gap:8px!important}.admin-payment-reminder-shell .admin-reminder-rank div{display:flex!important;justify-content:space-between!important;gap:10px!important;padding:9px 10px!important;border-radius:13px!important;background:#f8fafc!important;color:#334155!important}.admin-payment-reminder-shell .admin-reminder-list{display:grid!important;gap:14px!important}.admin-payment-reminder-shell .admin-reminder-item{display:grid!important;gap:12px!important;padding:16px!important;border:1px solid #e2e8f0!important;border-radius:22px!important;background:#fff!important;box-shadow:0 14px 40px rgba(15,23,42,.07)!important}.admin-payment-reminder-shell .admin-reminder-item__head{display:flex!important;justify-content:space-between!important;gap:14px!important;align-items:flex-start!important}.admin-payment-reminder-shell .admin-reminder-item__head strong{display:block!important;color:#0f172a!important;font-size:1rem!important}.admin-payment-reminder-shell .admin-reminder-item__head span{display:block!important;color:#64748b!important;font-size:.83rem!important;margin-top:2px!important}.admin-payment-reminder-shell .admin-reminder-stage{display:inline-flex!important;align-items:center!important;padding:6px 10px!important;border-radius:999px!important;background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff)!important;border:1px solid color-mix(in srgb,var(--admin-primary) 22%,#ffffff)!important;color:var(--admin-primary-dark)!important;font-size:.76rem!important;font-weight:900!important;white-space:nowrap!important}.admin-payment-reminder-shell .admin-reminder-stage--hot{background:#fef2f2!important;border-color:#fecaca!important;color:#991b1b!important}.admin-payment-reminder-shell .admin-reminder-meta{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:8px!important}.admin-payment-reminder-shell .admin-reminder-meta div{padding:10px!important;border-radius:14px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important;color:#64748b!important;font-size:.78rem!important}.admin-payment-reminder-shell .admin-reminder-meta strong{display:block!important;color:#0f172a!important;font-size:.92rem!important;margin-top:2px!important}.admin-payment-reminder-shell .admin-reminder-actions{display:flex!important;flex-wrap:wrap!important;gap:8px!important;align-items:center!important}.admin-payment-reminder-shell .admin-reminder-actions form{display:inline-flex!important;gap:8px!important;margin:0!important}.admin-payment-reminder-shell .admin-reminder-message{grid-column:1/-1!important}.admin-payment-reminder-shell .admin-reminder-message textarea{width:100%!important;min-height:110px!important;border:1px solid #cbd5e1!important;border-radius:14px!important;padding:10px!important;color:#0f172a!important;background:#fff!important}.admin-payment-reminder-shell .admin-reminder-history{display:grid!important;gap:8px!important}.admin-payment-reminder-shell .admin-reminder-history div{padding:10px 12px!important;border-radius:14px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important;font-size:.84rem!important;color:#475569!important}.admin-payment-reminder-shell .admin-reminder-history strong{color:#0f172a!important}.admin-payment-reminder-shell .admin-empty-card{padding:22px!important;text-align:center!important}@media(max-width:1000px){.admin-payment-reminder-shell .admin-reminder-stats,.admin-payment-reminder-shell .admin-reminder-panels,.admin-payment-reminder-shell .admin-reminder-meta{grid-template-columns:1fr 1fr!important}}@media(max-width:680px){.admin-payment-reminder-shell .admin-reminder-stats,.admin-payment-reminder-shell .admin-reminder-panels,.admin-payment-reminder-shell .admin-reminder-meta{grid-template-columns:1fr!important}.admin-payment-reminder-shell .admin-reminder-range-links{overflow-x:auto!important;flex-wrap:nowrap!important;padding-bottom:6px!important}.admin-payment-reminder-shell .admin-reminder-item__head{display:grid!important}.admin-payment-reminder-shell .admin-reminder-actions form{width:100%!important}.admin-payment-reminder-shell .admin-reminder-actions .admin-btn{width:100%!important;justify-content:center!important}}
                </style>

                <form class="admin-card admin-order-filter" method="get">
                    <input type="hidden" name="range" value="<?= esc(admin_payment_reminders_range()); ?>">
                    <div class="admin-reminder-range-field">
                        <span class="admin-lead-range-title" id="reminderRangeLegend">Rentang Data Order/Invoice</span>
                        <div class="admin-reminder-range-links" role="group" aria-labelledby="reminderRangeLegend">
                            <?php foreach ($rangeOptions as $rangeValue => $rangeLabel): ?>
                                <a class="admin-reminder-range-link <?= admin_payment_reminders_range() === $rangeValue ? 'is-active' : ''; ?>" href="<?= esc(admin_payment_reminders_current_url(['range' => $rangeValue])); ?>"><?= esc($rangeLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-reminder-note">Rentang aktif: <strong><?= esc(admin_payment_reminders_range_label()); ?></strong></div>
                    <label>Tahun
                        <input type="number" name="year" min="2020" max="<?= esc(date('Y')); ?>" value="<?= esc(admin_payment_reminders_selected_year()); ?>">
                    </label>
                    <label>Dari Tanggal
                        <input type="date" name="date_from" value="<?= esc(admin_payment_reminders_date_input('date_from')); ?>">
                    </label>
                    <label>Sampai Tanggal
                        <input type="date" name="date_to" value="<?= esc(admin_payment_reminders_date_input('date_to')); ?>">
                    </label>
                    <label>Tahap Reminder
                        <select name="stage">
                            <?php foreach ($stageOptions as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= admin_payment_reminders_filter('stage', 60) === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Status Pembayaran
                        <select name="payment_status">
                            <option value="">Semua status pembayaran</option>
                            <?php foreach (order_allowed_payment_statuses() as $status): ?>
                                <option value="<?= esc($status); ?>" <?= admin_payment_reminders_filter('payment_status') === $status ? 'selected' : ''; ?>><?= esc($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Produk
                        <input name="product_title" value="<?= esc(admin_payment_reminders_filter('product_title')); ?>" placeholder="Nama produk...">
                    </label>
                    <label>Lokasi
                        <input name="location" value="<?= esc(admin_payment_reminders_filter('location')); ?>" placeholder="Area layanan atau kota...">
                    </label>
                    <label>Pencarian
                        <input name="search" value="<?= esc(admin_payment_reminders_filter('search', 120)); ?>" placeholder="Nama, nomor, invoice, order...">
                    </label>
                    <label class="admin-checkline">
                        <input type="checkbox" name="include_completed" value="1" <?= (string)($_GET['include_completed'] ?? '') !== '' ? 'checked' : ''; ?>> tampilkan yang DP/Lunas juga
                    </label>
                    <div class="admin-lead-filter__actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn" href="<?= esc(url('admin/payment-reminders')); ?>">Reset</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_payment_reminders_current_url(['action' => 'export'])); ?>">Export CSV</a>
                    </div>
                </form>

                <div class="admin-reminder-stats">
                    <div class="admin-reminder-card"><span>Total Kandidat</span><strong><?= esc((string)($summary['total'] ?? 0)); ?></strong><small>Invoice/order perlu dipantau</small></div>
                    <div class="admin-reminder-card"><span>H+ Reminder</span><strong><?= esc((string)($summary['h_plus'] ?? 0)); ?></strong><small>H+1, H+2, dst sejak invoice</small></div>
                    <div class="admin-reminder-card"><span>H-1</span><strong><?= esc((string)($summary['due_tomorrow'] ?? 0)); ?></strong><small>Jatuh tempo besok</small></div>
                    <div class="admin-reminder-card"><span>Jatuh Tempo</span><strong><?= esc((string)($summary['due_today'] ?? 0)); ?></strong><small>Harus difollow-up hari ini</small></div>
                    <div class="admin-reminder-card"><span>Kadaluarsa</span><strong><?= esc((string)($summary['expired'] ?? 0)); ?></strong><small>Lewat batas invoice</small></div>
                </div>

                <div class="admin-reminder-panels">
                    <section class="admin-card admin-lead-panel">
                        <h2>Tahap Reminder</h2>
                        <div class="admin-reminder-rank">
                            <?php foreach (($summary['by_stage'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_stage'])): ?><p class="admin-muted">Belum ada invoice yang perlu reminder.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Status Pembayaran</h2>
                        <div class="admin-reminder-rank">
                            <?php foreach (($summary['by_payment_status'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_payment_status'])): ?><p class="admin-muted">Belum ada data pembayaran.</p><?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="admin-card admin-lead-panel">
                    <div class="admin-lead-panel__head">
                        <div>
                            <h2>Daftar Reminder Pembayaran</h2>
                            <p>Gunakan tombol WhatsApp/email untuk follow-up. Setelah follow-up manual, klik catat reminder agar ada history.</p>
                        </div>
                    </div>
                    <div class="admin-reminder-list">
                        <?php foreach ($items as $item): ?>
                            <?php
                                $meta = (array)($item['_reminder'] ?? []);
                                $last = (array)($item['_last_reminder'] ?? []);
                                $waPhone = function_exists('order_phone_for_whatsapp') ? order_phone_for_whatsapp((string)($item['phone'] ?? '')) : preg_replace('/\D+/', '', (string)($item['phone'] ?? ''));
                                $waMessage = payment_reminder_whatsapp_message($item);
                                $hotClass = in_array((string)($meta['priority'] ?? ''), ['Tinggi', 'Sangat Panas'], true) ? ' admin-reminder-stage--hot' : '';
                            ?>
                            <article class="admin-reminder-item">
                                <div class="admin-reminder-item__head">
                                    <div>
                                        <strong><?= esc(order_invoice_number($item)); ?> · <?= esc(order_public_reference($item)); ?></strong>
                                        <span><?= esc((string)($item['product_title'] ?? 'Pesanan')); ?> · <?= esc((string)($item['name'] ?? '-')); ?></span>
                                    </div>
                                    <em class="admin-reminder-stage<?= esc($hotClass); ?>"><?= esc((string)($meta['stage'] ?? 'Reminder')); ?></em>
                                </div>
                                <div class="admin-reminder-meta">
                                    <div>Jatuh Tempo<strong><?= esc((string)($meta['due_label'] ?? '-')); ?></strong></div>
                                    <div>Usia Invoice<strong>H+<?= esc((string)($meta['age_days'] ?? 0)); ?></strong></div>
                                    <div>Status Payment<strong><?= esc((string)($item['payment_status'] ?? 'Belum Ditagih')); ?></strong></div>
                                    <div>Nominal<strong><?= esc(order_invoice_total($item) > 0 ? rupiah(order_invoice_total($item)) : 'Belum ditentukan'); ?></strong></div>
                                </div>
                                <div class="admin-reminder-meta">
                                    <div>Customer<strong><?= esc((string)($item['name'] ?? '-')); ?></strong></div>
                                    <div>WhatsApp<strong><?= esc((string)($item['phone'] ?? '-')); ?></strong></div>
                                    <div>Email<strong><?= esc((string)($item['email'] ?? '-')); ?></strong></div>
                                    <div>Reminder Terakhir<strong><?= !empty($last) ? esc(date('d M Y H:i', (int)($last['_ts'] ?? time()))) : 'Belum ada'; ?></strong></div>
                                </div>
                                <div class="admin-reminder-message">
                                    <label>Preview Pesan WhatsApp Reminder
                                        <textarea readonly><?= esc($waMessage); ?></textarea>
                                    </label>
                                </div>
                                <div class="admin-reminder-actions">
                                    <?php if ($waPhone !== ''): ?>
                                        <a class="admin-btn admin-btn--primary" target="_blank" rel="nofollow noopener" href="<?= esc('https://wa.me/' . $waPhone . '?text=' . rawurlencode($waMessage)); ?>" <?= function_exists('conversion_link_attrs') ? conversion_link_attrs(['source' => 'Admin Payment Reminder', 'type' => 'whatsapp-payment-reminder', 'channel' => 'whatsapp', 'intent' => 'payment-reminder', 'label' => order_public_reference($item)]) : ''; ?>>Chat WA Reminder</a>
                                        <form method="post">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="log_reminder">
                                            <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                            <input type="hidden" name="channel" value="whatsapp">
                                            <button class="admin-btn admin-btn--ghost" type="submit">Catat WA Sudah Dikirim</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (filter_var((string)($item['email'] ?? ''), FILTER_VALIDATE_EMAIL)): ?>
                                        <form method="post">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="send_email_reminder">
                                            <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                            <button class="admin-btn admin-btn--ghost" type="submit">Kirim Email Reminder</button>
                                        </form>
                                    <?php endif; ?>
                                    <a class="admin-btn admin-btn--ghost" href="<?= esc(order_public_invoice_url($item)); ?>" target="_blank" rel="nofollow noopener">Lihat Invoice</a>
                                    <a class="admin-btn admin-btn--ghost" href="<?= esc(url('admin/orders?range=all&search=' . rawurlencode(order_public_reference($item)))); ?>">Buka Order</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <div class="admin-card admin-empty-card">
                                <h3>Belum ada invoice yang perlu reminder.</h3>
                                <p>Reminder akan muncul jika ada order/invoice manual dengan status pembayaran belum selesai. Coba cek rentang data atau aktifkan “tampilkan DP/Lunas juga”.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="admin-card admin-lead-panel">
                    <h2>Riwayat Reminder Terakhir</h2>
                    <div class="admin-reminder-history">
                        <?php foreach ($events as $event): ?>
                            <div><strong><?= esc((string)($event['order_ref'] ?? '-')); ?></strong> · <?= esc((string)($event['channel'] ?? '-')); ?> · <?= esc((string)($event['status'] ?? '-')); ?><br><span><?= esc(date('d M Y H:i', (int)($event['_ts'] ?? time()))); ?> · <?= esc((string)($event['stage'] ?? '')); ?></span></div>
                        <?php endforeach; ?>
                        <?php if (!$events): ?><p class="admin-muted">Belum ada riwayat reminder.</p><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require COMPONENTS_PATH . '/layout/footer.php'; ?>
