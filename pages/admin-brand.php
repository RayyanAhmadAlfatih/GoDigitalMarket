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

        if ((string)($_POST['form_action'] ?? '') === 'reset') {
            theme_reset_settings();
            redirect_302('admin/brand?message=' . rawurlencode('Pengaturan brand sudah dikembalikan ke bawaan template.'));
        }

        theme_save_settings($_POST);
        redirect_302('admin/brand?message=' . rawurlencode('Pengaturan brand dan warna berhasil disimpan.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = theme_settings();
$presets = theme_presets();
$colorKeys = theme_color_keys();
$currentPreset = (string)($settings['theme_preset'] ?? 'biru-profesional');
$onboardingMiniReport = function_exists('umkm_onboarding_report') ? umkm_onboarding_report() : null;

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Brand & Warna - ' . SITE_NAME,
    'description' => 'Atur nama bisnis, logo, kontak, warna website, dan tampilan dasar dashboard.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-brand-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Pengaturan Brand</div>
                <h1>Brand & Warna Website</h1>
                <p>Atur identitas bisnis, logo, kontak, dan warna website agar tampil sesuai karakter brand.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (is_array($onboardingMiniReport)): ?>
                <?php $miniTask = (array)($onboardingMiniReport['next_todo'] ?? $onboardingMiniReport['today_task'] ?? []); ?>
                <div class="admin-onboarding-mini">
                    <div class="admin-onboarding-mini__score"><?= (int)($onboardingMiniReport['score'] ?? 0); ?>%</div>
                    <div>
                        <span class="admin-badge">Panduan Hari Ini</span>
                        <h3><?= esc((string)($miniTask['title'] ?? 'Lanjut setup website')); ?></h3>
                        <p><?= esc((string)($miniTask['summary'] ?? 'Ikuti panduan ringan agar website lebih cepat siap dipakai.')); ?></p>
                    </div>
                    <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/onboarding-assistant')); ?>">Buka Panduan</a>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="admin-brand-layout" id="brandThemeForm">
                <?= csrf_field(); ?>

                <div class="admin-brand-main">
                    <div class="admin-page-subtabs admin-page-subtabs--6" role="tablist" aria-label="Bagian Brand & Warna">
                        <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" aria-controls="brand-tab-identity" data-admin-page-tab="brand-identity"><span>1. Identitas Bisnis</span><small>Nama, tagline, deskripsi</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" aria-controls="brand-tab-contact" data-admin-page-tab="brand-contact"><span>2. Kontak Bisnis</span><small>WA, email, alamat</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" aria-controls="brand-tab-media" data-admin-page-tab="brand-media"><span>3. Logo & Media</span><small>Logo, favicon, gambar share</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" aria-controls="brand-tab-social" data-admin-page-tab="brand-social"><span>4. Sosial Media</span><small>Link sosial brand</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" aria-controls="brand-tab-colors" data-admin-page-tab="brand-colors"><span>5. Warna Website</span><small>Preset & warna custom</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" aria-controls="brand-tab-login" data-admin-page-tab="brand-login"><span>6. Login & Efek</span><small>Branding login + animasi</small></button>
                    </div>

                    <div class="admin-page-mobile-jump">
                        <label class="admin-field">
                            <span>Pilih bagian pengaturan</span>
                            <select data-admin-page-tab-select aria-label="Pilih bagian Brand & Warna">
                                <option value="brand-identity">1. Identitas Bisnis</option>
                                <option value="brand-contact">2. Kontak Bisnis</option>
                                <option value="brand-media">3. Logo & Media</option>
                                <option value="brand-social">4. Sosial Media</option>
                                <option value="brand-colors">5. Warna Website</option>
                                <option value="brand-login">6. Login & Efek</option>
                            </select>
                        </label>
                    </div>

                    <section class="admin-page-tab-panel is-active" id="brand-tab-identity" role="tabpanel" data-admin-page-tab-panel="brand-identity">
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Identitas Bisnis</span>
                            <h2>Profil Brand</h2>
                            <p>Isi nama dan informasi utama yang akan dipakai di header, footer, SEO, dan WhatsApp.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field">
                                <span>Nama Bisnis</span>
                                <input type="text" name="business_name" value="<?= esc((string)($settings['business_name'] ?? '')); ?>" maxlength="90" required>
                            </label>

                            <label class="admin-field">
                                <span>Tagline Singkat</span>
                                <input type="text" name="tagline" value="<?= esc((string)($settings['tagline'] ?? '')); ?>" maxlength="180" placeholder="Contoh: Solusi kebutuhan harian keluarga Indonesia">
                            </label>

                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Bisnis</span>
                                <textarea name="description" rows="3" maxlength="240" placeholder="Jelaskan singkat bisnis dan penawaran utama."><?= esc((string)($settings['description'] ?? '')); ?></textarea>
                                <small>Deskripsi ini juga dipakai sebagai fallback SEO website.</small>
                            </label>

                            <label class="admin-field admin-field--wide">
                                <span>Kata Kunci Utama</span>
                                <input type="text" name="keywords" value="<?= esc((string)($settings['keywords'] ?? '')); ?>" maxlength="240" placeholder="Contoh: toko online, jasa desain, kuliner rumahan">
                            </label>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" id="brand-tab-contact" role="tabpanel" data-admin-page-tab-panel="brand-contact" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Kontak</span>
                            <h2>Informasi Customer</h2>
                            <p>Kontak ini dipakai untuk tombol WhatsApp, footer, dan informasi bisnis.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field">
                                <span>Nomor WhatsApp</span>
                                <input type="text" name="whatsapp" value="<?= esc((string)($settings['whatsapp'] ?? '')); ?>" inputmode="numeric" placeholder="628xxxxxxxxxx">
                                <small>Gunakan format 62 tanpa tanda plus.</small>
                            </label>

                            <label class="admin-field">
                                <span>Nomor Telepon</span>
                                <input type="text" name="phone" value="<?= esc((string)($settings['phone'] ?? '')); ?>" placeholder="628xxxxxxxxxx">
                            </label>

                            <label class="admin-field">
                                <span>Email</span>
                                <input type="email" name="email" value="<?= esc((string)($settings['email'] ?? '')); ?>" placeholder="admin@example.com">
                            </label>

                            <label class="admin-field">
                                <span>Alamat / Area Layanan</span>
                                <input type="text" name="address" value="<?= esc((string)($settings['address'] ?? '')); ?>" maxlength="220" placeholder="Contoh: Jakarta, Indonesia">
                            </label>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" id="brand-tab-media" role="tabpanel" data-admin-page-tab-panel="brand-media" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Logo & Media</span>
                            <h2>Logo, Favicon, dan Gambar Share</h2>
                            <p>Upload logo brand, ikon browser, dan gambar default saat link website dibagikan.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field">
                                <span>Upload Logo</span>
                                <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp">
                                <small>Kosongkan jika tidak ingin mengganti logo.</small>
                            </label>

                            <label class="admin-field">
                                <span>URL / Path Logo</span>
                                <input type="text" name="logo_url" value="<?= esc((string)($settings['logo_url'] ?? '')); ?>" placeholder="images/logo.png">
                            </label>

                            <label class="admin-field">
                                <span>Upload Favicon</span>
                                <input type="file" name="favicon_file" accept="image/png,image/jpeg,image/webp">
                            </label>

                            <label class="admin-field">
                                <span>URL / Path Favicon</span>
                                <input type="text" name="favicon_url" value="<?= esc((string)($settings['favicon_url'] ?? '')); ?>" placeholder="images/favicon.png">
                            </label>

                            <label class="admin-field">
                                <span>Upload Gambar Share</span>
                                <input type="file" name="og_image_file" accept="image/png,image/jpeg,image/webp">
                            </label>

                            <label class="admin-field">
                                <span>URL / Path Gambar Share</span>
                                <input type="text" name="og_image_url" value="<?= esc((string)($settings['og_image_url'] ?? '')); ?>" placeholder="images/og-default.jpg">
                            </label>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" id="brand-tab-social" role="tabpanel" data-admin-page-tab-panel="brand-social" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Sosial Media</span>
                            <h2>Link Sosial Media</h2>
                            <p>Isi yang digunakan saja. Link kosong tidak akan tampil di footer.</p>
                        </div>

                        <div class="admin-form-grid">
                            <?php foreach ([
                                'facebook_url' => 'Facebook',
                                'instagram_url' => 'Instagram',
                                'youtube_url' => 'YouTube',
                                'tiktok_url' => 'TikTok',
                                'linkedin_url' => 'LinkedIn',
                            ] as $field => $label): ?>
                                <label class="admin-field <?= in_array($field, ['tiktok_url', 'linkedin_url'], true) ? '' : ''; ?>">
                                    <span><?= esc($label); ?></span>
                                    <input type="url" name="<?= esc($field); ?>" value="<?= esc((string)($settings[$field] ?? '')); ?>" placeholder="https://...">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" id="brand-tab-colors" role="tabpanel" data-admin-page-tab-panel="brand-colors" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Warna Website</span>
                            <h2>Pilih Preset atau Warna Custom</h2>
                            <p>Pakai preset untuk mulai cepat, lalu sesuaikan warna jika brand punya identitas khusus.</p>
                        </div>

                        <div class="theme-preset-grid">
                            <?php foreach ($presets as $presetKey => $preset): ?>
                                <label class="theme-preset-card <?= $currentPreset === $presetKey ? 'is-active' : ''; ?>" data-preset-card>
                                    <input type="radio" name="theme_preset" value="<?= esc($presetKey); ?>" <?= $currentPreset === $presetKey ? 'checked' : ''; ?> data-preset='<?= esc(json_encode($preset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?>'>
                                    <strong><?= esc((string)$preset['label']); ?></strong>
                                    <small><?= esc((string)$preset['description']); ?></small>
                                    <span class="theme-preset-swatches">
                                        <i style="background:<?= esc((string)$preset['primary_color']); ?>"></i>
                                        <i style="background:<?= esc((string)$preset['secondary_color']); ?>"></i>
                                        <i style="background:<?= esc((string)$preset['button_color']); ?>"></i>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="admin-form-grid theme-color-grid">
                            <?php foreach ($colorKeys as $field => $label): ?>
                                <label class="admin-field theme-color-field">
                                    <span><?= esc($label); ?></span>
                                    <input type="color" name="<?= esc($field); ?>" value="<?= esc((string)($settings[$field] ?? '#000000')); ?>" data-color-field="<?= esc($field); ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" id="brand-tab-login" role="tabpanel" data-admin-page-tab-panel="brand-login" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Login Branding</span>
                            <h2>Custom Halaman Login & Efek Ringan</h2>
                            <p>Atur tampilan <code>/admin/login</code> agar terasa sesuai brand bisnis. Efek dibuat ringan agar tetap cepat di shared hosting.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field admin-field--wide admin-check-card">
                                <span><input type="checkbox" name="login_branding_enabled" value="1" <?= !empty($settings['login_branding_enabled']) ? 'checked' : ''; ?>> Aktifkan branding halaman login</span>
                                <small>Jika aktif, halaman login memakai logo, nama brand, headline, dan catatan brand.</small>
                            </label>

                            <label class="admin-field">
                                <span>Layout Login</span>
                                <select name="login_layout">
                                    <?php foreach (['split' => 'Split Brand Panel', 'center' => 'Simple Center', 'card' => 'Card Premium'] as $value => $label): ?>
                                        <option value="<?= esc($value); ?>" <?= (string)($settings['login_layout'] ?? 'split') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="admin-field">
                                <span>Background Login</span>
                                <select name="login_background_style">
                                    <?php foreach (['soft-gradient' => 'Soft Gradient', 'brand-gradient' => 'Brand Gradient', 'clean' => 'Clean Putih', 'image' => 'Gambar Background'] as $value => $label): ?>
                                        <option value="<?= esc($value); ?>" <?= (string)($settings['login_background_style'] ?? 'soft-gradient') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="admin-field">
                                <span>Upload Logo Login Opsional</span>
                                <input type="file" name="login_logo_file" accept="image/png,image/jpeg,image/webp">
                                <small>Kosongkan agar memakai logo utama website.</small>
                            </label>

                            <label class="admin-field">
                                <span>URL / Path Logo Login</span>
                                <input type="text" name="login_logo_url" value="<?= esc((string)($settings['login_logo_url'] ?? '')); ?>" placeholder="Kosongkan = pakai logo utama">
                            </label>

                            <label class="admin-field">
                                <span>Upload Background Login Opsional</span>
                                <input type="file" name="login_background_file" accept="image/png,image/jpeg,image/webp">
                                <small>Dipakai jika mode background memilih Gambar Background.</small>
                            </label>

                            <label class="admin-field">
                                <span>URL / Path Background Login</span>
                                <input type="text" name="login_background_image" value="<?= esc((string)($settings['login_background_image'] ?? '')); ?>" placeholder="assets/uploads/brand/background.webp">
                            </label>

                            <label class="admin-field">
                                <span>Badge Kecil</span>
                                <input type="text" name="login_badge" value="<?= esc((string)($settings['login_badge'] ?? 'Dashboard Admin')); ?>" maxlength="80">
                            </label>

                            <label class="admin-field">
                                <span>Judul Login</span>
                                <input type="text" name="login_title" value="<?= esc((string)($settings['login_title'] ?? 'Masuk ke Dashboard')); ?>" maxlength="120">
                            </label>

                            <label class="admin-field admin-field--wide">
                                <span>Tagline Login</span>
                                <input type="text" name="login_tagline" value="<?= esc((string)($settings['login_tagline'] ?? 'Kelola website bisnis dari satu tempat.')); ?>" maxlength="180">
                            </label>

                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Login</span>
                                <textarea name="login_description" rows="3" maxlength="260"><?= esc((string)($settings['login_description'] ?? 'Atur katalog, landing page, checkout, form prospek, artikel SEO, dan insight bisnis tanpa ribet.')); ?></textarea>
                            </label>

                            <label class="admin-field">
                                <span>Teks Tombol Login</span>
                                <input type="text" name="login_button_text" value="<?= esc((string)($settings['login_button_text'] ?? 'Masuk Dashboard')); ?>" maxlength="80">
                            </label>

                            <label class="admin-field">
                                <span>Catatan Bawah Form</span>
                                <input type="text" name="login_footer_note" value="<?= esc((string)($settings['login_footer_note'] ?? 'Dashboard resmi {business_name}')); ?>" maxlength="160">
                                <small>Bisa pakai token <code>{business_name}</code>.</small>
                            </label>
                        </div>

                        <div class="admin-form-head admin-form-head--sub">
                            <span class="admin-badge">Motion Polish</span>
                            <h3>Efek ringan untuk admin dan publik</h3>
                            <p>Efek berupa hover, fade ringan, dan reveal saat scroll. Otomatis mengikuti preferensi reduced motion browser.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field admin-check-card"><span><input type="checkbox" name="motion_effects_enabled" value="1" <?= !empty($settings['motion_effects_enabled']) ? 'checked' : ''; ?>> Aktifkan efek ringan global</span><small>Matikan jika ingin tampilan sangat statis.</small></label>
                            <label class="admin-field admin-check-card"><span><input type="checkbox" name="motion_public_enabled" value="1" <?= !empty($settings['motion_public_enabled']) ? 'checked' : ''; ?>> Frontend publik</span><small>Homepage, katalog, artikel, portfolio, checkout, dan halaman umum.</small></label>
                            <label class="admin-field admin-check-card"><span><input type="checkbox" name="motion_admin_enabled" value="1" <?= !empty($settings['motion_admin_enabled']) ? 'checked' : ''; ?>> Area admin</span><small>Card, tab, tombol, alert, dan dashboard admin.</small></label>
                            <label class="admin-field admin-check-card"><span><input type="checkbox" name="motion_landing_enabled" value="1" <?= !empty($settings['motion_landing_enabled']) ? 'checked' : ''; ?>> Landing page builder live</span><small>Landing page publik dari builder dan preview ringan.</small></label>
                            <label class="admin-field">
                                <span>Intensitas Efek</span>
                                <select name="motion_intensity">
                                    <?php foreach (['soft' => 'Soft / ringan', 'medium' => 'Medium / sedikit lebih terasa'] as $value => $label): ?>
                                        <option value="<?= esc($value); ?>" <?= (string)($settings['motion_intensity'] ?? 'soft') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="theme-login-preview">
                            <div class="theme-login-preview__panel">
                                <span><?= esc((string)($settings['login_badge'] ?? 'Dashboard Admin')); ?></span>
                                <strong><?= esc((string)($settings['login_title'] ?? 'Masuk ke Dashboard')); ?></strong>
                                <small><?= esc((string)($settings['login_tagline'] ?? SITE_TAGLINE)); ?></small>
                            </div>
                            <div class="theme-login-preview__card">
                                <img src="<?= esc(function_exists('theme_login_logo_url') ? theme_login_logo_url() : (function_exists('theme_logo_url') ? theme_logo_url() : asset('images/logo.png'))); ?>" alt="Preview logo login" onerror="this.style.display='none'">
                                <b>Password Admin</b>
                                <i></i>
                                <button type="button"><?= esc((string)($settings['login_button_text'] ?? 'Masuk Dashboard')); ?></button>
                            </div>
                        </div>
                    </div>
                    </section>
                </div>

                <aside class="admin-brand-side">
                    <div class="admin-card admin-editor admin-brand-sticky">
                        <div class="admin-form-head">
                            <span class="admin-badge">Preview</span>
                            <h2>Tampilan Brand</h2>
                            <p>Ringkasan tampilan yang akan dipakai website.</p>
                        </div>

                        <div class="theme-brand-preview" id="themeBrandPreview">
                            <div class="theme-brand-preview__bar"></div>
                            <div class="theme-brand-preview__body">
                                <img src="<?= esc(function_exists('theme_logo_url') ? theme_logo_url() : asset('images/logo.png')); ?>" alt="Logo brand" onerror="this.style.display='none'">
                                <strong><?= esc((string)($settings['business_name'] ?? SITE_NAME)); ?></strong>
                                <span><?= esc((string)($settings['tagline'] ?? SITE_TAGLINE)); ?></span>
                                <button type="button">Contoh Tombol CTA</button>
                            </div>
                        </div>

                        <div class="admin-help admin-help--soft">
                            <strong>Catatan aman</strong>
                            <p>Perubahan warna langsung dipakai oleh header, tombol, footer, form, dan dashboard setelah disimpan.</p>
                        </div>

                        <div class="admin-footer-actions admin-brand-actions">
                            <button class="admin-btn admin-btn--primary admin-btn--full" type="submit" name="form_action" value="save">Simpan Pengaturan</button>
                            <button class="admin-btn admin-btn--soft admin-btn--full" type="submit" name="form_action" value="reset" onclick="return confirm('Kembalikan pengaturan brand ke bawaan template?')">Reset Bawaan</button>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </section>
</main>

<script>
(function(){
    const form = document.getElementById('brandThemeForm');
    if (!form) return;

    const cards = form.querySelectorAll('[data-preset-card]');
    const preview = document.getElementById('themeBrandPreview');

    function setColor(name, value) {
        const input = form.querySelector('[data-color-field="' + name + '"]');
        if (input && /^#[0-9a-fA-F]{6}$/.test(value || '')) {
            input.value = value;
        }
    }

    function updatePreview() {
        if (!preview) return;
        const header = form.querySelector('[data-color-field="header_color"]')?.value || '#1d4ed8';
        const dark = form.querySelector('[data-color-field="primary_dark_color"]')?.value || '#1e3a8a';
        const button = form.querySelector('[data-color-field="button_color"]')?.value || '#2563eb';
        const buttonText = form.querySelector('[data-color-field="button_text_color"]')?.value || '#ffffff';
        preview.style.background = 'linear-gradient(135deg,' + header + ',' + dark + ')';
        const buttonEl = preview.querySelector('button');
        if (buttonEl) {
            buttonEl.style.background = button;
            buttonEl.style.color = buttonText;
        }
    }

    cards.forEach(function(card){
        const radio = card.querySelector('input[type="radio"]');
        if (!radio) return;

        radio.addEventListener('change', function(){
            cards.forEach(function(item){ item.classList.remove('is-active'); });
            card.classList.add('is-active');

            try {
                const preset = JSON.parse(radio.dataset.preset || '{}');
                Object.keys(preset).forEach(function(key){ setColor(key, preset[key]); });
            } catch (error) {}

            updatePreview();
        });
    });

    form.querySelectorAll('[data-color-field]').forEach(function(input){
        input.addEventListener('input', updatePreview);
    });


    const pageTabs = Array.from(document.querySelectorAll('[data-admin-page-tab]'));
    const pagePanels = Array.from(document.querySelectorAll('[data-admin-page-tab-panel]'));
    const pageSelect = document.querySelector('[data-admin-page-tab-select]');

    function activatePageTab(target) {
        if (!target) return;
        pageTabs.forEach(function(tab){
            const active = tab.dataset.adminPageTab === target;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        pagePanels.forEach(function(panel){
            const active = panel.dataset.adminPageTabPanel === target;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
        if (pageSelect && pageSelect.value !== target) {
            pageSelect.value = target;
        }
    }

    pageTabs.forEach(function(tab){
        tab.addEventListener('click', function(){
            activatePageTab(tab.dataset.adminPageTab || '');
        });
    });

    if (pageSelect) {
        pageSelect.addEventListener('change', function(){
            activatePageTab(pageSelect.value);
        });
    }

    updatePreview();
})();
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
