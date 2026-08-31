<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        storage_adapter_save_from_post($_POST);
        redirect_302('admin/storage-database?message=' . rawurlencode('Pengaturan Storage & Database berhasil disimpan. Mode file-based tetap aman sebagai fallback.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$report = storage_adapter_report();
$settings = storage_adapter_settings(true);
$collections = (array)($report['collections'] ?? []);
$driver = (string)($report['driver'] ?? 'file');
$dbConfigured = !empty($report['db_configured']);
$dbAvailable = !empty($report['db_available']);
$summary = (array)($report['summary'] ?? []);

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Storage & Database - Admin',
    'description' => 'Fondasi penyimpanan file-based ke MySQL untuk website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="admin-content" class="admin-shell admin-storage-database-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem</div>
                <h1>Storage & Database</h1>
                <p>Fondasi transisi dari penyimpanan file/JSON ke MySQL. Default tetap aman: file-based aktif sampai admin mengaktifkan MySQL per data.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/data-migration')); ?>">Migrasi Data MySQL</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/data-health')); ?>">Cek Sistem</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/maintenance')); ?>">Backup & Restore</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Mode Aktif</span><h2><?= esc(strtoupper($driver)); ?></h2><p><?= $driver === 'file' ? 'Website tetap memakai file/JSON ringan.' : 'MySQL dipakai hanya untuk collection yang siap dan diaktifkan.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">DB Config</span><h2><?= $dbConfigured ? 'Ada' : 'Belum'; ?></h2><p><?= $dbConfigured ? 'DB_HOST/DB_NAME/DB_USER sudah terisi.' : 'Isi DB di .env saat siap migrasi.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Koneksi MySQL</span><h2><?= $dbAvailable ? 'Tersambung' : 'Belum'; ?></h2><p><?= $dbAvailable ? 'Koneksi database berhasil.' : 'Fallback file-based tetap menjaga website jalan.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Collection MySQL</span><h2><?= (int)($summary['mysql_active_collections'] ?? 0); ?></h2><p>Dari <?= (int)($summary['collection_total'] ?? count($collections)); ?> collection sudah aktif MySQL.</p></div>
            </div>

            <form method="post" class="admin-card admin-editor admin-storage-database-card">
                <?= csrf_field(); ?>
                <div class="admin-form-head"><span class="admin-badge">Pengaturan Aman</span><h2>Mode Penyimpanan</h2><p>Pilih bertahap. Jangan aktifkan MySQL untuk collection sebelum tabel dan data migrasi diuji.</p></div>
                <div class="admin-form-grid admin-form-row--2">
                    <label>Mode penyimpanan
                        <select name="storage_driver">
                            <option value="file" <?= $driver === 'file' ? 'selected' : ''; ?>>File / JSON aktif</option>
                            <option value="hybrid" <?= $driver === 'hybrid' ? 'selected' : ''; ?>>Hybrid: file + MySQL per collection</option>
                            <option value="mysql" <?= $driver === 'mysql' ? 'selected' : ''; ?>>MySQL production mode bertahap</option>
                        </select>
                    </label>
                    <label class="admin-toggle-option"><input type="checkbox" name="safe_fallback" value="1" <?= !empty($settings['safe_fallback']) ? 'checked' : ''; ?>> Tetap aktifkan fallback file-based jika MySQL belum siap</label>
                </div>
                <div class="admin-alert admin-alert--info" style="margin-top:16px">Aktifkan MySQL bertahap hanya untuk data yang tabel dan jalur runtime-nya sudah siap. File-based tetap menjadi fallback aman.</div>

                <div class="admin-table-wrap admin-table-wrap--comfortable" style="margin-top:18px">
                    <table class="admin-table">
                        <thead><tr><th>Aktifkan</th><th>Collection</th><th>File/JSON</th><th>MySQL</th><th>Status</th><th>Catatan</th></tr></thead>
                        <tbody>
                        <?php foreach ($collections as $row): ?>
                            <?php
                                $key = (string)($row['key'] ?? '');
                                $ready = !empty($row['ready_for_mysql']);
                                $enabled = !empty($row['mysql_enabled']);
                                $tableReady = !empty($row['mysql_table_ready']);
                            ?>
                            <tr>
                                <td>
                                    <label class="admin-toggle-option" style="justify-content:center">
                                        <input type="checkbox" name="mysql_collections[]" value="<?= esc($key); ?>" <?= $enabled ? 'checked' : ''; ?> <?= (!$ready || !$tableReady || !$dbAvailable) ? 'disabled' : ''; ?>>
                                    </label>
                                </td>
                                <td><strong><?= esc((string)($row['label'] ?? $key)); ?></strong><br><small><?= esc((string)($row['module'] ?? '-')); ?></small></td>
                                <td><?= esc((string)($row['json_file'] ?? '-')); ?><br><small><?= (int)($row['json_count'] ?? 0); ?> record</small></td>
                                <td><?= esc((string)($row['mysql_table'] ?? '-')); ?><br><small><?= $tableReady ? ((int)($row['mysql_count'] ?? 0) . ' row') : 'tabel belum ada'; ?></small></td>
                                <td><span class="admin-status-pill admin-status-pill--<?= (($row['active_mode'] ?? '') === 'mysql') ? 'ok' : ($enabled ? 'warning' : 'info'); ?>"><?= esc((string)($row['status'] ?? 'File aktif')); ?></span></td>
                                <td><?= esc((string)($row['note'] ?? '')); ?><?= (!$ready ? '<br><small>Menunggu fase migrasi berikutnya.</small>' : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-form-actions" style="margin-top:18px">
                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Storage & Database</button>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/storage-database')); ?>">Refresh Status</a>
                </div>
            </form>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card admin-roadmap-card">
                    <span class="admin-badge">Tahapan Aman</span>
                    <h2>Urutan migrasi yang disarankan</h2>
                    <ol class="admin-list admin-roadmap-list">
                        <li>Backup file dan database dari <strong>Backup & Restore</strong>.</li>
                        <li>Isi koneksi MySQL di <code>.env</code>.</li>
                        <li>Import schema dari <code>database.sql</code> lalu <code>database/mysql-storage-schema.sql</code>.</li>
                        <li>Buka <strong>Migrasi Data MySQL</strong> untuk preview dan copy data ke tabel runtime/bridge yang sesuai.</li>
                        <li>Aktifkan <strong>Hybrid</strong>, lalu aktifkan data penting satu per satu setelah data dicek.</li>
                        <li>Aktifkan konten inti, lalu lanjutkan data operasional/analytics satu per satu setelah hasil migrasi dicek.</li>
                    </ol>
                </article>
                <article class="admin-card">
                    <span class="admin-badge">Tidak Merusak File-based</span>
                    <h2>Kenapa belum langsung full MySQL?</h2>
                    <p>Karena website sudah stabil sebagai package file-based. Fondasi adapter dibuat bertahap agar website tidak error kalau database belum siap.</p>
                    <p>Mode full MySQL akan lebih aman setelah migrasi data dan simulasi CRUD real per modul selesai.</p>
                </article>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
