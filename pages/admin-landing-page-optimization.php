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
    redirect_302('admin/landing-page-optimization');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !admin_panel_logged_in()) {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        $_SESSION['admin_articles_logged_in'] = true;
        if (function_exists('activity_log_record')) {
            activity_log_record('login', 'admin', null, 'Admin login ke Optimasi Landing Page.', ['area' => 'landing_page_optimization']);
        }
        redirect_302('admin/landing-page-optimization');
    } else {
        $error = 'Password admin salah.';
        if (function_exists('activity_log_record')) {
            activity_log_record('login_failed', 'admin', null, 'Percobaan login Optimasi Landing Page gagal.', ['area' => 'landing_page_optimization']);
        }
    }
}

$loggedIn = admin_panel_logged_in();
$rangeInfo = function_exists('landing_page_analytics_date_filter') ? landing_page_analytics_date_filter($_GET) : ['range' => '30', 'days' => 30, 'filters' => []];
$range = (string)($rangeInfo['range'] ?? '30');
$days = (int)($rangeInfo['days'] ?? 30);
$filters = (array)($rangeInfo['filters'] ?? []);
$lpSlug = slugify((string)($_GET['lp'] ?? ''));
if ($lpSlug !== '') {
    $filters['lp_slug'] = $lpSlug;
}
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
if ($statusFilter !== '') {
    $filters['status'] = $statusFilter;
}
$issueFilter = trim((string)($_GET['issue'] ?? 'all'));
if ($issueFilter !== '') {
    $filters['issue'] = $issueFilter;
}

$report = $loggedIn && function_exists('landing_page_optimization_report') ? landing_page_optimization_report($days, $filters) : ['summary' => [], 'items' => [], 'recommendations' => [], 'media_readiness' => []];
$summary = (array)($report['summary'] ?? []);
$pages = function_exists('landing_page_all') ? landing_page_all(true) : [];

if (!function_exists('admin_lp_optimization_send_csv')) {
    function admin_lp_optimization_send_csv(string $filename, array $headers, array $rows): void
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

if ($loggedIn && strtolower(trim((string)($_GET['export'] ?? ''))) === 'optimization') {
    $rows = function_exists('landing_page_optimization_csv_rows') ? landing_page_optimization_csv_rows($report) : [];
    $headers = ['title', 'slug', 'status', 'score', 'critical_count', 'issue_count', 'page_kunjungan', 'cta_click', 'lead_total', 'order', 'cta_rate', 'lead_rate', 'conversion_rate', 'issues', 'recommendations', 'edit_url', 'analytics_url'];
    admin_lp_optimization_send_csv('landing-page-optimization-' . date('Ymd-His') . '.csv', $headers, $rows);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Optimasi Landing Page - ' . SITE_NAME,
    'description' => 'Pusat optimasi landing page berbasis builder, analytics, A/B testing, AI Copy Assistant, dan media SEO readiness.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-lp-optimization-template">
    <section class="admin-hero admin-lp-optimization-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Optimasi Landing Page</div>
                <h1>Optimasi Landing Page</h1>
                <p>Pantau landing page mana yang perlu diperbaiki, Tombol mana yang paling efektif, dan konten mana yang perlu dipoles.</p>
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
                        <h2>Masuk Optimasi Landing Page</h2>
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
                <?php admin_panel_render_nav('admin/landing-page-optimization'); ?>

                <form method="get" class="admin-card admin-lp-optimizer-filter-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Filter Optimization</span>
                            <h2>Prioritas Perbaikan Landing Page</h2>
                            <p>Pakai filter ini untuk melihat Landing Page published, Landing Page bermasalah, Landing Page siap scale, atau detail satu Landing Page tertentu.</p>
                        </div>
                        <div class="admin-lp-filter-actions">
                            <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-optimization?' . http_build_query(array_merge($_GET, ['export' => 'optimization'])))); ?>">Export Optimasi</a>
                        </div>
                    </div>
                    <div class="admin-form-row admin-form-row--4 admin-lp-filter-grid">
                        <label>Rentang
                            <select name="range">
                                <?php foreach (['7' => '7 hari', '14' => '14 hari', '30' => '30 hari', '60' => '60 hari', '90' => '90 hari', '180' => '180 hari', '365' => '1 tahun', 'custom' => 'Custom tanggal'] as $key => $label): ?>
                                    <option value="<?= esc((string)$key); ?>" <?= $range === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
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
                                    <option value="<?= esc((string)($page['slug'] ?? '')); ?>" <?= $lpSlug === (string)($page['slug'] ?? '') ? 'selected' : ''; ?>><?= esc((string)($page['title'] ?? $page['slug'] ?? 'Landing Page')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Status
                            <select name="status">
                                <?php foreach (['all' => 'Semua status', 'published' => 'Published', 'draft' => 'Draft', 'archived' => 'Archived'] as $key => $label): ?>
                                    <option value="<?= esc((string)$key); ?>" <?= $statusFilter === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Tipe Insight
                            <select name="issue">
                                <?php foreach (['all' => 'Semua', 'critical' => 'Issue merah', 'needs_fix' => 'Perlu diperbaiki', 'ready' => 'Siap scale'] as $key => $label): ?>
                                    <option value="<?= esc((string)$key); ?>" <?= $issueFilter === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </form>

                <div class="admin-lp-opt-kpi-grid">
                    <article class="admin-card admin-lp-opt-kpi"><span class="admin-badge">Avg Score</span><strong><?= number_format((int)($summary['avg_score'] ?? 0)); ?>%</strong><small>Rata-rata health score landing page.</small></article>
                    <article class="admin-card admin-lp-opt-kpi"><span class="admin-badge">Siap Scale</span><strong><?= number_format((int)($summary['ready'] ?? 0)); ?></strong><small>Landing Page dengan score 82 ke atas.</small></article>
                    <article class="admin-card admin-lp-opt-kpi"><span class="admin-badge">Perlu Fix</span><strong><?= number_format((int)($summary['needs_fix'] ?? 0)); ?></strong><small>Landing Page yang punya checklist perbaikan.</small></article>
                    <article class="admin-card admin-lp-opt-kpi"><span class="admin-badge">Issue Merah</span><strong><?= number_format((int)($summary['critical'] ?? 0)); ?></strong><small>Prioritas sebelum budget iklan dinaikkan.</small></article>
                </div>

                <div class="admin-grid admin-grid--2 admin-lp-opt-insights">
                    <div class="admin-card">
                        <div class="admin-form-head"><span class="admin-badge">Smart Recommendation</span><h2>Action Utama</h2><p>Rekomendasi global dari gabungan data builder, analytics, modul Tes A/B existing, dan SEO.</p></div>
                        <ul class="admin-checklist">
                            <?php foreach ((array)($report['recommendations'] ?? []) as $recommendation): ?>
                                <li><?= esc((string)$recommendation); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="admin-card admin-lp-opt-action-queue">
                        <div class="admin-form-head"><span class="admin-badge">Next Action Queue</span><h2>Prioritas Minggu Ini</h2><p>Urutan kerja praktis dari optimization existing. Tidak membuat modul Tes A/B baru, hanya mengarahkan action berikutnya.</p></div>
                        <div class="admin-lp-opt-queue-list">
                            <?php foreach ((array)($report['action_queue'] ?? []) as $todo): ?>
                                <article class="admin-lp-opt-queue-item admin-lp-opt-queue-item--<?= esc((string)($todo['tone'] ?? 'info')); ?>">
                                    <div>
                                        <span><?= esc((string)($todo['badge'] ?? 'Action')); ?></span>
                                        <strong><?= esc((string)($todo['title'] ?? 'Action')); ?></strong>
                                        <p><?= esc((string)($todo['lp_title'] ?? 'Landing Page')); ?><?= (string)($todo['slug'] ?? '') !== '' ? ' · /lp/' . esc((string)$todo['slug']) : ''; ?></p>
                                        <small><?= esc((string)($todo['action'] ?? '')); ?></small>
                                    </div>
                                    <div class="admin-lp-opt-queue-actions">
                                        <b><?= number_format((int)($todo['score'] ?? 0)); ?>%</b>
                                        <a class="admin-btn admin-btn--primary" href="<?= esc((string)($todo['edit_url'] ?? '#')); ?>">Edit</a>
                                        <a class="admin-btn admin-btn--soft" href="<?= esc((string)($todo['analytics_url'] ?? '#')); ?>">Analytics</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <?php if (empty($report['action_queue'])): ?><p class="admin-muted">Belum ada prioritas karena data landing page belum tersedia.</p><?php endif; ?>
                        </div>
                    </div>
                    <?php $media = (array)($report['media_readiness'] ?? []); $mediaSummary = (array)($media['summary'] ?? []); ?>
                    <div class="admin-card admin-lp-media-readiness">
                        <div class="admin-form-head admin-form-head--split">
                            <div><span class="admin-badge">Aktif</span><h2>Media/Asset SEO Readiness</h2><p><?= esc((string)($media['note'] ?? 'Media & Asset SEO Manager sudah aktif sebagai upgrade dari Media Library lama.')); ?></p></div>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/media-library')); ?>">Buka Media & Asset SEO</a>
                        </div>
                        <div class="admin-lp-media-stats">
                            <div><strong><?= number_format((int)($media['readiness_percent'] ?? 0)); ?>%</strong><small>Readiness</small></div>
                            <div><strong><?= number_format((int)($mediaSummary['total'] ?? 0)); ?></strong><small>Total asset</small></div>
                            <div><strong><?= number_format((int)($mediaSummary['missing_alt'] ?? 0)); ?></strong><small>Missing alt</small></div>
                            <div><strong><?= number_format((int)($mediaSummary['large'] ?? 0)); ?></strong><small>Large image</small></div>
                        </div>
                        <p class="admin-muted">Pemeriksaan media sudah aktif untuk membantu merapikan gambar, alt text, nama file, dan performa halaman.</p>
                    </div>
                </div>

                <div class="admin-card admin-table-card admin-lp-opt-table-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><span class="admin-badge">Optimization Ranking</span><h2>Prioritas Optimasi Landing Page</h2><p>Urutan otomatis berdasarkan score, issue merah, dan performa traffic.</p></div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-lp-opt-table">
                            <thead><tr><th>Landing Page</th><th>Score</th><th>Performa</th><th>Issue Utama</th><th>Rekomendasi</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ((array)($report['items'] ?? []) as $item): ?>
                                    <?php $metrics = (array)($item['metrics'] ?? []); $issues = (array)($item['issues'] ?? []); $recs = (array)($item['recommendations'] ?? []); ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc((string)($item['title'] ?? 'Landing Page')); ?></strong><br>
                                            <small><?= esc('/lp/' . (string)($item['slug'] ?? '')); ?> · <?= esc((string)($item['status'] ?? '-')); ?></small>
                                        </td>
                                        <td>
                                            <span class="admin-lp-score admin-lp-score--<?= esc((string)($item['tone'] ?? 'warning')); ?>"><?= number_format((int)($item['score'] ?? 0)); ?>%</span><br>
                                            <small><?= number_format((int)($item['issue_count'] ?? 0)); ?> issue</small>
                                        </td>
                                        <td>
                                            <small>Kunjungan: <strong><?= number_format((int)($metrics['page_kunjungan'] ?? 0)); ?></strong></small><br>
                                            <small>Tombol: <?= number_format((int)($metrics['cta_click'] ?? 0)); ?> · Lead: <?= number_format((int)($metrics['lead_total'] ?? 0)); ?> · Order: <?= number_format((int)($metrics['order'] ?? 0)); ?></small><br>
                                            <small>Rasio Hasil: <strong><?= esc((string)($metrics['conversion_rate'] ?? 0)); ?>%</strong></small>
                                        </td>
                                        <td>
                                            <?php foreach (array_slice($issues, 0, 3) as $issue): ?>
                                                <span class="admin-lp-issue admin-lp-issue--<?= esc((string)($issue['severity'] ?? 'info')); ?>"><?= esc((string)($issue['title'] ?? 'Issue')); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (!$issues): ?><span class="admin-badge">Aman</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <ul class="admin-lp-opt-mini-list">
                                                <?php foreach (array_slice($recs, 0, 3) as $rec): ?>
                                                    <li><?= esc((string)$rec); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                        <td class="admin-table-actions">
                                            <a class="admin-btn admin-btn--primary" href="<?= esc((string)($item['edit_url'] ?? '#')); ?>">Edit</a>
                                            <a class="admin-btn admin-btn--soft" href="<?= esc((string)($item['analytics_url'] ?? '#')); ?>">Analytics</a>
                                            <a class="admin-btn admin-btn--soft" href="<?= esc((string)($item['url'] ?? '#')); ?>" target="_blank" rel="noopener">Preview</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($report['items'])): ?><tr><td colspan="6">Belum ada landing page yang cocok dengan filter ini.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-grid admin-grid--2">
                    <div class="admin-card">
                        <div class="admin-form-head"><span class="admin-badge">Checklist Publish</span><h2>Checklist Sebelum Scale</h2><p>Gunakan ini sebagai aturan cepat sebelum traffic iklan dinaikkan.</p></div>
                        <ul class="admin-checklist">
                            <li>Score Landing Page minimal 82 dan tidak ada issue merah.</li>
                            <li>Hero, Tombol, Form Custom, FAQ, dan SEO Pack sudah terisi.</li>
                            <li>Tes A/B existing aktif hanya jika variasi A/B sudah lengkap.</li>
                            <li>Gambar utama punya alt text dan tidak terlalu besar.</li>
                        </ul>
                    </div>
                    <div class="admin-card">
                        <div class="admin-form-head"><span class="admin-badge">Selesai</span><h2>Media & Asset SEO Manager Aktif</h2><p>Fondasi Sistem sudah dinaikkan ke manager yang lebih dalam.</p></div>
                        <ul class="admin-checklist">
                            <li>Asset score sudah membaca ukuran, format, alt, filename, dan usage.</li>
                            <li>Mapping asset sudah mencakup landing page, produk, dan artikel.</li>
                            <li>Saran alt text dan filename SEO tersedia per asset.</li>
                            <li>Rekomendasi WebP/compression dibuat aman tanpa rename otomatis file lama.</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
