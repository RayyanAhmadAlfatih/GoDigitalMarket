<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$settings = function_exists('cta_placement_settings') ? cta_placement_settings(true) : [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'save') {
            $settings = cta_placement_settings_from_post($_POST, $settings);
            cta_placement_write_settings($settings, true);
            redirect_302('admin/cta-placement?message=' . rawurlencode('CTA Placement Assistant berhasil disimpan.'));
        }

        if ($action === 'quick_plan') {
            cta_placement_add_deployment((string)($_POST['variant_id'] ?? ''), (string)($_POST['placement'] ?? 'homepage_mid'));
            redirect_302('admin/cta-placement?message=' . rawurlencode('Rencana placement CTA berhasil ditambahkan.'));
        }

        if ($action === 'mark_status') {
            cta_placement_update_status((string)($_POST['id'] ?? ''), (string)($_POST['status'] ?? 'planned'));
            redirect_302('admin/cta-placement?message=' . rawurlencode('Status placement berhasil diperbarui.'));
        }

        if ($action === 'deploy_homepage_hero') {
            cta_placement_deploy_homepage_hero((string)($_POST['id'] ?? $_POST['deployment_action_id'] ?? ''));
            redirect_302('admin/cta-placement?message=' . rawurlencode('Winner CTA berhasil dipasang ke Homepage Hero.'));
        }

        if ($action === 'deploy_homepage_form') {
            cta_placement_deploy_homepage_form((string)($_POST['id'] ?? $_POST['deployment_action_id'] ?? ''));
            redirect_302('admin/cta-placement?message=' . rawurlencode('Winner CTA berhasil dipasang ke section Form Homepage.'));
        }

        if ($action === 'delete') {
            cta_placement_delete_deployment((string)($_POST['id'] ?? ''));
            redirect_302('admin/cta-placement?message=' . rawurlencode('Rencana placement sudah dihapus.'));
        }

        if ($action === 'reset') {
            cta_placement_reset();
            redirect_302('admin/cta-placement?message=' . rawurlencode('CTA Placement Assistant dikembalikan ke awal.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = function_exists('cta_placement_settings') ? cta_placement_settings(true) : [];
$summary = function_exists('cta_placement_summary') ? cta_placement_summary($settings) : [];
$statusOptions = function_exists('cta_placement_status_options') ? cta_placement_status_options() : [];
$priorityOptions = function_exists('cta_placement_priority_options') ? cta_placement_priority_options() : [];
$areaOptions = function_exists('cta_placement_area_options') ? cta_placement_area_options() : [];
$candidates = function_exists('cta_placement_candidate_variants') ? cta_placement_candidate_variants(8) : [];
$suggestions = function_exists('cta_placement_suggestions') ? cta_placement_suggestions(8) : [];
$deployments = (array)($settings['deployments'] ?? []);

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cta-placement-deployment-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'deployments' => $deployments,
        'suggestions' => $suggestions,
        'candidates' => $candidates,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('cta_placement_export_csv')) {
    cta_placement_export_csv($deployments);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'CTA Placement & Winner Deployment - Admin',
    'description' => 'Assistant untuk mengarahkan winner Offer Lab ke homepage, artikel, landing page, trust block, form, follow-up, dan campaign.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-cta-placement-shell">
    <section class="admin-hero admin-cta-placement-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>CTA Placement & Winner Deployment Assistant</h1>
                <p>Arahkan winner dari Offer Lab ke area paling strategis: homepage, artikel, landing page, trust block, form, follow-up, dan campaign.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/offer-cta-testing')); ?>">Offer Lab</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/homepage')); ?>">Homepage</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-playbook')); ?>">Profit Playbook</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-result-tracker')); ?>">CTA Result</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-placement-overview">
                <article class="admin-card admin-cta-placement-score-card">
                    <span class="admin-badge">Deployment Readiness</span>
                    <div class="admin-cta-placement-score-ring" style="--score:<?= (int)($summary['readiness_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['readiness_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>Kesiapan pasang winner</h2>
                    <p><?= esc((string)($summary['next_focus'] ?? 'Pilih winner dan buat rencana placement.')); ?></p>
                </article>

                <article class="admin-card admin-cta-placement-metric-card">
                    <span class="admin-badge">Pipeline Placement</span>
                    <div class="admin-cta-placement-mini-metrics">
                        <span><strong><?= (int)($summary['total_deployments'] ?? 0); ?></strong> rencana</span>
                        <span><strong><?= (int)($summary['one_click_ready'] ?? 0); ?></strong> one-click</span>
                        <span><strong><?= (int)($summary['deployed'] ?? 0); ?></strong> terpasang</span>
                        <span><strong><?= (int)($summary['winner_count'] ?? 0); ?></strong> winner</span>
                    </div>
                    <p>Gunakan pipeline ini agar CTA tidak cuma menang di lab, tapi benar-benar masuk ke halaman yang mendekati lead/order.</p>
                </article>

                <article class="admin-card admin-cta-placement-context-card">
                    <span class="admin-badge">Alur Kerja</span>
                    <ol class="admin-cta-placement-steps">
                        <li>Pilih winner atau kandidat kuat dari Offer Lab.</li>
                        <li>Tentukan area placement yang paling dekat dengan intent pengunjung.</li>
                        <li>Pasang, tandai status, lalu pantau hasilnya di CTA Result Tracker.</li>
                    </ol>
                    <div class="admin-cta-placement-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement?export=csv')); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement?export=json')); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-placement-candidates-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Winner Candidate</span>
                        <h2>Kandidat offer yang siap diarahkan</h2>
                        <p>Pilih varian dari Offer Lab, lalu buat rencana pemasangan ke area yang paling masuk akal.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/offer-cta-testing')); ?>">Kelola Offer Lab</a>
                </div>

                <?php if (!$candidates): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada kandidat offer</h3>
                        <p>Buat varian di Offer Lab dulu, pilih winner, lalu kembali ke halaman ini untuk deployment.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-cta-placement-candidate-grid">
                        <?php foreach ($candidates as $candidate): ?>
                            <?php $candidateScore = (int)($candidate['score'] ?? 0); ?>
                            <article class="admin-cta-placement-candidate">
                                <span class="admin-badge"><?= esc((string)($candidate['status'] ?? 'draft')); ?> · Score <?= $candidateScore; ?></span>
                                <h3><?= esc((string)($candidate['title'] ?? 'Offer')); ?></h3>
                                <p><?= esc((string)($candidate['headline'] ?? 'Headline belum diisi.')); ?></p>
                                <div class="admin-cta-placement-preview-line"><strong><?= esc((string)($candidate['cta_label'] ?? 'CTA')); ?></strong><span><?= esc((string)($candidate['placement'] ?? 'homepage_mid')); ?></span></div>
                                <form method="post" action="<?= esc(url('admin/cta-placement')); ?>" class="admin-cta-placement-inline-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="quick_plan">
                                    <input type="hidden" name="variant_id" value="<?= esc((string)($candidate['id'] ?? '')); ?>">
                                    <label><span>Pasang ke</span><select name="placement">
                                        <?php foreach ($areaOptions as $value => $area): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= (string)($candidate['placement'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)($area['label'] ?? $value)); ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                    <button class="admin-btn admin-btn--primary" type="submit">Buat Rencana</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-placement-suggestion-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Rekomendasi Cepat</span>
                        <h2>Area placement yang disarankan</h2>
                        <p>Rekomendasi ini membaca status winner, skor offer, goal, dan placement asal dari Offer Lab.</p>
                    </div>
                </div>

                <?php if (!$suggestions): ?>
                    <div class="admin-empty-state"><p>Belum ada rekomendasi. Tambahkan varian offer/CTA terlebih dahulu.</p></div>
                <?php else: ?>
                    <div class="admin-cta-placement-suggestion-grid">
                        <?php foreach ($suggestions as $suggestion): ?>
                            <article class="admin-cta-placement-suggestion">
                                <span class="admin-badge"><?= esc((string)($suggestion['placement_label'] ?? 'Placement')); ?> · <?= esc((string)($priorityOptions[$suggestion['priority'] ?? 'medium'] ?? 'Sedang')); ?></span>
                                <h3><?= esc((string)($suggestion['variant_title'] ?? 'Offer Winner')); ?></h3>
                                <p><?= esc((string)($suggestion['deployment_note'] ?? 'Cocok untuk placement ini.')); ?></p>
                                <form method="post" action="<?= esc(url('admin/cta-placement')); ?>">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="quick_plan">
                                    <input type="hidden" name="variant_id" value="<?= esc((string)($suggestion['variant_id'] ?? '')); ?>">
                                    <input type="hidden" name="placement" value="<?= esc((string)($suggestion['placement'] ?? 'homepage_mid')); ?>">
                                    <button class="admin-btn admin-btn--light" type="submit">Tambahkan ke Pipeline</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <form class="admin-stack" method="post" action="<?= esc(url('admin/cta-placement')); ?>">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="save">

                <section class="admin-card admin-cta-placement-settings-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Pengaturan</span>
                            <h2>Catatan deployment</h2>
                            <p>Catatan ini membantu admin mengingat strategi umum saat memasang winner CTA.</p>
                        </div>
                        <label class="admin-toggle-card admin-cta-placement-toggle">
                            <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>>
                            <span><strong>Aktifkan Assistant</strong><small>Tampilkan dan pakai pipeline deployment CTA.</small></span>
                        </label>
                    </div>
                    <label><span>Catatan strategi</span><textarea name="deployment_note" rows="3"><?= esc((string)($settings['deployment_note'] ?? '')); ?></textarea></label>
                </section>

                <section class="admin-cta-placement-deployment-grid">
                    <?php if (!$deployments): ?>
                        <article class="admin-card admin-empty-state">
                            <h2>Pipeline placement masih kosong</h2>
                            <p>Tambahkan rencana dari kandidat atau rekomendasi cepat di atas. Setelah itu admin bisa edit copy, target, status, dan area pemasangan.</p>
                        </article>
                    <?php endif; ?>

                    <?php foreach ($deployments as $i => $deployment): ?>
                        <?php $area = $areaOptions[$deployment['placement'] ?? 'homepage_mid'] ?? []; ?>
                        <article class="admin-card admin-cta-placement-deployment-card is-<?= esc((string)($deployment['priority'] ?? 'medium')); ?>">
                            <div class="admin-cta-placement-deployment-head">
                                <div>
                                    <span class="admin-badge"><?= esc((string)($statusOptions[$deployment['status'] ?? 'planned'] ?? 'Rencana')); ?> · <?= esc((string)($priorityOptions[$deployment['priority'] ?? 'medium'] ?? 'Sedang')); ?></span>
                                    <h2><?= esc((string)($deployment['target_label'] ?? 'Placement CTA')); ?></h2>
                                    <p><?= esc((string)($deployment['variant_title'] ?? 'Offer Winner')); ?></p>
                                </div>
                                <a class="admin-btn admin-btn--light" href="<?= esc(url((string)($deployment['admin_url'] ?? 'admin/offer-cta-testing'))); ?>">Buka Menu</a>
                            </div>

                            <input type="hidden" name="deployment_id[]" value="<?= esc((string)($deployment['id'] ?? '')); ?>">
                            <input type="hidden" name="variant_id[]" value="<?= esc((string)($deployment['variant_id'] ?? '')); ?>">
                            <input type="hidden" name="created_at[]" value="<?= esc((string)($deployment['created_at'] ?? date(DATE_ATOM))); ?>">

                            <div class="admin-form-grid admin-form-grid--2">
                                <label><span>Nama varian</span><input type="text" name="variant_title[]" value="<?= esc((string)($deployment['variant_title'] ?? '')); ?>" maxlength="140"></label>
                                <label><span>Area placement</span><select name="placement[]">
                                    <?php foreach ($areaOptions as $value => $option): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($deployment['placement'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)($option['label'] ?? $value)); ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Status</span><select name="status[]">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($deployment['status'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Prioritas</span><select name="priority[]">
                                    <?php foreach ($priorityOptions as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($deployment['priority'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Label target</span><input type="text" name="target_label[]" value="<?= esc((string)($deployment['target_label'] ?? '')); ?>" maxlength="140"></label>
                                <label><span>URL target halaman</span><input type="text" name="target_url[]" value="<?= esc((string)($deployment['target_url'] ?? '/')); ?>" maxlength="500"></label>
                                <label><span>Admin URL</span><input type="text" name="admin_url[]" value="<?= esc((string)($deployment['admin_url'] ?? '')); ?>" maxlength="160"></label>
                                <label><span>Label tombol CTA</span><input type="text" name="cta_label[]" value="<?= esc((string)($deployment['cta_label'] ?? '')); ?>" maxlength="70"></label>
                                <label><span>URL tombol CTA</span><input type="text" name="cta_url[]" value="<?= esc((string)($deployment['cta_url'] ?? '/kontak')); ?>" maxlength="500"></label>
                            </div>

                            <label><span>Headline</span><input type="text" name="headline[]" value="<?= esc((string)($deployment['headline'] ?? '')); ?>" maxlength="180"></label>
                            <label><span>Subheadline</span><textarea name="subheadline[]" rows="2"><?= esc((string)($deployment['subheadline'] ?? '')); ?></textarea></label>
                            <label><span>Proof / alasan percaya</span><textarea name="proof_note[]" rows="2"><?= esc((string)($deployment['proof_note'] ?? '')); ?></textarea></label>
                            <label><span>Hipotesis</span><textarea name="hypothesis[]" rows="2"><?= esc((string)($deployment['hypothesis'] ?? '')); ?></textarea></label>
                            <label><span>Catatan pemasangan</span><textarea name="item_deployment_note[]" rows="3"><?= esc((string)($deployment['deployment_note'] ?? '')); ?></textarea></label>
                            <label><span>Catatan hasil setelah dipasang</span><textarea name="last_result_note[]" rows="2"><?= esc((string)($deployment['last_result_note'] ?? '')); ?></textarea></label>

                            <div class="admin-cta-placement-preview-box">
                                <span class="admin-badge">Preview Copy</span>
                                <h3><?= esc((string)($deployment['headline'] ?? 'Headline CTA')); ?></h3>
                                <p><?= esc((string)($deployment['subheadline'] ?? 'Subheadline CTA')); ?></p>
                                <a class="admin-btn admin-btn--primary" href="<?= esc(cta_placement_clean_url($deployment['cta_url'] ?? '/kontak', '/kontak')); ?>" target="_blank" rel="noopener"><?= esc((string)($deployment['cta_label'] ?? 'Hubungi Kami')); ?></a>
                                <?php if (!empty($deployment['proof_note'])): ?><small><?= esc((string)$deployment['proof_note']); ?></small><?php endif; ?>
                            </div>

                            <div class="admin-cta-placement-checklist">
                                <span class="admin-badge">Checklist Pasang</span>
                                <ul>
                                    <?php foreach (cta_placement_checklist($deployment) as $item): ?>
                                        <li><?= esc((string)$item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <div class="admin-cta-placement-card-actions">
                                <?php if (!empty($area['one_click']) && (string)($deployment['placement'] ?? '') === 'homepage_hero'): ?>
                                    <button class="admin-btn admin-btn--primary" type="submit" name="action" value="deploy_homepage_hero" formaction="<?= esc(url('admin/cta-placement')); ?>" formmethod="post" onclick="this.form.querySelector('[name=deployment_action_id]').value='<?= esc((string)($deployment['id'] ?? '')); ?>';">Pasang ke Hero</button>
                                <?php endif; ?>
                                <?php if (!empty($area['one_click']) && (string)($deployment['placement'] ?? '') === 'homepage_bottom'): ?>
                                    <button class="admin-btn admin-btn--primary" type="submit" name="action" value="deploy_homepage_form" formaction="<?= esc(url('admin/cta-placement')); ?>" formmethod="post" onclick="this.form.querySelector('[name=deployment_action_id]').value='<?= esc((string)($deployment['id'] ?? '')); ?>';">Pasang ke Form</button>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <input type="hidden" name="deployment_action_id" value="">
                <div class="admin-sticky-actions">
                    <button class="admin-btn admin-btn--primary" type="submit" name="action" value="save">Simpan Pipeline Placement</button>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/offer-cta-testing')); ?>">Kembali ke Offer Lab</a>
                </div>
            </form>

            <?php if ($deployments): ?>
                <section class="admin-card admin-cta-placement-quick-actions">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Aksi Cepat</span>
                            <h2>Update status atau hapus rencana</h2>
                        </div>
                    </div>
                    <div class="admin-cta-placement-action-grid">
                        <?php foreach ($deployments as $deployment): ?>
                            <div class="admin-cta-placement-action-row">
                                <div>
                                    <strong><?= esc((string)($deployment['target_label'] ?? 'Placement CTA')); ?></strong>
                                    <small><?= esc((string)($deployment['variant_title'] ?? 'Offer')); ?></small>
                                </div>
                                <form method="post" action="<?= esc(url('admin/cta-placement')); ?>">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="mark_status">
                                    <input type="hidden" name="id" value="<?= esc((string)($deployment['id'] ?? '')); ?>">
                                    <select name="status">
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= (string)($deployment['status'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="admin-btn admin-btn--light" type="submit">Update</button>
                                </form>
                                <?php if ((string)($deployment['placement'] ?? '') === 'homepage_hero'): ?>
                                    <form method="post" action="<?= esc(url('admin/cta-placement')); ?>">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="deploy_homepage_hero">
                                        <input type="hidden" name="id" value="<?= esc((string)($deployment['id'] ?? '')); ?>">
                                        <button class="admin-btn admin-btn--primary" type="submit">One-click Hero</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((string)($deployment['placement'] ?? '') === 'homepage_bottom'): ?>
                                    <form method="post" action="<?= esc(url('admin/cta-placement')); ?>">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="deploy_homepage_form">
                                        <input type="hidden" name="id" value="<?= esc((string)($deployment['id'] ?? '')); ?>">
                                        <button class="admin-btn admin-btn--primary" type="submit">One-click Form</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= esc(url('admin/cta-placement')); ?>" onsubmit="return confirm('Hapus rencana placement ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= esc((string)($deployment['id'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--danger" type="submit">Hapus</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <form class="admin-card admin-danger-zone" method="post" action="<?= esc(url('admin/cta-placement')); ?>" onsubmit="return confirm('Reset semua pipeline placement CTA?');">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="reset">
                <h2>Reset Deployment Assistant</h2>
                <p>Menghapus pipeline placement yang tersimpan. Varian Offer Lab tidak akan ikut terhapus.</p>
                <button class="admin-btn admin-btn--danger" type="submit">Reset Pipeline</button>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
