<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }
require_admin_auth();

wp_elementor_import_ensure_storage();
$message = '';
$error = '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'import_job_pages') {
            $jobId = trim((string)($_POST['job_id'] ?? ''));
            $mode = (string)($_POST['mode'] ?? 'safe_html');
            $status = (string)($_POST['status'] ?? 'draft');
            $result = wp_elementor_import_job_pages($jobId, ['mode' => $mode, 'status' => $status]);
            $message = 'Import Elementor/Page Builder selesai: ' . (int)($result['created'] ?? 0) . ' LP draft dibuat, ' . (int)($result['skipped'] ?? 0) . ' dilewati, ' . (int)($result['failed'] ?? 0) . ' gagal.';
        } elseif ($action === 'refresh_report') {
            wp_elementor_import_report();
            $message = 'Scan ulang Elementor/Page Builder selesai.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$selectedJob = trim((string)($_GET['job_id'] ?? ''));
$report = wp_elementor_import_report($selectedJob !== '' ? $selectedJob : null);
$rows = (array)($report['rows'] ?? []);
$jobs = wp_elementor_import_jobs();

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="elementor-safe-import-' . date('Ymd-His') . '.json"');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="elementor-safe-import-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['job_id','title','slug','legacy_url','builder','confidence','complex_widgets','warnings']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['job_id'] ?? '',
            $row['title'] ?? '',
            $row['slug'] ?? '',
            $row['legacy_url'] ?? '',
            $row['builder'] ?? '',
            $row['confidence'] ?? '',
            implode('|', (array)($row['complex_widgets'] ?? [])),
            implode(' | ', (array)($row['warnings'] ?? [])),
        ]);
    }
    fclose($out);
    exit;
}

$baseUrl = static function (array $override = []) use ($selectedJob): string {
    return url('admin/wp-elementor-import?' . http_build_query(array_merge(['job_id' => $selectedJob], $override)));
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Elementor Safe HTML Block Import - Admin',
    'description' => 'Deteksi Elementor/page builder dari export WordPress dan import sebagai HTML block aman di Landing Page Builder U-Growth.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="admin-content" class="admin-shell admin-wp-elementor-import-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Elementor Safe HTML Block Import</h1>
                <p>Import halaman WordPress/Page Builder sebagai Landing Page draft dengan HTML block aman. Desain tidak dijanjikan 100% sama, tapi konten SEO dan struktur halaman bisa masuk dulu untuk direview.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/wp-migration')); ?>">Migrasi WordPress</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/wp-content-cleaner')); ?>">Content Cleaner</a>
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
                    <span class="admin-badge">Safe Import Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($report['score'] ?? 0); ?>;"><strong><?= (int)($report['score'] ?? 0); ?></strong><span>/100</span></div>
                    <h2>Kesiapan Page Builder</h2>
                    <p>Skor turun jika banyak widget kompleks seperti slider, popup, form plugin, atau custom script yang harus direview manual.</p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Deteksi</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($report['counts']['pages'] ?? 0); ?></strong> page/LP</span>
                        <span><strong><?= (int)($report['counts']['elementor'] ?? 0); ?></strong> Elementor</span>
                        <span><strong><?= (int)($report['counts']['complex'] ?? 0); ?></strong> kompleks</span>
                    </div>
                    <p>Gunakan mode HTML aman untuk migrasi cepat, lalu pecah ke block native U-Growth secara bertahap jika dibutuhkan.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Import Action</span>
                    <h2><?= (int)($report['counts']['safe_html_ready'] ?? 0); ?> halaman siap import aman</h2>
                    <p>Semua import dibuat draft agar admin bisa review sebelum publish.</p>
                    <div class="admin-cta-result-export-row"><a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export'=>'csv'])); ?>">Export CSV</a></div>
                </article>
            </div>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card">
                    <span class="admin-badge">Scan Job</span>
                    <h2>Pilih Job WordPress</h2>
                    <form method="get" class="admin-form-grid">
                        <label><span>Job Preview/Import</span><select name="job_id">
                            <option value="">Semua job</option>
                            <?php foreach ($jobs as $job): $jid = (string)($job['id'] ?? ''); ?>
                                <option value="<?= esc($jid); ?>" <?= $selectedJob === $jid ? 'selected' : ''; ?>><?= esc($jid . ' · ' . (string)($job['original_name'] ?? $job['stored_name'] ?? 'WordPress export')); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <button class="admin-btn admin-btn--primary" type="submit">Scan Page Builder</button>
                    </form>
                    <form method="post" class="admin-form-grid">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="refresh_report">
                        <button class="admin-btn admin-btn--light" type="submit">Refresh Report</button>
                    </form>
                </article>
                <article class="admin-card">
                    <span class="admin-badge">Import Aman</span>
                    <h2>Buat Landing Page Draft</h2>
                    <p>Import halaman/page builder sebagai LP draft. Mode aman menyimpan konten sebagai satu HTML block tersanitasi.</p>
                    <form method="post" onsubmit="return confirm('Import halaman dari job ini sebagai Landing Page draft?');" class="admin-form-grid">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="import_job_pages">
                        <label><span>Job</span><select name="job_id" required>
                            <option value="">Pilih job</option>
                            <?php foreach ($jobs as $job): $jid = (string)($job['id'] ?? ''); ?>
                                <option value="<?= esc($jid); ?>" <?= $selectedJob === $jid ? 'selected' : ''; ?>><?= esc($jid); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label><span>Mode</span><select name="mode"><option value="safe_html">HTML block aman</option><option value="mixed_native">Campuran: native sederhana + HTML fallback</option></select></label>
                        <label><span>Status</span><select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label>
                        <button class="admin-btn admin-btn--primary" type="submit">Import sebagai LP Draft</button>
                    </form>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-card-header"><div><span class="admin-badge">Report</span><h2><?= count($rows); ?> halaman/page builder</h2><p>Widget kompleks tidak dibuang diam-diam. Modul memberi warning agar admin tahu bagian mana yang perlu review manual.</p></div></div>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Halaman</th><th>Builder</th><th>Widget Kompleks</th><th>Warning</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($rows, 0, 200) as $row): ?>
                            <tr>
                                <td><strong><?= esc((string)($row['title'] ?? '')); ?></strong><br><small><?= esc((string)($row['slug'] ?? '')); ?> · <?= esc((string)($row['legacy_url'] ?? '')); ?></small><br><small><?= esc((string)($row['excerpt'] ?? '')); ?></small></td>
                                <td><span class="admin-status-pill admin-status-pill--ok"><?= esc((string)($row['builder'] ?? '')); ?></span><br><small>Confidence <?= (int)($row['confidence'] ?? 0); ?>%</small></td>
                                <td><?php foreach ((array)($row['complex_widgets'] ?? []) as $widget): ?><code><?= esc((string)$widget); ?></code><br><?php endforeach; ?><?php if (empty($row['complex_widgets'])): ?><span class="admin-muted">Tidak ada</span><?php endif; ?></td>
                                <td><?php foreach (array_slice((array)($row['warnings'] ?? []), 0, 4) as $warning): ?><small>• <?= esc((string)$warning); ?></small><br><?php endforeach; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="4">Belum ada page/LP WordPress dari job migrasi. Upload/preview XML WordPress dulu di menu Migrasi WordPress.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
