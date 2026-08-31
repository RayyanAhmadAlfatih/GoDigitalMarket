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
            homepage_reset_settings();
            redirect_302('admin/homepage?message=' . rawurlencode('Pengaturan beranda sudah dikembalikan ke bawaan template.'));
        }

        homepage_save_settings(homepage_settings_from_post($_POST));
        redirect_302('admin/homepage?message=' . rawurlencode('Pengaturan beranda berhasil disimpan.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = homepage_settings();
$modes = homepage_modes();
$currentMode = (string)($settings['mode'] ?? 'catalog');
$homepageSource = (string)($settings['source'] ?? 'template');
$sourceOptions = function_exists('homepage_source_options') ? homepage_source_options() : [];
$publishedLandingPages = function_exists('homepage_published_landing_pages') ? homepage_published_landing_pages() : [];
$selectedLandingPage = function_exists('homepage_selected_landing_page') ? homepage_selected_landing_page($settings) : null;
$selectedLandingSlug = (string)($settings['landing_page_slug'] ?? '');
$hero = (array)($settings['hero'] ?? []);
$sections = (array)($settings['sections'] ?? []);
$trustItems = (array)($settings['trust_items'] ?? []);
for ($i = count($trustItems); $i < 4; $i++) {
    $trustItems[] = ['title' => '', 'text' => ''];
}
$sectionDefinitions = function_exists('homepage_section_definitions') ? homepage_section_definitions() : [];
$sectionOrder = function_exists('homepage_ordered_sections') ? homepage_ordered_sections($settings) : array_keys($sections);
$orderedActiveSections = array_values(array_filter($sectionOrder, static fn(string $key): bool => !empty($sections[$key])));

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Atur Beranda - ' . SITE_NAME,
    'description' => 'Pilih mode homepage dan atur konten utama beranda website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-homepage-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Beranda Website</div>
                <h1>Atur Tampilan Homepage</h1>
                <p>Pilih gaya homepage sesuai kebutuhan bisnis, lalu sesuaikan headline, tombol, dan section yang tampil.</p>
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

            <form method="post" class="admin-navigation-layout admin-homepage-layout">
                <?= csrf_field(); ?>

                <div class="admin-navigation-main" data-admin-page-tab-scope>
                    <div class="admin-page-subtabs admin-page-subtabs--7" role="tablist" aria-label="Bagian Atur Beranda">
                        <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="home-source"><span>1. Sumber Beranda</span><small>Template atau LP</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="home-mode"><span>2. Mode Homepage</span><small>Tujuan beranda</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="home-hero"><span>3. Hero</span><small>Headline utama</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="home-section"><span>4. Section</span><small>Bagian tampil</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="home-order"><span>5. Urutan</span><small>Susun homepage</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="home-benefits"><span>6. Benefit Singkat</span><small>Poin trust</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="home-copy"><span>7. Teks Section</span><small>Konten pendukung</small></button>
                    </div>
                    <div class="admin-page-mobile-jump">
                        <label class="admin-field"><span>Pilih bagian</span><select data-admin-page-tab-select aria-label="Pilih bagian Atur Beranda"><option value="home-source">1. Sumber Beranda</option><option value="home-mode">2. Mode Homepage</option><option value="home-hero">3. Hero</option><option value="home-section">4. Section</option><option value="home-order">5. Urutan Section</option><option value="home-benefits">6. Benefit Singkat</option><option value="home-copy">7. Teks Section</option></select></label>
                    </div>

                    <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="home-source">
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Sumber Beranda</span>
                            <h2>Pilih Isi yang Tampil di Homepage</h2>
                            <p>Gunakan mode bawaan template, atau jadikan landing page yang sudah dipublish sebagai halaman utama website.</p>
                        </div>

                        <div class="homepage-source-grid">
                            <label class="homepage-mode-card <?= $homepageSource === 'template' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="source" value="template" <?= $homepageSource === 'template' ? 'checked' : ''; ?>>
                                <strong><?= esc((string)($sourceOptions['template']['label'] ?? 'Mode Beranda Template')); ?></strong>
                                <span><?= esc((string)($sourceOptions['template']['description'] ?? 'Beranda memakai pengaturan dari halaman ini.')); ?></span>
                            </label>
                            <label class="homepage-mode-card <?= $homepageSource === 'landing_page' ? 'is-selected' : ''; ?> <?= !$publishedLandingPages ? 'is-disabled' : ''; ?>">
                                <input type="radio" name="source" value="landing_page" <?= $homepageSource === 'landing_page' ? 'checked' : ''; ?> <?= !$publishedLandingPages ? 'disabled' : ''; ?>>
                                <strong><?= esc((string)($sourceOptions['landing_page']['label'] ?? 'Landing Page Builder')); ?></strong>
                                <span><?= esc((string)($sourceOptions['landing_page']['description'] ?? 'Beranda memakai landing page publish.')); ?></span>
                            </label>
                        </div>

                        <div class="admin-form-grid admin-homepage-landing-selector">
                            <label class="admin-field admin-field--wide">
                                <span>Pilih Landing Page untuk Homepage</span>
                                <select name="landing_page_slug" <?= !$publishedLandingPages ? 'disabled' : ''; ?>>
                                    <option value="">Pilih landing page yang sudah publish</option>
                                    <?php foreach ($publishedLandingPages as $landingPage): ?>
                                        <?php $landingSlug = (string)($landingPage['slug'] ?? ''); ?>
                                        <option value="<?= esc($landingSlug); ?>" <?= $selectedLandingSlug === $landingSlug ? 'selected' : ''; ?>>
                                            <?= esc((string)($landingPage['title'] ?? $landingSlug)); ?> — /lp/<?= esc($landingSlug); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$publishedLandingPages): ?>
                                    <small>Belum ada landing page yang publish. Buka Landing Page Builder, publish halaman yang ingin dipakai, lalu kembali ke sini.</small>
                                <?php else: ?>
                                    <small>Jika dipilih sebagai homepage, URL utama website akan menampilkan landing page ini. URL asli /lp/slug tetap aktif.</small>
                                <?php endif; ?>
                            </label>
                            <?php if ($selectedLandingPage): ?>
                                <div class="admin-field admin-field--wide">
                                    <span>Landing Page Aktif</span>
                                    <div class="admin-preview-box">
                                        <strong><?= esc((string)($selectedLandingPage['title'] ?? 'Landing Page')); ?></strong>
                                        <span>Slug: /lp/<?= esc((string)($selectedLandingPage['slug'] ?? '')); ?> · Homepage: <?= esc(url('')); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="home-mode" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Mode Homepage</span>
                            <h2>Pilih Tujuan Beranda</h2>
                            <p>Mode ini dipakai saat sumber beranda memakai Mode Beranda Template. Jika memakai landing page sebagai homepage, bagian ini tetap tersimpan sebagai cadangan aman.</p>
                        </div>

                        <div class="homepage-mode-grid">
                            <?php foreach ($modes as $modeKey => $mode): ?>
                                <label class="homepage-mode-card <?= $currentMode === $modeKey ? 'is-selected' : ''; ?>">
                                    <input type="radio" name="mode" value="<?= esc((string)$modeKey); ?>" <?= $currentMode === $modeKey ? 'checked' : ''; ?>>
                                    <strong><?= esc((string)($mode['label'] ?? $modeKey)); ?></strong>
                                    <span><?= esc((string)($mode['description'] ?? '')); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="home-hero" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Hero</span>
                            <h2>Headline Utama</h2>
                            <p>Bagian pertama yang dilihat pengunjung. Buat kalimat yang jelas, singkat, dan sesuai brand.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field">
                                <span>Label Kecil</span>
                                <input type="text" name="hero_eyebrow" value="<?= esc((string)($hero['eyebrow'] ?? '')); ?>" maxlength="120" placeholder="Contoh: Website resmi bisnis kami">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Besar</span>
                                <input type="text" name="hero_title" value="<?= esc((string)($hero['title'] ?? '')); ?>" maxlength="140" placeholder="Judul utama beranda">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Singkat</span>
                                <textarea name="hero_description" rows="3" maxlength="320" placeholder="Jelaskan manfaat utama website atau bisnis Anda."><?= esc((string)($hero['description'] ?? '')); ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span>Label Tombol Utama</span>
                                <input type="text" name="hero_primary_label" value="<?= esc((string)($hero['primary_label'] ?? '')); ?>" maxlength="40" placeholder="Lihat Katalog">
                            </label>
                            <label class="admin-field">
                                <span>Link Tombol Utama</span>
                                <input type="text" name="hero_primary_url" value="<?= esc((string)($hero['primary_url'] ?? '')); ?>" placeholder="/katalog, /kontak, atau #form-konsultasi">
                            </label>
                            <label class="admin-field">
                                <span>Label Tombol Kedua</span>
                                <input type="text" name="hero_secondary_label" value="<?= esc((string)($hero['secondary_label'] ?? '')); ?>" maxlength="40" placeholder="Konsultasi">
                            </label>
                            <label class="admin-field">
                                <span>Link Tombol Kedua</span>
                                <input type="text" name="hero_secondary_url" value="<?= esc((string)($hero['secondary_url'] ?? '')); ?>" placeholder="/kontak atau link lainnya">
                            </label>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="home-section" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Section</span>
                            <h2>Bagian yang Ditampilkan</h2>
                            <p>Aktifkan bagian yang cocok dengan kebutuhan website. Bagian yang tidak dipakai bisa disembunyikan.</p>
                        </div>

                        <div class="admin-toggle-grid admin-toggle-grid--sections">
                            <?php foreach ($sectionDefinitions as $key => $definition): ?>
                                <label class="admin-check-card admin-check-card--with-help">
                                    <input type="checkbox" name="sections[<?= esc((string)$key); ?>]" value="1" <?= !empty($sections[$key]) ? 'checked' : ''; ?>>
                                    <span>
                                        <?= esc((string)($definition['label'] ?? $key)); ?>
                                        <small><?= esc((string)($definition['description'] ?? 'Atur tampil/sembunyi section ini.')); ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="home-order" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Smart Homepage Section Manager</span>
                            <h2>Susun Urutan Section Homepage</h2>
                            <p>Ubah angka urutan untuk mengatur alur homepage. Angka kecil tampil lebih dulu. Section yang disembunyikan tetap tersimpan dan bisa diaktifkan lagi kapan saja.</p>
                        </div>

                        <div class="homepage-section-order-list">
                            <?php foreach ($sectionOrder as $orderIndex => $sectionKey): ?>
                                <?php $definition = (array)($sectionDefinitions[$sectionKey] ?? ['label' => $sectionKey, 'description' => 'Section homepage']); ?>
                                <div class="homepage-section-order-row <?= !empty($sections[$sectionKey]) ? 'is-enabled' : 'is-hidden'; ?>">
                                    <div class="homepage-section-order-number"><?= (int)$orderIndex + 1; ?></div>
                                    <div class="homepage-section-order-copy">
                                        <strong><?= esc((string)($definition['label'] ?? $sectionKey)); ?></strong>
                                        <span><?= esc((string)($definition['description'] ?? 'Section homepage')); ?></span>
                                        <small><?= !empty($sections[$sectionKey]) ? 'Status: tampil di homepage' : 'Status: disembunyikan'; ?></small>
                                    </div>
                                    <label class="admin-field homepage-section-order-input">
                                        <span>Urutan</span>
                                        <input type="hidden" name="section_order_key[]" value="<?= esc((string)$sectionKey); ?>">
                                        <input type="number" name="section_order_rank[]" value="<?= esc((string)(($orderIndex + 1) * 10)); ?>" min="1" max="999">
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="admin-preview-box">
                            <strong>Tips cepat</strong>
                            <span>Untuk homepage jualan, gunakan alur Hero → Benefit → Katalog/Layanan → Trust/Testimoni/FAQ/CTA → Form. Untuk company profile, portfolio bisa dinaikkan sebelum form.</span>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="home-benefits" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Benefit Singkat</span>
                            <h2>Ringkasan di Bawah Hero</h2>
                            <p>Isi maksimal empat poin singkat. Bagian ini cocok untuk trust signal, keunggulan, atau kategori utama.</p>
                        </div>

                        <div class="homepage-trust-editor">
                            <?php foreach (array_slice($trustItems, 0, 4) as $index => $item): ?>
                                <div class="homepage-trust-row">
                                    <strong><?= (int)$index + 1; ?></strong>
                                    <label class="admin-field">
                                        <span>Judul</span>
                                        <input type="text" name="trust_title[<?= (int)$index; ?>]" value="<?= esc((string)($item['title'] ?? '')); ?>" maxlength="28" placeholder="Contoh: Cepat">
                                    </label>
                                    <label class="admin-field">
                                        <span>Keterangan</span>
                                        <input type="text" name="trust_text[<?= (int)$index; ?>]" value="<?= esc((string)($item['text'] ?? '')); ?>" maxlength="90" placeholder="Contoh: Respon cepat lewat WhatsApp">
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="home-copy" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Teks Section</span>
                            <h2>Konten Pendukung</h2>
                            <p>Sesuaikan judul dan deskripsi section agar lebih cocok dengan bisnis yang memakai template ini.</p>
                        </div>

                        <div class="admin-form-grid">
                            <?php $profileIntro = (array)($settings['profile_intro'] ?? []); ?>
                            <label class="admin-field">
                                <span>Label Pengantar</span>
                                <input type="text" name="profile_intro_eyebrow" value="<?= esc((string)($profileIntro['eyebrow'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Pengantar</span>
                                <input type="text" name="profile_intro_title" value="<?= esc((string)($profileIntro['title'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Pengantar</span>
                                <textarea name="profile_intro_description" rows="2" maxlength="260"><?= esc((string)($profileIntro['description'] ?? '')); ?></textarea>
                            </label>

                            <?php $catalogSection = (array)($settings['featured_catalog'] ?? []); ?>
                            <label class="admin-field">
                                <span>Label Katalog</span>
                                <input type="text" name="featured_catalog_eyebrow" value="<?= esc((string)($catalogSection['eyebrow'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Katalog</span>
                                <input type="text" name="featured_catalog_title" value="<?= esc((string)($catalogSection['title'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Katalog</span>
                                <textarea name="featured_catalog_description" rows="2" maxlength="260"><?= esc((string)($catalogSection['description'] ?? '')); ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span>Label Tombol Katalog</span>
                                <input type="text" name="featured_catalog_button_label" value="<?= esc((string)($catalogSection['button_label'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field">
                                <span>Link Tombol Katalog</span>
                                <input type="text" name="featured_catalog_button_url" value="<?= esc((string)($catalogSection['button_url'] ?? '')); ?>">
                            </label>
                            <label class="admin-field">
                                <span>Jumlah Item Katalog</span>
                                <input type="number" name="featured_catalog_limit" value="<?= esc((string)($catalogSection['limit'] ?? 6)); ?>" min="1" max="12">
                            </label>

                            <?php $servicesHighlight = (array)($settings['services_highlight'] ?? []); ?>
                            <label class="admin-field">
                                <span>Label Layanan</span>
                                <input type="text" name="services_highlight_eyebrow" value="<?= esc((string)($servicesHighlight['eyebrow'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Layanan</span>
                                <input type="text" name="services_highlight_title" value="<?= esc((string)($servicesHighlight['title'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Layanan</span>
                                <textarea name="services_highlight_description" rows="2" maxlength="260"><?= esc((string)($servicesHighlight['description'] ?? '')); ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span>Label Tombol Layanan</span>
                                <input type="text" name="services_highlight_button_label" value="<?= esc((string)($servicesHighlight['button_label'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field">
                                <span>Link Tombol Layanan</span>
                                <input type="text" name="services_highlight_button_url" value="<?= esc((string)($servicesHighlight['button_url'] ?? '/layanan')); ?>">
                            </label>
                            <label class="admin-field">
                                <span>Jumlah Item Layanan</span>
                                <input type="number" name="services_highlight_limit" value="<?= esc((string)($servicesHighlight['limit'] ?? 3)); ?>" min="1" max="12">
                            </label>

                            <?php $businessFit = (array)($settings['business_fit'] ?? []); ?>
                            <label class="admin-field">
                                <span>Label Jenis Bisnis</span>
                                <input type="text" name="business_fit_eyebrow" value="<?= esc((string)($businessFit['eyebrow'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Jenis Bisnis</span>
                                <input type="text" name="business_fit_title" value="<?= esc((string)($businessFit['title'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Jenis Bisnis</span>
                                <textarea name="business_fit_description" rows="2" maxlength="260"><?= esc((string)($businessFit['description'] ?? '')); ?></textarea>
                            </label>

                            <?php $articleSection = (array)($settings['latest_articles'] ?? []); ?>
                            <label class="admin-field">
                                <span>Label Artikel</span>
                                <input type="text" name="latest_articles_eyebrow" value="<?= esc((string)($articleSection['eyebrow'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Artikel</span>
                                <input type="text" name="latest_articles_title" value="<?= esc((string)($articleSection['title'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Artikel</span>
                                <textarea name="latest_articles_description" rows="2" maxlength="260"><?= esc((string)($articleSection['description'] ?? '')); ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span>Jumlah Artikel</span>
                                <input type="number" name="latest_articles_limit" value="<?= esc((string)($articleSection['limit'] ?? 3)); ?>" min="1" max="12">
                            </label>

                            <?php $portfolioHighlight = (array)($settings['portfolio_highlight'] ?? []); ?>
                            <label class="admin-field">
                                <span>Label Portfolio</span>
                                <input type="text" name="portfolio_highlight_eyebrow" value="<?= esc((string)($portfolioHighlight['eyebrow'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Portfolio</span>
                                <input type="text" name="portfolio_highlight_title" value="<?= esc((string)($portfolioHighlight['title'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Portfolio</span>
                                <textarea name="portfolio_highlight_description" rows="2" maxlength="260"><?= esc((string)($portfolioHighlight['description'] ?? '')); ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span>Label Tombol Portfolio</span>
                                <input type="text" name="portfolio_highlight_button_label" value="<?= esc((string)($portfolioHighlight['button_label'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field">
                                <span>Link Tombol Portfolio</span>
                                <input type="text" name="portfolio_highlight_button_url" value="<?= esc((string)($portfolioHighlight['button_url'] ?? '/portfolio')); ?>">
                            </label>
                            <label class="admin-field">
                                <span>Jumlah Item Portfolio</span>
                                <input type="number" name="portfolio_highlight_limit" value="<?= esc((string)($portfolioHighlight['limit'] ?? 3)); ?>" min="1" max="12">
                            </label>

                            <?php $leadForm = (array)($settings['lead_form'] ?? []); ?>
                            <label class="admin-field admin-field--wide">
                                <span>Judul Form</span>
                                <input type="text" name="lead_form_title" value="<?= esc((string)($leadForm['title'] ?? '')); ?>" maxlength="100">
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Form</span>
                                <textarea name="lead_form_text" rows="2" maxlength="260"><?= esc((string)($leadForm['text'] ?? '')); ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span>Teks Tombol Form</span>
                                <input type="text" name="lead_form_button" value="<?= esc((string)($leadForm['button'] ?? '')); ?>" maxlength="100">
                            </label>
                        </div>
                    </div>
                    </section>
                </div>

                <aside class="admin-navigation-side">
                    <div class="admin-card admin-sticky-panel">
                        <span class="admin-badge">Preview Ringkas</span>
                        <?php if ($homepageSource === 'landing_page' && $selectedLandingPage): ?>
                            <h3>Landing Page sebagai Homepage</h3>
                            <p><?= esc((string)($selectedLandingPage['title'] ?? 'Landing Page')); ?></p>
                            <div class="admin-preview-box">
                                <strong><?= esc((string)($selectedLandingPage['meta_title'] ?? $selectedLandingPage['title'] ?? 'SEO Landing Page')); ?></strong>
                                <span><?= esc((string)($selectedLandingPage['meta_description'] ?? 'Homepage akan mengambil konten dari landing page terpilih.')); ?></span>
                            </div>
                        <?php else: ?>
                            <h3><?= esc((string)($modes[$currentMode]['label'] ?? 'Homepage')); ?></h3>
                            <p><?= esc((string)($hero['title'] ?? 'Judul homepage')); ?></p>
                            <div class="admin-preview-box">
                                <strong><?= esc((string)($hero['eyebrow'] ?? 'Label kecil')); ?></strong>
                                <span><?= esc((string)($hero['description'] ?? 'Deskripsi singkat beranda.')); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="admin-preview-box">
                            <strong>Urutan Aktif</strong>
                            <span><?= esc(implode(' → ', array_map(static fn(string $key): string => (string)($sectionDefinitions[$key]['short_label'] ?? $sectionDefinitions[$key]['label'] ?? $key), array_slice($orderedActiveSections, 0, 8)))); ?></span>
                        </div>
                        <div class="admin-action-stack">
                            <button class="admin-btn" type="submit">Simpan Beranda</button>
                            <button class="admin-btn admin-btn--ghost" type="submit" name="form_action" value="reset" onclick="return confirm('Kembalikan pengaturan beranda ke bawaan template?')">Reset Bawaan</button>
                            <a class="admin-btn admin-btn--light" href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Homepage</a>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
