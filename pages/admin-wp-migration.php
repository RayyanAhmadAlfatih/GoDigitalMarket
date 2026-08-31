<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

seo_noindex();
$message = '';
$error = '';
$activeJob = null;
$preview = null;
$result = null;

try {
    wp_migration_ensure_storage();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        require_csrf();
        $action = (string)($_POST['action'] ?? 'preview');
        if ($action === 'preview') {
            $upload = wp_migration_store_upload($_FILES['wp_file'] ?? []);
            $preview = wp_migration_preview_file((string)$upload['path']);
            $activeJob = [
                'id' => $upload['job_id'],
                'status' => 'previewed',
                'file_name' => $upload['original_name'],
                'file_path' => $upload['path'],
                'file_size' => $upload['size'],
                'summary' => $preview['summary'],
                'samples' => $preview['samples'],
                'created_at' => date('c'),
            ];
            wp_migration_save_job($activeJob);
            $message = 'Preview file WordPress berhasil dibuat. Cek ringkasan sebelum import.';
        } elseif ($action === 'import') {
            $jobId = trim((string)($_POST['job_id'] ?? ''));
            $result = wp_migration_run_import($jobId, [
                'import_posts' => !empty($_POST['import_posts']),
                'import_pages' => !empty($_POST['import_pages']),
                'make_backup' => !empty($_POST['make_backup']),
                'duplicate_strategy' => (string)($_POST['duplicate_strategy'] ?? 'rename'),
                'canonical_strategy' => (string)($_POST['canonical_strategy'] ?? 'legacy'),
                'page_status' => 'draft',
            ]);
            $activeJob = wp_migration_find_job($jobId);
            $message = 'Import selesai: ' . (int)($result['created_articles'] ?? 0) . ' artikel dan ' . (int)($result['created_landing_pages'] ?? 0) . ' halaman/LP draft dibuat.';
        } elseif ($action === 'rollback') {
            $rollback = wp_migration_rollback(trim((string)($_POST['job_id'] ?? '')));
            $message = 'Rollback selesai. File dipulihkan: ' . implode(', ', (array)($rollback['restored'] ?? []));
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (!$activeJob && isset($_GET['job'])) {
    $activeJob = wp_migration_find_job((string)$_GET['job']);
}
if ($activeJob && !$preview && is_file((string)($activeJob['file_path'] ?? ''))) {
    try { $preview = wp_migration_preview_file((string)$activeJob['file_path']); } catch (Throwable $e) { /* preview optional */ }
}
$jobs = wp_migration_jobs(12);

$GLOBALS['admin_page'] = true;
set_seo(['title' => 'Migrasi WordPress - Admin', 'description' => 'Import WordPress XML/WXR atau CSV ke U-Growth dengan preview, backup, dan log batch.', 'robots' => 'noindex, nofollow']);
require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="admin-content" class="admin-shell admin-wp-migration-shell">
    <section class="admin-hero"><div class="container admin-hero__inner"><div><div class="admin-eyebrow">Konten & SEO</div><h1>Migrasi WordPress ke U-Growth</h1><p>Import artikel, halaman, SEO meta, canonical, kategori/tag, featured image remote, dan legacy URL dengan preview aman sebelum eksekusi.</p></div><div class="admin-hero__actions"><a class="admin-btn admin-btn--light" href="<?= esc(url('admin/artikel')); ?>">Artikel</a><a class="admin-btn admin-btn--light" href="<?= esc(url('admin/landing-pages')); ?>">Landing Pages</a></div></div></section>
    <section class="admin-section"><div class="container admin-stack">
        <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>
        <section class="admin-grid admin-grid--2">
            <form class="admin-card admin-editor" method="post" enctype="multipart/form-data">
                <?= csrf_field(); ?><input type="hidden" name="action" value="preview">
                <div class="admin-form-head"><span class="admin-badge">Step 1</span><h2>Upload export WordPress</h2><p>Gunakan XML/WXR dari Tools → Export WordPress. CSV juga bisa untuk mapping sederhana.</p></div>
                <label>File XML/WXR/CSV <input type="file" name="wp_file" accept=".xml,.wxr,.csv,.txt" required></label>
                <div class="admin-alert admin-alert--info">Preview tidak menulis data. File disimpan di storage privat <code>storage/wp-migration</code>.</div>
                <div class="admin-form-actions"><button class="admin-btn admin-btn--primary" type="submit">Buat Preview Aman</button></div>
            </form>
            <article class="admin-card"><span class="admin-badge">Guardrail</span><h2>Yang dijaga modul ini</h2><p>Fitur lama tidak dibongkar. Import bersifat additive: artikel masuk sebagai source <strong>Import WordPress</strong>, halaman masuk sebagai landing page draft, dan storage dibackup sebelum import.</p><ul><li>Artikel lama tidak otomatis ditimpa.</li><li>Canonical bisa mengikuti URL lama.</li><li>Rollback file JSON tersedia per batch.</li><li>Elementor dideteksi, tapi konversi native penuh masuk fase berikutnya.</li></ul></article>
        </section>
        <?php if ($activeJob): $summary = (array)($preview['summary'] ?? $activeJob['summary'] ?? []); ?>
            <section class="admin-card"><div class="admin-form-head"><span class="admin-badge">Preview Batch</span><h2><?= esc((string)($activeJob['file_name'] ?? $activeJob['id'])); ?></h2><p>Batch: <code><?= esc((string)$activeJob['id']); ?></code></p></div>
                <div class="admin-grid admin-grid--stats"><div class="admin-preview-metric"><strong><?= (int)($summary['total'] ?? 0); ?></strong><small>Total konten</small></div><div class="admin-preview-metric"><strong><?= (int)($summary['articles'] ?? 0); ?></strong><small>Artikel</small></div><div class="admin-preview-metric"><strong><?= (int)($summary['landing_pages'] ?? 0); ?></strong><small>Halaman/LP</small></div><div class="admin-preview-metric"><strong><?= (int)($summary['conflicts'] ?? 0); ?></strong><small>Konflik slug</small></div><div class="admin-preview-metric"><strong><?= (int)($summary['warnings'] ?? 0); ?></strong><small>Catatan</small></div></div>
                <?php if (!empty($preview['samples'])): ?><div class="admin-table-wrap admin-table-wrap--comfortable" style="margin-top:14px"><table class="admin-table"><thead><tr><th>Tipe</th><th>Judul</th><th>Slug</th><th>Legacy URL</th><th>Status</th><th>Catatan</th></tr></thead><tbody><?php foreach ((array)$preview['samples'] as $item): ?><tr><td><?= esc((string)($item['target_type'] ?? '')); ?></td><td><?= esc((string)($item['title'] ?? '')); ?></td><td><code><?= esc((string)($item['slug'] ?? '')); ?></code></td><td><code><?= esc((string)($item['legacy_url'] ?? '')); ?></code></td><td><?= esc((string)($item['conflict'] ?? 'none')); ?></td><td><?= esc(implode('; ', (array)($item['warnings'] ?? []))); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                <?php if (($activeJob['status'] ?? '') !== 'imported'): ?><form method="post" class="admin-stack" style="margin-top:16px"><?= csrf_field(); ?><input type="hidden" name="action" value="import"><input type="hidden" name="job_id" value="<?= esc((string)$activeJob['id']); ?>"><label class="admin-toggle-option"><input type="checkbox" name="import_posts" value="1" checked> Import WordPress Posts sebagai Artikel</label><label class="admin-toggle-option"><input type="checkbox" name="import_pages" value="1" checked> Import WordPress Pages sebagai Landing Page draft</label><label class="admin-toggle-option"><input type="checkbox" name="make_backup" value="1" checked> Buat backup storage sebelum import</label><div class="admin-form-grid admin-form-row--2"><label>Jika slug sudah ada <select name="duplicate_strategy"><option value="rename">Rename otomatis</option><option value="skip">Lewati</option></select></label><label>Canonical artikel <select name="canonical_strategy"><option value="legacy">Pakai canonical/URL lama jika ada</option><option value="ugrowth">Biarkan mengikuti URL U-Growth</option></select></label></div><div class="admin-form-actions"><button class="admin-btn admin-btn--primary" type="submit">Jalankan Import Aman</button></div></form><?php else: ?><form method="post" onsubmit="return confirm('Rollback akan mengembalikan file JSON dari backup batch ini. Lanjutkan?')"><?= csrf_field(); ?><input type="hidden" name="action" value="rollback"><input type="hidden" name="job_id" value="<?= esc((string)$activeJob['id']); ?>"><button class="admin-btn admin-btn--danger" type="submit">Rollback Batch Ini</button></form><?php endif; ?>
            </section>
        <?php endif; ?>
        <section class="admin-card"><div class="admin-form-head"><span class="admin-badge">Riwayat</span><h2>Batch migrasi terakhir</h2></div><?php if ($jobs): ?><div class="admin-table-wrap admin-table-wrap--comfortable"><table class="admin-table"><thead><tr><th>Waktu</th><th>File</th><th>Status</th><th>Ringkasan</th><th>Aksi</th></tr></thead><tbody><?php foreach ($jobs as $job): $res=(array)($job['result']??[]); ?><tr><td><?= esc((string)($job['created_at'] ?? '-')); ?></td><td><?= esc((string)($job['file_name'] ?? '-')); ?></td><td><?= esc((string)($job['status'] ?? '-')); ?></td><td><?= (int)($res['created_articles'] ?? $job['summary']['articles'] ?? 0); ?> artikel · <?= (int)($res['created_landing_pages'] ?? $job['summary']['landing_pages'] ?? 0); ?> LP</td><td><a class="admin-btn admin-btn--small" href="<?= esc(url('admin/wp-migration?job=' . rawurlencode((string)$job['id']))); ?>">Buka</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p>Belum ada batch migrasi.</p><?php endif; ?></section>
    </div></section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
