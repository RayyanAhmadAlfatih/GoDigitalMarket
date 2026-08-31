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
        if ($action === 'save_redirect') {
            $record = seo_preservation_save_record([
                'id' => trim((string)($_POST['id'] ?? '')),
                'source_path' => (string)($_POST['source_path'] ?? ''),
                'target_url' => (string)($_POST['target_url'] ?? ''),
                'code' => (int)($_POST['code'] ?? 301),
                'status' => (string)($_POST['status'] ?? 'active'),
                'type' => 'manual',
                'note' => (string)($_POST['note'] ?? ''),
            ]);
            $message = 'Redirect disimpan: ' . (string)$record['source_path'] . ' → ' . (string)$record['target_url'];
        } elseif ($action === 'delete_redirect') {
            seo_preservation_delete_record((string)($_POST['id'] ?? ''));
            $message = 'Redirect berhasil dihapus.';
        } elseif ($action === 'sync_content') {
            $sync = seo_preservation_sync_redirects_from_content();
            $message = 'Scan legacy URL selesai. Kandidat redirect dibuat: ' . (int)($sync['created'] ?? 0) . ', dilewati/preserve: ' . (int)($sync['skipped'] ?? 0) . '. Kandidat auto dibuat inactive agar bisa direview dulu.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$report = seo_preservation_report();
$records = (array)($report['records'] ?? []);
$aliases = (array)($report['aliases'] ?? []);
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));

$filteredRecords = array_values(array_filter($records, static function (array $record) use ($status, $q): bool {
    if ($status !== 'all' && (string)($record['status'] ?? 'active') !== $status) {
        return false;
    }
    if ($q !== '') {
        $haystack = strtolower(implode(' ', [$record['source_path'] ?? '', $record['target_url'] ?? '', $record['type'] ?? '', $record['note'] ?? '']));
        return str_contains($haystack, strtolower($q));
    }
    return true;
}));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-preservation-' . date('Ymd-His') . '.json"');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-redirects-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['source_path', 'target_url', 'code', 'status', 'type', 'hits', 'note']);
    foreach ($filteredRecords as $record) {
        fputcsv($out, [
            $record['source_path'] ?? '',
            $record['target_url'] ?? '',
            $record['code'] ?? 301,
            $record['status'] ?? 'active',
            $record['type'] ?? '',
            $record['hits'] ?? 0,
            $record['note'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$baseUrl = static function (array $override = []) use ($status, $q): string {
    return url('admin/seo-preservation?' . http_build_query(array_merge(['status' => $status, 'q' => $q], $override)));
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SEO Preservation & Redirect - Admin',
    'description' => 'Kelola legacy URL, canonical, redirect 301, dan old URL map untuk migrasi WordPress ke U-Growth.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="admin-content" class="admin-shell admin-seo-preservation-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>SEO Preservation & Redirect</h1>
                <p>Lapisan ini menjaga URL lama WordPress, canonical, dan redirect 301 agar artikel/halaman yang sudah ranking tidak hilang saat dipindah ke U-Growth.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/wp-migration')); ?>">Migrasi WordPress</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/dynamic-content-guard')); ?>">Dynamic Guard</a>
                <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">SEO Preservation Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($report['health_score'] ?? 0); ?>;"><strong><?= (int)($report['health_score'] ?? 0); ?></strong><span>/100</span></div>
                    <h2>Legacy URL Readiness</h2>
                    <p>Resolver aktif sebelum 404. URL lama bisa dibuka sebagai alias, atau diarahkan 301 jika owner memilih pindah URL.</p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Redirect Map</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($report['counts']['redirects'] ?? 0); ?></strong> total</span>
                        <span><strong><?= (int)($report['counts']['active_redirects'] ?? 0); ?></strong> aktif</span>
                        <span><strong><?= (int)($report['counts']['inactive_redirects'] ?? 0); ?></strong> review</span>
                    </div>
                    <p>Auto-scan membuat kandidat redirect dalam status inactive agar tidak ada 301 yang aktif tanpa review.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Legacy Alias</span>
                    <h2><?= (int)($report['counts']['legacy_aliases'] ?? 0); ?> URL lama terdeteksi</h2>
                    <p><?= (int)($report['counts']['preserved_urls'] ?? 0); ?> preserve URL lama · <?= (int)($report['counts']['redirect_candidates'] ?? 0); ?> kandidat redirect.</p>
                    <form method="post" class="admin-cta-result-export-row">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="sync_content">
                        <button class="admin-btn admin-btn--primary" type="submit">Scan Legacy URL</button>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                    </form>
                </article>
            </div>

            <section class="admin-grid admin-grid--2">
                <form method="post" class="admin-card admin-editor">
                    <?= csrf_field(); ?><input type="hidden" name="action" value="save_redirect">
                    <div class="admin-form-head">
                        <span class="admin-badge">Manual 301</span>
                        <h2>Tambah Redirect URL Lama</h2>
                        <p>Gunakan saat URL WordPress lama harus dipindah permanen ke URL U-Growth baru.</p>
                    </div>
                    <label>Source path lama <input type="text" name="source_path" placeholder="/strategi-list-building-yang-efektif" required></label>
                    <label>Target URL/path baru <input type="text" name="target_url" placeholder="/artikel/strategi-list-building-yang-efektif" required></label>
                    <div class="admin-form-grid admin-form-row--2">
                        <label>Kode <select name="code"><option value="301">301 Permanent</option><option value="302">302 Temporary</option><option value="308">308 Permanent</option></select></label>
                        <label>Status <select name="status"><option value="active">Aktif</option><option value="inactive">Review dulu</option></select></label>
                    </div>
                    <label>Catatan <textarea name="note" rows="3" placeholder="Misal: redirect dari URL WordPress lama"></textarea></label>
                    <div class="admin-form-actions"><button class="admin-btn admin-btn--primary" type="submit">Simpan Redirect</button></div>
                </form>

                <article class="admin-card">
                    <span class="admin-badge">Strategi SEO</span>
                    <h2>Preserve URL vs Redirect</h2>
                    <ul>
                        <li><strong>Preserve URL lama:</strong> URL lama tetap dibuka dan canonical mengarah ke URL lama. Cocok kalau URL sudah ranking kuat.</li>
                        <li><strong>301 Redirect:</strong> URL lama diarahkan ke URL baru. Cocok kalau struktur URL ingin dirapikan ke <code>/artikel/slug</code> atau <code>/lp/slug</code>.</li>
                        <li><strong>Resolver sebelum 404:</strong> root-level slug WordPress seperti <code>/judul-artikel</code> bisa ditangani tanpa mengganggu route U-Growth yang sudah ada.</li>
                    </ul>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Redirect Map</span>
                        <h2><?= count($filteredRecords); ?> redirect tampil</h2>
                        <p>Redirect aktif akan dieksekusi sebelum legacy alias. Gunakan inactive untuk kandidat hasil scan yang belum disetujui.</p>
                    </div>
                </div>
                <form method="get" class="admin-filter-form">
                    <label><span>Status</span><select name="status"><option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>Semua</option><option value="active" <?= $status === 'active' ? 'selected' : ''; ?>>Aktif</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : ''; ?>>Review</option></select></label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="source, target, catatan..."></label>
                    <button class="admin-btn admin-btn--primary" type="submit">Filter</button>
                </form>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Source</th><th>Target</th><th>Kode</th><th>Status</th><th>Hits</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($filteredRecords as $record): ?>
                            <tr>
                                <td><code><?= esc((string)($record['source_path'] ?? '')); ?></code><br><small><?= esc((string)($record['type'] ?? 'manual')); ?></small></td>
                                <td><code><?= esc((string)($record['target_url'] ?? '')); ?></code><br><small><?= esc((string)($record['note'] ?? '')); ?></small></td>
                                <td><?= (int)($record['code'] ?? 301); ?></td>
                                <td><?= esc((string)($record['status'] ?? 'active')); ?></td>
                                <td><?= (int)($record['hits'] ?? 0); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Hapus redirect ini?')">
                                        <?= csrf_field(); ?><input type="hidden" name="action" value="delete_redirect"><input type="hidden" name="id" value="<?= esc((string)($record['id'] ?? '')); ?>">
                                        <button class="admin-btn admin-btn--small admin-btn--danger" type="submit">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$filteredRecords): ?><tr><td colspan="6">Belum ada redirect. Tambahkan manual atau gunakan Scan Legacy URL.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Old URL Map</span>
                        <h2><?= count($aliases); ?> legacy URL dari konten</h2>
                        <p>Daftar ini berasal dari field <code>legacy_url</code>, <code>original_url</code>, <code>old_url</code>, atau data import WordPress.</p>
                    </div>
                </div>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Tipe</th><th>Judul</th><th>URL Lama</th><th>Canonical</th><th>Mode</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($aliases, 0, 80) as $row): ?>
                            <tr>
                                <td><?= esc((string)($row['type'] ?? '')); ?></td>
                                <td><?= esc((string)($row['title'] ?? '')); ?></td>
                                <td><code><?= esc((string)($row['source_path'] ?? '')); ?></code></td>
                                <td><code><?= esc((string)($row['canonical'] ?? '')); ?></code></td>
                                <td><?= esc((string)($row['mode'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$aliases): ?><tr><td colspan="5">Belum ada legacy URL. Data ini akan muncul setelah migrasi WP membawa old URL/canonical.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
