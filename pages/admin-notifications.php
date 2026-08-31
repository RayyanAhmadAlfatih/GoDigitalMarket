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
    redirect_302('admin/notifications');
}

function admin_notifications_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_notifications_filter(string $key, int $max = 100): string
{
    return notification_clean((string)($_GET[$key] ?? ''), $max);
}

function admin_notifications_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));
    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_notifications_days(): int
{
    $range = admin_notifications_range();
    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }
    return max(1, min(3650, (int)$range));
}

function admin_notifications_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_notifications_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));
    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_notifications_filters(): array
{
    $range = admin_notifications_range();
    $filters = array_filter([
        'status' => admin_notifications_filter('status'),
        'transport' => admin_notifications_filter('transport'),
        'type' => admin_notifications_filter('type'),
        'target_type' => admin_notifications_filter('target_type'),
        'to' => admin_notifications_filter('to', 160),
        'search' => admin_notifications_filter('search', 160),
    ], static fn($v): bool => $v !== '' && $v !== null && $v !== false);

    if ($range === 'all') {
        $filters['_all_time'] = true;
    }
    if ($range === 'year') {
        $year = admin_notifications_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
    }
    if ($range === 'custom') {
        $from = admin_notifications_date_input('date_from');
        $to = admin_notifications_date_input('date_to');
        if ($from !== '') {
            $filters['_start_ts'] = strtotime($from . ' 00:00:00') ?: 0;
        }
        if ($to !== '') {
            $filters['_end_ts'] = strtotime($to . ' 23:59:59') ?: time();
        }
    }
    return $filters;
}

function admin_notifications_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_notifications_range(),
        'year' => admin_notifications_selected_year(),
        'date_from' => admin_notifications_date_input('date_from'),
        'date_to' => admin_notifications_date_input('date_to'),
        'status' => admin_notifications_filter('status'),
        'transport' => admin_notifications_filter('transport'),
        'type' => admin_notifications_filter('type'),
        'target_type' => admin_notifications_filter('target_type'),
        'to' => admin_notifications_filter('to', 160),
        'search' => admin_notifications_filter('search', 160),
    ], $extra);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/notifications' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_notifications_range_label(): string
{
    $range = admin_notifications_range();
    if ($range === 'all') {
        return 'Semua data email notification';
    }
    if ($range === 'year') {
        return 'Tahun ' . admin_notifications_selected_year();
    }
    if ($range === 'custom') {
        $from = admin_notifications_date_input('date_from');
        $to = admin_notifications_date_input('date_to');
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
    return admin_notifications_days() . ' hari terakhir';
}

function admin_notifications_export_csv(array $events): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="email-notifications-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['time', 'status', 'transport', 'type', 'target_type', 'target_ref', 'to', 'subject', 'error']);
    foreach ($events as $event) {
        fputcsv($out, [
            (string)($event['time'] ?? ''),
            (string)($event['status'] ?? ''),
            (string)($event['transport'] ?? ''),
            (string)($event['type'] ?? ''),
            (string)($event['target_type'] ?? ''),
            (string)($event['target_ref'] ?? ''),
            (string)($event['to'] ?? ''),
            (string)($event['subject'] ?? ''),
            (string)($event['error'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_notifications_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'notifications']);
            }
            redirect_302('admin/notifications');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'send_test_email') {
        $to = notification_clean((string)($_POST['test_email'] ?? ''), 160);
        if ($to === '') {
            $to = notification_admin_email();
        }
        $template = notification_clean((string)($_POST['test_template'] ?? 'system_test'), 80);
        [$subject, $body, $type] = notification_template_demo($template);
        if (!notification_rule_enabled('test_email', true)) {
            notification_log_rule_disabled('test_email', $to, $subject, $type, 'system', 'test');
            $ok = false;
        } else {
            $ok = notification_send_email(
                $to,
                $subject,
                $body,
                ['type' => $type, 'target_type' => 'system', 'target_ref' => 'test']
            );
        }
        redirect_302('admin/notifications?test=' . ($ok ? 'ok' : 'fail') . '&template=' . rawurlencode($template));
    }
}

$loggedIn = admin_notifications_logged_in();
$filters = admin_notifications_filters();
$events = $loggedIn ? notification_read_all(admin_notifications_days(), $filters, 20000) : [];
$summary = $loggedIn ? notification_summary(admin_notifications_days(), $filters) : [];
$rules = $loggedIn && function_exists('notification_rules_summary') ? notification_rules_summary() : [];
$templatePreviewType = notification_clean((string)($_GET['template'] ?? 'order_customer'), 80);
[$previewSubject, $previewBody, $previewLogType] = function_exists('notification_template_demo') ? notification_template_demo($templatePreviewType) : ['', '', ''];

if ($loggedIn && $action === 'export') {
    admin_notifications_export_csv($events);
}

if (($_GET['test'] ?? '') === 'ok') {
    $message = 'Test email berhasil diproses. Cek status terbaru pada tabel log di bawah.';
}
if (($_GET['test'] ?? '') === 'fail') {
    $message = 'Test email diproses namun statusnya gagal/disabled. Cek kolom error pada log terbaru.';
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Email Notification - Admin',
    'description' => 'Riwayat email sistem untuk order, form, invoice, dan tes SMTP.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<!-- Template inline notification polish -->
<style>
.admin-notifications-shell .admin-filter-card{padding:1.25rem!important;border-radius:24px!important}.admin-notifications-shell .admin-rule-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:.75rem!important;margin-top:1rem!important}.admin-notifications-shell .admin-rule-item{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:.8rem!important;padding:.85rem!important;border:1px solid #dbe7e2!important;border-radius:18px!important;background:#fff!important}.admin-notifications-shell .admin-rule-item code{font-size:.72rem!important;color:#475569!important}.admin-notifications-shell .admin-status-pill{display:inline-flex!important;align-items:center!important;border-radius:999px!important;padding:.32rem .65rem!important;font-size:.75rem!important;font-weight:900!important;background:color-mix(in srgb,var(--bg) 82%,#ffffff)!important;color:var(--admin-primary)!important;white-space:nowrap!important}.admin-notifications-shell .admin-status-pill.is-off{background:#fee2e2!important;color:#991b1b!important}.admin-notifications-shell .admin-template-preview{white-space:pre-wrap!important;background:#0f172a!important;color:#e5f8ef!important;border-radius:16px!important;padding:1rem!important;overflow:auto!important;max-height:360px!important;font-size:.84rem!important;line-height:1.6!important}.admin-notifications-shell .admin-range-chips{display:flex!important;flex-wrap:wrap!important;gap:10px!important;align-items:center!important;margin:8px 0!important}.admin-notifications-shell .admin-range-chip{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:38px!important;padding:9px 14px!important;border:1px solid #dbeafe!important;border-radius:999px!important;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;text-decoration:none!important;font-size:.84rem!important;font-weight:850!important;white-space:nowrap!important}.admin-notifications-shell .admin-range-chip:hover,.admin-notifications-shell .admin-range-chip.is-active{border-color:rgba(15,118,110,.48)!important;background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff)!important;color:var(--admin-primary-dark)!important;-webkit-text-fill-color:var(--admin-primary-dark)!important}.admin-notifications-shell .admin-filter-grid,.admin-notifications-shell .admin-test-email-form{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:1rem!important;align-items:end!important;margin-top:1rem!important}.admin-notifications-shell .admin-filter-grid label,.admin-notifications-shell .admin-test-email-form label{display:grid!important;gap:.45rem!important;color:#0f2f25!important;font-weight:800!important}.admin-notifications-shell .admin-filter-grid input,.admin-notifications-shell .admin-filter-grid select,.admin-notifications-shell .admin-test-email-form input{width:100%!important;border:1px solid #cbd5e1!important;border-radius:14px!important;padding:.82rem .95rem!important;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;font:inherit!important;min-height:46px!important}.admin-notifications-shell .admin-metric-grid,.admin-notifications-shell .admin-grid--three{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:1rem!important;margin:1.2rem 0!important}.admin-notifications-shell .admin-metric-card{display:grid!important;gap:.45rem!important;padding:1.1rem!important;border-radius:20px!important;background:#fff!important;border:1px solid #dbe7e2!important;box-shadow:0 12px 30px rgba(15,23,42,.05)!important}@media(max-width:1100px){.admin-notifications-shell .admin-filter-grid,.admin-notifications-shell .admin-test-email-form,.admin-notifications-shell .admin-metric-grid,.admin-notifications-shell .admin-grid--three{grid-template-columns:repeat(2,minmax(0,1fr))!important}}@media(max-width:680px){.admin-notifications-shell .admin-range-chips{overflow-x:auto!important;flex-wrap:nowrap!important;padding-bottom:6px!important}.admin-notifications-shell .admin-filter-grid,.admin-notifications-shell .admin-test-email-form,.admin-notifications-shell .admin-metric-grid,.admin-notifications-shell .admin-grid--three{grid-template-columns:1fr!important}}
</style>
<main id="main-content" class="admin-shell admin-notifications-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem / SMTP / Email Server</div>
                <h1>Riwayat Email</h1>
                <p>Pantau email order, form, invoice, test SMTP, dan status terkirim/gagal dari satu tempat.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-layout">
                    <div class="admin-login-copy">
                        <h2>Login Admin</h2>
                        <p>Masukkan password admin untuk membuka riwayat email sistem.</p>
                    </div>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autocomplete="current-password">
                        <?php if ($error): ?><p class="admin-error"><?= esc($error); ?></p><?php endif; ?>
                        <button class="admin-btn admin-btn--primary" type="submit">Masuk</button>
                    </form>
                </div>
            <?php else: ?>
                <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="admin-alert admin-alert--danger"><?= esc($error); ?></div><?php endif; ?>

                <div class="admin-card admin-filter-card">
                    <form method="get" class="admin-lead-filter-form">
                        <input type="hidden" name="range" value="<?= esc(admin_notifications_range()); ?>">
                        <div class="admin-range-chips" aria-label="Pilih rentang data">
                            <?php foreach (['7' => '7 hari', '14' => '14 hari', '30' => '30 hari', '60' => '60 hari', '90' => '90 hari', '180' => '180 hari', '365' => '1 tahun', 'year' => 'Pilih tahun', 'all' => 'Semua waktu', 'custom' => 'Custom tanggal'] as $value => $label): ?>
                                <a class="admin-range-chip <?= admin_notifications_range() === $value ? 'is-active' : ''; ?>" href="<?= esc(admin_notifications_current_url(['range' => $value])); ?>"><?= esc($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <div class="admin-filter-grid">
                            <label>Tahun
                                <input type="number" name="year" min="2020" max="2100" value="<?= esc(admin_notifications_selected_year()); ?>">
                            </label>
                            <label>Dari Tanggal
                                <input type="date" name="date_from" value="<?= esc(admin_notifications_date_input('date_from')); ?>">
                            </label>
                            <label>Sampai Tanggal
                                <input type="date" name="date_to" value="<?= esc(admin_notifications_date_input('date_to')); ?>">
                            </label>
                            <label>Status
                                <select name="status">
                                    <option value="">Semua status</option>
                                    <?php foreach (['sent', 'failed', 'logged', 'disabled'] as $status): ?>
                                        <option value="<?= esc($status); ?>" <?= admin_notifications_filter('status') === $status ? 'selected' : ''; ?>><?= esc($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Transport
                                <select name="transport">
                                    <option value="">Semua transport</option>
                                    <?php foreach (['log', 'mail', 'smtp'] as $transport): ?>
                                        <option value="<?= esc($transport); ?>" <?= admin_notifications_filter('transport') === $transport ? 'selected' : ''; ?>><?= esc($transport); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Tipe
                                <input type="text" name="type" placeholder="order_customer, inquiry_admin..." value="<?= esc(admin_notifications_filter('type')); ?>">
                            </label>
                            <label>Recipient
                                <input type="text" name="to" placeholder="email tujuan" value="<?= esc(admin_notifications_filter('to', 160)); ?>">
                            </label>
                            <label>Pencarian
                                <input type="text" name="search" placeholder="subject, ref, error..." value="<?= esc(admin_notifications_filter('search', 160)); ?>">
                            </label>
                        </div>
                        <div class="admin-row-actions">
                            <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                            <a class="admin-btn admin-btn--ghost" href="<?= url('admin/notifications'); ?>">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="admin-alert admin-alert--info">
                    <strong>Status:</strong> <?= notification_enabled() ? 'Email otomatis aktif' : 'Email otomatis belum aktif'; ?> ·
                    <strong>Transport:</strong> <?= esc(notification_transport()); ?> ·
                    <strong>Admin To:</strong> <?= esc(notification_admin_email() ?: 'belum diisi'); ?> ·
                    <strong>Rentang:</strong> <?= esc(admin_notifications_range_label()); ?>
                </div>

                <div class="admin-card admin-filter-card">
                    <h2>Aturan Notifikasi Email</h2>
                    <p class="admin-muted">Sistem ini menambahkan rule per jenis notifikasi. Semua rule bisa dikontrol dari <code>.env</code>, jadi admin bisa menentukan email mana yang aktif tanpa mengubah kode.</p>
                    <div class="admin-rule-grid">
                        <?php foreach ($rules as $rule => $item): ?>
                            <div class="admin-rule-item">
                                <div>
                                    <strong><?= esc((string)$item['label']); ?></strong><br>
                                    <code><?= esc((string)$item['env_key']); ?></code>
                                </div>
                                <span class="admin-status-pill <?= !empty($item['enabled']) ? '' : 'is-off'; ?>"><?= !empty($item['enabled']) ? 'Aktif' : 'Nonaktif'; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="admin-card admin-filter-card">
                    <h2>Preview Template Email</h2>
                    <p class="admin-muted">Pilih tipe template melalui parameter <code>?template=order_customer</code> atau gunakan dropdown test email di bawah. Preview ini membantu mengecek bahasa email sebelum dipakai live.</p>
                    <p><strong>Subject:</strong> <?= esc($previewSubject); ?> <small>(<?= esc($previewLogType); ?>)</small></p>
                    <pre class="admin-template-preview"><?= esc($previewBody); ?></pre>
                </div>

                <div class="admin-card admin-filter-card">
                    <h2>Test Email</h2>
                    <p class="admin-muted">Gunakan ini untuk cek konfigurasi. Kalau transport masih <strong>log</strong>, test hanya dicatat ke log tanpa dikirim keluar.</p>
                    <form method="post" class="admin-filter-grid">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="send_test_email">
                        <label>Email Tujuan
                            <input type="email" name="test_email" placeholder="kosongkan untuk EMAIL_ADMIN_TO" value="">
                        </label>
                        <label>Template Test
                            <select name="test_template">
                                <?php foreach (['system_test' => 'Test Sistem', 'order_customer' => 'Konfirmasi Order Customer', 'order_admin' => 'Order Baru ke Admin', 'inquiry_customer' => 'Konfirmasi Inquiry Customer', 'inquiry_admin' => 'Inquiry Baru ke Admin', 'order_status_link' => 'Link Status Order', 'invoice_link' => 'Link Invoice'] as $tpl => $label): ?>
                                    <option value="<?= esc($tpl); ?>"><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>&nbsp;<button class="admin-btn admin-btn--primary" type="submit">Kirim Test Email</button></label>
                    </form>
                </div>

                <div class="admin-metric-grid">
                    <div class="admin-metric-card"><span>Total Event</span><strong><?= esc((string)($summary['total'] ?? 0)); ?></strong><small>Sesuai filter aktif</small></div>
                    <div class="admin-metric-card"><span>Hari Ini</span><strong><?= esc((string)($summary['today'] ?? 0)); ?></strong><small>Email event hari ini</small></div>
                    <div class="admin-metric-card"><span>Terkirim</span><strong><?= esc((string)($summary['sent'] ?? 0)); ?></strong><small>Status sent</small></div>
                    <div class="admin-metric-card"><span>Gagal</span><strong><?= esc((string)($summary['failed'] ?? 0)); ?></strong><small>Perlu cek konfigurasi</small></div>
                    <div class="admin-metric-card"><span>Log Mode</span><strong><?= esc((string)($summary['logged'] ?? 0)); ?></strong><small>Dicatat tanpa dikirim</small></div>
                    <div class="admin-metric-card"><span>Disabled</span><strong><?= esc((string)($summary['disabled'] ?? 0)); ?></strong><small>Tracking log saat fitur off</small></div>
                </div>

                <div class="admin-grid admin-grid--three">
                    <div class="admin-card"><h3>Status Email</h3><?php foreach (($summary['by_status'] ?? []) as $key => $count): ?><p><strong><?= esc((string)$key); ?></strong> · <?= esc((string)$count); ?></p><?php endforeach; ?><?php if (empty($summary['by_status'])): ?><p>Belum ada data.</p><?php endif; ?></div>
                    <div class="admin-card"><h3>Tipe Email</h3><?php foreach (($summary['by_type'] ?? []) as $key => $count): ?><p><strong><?= esc((string)$key); ?></strong> · <?= esc((string)$count); ?></p><?php endforeach; ?><?php if (empty($summary['by_type'])): ?><p>Belum ada data.</p><?php endif; ?></div>
                    <div class="admin-card"><h3>Transport</h3><?php foreach (($summary['by_transport'] ?? []) as $key => $count): ?><p><strong><?= esc((string)$key); ?></strong> · <?= esc((string)$count); ?></p><?php endforeach; ?><?php if (empty($summary['by_transport'])): ?><p>Belum ada data.</p><?php endif; ?></div>
                </div>

                <section class="admin-card admin-table-card">
                    <div class="admin-toolbar">
                        <div>
                            <h2>Log Email Terbaru</h2>
                            <p>Log ini membantu cek apakah notifikasi order/inquiry terkirim, gagal, disabled, atau hanya tersimpan di mode log.</p>
                        </div>
                    </div>
                    <div class="admin-order-cards">
                        <?php foreach ($events as $event): ?>
                            <article class="admin-order-card">
                                <div class="admin-order-card__head">
                                    <div>
                                        <strong><?= esc((string)($event['subject'] ?? '-')); ?></strong>
                                        <span><?= esc(date('d M Y H:i', (int)($event['_ts'] ?? time()))); ?> · <?= esc((string)($event['to'] ?? '-')); ?></span>
                                    </div>
                                    <em><?= esc((string)($event['status'] ?? '-')); ?></em>
                                </div>
                                <div class="admin-order-card__body">
                                    <p><b>Transport:</b> <?= esc((string)($event['transport'] ?? '-')); ?> · <b>Tipe:</b> <?= esc((string)($event['type'] ?? '-')); ?></p>
                                    <p><b>Target:</b> <?= esc((string)($event['target_type'] ?? '-')); ?> · <?= esc((string)($event['target_ref'] ?? '-')); ?></p>
                                    <?php if (!empty($event['error'])): ?><p><b>Info/Error:</b><br><?= nl2br(esc((string)$event['error'])); ?></p><?php endif; ?>
                                    <?php if (!empty($event['message'])): ?><details><summary>Lihat isi email</summary><pre><?= esc((string)$event['message']); ?></pre></details><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$events): ?>
                            <div class="admin-card admin-empty-card">
                                <h3>Belum ada email event sesuai filter.</h3>
                                <p>Aktifkan email notification, submit inquiry/order, atau kirim test email untuk membuat event pertama.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
