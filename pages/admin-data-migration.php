<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$result = [];
$selectedCollection = trim((string)($_GET['collection'] ?? $_POST['collection'] ?? 'products'));
if (!storage_adapter_allowed_migration_collection($selectedCollection)) {
    $selectedCollection = 'products';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? 'preview');
        if ($action === 'migrate') {
            $result = function_exists('storage_adapter_run_collection_migration')
                ? storage_adapter_run_collection_migration($selectedCollection, !empty($_POST['make_backup']))
                : storage_adapter_run_generic_migration($selectedCollection, !empty($_POST['make_backup']));
            $target = trim((string)($result['target'] ?? 'MySQL bridge'));
            $message = 'Migrasi ke ' . ($target !== '' ? $target : 'MySQL') . ' selesai: ' . (int)($result['migrated_records'] ?? 0) . ' record diproses.';
        } else {
            $result = storage_adapter_preview_collection_migration($selectedCollection);
            $message = 'Preview migrasi diperbarui.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$collections = storage_adapter_default_collections();
$preview = $result ?: storage_adapter_preview_collection_migration($selectedCollection);
$schema = storage_adapter_schema_status();
$report = storage_adapter_report();
$history = storage_adapter_migration_history(10);
$bridgeReady = storage_adapter_generic_bridge_ready();
$dbAvailable = !empty($report['db_available']);
$selectedTargetTable = function_exists('storage_adapter_typed_migration_table') ? storage_adapter_typed_migration_table($selectedCollection) : '';
$selectedTargetReady = $selectedTargetTable !== ''
    ? (function_exists('storage_adapter_typed_migration_ready') && storage_adapter_typed_migration_ready($selectedCollection))
    : $bridgeReady;
$selectedTargetLabel = $selectedTargetTable !== '' ? $selectedTargetTable : 'ugrowth_storage_records';

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Migrasi Data MySQL - Admin',
    'description' => 'Tool migrasi bertahap dari file/JSON ke MySQL runtime.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="admin-content" class="admin-shell admin-storage-migration-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem</div>
                <h1>Migrasi Data MySQL</h1>
                <p>Tool aman untuk menyalin data file/JSON/JSONL ke MySQL. Produk, artikel, landing page, lead/form, inquiry, order, bukti pembayaran, analytics, riwayat email, dan log aktivitas admin masuk tabel runtime bertahap.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/storage-database')); ?>">Storage & Database</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/data-health')); ?>">Cek Sistem</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">MySQL</span><h2><?= $dbAvailable ? 'Tersambung' : 'Belum'; ?></h2><p><?= $dbAvailable ? 'Database bisa dipakai untuk preview/migrasi.' : 'Isi .env DB lalu import schema sebelum migrasi.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Bridge Table</span><h2><?= $bridgeReady ? 'Siap' : 'Belum'; ?></h2><p>Butuh <code>ugrowth_storage_records</code> dan <code>ugrowth_storage_migrations</code>.</p></div>
                <div class="admin-card"><span class="admin-badge">Collection</span><h2><?= count($collections); ?></h2><p>Collection sudah dipetakan untuk migrasi bertahap.</p></div>
                <div class="admin-card"><span class="admin-badge">Mode Runtime</span><h2><?= esc(strtoupper((string)($report['driver'] ?? 'file'))); ?></h2><p>Mode runtime tidak diubah otomatis oleh tool migrasi ini.</p></div>
            </div>

            <form method="get" class="admin-card admin-editor admin-storage-migration-card">
                <div class="admin-form-head"><span class="admin-badge">Preview Aman</span><h2>Pilih data yang akan dicek</h2><p>Preview hanya membaca file sumber. Belum menulis ke database.</p></div>
                <div class="admin-form-grid admin-form-row--2">
                    <label>Collection data
                        <select name="collection">
                            <?php foreach ($collections as $key => $meta): ?>
                                <option value="<?= esc($key); ?>" <?= $selectedCollection === $key ? 'selected' : ''; ?>><?= esc((string)($meta['label'] ?? $key)); ?> — <?= esc((string)($meta['module'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="admin-form-actions admin-form-actions--inline"><button class="admin-btn admin-btn--primary" type="submit">Preview Data</button></div>
                </div>
            </form>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card">
                    <span class="admin-badge">Preview Collection</span>
                    <h2><?= esc((string)($preview['label'] ?? $selectedCollection)); ?></h2>
                    <p>File sumber: <code><?= esc((string)($preview['file'] ?? '-')); ?></code></p>
                    <div class="admin-grid admin-grid--stats admin-grid--compact admin-preview-metrics">
                        <div class="admin-preview-metric"><strong><?= (int)($preview['records'] ?? 0); ?></strong><small>record terbaca</small></div>
                        <div class="admin-preview-metric"><strong><?= esc((string)($preview['record_type'] ?? '-')); ?></strong><small>format file</small></div>
                    </div>
                    <?php if (!empty($preview['samples'])): ?>
                        <div class="admin-table-wrap admin-table-wrap--comfortable" style="margin-top:14px">
                            <table class="admin-table">
                                <thead><tr><th>Key</th><th>Ref</th><th>Judul/Nama</th></tr></thead>
                                <tbody>
                                <?php foreach ((array)$preview['samples'] as $sample): ?>
                                    <tr><td><code><?= esc((string)($sample['key'] ?? '')); ?></code></td><td><?= esc((string)($sample['ref'] ?? '-')); ?></td><td><?= esc((string)($sample['title'] ?? '-')); ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p><strong>Belum ada sample record.</strong> Collection ini mungkin belum punya data file.</p>
                    <?php endif; ?>
                </article>

                <article class="admin-card">
                    <span class="admin-badge">Eksekusi Migrasi</span>
                    <h2>Copy ke MySQL runtime</h2>
                    <p>Target migrasi collection ini: <code><?= esc($selectedTargetLabel); ?></code>. Runtime website tetap file-based sampai admin mengaktifkan collection terkait di Storage & Database.</p>
                    <form method="post" class="admin-stack">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="collection" value="<?= esc($selectedCollection); ?>">
                        <input type="hidden" name="action" value="migrate">
                        <label class="admin-toggle-option"><input type="checkbox" name="make_backup" value="1" checked> Buat backup file sumber sebelum migrasi</label>
                        <?php if (!$dbAvailable || !$selectedTargetReady): ?>
                            <div class="admin-alert admin-alert--warning">Migrasi belum aktif karena koneksi DB atau tabel target belum siap. Import <code>database.sql</code> dan <code>database/mysql-storage-schema.sql</code> dulu.</div>
                        <?php endif; ?>
                        <div class="admin-form-actions">
                            <button class="admin-btn admin-btn--primary" type="submit" <?= (!$dbAvailable || !$selectedTargetReady) ? 'disabled' : ''; ?>>Jalankan Migrasi Aman</button>
                            <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/storage-database')); ?>">Cek Storage Mode</a>
                        </div>
                    </form>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-form-head"><span class="admin-badge">Schema MySQL</span><h2>Status tabel migrasi</h2><p>Tabel target runtime/bridge harus siap sebelum tool migrasi bisa menulis data.</p></div>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Tabel</th><th>Status</th><th>Row</th><th>Catatan</th></tr></thead>
                        <tbody>
                        <?php foreach ($schema as $row): ?>
                            <tr>
                                <td><code><?= esc((string)($row['table'] ?? '')); ?></code></td>
                                <td><span class="admin-status-pill admin-status-pill--<?= !empty($row['ready']) ? 'ok' : (!empty($row['exists']) ? 'warning' : 'info'); ?>"><?= !empty($row['ready']) ? 'Siap' : (!empty($row['exists']) ? 'Perlu kolom' : 'Belum ada'); ?></span></td>
                                <td><?= (int)($row['row_count'] ?? 0); ?></td>
                                <td><?= !empty($row['required']) ? 'Wajib untuk migrasi bridge dan log migrasi.' : 'Tabel typed/runtime untuk collection terkait.'; ?><?= !empty($row['missing_columns']) ? '<br><small>Kolom kurang: ' . esc(implode(', ', (array)$row['missing_columns'])) . '</small>' : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-form-head"><span class="admin-badge">Riwayat</span><h2>Riwayat migrasi terakhir</h2><p>Ditampilkan jika database dan tabel migrasi sudah tersedia.</p></div>
                <?php if ($history): ?>
                    <div class="admin-table-wrap admin-table-wrap--comfortable">
                        <table class="admin-table">
                            <thead><tr><th>Waktu</th><th>Collection</th><th>Status</th><th>Record</th><th>Catatan</th></tr></thead>
                            <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr><td><?= esc((string)($row['created_at'] ?? $row['started_at'] ?? '-')); ?></td><td><?= esc((string)($row['collection'] ?? '-')); ?></td><td><?= esc((string)($row['status'] ?? '-')); ?></td><td><?= (int)($row['migrated_records'] ?? 0); ?>/<?= (int)($row['total_records'] ?? 0); ?></td><td><?= nl2br(esc((string)($row['notes'] ?? ''))); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>Belum ada riwayat migrasi atau tabel migration log belum tersedia.</p>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
