<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$presets = function_exists('starter_wizard_presets') ? starter_wizard_presets() : [];
$stateForSelection = function_exists('starter_wizard_state') ? starter_wizard_state() : [];
$selectedPreset = (string)($_GET['preset'] ?? ($stateForSelection['selected_preset'] ?? 'hybrid_umkm'));
if (!isset($presets[$selectedPreset])) {
    $selectedPreset = isset($presets[(string)($stateForSelection['selected_preset'] ?? '')]) ? (string)$stateForSelection['selected_preset'] : 'hybrid_umkm';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['form_action'] ?? '');
        $postPreset = (string)($_POST['preset'] ?? $selectedPreset);
        if (!isset($presets[$postPreset])) {
            $postPreset = 'hybrid_umkm';
        }

        if ($action === 'apply_preset') {
            $stateBeforeApply = function_exists('starter_wizard_state') ? starter_wizard_state() : [];
            $activeSetupMode = (string)($stateBeforeApply['setup_mode'] ?? 'preset_full');
            starter_wizard_apply_preset($postPreset, $activeSetupMode);
            $applyMessage = $activeSetupMode === 'scratch'
                ? 'Build From Scratch aktif. Preset hanya dijadikan referensi; kategori dan konten publik disiapkan kosong/placeholder editable tanpa menghapus data lama.'
                : ($activeSetupMode === 'preset_structure'
                    ? 'Struktur preset berhasil diterapkan. Label, kategori, menu/header/footer aktif; konten publik disiapkan kosong/placeholder editable.'
                    : 'Preset starter berhasil diterapkan sebagai editable starter content tanpa menghapus data lama.');
            redirect_302('admin/starter-wizard?preset=' . rawurlencode($postPreset) . '&message=' . rawurlencode($applyMessage));
        }

        if ($action === 'set_setup_mode') {
            $setupMode = starter_wizard_clean($_POST['setup_mode'] ?? 'preset_full', 40);
            if (function_exists('starter_wizard_set_setup_mode')) {
                starter_wizard_set_setup_mode($setupMode, $postPreset);
            }
            if (function_exists('starter_wizard_apply_preset')) {
                starter_wizard_apply_preset($postPreset, $setupMode);
            }
            $msg = $setupMode === 'scratch'
                ? 'Build From Scratch Mode aktif. Struktur dan konten publik disiapkan kosong/placeholder editable; produk, artikel, order, dan lead lama tidak dihapus.'
                : ($setupMode === 'preset_structure'
                    ? 'Struktur preset aktif. Mode, label, kategori, menu/header/footer mengikuti niche; konten publik disiapkan kosong/placeholder editable.'
                    : 'Preset penuh berhasil diterapkan sebagai editable starter content dari dashboard admin.');
            redirect_302('admin/starter-wizard?preset=' . rawurlencode($postPreset) . '&message=' . rawurlencode($msg));
        }

        if ($action === 'convert_default_content') {
            $result = function_exists('starter_wizard_convert_default_content') ? starter_wizard_convert_default_content() : [];
            $msg = 'Konten bawaan template disiapkan agar editable. Produk dibuat: ' . (int)($result['products_created'] ?? 0) . ', artikel dibuat: ' . (int)($result['articles_created'] ?? 0) . ', halaman template: ' . (int)($result['template_pages_ready'] ?? 0) . '.';
            redirect_302('admin/starter-wizard?preset=' . rawurlencode($postPreset) . '&message=' . rawurlencode($msg));
        }

        if ($action === 'toggle_step') {
            $state = starter_wizard_state();
            $step = starter_wizard_clean($_POST['step'] ?? '', 40);
            $completed = (array)($state['completed_steps'] ?? []);
            if ($step !== '') {
                if (in_array($step, $completed, true)) {
                    $completed = array_values(array_filter($completed, static fn($item): bool => (string)$item !== $step));
                } else {
                    $completed[] = $step;
                }
            }
            $state['selected_preset'] = $postPreset;
            $state['completed_steps'] = array_values(array_unique($completed));
            starter_wizard_save_state($state);
            redirect_302('admin/starter-wizard?preset=' . rawurlencode($postPreset) . '&message=' . rawurlencode('Checklist onboarding berhasil diperbarui.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$state = function_exists('starter_wizard_state') ? starter_wizard_state() : ['completed_steps' => []];
$preset = function_exists('starter_wizard_preset') ? starter_wizard_preset($selectedPreset) : ($presets[$selectedPreset] ?? []);
$readiness = function_exists('starter_wizard_readiness') ? starter_wizard_readiness($state, $preset) : ['score' => 1, 'label' => 'Starter Wizard', 'done' => 0, 'total' => 6];
$steps = function_exists('starter_wizard_steps') ? starter_wizard_steps($preset) : [];
$currentBusiness = function_exists('business_current_mode') ? business_current_mode() : ['label' => 'Hybrid Growth Website'];
$completedSteps = (array)($state['completed_steps'] ?? []);
$setupModes = function_exists('starter_wizard_setup_modes') ? starter_wizard_setup_modes() : [];
$currentSetupMode = (string)($state['setup_mode'] ?? 'preset_full');
$currentSetupLabel = (string)($setupModes[$currentSetupMode]['label'] ?? $currentSetupMode);
$currentSetupBadge = (string)($setupModes[$currentSetupMode]['badge'] ?? 'Mode');
$activeSummary = function_exists('starter_wizard_active_summary') ? starter_wizard_active_summary($state, $preset, $currentBusiness) : [];
$editableInventory = function_exists('starter_wizard_editable_inventory') ? starter_wizard_editable_inventory($preset) : [];

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="website-starter-wizard-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'selected_preset' => $selectedPreset,
        'preset' => $preset,
        'readiness' => $readiness,
        'steps' => $steps,
        'state' => $state,
        'setup_modes' => $setupModes,
        'editable_inventory' => $editableInventory,
        'active_summary' => $activeSummary,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="website-starter-wizard-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['section', 'label', 'value', 'note'], ',', '"', '\\', "\n");
    fputcsv($out, ['preset', 'Niche', (string)($preset['label'] ?? $selectedPreset), (string)($preset['fit'] ?? '')], ',', '"', '\\', "\n");
    fputcsv($out, ['readiness', 'Score', (string)($readiness['score'] ?? 0), (string)($readiness['label'] ?? '')], ',', '"', '\\', "\n");
    fputcsv($out, ['setup_mode', 'Mode Setup', (string)($setupModes[$currentSetupMode]['label'] ?? $currentSetupMode), 'Preset/custom mode tetap editable'], ',', '"', '\\', "\n");
    foreach ((array)($preset['starter_pages'] ?? []) as $item) {
        fputcsv($out, ['starter_page', (string)$item, '', ''], ',', '"', '\\', "\n");
    }
    foreach ($steps as $key => $step) {
        fputcsv($out, ['checklist', (string)($step['label'] ?? $key), in_array((string)$key, $completedSteps, true) ? 'done' : 'todo', (string)($step['body'] ?? '')], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Website Starter Wizard - Admin',
    'description' => 'Wizard starter niche, onboarding checklist, dan setup awal UMKM Growth Web Template.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-starter-wizard-shell">
    <section class="admin-hero admin-starter-wizard-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">UMKM Growth Setup</div>
                <h1>Website Starter Wizard</h1>
                <p>Pilih niche bisnis, terapkan mode website, rapikan kategori, konten publik, menu navigasi, header/footer, lalu ikuti checklist onboarding agar template cepat siap dipakai pelaku UMKM.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/business')); ?>">Mode & Kategori</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/template-content')); ?>">Konten Template</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/navigation')); ?>">Menu & Footer</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <form method="get" action="<?= esc(url('admin/starter-wizard')); ?>" class="admin-card admin-report-filter admin-starter-wizard-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Starter Setting</span>
                        <h3>Atur preset niche bisnis</h3>
                    </div>
                    <p>Pilih preset sesuai jenis UMKM. Preset ini aman karena tidak menghapus produk, artikel, order, lead, atau data lama; menu/header/footer hanya disinkronkan sebagai starter yang tetap bisa diedit.</p>
                </div>
                <div class="admin-report-filter-grid admin-report-filter-grid--wide">
                    <label><span>Niche Starter</span>
                        <select name="preset">
                            <?php foreach ($presets as $key => $item): ?>
                                <option value="<?= esc((string)$key); ?>" <?= $selectedPreset === (string)$key ? 'selected' : ''; ?>><?= esc((string)($item['label'] ?? $key)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Setup Path Aktif</span><input type="text" value="<?= esc($currentSetupLabel); ?>" readonly></label>
                    <label><span>Mode Bisnis Aktif</span><input type="text" value="<?= esc((string)($currentBusiness['label'] ?? 'Hybrid Growth Website')); ?>" readonly></label>
                    <label><span>Score Setup</span><input type="text" value="<?= (int)($readiness['score'] ?? 0); ?>/100 - <?= esc((string)($readiness['label'] ?? 'Starter')); ?>" readonly></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan Filter</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/starter-wizard')); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(starter_wizard_current_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(starter_wizard_current_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-card admin-starter-wizard-flow-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Alur Starter Wizard</span>
                        <h2>Starter Setting → Setup Path → Setup Readiness</h2>
                        <p>Ketiganya saling terkait: pilih niche, pilih cara menerapkan, lalu cek kesiapan setup. Semua hasil tetap editable dari dashboard.</p>
                    </div>
                    <strong><?= esc($currentSetupBadge); ?> · <?= esc($currentSetupLabel); ?></strong>
                </div>
                <div class="admin-starter-wizard-flow-grid">
                    <?php foreach ($activeSummary as $summary): ?>
                        <div>
                            <span><?= esc((string)($summary['label'] ?? 'Info')); ?></span>
                            <strong><?= esc((string)($summary['value'] ?? '-')); ?></strong>
                            <p><?= esc((string)($summary['note'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card admin-starter-wizard-mode-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Setup Path</span>
                        <h2>Pilih cara mulai website</h2>
                        <p>Preset tidak mengunci isi website. Admin bisa pakai preset penuh, pakai struktur saja, atau bangun dari nol sambil eksplor semua fitur growth.</p>
                    </div>
                    <form method="post" onsubmit="return confirm('Siapkan konten bawaan template agar bisa diedit dari dashboard?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="convert_default_content">
                        <input type="hidden" name="preset" value="<?= esc($selectedPreset); ?>">
                        <button class="admin-btn admin-btn--soft" type="submit">Jadikan Konten Bawaan Editable</button>
                    </form>
                </div>
                <div class="admin-starter-wizard-mode-grid">
                    <?php foreach ($setupModes as $modeKey => $mode): ?>
                        <form method="post" class="admin-starter-wizard-mode <?= $currentSetupMode === (string)$modeKey ? 'is-active' : ''; ?>">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="set_setup_mode">
                            <input type="hidden" name="setup_mode" value="<?= esc((string)$modeKey); ?>">
                            <input type="hidden" name="preset" value="<?= esc($selectedPreset); ?>">
                            <span><?= esc((string)($mode['badge'] ?? 'Mode')); ?></span>
                            <strong><?= esc((string)($mode['label'] ?? $modeKey)); ?></strong>
                            <p><?= esc((string)($mode['description'] ?? '')); ?></p>
                            <button class="admin-btn <?= $currentSetupMode === (string)$modeKey ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" type="submit"><?= $currentSetupMode === (string)$modeKey ? 'Sedang Aktif' : 'Pilih Mode'; ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-starter-wizard-top-grid">
                <div class="admin-card admin-starter-wizard-score">
                    <span class="admin-badge">Setup Readiness</span>
                    <strong><?= (int)($readiness['score'] ?? 0); ?><small>/100</small></strong>
                    <h2><?= esc((string)($readiness['label'] ?? 'Starter Wizard')); ?></h2>
                    <p><?= (int)($readiness['done'] ?? 0); ?> dari <?= (int)($readiness['total'] ?? 6); ?> checklist onboarding sudah ditandai selesai.</p><small>Policy: semua preset dan template bawaan adalah editable starter content.</small>
                </div>
                <div class="admin-card admin-starter-wizard-preset-card">
                    <span class="admin-badge"><?= esc((string)($preset['badge'] ?? 'Niche Preset')); ?></span>
                    <h2><?= esc((string)($preset['label'] ?? 'Universal Business Growth')); ?></h2>
                    <p><?= esc((string)($preset['headline'] ?? 'Website starter untuk UMKM Growth.')); ?></p>
                    <small><?= esc((string)($preset['fit'] ?? 'Cocok untuk berbagai niche UMKM.')); ?></small>
                    <form method="post" onsubmit="return confirm('Terapkan preset ini? Data lama tidak dihapus. Mode, kategori, konten publik, menu, header, dan footer akan disinkronkan sebagai starter editable.');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="apply_preset">
                        <input type="hidden" name="preset" value="<?= esc($selectedPreset); ?>">
                        <button class="admin-btn admin-btn--primary" type="submit"><?= $currentSetupMode === 'scratch' ? 'Aktifkan Build From Scratch' : ($currentSetupMode === 'preset_structure' ? 'Terapkan Struktur Kosong' : 'Terapkan Preset Penuh'); ?></button>
                    </form>
                </div>
                <div class="admin-card admin-starter-wizard-cta-card">
                    <span class="admin-badge">Primary CTA</span>
                    <h2><?= esc((string)($preset['primary_cta'] ?? 'Konsultasi / Pesan')); ?></h2>
                    <p>CTA ini menjadi arah utama untuk halaman produk, layanan, landing page, form, menu/header, dan follow-up funnel.</p>
                    <div class="admin-starter-wizard-mini-actions">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/brand')); ?>">Brand</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/produk')); ?>">Katalog</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/navigation')); ?>">Menu</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/funnel-action-center')); ?>">Action</a>
                    </div>
                </div>
            </div>

            <div class="admin-card admin-starter-wizard-presets-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Niche Preset</span>
                        <h2>Pilih starter sesuai model bisnis</h2>
                        <p>Preset membantu admin non-teknis langsung punya arah kategori, CTA, halaman prioritas, blok konversi, dan sprint 30 hari.</p>
                    </div>
                </div>
                <div class="admin-starter-wizard-preset-grid">
                    <?php foreach ($presets as $key => $item): ?>
                        <a class="admin-starter-wizard-preset <?= $selectedPreset === (string)$key ? 'is-active' : ''; ?>" href="<?= esc(url('admin/starter-wizard?preset=' . rawurlencode((string)$key))); ?>">
                            <span><?= esc((string)($item['badge'] ?? 'Preset')); ?></span>
                            <strong><?= esc((string)($item['label'] ?? $key)); ?></strong>
                            <small><?= esc((string)($item['fit'] ?? '')); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-grid-2 admin-starter-wizard-plan-grid">
                <div class="admin-card admin-starter-wizard-card">
                    <span class="admin-badge">Starter Pages</span>
                    <h2>Halaman prioritas</h2>
                    <div class="admin-starter-wizard-list">
                        <?php foreach ((array)($preset['starter_pages'] ?? []) as $item): ?>
                            <div><strong><?= esc((string)$item); ?></strong><p>Masuk daftar halaman awal yang sebaiknya disiapkan untuk niche ini.</p></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="admin-card admin-starter-wizard-card">
                    <span class="admin-badge">Conversion Blocks</span>
                    <h2>Blok yang disarankan</h2>
                    <div class="admin-starter-wizard-chip-list">
                        <?php foreach ((array)($preset['blocks'] ?? []) as $item): ?>
                            <span><?= esc((string)$item); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="admin-card admin-starter-wizard-category-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Flexible Category Preview</span>
                        <h2>Kategori awal yang akan ditambahkan</h2>
                        <p>Kategori preset ditambahkan secara aman tanpa menghapus data yang sudah ada.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/business')); ?>">Kelola Manual</a>
                </div>
                <div class="admin-starter-wizard-category-grid">
                    <?php foreach (['catalog' => 'Katalog', 'article' => 'Artikel SEO', 'portfolio' => 'Portfolio'] as $domain => $title): ?>
                        <div>
                            <strong><?= esc($title); ?></strong>
                            <?php foreach ((array)($preset['categories'][$domain] ?? []) as $cat): ?>
                                <span><?= esc((string)$cat); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card admin-starter-wizard-editable-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Editable Preset Manager</span>
                        <h2>Semua hasil starter bisa diedit dari dashboard</h2>
                        <p>Inventory ini memastikan preset niche dan konten awal menjadi starter content yang bisa dikelola. Admin bisa edit, tambah, sembunyikan, hapus, atau bangun ulang dari nol.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/template-content')); ?>">Edit Halaman Bawaan</a>
                </div>
                <div class="admin-starter-wizard-editable-grid">
                    <?php foreach ($editableInventory as $item): ?>
                        <a class="admin-starter-wizard-editable-item" href="<?= esc(url((string)($item['href'] ?? 'admin'))); ?>">
                            <span><?= esc((string)($item['status'] ?? 'Editable')); ?></span>
                            <strong><?= esc((string)($item['area'] ?? 'Area')); ?></strong>
                            <small><?= (int)($item['count'] ?? 0); ?> item/setting</small>
                            <p><?= esc((string)($item['note'] ?? 'Bisa dikelola dari dashboard.')); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card admin-starter-wizard-checklist-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Onboarding Checklist</span>
                        <h2>Langkah agar website cepat siap dipakai</h2>
                        <p>Checklist ini membantu UMKM tidak bingung setelah install: mulai dari brand, katalog, SEO, CTA, sampai laporan growth.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/growth-snapshot')); ?>">Lihat Growth Snapshot</a>
                </div>
                <div class="admin-starter-wizard-checklist">
                    <?php foreach ($steps as $key => $step): ?>
                        <?php $done = in_array((string)$key, $completedSteps, true); ?>
                        <div class="<?= $done ? 'is-done' : ''; ?>">
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="toggle_step">
                                <input type="hidden" name="preset" value="<?= esc($selectedPreset); ?>">
                                <input type="hidden" name="step" value="<?= esc((string)$key); ?>">
                                <button type="submit" aria-label="Toggle checklist <?= esc((string)($step['label'] ?? $key)); ?>"><?= $done ? '✓' : '+'; ?></button>
                            </form>
                            <div>
                                <strong><?= esc((string)($step['label'] ?? $key)); ?></strong>
                                <p><?= esc((string)($step['body'] ?? '')); ?></p>
                            </div>
                            <a href="<?= esc(url((string)($step['href'] ?? 'admin'))); ?>">Buka</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card admin-starter-wizard-sprint-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">30 Hari Pertama</span>
                        <h2>Sprint starter untuk niche ini</h2>
                        <p>Urutan kerja singkat agar template tidak cuma tampil, tapi mulai bergerak ke traffic, lead, dan conversion.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-content-planner')); ?>">Buka Content Planner</a>
                </div>
                <div class="admin-starter-wizard-sprint">
                    <?php foreach ((array)($preset['sprint'] ?? []) as $index => $item): ?>
                        <div><span>Minggu <?= $index + 1; ?></span><strong><?= esc((string)$item); ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
