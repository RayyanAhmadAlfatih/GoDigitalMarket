<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$settings = function_exists('offer_cta_lab_settings') ? offer_cta_lab_settings(true) : [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'save') {
            $settings = offer_cta_lab_settings_from_post($_POST, $settings);
            offer_cta_lab_write_settings($settings, true);
            redirect_302('admin/offer-cta-testing?message=' . rawurlencode('Offer & CTA Testing Lab berhasil disimpan.'));
        }

        if ($action === 'quick_add') {
            offer_cta_lab_add_variant([
                'id' => (string)($_POST['id'] ?? ''),
                'title' => (string)($_POST['title'] ?? ''),
                'status' => 'draft',
                'goal' => (string)($_POST['goal'] ?? 'whatsapp_lead'),
                'placement' => (string)($_POST['placement'] ?? 'homepage_mid'),
                'channel' => (string)($_POST['channel'] ?? 'website'),
                'audience' => (string)($_POST['audience'] ?? ''),
                'hook' => (string)($_POST['hook'] ?? ''),
                'headline' => (string)($_POST['headline'] ?? ''),
                'subheadline' => (string)($_POST['subheadline'] ?? ''),
                'cta_label' => (string)($_POST['cta_label'] ?? ''),
                'cta_url' => (string)($_POST['cta_url'] ?? '/kontak'),
                'proof_note' => (string)($_POST['proof_note'] ?? ''),
                'hypothesis' => (string)($_POST['hypothesis'] ?? ''),
                'notes' => (string)($_POST['notes'] ?? ''),
            ]);
            redirect_302('admin/offer-cta-testing?message=' . rawurlencode('Ide offer/CTA ditambahkan sebagai draft.'));
        }

        if ($action === 'set_winner') {
            offer_cta_lab_set_winner((string)($_POST['id'] ?? ''));
            redirect_302('admin/offer-cta-testing?message=' . rawurlencode('Variant dipilih sebagai winner.'));
        }

        if ($action === 'delete_variant') {
            offer_cta_lab_delete_variant((string)($_POST['id'] ?? ''));
            redirect_302('admin/offer-cta-testing?message=' . rawurlencode('Variant offer/CTA sudah dihapus.'));
        }

        if ($action === 'reset') {
            offer_cta_lab_reset();
            redirect_302('admin/offer-cta-testing?message=' . rawurlencode('Offer & CTA Testing Lab dikembalikan ke bawaan.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = function_exists('offer_cta_lab_settings') ? offer_cta_lab_settings(true) : [];
$statusOptions = function_exists('offer_cta_lab_status_options') ? offer_cta_lab_status_options() : [];
$goalOptions = function_exists('offer_cta_lab_goal_options') ? offer_cta_lab_goal_options() : [];
$placementOptions = function_exists('offer_cta_lab_placement_options') ? offer_cta_lab_placement_options() : [];
$channelOptions = function_exists('offer_cta_lab_channel_options') ? offer_cta_lab_channel_options() : [];
$summary = function_exists('offer_cta_lab_summary') ? offer_cta_lab_summary($settings) : [];
$context = function_exists('offer_cta_lab_context_report') ? offer_cta_lab_context_report() : [];
$suggestions = function_exists('offer_cta_lab_suggestions') ? offer_cta_lab_suggestions(6) : [];

$statusFilter = (string)($_GET['status'] ?? 'all');
if ($statusFilter !== 'all' && !isset($statusOptions[$statusFilter])) {
    $statusFilter = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$variants = function_exists('offer_cta_lab_filter_variants') ? offer_cta_lab_filter_variants((array)($settings['variants'] ?? []), $statusFilter, $q) : (array)($settings['variants'] ?? []);

function admin_offer_cta_url(array $overrides = []): string
{
    $query = array_merge([
        'status' => $_GET['status'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/offer-cta-testing' . ($query ? '?' . http_build_query($query) : ''));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="offer-cta-testing-lab-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'context' => $context,
        'variants' => $variants,
        'suggestions' => $suggestions,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('offer_cta_lab_export_csv')) {
    offer_cta_lab_export_csv($variants);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Offer & CTA Testing Lab - Admin',
    'description' => 'Lab ringan untuk menyusun, membandingkan, dan memilih offer serta CTA terbaik untuk homepage, artikel, landing page, form, dan campaign.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-offer-cta-shell">
    <section class="admin-hero admin-offer-cta-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Offer & CTA Testing Lab</h1>
                <p>Bandingkan beberapa varian penawaran, headline, tombol, dan proof sebelum dipakai di homepage, artikel, landing page, form, trust block, atau campaign.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Profit Action</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-playbook')); ?>">Profit Playbook</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement')); ?>">Deploy Winner</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/landing-pages')); ?>">Landing Page</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-offer-cta-overview">
                <article class="admin-card admin-offer-cta-score-card">
                    <span class="admin-badge">Lab Score</span>
                    <div class="admin-offer-cta-score-ring" style="--score:<?= (int)($summary['average_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['average_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>Rata-rata kesiapan offer</h2>
                    <p><?= (int)($summary['total_variants'] ?? 0); ?> varian tersimpan · <?= (int)($summary['with_data'] ?? 0); ?> punya data manual.</p>
                </article>

                <article class="admin-card admin-offer-cta-winner-card">
                    <span class="admin-badge">Winner Candidate</span>
                    <?php $candidate = (array)($summary['best_candidate'] ?? []); ?>
                    <h2><?= esc((string)($candidate['title'] ?? 'Belum ada kandidat')); ?></h2>
                    <p><?= esc((string)($candidate['headline'] ?? 'Tambahkan varian offer dan CTA agar sistem bisa memberi kandidat terbaik.')); ?></p>
                    <?php if ($candidate): ?>
                        <div class="admin-offer-cta-preview-line"><strong><?= esc((string)($candidate['cta_label'] ?? 'CTA')); ?></strong><span>Score <?= (int)($candidate['score'] ?? 0); ?></span></div>
                    <?php endif; ?>
                </article>

                <article class="admin-card admin-offer-cta-context-card">
                    <span class="admin-badge">Signal Dibaca</span>
                    <div class="admin-offer-cta-mini-metrics">
                        <span><strong><?= (int)($context['products_count'] ?? 0); ?></strong> katalog</span>
                        <span><strong><?= (int)($context['articles_count'] ?? 0); ?></strong> artikel</span>
                        <span><strong><?= (int)($context['trust_enabled_blocks'] ?? 0); ?></strong> trust block</span>
                        <span><strong><?= (int)($context['profit_actions'] ?? 0); ?></strong> profit action</span>
                    </div>
                    <p>Ide otomatis dibuat dari katalog, artikel, trust block, dan dashboard profit agar admin tidak mulai dari nol.</p>
                </article>
            </div>

            <form class="admin-card admin-offer-cta-filter" method="get" action="<?= esc(url('admin/offer-cta-testing')); ?>">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Filter Lab</span>
                        <h2>Cari varian offer & CTA</h2>
                        <p>Gunakan filter ini saat jumlah eksperimen sudah mulai banyak.</p>
                    </div>
                    <div class="admin-report-filter-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan Filter</button>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_offer_cta_url(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_offer_cta_url(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </div>
                <div class="admin-report-filter-grid admin-offer-cta-filter-grid">
                    <label><span>Status</span><select name="status">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : ''; ?>>Semua status</option>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= esc((string)$value); ?>" <?= $statusFilter === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="Cari headline, CTA, target, atau placement..."></label>
                </div>
            </form>

            <form class="admin-stack" method="post" action="<?= esc(url('admin/offer-cta-testing')); ?>">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="save">

                <section class="admin-card admin-offer-cta-settings-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Pengaturan Lab</span>
                            <h2>Aktifkan dan catat arah testing</h2>
                            <p>Catatan ini membantu admin mengingat eksperimen apa yang sedang diprioritaskan.</p>
                        </div>
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Semua Varian</button>
                    </div>
                    <div class="admin-form-grid admin-form-grid--2">
                        <label class="admin-check-card admin-check-card--equal">
                            <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>>
                            <span><strong>Aktifkan Offer & CTA Lab</strong><small>Lab aktif untuk menyimpan ide, memilih winner, dan membaca score kesiapan.</small></span>
                        </label>
                        <label><span>Variant yang sedang dipakai</span><select name="active_variant_id">
                            <option value="">Belum dipilih</option>
                            <?php foreach ((array)($settings['variants'] ?? []) as $variant): ?>
                                <option value="<?= esc((string)($variant['id'] ?? '')); ?>" <?= (string)($settings['active_variant_id'] ?? '') === (string)($variant['id'] ?? '') ? 'selected' : ''; ?>><?= esc((string)($variant['title'] ?? 'Variant')); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    </div>
                    <label><span>Catatan testing</span><textarea name="testing_note" rows="3" placeholder="Contoh: Minggu ini fokus testing CTA WhatsApp dari artikel SEO ke katalog."><?= esc((string)($settings['testing_note'] ?? '')); ?></textarea></label>
                </section>

                <section class="admin-offer-cta-variant-grid">
                    <?php foreach ($variants as $i => $variant): ?>
                        <article class="admin-card admin-offer-cta-variant-card <?= (string)($variant['status'] ?? '') === 'winner' ? 'is-winner' : ''; ?>">
                            <div class="admin-offer-cta-variant-head">
                                <div>
                                    <span class="admin-badge"><?= esc((string)($statusOptions[$variant['status'] ?? 'draft'] ?? 'Draft')); ?> · Score <?= (int)($variant['score'] ?? 0); ?></span>
                                    <h2><?= esc((string)($variant['title'] ?? 'Variant')); ?></h2>
                                    <p><?= esc((string)($variant['hypothesis'] ?? 'Tambahkan hipotesis agar testing lebih terarah.')); ?></p>
                                </div>
                                <div class="admin-offer-cta-metric-pill">
                                    <strong><?= esc((string)($variant['ctr'] ?? 0)); ?>%</strong><span>CTR</span>
                                </div>
                            </div>

                            <input type="hidden" name="variants[<?= (int)$i; ?>][id]" value="<?= esc((string)($variant['id'] ?? '')); ?>">
                            <input type="hidden" name="variants[<?= (int)$i; ?>][created_at]" value="<?= esc((string)($variant['created_at'] ?? date(DATE_ATOM))); ?>">

                            <div class="admin-form-grid admin-form-grid--2">
                                <label><span>Nama varian</span><input type="text" name="variants[<?= (int)$i; ?>][title]" value="<?= esc((string)($variant['title'] ?? '')); ?>" maxlength="120"></label>
                                <label><span>Status</span><select name="variants[<?= (int)$i; ?>][status]">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($variant['status'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Goal</span><select name="variants[<?= (int)$i; ?>][goal]">
                                    <?php foreach ($goalOptions as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($variant['goal'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Placement</span><select name="variants[<?= (int)$i; ?>][placement]">
                                    <?php foreach ($placementOptions as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($variant['placement'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Channel</span><select name="variants[<?= (int)$i; ?>][channel]">
                                    <?php foreach ($channelOptions as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($variant['channel'] ?? '') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Target audience</span><input type="text" name="variants[<?= (int)$i; ?>][audience]" value="<?= esc((string)($variant['audience'] ?? '')); ?>" maxlength="180"></label>
                            </div>

                            <div class="admin-form-grid admin-form-grid--2">
                                <label><span>Hook / alasan klik</span><textarea name="variants[<?= (int)$i; ?>][hook]" rows="3"><?= esc((string)($variant['hook'] ?? '')); ?></textarea></label>
                                <label><span>Hipotesis testing</span><textarea name="variants[<?= (int)$i; ?>][hypothesis]" rows="3"><?= esc((string)($variant['hypothesis'] ?? '')); ?></textarea></label>
                            </div>

                            <label><span>Headline</span><input type="text" name="variants[<?= (int)$i; ?>][headline]" value="<?= esc((string)($variant['headline'] ?? '')); ?>" maxlength="160"></label>
                            <label><span>Subheadline</span><textarea name="variants[<?= (int)$i; ?>][subheadline]" rows="3"><?= esc((string)($variant['subheadline'] ?? '')); ?></textarea></label>

                            <div class="admin-form-grid admin-form-grid--2">
                                <label><span>Label CTA</span><input type="text" name="variants[<?= (int)$i; ?>][cta_label]" value="<?= esc((string)($variant['cta_label'] ?? '')); ?>" maxlength="80"></label>
                                <label><span>URL CTA</span><input type="text" name="variants[<?= (int)$i; ?>][cta_url]" value="<?= esc((string)($variant['cta_url'] ?? '')); ?>" placeholder="/kontak atau https://..."></label>
                            </div>

                            <label><span>Proof / alasan percaya</span><input type="text" name="variants[<?= (int)$i; ?>][proof_note]" value="<?= esc((string)($variant['proof_note'] ?? '')); ?>" maxlength="220"></label>

                            <div class="admin-offer-cta-manual-metrics">
                                <label><span>Impression</span><input type="number" min="0" name="variants[<?= (int)$i; ?>][impressions]" value="<?= (int)($variant['impressions'] ?? 0); ?>"></label>
                                <label><span>Click</span><input type="number" min="0" name="variants[<?= (int)$i; ?>][clicks]" value="<?= (int)($variant['clicks'] ?? 0); ?>"></label>
                                <label><span>Lead</span><input type="number" min="0" name="variants[<?= (int)$i; ?>][leads]" value="<?= (int)($variant['leads'] ?? 0); ?>"></label>
                                <label><span>Order</span><input type="number" min="0" name="variants[<?= (int)$i; ?>][orders]" value="<?= (int)($variant['orders'] ?? 0); ?>"></label>
                            </div>

                            <label><span>Catatan</span><textarea name="variants[<?= (int)$i; ?>][notes]" rows="3"><?= esc((string)($variant['notes'] ?? '')); ?></textarea></label>

                            <div class="admin-offer-cta-variant-actions">
                                <?php if ((string)($variant['cta_url'] ?? '') !== ''): ?>
                                    <a class="admin-btn admin-btn--soft" href="<?= esc(offer_cta_lab_url_to_href((string)($variant['cta_url'] ?? ''))); ?>" target="_blank" rel="noopener">Preview CTA</a>
                                <?php endif; ?>
                                <button class="admin-btn admin-btn--primary" type="submit">Simpan</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="admin-card admin-offer-cta-empty-row">
                    <div class="admin-form-head">
                        <span class="admin-badge">Tambah Manual</span>
                        <h2>Tambah 1 baris kosong</h2>
                        <p>Isi minimal nama varian, headline, atau CTA, lalu simpan.</p>
                    </div>
                    <?php $newIndex = count($variants) + 1; ?>
                    <div class="admin-form-grid admin-form-grid--3">
                        <input type="hidden" name="variants[<?= (int)$newIndex; ?>][id]" value="">
                        <label><span>Nama varian baru</span><input type="text" name="variants[<?= (int)$newIndex; ?>][title]" placeholder="Contoh: CTA Konsultasi Gratis"></label>
                        <label><span>Headline baru</span><input type="text" name="variants[<?= (int)$newIndex; ?>][headline]" placeholder="Butuh solusi yang paling pas?"></label>
                        <label><span>Label CTA baru</span><input type="text" name="variants[<?= (int)$newIndex; ?>][cta_label]" placeholder="Konsultasi Sekarang"></label>
                    </div>
                    <input type="hidden" name="variants[<?= (int)$newIndex; ?>][status]" value="draft">
                    <input type="hidden" name="variants[<?= (int)$newIndex; ?>][goal]" value="whatsapp_lead">
                    <input type="hidden" name="variants[<?= (int)$newIndex; ?>][placement]" value="homepage_mid">
                    <input type="hidden" name="variants[<?= (int)$newIndex; ?>][channel]" value="website">
                    <input type="hidden" name="variants[<?= (int)$newIndex; ?>][cta_url]" value="/kontak">
                    <div class="admin-form-actions"><button class="admin-btn admin-btn--primary" type="submit">Simpan Varian Baru</button></div>
                </section>
            </form>

            <section class="admin-card admin-offer-cta-suggestion-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Ide Otomatis</span>
                        <h2>Rekomendasi offer & CTA</h2>
                        <p>Ambil ide ini sebagai draft, lalu edit sesuai suara brand dan campaign yang sedang berjalan.</p>
                    </div>
                </div>
                <div class="admin-offer-cta-suggestion-grid">
                    <?php foreach ($suggestions as $suggestion): ?>
                        <article class="admin-offer-cta-suggestion">
                            <span><?= esc((string)($placementOptions[$suggestion['placement'] ?? ''] ?? 'Placement')); ?> · <?= esc((string)($goalOptions[$suggestion['goal'] ?? ''] ?? 'Goal')); ?></span>
                            <h3><?= esc((string)($suggestion['title'] ?? 'Ide Offer')); ?></h3>
                            <p><?= esc((string)($suggestion['headline'] ?? '')); ?></p>
                            <small><?= esc((string)($suggestion['hypothesis'] ?? '')); ?></small>
                            <form method="post" action="<?= esc(url('admin/offer-cta-testing')); ?>">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="quick_add">
                                <?php foreach (['id','title','goal','placement','channel','audience','hook','headline','subheadline','cta_label','cta_url','proof_note','hypothesis','notes'] as $field): ?>
                                    <input type="hidden" name="<?= esc($field); ?>" value="<?= esc((string)($suggestion[$field] ?? '')); ?>">
                                <?php endforeach; ?>
                                <button class="admin-btn admin-btn--soft" type="submit">Tambah sebagai Draft</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($variants): ?>
                <section class="admin-card admin-offer-cta-danger-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Aksi Cepat</span>
                            <h2>Pilih winner atau hapus varian</h2>
                            <p>Gunakan setelah varian sudah dibandingkan. Winner akan menjadi kandidat utama untuk dipasang di halaman dan campaign.</p>
                        </div>
                    </div>
                    <div class="admin-offer-cta-action-grid">
                        <?php foreach ($variants as $variant): ?>
                            <div class="admin-offer-cta-action-row">
                                <div><strong><?= esc((string)($variant['title'] ?? 'Variant')); ?></strong><span>Score <?= (int)($variant['score'] ?? 0); ?> · <?= esc((string)($statusOptions[$variant['status'] ?? 'draft'] ?? 'Draft')); ?></span></div>
                                <form method="post" action="<?= esc(url('admin/offer-cta-testing')); ?>">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="set_winner">
                                    <input type="hidden" name="id" value="<?= esc((string)($variant['id'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--soft" type="submit">Jadikan Winner</button>
                                </form>
                                <form method="post" action="<?= esc(url('admin/offer-cta-testing')); ?>" onsubmit="return confirm('Hapus varian ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_variant">
                                    <input type="hidden" name="id" value="<?= esc((string)($variant['id'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--danger" type="submit">Hapus</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <form class="admin-card" method="post" action="<?= esc(url('admin/offer-cta-testing')); ?>" onsubmit="return confirm('Reset semua varian ke bawaan?');">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="reset">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Reset</span>
                        <h2>Kembalikan ke bawaan</h2>
                        <p>Gunakan hanya kalau ingin memulai ulang lab dari contoh awal.</p>
                    </div>
                    <button class="admin-btn admin-btn--soft" type="submit">Reset Lab</button>
                </div>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
