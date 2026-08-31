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
            navigation_reset_settings();
            redirect_302('admin/navigation?message=' . rawurlencode('Menu, header, dan footer sudah dikembalikan ke bawaan template.'));
        }

        navigation_save_settings(navigation_settings_from_post($_POST));
        redirect_302('admin/navigation?message=' . rawurlencode('Pengaturan menu, header, dan footer berhasil disimpan.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = navigation_settings();
$header = (array)($settings['header'] ?? []);
$menuItems = (array)($settings['menu_items'] ?? []);
$footer = (array)($settings['footer'] ?? []);
$footerColumns = (array)($settings['footer_columns'] ?? []);
$bottomLinks = (array)($settings['bottom_links'] ?? []);

for ($i = count($menuItems); $i < 8; $i++) {
    $menuItems[] = ['label' => '', 'url' => '', 'enabled' => false, 'new_tab' => false, 'children' => []];
}

for ($i = count($footerColumns); $i < 4; $i++) {
    $footerColumns[] = ['title' => '', 'links' => []];
}

for ($i = count($bottomLinks); $i < 4; $i++) {
    $bottomLinks[] = ['label' => '', 'url' => '', 'enabled' => false, 'new_tab' => false];
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Menu, Header & Footer - ' . SITE_NAME,
    'description' => 'Atur menu navigasi, tampilan header, dan footer website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-navigation-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Struktur Website</div>
                <h1>Menu, Header & Footer</h1>
                <p>Atur link menu, informasi bagian atas website, kolom footer, dan link penting tanpa mengubah kode.</p>
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

            <form method="post" class="admin-navigation-layout">
                <?= csrf_field(); ?>

                <div class="admin-navigation-main" data-admin-page-tab-scope>
                    <div class="admin-page-subtabs admin-page-subtabs--3" role="tablist" aria-label="Bagian Menu & Footer">
                        <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="nav-header"><span>1. Header</span><small>Bar atas, logo, search</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="nav-menu"><span>2. Menu Utama</span><small>Navigasi website</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="nav-footer"><span>3. Footer</span><small>Brand, kolom, link bawah</small></button>
                    </div>
                    <div class="admin-page-mobile-jump">
                        <label class="admin-field"><span>Pilih bagian</span><select data-admin-page-tab-select aria-label="Pilih bagian Menu & Footer"><option value="nav-header">1. Header</option><option value="nav-menu">2. Menu Utama</option><option value="nav-footer">3. Footer</option></select></label>
                    </div>

                    <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="nav-header">
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Header</span>
                            <h2>Bagian Atas Website</h2>
                            <p>Pilih elemen yang ingin ditampilkan di area header website.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field admin-field--wide">
                                <span>Teks Bar Atas</span>
                                <input type="text" name="topbar_text" value="<?= esc((string)($header['topbar_text'] ?? '')); ?>" maxlength="180" placeholder="Contoh: Website resmi toko dan layanan kami">
                            </label>
                            <label class="admin-field">
                                <span>Placeholder Search</span>
                                <input type="text" name="search_placeholder" value="<?= esc((string)($header['search_placeholder'] ?? '')); ?>" maxlength="120" placeholder="Cari produk atau artikel...">
                            </label>
                            <label class="admin-field">
                                <span>Label Tombol Header</span>
                                <input type="text" name="header_cta_label" value="<?= esc((string)($header['header_cta_label'] ?? '')); ?>" maxlength="40" placeholder="Konsultasi">
                            </label>
                            <label class="admin-field">
                                <span>Link Tombol Header</span>
                                <input type="text" name="header_cta_url" value="<?= esc((string)($header['header_cta_url'] ?? '')); ?>" placeholder="/kontak atau https://...">
                            </label>
                        </div>

                        <div class="admin-toggle-grid">
                            <?php foreach ([
                                'show_topbar' => 'Tampilkan bar atas',
                                'show_topbar_phone' => 'Tampilkan telepon',
                                'show_topbar_whatsapp' => 'Tampilkan WhatsApp',
                                'show_logo' => 'Tampilkan logo',
                                'show_menu' => 'Tampilkan menu utama',
                                'show_search' => 'Tampilkan kolom pencarian',
                                'show_header_cta' => 'Tampilkan tombol di header',
                                'header_cta_new_tab' => 'Tombol header buka tab baru',
                            ] as $field => $label): ?>
                                <label class="admin-check-card">
                                    <input type="checkbox" name="<?= esc($field); ?>" value="1" <?= !empty($header[$field]) ? 'checked' : ''; ?>>
                                    <span><?= esc($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="nav-menu" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Menu Utama</span>
                            <h2>Navigasi Website</h2>
                            <p>Isi label dan link menu. Baris kosong bisa dipakai untuk menambah menu baru. Untuk submenu, tulis satu baris per link dengan format <strong>Label | /link</strong>.</p>
                        </div>

                        <?php
                        $menuRows = array_values((array)$menuItems);
                        for ($i = count($menuRows); $i < 10; $i++) {
                            $menuRows[] = ['label' => '', 'url' => '', 'enabled' => false, 'new_tab' => false, 'children' => []];
                        }
                        ?>
                        <div class="admin-menu-builder">
                            <?php foreach (array_slice($menuRows, 0, 10) as $index => $item): ?>
                                <div class="admin-menu-row <?= trim((string)($item['label'] ?? '')) === '' ? 'is-empty' : ''; ?>">
                                    <div class="admin-menu-row__number"><?= (int)$index + 1; ?></div>
                                    <div class="admin-form-grid admin-form-grid--compact">
                                        <label class="admin-field">
                                            <span>Nama Menu</span>
                                            <input type="text" name="menu_label[<?= (int)$index; ?>]" value="<?= esc((string)($item['label'] ?? '')); ?>" maxlength="80" placeholder="Contoh: Katalog">
                                        </label>
                                        <label class="admin-field">
                                            <span>Link</span>
                                            <input type="text" name="menu_url[<?= (int)$index; ?>]" value="<?= esc((string)($item['url'] ?? '')); ?>" placeholder="/katalog atau https://...">
                                        </label>
                                        <label class="admin-field admin-field--wide">
                                            <span>Submenu Opsional</span>
                                            <textarea name="menu_children[<?= (int)$index; ?>]" rows="3" placeholder="Produk | /katalog&#10;Layanan | /layanan"><?= esc(navigation_children_to_text((array)($item['children'] ?? []))); ?></textarea>
                                        </label>
                                    </div>
                                    <div class="admin-menu-row__options">
                                        <label><input type="checkbox" name="menu_enabled[<?= (int)$index; ?>]" value="1" <?= !empty($item['enabled']) ? 'checked' : ''; ?>> Tampilkan</label>
                                        <label><input type="checkbox" name="menu_new_tab[<?= (int)$index; ?>]" value="1" <?= !empty($item['new_tab']) ? 'checked' : ''; ?>> Tab baru</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="nav-footer" hidden>
                    <div class="admin-page-nested-tabs" data-admin-page-tab-scope>
                        <div class="admin-page-subtabs admin-page-subtabs--3" role="tablist" aria-label="Bagian Footer">
                            <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="footer-brand"><span>Info Brand</span><small>Deskripsi & copyright</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="footer-columns"><span>Kolom Footer</span><small>Kelompok link</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="footer-bottom"><span>Link Footer Bawah</span><small>Policy, terms, sitemap</small></button>
                        </div>
                        <div class="admin-page-mobile-jump">
                            <label class="admin-field"><span>Pilih bagian footer</span><select data-admin-page-tab-select aria-label="Pilih bagian footer"><option value="footer-brand">Info Brand</option><option value="footer-columns">Kolom Footer</option><option value="footer-bottom">Link Footer Bawah</option></select></label>
                        </div>

                        <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="footer-brand">
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Footer</span>
                            <h2>Info Brand di Footer</h2>
                            <p>Atur deskripsi singkat, kontak, sosial media, dan teks copyright.</p>
                        </div>

                        <div class="admin-form-grid">
                            <label class="admin-field admin-field--wide">
                                <span>Deskripsi Footer</span>
                                <textarea name="footer_brand_description" rows="3" maxlength="260" placeholder="Deskripsi singkat bisnis Anda."><?= esc((string)($footer['brand_description'] ?? '')); ?></textarea>
                            </label>
                            <label class="admin-field admin-field--wide">
                                <span>Teks Copyright</span>
                                <input type="text" name="footer_copyright_text" value="<?= esc((string)($footer['copyright_text'] ?? '')); ?>" maxlength="160" placeholder="© {year} {site}. All rights reserved.">
                                <small>Gunakan <code>{year}</code> untuk tahun otomatis dan <code>{site}</code> untuk nama bisnis.</small>
                            </label>
                        </div>

                        <div class="admin-toggle-grid">
                            <?php foreach ([
                                'footer_show_brand_column' => ['label' => 'Tampilkan kolom brand', 'value' => !empty($footer['show_brand_column'])],
                                'footer_show_social_links' => ['label' => 'Tampilkan sosial media', 'value' => !empty($footer['show_social_links'])],
                                'footer_show_contact_line' => ['label' => 'Tampilkan kontak footer', 'value' => !empty($footer['show_contact_line'])],
                            ] as $field => $meta): ?>
                                <label class="admin-check-card">
                                    <input type="checkbox" name="<?= esc($field); ?>" value="1" <?= $meta['value'] ? 'checked' : ''; ?>>
                                    <span><?= esc($meta['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="footer-columns" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Kolom Footer</span>
                            <h2>Link Footer</h2>
                            <p>Buat sampai 4 kolom link. Link kosong tidak akan tampil.</p>
                        </div>

                        <div class="admin-footer-column-grid">
                            <?php foreach (array_slice($footerColumns, 0, 4) as $columnIndex => $column): ?>
                                <?php
                                $links = (array)($column['links'] ?? []);
                                for ($i = count($links); $i < 5; $i++) {
                                    $links[] = ['label' => '', 'url' => '', 'enabled' => false, 'new_tab' => false];
                                }
                                ?>
                                <div class="admin-footer-column-card">
                                    <label class="admin-field">
                                        <span>Judul Kolom <?= (int)$columnIndex + 1; ?></span>
                                        <input type="text" name="footer_column_title[<?= (int)$columnIndex; ?>]" value="<?= esc((string)($column['title'] ?? '')); ?>" maxlength="80" placeholder="Contoh: Halaman">
                                    </label>

                                    <?php foreach (array_slice($links, 0, 5) as $linkIndex => $link): ?>
                                        <div class="admin-footer-link-row">
                                            <label class="admin-field">
                                                <span>Label</span>
                                                <input type="text" name="footer_link_label[<?= (int)$columnIndex; ?>][<?= (int)$linkIndex; ?>]" value="<?= esc((string)($link['label'] ?? '')); ?>" placeholder="Kontak">
                                            </label>
                                            <label class="admin-field">
                                                <span>Link</span>
                                                <input type="text" name="footer_link_url[<?= (int)$columnIndex; ?>][<?= (int)$linkIndex; ?>]" value="<?= esc((string)($link['url'] ?? '')); ?>" placeholder="/kontak">
                                            </label>
                                            <div class="admin-footer-link-options">
                                                <label><input type="checkbox" name="footer_link_enabled[<?= (int)$columnIndex; ?>][<?= (int)$linkIndex; ?>]" value="1" <?= !empty($link['enabled']) ? 'checked' : ''; ?>> Tampil</label>
                                                <label><input type="checkbox" name="footer_link_new_tab[<?= (int)$columnIndex; ?>][<?= (int)$linkIndex; ?>]" value="1" <?= !empty($link['new_tab']) ? 'checked' : ''; ?>> Tab baru</label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="footer-bottom" hidden>
                    <div class="admin-card admin-editor">
                        <div class="admin-form-head">
                            <span class="admin-badge">Link Bawah</span>
                            <h2>Footer Paling Bawah</h2>
                            <p>Biasanya berisi Privacy Policy, Terms, dan Sitemap.</p>
                        </div>

                        <div class="admin-menu-builder admin-menu-builder--bottom-links">
                            <?php foreach (array_slice($bottomLinks, 0, 4) as $index => $link): ?>
                                <div class="admin-footer-link-row admin-footer-link-row--bottom">
                                    <label class="admin-field">
                                        <span>Label</span>
                                        <input type="text" name="bottom_link_label[<?= (int)$index; ?>]" value="<?= esc((string)($link['label'] ?? '')); ?>" placeholder="Privacy Policy">
                                    </label>
                                    <label class="admin-field">
                                        <span>Link</span>
                                        <input type="text" name="bottom_link_url[<?= (int)$index; ?>]" value="<?= esc((string)($link['url'] ?? '')); ?>" placeholder="/privacy-policy">
                                    </label>
                                    <div class="admin-footer-link-options">
                                        <label><input type="checkbox" name="bottom_link_enabled[<?= (int)$index; ?>]" value="1" <?= !empty($link['enabled']) ? 'checked' : ''; ?>> Tampil</label>
                                        <label><input type="checkbox" name="bottom_link_new_tab[<?= (int)$index; ?>]" value="1" <?= !empty($link['new_tab']) ? 'checked' : ''; ?>> Tab baru</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                        </section>
                    </div>
                    </section>
                </div>

                <aside class="admin-navigation-side">
                    <div class="admin-card admin-editor admin-navigation-sticky">
                        <div class="admin-form-head">
                            <span class="admin-badge">Preview Ringkas</span>
                            <h2>Struktur Aktif</h2>
                            <p>Cek ringkasan menu sebelum disimpan.</p>
                        </div>

                        <div class="admin-navigation-preview">
                            <strong>Menu Utama</strong>
                            <ul>
                                <?php foreach (array_slice($menuItems, 0, 8) as $item): ?>
                                    <?php if (!empty($item['enabled']) && trim((string)($item['label'] ?? '')) !== ''): ?>
                                        <li><?= esc((string)$item['label']); ?> <span><?= esc((string)($item['url'] ?? '')); ?></span></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="admin-help admin-help--soft">
                            <strong>Tips pengisian</strong>
                            <p>Gunakan link internal seperti <code>/katalog</code>, <code>/layanan</code>, atau link lengkap seperti <code>https://example.com</code>.</p>
                        </div>

                        <div class="admin-footer-actions admin-brand-actions">
                            <button class="admin-btn admin-btn--primary admin-btn--full" type="submit" name="form_action" value="save">Simpan Menu & Footer</button>
                            <button class="admin-btn admin-btn--soft admin-btn--full" type="submit" name="form_action" value="reset" onclick="return confirm('Kembalikan menu, header, dan footer ke bawaan template?')">Reset Bawaan</button>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
