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
    redirect_302('admin/production-readiness');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !admin_panel_logged_in()) {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        $_SESSION['admin_articles_logged_in'] = true;
        if (function_exists('activity_log_record')) {
            activity_log_record('login', 'admin', null, 'Admin login ke Checklist Website Siap Online.', ['area' => 'production_readiness']);
        }
        redirect_302('admin/production-readiness');
    } else {
        $error = 'Password admin salah.';
        if (function_exists('activity_log_record')) {
            activity_log_record('login_failed', 'admin', null, 'Percobaan login Checklist Website Siap Online gagal.', ['area' => 'production_readiness']);
        }
    }
}

$loggedIn = admin_panel_logged_in();
$report = $loggedIn && function_exists('production_readiness_report') ? production_readiness_report() : [];
$summary = (array)($report['summary'] ?? []);
$counts = (array)($summary['counts'] ?? []);
$gate = (array)($report['gate'] ?? []);
$statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['ok', 'warning', 'error', 'info'], true)) {
    $statusFilter = '';
}
$checks = (array)($report['checks'] ?? []);
if ($statusFilter !== '') {
    $checks = array_values(array_filter($checks, static fn(array $row): bool => (string)($row['status'] ?? 'info') === $statusFilter));
}

if (!function_exists('admin_production_readiness_send_csv')) {
    function admin_production_readiness_send_csv(string $filename, array $headers, array $rows): void
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

if ($loggedIn && strtolower(trim((string)($_GET['export'] ?? ''))) === 'readiness') {
    $rows = function_exists('production_readiness_csv_rows') ? production_readiness_csv_rows($report) : [];
    admin_production_readiness_send_csv('production-readiness-v29-50-' . date('Ymd-His') . '.csv', ['status', 'check', 'message', 'action', 'meta'], $rows);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Kesiapan Website - ' . SITE_NAME,
    'description' => 'Production Release Candidate checklist untuk keamanan, SEO, runtime, data, backup, dan deployment website produk layanan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-production-readiness-shell">
    <section class="admin-hero admin-production-readiness-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Checklist Website Siap Online</div>
                <h1>Checklist Website Siap Online</h1>
                <p>Cek keamanan, folder penyimpanan, SEO, media, backup, dan langkah penting sebelum website dipublikasikan.</p>
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
                        <h2>Masuk Kesiapan Website</h2>
                        <p>Gunakan password admin yang sama. Halaman ini hanya untuk admin sebelum upload/live production.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/production-readiness'); ?>

                <div class="admin-card admin-production-gate-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Go-live Gate</span>
                            <h2><?= esc((string)($gate['label'] ?? 'Readiness Check')); ?></h2>
                            <p><?= esc((string)($gate['message'] ?? 'Checklist final siap dibaca.')); ?></p>
                            <small>Generated: <?= esc((string)($report['generated_at'] ?? '-')); ?> · ENV: <?= esc((string)($report['environment'] ?? '-')); ?> · SITE: <?= esc((string)($report['site_url'] ?? '-')); ?></small>
                        </div>
                        <div class="admin-toolbar__actions">
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/data-health')); ?>">Buka Cek Sistem</a>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/maintenance')); ?>">Backup</a>
                            <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/production-readiness?export=readiness')); ?>">Download CSV</a>
                        </div>
                    </div>
                    <div class="admin-grid admin-grid--stats">
                        <div><span class="admin-badge">Score</span><h2><?= (int)($report['score'] ?? 0); ?>%</h2><p>Skor kesiapan production.</p></div>
                        <div><span class="admin-badge">OK</span><h2><?= (int)($counts['ok'] ?? 0); ?></h2><p>Checklist aman.</p></div>
                        <div><span class="admin-badge">Warning</span><h2><?= (int)($counts['warning'] ?? 0); ?></h2><p>Perlu review.</p></div>
                        <div><span class="admin-badge">Error</span><h2><?= (int)($counts['error'] ?? 0); ?></h2><p>Blocking issue.</p></div>
                    </div>
                </div>

                <div class="admin-card" style="margin-top:18px">
                    <div class="admin-form-head">
                        <span class="admin-badge">Release Summary</span>
                        <h2>Ringkasan Source & Data</h2>
                        <p>Snapshot singkat untuk memastikan fitur utama Sistem sudah terbaca sebelum live.</p>
                    </div>
                    <?php $dataCounts = (array)($report['counts'] ?? []); ?>
                    <div class="admin-grid admin-grid--stats">
                        <div><span class="admin-source-badge">Produk</span><strong style="display:block;font-size:1.4rem;margin-top:6px"><?= (int)($dataCounts['products'] ?? 0); ?></strong></div>
                        <div><span class="admin-source-badge">Artikel</span><strong style="display:block;font-size:1.4rem;margin-top:6px"><?= (int)($dataCounts['articles'] ?? 0); ?></strong></div>
                        <div><span class="admin-source-badge">Landing Page</span><strong style="display:block;font-size:1.4rem;margin-top:6px"><?= (int)($dataCounts['landing_pages'] ?? 0); ?></strong></div>
                        <div><span class="admin-source-badge">LP Published</span><strong style="display:block;font-size:1.4rem;margin-top:6px"><?= (int)($dataCounts['landing_pages_published'] ?? 0); ?></strong></div>
                        <div><span class="admin-source-badge">Feature Ready</span><strong style="display:block;font-size:1.4rem;margin-top:6px"><?= (int)($dataCounts['features_ready'] ?? 0); ?>/<?= (int)($dataCounts['features_total'] ?? 0); ?></strong></div>
                    </div>
                </div>

                <div class="admin-card" style="margin-top:18px">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Launch Steps</span>
                            <h2>Urutan Aman Upload Website</h2>
                            <p>Ikuti urutan ini supaya update final tidak mengganggu data existing.</p>
                        </div>
                    </div>
                    <ol class="admin-launch-steps">
                        <?php foreach ((array)($report['launch_steps'] ?? []) as $step): ?>
                            <li><?= esc((string)$step); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <div class="admin-card admin-health-card" style="margin-top:18px">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Final Checklist</span>
                            <h2>Detail Kesiapan Website</h2>
                            <p>Filter berdasarkan status untuk fokus pada warning/error sebelum live.</p>
                        </div>
                        <div class="admin-toolbar__actions">
                            <a class="admin-btn <?= $statusFilter === '' ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= esc(url('admin/production-readiness')); ?>">Semua</a>
                            <?php foreach (['error' => 'Error', 'warning' => 'Warning', 'ok' => 'OK', 'info' => 'Info'] as $statusKey => $statusLabel): ?>
                                <a class="admin-btn <?= $statusFilter === $statusKey ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= esc(url('admin/production-readiness?status=' . $statusKey)); ?>"><?= esc($statusLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-table-wrap admin-table-wrap--comfortable">
                        <table class="admin-table admin-health-table">
                            <thead><tr><th>Status</th><th>Check</th><th>Pesan</th><th>Action</th><th>Meta</th></tr></thead>
                            <tbody>
                            <?php foreach ($checks as $check): ?>
                                <?php $status = (string)($check['status'] ?? 'info'); ?>
                                <tr>
                                    <td><span class="admin-status-pill admin-status-pill--<?= esc($status); ?>"><?= esc(strtoupper($status)); ?></span></td>
                                    <td><strong><?= esc((string)($check['label'] ?? '-')); ?></strong></td>
                                    <td><?= esc((string)($check['message'] ?? '-')); ?></td>
                                    <td><?= esc((string)($check['action'] ?? '-')); ?></td>
                                    <td><pre class="admin-meta-pre"><?= esc(json_encode($check['meta'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></pre></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
