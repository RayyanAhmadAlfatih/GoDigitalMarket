<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();
$message = '';
$error = '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'dry_download') {
            $result = wp_media_migration_download_candidates(['dry_run'=>true, 'limit'=>(int)($_POST['limit'] ?? 20), 'include_external'=>!empty($_POST['include_external'])]);
            $message = 'Dry-run download selesai. Kandidat: ' . (int)($result['attempted'] ?? 0) . ' gambar.';
        } elseif ($action === 'download') {
            $result = wp_media_migration_download_candidates(['dry_run'=>false, 'limit'=>(int)($_POST['limit'] ?? 20), 'include_external'=>!empty($_POST['include_external'])]);
            $message = 'Download selesai. Berhasil: ' . (int)($result['downloaded'] ?? 0) . ', skip: ' . (int)($result['skipped'] ?? 0) . ', gagal: ' . (int)($result['failed'] ?? 0) . '.';
        } elseif ($action === 'dry_rewrite') {
            $result = wp_media_migration_rewrite_downloaded_media(true);
            $message = 'Dry-run rewrite selesai. Sumber yang akan berubah: ' . (int)($result['changed_sources'] ?? 0) . ', map lokal: ' . (int)($result['map_count'] ?? 0) . '.';
        } elseif ($action === 'rewrite') {
            $result = wp_media_migration_rewrite_downloaded_media(false);
            $message = 'Rewrite media selesai. Sumber berubah: ' . (int)($result['changed_sources'] ?? 0) . '. Backup: ' . (string)($result['backup_dir'] ?? '-');
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$status = (string)($_GET['status'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));
$report = wp_media_migration_scan(['status'=>$status, 'q'=>$q]);
$rows = (array)($report['rows'] ?? []);

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="wp-media-migration-' . date('Ymd-His') . '.json"');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wp-media-migration-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['source_type','source_title','field','url','status','is_wordpress_upload','local_relative','local_exists']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['source_type'] ?? '', $row['source_title'] ?? '', $row['field'] ?? '', $row['url'] ?? '', $row['status'] ?? '', !empty($row['is_wordpress_upload']) ? 'yes' : 'no', $row['local_relative'] ?? '', !empty($row['local_exists']) ? 'yes' : 'no']);
    }
    fclose($out);
    exit;
}

$baseUrl = static function (array $override = []) use ($status, $q): string {
    return url('admin/wp-media-migration?' . http_build_query(array_merge(['status'=>$status, 'q'=>$q], $override)));
};

$statusLabels = ['all'=>'Semua','wp_remote'=>'WP upload remote','external_remote'=>'Remote eksternal','downloaded'=>'Sudah lokal','local'=>'Sudah lokal/non-remote'];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'WordPress Media Migration - Admin',
    'description' => 'Scan, download, dan rewrite gambar WordPress lama ke storage U-Growth dengan backup aman.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="admin-content" class="admin-shell admin-wp-media-migration-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>WordPress Media Migration</h1>
                <p>Pindahkan gambar dari <code>wp-content/uploads</code> ke U-Growth secara bertahap: scan dulu, download aman, lalu rewrite URL setelah backup.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/wp-migration')); ?>">Migrasi WordPress</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/internal-link-migration')); ?>">Internal Link</a>
                <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export'=>'json'])); ?>">Export JSON</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Media Migration Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($report['health_score'] ?? 0); ?>;"><strong><?= (int)($report['health_score'] ?? 0); ?></strong><span>/100</span></div>
                    <h2>Kesiapan Media</h2>
                    <p>Skor turun jika masih banyak gambar WordPress remote yang belum dipindah atau masih bergantung domain lama.</p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Remote WP Upload</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($report['counts']['wp_uploads'] ?? 0); ?></strong> wp-content</span>
                        <span><strong><?= (int)($report['counts']['not_downloaded'] ?? 0); ?></strong> belum lokal</span>
                        <span><strong><?= (int)($report['counts']['downloaded'] ?? 0); ?></strong> sudah lokal</span>
                    </div>
                    <p>Prioritaskan gambar dari domain WordPress lama yang dipakai di artikel dan halaman ranking.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Rewrite Ready</span>
                    <h2><?= (int)($report['counts']['rewrite_ready'] ?? 0); ?> URL siap diganti</h2>
                    <p><?= (int)($report['counts']['external_remote'] ?? 0); ?> remote eksternal · <?= (int)($report['counts']['local'] ?? 0); ?> sudah lokal.</p>
                    <div class="admin-cta-result-export-row"><a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export'=>'csv'])); ?>">Export CSV</a></div>
                </article>
            </div>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card">
                    <span class="admin-badge">Download Bertahap</span>
                    <h2>Ambil Gambar WordPress ke U-Growth</h2>
                    <p>Default hanya mengambil URL yang mengandung <code>wp-content/uploads</code>. Remote eksternal bisa ikut diambil jika dicentang.</p>
                    <form method="post" class="admin-form-grid admin-form-row--2">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="dry_download">
                        <label>Limit <input type="number" name="limit" value="20" min="1" max="100"></label>
                        <label class="admin-check"><input type="checkbox" name="include_external" value="1"> Sertakan remote eksternal</label>
                        <button class="admin-btn admin-btn--light" type="submit">Dry-run Download</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Download gambar remote sekarang? Proses dibatasi per batch agar aman untuk shared hosting.');" class="admin-form-grid admin-form-row--2">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="download">
                        <label>Limit <input type="number" name="limit" value="20" min="1" max="100"></label>
                        <label class="admin-check"><input type="checkbox" name="include_external" value="1"> Sertakan remote eksternal</label>
                        <button class="admin-btn admin-btn--primary" type="submit">Download Batch</button>
                    </form>
                </article>
                <article class="admin-card">
                    <span class="admin-badge">Rewrite Aman</span>
                    <h2>Ganti URL Remote ke Lokal</h2>
                    <p>Rewrite hanya mengganti URL yang sudah berhasil didownload dan tercatat di media map. Backup storage dibuat sebelum apply.</p>
                    <form method="post" class="admin-cta-result-export-row">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="dry_rewrite">
                        <button class="admin-btn admin-btn--light" type="submit">Dry-run Rewrite</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Apply rewrite URL media ke lokal? Backup storage akan dibuat otomatis.');" class="admin-cta-result-export-row">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="rewrite">
                        <button class="admin-btn admin-btn--primary" type="submit">Apply Rewrite</button>
                    </form>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-card-header"><div><span class="admin-badge">Media URL Scanner</span><h2><?= count($rows); ?> gambar tampil</h2><p>Scan membaca artikel, produk/layanan, gallery, dan blok landing page.</p></div></div>
                <form method="get" class="admin-filter-form">
                    <label><span>Status</span><select name="status"><?php foreach ($statusLabels as $key=>$label): ?><option value="<?= esc($key); ?>" <?= $status===$key?'selected':''; ?>><?= esc($label); ?></option><?php endforeach; ?></select></label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, URL, field..."></label>
                    <button class="admin-btn admin-btn--primary" type="submit">Filter</button>
                </form>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Sumber</th><th>URL Lama</th><th>Status</th><th>Target Lokal</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($rows, 0, 200) as $row): ?>
                            <tr>
                                <td><strong><?= esc((string)($row['source_title'] ?? '')); ?></strong><br><small><?= esc((string)($row['source_type'] ?? '')); ?> · <?= esc((string)($row['field'] ?? '')); ?></small></td>
                                <td><code><?= esc((string)($row['url'] ?? '')); ?></code><br><small><?= !empty($row['is_wordpress_upload']) ? 'WordPress upload' : 'Remote/lokal'; ?></small></td>
                                <td><span class="admin-status-pill admin-status-pill--<?= !empty($row['local_exists']) ? 'ok' : 'warning'; ?>"><?= esc((string)($row['status'] ?? '')); ?></span></td>
                                <td><code><?= esc((string)($row['local_relative'] ?? '')); ?></code><br><?php if (!empty($row['local_exists'])): ?><small>Siap rewrite</small><?php else: ?><small>Belum ada file lokal</small><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="4">Belum ada media remote terdeteksi dari konten saat ini.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
