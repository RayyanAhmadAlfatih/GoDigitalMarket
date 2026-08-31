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
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_note') {
            release_audit_save_note((string)($_POST['review_note'] ?? ''), (string)($_POST['review_owner'] ?? ''));
            redirect_302('admin/release-audit?message=' . rawurlencode('Catatan release audit berhasil disimpan.'));
        }
        if ($action === 'toggle_check') {
            release_audit_toggle_check((string)($_POST['check_id'] ?? ''), !empty($_POST['done']), (string)($_POST['check_note'] ?? ''));
            redirect_302('admin/release-audit?message=' . rawurlencode('Checklist release berhasil diperbarui.'));
        }
        if ($action === 'reset') {
            release_audit_reset_state();
            redirect_302('admin/release-audit?message=' . rawurlencode('Catatan release audit sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = release_audit_summary();

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="release-audit-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_GET['export'] ?? '') === 'csv') {
    release_audit_export_csv($summary);
}
if (($_GET['export'] ?? '') === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="release-audit-' . date('Ymd-His') . '.txt"');
    echo release_audit_plain_text($summary);
    exit;
}

$scores = (array)($summary['scores'] ?? []);
$modules = (array)($summary['modules'] ?? []);
$checks = [
    'Security & Storage' => (array)($summary['security_checks'] ?? []),
    'Data Flow' => (array)($summary['data_flow_checks'] ?? []),
    'Public Copy' => (array)($summary['public_copy_checks'] ?? []),
    'Seed & Default Content' => (array)($summary['default_content_checks'] ?? []),
    'Workflow Admin' => (array)($summary['workflow_checks'] ?? []),
    'Admin CRUD Runtime' => (array)($summary['admin_crud_runtime_checks'] ?? []),
    'Public Submit Runtime' => (array)($summary['public_submission_runtime_checks'] ?? []),
    'Role Permission Runtime' => (array)($summary['role_permission_runtime_checks'] ?? []),
    'HTTP E2E' => (array)($summary['http_e2e_checks'] ?? []),
    'Commerce Runtime' => (array)($summary['commerce_runtime_checks'] ?? []),
    'Buyer Access & Restriction' => (array)($summary['buyer_access_restriction_checks'] ?? []),
    'Final Release Candidate' => (array)($summary['final_release_candidate_checks'] ?? []),
    'Admin Layout CSS' => (array)($summary['admin_layout_css_checks'] ?? []),
    'Storage & Database' => (array)($summary['storage_database_checks'] ?? []),
    'Backup & Sync Data' => (array)($summary['cloud_backup_checks'] ?? []),
    'LP Builder Regression' => (array)($summary['lp_builder_regression_checks'] ?? []),
    'Route & Auth' => (array)($summary['route_checks'] ?? []),
];
$critical = (array)($summary['critical_findings'] ?? []);
$state = (array)($summary['state'] ?? []);
$manualChecklist = [
    'upload-staging' => 'Upload ZIP ke staging/local sebelum production.',
    'login-admin' => 'Login dashboard dan cek menu utama admin.',
    'public-pages' => 'Buka homepage, katalog, artikel, produk/detail, dan form.',
    'lead-test' => 'Tes klik CTA/form agar Lead Tracking terbaca.',
    'commerce-runtime-test' => 'Tes checkout, cek ongkir, invoice, bukti bayar, reminder, status lunas, dan akses produk.',
    'buyer-access-test' => 'Tes member area dan restriction konten untuk pembeli produk tertentu.',
    'storage-database-test' => 'Cek Storage & Database: pastikan mode file/hybrid/MySQL sesuai rencana migrasi.',
    'cloud-backup-sync-test' => 'Cek Backup & Sync Data: export lead/order dan endpoint Google Sheets/Drive jika sudah siap.',
    'lp-builder-regression-test' => 'Tes LP Builder: preview vs frontend, sticky custom menu, warna tombol, mini footer, form, HTML safe mode, dan mobile preview.',
    'backup-before-live' => 'Backup hosting/VPS sebelum replace source production.',
    'env-production' => 'Pastikan APP_URL, ADMIN_PASSWORD, SMTP/payment/analytics production sudah diisi.',
    'ssl-canonical' => 'Cek SSL/domain/canonical dari browser nyata setelah upload.',
];
$manualState = (array)($state['checklist'] ?? []);

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Audit Kesiapan Website - Admin',
    'description' => 'Audit final untuk mengecek keamanan, route, alur data growth, copy publik, dan kesiapan release U-Growth Web Template.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-command-center-shell">
    <section class="admin-hero admin-profit-report-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Audit Kesiapan Website</div>
                <h1>Audit Kesiapan Website</h1>
                <p>Audit untuk memastikan route commerce, keamanan dasar, alur data growth, copy publik, dan kesiapan source tetap aman untuk dipakai pelaku UMKM.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/u-growth-command-center')); ?>">Command Center</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/production-readiness')); ?>">Kesiapan Website</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/data-health')); ?>">Cek Sistem</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Ringkasan Audit</span>
                        <h2>Skor kesiapan otomatis</h2>
                        <p>Skor ini membaca wiring modul, keamanan dasar, route, seed/default content, workflow admin, CRUD admin, submit publik, permission role, HTTP E2E, final release candidate, LP Builder regression, copy publik, dan alur data growth. Hasil audit ini tidak membuat tracking baru.</p>
                    </div>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/release-audit?export=csv')); ?>">Ekspor CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/release-audit?export=json')); ?>">Ekspor JSON</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/release-audit?export=text')); ?>">Ekspor Teks</a>
                    </div>
                </div>
            </section>

            <div class="admin-cta-result-overview admin-profit-report-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Skor Kesiapan</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['overall_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['overall_score'] ?? 0); ?></strong><span>%</span>
                    </div>
                    <h2><?= esc((string)($summary['score_label'] ?? 'Audit')); ?></h2>
                    <p>Skor gabungan keamanan, kerapian source, route, alur data, dan modul growth.</p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Temuan Kritis</span>
                    <h2><?= count($critical); ?> temuan</h2>
                    <p><?= count($critical) === 0 ? 'Tidak ada temuan kritis dari audit otomatis.' : 'Ada item yang perlu dicek sebelum upload production.'; ?></p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/activity-log')); ?>">Log Sistem</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/maintenance')); ?>">Backup</a>
                    </div>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Growth Loop</span>
                    <h2>SEO → CTA → Lead → Order</h2>
                    <p>Audit memastikan modul growth membaca Lead Tracking existing, bukan membuat sistem tracking dobel.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/leads')); ?>">Tracking Lead</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-profit-attribution')); ?>">SEO Profit</a>
                    </div>
                </article>
            </div>

            <section class="admin-grid admin-grid--stats admin-report-main-stats">
                <?php foreach ($scores as $label => $score): ?>
                    <article class="admin-card admin-report-metric">
                        <span class="admin-badge"><?= esc(ucwords(str_replace('_', ' ', (string)$label))); ?></span>
                        <h2><?= (int)$score; ?>%</h2>
                        <p><?= (int)$score >= 100 ? 'Aman.' : 'Masih ada catatan ringan yang perlu dicek.'; ?></p>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if ($critical): ?>
                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Perlu Dicek</span>
                            <h2>Temuan audit otomatis</h2>
                            <p>Kerjakan temuan ini dulu sebelum upload ke production.</p>
                        </div>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php foreach ($critical as $item): ?>
                            <article class="admin-mini-card">
                                <strong><?= esc((string)($item['title'] ?? 'Temuan')); ?></strong>
                                <p><?= esc((string)($item['note'] ?? 'Perlu dicek.')); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Sambungan Modul</span>
                        <h2>Audit sambungan modul growth</h2>
                        <p>Setiap modul dicek dari route, page, core helper, function, registry menu, dan Pusat Bantuan. Menu yang sengaja disembunyikan lewat Menu & Fitur Admin tetap dihitung aman selama route, file, dan logic-nya tersedia.</p>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Modul</th><th>Group</th><th>Sumber Data</th><th>Menu</th><th>Score</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td><strong><?= esc((string)($module['title'] ?? 'Module')); ?></strong><br><small><?= esc((string)($module['route'] ?? '')); ?></small></td>
                                    <td><?= esc((string)($module['group'] ?? 'Growth')); ?></td>
                                    <td><?= esc((string)($module['source'] ?? '-')); ?></td>
                                    <td><span class="admin-badge"><?= esc((string)($module['menu_status'] ?? '-')); ?></span></td>
                                    <td><?= (int)($module['score'] ?? 0); ?>%</td>
                                    <td><span class="admin-badge"><?= esc((string)($module['status'] ?? 'OK')); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="admin-grid admin-grid--two">
                <?php foreach ($checks as $groupLabel => $items): ?>
                    <section class="admin-card">
                        <div class="admin-card-header">
                            <div>
                                <span class="admin-badge"><?= esc($groupLabel); ?></span>
                                <h2>Checklist <?= esc($groupLabel); ?></h2>
                            </div>
                        </div>
                        <div class="admin-stack admin-stack--sm">
                            <?php foreach ($items as $item): ?>
                                <article class="admin-mini-card">
                                    <strong><?= !empty($item['ok']) ? '✅' : '⚠️'; ?> <?= esc((string)($item['title'] ?? 'Check')); ?></strong>
                                    <p><?= esc((string)($item['note'] ?? '')); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Manual Release Checklist</span>
                        <h2>Checklist sebelum upload production</h2>
                        <p>Bagian ini untuk catatan admin/tim. Audit otomatis sudah membantu, tapi tes manual di hosting tetap penting.</p>
                    </div>
                    <form method="post" onsubmit="return confirm('Reset catatan release audit?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset">
                        <button class="admin-btn admin-btn--light" type="submit">Reset Catatan</button>
                    </form>
                </div>
                <div class="admin-grid admin-grid--two">
                    <?php foreach ($manualChecklist as $id => $label): ?>
                        <?php $itemState = (array)($manualState[$id] ?? []); ?>
                        <form method="post" class="admin-mini-card admin-stack admin-stack--sm">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle_check">
                            <input type="hidden" name="check_id" value="<?= esc($id); ?>">
                            <label class="analytics-check"><input type="checkbox" name="done" value="1" <?= !empty($itemState['done']) ? 'checked' : ''; ?>> <strong><?= esc($label); ?></strong></label>
                            <textarea name="check_note" rows="3" placeholder="Catatan singkat opsional..."><?= esc((string)($itemState['note'] ?? '')); ?></textarea>
                            <button class="admin-btn admin-btn--soft" type="submit">Simpan Checklist</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="admin-card admin-profit-report-copy-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Release Note</span>
                        <h2>Catatan final untuk tim</h2>
                        <p>Simpan catatan ringkas sebelum website dipindah ke production.</p>
                    </div>
                </div>
                <form method="post" class="admin-stack">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="save_note">
                    <label>Penanggung jawab
                        <input type="text" name="review_owner" value="<?= esc((string)($state['review_owner'] ?? '')); ?>" placeholder="Nama admin/tim">
                    </label>
                    <label>Catatan release
                        <textarea name="review_note" rows="8" placeholder="Contoh: sudah upload ke staging, homepage aman, form sudah dites, backup production sudah siap."><?= esc((string)($state['review_note'] ?? '')); ?></textarea>
                    </label>
                    <button class="admin-btn" type="submit">Simpan Catatan Release</button>
                </form>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
