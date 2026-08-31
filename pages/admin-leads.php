<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$action = (string)($_GET['action'] ?? 'dashboard');
$message = '';
$error = '';

if ($action === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/leads');
}

function admin_leads_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_leads_filter_input(string $key, int $max = 80): string
{
    return conversion_dashboard_clean_filter((string)($_GET[$key] ?? ''), $max);
}

function admin_leads_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '')));

    if ($range === '' && isset($_GET['days'])) {
        $range = (string)((int)$_GET['days']);
    }

    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];

    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_leads_days(): int
{
    $range = admin_leads_range();

    if (in_array($range, ['all', 'custom', 'year'], true)) {
        return 0;
    }

    return max(1, min(3650, (int)$range));
}

function admin_leads_date_input(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function admin_leads_selected_year(): string
{
    $year = trim((string)($_GET['year'] ?? date('Y')));

    return preg_match('/^\d{4}$/', $year) ? $year : date('Y');
}

function admin_leads_view_mode(): string
{
    $mode = strtolower(trim((string)($_GET['mode'] ?? 'compact')));

    return $mode === 'raw' ? 'raw' : 'compact';
}

function admin_leads_event_group(): string
{
    $group = strtolower(trim((string)($_GET['event_group'] ?? '')));
    $allowed = ['', 'high_intent', 'support', 'conversion', 'order', 'payment', 'inquiry', 'page_view', 'interaction', 'whatsapp', 'checkout'];

    return in_array($group, $allowed, true) ? $group : '';
}

function admin_leads_filters(): array
{
    $range = admin_leads_range();
    $filters = array_filter([
        'source' => admin_leads_filter_input('source'),
        'category' => admin_leads_filter_input('category'),
        'location' => admin_leads_filter_input('location'),
        'type' => admin_leads_filter_input('type'),
        'channel' => admin_leads_filter_input('channel'),
        'intent' => admin_leads_filter_input('intent'),
        'event_group' => admin_leads_event_group(),
        '_view_mode' => admin_leads_view_mode(),
        'whatsapp_only' => isset($_GET['whatsapp_only']),
    ], static fn($value): bool => $value !== '' && $value !== false && $value !== null);

    if ($range === 'all') {
        $filters['_all_time'] = true;
    }

    if ($range === 'year') {
        $year = admin_leads_selected_year();
        $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
        $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
        $filters['_year'] = $year;
    }

    if ($range === 'custom') {
        $from = admin_leads_date_input('date_from');
        $to = admin_leads_date_input('date_to');

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

function admin_leads_current_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_leads_range(),
        'year' => admin_leads_selected_year(),
        'date_from' => admin_leads_date_input('date_from'),
        'date_to' => admin_leads_date_input('date_to'),
        'source' => admin_leads_filter_input('source'),
        'category' => admin_leads_filter_input('category'),
        'location' => admin_leads_filter_input('location'),
        'channel' => admin_leads_filter_input('channel'),
        'intent' => admin_leads_filter_input('intent'),
        'type' => admin_leads_filter_input('type'),
        'event_group' => admin_leads_event_group(),
        'mode' => admin_leads_view_mode(),
    ], $extra);

    if (isset($_GET['whatsapp_only']) && !array_key_exists('whatsapp_only', $query)) {
        $query['whatsapp_only'] = '1';
    }

    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);

    return url('admin/leads' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_leads_percent_bar(int $value, int $max): string
{
    if ($max <= 0) {
        return '0';
    }

    return (string)max(3, min(100, (int)round(($value / $max) * 100)));
}

function admin_leads_range_label(array $summary): string
{
    $range = (array)($summary['range'] ?? []);

    if (!empty($range['all_time'])) {
        return 'Semua data sejak tracking aktif';
    }

    $start = (string)($range['start'] ?? '');
    $end = (string)($range['end'] ?? '');

    if ($start !== '' && $end !== '') {
        return date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end));
    }

    return 'Rentang aktif';
}

function admin_leads_json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_leads_export_csv(array $events): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="lead-events-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['time', 'event_group', 'event_kind', 'source', 'type', 'channel', 'category', 'location', 'intent', 'label', 'page_path', 'target_host', 'is_whatsapp', 'repeat_count']);

    foreach ($events as $event) {
        fputcsv($out, [
            (string)($event['time'] ?? ''),
            (string)($event['_event_group_label'] ?? ''),
            (string)($event['_event_kind'] ?? ''),
            (string)($event['source'] ?? ''),
            (string)($event['type'] ?? ''),
            (string)($event['channel'] ?? ''),
            (string)($event['category'] ?? ''),
            (string)($event['location'] ?? ''),
            (string)($event['intent'] ?? ''),
            (string)($event['label'] ?? ''),
            (string)($event['page_path'] ?? ''),
            (string)($event['target_host'] ?? ''),
            !empty($event['is_whatsapp']) ? 'yes' : 'no',
            (string)($event['_repeat_count'] ?? 1),
        ]);
    }

    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();

    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_leads_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'leads']);
            }
            redirect_302('admin/leads');
        }

        $error = 'Password admin salah.';
    }
}

$loggedIn = admin_leads_logged_in();

if ($loggedIn && (string)($_GET['format'] ?? '') === 'json') {
    $summary = conversion_dashboard_summary(admin_leads_days(), admin_leads_filters());
    admin_leads_json_response(['ok' => true, 'summary' => $summary]);
}

$summary = $loggedIn ? conversion_dashboard_summary(admin_leads_days(), admin_leads_filters()) : [];
$availableYears = $loggedIn ? (array)($summary['available_years'] ?? conversion_available_lead_years()) : [];

if ($loggedIn && $action === 'export') {
    admin_leads_export_csv(conversion_read_lead_events(admin_leads_days(), admin_leads_filters(), 200000));
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Tracking Lead - Admin',
    'description' => 'Dashboard internal untuk membaca klik CTA, WhatsApp, form, checkout, dan conversion channel website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-leads-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Lead & Customer</div>
                <h1>Tracking Lead</h1>
                <p>Pantau sumber calon customer dari WhatsApp, form, checkout, artikel, katalog, produk, dan halaman layanan.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-login-layout">
                    <div class="admin-login-copy">
                        <span class="admin-badge">Akses terbatas</span>
                        <h2>Masuk Tracking Lead</h2>
                        <p>Gunakan password admin yang sama. Dashboard ini hanya membaca data conversion anonim dari file log internal.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php $trackingEnabled = !empty($summary['enabled']); ?>
                <?php if (!$trackingEnabled): ?>
                    <div class="admin-alert admin-alert--error">Pencatatan lead sedang nonaktif. Aktifkan fitur lead tracking dari pengaturan sistem jika ingin merekam klik tombol dan form.</div>
                <?php endif; ?>

                <style>
                    .admin-leads-shell .admin-lead-range-links{display:flex!important;flex-wrap:wrap!important;gap:10px!important;align-items:center!important;margin-top:10px!important;visibility:visible!important;opacity:1!important;}
                    .admin-leads-shell .admin-lead-range-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;padding:10px 14px!important;border:1px solid #dbeafe!important;border-radius:999px!important;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;font-size:.84rem!important;font-weight:850!important;line-height:1!important;text-decoration:none!important;visibility:visible!important;opacity:1!important;white-space:nowrap!important;box-shadow:none!important;}
                    .admin-leads-shell .admin-lead-range-link:hover,.admin-leads-shell .admin-lead-range-link.is-active{border-color:rgba(15,118,110,.48)!important;background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff)!important;color:var(--admin-primary-dark)!important;-webkit-text-fill-color:var(--admin-primary-dark)!important;}
                    .admin-leads-shell .admin-lead-range-field--links{grid-column:1/-1!important;}
                    @media(max-width:680px){.admin-leads-shell .admin-lead-range-links{overflow-x:auto!important;flex-wrap:nowrap!important;padding-bottom:6px!important;}}
                </style>

                <div class="admin-toolbar admin-page-actions">
                    <div>
                        <span class="admin-badge">Kelola Data Lead</span>
                        <h2>Aksi Lead</h2>
                        <p class="admin-muted">Pilih tampilan ringkas untuk membaca data lebih cepat, atau tampilan detail untuk melihat semua aktivitas sesuai filter.</p>
                    </div>
                    <div class="admin-toolbar__actions admin-mode-toggle">
                        <a class="admin-btn <?= admin_leads_view_mode() === 'compact' ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= esc(admin_leads_current_url(['mode' => 'compact'])); ?>">Ringkas</a>
                        <a class="admin-btn <?= admin_leads_view_mode() === 'raw' ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= esc(admin_leads_current_url(['mode' => 'raw'])); ?>">Detail</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_leads_current_url(['action' => 'export'])); ?>">Export CSV</a>
                    </div>
                </div>

                <form method="get" class="admin-card admin-lead-filter admin-lead-filter--template" id="leadFilterForm">
                    <div class="admin-lead-range-field admin-lead-range-field--links">
                        <span class="admin-lead-range-title" id="leadRangeLegend">Rentang Data</span>
                        <?php
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
                            $activeRange = admin_leads_range();
                        ?>
                        <div class="admin-lead-range-links" role="group" aria-labelledby="leadRangeLegend">
                            <?php foreach ($rangeOptions as $rangeValue => $rangeLabel): ?>
                                <a
                                    class="admin-lead-range-link <?= $activeRange === $rangeValue ? 'is-active' : ''; ?>"
                                    href="<?= esc(admin_leads_current_url([
                                        'range' => $rangeValue,
                                        'date_from' => $rangeValue === 'custom' ? admin_leads_date_input('date_from') : null,
                                        'date_to' => $rangeValue === 'custom' ? admin_leads_date_input('date_to') : null,
                                    ])); ?>"
                                    <?= $activeRange === $rangeValue ? 'aria-current="true"' : ''; ?>
                                ><?= esc($rangeLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <input id="leadRangeHidden" type="hidden" name="range" value="<?= esc($activeRange); ?>">
                        <small class="admin-lead-range-help">Klik salah satu rentang data di atas. Untuk pilihan Tahun atau Custom tanggal, atur field tambahan lalu klik Terapkan.</small>
                    </div>
                    <div class="admin-lead-year-field" data-range-field="year">
                        <label>Tahun</label>
                        <select name="year">
                            <?php foreach ($availableYears as $yearOption): ?>
                                <option value="<?= esc((string)$yearOption); ?>" <?= admin_leads_selected_year() === (string)$yearOption ? 'selected' : ''; ?>><?= esc((string)$yearOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-lead-date-field" data-range-field="custom">
                        <label>Dari Tanggal</label>
                        <input type="date" name="date_from" value="<?= esc(admin_leads_date_input('date_from')); ?>">
                    </div>
                    <div class="admin-lead-date-field" data-range-field="custom">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="date_to" value="<?= esc(admin_leads_date_input('date_to')); ?>">
                    </div>
                    <input type="hidden" name="mode" value="<?= esc(admin_leads_view_mode()); ?>">
                    <div>
                        <label>Jenis Aktivitas</label>
                        <select name="event_group">
                            <?php $activeEventGroup = admin_leads_event_group(); ?>
                            <?php foreach ([
                                '' => 'Semua aktivitas',
                                'high_intent' => 'Minat tinggi',
                                'support' => 'Bantuan/support',
                                'conversion' => 'WhatsApp / Konversi',
                                'order' => 'Pesanan / Checkout',
                                'payment' => 'Pembayaran / Bukti',
                                'inquiry' => 'Form Masuk',
                                'page_view' => 'Kunjungan Halaman',
                                'interaction' => 'Interaksi lain',
                            ] as $groupValue => $groupLabel): ?>
                                <option value="<?= esc($groupValue); ?>" <?= $activeEventGroup === $groupValue ? 'selected' : ''; ?>><?= esc($groupLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Channel</label>
                        <select name="channel">
                            <?php $activeChannel = admin_leads_filter_input('channel'); ?>
                            <?php foreach (['' => 'Semua channel', 'whatsapp' => 'WhatsApp', 'form' => 'Form', 'checkout' => 'Checkout', 'payment' => 'Payment/QRIS', 'click' => 'Click lain'] as $channelValue => $channelLabel): ?>
                                <option value="<?= esc($channelValue); ?>" <?= $activeChannel === $channelValue ? 'selected' : ''; ?>><?= esc($channelLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Sumber</label>
                        <input name="source" value="<?= esc(admin_leads_filter_input('source')); ?>" placeholder="homepage, katalog, artikel...">
                    </div>
                    <div>
                        <label>Kategori</label>
                        <input name="category" value="<?= esc(admin_leads_filter_input('category')); ?>" placeholder="produk, layanan, info-lokal...">
                    </div>
                    <div>
                        <label>Lokasi</label>
                        <input name="location" value="<?= esc(admin_leads_filter_input('location')); ?>" placeholder="Area layanan, kota, atau cabang...">
                    </div>
                    <div class="admin-lead-filter__check">
                        <label><input type="checkbox" name="whatsapp_only" value="1" <?= isset($_GET['whatsapp_only']) ? 'checked' : ''; ?>> Hanya WhatsApp</label>
                        <small>Filter ini hanya mengubah tampilan laporan.</small>
                    </div>
                    <div class="admin-lead-filter__actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/leads'); ?>">Reset</a>
                    </div>
                </form>

                <div class="admin-lead-livebar">
                    <div>
                        <strong>Status dashboard:</strong>
                        <span id="leadLiveStatus">Aktif, refresh otomatis tiap 30 detik</span>
                    </div>
                    <small>Rentang: <span id="leadRangeLabel"><?= esc(admin_leads_range_label($summary)); ?></span> · Terakhir update: <span id="leadUpdatedAt"><?= esc(date('H:i:s')); ?></span></small>
                </div>

                <div class="admin-lead-stats" id="leadStats">
                    <article class="admin-lead-stat-card">
                        <span>Total Aktivitas</span>
                        <strong data-stat="total"><?= number_format((int)($summary['total'] ?? 0), 0, ',', '.'); ?></strong>
                        <small><?= admin_leads_view_mode() === 'raw' ? 'Mode detail sesuai filter' : 'Sudah diringkas agar mudah dibaca'; ?></small>
                    </article>
                    <article class="admin-lead-stat-card">
                        <span>Data Detail</span>
                        <strong data-stat="total_raw"><?= number_format((int)($summary['total_raw'] ?? 0), 0, ',', '.'); ?></strong>
                        <small>Data detail tetap tersedia saat export</small>
                    </article>
                    <article class="admin-lead-stat-card">
                        <span>High Intent</span>
                        <strong data-stat="high_intent"><?= number_format((int)($summary['high_intent'] ?? 0), 0, ',', '.'); ?></strong>
                        <small>WhatsApp, inquiry, checkout, payment</small>
                    </article>
                    <article class="admin-lead-stat-card">
                        <span>Support Event</span>
                        <strong data-stat="support"><?= number_format((int)($summary['support'] ?? 0), 0, ',', '.'); ?></strong>
                        <small>Invoice/status/page view pendukung</small>
                    </article>
                    <article class="admin-lead-stat-card">
                        <span>Hari Ini</span>
                        <strong data-stat="today"><?= number_format((int)($summary['today'] ?? 0), 0, ',', '.'); ?></strong>
                        <small>Klik/event yang masuk hari ini</small>
                    </article>
                    <article class="admin-lead-stat-card">
                        <span>WhatsApp</span>
                        <strong data-stat="whatsapp"><?= number_format((int)($summary['whatsapp'] ?? 0), 0, ',', '.'); ?></strong>
                        <small>Event menuju WhatsApp</small>
                    </article>
                    <article class="admin-lead-stat-card">
                        <span>Top Channel</span>
                        <strong data-stat="top_channel"><?= esc((string)($summary['top_channel'] ?? '-')); ?></strong>
                        <small>Channel conversion paling aktif</small>
                    </article>
                    <article class="admin-lead-stat-card">
                        <span>Top Kategori</span>
                        <strong data-stat="top_category"><?= esc((string)($summary['top_category'] ?? '-')); ?></strong>
                        <small>Kategori paling banyak diklik</small>
                    </article>
                </div>

                <div class="admin-lead-grid-main admin-lead-grid-main--charts">
                    <section class="admin-card admin-lead-panel">
                        <div class="admin-lead-panel__head">
                            <div>
                                <h2>Grafik Harian</h2>
                                <p>Melihat pergerakan minat customer dalam periode aktif.</p>
                            </div>
                        </div>
                        <div class="admin-lead-bars admin-lead-bars--chart" id="leadDailyBars">
                            <?php foreach (($summary['daily'] ?? []) as $day => $count): ?>
                                <div class="admin-lead-bar-row">
                                    <span><?= esc(date('d M', strtotime((string)$day))); ?></span>
                                    <div><i style="width: <?= esc(admin_leads_percent_bar((int)$count, (int)($summary['max_daily'] ?? 0))); ?>%"></i></div>
                                    <strong><?= (int)$count; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="admin-card admin-lead-panel">
                        <div class="admin-lead-panel__head">
                            <div>
                                <h2>Grafik Bulanan</h2>
                                <p>Cocok untuk membaca performa jangka panjang sejak website mulai aktif.</p>
                            </div>
                        </div>
                        <div class="admin-lead-bars admin-lead-bars--chart" id="leadMonthlyBars">
                            <?php foreach (($summary['monthly'] ?? []) as $month => $count): ?>
                                <div class="admin-lead-bar-row">
                                    <span><?= esc(date('M Y', strtotime((string)$month . '-01'))); ?></span>
                                    <div><i style="width: <?= esc(admin_leads_percent_bar((int)$count, (int)($summary['max_monthly'] ?? 0))); ?>%"></i></div>
                                    <strong><?= (int)$count; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <div class="admin-lead-grid-secondary admin-lead-grid-secondary--template">
                    <section class="admin-card admin-lead-panel">
                        <h2>Channel Lead</h2>
                        <p>Ringkasan asal lead dari WhatsApp, form, checkout, pembayaran, dan channel lainnya.</p>
                        <div class="admin-lead-rank admin-lead-rank--bars" id="leadTopChannels">
                            <?php foreach (($summary['by_channel'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><em style="width: <?= esc(admin_leads_percent_bar((int)$count, max((array)($summary['by_channel'] ?? [0])))); ?>%"></em><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_channel'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Sumber Lead</h2>
                        <div class="admin-lead-rank" id="leadTopSumbers">
                            <?php foreach (($summary['by_source'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_source'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Kategori Teratas</h2>
                        <div class="admin-lead-rank" id="leadTopCategories">
                            <?php foreach (($summary['by_category'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_category'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Area / Lokasi Teratas</h2>
                        <div class="admin-lead-rank" id="leadTopLocations">
                            <?php foreach (($summary['by_location'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_location'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                    <section class="admin-card admin-lead-panel">
                        <h2>Tujuan Customer</h2>
                        <div class="admin-lead-rank" id="leadTopIntents">
                            <?php foreach (($summary['by_intent'] ?? []) as $label => $count): ?>
                                <div><span><?= esc((string)$label); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['by_intent'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="admin-card admin-lead-panel">
                    <div class="admin-lead-panel__head">
                        <div>
                            <h2>Halaman yang Paling Mengirim Lead</h2>
                            <p>Gunakan ini untuk menentukan artikel, produk, katalog, atau lokasi yang perlu diperkuat.</p>
                        </div>
                    </div>
                    <div class="admin-lead-rank admin-lead-rank--pages" id="leadTopPages">
                        <?php foreach (($summary['top_pages'] ?? []) as $label => $count): ?>
                            <div><span><?= esc((string)$label); ?></span><strong><?= (int)$count; ?></strong></div>
                        <?php endforeach; ?>
                        <?php if (empty($summary['top_pages'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                    </div>
                </section>

                <section class="admin-card admin-lead-panel">
                    <div class="admin-lead-panel__head">
                        <div>
                            <h2>Aktivitas Terbaru <?= admin_leads_view_mode() === 'raw' ? '(Detail)' : '(Ringkas)'; ?></h2>
                            <p>Mode ringkas menggabungkan aktivitas serupa agar tabel lebih mudah dibaca. Data lengkap tetap bisa di-export.</p>
                        </div>
                    </div>
                    <div class="admin-lead-table-wrap">
                        <table class="admin-lead-table" id="leadRecentTable">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Tipe</th>
                                    <th>Channel</th>
                                    <th>Sumber</th>
                                    <th>Intent</th>
                                    <th>Label</th>
                                    <th>Repeat</th>
                                    <th>Halaman</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($summary['recent'] ?? []) as $event): ?>
                                    <?php
                                    $eventGroup = (string)($event['_event_group'] ?? 'interaction');
                                    $eventLabel = (string)($event['_event_group_label'] ?? 'Interaction');
                                    $repeatCount = max(1, (int)($event['_repeat_count'] ?? 1));
                                    ?>
                                    <tr>
                                        <td><?= esc(date('d M H:i', (int)($event['_ts'] ?? time()))); ?></td>
                                        <td><span class="admin-event-badge admin-event-badge--<?= esc($eventGroup); ?>"><?= esc($eventLabel); ?></span></td>
                                        <td><?= esc((string)($event['channel'] ?? '-')); ?></td>
                                        <td><?= esc((string)($event['source'] ?? '-')); ?></td>
                                        <td><?= esc((string)($event['intent'] ?? '-')); ?></td>
                                        <td><?= esc((string)($event['label'] ?? '-')); ?></td>
                                        <td><?= $repeatCount > 1 ? '<span class="admin-repeat-pill">×' . (int)$repeatCount . '</span>' : '<span class="admin-muted">1</span>'; ?></td>
                                        <td><?= esc((string)($event['page_path'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($summary['recent'])): ?>
                                    <tr><td colspan="8">Belum ada aktivitas. Coba klik tombol WhatsApp atau kirim form dari halaman publik setelah tracking aktif.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="admin-card admin-lead-panel admin-lead-guide">
                    <h2>Cara Membaca Dashboard</h2>
                    <div class="admin-lead-guide-grid">
                        <div><strong>Trend bulanan naik</strong><p>SEO dan konten mulai mengirim conversion lebih konsisten dari waktu ke waktu.</p></div>
                        <div><strong>Channel WhatsApp tinggi</strong><p>User masih nyaman konsultasi langsung. Perkuat CTA dan teks pembuka WA.</p></div>
                        <div><strong>Channel form/checkout muncul</strong><p>Nanti bisa dipakai untuk membaca performa inquiry, checkout, dan payment QRIS.</p></div>
                        <div><strong>Top Lokasi tinggi</strong><p>Area itu potensial dibuatkan artikel lokal, produk unggulan, atau campaign khusus.</p></div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php if ($loggedIn): ?>
<script>
(function () {
    const apiUrl = new URL(window.location.href);
    apiUrl.searchParams.set('format', 'json');

    const numberFormat = new Intl.NumberFormat('id-ID');

    function esc(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
        });
    }

    function setStat(name, value) {
        const node = document.querySelector('[data-stat="' + name + '"]');
        if (!node) return;
        node.textContent = typeof value === 'number' ? numberFormat.format(value) : String(value ?? '-');
    }

    function renderRank(id, data, withBars = false) {
        const node = document.getElementById(id);
        if (!node) return;
        const entries = Object.entries(data || {});
        const max = Math.max(1, ...entries.map(([, count]) => Number(count || 0)));
        node.innerHTML = entries.length
            ? entries.map(function ([label, count]) {
                const bar = withBars ? '<em style="width:' + Math.max(3, Math.round((Number(count) / max) * 100)) + '%"></em>' : '';
                return '<div><span>' + esc(label) + '</span>' + bar + '<strong>' + numberFormat.format(count) + '</strong></div>';
            }).join('')
            : '<p class="admin-muted">Belum ada data.</p>';
    }

    function renderBars(id, data, maxKey, monthMode) {
        const node = document.getElementById(id);
        if (!node) return;
        const entries = Object.entries(data || {});
        const max = Math.max(1, ...entries.map(([, count]) => Number(count || 0)), Number(maxKey || 0));
        node.innerHTML = entries.length ? entries.map(function ([key, count]) {
            let label = key;
            if (monthMode) {
                const date = new Date(key + '-01T00:00:00');
                label = !isNaN(date.getTime()) ? date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' }) : key;
            } else {
                const date = new Date(key + 'T00:00:00');
                label = !isNaN(date.getTime()) ? date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) : key;
            }
            const width = Math.max(3, Math.min(100, Math.round((Number(count) / max) * 100)));
            return '<div class="admin-lead-bar-row"><span>' + esc(label) + '</span><div><i style="width:' + width + '%"></i></div><strong>' + numberFormat.format(count) + '</strong></div>';
        }).join('') : '<p class="admin-muted">Belum ada data.</p>';
    }

    function renderRecent(summary) {
        const table = document.getElementById('leadRecentTable');
        if (!table) return;
        const body = table.querySelector('tbody');
        const events = summary.recent || [];
        body.innerHTML = events.length
            ? events.slice(0, 30).map(function (event) {
                const date = event.time ? new Date(event.time) : null;
                const time = date && !isNaN(date.getTime())
                    ? date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) + ' ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
                    : '-';
                const group = event._event_group || 'interaction';
                const groupLabel = event._event_group_label || 'Interaction';
                const repeat = Number(event._repeat_count || 1);
                return '<tr>' +
                    '<td>' + esc(time) + '</td>' +
                    '<td><span class="admin-event-badge admin-event-badge--' + esc(group) + '">' + esc(groupLabel) + '</span></td>' +
                    '<td>' + esc(event.channel || '-') + '</td>' +
                    '<td>' + esc(event.source || '-') + '</td>' +
                    '<td>' + esc(event.intent || '-') + '</td>' +
                    '<td>' + esc(event.label || '-') + '</td>' +
                    '<td>' + (repeat > 1 ? '<span class="admin-repeat-pill">×' + esc(repeat) + '</span>' : '<span class="admin-muted">1</span>') + '</td>' +
                    '<td>' + esc(event.page_path || '-') + '</td>' +
                '</tr>';
            }).join('')
            : '<tr><td colspan="8">Belum ada aktivitas. Coba klik tombol WhatsApp atau kirim form dari halaman publik setelah tracking aktif.</td></tr>';
    }

    function getSelectedRange() {
        const hiddenRange = document.getElementById('leadRangeHidden');
        if (hiddenRange && hiddenRange.value) return hiddenRange.value;
        return '30';
    }

    function toggleRangeFields() {
        const range = getSelectedRange();
        document.querySelectorAll('[data-range-field]').forEach(function (field) {
            field.style.display = field.dataset.rangeField === range ? '' : 'none';
        });
    }

    async function refreshLeads() {
        const status = document.getElementById('leadLiveStatus');
        try {
            if (status) status.textContent = 'Mengambil data terbaru...';
            const response = await fetch(apiUrl.toString(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            const summary = data.summary || {};

            setStat('total', Number(summary.total || 0));
            setStat('total_raw', Number(summary.total_raw || 0));
            setStat('high_intent', Number(summary.high_intent || 0));
            setStat('support', Number(summary.support || 0));
            setStat('today', Number(summary.today || 0));
            setStat('whatsapp', Number(summary.whatsapp || 0));
            setStat('top_channel', summary.top_channel || '-');
            setStat('top_category', summary.top_category || '-');

            renderBars('leadDailyBars', summary.daily, summary.max_daily, false);
            renderBars('leadMonthlyBars', summary.monthly, summary.max_monthly, true);
            renderRank('leadTopChannels', summary.by_channel, true);
            renderRank('leadTopSumbers', summary.by_source);
            renderRank('leadTopCategories', summary.by_category);
            renderRank('leadTopLocations', summary.by_location);
            renderRank('leadTopIntents', summary.by_intent);
            renderRank('leadTopPages', summary.top_pages);
            renderRecent(summary);

            const updated = document.getElementById('leadUpdatedAt');
            if (updated) updated.textContent = new Date().toLocaleTimeString('id-ID');
            const rangeLabel = document.getElementById('leadRangeLabel');
            if (rangeLabel && summary.range) {
                rangeLabel.textContent = summary.range.all_time ? 'Semua data sejak tracking aktif' : [summary.range.start, summary.range.end].filter(Boolean).join(' - ');
            }
            if (status) status.textContent = 'Aktif, refresh otomatis tiap 30 detik';
        } catch (error) {
            if (status) status.textContent = 'Gagal refresh otomatis. Reload halaman jika perlu.';
        }
    }

    toggleRangeFields();
    window.setInterval(refreshLeads, 30000);
})();
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
