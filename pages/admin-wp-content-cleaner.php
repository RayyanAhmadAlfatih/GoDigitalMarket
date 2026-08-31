<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }
require_admin_auth();

wp_content_cleaner_ensure_storage();
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $removeRisky = isset($_POST['remove_risky_tags']);
    $preserveUnknown = isset($_POST['preserve_unknown_shortcodes']);
    $options = ['remove_risky_tags'=>$removeRisky, 'preserve_unknown_shortcodes'=>$preserveUnknown];

    if ($action === 'dry_clean') {
        $result = wp_content_cleaner_run(true, $options);
        $message = 'Dry-run selesai: ' . (int)($result['changed_sources'] ?? 0) . ' sumber akan berubah jika apply dijalankan.';
    } elseif ($action === 'apply_clean') {
        $result = wp_content_cleaner_run(false, $options);
        $message = 'Cleaner diterapkan: ' . (int)($result['changed_sources'] ?? 0) . ' sumber diperbarui. Backup: ' . (string)($result['backup_dir'] ?? '-');
    } elseif ($action === 'refresh_scan') {
        wp_content_cleaner_scan(['type'=>'all']);
        $message = 'Scan ulang selesai.';
    }
}

$type = (string)($_GET['type'] ?? 'all');
$severity = (string)($_GET['severity'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));
$report = wp_content_cleaner_scan(['type'=>$type, 'severity'=>$severity, 'q'=>$q]);
$rows = (array)($report['rows'] ?? []);

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="wp-content-cleaner-' . date('Ymd-His') . '.json"');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wp-content-cleaner-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['source_type','source_title','field','severity','gutenberg_comments','shortcodes','drop_shortcodes','unknown_shortcodes','risky_tags','samples']);
    foreach ($rows as $row) {
        $counts = (array)($row['counts'] ?? []);
        fputcsv($out, [
            $row['source_type'] ?? '',
            $row['source_title'] ?? '',
            $row['field'] ?? '',
            $row['severity'] ?? '',
            $counts['gutenberg_comments'] ?? 0,
            $counts['shortcodes'] ?? 0,
            $counts['drop_shortcodes'] ?? 0,
            $counts['unknown_shortcodes'] ?? 0,
            $counts['risky_tags'] ?? 0,
            implode(' | ', (array)($row['samples'] ?? [])),
        ]);
    }
    fclose($out);
    exit;
}

$baseUrl = static function (array $override = []) use ($type, $severity, $q): string {
    return url('admin/wp-content-cleaner?' . http_build_query(array_merge(['type'=>$type, 'severity'=>$severity, 'q'=>$q], $override)));
};

$typeLabels = ['all'=>'Semua','article'=>'Artikel','product'=>'Produk/Layanan','landing_page'=>'Landing Page'];
$severityLabels = ['all'=>'Semua','review'=>'Butuh review','needs_cleaning'=>'Perlu dibersihkan','clean'=>'Bersih'];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Shortcode & Gutenberg Cleaner - Admin',
    'description' => 'Preview dan bersihkan shortcode, Gutenberg comments, dan sisa plugin WordPress setelah migrasi ke U-Growth.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="admin-content" class="admin-shell admin-wp-content-cleaner-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Shortcode & Gutenberg Cleaner</h1>
                <p>Bersihkan residue WordPress seperti shortcode plugin, Gutenberg block comment, builder markup, dan tag berisiko dengan preview, backup, dan apply manual.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/wp-migration')); ?>">Migrasi WordPress</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/wp-media-migration')); ?>">Media Migration</a>
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
                    <span class="admin-badge">Cleaner Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($report['health_score'] ?? 0); ?>;"><strong><?= (int)($report['health_score'] ?? 0); ?></strong><span>/100</span></div>
                    <h2>Kebersihan Konten</h2>
                    <p>Skor turun jika masih ada shortcode/plugin residue, Gutenberg comment, unknown shortcode, atau tag berisiko.</p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Temuan</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($report['counts']['shortcodes'] ?? 0); ?></strong> shortcode</span>
                        <span><strong><?= (int)($report['counts']['gutenberg_comments'] ?? 0); ?></strong> Gutenberg</span>
                        <span><strong><?= (int)($report['counts']['risky_tags'] ?? 0); ?></strong> risky tag</span>
                    </div>
                    <p>Prioritaskan item review sebelum artikel lama dipublish ulang atau dijadikan money page.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Sumber Terdampak</span>
                    <h2><?= (int)($report['counts']['total'] ?? 0); ?> field perlu dicek</h2>
                    <p><?= (int)($report['counts']['articles'] ?? 0); ?> artikel · <?= (int)($report['counts']['products'] ?? 0); ?> produk/layanan · <?= (int)($report['counts']['landing_pages'] ?? 0); ?> LP.</p>
                    <div class="admin-cta-result-export-row"><a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export'=>'csv'])); ?>">Export CSV</a></div>
                </article>
            </div>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card">
                    <span class="admin-badge">Dry-run</span>
                    <h2>Preview Cleaner</h2>
                    <p>Dry-run menghitung konten yang akan berubah tanpa menyentuh storage.</p>
                    <form method="post" class="admin-form-grid">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="dry_clean">
                        <label class="admin-check"><input type="checkbox" name="remove_risky_tags" value="1" checked> Hapus script/style/iframe lama yang berisiko</label>
                        <label class="admin-check"><input type="checkbox" name="preserve_unknown_shortcodes" value="1" checked> Pertahankan unknown shortcode untuk review manual</label>
                        <button class="admin-btn admin-btn--light" type="submit">Dry-run Cleaner</button>
                    </form>
                </article>
                <article class="admin-card">
                    <span class="admin-badge">Apply Aman</span>
                    <h2>Bersihkan Konten</h2>
                    <p>Apply membuat backup storage otomatis, lalu membersihkan field yang tersimpan di JSON/runtime storage.</p>
                    <form method="post" onsubmit="return confirm('Apply cleaner sekarang? Backup storage akan dibuat otomatis sebelum perubahan.');" class="admin-form-grid">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="apply_clean">
                        <label class="admin-check"><input type="checkbox" name="remove_risky_tags" value="1" checked> Hapus script/style/iframe lama yang berisiko</label>
                        <label class="admin-check"><input type="checkbox" name="preserve_unknown_shortcodes" value="1" checked> Pertahankan unknown shortcode untuk review manual</label>
                        <button class="admin-btn admin-btn--primary" type="submit">Apply Cleaner</button>
                    </form>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-card-header"><div><span class="admin-badge">Scan Report</span><h2><?= count($rows); ?> field tampil</h2><p>Scan membaca artikel, produk/layanan, dan blok landing page yang mungkin membawa shortcode/plugin WordPress.</p></div></div>
                <form method="get" class="admin-filter-form">
                    <label><span>Tipe</span><select name="type"><?php foreach ($typeLabels as $key=>$label): ?><option value="<?= esc($key); ?>" <?= $type===$key?'selected':''; ?>><?= esc($label); ?></option><?php endforeach; ?></select></label>
                    <label><span>Status</span><select name="severity"><?php foreach ($severityLabels as $key=>$label): ?><option value="<?= esc($key); ?>" <?= $severity===$key?'selected':''; ?>><?= esc($label); ?></option><?php endforeach; ?></select></label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, shortcode, field..."></label>
                    <button class="admin-btn admin-btn--primary" type="submit">Filter</button>
                </form>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Sumber</th><th>Temuan</th><th>Status</th><th>Contoh</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($rows, 0, 200) as $row): $counts = (array)($row['counts'] ?? []); ?>
                            <tr>
                                <td><strong><?= esc((string)($row['source_title'] ?? '')); ?></strong><br><small><?= esc((string)($row['source_type'] ?? '')); ?> · <?= esc((string)($row['field'] ?? '')); ?></small></td>
                                <td><small>Shortcode: <?= (int)($counts['shortcodes'] ?? 0); ?> · Gutenberg: <?= (int)($counts['gutenberg_comments'] ?? 0); ?> · Unknown: <?= (int)($counts['unknown_shortcodes'] ?? 0); ?> · Risky: <?= (int)($counts['risky_tags'] ?? 0); ?></small></td>
                                <td><span class="admin-status-pill admin-status-pill--<?= ((string)($row['severity'] ?? '')) === 'review' ? 'warning' : 'ok'; ?>"><?= esc((string)($row['severity'] ?? '')); ?></span></td>
                                <td><?php foreach (array_slice((array)($row['samples'] ?? []), 0, 3) as $sample): ?><code><?= esc((string)$sample); ?></code><br><?php endforeach; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="4">Tidak ada shortcode/Gutenberg residue terdeteksi dari konten saat ini.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
