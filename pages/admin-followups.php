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
    redirect_302('admin/followups');
}

function admin_followups_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_followups_filter(string $key, int $max = 80): string
{
    return crm_clean((string)($_GET[$key] ?? ''), $max);
}

function admin_followups_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));
    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_followups_days(): int
{
    $range = admin_followups_range();
    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }
    return max(1, min(3650, (int)$range));
}

function admin_followups_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_followups_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));
    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_followups_filters(): array
{
    $range = admin_followups_range();
    $filters = array_filter([
        'target_type' => admin_followups_filter('target_type'),
        'priority' => admin_followups_filter('priority'),
        'outcome' => admin_followups_filter('outcome'),
        'due' => admin_followups_filter('due'),
        'search' => admin_followups_filter('search', 120),
    ], static fn($v): bool => $v !== '' && $v !== null && $v !== false);

    if ($range === 'all') {
        $filters['_all_time'] = true;
    }
    if ($range === 'year') {
        $year = admin_followups_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
    }
    if ($range === 'custom') {
        $from = admin_followups_date_input('date_from');
        $to = admin_followups_date_input('date_to');
        if ($from !== '') {
            $filters['_start_ts'] = strtotime($from . ' 00:00:00') ?: 0;
        }
        if ($to !== '') {
            $filters['_end_ts'] = strtotime($to . ' 23:59:59') ?: time();
        }
    }
    return $filters;
}

function admin_followups_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_followups_range(),
        'year' => admin_followups_selected_year(),
        'date_from' => admin_followups_date_input('date_from'),
        'date_to' => admin_followups_date_input('date_to'),
        'target_type' => admin_followups_filter('target_type'),
        'priority' => admin_followups_filter('priority'),
        'outcome' => admin_followups_filter('outcome'),
        'due' => admin_followups_filter('due'),
        'search' => admin_followups_filter('search', 120),
    ], $extra);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/followups' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_followups_range_label(): string
{
    $range = admin_followups_range();
    if ($range === 'all') {
        return 'Semua data follow-up';
    }
    if ($range === 'year') {
        return 'Tahun ' . admin_followups_selected_year();
    }
    if ($range === 'custom') {
        $from = admin_followups_date_input('date_from');
        $to = admin_followups_date_input('date_to');
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
    return admin_followups_days() . ' hari terakhir';
}

function admin_followups_export_csv(array $events): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="follow-up-history-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['time', 'target_type', 'target_ref', 'target_name', 'phone', 'email', 'subject', 'priority', 'outcome', 'next_followup_date', 'next_followup_time', 'note']);
    foreach ($events as $event) {
        fputcsv($out, [
            (string)($event['time'] ?? ''),
            (string)($event['target_type'] ?? ''),
            (string)($event['target_ref'] ?? ''),
            (string)($event['target_name'] ?? ''),
            (string)($event['phone'] ?? ''),
            (string)($event['email'] ?? ''),
            (string)($event['subject'] ?? ''),
            (string)($event['priority'] ?? ''),
            (string)($event['outcome'] ?? ''),
            (string)($event['next_followup_date'] ?? ''),
            (string)($event['next_followup_time'] ?? ''),
            (string)($event['note'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_followups_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'followups']);
            }
            redirect_302('admin/followups');
        }
        $error = 'Password admin salah.';
    }
}

$loggedIn = admin_followups_logged_in();
$filters = admin_followups_filters();
$events = $loggedIn ? crm_read_all(admin_followups_days(), $filters, 20000) : [];
$summary = $loggedIn ? crm_summary(admin_followups_days(), $filters) : [];

if ($loggedIn && $action === 'export') {
    admin_followups_export_csv($events);
}

if (!empty($_GET['logged'])) {
    $message = 'Catatan follow-up berhasil disimpan.';
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Follow-up CRM - Admin',
    'description' => 'Dashboard follow-up dan reminder CRM ringan untuk order dan inquiry.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<!-- Template inline followup polish -->
<style>
.admin-followups-shell .admin-lead-range-links{display:flex!important;flex-wrap:wrap!important;gap:10px!important;align-items:center!important;margin-top:8px!important;margin-bottom:8px!important}.admin-followups-shell .admin-lead-range-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:38px!important;padding:9px 14px!important;border:1px solid #dbeafe!important;border-radius:999px!important;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;text-decoration:none!important;font-size:.84rem!important;font-weight:850!important;white-space:nowrap!important}.admin-followups-shell .admin-lead-range-link:hover,.admin-followups-shell .admin-lead-range-link.is-active{border-color:rgba(15,118,110,.48)!important;background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff)!important;color:var(--admin-primary-dark)!important;-webkit-text-fill-color:var(--admin-primary-dark)!important}.admin-followups-shell .admin-followup-filter{padding:1.25rem!important;gap:1rem!important}
</style>
<main id="main-content" class="admin-shell admin-followups-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Mini CRM</div>
                <h1>Follow-up History & Reminder</h1>
                <p>Pantau riwayat follow-up, lead panas, reminder hari ini, dan calon pembeli yang perlu ditindaklanjuti.</p>
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
                    <p>Masukkan password admin untuk membuka dashboard follow-up.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-toolbar admin-page-actions">
                    <div>
                        <span class="admin-badge">Export</span>
                        <h2>Action Follow-up</h2>
                    </div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_followups_current_url(['action' => 'export'])); ?>">Export CSV</a>
                    </div>
                </div>
                <?php
                    $activeRange = admin_followups_range();
                    $rangeOptions = [
                        '7' => '7 hari', '14' => '14 hari', '30' => '30 hari', '60' => '60 hari',
                        '90' => '90 hari', '180' => '180 hari', '365' => '1 tahun', 'year' => 'Pilih tahun',
                        'all' => 'Semua waktu', 'custom' => 'Custom tanggal',
                    ];
                ?>
                <form method="get" class="admin-card admin-followup-filter">
                    <div class="admin-followup-range-field">
                        <span>Rentang Data</span>
                        <div class="admin-lead-range-links">
                            <?php foreach ($rangeOptions as $value => $label): ?>
                                <a class="admin-lead-range-link <?= $activeRange === $value ? 'is-active' : ''; ?>" href="<?= esc(admin_followups_current_url(['range' => $value])); ?>"><?= esc($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <small>Rentang aktif: <strong><?= esc(admin_followups_range_label()); ?></strong></small>
                    </div>
                    <label>Tahun
                        <input type="number" name="year" min="2020" max="<?= esc(date('Y')); ?>" value="<?= esc(admin_followups_selected_year()); ?>">
                    </label>
                    <label>Dari Tanggal
                        <input type="date" name="date_from" value="<?= esc(admin_followups_date_input('date_from')); ?>">
                    </label>
                    <label>Sampai Tanggal
                        <input type="date" name="date_to" value="<?= esc(admin_followups_date_input('date_to')); ?>">
                    </label>
                    <label>Tipe Lead
                        <select name="target_type">
                            <option value="">Semua tipe</option>
                            <option value="order" <?= admin_followups_filter('target_type') === 'order' ? 'selected' : ''; ?>>Order</option>
                            <option value="inquiry" <?= admin_followups_filter('target_type') === 'inquiry' ? 'selected' : ''; ?>>Inquiry</option>
                        </select>
                    </label>
                    <label>Prioritas
                        <select name="priority">
                            <option value="">Semua prioritas</option>
                            <?php foreach (array_keys(crm_priorities()) as $priority): ?>
                                <option value="<?= esc($priority); ?>" <?= admin_followups_filter('priority') === $priority ? 'selected' : ''; ?>><?= esc($priority); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Hasil Follow-up
                        <select name="outcome">
                            <option value="">Semua hasil</option>
                            <?php foreach (array_keys(crm_outcomes()) as $outcome): ?>
                                <option value="<?= esc($outcome); ?>" <?= admin_followups_filter('outcome') === $outcome ? 'selected' : ''; ?>><?= esc($outcome); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Reminder
                        <select name="due">
                            <option value="">Semua reminder</option>
                            <option value="today" <?= admin_followups_filter('due') === 'today' ? 'selected' : ''; ?>>Hari ini</option>
                            <option value="overdue" <?= admin_followups_filter('due') === 'overdue' ? 'selected' : ''; ?>>Terlambat</option>
                            <option value="upcoming" <?= admin_followups_filter('due') === 'upcoming' ? 'selected' : ''; ?>>Akan datang</option>
                            <option value="scheduled" <?= admin_followups_filter('due') === 'scheduled' ? 'selected' : ''; ?>>Terjadwal</option>
                        </select>
                    </label>
                    <label>Pencarian
                        <input name="search" value="<?= esc(admin_followups_filter('search', 120)); ?>" placeholder="Nama, nomor, order, catatan...">
                    </label>
                    <div class="admin-lead-filter__actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn" href="<?= url('admin/followups'); ?>">Reset</a>
                    </div>
                </form>

                <div class="admin-lead-stats admin-followup-stats">
                    <div class="admin-lead-stat-card"><span>Total Follow-up</span><strong><?= esc((string)($summary['total'] ?? 0)); ?></strong><small>Sesuai filter aktif</small></div>
                    <div class="admin-lead-stat-card"><span>Hari Ini</span><strong><?= esc((string)($summary['today'] ?? 0)); ?></strong><small>Reminder jatuh hari ini</small></div>
                    <div class="admin-lead-stat-card"><span>Terlambat</span><strong><?= esc((string)($summary['overdue'] ?? 0)); ?></strong><small>Reminder perlu dikejar</small></div>
                    <div class="admin-lead-stat-card"><span>Lead Panas</span><strong><?= esc((string)($summary['hot'] ?? 0)); ?></strong><small>Prioritas tinggi/sangat panas</small></div>
                </div>

                <div class="admin-lead-grid-secondary admin-followup-grid">
                    <section class="admin-card admin-lead-panel">
                        <h2>Prioritas Lead</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($summary['by_priority'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_priority'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Hasil Follow-up</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($summary['by_outcome'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_outcome'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Reminder Terdekat</h2>
                        <div class="admin-followup-mini-list">
                            <?php foreach (($summary['scheduled'] ?? []) as $item): ?>
                                <div><strong><?= esc((string)($item['target_name'] ?? '-')); ?></strong><span><?= esc(crm_next_label($item)); ?> · <?= esc((string)($item['priority'] ?? 'Normal')); ?></span></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['scheduled'])): ?><p class="admin-muted">Belum ada reminder terjadwal.</p><?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="admin-card admin-lead-panel admin-followup-list">
                    <div class="admin-lead-panel__head">
                        <div>
                            <h2>Riwayat Follow-up</h2>
                            <p>Catatan follow-up dari order dan inquiry. Gunakan data ini untuk menentukan prioritas follow-up berikutnya.</p>
                        </div>
                    </div>

                    <div class="admin-followup-cards">
                        <?php foreach ($events as $event): ?>
                            <?php
                                $dueClass = '';
                                $dueTs = (int)($event['_due_ts'] ?? 0);
                                if ($dueTs > 0) {
                                    if ($dueTs < (strtotime(date('Y-m-d 00:00:00')) ?: time())) {
                                        $dueClass = ' is-overdue';
                                    } elseif (date('Y-m-d', $dueTs) === date('Y-m-d')) {
                                        $dueClass = ' is-today';
                                    }
                                }
                            ?>
                            <article class="admin-followup-card admin-followup-card--<?= esc(crm_status_class((string)($event['priority'] ?? 'normal'))); ?><?= esc($dueClass); ?>">
                                <div class="admin-followup-card__head">
                                    <div>
                                        <strong><?= esc((string)($event['target_name'] ?? '-')); ?></strong>
                                        <span><?= esc(date('d M Y H:i', (int)($event['_ts'] ?? time()))); ?> · <?= esc(strtoupper((string)($event['target_type'] ?? ''))); ?></span>
                                    </div>
                                    <em><?= esc((string)($event['priority'] ?? 'Normal')); ?></em>
                                </div>
                                <div class="admin-followup-card__body">
                                    <p><b>Referensi:</b> <?= esc((string)($event['target_ref'] ?? '-')); ?> · <b>Hasil:</b> <?= esc((string)($event['outcome'] ?? '-')); ?></p>
                                    <p><b>Kontak:</b> <?= esc((string)($event['phone'] ?? '-')); ?><?= !empty($event['email']) ? ' · <b>Email:</b> ' . esc((string)$event['email']) : ''; ?></p>
                                    <?php if (!empty($event['subject'])): ?><p><b>Konteks:</b> <?= esc((string)$event['subject']); ?></p><?php endif; ?>
                                    <?php if (!empty($event['note'])): ?><p><b>Catatan:</b><br><?= nl2br(esc((string)$event['note'])); ?></p><?php endif; ?>
                                    <p><b>Reminder:</b> <?= esc(crm_next_label($event)); ?></p>
                                </div>
                                <div class="admin-followup-card__actions">
                                    <?php if (($event['target_type'] ?? '') === 'order'): ?>
                                        <a class="admin-btn admin-btn--ghost" href="<?= esc(url('admin/orders?search=' . rawurlencode((string)($event['target_ref'] ?? $event['target_name'] ?? '')))); ?>">Buka Order</a>
                                    <?php elseif (($event['target_type'] ?? '') === 'inquiry'): ?>
                                        <a class="admin-btn admin-btn--ghost" href="<?= esc(url('admin/inquiries?search=' . rawurlencode((string)($event['target_ref'] ?? $event['target_name'] ?? '')))); ?>">Buka Inquiry</a>
                                    <?php endif; ?>
                                    <?php if (!empty($event['phone'])): ?>
                                        <?php $waPhone = function_exists('order_phone_for_whatsapp') ? order_phone_for_whatsapp((string)$event['phone']) : preg_replace('/\D+/', '', (string)$event['phone']); ?>
                                        <a class="admin-btn admin-btn--primary" href="<?= esc('https://wa.me/' . $waPhone . '?text=' . rawurlencode('Halo ' . (string)($event['target_name'] ?? '') . ', kami menindaklanjuti kebutuhan Anda di ' . SITE_NAME . '. Apakah masih bisa kami bantu?')); ?>" target="_blank" rel="nofollow noopener">Chat WA</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$events): ?>
                            <div class="admin-card admin-empty-card">
                                <h3>Belum ada riwayat follow-up sesuai filter.</h3>
                                <p>Tambahkan catatan follow-up dari halaman order atau inquiry. Reminder yang dijadwalkan akan muncul di dashboard ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
