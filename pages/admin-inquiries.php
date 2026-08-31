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
    redirect_302('admin/inquiries');
}

function admin_inquiries_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_inquiries_filter(string $key, int $max = 80): string
{
    return inquiry_clean((string)($_GET[$key] ?? ''), $max);
}

function admin_inquiries_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));

    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }

    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];

    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_inquiries_days(): int
{
    $range = admin_inquiries_range();

    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }

    return max(1, min(3650, (int)$range));
}

function admin_inquiries_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_inquiries_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));

    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_inquiries_filters(): array
{
    $range = admin_inquiries_range();
    $filters = array_filter([
        'status' => admin_inquiries_filter('status'),
        'source' => admin_inquiries_filter('source'),
        'category' => admin_inquiries_filter('category'),
        'location' => admin_inquiries_filter('location'),
        'need' => admin_inquiries_filter('need'),
        'search' => admin_inquiries_filter('search', 120),
    ], static fn($v): bool => $v !== '' && $v !== null && $v !== false);

    if ($range === 'all') {
        $filters['_all_time'] = true;
    }

    if ($range === 'year') {
        $year = admin_inquiries_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
        $filters['_year'] = $year;
    }

    if ($range === 'custom') {
        $from = admin_inquiries_date_input('date_from');
        $to = admin_inquiries_date_input('date_to');

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

function admin_inquiries_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_inquiries_range(),
        'year' => admin_inquiries_selected_year(),
        'date_from' => admin_inquiries_date_input('date_from'),
        'date_to' => admin_inquiries_date_input('date_to'),
        'status' => admin_inquiries_filter('status'),
        'source' => admin_inquiries_filter('source'),
        'category' => admin_inquiries_filter('category'),
        'location' => admin_inquiries_filter('location'),
        'need' => admin_inquiries_filter('need'),
        'search' => admin_inquiries_filter('search', 120),
    ], $extra);

    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/inquiries' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_inquiries_range_label(): string
{
    $range = admin_inquiries_range();

    if ($range === 'all') {
        return 'Semua data sejak form lead aktif';
    }

    if ($range === 'year') {
        return 'Tahun ' . admin_inquiries_selected_year();
    }

    if ($range === 'custom') {
        $from = admin_inquiries_date_input('date_from');
        $to = admin_inquiries_date_input('date_to');

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

    return admin_inquiries_days() . ' hari terakhir';
}

function admin_inquiries_export_csv(array $inquiries): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="form-inquiries-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['time', 'status', 'name', 'phone', 'email', 'need', 'location', 'source', 'category', 'intent', 'item_title', 'page_path', 'message']);

    foreach ($inquiries as $item) {
        fputcsv($out, [
            (string)($item['time'] ?? ''),
            (string)($item['status'] ?? ''),
            (string)($item['name'] ?? ''),
            (string)($item['phone'] ?? ''),
            (string)($item['email'] ?? ''),
            (string)($item['need'] ?? ''),
            (string)($item['location'] ?? ''),
            (string)($item['source'] ?? ''),
            (string)($item['category'] ?? ''),
            (string)($item['intent'] ?? ''),
            (string)($item['item_title'] ?? ''),
            (string)($item['page_path'] ?? ''),
            (string)($item['message'] ?? ''),
        ]);
    }

    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();

    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_inquiries_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'inquiries']);
            }
            redirect_302('admin/inquiries');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'update_status') {
        $ok = inquiry_update_status((string)($_POST['id'] ?? ''), (string)($_POST['status'] ?? ''), (string)($_POST['note'] ?? ''));
        if ($ok) {
            redirect_302('admin/inquiries?updated=1');
        }
        $error = 'Status inquiry belum bisa diperbarui.';
    } elseif (($_POST['form_action'] ?? '') === 'add_followup') {
        $ok = crm_store_followup([
            'target_type' => 'inquiry',
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
            'source' => 'admin-inquiries',
        ]);
        if ($ok) {
            redirect_302('admin/inquiries?followup_logged=1');
        }
        $error = 'Catatan follow-up belum bisa disimpan. Pastikan catatan atau hasil follow-up sudah diisi.';
    }
}

$loggedIn = admin_inquiries_logged_in();
$filters = admin_inquiries_filters();
$inquiries = $loggedIn ? inquiry_read_all(admin_inquiries_days(), $filters, 10000) : [];
$summary = $loggedIn ? inquiry_summary(admin_inquiries_days(), $filters) : [];

if ($loggedIn && $action === 'export') {
    admin_inquiries_export_csv($inquiries);
}

if (!empty($_GET['updated'])) {
    $message = 'Status inquiry berhasil diperbarui.';
}
if (!empty($_GET['followup_logged'])) {
    $message = 'Catatan follow-up berhasil disimpan.';
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Inbox Lead / Form - Admin',
    'description' => 'Inbox admin untuk membaca form lead dan inquiry pelanggan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-inquiries-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Inbox Lead / Form</div>
                <h1>Form Lead & Inquiry Dashboard</h1>
                <p>Pantau inquiry dari form publik, tindak lanjuti calon pembeli, dan kelola status lead tanpa database tambahan.</p>
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
                    <p>Masukkan password admin untuk membuka inbox inquiry.</p>
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
                        <h2>Action Lead / Form</h2>
                    </div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_inquiries_current_url(['action' => 'export'])); ?>">Export CSV</a>
                    </div>
                </div>
                <?php
                    $activeRange = admin_inquiries_range();
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
                    .admin-inquiries-shell .admin-inquiry-range-field{grid-column:1/-1!important;}
                    .admin-inquiries-shell .admin-inquiry-range-links{display:flex!important;flex-wrap:wrap!important;gap:10px!important;align-items:center!important;margin-top:10px!important;}
                    .admin-inquiries-shell .admin-inquiry-range-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;padding:10px 14px!important;border:1px solid #dbeafe!important;border-radius:999px!important;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;font-size:.84rem!important;font-weight:850!important;line-height:1!important;text-decoration:none!important;white-space:nowrap!important;}
                    .admin-inquiries-shell .admin-inquiry-range-link:hover,.admin-inquiries-shell .admin-inquiry-range-link.is-active{border-color:rgba(15,118,110,.48)!important;background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff)!important;color:var(--admin-primary-dark)!important;-webkit-text-fill-color:var(--admin-primary-dark)!important;}
                    .admin-inquiries-shell .admin-inquiry-range-help{display:block!important;margin-top:8px!important;color:#64748b!important;font-size:.78rem!important;}
                    .admin-inquiries-shell .admin-inquiry-range-note{grid-column:1/-1!important;padding:12px 14px!important;border:1px solid var(--border)!important;background:color-mix(in srgb,var(--bg) 82%,#ffffff)!important;border-radius:16px!important;color:var(--primary-dark)!important;font-size:.86rem!important;}
                    .admin-inquiries-shell .admin-inquiry-followup-actions{display:flex!important;gap:8px!important;flex-wrap:wrap!important;align-items:center!important;margin-top:10px!important;}
                    @media(max-width:680px){.admin-inquiries-shell .admin-inquiry-range-links{overflow-x:auto!important;flex-wrap:nowrap!important;padding-bottom:6px!important;}}
                </style>
                <form class="admin-card admin-inquiry-filter" method="get">
                    <input type="hidden" name="range" value="<?= esc($activeRange); ?>">
                    <div class="admin-inquiry-range-field">
                        <span class="admin-lead-range-title" id="inquiryRangeLegend">Rentang Data</span>
                        <div class="admin-inquiry-range-links" role="group" aria-labelledby="inquiryRangeLegend">
                            <?php foreach ($rangeOptions as $rangeValue => $rangeLabel): ?>
                                <a
                                    class="admin-inquiry-range-link <?= $activeRange === $rangeValue ? 'is-active' : ''; ?>"
                                    href="<?= esc(admin_inquiries_current_url([
                                        'range' => $rangeValue,
                                        'date_from' => $rangeValue === 'custom' ? admin_inquiries_date_input('date_from') : null,
                                        'date_to' => $rangeValue === 'custom' ? admin_inquiries_date_input('date_to') : null,
                                    ])); ?>"
                                ><?= esc($rangeLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <small class="admin-inquiry-range-help">Klik salah satu rentang data. Untuk pilihan Tahun atau Custom tanggal, atur field tambahan lalu klik Terapkan.</small>
                    </div>
                    <div class="admin-inquiry-range-note">Rentang aktif: <strong><?= esc(admin_inquiries_range_label()); ?></strong></div>
                    <label>Tahun
                        <input type="number" name="year" min="2020" max="<?= esc(date('Y')); ?>" value="<?= esc(admin_inquiries_selected_year()); ?>">
                    </label>
                    <label>Dari Tanggal
                        <input type="date" name="date_from" value="<?= esc(admin_inquiries_date_input('date_from')); ?>">
                    </label>
                    <label>Sampai Tanggal
                        <input type="date" name="date_to" value="<?= esc(admin_inquiries_date_input('date_to')); ?>">
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="">Semua status</option>
                            <?php foreach (['Baru','Dihubungi','Deal','Tidak Jadi','Spam'] as $status): ?>
                                <option value="<?= esc($status); ?>" <?= admin_inquiries_filter('status') === $status ? 'selected' : ''; ?>><?= esc($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Kebutuhan
                        <input name="need" value="<?= esc(admin_inquiries_filter('need')); ?>" placeholder="Layanan, produk fisik...">
                    </label>
                    <label>Lokasi
                        <input name="location" value="<?= esc(admin_inquiries_filter('location')); ?>" placeholder="Area layanan atau kota...">
                    </label>
                    <label>Pencarian
                        <input name="search" value="<?= esc(admin_inquiries_filter('search', 120)); ?>" placeholder="Nama, nomor, produk, catatan...">
                    </label>
                    <div class="admin-lead-filter__actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn" href="<?= url('admin/inquiries'); ?>">Reset</a>
                    </div>
                </form>

                <div class="admin-lead-stats admin-inquiry-stats">
                    <div class="admin-lead-stat-card"><span>Total Lead / Form</span><strong><?= esc((string)($summary['total'] ?? 0)); ?></strong><small>Sesuai filter aktif</small></div>
                    <div class="admin-lead-stat-card"><span>Hari Ini</span><strong><?= esc((string)($summary['today'] ?? 0)); ?></strong><small>Lead/form masuk hari ini</small></div>
                    <div class="admin-lead-stat-card"><span>Baru</span><strong><?= esc((string)($summary['new'] ?? 0)); ?></strong><small>Belum ditindaklanjuti</small></div>
                    <div class="admin-lead-stat-card"><span>Status Teratas</span><strong><?= esc((string)(array_key_first((array)($summary['by_status'] ?? [])) ?: '-')); ?></strong><small>Status paling banyak</small></div>
                </div>

                <?php
                    $inquiryFollowupSummary = function_exists('crm_summary') ? crm_summary(0, ['_all_time' => true, 'target_type' => 'inquiry']) : [];
                ?>
                <div class="admin-followup-snapshot">
                    <div><strong><?= esc((string)($inquiryFollowupSummary['today'] ?? 0)); ?></strong><span>Follow-up lead/form hari ini</span></div>
                    <div><strong><?= esc((string)($inquiryFollowupSummary['overdue'] ?? 0)); ?></strong><span>Follow-up terlambat</span></div>
                    <div><strong><?= esc((string)($inquiryFollowupSummary['hot'] ?? 0)); ?></strong><span>Lead/form prioritas tinggi</span></div>
                    <a class="admin-btn admin-btn--ghost" href="<?= url('admin/followups?target_type=inquiry&due=today'); ?>">Lihat Follow-up</a>
                </div>

                <div class="admin-lead-grid-secondary admin-inquiry-grid">
                    <section class="admin-card admin-lead-panel">
                        <h2>Kebutuhan Teratas</h2>
                        <div class="admin-lead-rank">
                            <?php foreach (($summary['by_need'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= esc((string)$count); ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_need'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
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

                <section class="admin-card admin-lead-panel admin-inquiry-list">
                    <div class="admin-lead-panel__head">
                        <div>
                            <h2>Lead / Form Terbaru</h2>
                            <p>Kelola form yang masuk dari customer. Data tetap tersimpan di website dan bisa di-export bila dibutuhkan.</p>
                        </div>
                    </div>

                    <div class="admin-inquiry-cards">
                        <?php foreach ($inquiries as $item): ?>
                            <article class="admin-inquiry-card admin-inquiry-card--<?= esc(slugify((string)($item['status'] ?? 'baru'))); ?>">
                                <div class="admin-inquiry-card__head">
                                    <div>
                                        <strong><?= esc((string)($item['name'] ?? '-')); ?></strong>
                                        <span><?= esc(date('d M Y H:i', (int)($item['_ts'] ?? time()))); ?></span>
                                    </div>
                                    <em><?= esc((string)($item['status'] ?? 'Baru')); ?></em>
                                </div>
                                <div class="admin-inquiry-card__body">
                                    <p><b>Kontak:</b> <?= esc((string)($item['phone'] ?? '-')); ?><?= !empty($item['email']) ? ' · <b>Email:</b> ' . esc((string)$item['email']) : ''; ?></p>
                                    <p><b>Kebutuhan:</b> <?= esc((string)($item['need'] ?? '-')); ?></p>
                                    <p><b>Lokasi:</b> <?= esc((string)($item['location'] ?? '-')); ?></p>
                                    <?php if (!empty($item['item_title'])): ?><p><b>Konteks:</b> <?= esc((string)$item['item_title']); ?></p><?php endif; ?>
                                    <?php if (!empty($item['message'])): ?><p><b>Catatan:</b><br><?= nl2br(esc((string)$item['message'])); ?></p><?php endif; ?>
                                    <p><b>Sumber:</b> <?= esc((string)($item['source'] ?? '-')); ?> · <?= esc((string)($item['page_path'] ?? '-')); ?></p>
                                    <?php if (!empty($item['status_note'])): ?><p><b>Catatan Admin:</b><br><?= nl2br(esc((string)$item['status_note'])); ?></p><?php endif; ?>
                                </div>
                                <form method="post" class="admin-inquiry-status-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="update_status">
                                    <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                    <select name="status">
                                        <?php foreach (['Baru','Dihubungi','Deal','Tidak Jadi','Spam'] as $status): ?>
                                            <option value="<?= esc($status); ?>" <?= ((string)($item['status'] ?? 'Baru')) === $status ? 'selected' : ''; ?>><?= esc($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="note" placeholder="Catatan admin singkat" value="<?= esc((string)($item['status_note'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--primary" type="submit">Update</button>
                                    <?php if (!empty($item['phone'])): ?>
                                        <?php
                                            $followUpMessage = 'Halo ' . (string)($item['name'] ?? '') . ', kami menindaklanjuti inquiry Anda di ' . SITE_NAME . '. ';
                                            if (!empty($item['need'])) {
                                                $followUpMessage .= 'Kebutuhan: ' . (string)$item['need'] . '. ';
                                            }
                                            if (!empty($item['location'])) {
                                                $followUpMessage .= 'Area: ' . (string)$item['location'] . '. ';
                                            }
                                            $followUpMessage .= 'Apakah masih bisa kami bantu?';
                                        ?>
                                        <a class="admin-btn admin-btn--primary" href="<?= esc(wa_link_contextual($followUpMessage, ['source' => 'Admin Inquiry Inbox', 'title' => (string)($item['need'] ?? 'Inquiry')], inquiry_phone_for_whatsapp((string)$item['phone']))); ?>" target="_blank" rel="nofollow noopener">Chat WhatsApp</a>
                                    <?php endif; ?>
                                </form>
                                <?php
                                    $inquiryRecentFollowups = function_exists('crm_recent_for_target') ? crm_recent_for_target('inquiry', (string)($item['id'] ?? ''), 5) : [];
                                    $inquirySuggestedPriority = function_exists('crm_temperature_from_inquiry') ? crm_temperature_from_inquiry($item) : 'Normal';
                                    $inquiryRef = 'INQ-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', (string)($item['id'] ?? 'inquiry')), -6));
                                ?>
                                <div class="admin-crm-panel">
                                    <div class="admin-crm-panel__head">
                                        <div>
                                            <h3>Mini CRM Follow-up</h3>
                                            <p>Catat hasil follow-up, tentukan temperatur lead, dan jadwalkan reminder agar inquiry tidak terlewat.</p>
                                        </div>
                                        <span class="admin-crm-temp admin-crm-temp--<?= esc(crm_status_class($inquirySuggestedPriority)); ?>"><?= esc($inquirySuggestedPriority); ?></span>
                                    </div>
                                    <form method="post" class="admin-crm-form">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="form_action" value="add_followup">
                                        <input type="hidden" name="target_id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                        <input type="hidden" name="target_ref" value="<?= esc($inquiryRef); ?>">
                                        <input type="hidden" name="target_name" value="<?= esc((string)($item['name'] ?? '')); ?>">
                                        <input type="hidden" name="phone" value="<?= esc((string)($item['phone'] ?? '')); ?>">
                                        <input type="hidden" name="email" value="<?= esc((string)($item['email'] ?? '')); ?>">
                                        <input type="hidden" name="subject" value="<?= esc((string)($item['need'] ?? $item['item_title'] ?? 'Inquiry')); ?>">
                                        <label>Prioritas / Temperatur Lead
                                            <select name="priority">
                                                <?php foreach (array_keys(crm_priorities()) as $priority): ?>
                                                    <option value="<?= esc($priority); ?>" <?= $priority === $inquirySuggestedPriority ? 'selected' : ''; ?>><?= esc($priority); ?></option>
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
                                            <textarea name="followup_note" rows="4" placeholder="Contoh: sudah chat customer, minta rekomendasi paket, atau minta dihubungi ulang besok."></textarea>
                                        </label>
                                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Follow-up</button>
                                    </form>
                                    <div class="admin-crm-history">
                                        <h4>Riwayat Terakhir</h4>
                                        <?php foreach ($inquiryRecentFollowups as $followup): ?>
                                            <div><strong><?= esc((string)($followup['outcome'] ?? 'Catatan')); ?></strong><span><?= esc(date('d M Y H:i', (int)($followup['_ts'] ?? time()))); ?> · <?= esc((string)($followup['priority'] ?? 'Normal')); ?> · <?= esc(crm_next_label($followup)); ?></span><?php if (!empty($followup['note'])): ?><p><?= nl2br(esc((string)$followup['note'])); ?></p><?php endif; ?></div>
                                        <?php endforeach; ?>
                                        <?php if (!$inquiryRecentFollowups): ?><p class="admin-muted">Belum ada riwayat follow-up untuk inquiry ini.</p><?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$inquiries): ?>
                            <div class="admin-card admin-empty-card">
                                <h3>Belum ada inquiry sesuai filter.</h3>
                                <p>Coba kirim form dari halaman produk/kontak, atau ubah filter rentang data. Tombol <strong>Chat WhatsApp</strong> akan muncul otomatis pada setiap inquiry yang memiliki nomor WhatsApp/telepon valid.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
