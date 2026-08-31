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
        if ($action === 'dry_rewrite') {
            $result = internal_link_migration_rewrite_known_links(true);
            $message = 'Dry-run selesai. Sumber yang akan berubah: ' . (int)($result['changed_sources'] ?? 0) . ', link kandidat: ' . (int)($result['replacements'] ?? 0) . '.';
        } elseif ($action === 'apply_rewrite') {
            $result = internal_link_migration_rewrite_known_links(false);
            $message = 'Rewrite link selesai. Sumber berubah: ' . (int)($result['changed_sources'] ?? 0) . ', link diganti: ' . (int)($result['replacements'] ?? 0) . '. Backup: ' . (string)($result['backup_dir'] ?? '-');
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$linkReport = internal_link_migration_scan();
$breadcrumbReport = breadcrumb_migration_report();
$rows = (array)($linkReport['rows'] ?? []);
$status = (string)($_GET['status'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));

$filteredRows = array_values(array_filter($rows, static function (array $row) use ($status, $q): bool {
    if ($status !== 'all' && (string)($row['status'] ?? '') !== $status) { return false; }
    if ($q !== '') {
        $haystack = strtolower(implode(' ', [$row['href'] ?? '', $row['suggested_url'] ?? '', $row['source_title'] ?? '', $row['target_title'] ?? '', $row['note'] ?? '']));
        return str_contains($haystack, strtolower($q));
    }
    return true;
}));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="internal-link-migration-' . date('Ymd-His') . '.json"');
    echo json_encode(['links'=>$linkReport,'breadcrumbs'=>$breadcrumbReport], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="internal-link-migration-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['source_type','source_title','href','status','suggested_url','target_type','target_title','note']);
    foreach ($filteredRows as $row) {
        fputcsv($out, [$row['source_type_label'] ?? '', $row['source_title'] ?? '', $row['href'] ?? '', $row['status'] ?? '', $row['suggested_url'] ?? '', $row['target_type'] ?? '', $row['target_title'] ?? '', $row['note'] ?? '']);
    }
    fclose($out);
    exit;
}

$baseUrl = static function (array $override = []) use ($status, $q): string {
    return url('admin/internal-link-migration?' . http_build_query(array_merge(['status'=>$status,'q'=>$q], $override)));
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Breadcrumb & Internal Link Migration - Admin',
    'description' => 'Audit breadcrumb, link internal, legacy URL, dan rekomendasi rewrite link untuk migrasi WordPress ke U-Growth.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="admin-content" class="admin-shell admin-internal-link-migration-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Breadcrumb & Internal Link Migration</h1>
                <p>Audit jalur breadcrumb, link internal lama dari WordPress, dan rekomendasi penggantian link agar struktur SEO tetap rapi setelah migrasi.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-preservation')); ?>">SEO Preservation</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/wp-migration')); ?>">Migrasi WordPress</a>
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
                    <span class="admin-badge">Internal Link Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($linkReport['health_score'] ?? 0); ?>;"><strong><?= (int)($linkReport['health_score'] ?? 0); ?></strong><span>/100</span></div>
                    <h2>Link Migration Readiness</h2>
                    <p>Link lama yang cocok dengan konten/redirect bisa direwrite setelah direview. Unknown internal link perlu dicek manual.</p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Link Internal</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($linkReport['counts']['total_links'] ?? 0); ?></strong> total link</span>
                        <span><strong><?= (int)($linkReport['counts']['fixable_links'] ?? 0); ?></strong> bisa diarahkan</span>
                        <span><strong><?= (int)($linkReport['counts']['unknown_internal'] ?? 0); ?></strong> perlu cek</span>
                    </div>
                    <p>Scan membaca konten artikel, produk/layanan, dan blok landing page.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Breadcrumb</span>
                    <h2><?= (int)($breadcrumbReport['counts']['total'] ?? 0); ?> halaman dicek</h2>
                    <p><?= (int)($breadcrumbReport['counts']['custom_paths'] ?? 0); ?> custom path · <?= (int)($breadcrumbReport['counts']['generated_paths'] ?? 0); ?> auto generated.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export'=>'csv'])); ?>">Export CSV</a>
                    </div>
                </article>
            </div>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card">
                    <span class="admin-badge">Safe Rewrite</span>
                    <h2>Rewrite Link yang Sudah Dikenali</h2>
                    <p>Rewrite hanya mengganti link yang statusnya <strong>legacy_replacement</strong> atau <strong>redirect_map</strong>. Backup storage dibuat otomatis sebelum apply.</p>
                    <form method="post" class="admin-cta-result-export-row">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="dry_rewrite">
                        <button class="admin-btn admin-btn--light" type="submit">Dry-run dulu</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Apply rewrite link yang sudah dikenali? Backup akan dibuat dulu.');" class="admin-cta-result-export-row">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="apply_rewrite">
                        <button class="admin-btn admin-btn--primary" type="submit">Apply Rewrite Aman</button>
                    </form>
                </article>
                <article class="admin-card">
                    <span class="admin-badge">Panduan</span>
                    <h2>Cara Membaca Status</h2>
                    <ul>
                        <li><strong>ok:</strong> link sudah cocok dengan URL konten utama.</li>
                        <li><strong>legacy_replacement:</strong> link lama dikenali dan bisa diarahkan ke canonical/URL utama.</li>
                        <li><strong>redirect_map:</strong> link cocok dengan redirect aktif.</li>
                        <li><strong>unknown_internal:</strong> link internal belum ditemukan di konten/redirect, perlu review manual.</li>
                    </ul>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Internal Link Checker</span>
                        <h2><?= count($filteredRows); ?> link tampil</h2>
                        <p>Gunakan filter untuk fokus ke link yang perlu diperbaiki sebelum/selepas migrasi WordPress.</p>
                    </div>
                </div>
                <form method="get" class="admin-filter-form">
                    <label><span>Status</span><select name="status">
                        <?php foreach (['all'=>'Semua','legacy_replacement'=>'Legacy replacement','redirect_map'=>'Redirect map','unknown_internal'=>'Unknown internal','ok'=>'OK','external'=>'External'] as $key=>$label): ?>
                            <option value="<?= esc($key); ?>" <?= $status===$key?'selected':''; ?>><?= esc($label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, URL, target..."></label>
                    <button class="admin-btn admin-btn--primary" type="submit">Filter</button>
                </form>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Sumber</th><th>Link Saat Ini</th><th>Status</th><th>Saran Target</th><th>Catatan</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($filteredRows, 0, 160) as $row): ?>
                            <tr>
                                <td><strong><?= esc((string)($row['source_title'] ?? '')); ?></strong><br><small><?= esc((string)($row['source_type_label'] ?? '')); ?></small></td>
                                <td><code><?= esc((string)($row['href'] ?? '')); ?></code></td>
                                <td><?= esc((string)($row['status'] ?? '')); ?></td>
                                <td><code><?= esc((string)($row['suggested_url'] ?? '')); ?></code><br><small><?= esc(trim((string)($row['target_type'] ?? '') . ' ' . (string)($row['target_title'] ?? ''))); ?></small></td>
                                <td><?= esc((string)($row['note'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$filteredRows): ?><tr><td colspan="5">Belum ada link yang sesuai filter.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header"><div><span class="admin-badge">Breadcrumb Mapper</span><h2>Preview Jalur Breadcrumb</h2><p>Field <code>breadcrumb_path</code> dari import WP akan dipakai jika tersedia. Jika tidak ada, U-Growth membuat jalur dari kategori/subkategori secara otomatis.</p></div></div>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Tipe</th><th>Judul</th><th>Breadcrumb</th><th>Mode</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice((array)($breadcrumbReport['rows'] ?? []), 0, 100) as $row): ?>
                            <tr>
                                <td><?= esc((string)($row['type'] ?? '')); ?></td>
                                <td><?= esc((string)($row['title'] ?? '')); ?></td>
                                <td><?= esc(implode(' / ', array_map(static fn(array $i): string => (string)($i['name'] ?? ''), (array)($row['trail'] ?? [])))); ?></td>
                                <td><?= !empty($row['custom']) ? 'Custom import' : 'Auto generated'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
