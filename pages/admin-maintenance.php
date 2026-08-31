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
    redirect_302('admin/maintenance');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !admin_panel_logged_in()) {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        $_SESSION['admin_articles_logged_in'] = true;
        activity_log_record('login', 'admin', null, 'Admin login ke Perawatan Website.', ['area' => 'maintenance']);
        redirect_302('admin/maintenance');
    } else {
        $error = 'Password admin salah.';
        activity_log_record('login_failed', 'admin', null, 'Percobaan login Perawatan Website gagal.', ['area' => 'maintenance']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_panel_logged_in() && isset($_POST['maintenance_action'])) {
    require_csrf();
    $maintenanceAction = (string)($_POST['maintenance_action'] ?? '');

    if ($maintenanceAction === 'download_backup') {
        $backup = maintenance_create_backup();
        if (!empty($backup['success']) && is_file((string)($backup['path'] ?? ''))) {
            activity_log_record('download_backup', 'maintenance', null, 'Admin membuat dan mengunduh backup data.', [
                'filename' => $backup['filename'] ?? '',
                'files_count' => $backup['files_count'] ?? 0,
                'size_bytes' => $backup['size_bytes'] ?? 0,
            ]);

            $path = (string)$backup['path'];
            $filename = (string)($backup['filename'] ?? basename($path));
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            header('Content-Length: ' . (string)((int)@filesize($path)));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            readfile($path);
            exit;
        }

        $error = (string)($backup['message'] ?? 'Backup gagal dibuat.');
        activity_log_record('download_backup_failed', 'maintenance', null, 'Gagal membuat backup data.', ['message' => $error]);
    }

    if ($maintenanceAction === 'clear_cache') {
        $deleted = maintenance_clear_cache_files();
        activity_log_record('clear_cache', 'maintenance', null, 'Cache dibersihkan dari Perawatan Website.', ['deleted_files' => $deleted]);
        redirect_302('admin/maintenance?message=' . rawurlencode('Cache dibersihkan: ' . $deleted . ' file dihapus. Backup ZIP tidak ikut dihapus.'));
    }
}

$loggedIn = admin_panel_logged_in();
$overview = $loggedIn ? maintenance_storage_overview() : [];
$backupFiles = $loggedIn ? maintenance_collect_files() : [];
$backupSize = 0;
foreach ($backupFiles as $file) {
    $backupSize += is_file($file) ? (int)@filesize($file) : 0;
}
$recentBackups = $loggedIn ? maintenance_recent_backups(8) : [];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Backup & Restore - Admin - ' . SITE_NAME,
    'description' => 'Maintenance center untuk backup data, storage overview, dan cache cleanup.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-maintenance-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem</div>
                <h1>Backup & Restore</h1>
                <p>Export backup data penting sebelum update website. Area ini juga memuat tools maintenance ringan seperti cek storage dan bersihkan cache.</p>
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
                        <h2>Masuk Backup & Restore</h2>
                        <p>Gunakan password admin yang sama. Backup tidak menyertakan file rahasia seperti .env atau token penting.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/maintenance'); ?>

                <div class="admin-grid admin-grid--stats admin-maintenance-summary">
                    <div class="admin-card"><span class="admin-badge">Item Backup</span><h2><?= count($backupFiles); ?></h2><p>File data, upload, dan log yang siap di-backup.</p></div>
                    <div class="admin-card"><span class="admin-badge">Ukuran Backup</span><h2><?= esc(maintenance_readable_size($backupSize)); ?></h2><p>Estimasi ukuran sebelum kompresi ZIP.</p></div>
                    <div class="admin-card"><span class="admin-badge">Metode ZIP</span><h2><?= class_exists('ZipArchive') ? 'Native' : 'Fallback'; ?></h2><p><?= class_exists('ZipArchive') ? 'Pakai PHP ZipArchive.' : 'Pakai metode ZIP bawaan aplikasi.'; ?></p></div>
                    <div class="admin-card"><span class="admin-badge">Secret</span><h2>.env OFF</h2><p>File .env tidak dimasukkan ke backup.</p></div>
                </div>

                <div class="admin-grid admin-grid--two" style="margin-top:18px">
                    <div class="admin-card admin-maintenance-panel">
                        <div class="admin-form-head"><h2>Backup Data</h2><p>Download ZIP berisi data, log, dan file upload. Cocok sebelum update atau pindah hosting.</p></div>
                        <form method="post">
                            <?= csrf_field(); ?>
                            <button class="admin-btn admin-btn--primary" type="submit" name="maintenance_action" value="download_backup">Download Backup ZIP</button>
                        </form>
                        <div class="admin-help-box" style="margin-top:16px">
                            <strong>Isi backup:</strong> data website, logs, dan file upload. <code>.env</code>, cache page, dan file backup lama tidak ikut dimasukkan.
                        </div>
                    </div>
                    <div class="admin-card admin-maintenance-panel">
                        <div class="admin-form-head"><h2>Bersihkan Cache</h2><p>Bersihkan cache halaman/file sementara. Backup ZIP tetap disimpan.</p></div>
                        <form method="post">
                            <?= csrf_field(); ?>
                            <button class="admin-btn admin-btn--soft" type="submit" name="maintenance_action" value="clear_cache">Clear Cache</button>
                        </form>
                        <div class="admin-help-box" style="margin-top:16px">
                            Aman untuk dijalankan setelah update template/CSS/SEO agar halaman publik refresh lebih cepat.
                        </div>
                    </div>
                    <div class="admin-card admin-maintenance-panel">
                        <div class="admin-form-head"><h2>Restore Manual Aman</h2><p>Restore otomatis sengaja tidak dibuat satu klik agar file live tidak tertimpa tanpa kontrol.</p></div>
                        <div class="admin-help-box">
                            <strong>Cara restore:</strong> download backup ZIP, ekstrak di komputer, pilih file data/upload yang ingin dikembalikan, lalu upload manual lewat File Manager/FTP. Simpan backup baru sebelum menimpa file live.
                        </div>
                    </div>
                </div>

                <div class="admin-card admin-health-card" style="margin-top:18px">
                    <div class="admin-form-head"><h2>Storage Overview</h2><p>Ringkasan folder penting agar ukuran data tetap terkontrol.</p></div>
                    <div class="admin-table-wrap admin-table-wrap--comfortable">
                        <table class="admin-table admin-health-table">
                            <thead><tr><th>Area</th><th>Status</th><th>File</th><th>Size</th><th>Path</th></tr></thead>
                            <tbody>
                            <?php foreach ($overview as $key => $row): ?>
                                <tr>
                                    <td><strong><?= esc(ucwords(str_replace('_', ' ', (string)$key))); ?></strong></td>
                                    <td><span class="admin-status-pill admin-status-pill--<?= !empty($row['exists']) && !empty($row['writable']) ? 'ok' : 'warning'; ?>"><?= !empty($row['exists']) ? (!empty($row['writable']) ? 'OK' : 'WARN') : 'INFO'; ?></span></td>
                                    <td><?= (int)($row['files'] ?? 0); ?></td>
                                    <td><?= esc((string)($row['size_label'] ?? '-')); ?></td>
                                    <td><code class="admin-code-path"><?= esc((string)($row['path'] ?? '-')); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-card admin-health-card" style="margin-top:18px">
                    <div class="admin-form-head"><h2>Backup Terbaru</h2><p>File backup disimpan sementara di cache/maintenance-backups. Download baru akan tercatat di Activity Log.</p></div>
                    <?php if (!$recentBackups): ?>
                        <p>Belum ada backup yang tersimpan.</p>
                    <?php else: ?>
                        <div class="admin-table-wrap admin-table-wrap--comfortable">
                            <table class="admin-table admin-health-table">
                                <thead><tr><th>Filename</th><th>Size</th><th>Dibuat</th></tr></thead>
                                <tbody>
                                <?php foreach ($recentBackups as $backup): ?>
                                    <tr>
                                        <td><code class="admin-code-path"><?= esc((string)($backup['filename'] ?? '-')); ?></code></td>
                                        <td><?= esc(maintenance_readable_size((int)($backup['size_bytes'] ?? 0))); ?></td>
                                        <td><?= esc((string)($backup['created_at'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
