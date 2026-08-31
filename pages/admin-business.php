<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$current = business_settings();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['form_action'] ?? 'save_business');

        if ($action === 'reset') {
            business_reset_settings();
            redirect_302('admin/business?message=' . rawurlencode('Mode bisnis dan kategori sudah dikembalikan ke bawaan template.'));
        }

        if ($action === 'save_categories') {
            business_save_settings(business_category_settings_from_post($_POST, $current));
            if (function_exists('activity_log_record')) {
                activity_log_record('update_business_categories', 'system', null, 'Admin mengubah kategori fleksibel website.', ['area' => 'business']);
            }
            redirect_302('admin/business?message=' . rawurlencode('Kategori fleksibel berhasil disimpan.'));
        }

        business_save_settings(business_settings_from_post($_POST, $current));
        if (function_exists('activity_log_record')) {
            activity_log_record('update_business_mode', 'system', null, 'Admin mengubah mode bisnis website.', ['area' => 'business', 'mode' => (string)($_POST['business_mode'] ?? '')]);
        }
        redirect_302('admin/business?message=' . rawurlencode('Mode bisnis, label, dan visibilitas berhasil disimpan.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = business_settings();
$modes = business_mode_definitions();
$currentMode = (string)($settings['business_mode'] ?? 'hybrid');
$currentDefinition = $modes[$currentMode] ?? $modes['hybrid'];
$labels = (array)($settings['labels'] ?? []);
$visibility = (array)($settings['visibility'] ?? []);
$categoryDomains = [
    'catalog' => ['title' => 'Kategori Katalog Produk/Jasa', 'hint' => 'Dipakai di admin katalog, filter katalog publik, SEO landing, order, dan tracking lead.'],
    'article' => ['title' => 'Kategori Artikel SEO', 'hint' => 'Dipakai di admin artikel, halaman artikel publik, funnel konten, dan rekomendasi konten terkait.'],
    'portfolio' => ['title' => 'Kategori Portfolio / Showcase', 'hint' => 'Fondasi untuk karya, case study, result, dan showcase bisnis.'],
];

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Mode Bisnis & Kategori - ' . SITE_NAME,
    'description' => 'Atur mode website, label menu bisnis, kategori fleksibel, dan fondasi portfolio/one-page/multi-page.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-business-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Universal Template Core</div>
                <h1>Mode Bisnis & Kategori Fleksibel</h1>
                <p>Jadikan website ini cocok untuk toko produk, jasa, personal branding, portfolio, company profile, produk digital, landing page, atau model hybrid dengan pengaturan yang fleksibel.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/produk')); ?>">Kelola Katalog</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--4">
                <div class="admin-card"><span class="admin-badge">Mode Aktif</span><h2><?= esc((string)$currentDefinition['label']); ?></h2><p><?= esc((string)$currentDefinition['description']); ?></p></div>
                <div class="admin-card"><span class="admin-badge">Layout</span><h2><?= (($settings['layout_mode'] ?? '') === 'one_page') ? 'One Page' : 'Multi Page'; ?></h2><p>Website bisa dipakai sebagai landing/portfolio ringkas atau website lengkap banyak halaman.</p></div>
                <div class="admin-card"><span class="admin-badge">Schema</span><h2><?= esc((string)($settings['schema_profile'] ?? 'LocalBusiness')); ?></h2><p>Profil SEO dasar agar template lebih relevan untuk niche bisnis berbeda.</p></div>
                <div class="admin-card"><span class="admin-badge">Kategori</span><h2><?= count(business_category_labels('catalog', true)); ?> Katalog</h2><p><?= count(business_category_labels('article', true)); ?> artikel dan <?= count(business_category_labels('portfolio', true)); ?> portfolio aktif.</p></div>
            </div>

            <div class="admin-navigation-layout" data-admin-page-tab-scope>
                <div class="admin-navigation-main">
                    <div class="admin-page-subtabs admin-page-subtabs--4" role="tablist" aria-label="Bagian Mode Bisnis">
                        <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="business-mode"><span>1. Mode Website</span><small>Jenis bisnis</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="business-labels"><span>2. Label Bisnis</span><small>Istilah menu</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="business-categories"><span>3. Kategori Fleksibel</span><small>Katalog, artikel, portfolio</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="business-guide"><span>4. Panduan Scale</span><small>Arah pemakaian</small></button>
                    </div>
                    <div class="admin-page-mobile-jump">
                        <label class="admin-field"><span>Pilih bagian</span><select data-admin-page-tab-select aria-label="Pilih bagian Mode Bisnis"><option value="business-mode">1. Mode Website</option><option value="business-labels">2. Label Bisnis</option><option value="business-categories">3. Kategori Fleksibel</option><option value="business-guide">4. Panduan Scale</option></select></label>
                    </div>

                    <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="business-mode">
                        <form method="post" class="admin-card admin-editor">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="save_business">
                            <div class="admin-form-head">
                                <span class="admin-badge">Mode Website</span>
                                <h2>Pilih Model Bisnis Utama</h2>
                                <p>Mode ini membantu menyesuaikan label, visibilitas, schema, dan strategi konten agar website lebih relevan dengan model bisnis yang dipilih.</p>
                            </div>

                            <div class="homepage-mode-grid">
                                <?php foreach ($modes as $key => $mode): ?>
                                    <label class="homepage-mode-card <?= $currentMode === $key ? 'is-selected' : ''; ?>">
                                        <input type="radio" name="business_mode" value="<?= esc((string)$key); ?>" <?= $currentMode === $key ? 'checked' : ''; ?>>
                                        <strong><?= esc((string)($mode['label'] ?? $key)); ?></strong>
                                        <span><?= esc((string)($mode['description'] ?? '')); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="admin-form-grid" style="margin-top:18px">
                                <label class="admin-field">
                                    <span>Mode Layout</span>
                                    <select name="layout_mode">
                                        <option value="multi_page" <?= (($settings['layout_mode'] ?? 'multi_page') === 'multi_page') ? 'selected' : ''; ?>>Multi Page / Website lengkap</option>
                                        <option value="one_page" <?= (($settings['layout_mode'] ?? '') === 'one_page') ? 'selected' : ''; ?>>One Page / Portfolio atau campaign ringkas</option>
                                    </select>
                                </label>
                                <label class="admin-field">
                                    <span>Schema Profil SEO</span>
                                    <select name="schema_profile">
                                        <?php foreach (['LocalBusiness','Organization','Person','Service','Product','Course','CreativeWork','WebPage'] as $schema): ?>
                                            <option value="<?= esc($schema); ?>" <?= (($settings['schema_profile'] ?? '') === $schema) ? 'selected' : ''; ?>><?= esc($schema); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="admin-check admin-field--wide"><input type="checkbox" name="apply_mode_preset" value="1"> Terapkan preset label & visibilitas bawaan sesuai mode yang dipilih</label>
                                <label class="admin-field admin-field--wide">
                                    <span>Catatan Strategi Internal</span>
                                    <textarea name="notes" rows="3" maxlength="300" placeholder="Contoh: Fokus utama lead WhatsApp, katalog dipakai sebagai produk digital + mentoring."><?= esc((string)($settings['notes'] ?? '')); ?></textarea>
                                    <small>Catatan ini hanya tampil di admin, tidak keluar ke frontend publik.</small>
                                </label>
                            </div>

                            <div class="admin-form-actions">
                                <button class="admin-btn admin-btn--primary" type="submit">Simpan Mode Bisnis</button>
                            </div>
                        </form>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="business-labels" hidden>
                        <form method="post" class="admin-card admin-editor">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="save_business">
                            <input type="hidden" name="business_mode" value="<?= esc($currentMode); ?>">
                            <input type="hidden" name="layout_mode" value="<?= esc((string)($settings['layout_mode'] ?? 'multi_page')); ?>">
                            <input type="hidden" name="schema_profile" value="<?= esc((string)($settings['schema_profile'] ?? 'LocalBusiness')); ?>">
                            <input type="hidden" name="notes" value="<?= esc((string)($settings['notes'] ?? '')); ?>">
                            <div class="admin-form-head">
                                <span class="admin-badge">Custom Label</span>
                                <h2>Ubah Istilah Sesuai Niche</h2>
                                <p>Contoh: Produk bisa menjadi Karya, Program, Paket, Solusi, Menu, atau Penawaran. Ini membuat template terasa native untuk banyak bisnis.</p>
                            </div>

                            <div class="admin-form-grid">
                                <?php foreach (['catalog' => 'Nama menu katalog', 'product' => 'Sebutan item produk/jasa', 'service' => 'Sebutan layanan/program', 'portfolio' => 'Sebutan portfolio/showcase', 'checkout' => 'Sebutan checkout/aksi beli', 'lead' => 'Sebutan prospek/lead', 'article' => 'Sebutan artikel/konten', 'primary_cta' => 'Tombol CTA utama'] as $key => $hint): ?>
                                    <label class="admin-field">
                                        <span><?= esc($hint); ?></span>
                                        <input name="label_<?= esc($key); ?>" value="<?= esc((string)($labels[$key] ?? '')); ?>" maxlength="90">
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="admin-card admin-nested-card" style="margin-top:18px">
                                <h3>Visibilitas Modul Publik</h3>
                                <p class="admin-help-text">Matikan modul yang tidak dipakai agar website portfolio/landing page tetap ringan, tapi data dan fiturnya tidak hilang.</p>
                                <div class="admin-check-grid">
                                    <?php foreach (['catalog' => 'Katalog / Produk', 'services' => 'Layanan', 'portfolio' => 'Portfolio / Showcase', 'articles' => 'Artikel SEO', 'checkout' => 'Checkout / Order', 'lead_forms' => 'Form Lead'] as $key => $label): ?>
                                        <label class="admin-check"><input type="hidden" name="visibility_<?= esc($key); ?>" value="0"><input type="checkbox" name="visibility_<?= esc($key); ?>" value="1" <?= !empty($visibility[$key]) ? 'checked' : ''; ?>> <?= esc($label); ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="admin-form-actions">
                                <button class="admin-btn admin-btn--primary" type="submit">Simpan Label & Visibilitas</button>
                            </div>
                        </form>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="business-categories" hidden>
                        <form method="post" class="admin-card admin-editor">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="save_categories">
                            <div class="admin-form-head">
                                <span class="admin-badge">Flexible Taxonomy</span>
                                <h2>Kategori Sesuai Bisnis Admin</h2>
                                <p>Admin bisa bikin kategori sendiri. Kosongkan nama kategori untuk menghapus baris saat disimpan. Slug dibuat otomatis dan aman dari duplikat.</p>
                            </div>

                            <?php foreach ($categoryDomains as $domain => $meta): ?>
                                <?php $rows = business_category_rows($domain, false); ?>
                                <div class="admin-card admin-nested-card" style="margin-bottom:18px">
                                    <div class="admin-form-head admin-form-head--split">
                                        <div>
                                            <h3><?= esc((string)$meta['title']); ?></h3>
                                            <p><?= esc((string)$meta['hint']); ?></p>
                                        </div>
                                        <span class="admin-badge"><?= count(business_category_labels($domain, true)); ?> aktif</span>
                                    </div>
                                    <div class="admin-category-editor">
                                        <?php for ($i = 0; $i < max(count($rows) + 2, 4); $i++): ?>
                                            <?php $row = $rows[$i] ?? ['label' => '', 'description' => '', 'enabled' => true]; ?>
                                            <div class="admin-form-grid admin-category-editor-row">
                                                <label class="admin-field">
                                                    <span>Nama Kategori</span>
                                                    <input name="<?= esc($domain); ?>_label[]" value="<?= esc((string)($row['label'] ?? '')); ?>" maxlength="80" placeholder="Contoh: Paket Premium">
                                                </label>
                                                <label class="admin-field">
                                                    <span>Deskripsi Singkat</span>
                                                    <input name="<?= esc($domain); ?>_description[]" value="<?= esc((string)($row['description'] ?? '')); ?>" maxlength="180" placeholder="Dipakai sebagai bantuan SEO/filter.">
                                                </label>
                                                <label class="admin-check"><input type="checkbox" name="<?= esc($domain); ?>_enabled[<?= (int)$i; ?>]" value="1" <?= !empty($row['enabled']) ? 'checked' : ''; ?>> Aktif</label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    <label class="admin-field admin-field--wide">
                                        <span>Tambah Banyak Kategori Sekaligus</span>
                                        <textarea name="<?= esc($domain); ?>_bulk" rows="3" placeholder="Satu kategori per baris. Contoh:&#10;Konsultasi&#10;Paket Premium&#10;E-book"></textarea>
                                    </label>
                                </div>
                            <?php endforeach; ?>

                            <div class="admin-form-actions">
                                <button class="admin-btn admin-btn--primary" type="submit">Simpan Kategori Fleksibel</button>
                            </div>
                        </form>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="business-guide" hidden>
                        <div class="admin-card admin-editor">
                            <div class="admin-form-head">
                                <span class="admin-badge">Growth Direction</span>
                                <h2>Cara Memakai Template Agar Bisnis Bisa Scale</h2>
                                <p>Template ini bukan cuma halaman online. Arahnya adalah mesin pertumbuhan: konten SEO, katalog jelas, form lead, checkout, follow-up, tracking, dan insight.</p>
                            </div>
                            <div class="admin-page-helper-grid">
                                <div class="admin-page-helper-card"><strong>1. Tentukan Mode</strong><span>Pilih apakah website dipakai untuk produk, jasa, personal brand, portfolio, company profile, digital course, atau hybrid.</span></div>
                                <div class="admin-page-helper-card"><strong>2. Rapikan Kategori</strong><span>Buat kategori sesuai cara customer mencari solusi, bukan hanya sesuai stok internal.</span></div>
                                <div class="admin-page-helper-card"><strong>3. Hubungkan ke Katalog/Form</strong><span>Gunakan kategori ini saat membuat produk, jasa, artikel, landing page, dan form lead.</span></div>
                                <div class="admin-page-helper-card"><strong>4. Optimasi SEO & CTA</strong><span>Setiap kategori harus punya konten, internal link, CTA WhatsApp/form, dan tracking lead.</span></div>
                                <div class="admin-page-helper-card"><strong>5. Baca Insight</strong><span>Pakai dashboard lead, analytics, landing page analytics, dan laporan untuk melihat mana yang menghasilkan prospek/order.</span></div>
                                <div class="admin-page-helper-card"><strong>6. Scale Bertahap</strong><span>Naikkan dari katalog → landing page → checkout → invoice → follow-up → campaign → insight bisnis.</span></div>
                            </div>

                            <form method="post" onsubmit="return confirm('Reset mode bisnis dan kategori ke bawaan template? Data produk, artikel, order, dan form tidak dihapus.');" style="margin-top:18px">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="reset">
                                <button class="admin-btn admin-btn--soft" type="submit">Reset Pengaturan Mode & Kategori</button>
                            </form>
                        </div>
                    </section>
                </div>

                <aside class="admin-navigation-side">
                    <div class="admin-card admin-nested-card">
                        <span class="admin-badge">Aman untuk Data Bisnis</span>
                        <h3>Pengaturan Fleksibel</h3>
                        <p class="admin-help-text">Produk, artikel, checkout, invoice, form, WhatsApp, SEO landing, dan analytics tetap berjalan. Pengaturan ini hanya membantu menyesuaikan label, kategori, dan positioning sesuai bisnis.</p>
                    </div>
                    <div class="admin-card admin-nested-card">
                        <h3>Shortcut</h3>
                        <div class="admin-stack-actions">
                            <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= esc(url('admin/homepage')); ?>">Atur Homepage</a>
                            <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= esc(url('admin/navigation')); ?>">Atur Menu & Footer</a>
                            <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= esc(url('admin/produk')); ?>">Kelola <?= esc(business_label('catalog', 'Katalog')); ?></a>
                            <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= esc(url('admin/artikel')); ?>">Kelola <?= esc(business_label('article', 'Artikel')); ?></a>
                        </div>
                    </div>
                    <div class="admin-card admin-nested-card">
                        <h3>Preview Label Aktif</h3>
                        <ul class="admin-checklist">
                            <li><?= esc(business_label('catalog', 'Katalog')); ?></li>
                            <li><?= esc(business_label('product', 'Produk')); ?></li>
                            <li><?= esc(business_label('service', 'Layanan')); ?></li>
                            <li><?= esc(business_label('portfolio', 'Portfolio')); ?></li>
                            <li><?= esc(business_label('checkout', 'Checkout')); ?></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
