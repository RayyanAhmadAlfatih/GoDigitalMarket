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
    redirect_302('admin/data-health');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !admin_panel_logged_in()) {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        $_SESSION['admin_articles_logged_in'] = true;
        activity_log_record('login', 'admin', null, 'Admin login ke Cek Sistem.', ['area' => 'data_health']);
        redirect_302('admin/data-health');
    } else {
        $error = 'Password admin salah.';
        activity_log_record('login_failed', 'admin', null, 'Percobaan login Cek Sistem gagal.', ['area' => 'data_health']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_panel_logged_in() && isset($_POST['health_action'])) {
    require_csrf();
    $healthAction = (string)($_POST['health_action'] ?? '');
    if ($healthAction === 'clear_cache') {
        $deleted = function_exists('maintenance_clear_cache_files') ? maintenance_clear_cache_files() : 0;
        if (!function_exists('maintenance_clear_cache_files')) {
            foreach (glob(CACHE_PATH . '/*') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }
            }
        }
        activity_log_record('clear_cache', 'data_health', null, 'Cache dibersihkan dari halaman Cek Sistem.', ['deleted_files' => $deleted]);
        redirect_302('admin/data-health?message=' . rawurlencode('Cache dibersihkan: ' . $deleted . ' file dihapus.'));
    }
}

$loggedIn = admin_panel_logged_in();
$report = $loggedIn ? data_health_report() : [];
$summary = (array)($report['summary'] ?? []);
$overall = (string)($summary['overall'] ?? 'info');
$counts = (array)($summary['counts'] ?? []);
$statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['ok', 'warning', 'error', 'info'], true)) {
    $statusFilter = '';
}
$checks = (array)($report['checks'] ?? []);
if ($statusFilter !== '') {
    $checks = array_values(array_filter($checks, static fn($row): bool => (string)($row['status'] ?? 'info') === $statusFilter));
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Admin Cek Sistem - ' . SITE_NAME,
    'description' => 'Audit kesehatan data dan SEO website produk dan layanan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-data-health-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Pemeriksaan Data Website</div>
                <h1>Audit Kesehatan Data Website</h1>
                <p>Cek data produk, artikel, order, pembayaran, analytics, file SEO, dan kesiapan website sebelum live.</p>
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
                        <h2>Masuk Cek Sistem</h2>
                        <p>Gunakan password admin yang sama dengan dashboard produk dan order.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/data-health'); ?>

                <div class="admin-grid admin-grid--stats">
                    <div class="admin-card"><span class="admin-badge">Overall</span><h2><?= esc(strtoupper($overall)); ?></h2><p>Hasil akhir audit data.</p></div>
                    <div class="admin-card"><span class="admin-badge">OK</span><h2><?= (int)($counts['ok'] ?? 0); ?></h2><p>Check aman.</p></div>
                    <div class="admin-card"><span class="admin-badge">Warning</span><h2><?= (int)($counts['warning'] ?? 0); ?></h2><p>Perlu dicek, belum fatal.</p></div>
                    <div class="admin-card"><span class="admin-badge">Error</span><h2><?= (int)($counts['error'] ?? 0); ?></h2><p>Perlu diperbaiki.</p></div>
                </div>

                <div class="admin-card" style="margin-top:18px">
                    <div class="admin-form-head admin-form-head--split">
                        <div><h2>Ringkasan Data</h2><p>Generated at: <?= esc((string)($report['generated_at'] ?? '-')); ?> | ENV: <?= esc((string)($report['environment'] ?? '-')); ?></p></div>
                        <form method="post">
                            <?= csrf_field(); ?>
                            <button class="admin-btn admin-btn--soft" type="submit" name="health_action" value="clear_cache">Clear Cache</button>
                        </form>
                    </div>
                    <?php $dataCounts = (array)($report['counts'] ?? []); ?>
                    <div class="admin-grid admin-grid--stats">
                        <?php foreach ($dataCounts as $label => $count): ?>
                            <div><span class="admin-source-badge"><?= esc(str_replace('_', ' ', (string)$label)); ?></span><strong style="display:block;font-size:1.4rem;margin-top:6px"><?= (int)$count; ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="admin-card admin-health-card" style="margin-top:18px">
                    <div class="admin-form-head admin-form-head--split">
                        <div><h2>Detail Check</h2><p>Status OK, warning, error, dan info untuk file, data penting, route, runtime folder, dan live readiness.</p></div>
                        <div class="admin-toolbar__actions">
                            <a class="admin-btn <?= $statusFilter === '' ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/data-health'); ?>">Semua</a>
                            <?php foreach (['error' => 'Error', 'warning' => 'Warning', 'ok' => 'OK', 'info' => 'Info'] as $statusKey => $statusLabel): ?>
                                <a class="admin-btn <?= $statusFilter === $statusKey ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/data-health?status=' . $statusKey); ?>"><?= esc($statusLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-table-wrap admin-table-wrap--comfortable">
                        <table class="admin-table admin-health-table">
                            <thead><tr><th>Status</th><th>Check</th><th>Pesan</th><th>Meta</th></tr></thead>
                            <tbody>
                            <?php foreach ($checks as $check): ?>
                                <?php $status = (string)($check['status'] ?? 'info'); ?>
                                <tr>
                                    <td><span class="admin-status-pill admin-status-pill--<?= esc($status); ?>"><?= esc(strtoupper($status)); ?></span></td>
                                    <td><strong><?= esc((string)($check['label'] ?? '-')); ?></strong></td>
                                    <td><?= esc((string)($check['message'] ?? '-')); ?></td>
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
