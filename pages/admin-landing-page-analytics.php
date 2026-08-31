<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = (string)($_GET['message'] ?? '');
$error = '';

if ((string)($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/landing-page-analytics');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !admin_panel_logged_in()) {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        $_SESSION['admin_articles_logged_in'] = true;
        if (function_exists('activity_log_record')) {
            activity_log_record('login', 'admin', null, 'Admin login ke Landing Page Analytics.', ['area' => 'landing_page_analytics']);
        }
        redirect_302('admin/landing-page-analytics');
    } else {
        $error = 'Password admin salah.';
        if (function_exists('activity_log_record')) {
            activity_log_record('login_failed', 'admin', null, 'Percobaan login Landing Page Analytics gagal.', ['area' => 'landing_page_analytics']);
        }
    }
}

$loggedIn = admin_panel_logged_in();
$rangeInfo = landing_page_analytics_date_filter($_GET);
$range = (string)$rangeInfo['range'];
$days = (int)$rangeInfo['days'];
$filters = (array)$rangeInfo['filters'];
$lpSlug = slugify((string)($_GET['lp'] ?? ''));
if ($lpSlug !== '') {
    $filters['lp_slug'] = $lpSlug;
}
$leadSegmentFilter = function_exists('landing_page_analytics_lead_segment') ? landing_page_analytics_lead_segment(['lead_segment' => (string)($_GET['segment'] ?? '')]) : '';
if ($leadSegmentFilter !== '') {
    $filters['lead_segment'] = $leadSegmentFilter;
}

$report = $loggedIn ? landing_page_analytics_report($days, $filters) : ['totals' => [], 'sumbers' => [], 'rows' => [], 'leads' => [], 'tracking_ready' => false];
$pages = function_exists('landing_page_all') ? landing_page_all(true) : [];
$totals = (array)($report['totals'] ?? []);

if (!function_exists('admin_lp_analytics_send_csv')) {
    function admin_lp_analytics_send_csv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'wb');
        if ($out) {
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn(string $key): string => (string)($row[$key] ?? ''), $headers), ',', '"', '\\');
            }
            fclose($out);
        }
        exit;
    }
}

if ($loggedIn) {
    $export = strtolower(trim((string)($_GET['export'] ?? '')));
    if (in_array($export, ['csv', 'leads'], true)) {
        $filename = 'landing-page-leads-' . ($lpSlug !== '' ? $lpSlug . '-' : '') . date('Ymd-His') . '.csv';
        $rows = landing_page_analytics_csv_rows($report);
        $headers = ['time', 'landing_page', 'slug', 'name', 'whatsapp', 'email', 'need', 'form_name', 'lead_segment', 'lead_tags', 'lead_priority', 'lead_stage', 'lead_score', 'ab_test_type', 'ab_variasi', 'ab_variasi_label', 'status', 'sumber_bucket', 'utm_sumber', 'utm_medium', 'utm_promosi', 'utm_content', 'utm_term', 'page_path'];
        admin_lp_analytics_send_csv($filename, $headers, $rows);
    }

    if ($export === 'performance') {
        $rows = [];
        foreach ((array)($report['rows'] ?? []) as $row) {
            $sumbers = (array)($row['sumbers'] ?? []);
            $rows[] = [
                'slug' => (string)($row['slug'] ?? ''),
                'title' => (string)($row['title'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'url' => (string)($row['url'] ?? ''),
                'page_kunjungan' => (string)($row['page_kunjungan'] ?? 0),
                'cta_click' => (string)($row['cta_click'] ?? 0),
                'form_submit' => (string)($row['form_submit'] ?? 0),
                'inquiry' => (string)($row['inquiry'] ?? 0),
                'lead_total' => (string)($row['lead_total'] ?? 0),
                'order' => (string)($row['order'] ?? 0),
                'conversion_rate' => (string)($row['conversion_rate'] ?? 0),
                'cta_rate' => (string)($row['cta_rate'] ?? 0),
                'lead_rate' => (string)($row['lead_rate'] ?? 0),
                'top_sumber' => (string)(array_key_first($sumbers) ?: ''),
                'latest_at' => (string)($row['latest_at'] ?? ''),
            ];
        }
        admin_lp_analytics_send_csv('landing-page-performance-' . date('Ymd-His') . '.csv', array_keys($rows[0] ?? [
            'slug' => '', 'title' => '', 'status' => '', 'url' => '', 'page_kunjungan' => '', 'cta_click' => '', 'form_submit' => '', 'inquiry' => '', 'lead_total' => '', 'order' => '', 'conversion_rate' => '', 'cta_rate' => '', 'lead_rate' => '', 'top_sumber' => '', 'latest_at' => '',
        ]), $rows);
    }

    if ($export === 'promosis') {
        $rows = [];
        foreach ((array)($report['promosi_breakdown'] ?? []) as $row) {
            $rows[] = [
                'promosi' => (string)($row['label'] ?? ''),
                'page_kunjungan' => (string)($row['page_kunjungan'] ?? 0),
                'cta_click' => (string)($row['cta_click'] ?? 0),
                'form_submit' => (string)($row['form_submit'] ?? 0),
                'inquiry' => (string)($row['inquiry'] ?? 0),
                'lead_total' => (string)($row['lead_total'] ?? 0),
                'order' => (string)($row['order'] ?? 0),
                'conversion_rate' => (string)($row['conversion_rate'] ?? 0),
                'latest_at' => (string)($row['latest_at'] ?? ''),
            ];
        }
        admin_lp_analytics_send_csv('landing-page-promosis-' . date('Ymd-His') . '.csv', array_keys($rows[0] ?? [
            'promosi' => '', 'page_kunjungan' => '', 'cta_click' => '', 'form_submit' => '', 'inquiry' => '', 'lead_total' => '', 'order' => '', 'conversion_rate' => '', 'latest_at' => '',
        ]), $rows);
    }
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Analytics Landing Page - ' . SITE_NAME,
    'description' => 'Dashboard performa landing page: kunjungan, klik tombol, lead, order, sumber promosi, tes variasi, dan rasio hasil.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-lp-analytics-template">
    <section class="admin-hero admin-lp-analytics-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Analytics Landing Page</div>
                <h1>Analytics Landing Page</h1>
                <p>Pantau kunjungan, klik tombol, form masuk, order, sumber traffic, dan performa variasi landing page dalam satu dashboard.</p>
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
                        <h2>Masuk Landing Page Analytics</h2>
                        <p>Gunakan password admin yang sama dengan dashboard utama.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/landing-page-analytics'); ?>

                <div data-admin-page-tab-scope>
                    <div class="admin-page-subtabs admin-page-subtabs--4" role="tablist" aria-label="Bagian Analisis Landing Page">
                        <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="lp-analytics-range"><span>1. Rentang Waktu</span><small>Filter & angka utama</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="lp-analytics-flow"><span>2. Alur Analisis</span><small>Alur, insight, trend</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="lp-analytics-traffic"><span>3. Sumber Traffic</span><small>Source, campaign, segment</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="lp-analytics-performance"><span>4. Performa LP</span><small>A/B, ranking, lead</small></button>
                    </div>
                    <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian analisis</span><select data-admin-page-tab-select aria-label="Pilih bagian Analisis Landing Page"><option value="lp-analytics-range">1. Rentang Waktu</option><option value="lp-analytics-flow">2. Alur Analisis</option><option value="lp-analytics-traffic">3. Sumber Traffic</option><option value="lp-analytics-performance">4. Performa LP</option></select></label></div>

                    <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="lp-analytics-range">
                <form method="get" class="admin-card admin-lp-filter-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Filter Dashboard</span>
                            <h2>Rentang, landing page, dan Segment</h2>
                            <p>Gunakan filter ini untuk membaca performa promosi iklan, landing page tertentu, atau segment lead tertentu.</p>
                        </div>
                        <div class="admin-lp-filter-actions">
                            <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-analytics?' . http_build_query(array_merge($_GET, ['export' => 'performance'])))); ?>">Export Performa</a>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-analytics?' . http_build_query(array_merge($_GET, ['export' => 'leads'])))); ?>">Export Lead</a>
                        </div>
                    </div>
                    <div class="admin-form-row admin-form-row--4 admin-lp-filter-grid">
                        <label>Rentang
                            <select name="range">
                                <?php foreach (['7' => '7 hari', '14' => '14 hari', '30' => '30 hari', '60' => '60 hari', '90' => '90 hari', '180' => '180 hari', '365' => '1 tahun', 'all' => 'Semua waktu', 'custom' => 'Custom'] as $value => $label): ?>
                                    <option value="<?= esc((string)$value); ?>" <?= $range === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Dari
                            <input type="date" name="from" value="<?= esc((string)($_GET['from'] ?? '')); ?>">
                        </label>
                        <label>Sampai
                            <input type="date" name="to" value="<?= esc((string)($_GET['to'] ?? '')); ?>">
                        </label>
                        <label>Landing Page
                            <select name="lp">
                                <option value="">Semua landing page</option>
                                <?php foreach ($pages as $page): ?>
                                    <?php $slug = (string)($page['slug'] ?? ''); if ($slug === '') { continue; } ?>
                                    <option value="<?= esc($slug); ?>" <?= $lpSlug === $slug ? 'selected' : ''; ?>><?= esc((string)($page['title'] ?? $slug)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Segment Lead
                            <input name="segment" value="<?= esc($leadSegmentFilter); ?>" placeholder="contoh: lp-consultation">
                        </label>
                    </div>
                </form>

                <?php if (empty($report['tracking_ready'])): ?>
                    <div class="admin-alert admin-alert--warning">Landing page tracking belum aktif. Pastikan ENABLE_LEAD_TRACKING=true dan endpoint lead-event bisa diakses.</div>
                <?php endif; ?>

                <div class="admin-grid admin-grid--stats admin-lp-analytics-stats admin-lp-analytics-stats--template">
                    <div class="admin-card"><span class="admin-badge">Page Kunjungan</span><h2><?= number_format((int)($totals['page_kunjungan'] ?? 0)); ?></h2><p>Kunjungan landing page yang tercatat lokal.</p></div>
                    <div class="admin-card"><span class="admin-badge">Tombol Click</span><h2><?= number_format((int)($totals['cta_click'] ?? 0)); ?></h2><p>Klik tombol/WhatsApp dari landing page.</p></div>
                    <div class="admin-card"><span class="admin-badge">Form Submit</span><h2><?= number_format((int)($totals['form_submit'] ?? 0)); ?></h2><p>Submit form custom landing page.</p></div>
                    <div class="admin-card"><span class="admin-badge">Lead</span><h2><?= number_format((int)($totals['lead_total'] ?? 0)); ?></h2><p>Form submit + inquiry yang masuk.</p></div>
                    <div class="admin-card"><span class="admin-badge">Order</span><h2><?= number_format((int)($totals['order'] ?? 0)); ?></h2><p>Order yang teratribusi ke landing page.</p></div>
                    <div class="admin-card"><span class="admin-badge">Rasio Hasil</span><h2><?= esc((string)($totals['conversion_rate'] ?? 0)); ?>%</h2><p>Lead/order dibanding kunjungan.</p></div>
                    <div class="admin-card"><span class="admin-badge">Rasio Klik Tombol</span><h2><?= esc((string)($totals['cta_rate'] ?? 0)); ?>%</h2><p>Rasio klik Tombol dari kunjungan.</p></div>
                    <div class="admin-card"><span class="admin-badge">Lead Rate</span><h2><?= esc((string)($totals['lead_rate'] ?? 0)); ?>%</h2><p>Rasio lead dari kunjungan.</p></div>
                </div>

                <div class="admin-card admin-table-card">
                    <div class="admin-form-head"><span class="admin-badge">Checklist</span><h2>Cara Baca Hasil Analisis</h2><p>Pakai data ini sebelum scale iklan atau duplikasi landing page.</p></div>
                    <ul class="admin-checklist">
                        <li>Rasio Hasil tinggi + kunjungan rendah: kandidat untuk ditambah traffic.</li>
                        <li>Kunjungan tinggi + tombol rendah: perbaiki headline, offer, dan posisi tombol.</li>
                        <li>Tombol tinggi + lead rendah: ringankan form atau arahkan ke WhatsApp.</li>
                        <li>Lead tinggi + order rendah: cek follow-up dan trust section.</li>
                    </ul>
                </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="lp-analytics-flow" hidden>
                <div class="admin-grid admin-grid--2 admin-lp-dashboard-grid">
                    <div class="admin-card admin-lp-alur-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Alur</span>
                            <h2>Kunjungan → Tombol → Lead → Order</h2>
                            <p>Dipakai untuk melihat bagian alur mana yang bocor.</p>
                        </div>
                        <div class="admin-lp-alur-list">
                            <?php foreach ((array)(($report['alur']['stages'] ?? [])) as $stage): ?>
                                <?php $rate = max(0, min(100, (float)($stage['rate'] ?? 0))); ?>
                                <div class="admin-lp-alur-row">
                                    <div><strong><?= esc((string)($stage['label'] ?? '')); ?></strong><span><?= esc((string)$rate); ?>%</span></div>
                                    <div class="admin-lp-progress"><i style="width:<?= esc((string)$rate); ?>%"></i></div>
                                    <small><?= number_format((int)($stage['value'] ?? 0)); ?> event</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-card admin-lp-insight-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Insight</span>
                            <h2>Rekomendasi Cepat</h2>
                            <p>Ringkasan otomatis berdasarkan angka alur saat ini.</p>
                        </div>
                        <div class="admin-lp-insight-list">
                            <?php foreach ((array)($report['insights'] ?? []) as $insight): ?>
                                <article class="admin-lp-insight admin-lp-insight--<?= esc((string)($insight['tone'] ?? 'info')); ?>">
                                    <strong><?= esc((string)($insight['title'] ?? 'Insight')); ?></strong>
                                    <p><?= esc((string)($insight['text'] ?? '')); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php $nextBoard = (array)($report['next_action_board'] ?? []); $focusPage = (array)($nextBoard['focus_page'] ?? []); ?>
                <div class="admin-card admin-lp-next-action-card admin-lp-next-action-card--<?= esc((string)($nextBoard['tone'] ?? 'info')); ?>">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Next Action Assistant</span>
                            <h2><?= esc((string)($nextBoard['title'] ?? 'Langkah berikutnya')); ?></h2>
                            <p><?= esc((string)($nextBoard['summary'] ?? 'Sistem membaca data existing dan menyusun prioritas kerja berikutnya.')); ?></p>
                        </div>
                        <div class="admin-lp-next-focus">
                            <span><?= esc((string)($nextBoard['focus_label'] ?? 'Fokus')); ?></span>
                            <strong><?= esc((string)($focusPage['title'] ?? 'LP utama')); ?></strong>
                            <?php if ((string)($focusPage['slug'] ?? '') !== ''): ?><small>/lp/<?= esc((string)$focusPage['slug']); ?></small><?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-lp-next-grid">
                        <div class="admin-lp-next-actions">
                            <?php foreach ((array)($nextBoard['quick_actions'] ?? []) as $action): ?>
                                <article class="admin-lp-next-action admin-lp-next-action--<?= esc((string)($action['tone'] ?? 'info')); ?>">
                                    <span><?= esc((string)($action['badge'] ?? 'Action')); ?></span>
                                    <strong><?= esc((string)($action['title'] ?? 'Action')); ?></strong>
                                    <p><?= esc((string)($action['text'] ?? '')); ?></p>
                                    <?php if ((string)($action['url'] ?? '') !== ''): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$action['url']); ?>"><?= esc((string)($action['button'] ?? 'Buka')); ?></a><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                            <?php if (empty($nextBoard['quick_actions'])): ?><p class="admin-muted">Belum ada action karena data analytics belum tersedia.</p><?php endif; ?>
                        </div>
                        <div class="admin-lp-next-side">
                            <div>
                                <strong>Playbook 7 Hari</strong>
                                <?php foreach ((array)($nextBoard['weekly_playbook'] ?? []) as $step): ?>
                                    <p><b><?= esc((string)($step['day'] ?? '')); ?></b> <?= esc((string)($step['task'] ?? '')); ?></p>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <strong>Signal Cepat</strong>
                                <?php $bestSignal = (array)($nextBoard['best_signal'] ?? []); $bestSource = (array)($nextBoard['best_source'] ?? []); $bestCampaign = (array)($nextBoard['best_campaign'] ?? []); ?>
                                <p>CTA: <?= esc((string)($bestSignal['label'] ?? 'Belum ada sinyal dominan')); ?><?= isset($bestSignal['cta_click']) ? ' · ' . number_format((int)$bestSignal['cta_click']) . ' klik' : ''; ?></p>
                                <p>Source: <?= esc((string)($bestSource['label'] ?? 'Belum ada source dominan')); ?><?= isset($bestSource['conversions']) ? ' · ' . number_format((int)$bestSource['conversions']) . ' conv' : ''; ?></p>
                                <p>Campaign: <?= esc((string)($bestCampaign['label'] ?? 'Belum ada campaign dominan')); ?></p>
                            </div>
                            <?php if (!empty($nextBoard['risk_flags'])): ?>
                                <div class="admin-lp-next-risk">
                                    <strong>Catatan Risiko</strong>
                                    <?php foreach ((array)$nextBoard['risk_flags'] as $risk): ?><p>• <?= esc((string)$risk); ?></p><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="admin-grid admin-grid--2 admin-lp-action-readiness-grid">
                    <div class="admin-card admin-lp-action-plan-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Action Plan</span>
                            <h2>Langkah Berikutnya</h2>
                            <p>Rekomendasi praktis dari data analytics existing, CTA signal, campaign, dan modul Tes A/B lama bila ada.</p>
                        </div>
                        <div class="admin-lp-action-list">
                            <?php foreach ((array)($report['action_plan'] ?? []) as $plan): ?>
                                <article class="admin-lp-action-item admin-lp-action-item--<?= esc((string)($plan['tone'] ?? 'info')); ?>">
                                    <span><?= esc((string)($plan['badge'] ?? 'Action')); ?></span>
                                    <strong><?= esc((string)($plan['title'] ?? 'Action Plan')); ?></strong>
                                    <p><?= esc((string)($plan['text'] ?? '')); ?></p>
                                    <?php if ((string)($plan['action'] ?? '') !== ''): ?><small><?= esc((string)$plan['action']); ?></small><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                            <?php if (empty($report['action_plan'])): ?><p class="admin-muted">Belum ada action plan karena data analytics belum tersedia.</p><?php endif; ?>
                        </div>
                    </div>
                    <?php $readiness = (array)($report['campaign_readiness'] ?? []); ?>
                    <div class="admin-card admin-lp-readiness-card admin-lp-readiness-card--<?= esc((string)($readiness['tone'] ?? 'warning')); ?>">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="admin-badge">Campaign Readiness</span>
                                <h2><?= esc((string)($readiness['label'] ?? 'Perlu dicek')); ?></h2>
                                <p>Checklist cepat sebelum LP dipakai untuk iklan atau traffic berbayar.</p>
                            </div>
                            <strong class="admin-lp-readiness-score"><?= number_format((int)($readiness['score'] ?? 0)); ?>%</strong>
                        </div>
                        <div class="admin-lp-readiness-meter"><i style="width:<?= esc((string)max(0, min(100, (int)($readiness['score'] ?? 0)))); ?>%"></i></div>
                        <div class="admin-lp-readiness-checks">
                            <?php foreach ((array)($readiness['checks'] ?? []) as $check): ?>
                                <div class="<?= !empty($check['ok']) ? 'is-ok' : 'is-warn'; ?>">
                                    <strong><?= !empty($check['ok']) ? '✓' : '•'; ?> <?= esc((string)($check['label'] ?? 'Checklist')); ?></strong>
                                    <span><?= esc((string)($check['message'] ?? '')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="admin-card admin-lp-trend-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Trend</span>
                            <h2>Trend Performa <?= ((string)($report['timeline_period'] ?? 'day') === 'month') ? 'Bulanan' : 'Harian'; ?></h2>
                            <p>Bar total menunjukkan seluruh aktivitas, bagian berwarna menunjukkan konversi/lead/order.</p>
                        </div>
                        <small>Data lengkap: <?= number_format((int)($report['raw_event_count'] ?? 0)); ?> · Ringkas: <?= number_format((int)($report['compact_event_count'] ?? 0)); ?></small>
                    </div>
                    <div class="admin-lp-trend-scroll">
                        <?php $timelineMax = max(1, (int)($report['timeline_max'] ?? 0)); ?>
                        <?php foreach ((array)($report['timeline'] ?? []) as $point): ?>
                            <?php
                                $totalPoint = (int)($point['total'] ?? 0);
                                $convPoint = (int)($point['conversions'] ?? 0);
                                $totalHeight = max(3, min(100, (int)round(($totalPoint / $timelineMax) * 100)));
                                $convHeight = max(0, min(100, (int)round(($convPoint / $timelineMax) * 100)));
                            ?>
                            <div class="admin-lp-trend-item" title="<?= esc((string)($point['label'] ?? '')); ?>: <?= number_format($totalPoint); ?> event">
                                <div class="admin-lp-trend-bar"><i style="height:<?= esc((string)$totalHeight); ?>%"></i><b style="height:<?= esc((string)$convHeight); ?>%"></b></div>
                                <small><?= esc((string)($point['label'] ?? '')); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="lp-analytics-traffic" hidden>
                <div class="admin-grid admin-grid--2 admin-lp-report-grid">
                    <div class="admin-card">
                        <div class="admin-form-head"><span class="admin-badge">Sumber Traffic</span><h2>Performa Sumber Traffic</h2><p>Ringkasan asal pengunjung dari promosi, iklan, atau website lain.</p></div>
                        <div class="admin-lp-sumber-list admin-lp-sumber-list--metric">
                            <?php foreach ((array)($report['sumber_breakdown'] ?? []) as $sumber): ?>
                                <div>
                                    <strong><?= esc((string)($sumber['label'] ?? 'Source')); ?></strong>
                                    <span><?= number_format((int)($sumber['page_kunjungan'] ?? 0)); ?> kunjungan · <?= number_format((int)($sumber['conversions'] ?? 0)); ?> conv · <?= esc((string)($sumber['conversion_rate'] ?? 0)); ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-card">
                        <div class="admin-form-head admin-form-head--split"><div><span class="admin-badge">Campaign</span><h2>UTM Campaign</h2><p>Ranking promosi berdasarkan kunjungan, klik, lead, dan order.</p></div><a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-analytics?' . http_build_query(array_merge($_GET, ['export' => 'promosis'])))); ?>">Export Campaign</a></div>
                        <div class="admin-lp-sumber-list admin-lp-sumber-list--metric">
                            <?php foreach (array_slice((array)($report['promosi_breakdown'] ?? []), 0, 8) as $promosi): ?>
                                <div>
                                    <strong><?= esc((string)($promosi['label'] ?? 'Campaign')); ?></strong>
                                    <span><?= number_format((int)($promosi['page_kunjungan'] ?? 0)); ?> kunjungan · <?= number_format((int)($promosi['lead_total'] ?? 0)); ?> lead · <?= number_format((int)($promosi['order'] ?? 0)); ?> order</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-card">
                        <div class="admin-form-head"><span class="admin-badge">Lead Segment</span><h2>Segmentasi Lead</h2><p>Ringkasan segment dari Form Builder Advanced.</p></div>
                        <div class="admin-lp-sumber-list">
                            <?php foreach ((array)($report['segments'] ?? []) as $segment => $count): ?>
                                <div><strong><?= esc((string)$segment); ?></strong><span><?= number_format((int)$count); ?></span></div>
                            <?php endforeach; ?>
                            <?php if (empty($report['segments'])): ?><div><strong>Belum ada segment</strong><span>0</span></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="lp-analytics-performance" hidden>
                <div class="admin-card admin-table-card admin-lp-signal-table-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><span class="admin-badge">CTA Signal</span><h2>Sinyal Tombol & Form per Blok</h2><p>Membaca label CTA dari blok LP seperti hero_cta, pricing_cta, countdown_cta, closing_cta, dan lead_form_submit tanpa membuat dashboard tracking baru.</p></div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-lp-signal-table">
                            <thead><tr><th>Sinyal</th><th>Klik Tombol</th><th>Form Submit</th><th>Lead</th><th>Rasio Hasil</th><th>Terbaru</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice((array)($report['cta_signal_breakdown'] ?? []), 0, 12) as $signal): ?>
                                    <tr>
                                        <td><strong><?= esc((string)($signal['label'] ?? 'CTA umum')); ?></strong></td>
                                        <td><?= number_format((int)($signal['cta_click'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($signal['form_submit'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($signal['lead_total'] ?? 0)); ?></td>
                                        <td><strong><?= esc((string)($signal['conversion_rate'] ?? 0)); ?>%</strong></td>
                                        <td><small><?= esc((string)($signal['latest_at'] ?? '')); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($report['cta_signal_breakdown'])): ?><tr><td colspan="6">Belum ada data sinyal CTA pada rentang ini.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-card admin-table-card admin-lp-ab-table-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><span class="admin-badge">Tes A/B Existing</span><h2>Performa Variasi Tombol/Form</h2><p>Membaca data dari modul A/B yang sudah ada. Versi ini tidak membuat A/B testing baru, hanya memperjelas insight dari data existing.</p></div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-lp-ab-table">
                            <thead><tr><th>LP</th><th>Test</th><th>Variasi</th><th>Kunjungan</th><th>Tombol</th><th>Lead</th><th>Order</th><th>Rasio Klik Tombol</th><th>Rasio Hasil</th><th>Terbaru</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice((array)($report['ab_breakdown'] ?? []), 0, 60) as $ab): ?>
                                    <tr>
                                        <td><strong><?= esc((string)($ab['slug'] ?? '')); ?></strong><br><small><?= esc((string)($ab['test_name'] ?? '')); ?></small></td>
                                        <td><?= esc(strtoupper((string)($ab['test_type'] ?? '-'))); ?><br><small><?= esc((string)($ab['test_id'] ?? '')); ?></small></td>
                                        <td><span class="admin-badge"><?= esc(strtoupper((string)($ab['variasi'] ?? '-'))); ?></span><br><small><?= esc((string)($ab['variasi_label'] ?? '')); ?></small></td>
                                        <td><?= number_format((int)($ab['page_kunjungan'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($ab['cta_click'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($ab['lead_total'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($ab['order'] ?? 0)); ?></td>
                                        <td><?= esc((string)($ab['cta_rate'] ?? 0)); ?>%</td>
                                        <td><strong><?= esc((string)($ab['conversion_rate'] ?? 0)); ?>%</strong></td>
                                        <td><small><?= esc((string)($ab['latest_at'] ?? '')); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($report['ab_breakdown'])): ?><tr><td colspan="10">Belum ada data Tes A/B pada rentang ini.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-card admin-table-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><span class="admin-badge">Per Landing Page</span><h2>Performa Landing Page</h2><p>Ranking landing page berdasarkan kunjungan, klik tombol, lead, order, dan rasio hasil.</p></div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-lp-analytics-table">
                            <thead><tr><th>Landing Page</th><th>Status</th><th>Kunjungan</th><th>Tombol</th><th>Form</th><th>Lead</th><th>Order</th><th>Rasio Klik Tombol</th><th>Rasio Hasil</th><th>Sumber Top</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ((array)($report['rows'] ?? []) as $row): ?>
                                    <?php $sumbers = (array)($row['sumbers'] ?? []); $topSource = array_key_first($sumbers) ?: 'Direct'; ?>
                                    <tr>
                                        <td><strong><?= esc((string)($row['title'] ?? '')); ?></strong><br><small><?= esc('/lp/' . (string)($row['slug'] ?? '')); ?></small></td>
                                        <td><span class="admin-badge admin-badge--<?= esc((string)($row['status'] ?? 'draft')); ?>"><?= esc((string)($row['status'] ?? '-')); ?></span></td>
                                        <td><?= number_format((int)($row['page_kunjungan'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($row['cta_click'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($row['form_submit'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($row['lead_total'] ?? 0)); ?></td>
                                        <td><?= number_format((int)($row['order'] ?? 0)); ?></td>
                                        <td><?= esc((string)($row['cta_rate'] ?? 0)); ?>%</td>
                                        <td><strong><?= esc((string)($row['conversion_rate'] ?? 0)); ?>%</strong></td>
                                        <td><?= esc((string)$topSource); ?></td>
                                        <td class="admin-table-actions"><a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-analytics?' . http_build_query(array_merge($_GET, ['lp' => (string)($row['slug'] ?? '')])))); ?>">Detail</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($report['rows'])): ?><tr><td colspan="11">Belum ada data landing page pada rentang ini.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-card admin-table-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><span class="admin-badge">Lead Report</span><h2>Landing Page Lead Report</h2><p>Nama, WhatsApp, email, asal landing page, promosi, segment, dan status follow-up.</p></div>
                        <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/landing-page-analytics?' . http_build_query(array_merge($_GET, ['export' => 'leads'])))); ?>">Export Lead</a>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-lp-leads-table">
                            <thead><tr><th>Waktu</th><th>Nama</th><th>WhatsApp</th><th>Email</th><th>LP/Form</th><th>Segment</th><th>A/B</th><th>Campaign</th><th>Sumber</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice((array)($report['leads'] ?? []), 0, 120) as $lead): ?>
                                    <tr>
                                        <td><?= esc((string)($lead['time'] ?? '-')); ?></td>
                                        <td><strong><?= esc((string)($lead['name'] ?? '-')); ?></strong><br><small><?= esc((string)($lead['need'] ?? '')); ?></small></td>
                                        <td><?= esc((string)($lead['phone'] ?? '')); ?></td>
                                        <td><?= esc((string)($lead['email'] ?? '')); ?></td>
                                        <td><?= esc((string)($lead['landing_title'] ?? $lead['landing_slug'] ?? '')); ?><br><small><?= esc((string)($lead['form_name'] ?? '')); ?></small></td>
                                        <td><?= esc((string)($lead['lead_segment'] ?? '')); ?><br><small><?= esc((string)($lead['lead_priority'] ?? '')); ?><?= ((string)($lead['lead_tags'] ?? '') !== '') ? ' · ' . esc((string)$lead['lead_tags']) : ''; ?></small></td>
                                        <td><?= esc(strtoupper((string)($lead['ab_variasi'] ?? ''))); ?><br><small><?= esc((string)($lead['ab_variasi_label'] ?? $lead['ab_test_type'] ?? '')); ?></small></td>
                                        <td><?= esc((string)($lead['promosi'] ?? '')); ?></td>
                                        <td><?= esc((string)($lead['sumber_bucket'] ?? 'Direct')); ?><br><small><?= esc(trim((string)($lead['utm_sumber'] ?? '') . ' / ' . (string)($lead['utm_medium'] ?? ''), ' /')); ?></small></td>
                                        <td><span class="admin-badge"><?= esc((string)($lead['status'] ?? 'Baru')); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($report['leads'])): ?><tr><td colspan="10">Belum ada lead dari landing page pada rentang ini.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
