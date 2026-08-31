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

function admin_transaction_audit_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_transaction_audit_filter(string $key, int $max = 100): string
{
    return transaction_clean((string)($_GET[$key] ?? ''), $max);
}

function admin_transaction_audit_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));
    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_transaction_audit_days(): int
{
    $range = admin_transaction_audit_range();
    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }
    return max(1, min(3650, (int)$range));
}

function admin_transaction_audit_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_transaction_audit_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));
    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_transaction_audit_filters(): array
{
    $range = admin_transaction_audit_range();
    $filters = array_filter([
        'severity' => admin_transaction_audit_filter('severity', 40),
        'code' => admin_transaction_audit_filter('code', 80),
        'search' => admin_transaction_audit_filter('search', 140),
    ], static fn($v): bool => $v !== '' && $v !== null && $v !== false);

    if ($range === 'all') {
        $filters['_all_time'] = true;
    }
    if ($range === 'year') {
        $year = admin_transaction_audit_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
        $filters['_year'] = $year;
    }
    if ($range === 'custom') {
        $from = admin_transaction_audit_date_input('date_from');
        $to = admin_transaction_audit_date_input('date_to');
        if ($from !== '') {
            $filters['_start_ts'] = strtotime($from . ' 00:00:00') ?: 0;
        }
        if ($to !== '') {
            $filters['_end_ts'] = strtotime($to . ' 23:59:59') ?: time();
        }
    }
    return $filters;
}

function admin_transaction_audit_event_filters(): array
{
    $base = admin_transaction_audit_filters();
    unset($base['severity'], $base['code']);
    $base['category'] = admin_transaction_audit_filter('event_category', 80);
    $base['action'] = admin_transaction_audit_filter('event_action', 80);
    return array_filter($base, static fn($v): bool => $v !== '' && $v !== null && $v !== false);
}

function admin_transaction_audit_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_transaction_audit_range(),
        'year' => admin_transaction_audit_selected_year(),
        'date_from' => admin_transaction_audit_date_input('date_from'),
        'date_to' => admin_transaction_audit_date_input('date_to'),
        'severity' => admin_transaction_audit_filter('severity', 40),
        'code' => admin_transaction_audit_filter('code', 80),
        'search' => admin_transaction_audit_filter('search', 140),
        'event_category' => admin_transaction_audit_filter('event_category', 80),
        'event_action' => admin_transaction_audit_filter('event_action', 80),
    ], $extra);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/transaction-audit' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_transaction_audit_range_label(): string
{
    $range = admin_transaction_audit_range();
    if ($range === 'all') {
        return 'Semua data transaksi sejak tersedia';
    }
    if ($range === 'year') {
        return 'Tahun ' . admin_transaction_audit_selected_year();
    }
    if ($range === 'custom') {
        $from = admin_transaction_audit_date_input('date_from');
        $to = admin_transaction_audit_date_input('date_to');
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
    return admin_transaction_audit_days() . ' hari terakhir';
}

function admin_transaction_filter_issues(array $issues): array
{
    $severity = admin_transaction_audit_filter('severity', 40);
    $code = admin_transaction_audit_filter('code', 80);
    $search = strtolower(admin_transaction_audit_filter('search', 140));
    return array_values(array_filter($issues, static function (array $issue) use ($severity, $code, $search): bool {
        if ($severity !== '' && (string)($issue['severity'] ?? '') !== $severity) {
            return false;
        }
        if ($code !== '' && (string)($issue['code'] ?? '') !== $code) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array_map('strval', $issue)));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }));
}

function admin_transaction_export_issues(array $issues): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="transaction-audit-issues-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['severity', 'code', 'title', 'description', 'order_ref', 'invoice_number', 'customer', 'product', 'status', 'payment_status']);
    foreach ($issues as $issue) {
        fputcsv($out, [
            (string)($issue['severity'] ?? ''),
            (string)($issue['code'] ?? ''),
            (string)($issue['title'] ?? ''),
            (string)($issue['description'] ?? ''),
            (string)($issue['order_ref'] ?? ''),
            (string)($issue['invoice_number'] ?? ''),
            (string)($issue['customer'] ?? ''),
            (string)($issue['product'] ?? ''),
            (string)($issue['status'] ?? ''),
            (string)($issue['payment_status'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

function admin_transaction_export_events(array $events): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="transaction-audit-events-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['time', 'category', 'action', 'target_type', 'target_ref', 'target_id', 'note', 'page_path']);
    foreach ($events as $event) {
        fputcsv($out, [
            (string)($event['time'] ?? ''),
            (string)($event['category'] ?? ''),
            (string)($event['action'] ?? ''),
            (string)($event['target_type'] ?? ''),
            (string)($event['target_ref'] ?? ''),
            (string)($event['target_id'] ?? ''),
            (string)($event['note'] ?? ''),
            (string)($event['page_path'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

if ($action === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/transaction-audit');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_transaction_audit_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'transaction_audit']);
            }
            redirect_302('admin/transaction-audit');
        }
        $error = 'Password admin salah.';
    }
}

$loggedIn = admin_transaction_audit_logged_in();
$filters = $loggedIn ? admin_transaction_audit_filters() : [];
$orders = $loggedIn ? order_read_all(admin_transaction_audit_days(), $filters, 50000) : [];
$report = $loggedIn ? transaction_readiness_report($orders) : ['score' => 0, 'issues' => [], 'counts' => [], 'total_orders' => 0];
$issues = $loggedIn ? admin_transaction_filter_issues((array)($report['issues'] ?? [])) : [];
$events = $loggedIn ? transaction_read_events(admin_transaction_audit_days(), admin_transaction_audit_event_filters(), 5000) : [];
$flowChecklist = $loggedIn ? transaction_flow_checklist($orders) : [];
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

if ($loggedIn && $action === 'export_issues') {
    admin_transaction_export_issues($issues);
}
if ($loggedIn && $action === 'export_events') {
    admin_transaction_export_events($events);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Audit Transaksi - Admin',
    'description' => 'Dashboard audit kesiapan transaksi, invoice manual, payment proof, dan readiness payment automation.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-transaction-audit-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Pemeriksaan Transaksi</div>
                <h1>Kesiapan Pembayaran Otomatis</h1>
                <p>Cek alur checkout, order, invoice, bukti pembayaran, dan pengingat sebelum mengaktifkan pembayaran otomatis.</p>
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
                    <p>Masukkan password admin untuk membuka audit transaksi.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <style>
                    .admin-transaction-audit-shell .tx-range-field{grid-column:1/-1!important;display:grid!important;gap:10px!important}.admin-transaction-audit-shell .tx-range-links{display:flex!important;flex-wrap:wrap!important;gap:9px!important}.admin-transaction-audit-shell .tx-range-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;padding:9px 13px!important;border-radius:999px!important;border:1px solid #cbd5e1!important;background:#fff!important;color:#0f172a!important;text-decoration:none!important;font-weight:850!important;font-size:.82rem!important}.admin-transaction-audit-shell .tx-range-link.is-active{background:var(--admin-primary)!important;border-color:var(--admin-primary)!important;color:#fff!important}.admin-transaction-audit-shell .tx-note{grid-column:1/-1!important;padding:11px 13px!important;border-radius:15px!important;background:var(--admin-soft)!important;border:1px solid var(--border)!important;color:var(--admin-primary-dark)!important}.admin-transaction-audit-shell .tx-stats{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:12px!important;margin:18px 0!important}.admin-transaction-audit-shell .tx-stat{display:grid!important;gap:8px!important;padding:16px!important;border-radius:20px!important;border:1px solid #e2e8f0!important;background:#fff!important;box-shadow:0 12px 34px rgba(15,23,42,.06)!important}.admin-transaction-audit-shell .tx-stat span{color:#64748b!important;font-size:.82rem!important;font-weight:800!important}.admin-transaction-audit-shell .tx-stat strong{font-size:1.55rem!important;color:#0f172a!important}.admin-transaction-audit-shell .tx-score strong{color:var(--admin-primary)!important}.admin-transaction-audit-shell .tx-panels{display:grid!important;grid-template-columns:1fr 1fr!important;gap:14px!important;margin:18px 0!important}.admin-transaction-audit-shell .tx-checklist{display:grid!important;gap:9px!important}.admin-transaction-audit-shell .tx-check{display:grid!important;grid-template-columns:28px 1fr!important;gap:10px!important;align-items:flex-start!important;padding:10px 12px!important;border-radius:15px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important}.admin-transaction-audit-shell .tx-check i{display:grid!important;place-items:center!important;width:24px!important;height:24px!important;border-radius:999px!important;background:#fee2e2!important;color:#991b1b!important;font-style:normal!important;font-weight:900!important}.admin-transaction-audit-shell .tx-check.is-done i{background:#dcfce7!important;color:var(--primary)!important}.admin-transaction-audit-shell .tx-check strong{display:block!important;color:#0f172a!important}.admin-transaction-audit-shell .tx-check small{display:block!important;color:#64748b!important;margin-top:2px!important}.admin-transaction-audit-shell .tx-rank{display:grid!important;gap:8px!important}.admin-transaction-audit-shell .tx-rank div{display:flex!important;justify-content:space-between!important;gap:10px!important;padding:10px 12px!important;border-radius:14px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important;color:#334155!important}.admin-transaction-audit-shell .tx-issues,.admin-transaction-audit-shell .tx-events{display:grid!important;gap:12px!important}.admin-transaction-audit-shell .tx-item{display:grid!important;gap:8px!important;padding:15px!important;border-radius:20px!important;border:1px solid #e2e8f0!important;background:#fff!important;box-shadow:0 12px 34px rgba(15,23,42,.06)!important}.admin-transaction-audit-shell .tx-item-head{display:flex!important;justify-content:space-between!important;gap:12px!important;align-items:flex-start!important}.admin-transaction-audit-shell .tx-item-head strong{display:block!important;color:#0f172a!important}.admin-transaction-audit-shell .tx-item-head span{display:block!important;color:#64748b!important;font-size:.84rem!important;margin-top:2px!important}.admin-transaction-audit-shell .tx-sev{display:inline-flex!important;align-items:center!important;padding:6px 10px!important;border-radius:999px!important;font-size:.75rem!important;font-weight:900!important;white-space:nowrap!important}.admin-transaction-audit-shell .tx-sev--critical,.admin-transaction-audit-shell .tx-sev--high{background:#fef2f2!important;border:1px solid #fecaca!important;color:#991b1b!important}.admin-transaction-audit-shell .tx-sev--medium{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff)!important;border:1px solid color-mix(in srgb,var(--admin-primary) 22%,#ffffff)!important;color:var(--admin-primary-dark)!important}.admin-transaction-audit-shell .tx-sev--low{background:#eff6ff!important;border:1px solid #bfdbfe!important;color:#1d4ed8!important}.admin-transaction-audit-shell .tx-meta{display:flex!important;flex-wrap:wrap!important;gap:7px!important}.admin-transaction-audit-shell .tx-meta span{display:inline-flex!important;border-radius:999px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important;color:#475569!important;padding:5px 9px!important;font-size:.77rem!important;font-weight:800!important}.admin-transaction-audit-shell .tx-matrix{width:100%!important;border-collapse:collapse!important;overflow:hidden!important;border-radius:16px!important}.admin-transaction-audit-shell .tx-matrix th,.admin-transaction-audit-shell .tx-matrix td{padding:10px!important;border-bottom:1px solid #e2e8f0!important;text-align:left!important;font-size:.84rem!important}.admin-transaction-audit-shell .tx-matrix th{background:var(--primary-dark)!important;color:#fff!important}.admin-transaction-audit-shell .tx-warning-box{padding:14px!important;border-radius:18px!important;background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff)!important;border:1px solid color-mix(in srgb,var(--admin-primary) 22%,#ffffff)!important;color:var(--admin-primary-dark)!important}.admin-transaction-audit-shell .admin-empty-card{text-align:center!important;padding:22px!important}@media(max-width:1000px){.admin-transaction-audit-shell .tx-stats,.admin-transaction-audit-shell .tx-panels{grid-template-columns:1fr 1fr!important}}@media(max-width:680px){.admin-transaction-audit-shell .tx-stats,.admin-transaction-audit-shell .tx-panels{grid-template-columns:1fr!important}.admin-transaction-audit-shell .tx-range-links{overflow-x:auto!important;flex-wrap:nowrap!important;padding-bottom:6px!important}.admin-transaction-audit-shell .tx-item-head{display:grid!important}}
                </style>

                <div class="admin-toolbar admin-page-actions">
                    <div>
                        <span class="admin-badge">Export</span>
                        <h2>Action Audit</h2>
                    </div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_transaction_audit_current_url(['action' => 'export_issues'])); ?>">Export Issue CSV</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_transaction_audit_current_url(['action' => 'export_events'])); ?>">Export Audit Log</a>
                    </div>
                </div>

                <form class="admin-card admin-order-filter" method="get">
                    <input type="hidden" name="range" value="<?= esc(admin_transaction_audit_range()); ?>">
                    <div class="tx-range-field">
                        <span class="admin-lead-range-title" id="txRangeLegend">Rentang Data Audit</span>
                        <div class="tx-range-links" role="group" aria-labelledby="txRangeLegend">
                            <?php foreach ($rangeOptions as $rangeValue => $rangeLabel): ?>
                                <a class="tx-range-link <?= admin_transaction_audit_range() === $rangeValue ? 'is-active' : ''; ?>" href="<?= esc(admin_transaction_audit_current_url(['range' => $rangeValue])); ?>"><?= esc($rangeLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tx-note">Rentang aktif: <strong><?= esc(admin_transaction_audit_range_label()); ?></strong></div>
                    <label>Tahun
                        <input type="number" name="year" min="2020" max="<?= esc(date('Y')); ?>" value="<?= esc(admin_transaction_audit_selected_year()); ?>">
                    </label>
                    <label>Dari Tanggal
                        <input type="date" name="date_from" value="<?= esc(admin_transaction_audit_date_input('date_from')); ?>">
                    </label>
                    <label>Sampai Tanggal
                        <input type="date" name="date_to" value="<?= esc(admin_transaction_audit_date_input('date_to')); ?>">
                    </label>
                    <label>Severity
                        <select name="severity">
                            <option value="">Semua severity</option>
                            <?php foreach (['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= admin_transaction_audit_filter('severity', 40) === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Kode Issue
                        <input name="code" value="<?= esc(admin_transaction_audit_filter('code', 80)); ?>" placeholder="invoice_overdue_unpaid...">
                    </label>
                    <label>Pencarian
                        <input name="search" value="<?= esc(admin_transaction_audit_filter('search', 140)); ?>" placeholder="Order, invoice, customer...">
                    </label>
                    <label>Kategori Log
                        <input name="event_category" value="<?= esc(admin_transaction_audit_filter('event_category', 80)); ?>" placeholder="order, payment-proof...">
                    </label>
                    <label>Aksi Log
                        <input name="event_action" value="<?= esc(admin_transaction_audit_filter('event_action', 80)); ?>" placeholder="order_status_updated...">
                    </label>
                    <div class="admin-lead-filter__actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn" href="<?= esc(url('admin/transaction-audit')); ?>">Reset</a>
                    </div>
                </form>

                <?php
                    $counts = (array)($report['counts'] ?? []);
                    $score = (int)($report['score'] ?? 0);
                    $issueCounts = transaction_count_by((array)($report['issues'] ?? []), 'code', 6);
                    $eventActionCounts = transaction_count_by($events, 'action', 6);
                ?>
                <div class="tx-stats">
                    <div class="tx-stat tx-score"><span>Skor Kesiapan</span><strong><?= esc((string)$score); ?>/100</strong><small>Semakin tinggi semakin siap dipakai.</small></div>
                    <div class="tx-stat"><span>Order Diaudit</span><strong><?= esc((string)count($orders)); ?></strong><small>Sesuai filter aktif.</small></div>
                    <div class="tx-stat"><span>High/Critical</span><strong><?= esc((string)(($counts['critical'] ?? 0) + ($counts['high'] ?? 0))); ?></strong><small>Prioritas dicek admin.</small></div>
                    <div class="tx-stat"><span>Total Catatan</span><strong><?= esc((string)count((array)($report['issues'] ?? []))); ?></strong><small>Sebelum filter issue.</small></div>
                    <div class="tx-stat"><span>Audit Log</span><strong><?= esc((string)count($events)); ?></strong><small>Action admin tercatat.</small></div>
                </div>

                <div class="tx-panels">
                    <section class="admin-card admin-lead-panel">
                        <h2>Checklist Alur</h2>
                        <div class="tx-checklist">
                            <?php foreach ($flowChecklist as $check): ?>
                                <div class="tx-check <?= !empty($check['done']) ? 'is-done' : ''; ?>">
                                    <i><?= !empty($check['done']) ? '✓' : '!'; ?></i>
                                    <div><strong><?= esc((string)$check['label']); ?></strong><small><?= esc((string)$check['note']); ?></small></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Catatan Teratas</h2>
                        <div class="tx-rank">
                            <?php foreach ($issueCounts as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (!$issueCounts): ?><p class="admin-muted">Belum ada issue yang terdeteksi di rentang ini.</p><?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="tx-panels">
                    <section class="admin-card admin-lead-panel">
                        <h2>Guardrail Status Pembayaran</h2>
                        <p>Sistem ini menambahkan validasi ringan agar perubahan status pembayaran sensitif tidak dilakukan tanpa catatan.</p>
                        <table class="tx-matrix">
                            <thead><tr><th>Kondisi</th><th>Aturan</th></tr></thead>
                            <tbody>
                                <tr><td>Refund</td><td>Wajib ada catatan pembayaran.</td></tr>
                                <tr><td>Lunas/DP diturunkan</td><td>Wajib ada catatan koreksi.</td></tr>
                                <tr><td>Batal/Spam dengan DP/Lunas</td><td>Wajib ada catatan admin/pembayaran.</td></tr>
                                <tr><td>Selesai tapi belum lunas</td><td>Ditandai sebagai warning audit.</td></tr>
                            </tbody>
                        </table>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Catatan Pembayaran Otomatis</h2>
                        <div class="tx-warning-box">
                            <?php foreach (transaction_gateway_readiness_notes() as $note): ?>
                                <p>• <?= esc($note); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <section class="admin-card admin-lead-panel">
                    <h2>Catatan Audit Transaksi</h2>
                    <p class="admin-muted">Catatan ini bukan berarti website error. Ini adalah checklist agar alur transaksi manual lebih siap sebelum memakai pembayaran otomatis.</p>
                    <div class="tx-issues">
                        <?php foreach (array_slice($issues, 0, 120) as $issue): ?>
                            <article class="tx-item">
                                <div class="tx-item-head">
                                    <div>
                                        <strong><?= esc((string)($issue['title'] ?? 'Issue')); ?></strong>
                                        <span><?= esc((string)($issue['description'] ?? '')); ?></span>
                                    </div>
                                    <span class="tx-sev tx-sev--<?= esc((string)($issue['severity'] ?? 'low')); ?>"><?= esc(strtoupper((string)($issue['severity'] ?? 'low'))); ?></span>
                                </div>
                                <div class="tx-meta">
                                    <span><?= esc((string)($issue['code'] ?? '')); ?></span>
                                    <span><?= esc((string)($issue['order_ref'] ?? '')); ?></span>
                                    <span><?= esc((string)($issue['invoice_number'] ?? '')); ?></span>
                                    <span><?= esc((string)($issue['status'] ?? '')); ?></span>
                                    <span><?= esc((string)($issue['payment_status'] ?? '')); ?></span>
                                </div>
                                <?php if (!empty($issue['order_id'])): ?><p><a href="<?= esc(url('admin/orders?search=' . rawurlencode((string)$issue['order_ref']))); ?>">Buka order terkait</a></p><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$issues): ?><div class="admin-card admin-empty-card"><h3>Tidak ada issue sesuai filter.</h3><p>Flow transaksi terlihat aman pada rentang/filter aktif.</p></div><?php endif; ?>
                    </div>
                </section>

                <div class="tx-panels">
                    <section class="admin-card admin-lead-panel">
                        <h2>Riwayat Aktivitas Terbaru</h2>
                        <div class="tx-events">
                            <?php foreach (array_slice($events, 0, 60) as $event): ?>
                                <article class="tx-item">
                                    <div class="tx-item-head">
                                        <div>
                                            <strong><?= esc((string)($event['action'] ?? 'action')); ?></strong>
                                            <span><?= esc(date('d M Y H:i', (int)($event['_ts'] ?? time()))); ?> · <?= esc((string)($event['category'] ?? 'transaction')); ?></span>
                                        </div>
                                        <span class="tx-sev tx-sev--low"><?= esc((string)($event['target_ref'] ?? '-')); ?></span>
                                    </div>
                                    <?php if (!empty($event['note'])): ?><p><?= nl2br(esc((string)$event['note'])); ?></p><?php endif; ?>
                                    <div class="tx-meta"><span><?= esc((string)($event['target_type'] ?? '')); ?></span><span><?= esc((string)($event['page_path'] ?? '')); ?></span></div>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$events): ?><p class="admin-muted">Belum ada action log transaksi. Log akan terisi saat admin update order, review bukti pembayaran, atau mencatat reminder.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Aksi Teratas</h2>
                        <div class="tx-rank">
                            <?php foreach ($eventActionCounts as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (!$eventActionCounts): ?><p class="admin-muted">Belum ada action log untuk dihitung.</p><?php endif; ?>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
