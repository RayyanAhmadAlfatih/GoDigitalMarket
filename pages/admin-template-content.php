<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$pages = function_exists('template_content_all') ? template_content_all() : [];
$currentKey = (string)($_GET['page'] ?? 'about');
if (!isset($pages[$currentKey])) {
    $currentKey = array_key_first($pages) ?: 'about';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['form_action'] ?? 'save_page');
        $postKey = (string)($_POST['page_key'] ?? $currentKey);
        if (!isset($pages[$postKey])) {
            $postKey = $currentKey;
        }
        if ($action === 'reset_page') {
            template_content_reset_page($postKey);
            redirect_302('admin/template-content?page=' . rawurlencode($postKey) . '&message=' . rawurlencode('Konten halaman dikembalikan ke starter bawaan template.'));
        }
        template_content_save_page($postKey, $_POST);
        redirect_302('admin/template-content?page=' . rawurlencode($postKey) . '&message=' . rawurlencode('Konten halaman template berhasil disimpan.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pages = function_exists('template_content_all') ? template_content_all() : [];
$page = $pages[$currentKey] ?? [];
$inventory = function_exists('template_content_inventory') ? template_content_inventory() : [];

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="editable-template-content-' . date('Ymd-His') . '.json"');
    echo json_encode(['pages' => $pages, 'inventory' => $inventory], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="editable-template-content-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['page', 'path', 'status', 'hero_title', 'meta_title', 'updated_at'], ',', '"', '\\', "\n");
    foreach ($pages as $item) {
        fputcsv($out, [(string)($item['label'] ?? ''), (string)($item['path'] ?? ''), (string)($item['status'] ?? ''), (string)($item['hero_title'] ?? ''), (string)($item['meta_title'] ?? ''), (string)($item['updated_at'] ?? '')], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Konten Template Editable - ' . SITE_NAME,
    'description' => 'Edit konten bawaan template publik seperti Tentang Kami, Kontak, Privacy Policy, dan Terms dari dashboard admin.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<style>
.admin-template-section-editor{margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(148,163,184,.28)}
.admin-template-section-list{display:grid;gap:1rem;margin:1rem 0}.admin-template-section-card{border:1px solid #dbe7f0;border-radius:22px;background:linear-gradient(180deg,#fff,#f8fbff);box-shadow:0 14px 34px rgba(15,23,42,.05);overflow:hidden}.admin-template-section-card summary{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;list-style:none}.admin-template-section-card summary::-webkit-details-marker{display:none}.admin-template-section-card summary strong{display:block;color:#0f172a;font-weight:950}.admin-template-section-card summary small{display:block;margin-top:.2rem;color:#64748b;font-weight:800}.admin-template-section-toggle{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#eaf1ff;color:#1454d8;font-weight:900;font-size:.78rem;padding:.4rem .7rem}.admin-template-section-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;padding:0 1.1rem 1.1rem}.admin-template-section-add{margin-top:1rem;padding:1.1rem;border-radius:22px;border:1px dashed #b9cffc;background:#f8fbff}.admin-template-section-add .admin-template-section-grid{padding:0;margin-top:1rem}.admin-template-content-check--danger span{color:#991b1b!important}.editable-template-body{max-width:820px;margin:1rem auto}.editable-template-body .card-content{line-height:1.75}@media(max-width:900px){.admin-template-section-grid{grid-template-columns:1fr}.admin-template-section-card summary{align-items:flex-start;flex-direction:column}.admin-template-section-toggle{align-self:flex-start}}
</style>

<main id="main-content" class="admin-shell admin-template-content-shell">
    <section class="admin-hero admin-template-content-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Editable Template Content</div>
                <h1>Konten Template Publik</h1>
                <p>Edit halaman dan section publik tanpa menyentuh kode. Hero, judul section, deskripsi, CTA, blok tambahan, dan konten starter bisa disesuaikan dengan niche, brand, dan kebutuhan bisnis.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url((string)($page['path'] ?? ''))); ?>" target="_blank" rel="noopener">Preview Halaman</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/starter-wizard')); ?>">Starter Wizard</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-template-content-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-card admin-template-content-info">
                <span class="admin-badge">Template Publik</span>
                <h2>Starter content = editable, bukan hardcoded</h2>
                <p>Halaman publik bawaan dan section dinamis seperti Home, Katalog, Layanan, dan Artikel sekarang punya sumber data editable. Admin bisa edit teks, sembunyikan section, tambah blok custom, reset ke bawaan, atau export data.</p>
                <div class="admin-template-content-actions">
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/template-content?export=csv')); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/template-content?export=json')); ?>">Export JSON</a>
                </div>
            </div>

            <div class="admin-template-content-layout">
                <aside class="admin-card admin-template-content-nav" aria-label="Daftar halaman template">
                    <span class="admin-badge">Halaman</span>
                    <h2>Pilih halaman</h2>
                    <div class="admin-template-content-page-list">
                        <?php foreach ($pages as $key => $item): ?>
                            <a class="<?= $currentKey === (string)$key ? 'is-active' : ''; ?>" href="<?= esc(url('admin/template-content?page=' . rawurlencode((string)$key))); ?>">
                                <strong><?= esc((string)($item['label'] ?? $key)); ?></strong>
                                <small>/<?= esc((string)($item['path'] ?? '')); ?> · <?= esc((string)($item['status'] ?? 'published')); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <form method="post" class="admin-card admin-template-content-form">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="form_action" value="save_page">
                    <input type="hidden" name="page_key" value="<?= esc($currentKey); ?>">

                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Edit Konten</span>
                            <h2><?= esc((string)($page['label'] ?? 'Halaman')); ?></h2>
                            <p>Gunakan placeholder <code>{site_name}</code>, <code>{whatsapp}</code>, <code>{email}</code>, atau <code>{site_url}</code> bila perlu.</p>
                        </div>
                        <div class="admin-template-content-actions">
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($page['path'] ?? ''))); ?>" target="_blank" rel="noopener">Preview</a>
                            <button class="admin-btn admin-btn--primary" type="submit">Simpan Konten</button>
                        </div>
                    </div>

                    <div class="admin-template-content-grid">
                        <label class="admin-field"><span>Status</span><select name="status"><option value="published" <?= (string)($page['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option><option value="draft" <?= (string)($page['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft / Noindex</option><option value="hidden" <?= (string)($page['status'] ?? '') === 'hidden' ? 'selected' : ''; ?>>Hidden / Noindex</option></select></label>
                        <label class="admin-field"><span>Meta Title</span><input type="text" name="meta_title" value="<?= esc((string)($page['meta_title'] ?? '')); ?>" maxlength="110"></label>
                        <label class="admin-field admin-field--wide"><span>Meta Description</span><input type="text" name="meta_description" value="<?= esc((string)($page['meta_description'] ?? '')); ?>" maxlength="220"></label>
                        <label class="admin-field"><span>Judul Hero</span><input type="text" name="hero_title" value="<?= esc((string)($page['hero_title'] ?? '')); ?>" maxlength="110"></label>
                        <label class="admin-field admin-field--wide"><span>Deskripsi Hero</span><input type="text" name="hero_description" value="<?= esc((string)($page['hero_description'] ?? '')); ?>" maxlength="220"></label>
                        <label class="admin-field"><span>Judul Konten Utama</span><input type="text" name="primary_title" value="<?= esc((string)($page['primary_title'] ?? '')); ?>" maxlength="110"></label>
                        <label class="admin-field admin-field--wide"><span>Konten Utama HTML Ringan</span><textarea name="primary_html" rows="8"><?= esc((string)($page['primary_html'] ?? '')); ?></textarea></label>
                        <label class="admin-field"><span>Judul Konten Samping</span><input type="text" name="secondary_title" value="<?= esc((string)($page['secondary_title'] ?? '')); ?>" maxlength="110"></label>
                        <label class="admin-field admin-field--wide"><span>Konten Samping HTML Ringan</span><textarea name="secondary_html" rows="6"><?= esc((string)($page['secondary_html'] ?? '')); ?></textarea></label>
                        <?php if ($currentKey === 'contact'): ?>
                            <label class="admin-field admin-template-content-check"><input type="checkbox" name="show_contact_form" value="1" <?= !array_key_exists('show_contact_form', $page) || !empty($page['show_contact_form']) ? 'checked' : ''; ?>><span>Tampilkan form kontak di halaman ini</span></label>
                        <?php else: ?>
                            <input type="hidden" name="show_contact_form" value="<?= !empty($page['show_contact_form']) ? '1' : '0'; ?>">
                        <?php endif; ?>
                    </div>

                    <?php $pageSections = (array)($page['sections'] ?? []); ?>
                    <?php if ($pageSections): ?>
                        <div class="admin-template-section-editor">
                            <div class="admin-form-head admin-form-head--split">
                                <div>
                                    <span class="admin-badge">Section Editable</span>
                                    <h2>Kelola section halaman ini</h2>
                                    <p>Bagian seperti hero, katalog pilihan, panduan, kategori artikel, dan form bisa diedit atau disembunyikan. Section custom bisa ditambah untuk kebutuhan niche/preset.</p>
                                </div>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($page['path'] ?? ''))); ?>" target="_blank" rel="noopener">Preview Publik</a>
                            </div>

                            <div class="admin-template-section-list">
                                <?php foreach ($pageSections as $sectionId => $section): ?>
                                    <?php if (!is_array($section)) { continue; } ?>
                                    <details class="admin-template-section-card" open>
                                        <summary>
                                            <span><strong><?= esc((string)($section['label'] ?? $sectionId)); ?></strong><small><?= !empty($section['locked']) ? 'Section bawaan' : 'Section custom'; ?> · <?= esc((string)($section['status'] ?? 'visible')); ?></small></span>
                                            <span class="admin-template-section-toggle">Edit</span>
                                        </summary>
                                        <input type="hidden" name="sections[<?= esc((string)$sectionId); ?>][label]" value="<?= esc((string)($section['label'] ?? $sectionId)); ?>">
                                        <div class="admin-template-section-grid">
                                            <label class="admin-field admin-template-content-check"><input type="checkbox" name="sections[<?= esc((string)$sectionId); ?>][is_visible]" value="1" <?= (string)($section['status'] ?? 'visible') === 'visible' ? 'checked' : ''; ?>><span>Tampilkan section ini di publik</span></label>
                                            <label class="admin-field"><span>Eyebrow / Badge kecil</span><input type="text" name="sections[<?= esc((string)$sectionId); ?>][eyebrow]" value="<?= esc((string)($section['eyebrow'] ?? '')); ?>" maxlength="80"></label>
                                            <label class="admin-field admin-field--wide"><span>Judul Section</span><input type="text" name="sections[<?= esc((string)$sectionId); ?>][title]" value="<?= esc((string)($section['title'] ?? '')); ?>" maxlength="140"></label>
                                            <label class="admin-field admin-field--wide"><span>Deskripsi Section</span><input type="text" name="sections[<?= esc((string)$sectionId); ?>][description]" value="<?= esc((string)($section['description'] ?? '')); ?>" maxlength="320"></label>
                                            <label class="admin-field admin-field--wide"><span>Konten HTML ringan / catatan section</span><textarea name="sections[<?= esc((string)$sectionId); ?>][body_html]" rows="5"><?= esc((string)($section['body_html'] ?? '')); ?></textarea></label>
                                            <label class="admin-field"><span>Label Tombol</span><input type="text" name="sections[<?= esc((string)$sectionId); ?>][button_label]" value="<?= esc((string)($section['button_label'] ?? '')); ?>" maxlength="60"></label>
                                            <label class="admin-field"><span>URL Tombol</span><input type="text" name="sections[<?= esc((string)$sectionId); ?>][button_url]" value="<?= esc((string)($section['button_url'] ?? '#')); ?>" maxlength="160"></label>
                                            <label class="admin-field"><span>Label Tombol Kedua</span><input type="text" name="sections[<?= esc((string)$sectionId); ?>][secondary_button_label]" value="<?= esc((string)($section['secondary_button_label'] ?? '')); ?>" maxlength="60"></label>
                                            <label class="admin-field"><span>URL Tombol Kedua</span><input type="text" name="sections[<?= esc((string)$sectionId); ?>][secondary_button_url]" value="<?= esc((string)($section['secondary_button_url'] ?? '#')); ?>" maxlength="160"></label>
                                            <?php if (empty($section['locked'])): ?>
                                                <label class="admin-field admin-template-content-check admin-template-content-check--danger"><input type="checkbox" name="sections[<?= esc((string)$sectionId); ?>][delete_section]" value="1"><span>Hapus section custom ini saat disimpan</span></label>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>

                            <div class="admin-template-section-add">
                                <span class="admin-badge">Tambah Section Custom</span>
                                <h3>Tambahkan blok baru tanpa menyentuh kode</h3>
                                <p>Section custom akan tampil di halaman publik dinamis sebagai blok tambahan. Cocok untuk testimoni, promo, FAQ singkat, trust block, atau narasi niche.</p>
                                <div class="admin-template-section-grid">
                                    <label class="admin-field"><span>Nama Section</span><input type="text" name="new_section_label" placeholder="Contoh: Testimoni Pelanggan"></label>
                                    <label class="admin-field"><span>Eyebrow</span><input type="text" name="new_section_eyebrow" placeholder="Bukti hasil"></label>
                                    <label class="admin-field admin-field--wide"><span>Judul Section Baru</span><input type="text" name="new_section_title" placeholder="Kenapa pelanggan memilih kami?"></label>
                                    <label class="admin-field admin-field--wide"><span>Deskripsi</span><input type="text" name="new_section_description" placeholder="Tulis ringkasan section baru."></label>
                                    <label class="admin-field admin-field--wide"><span>Body HTML Ringan</span><textarea name="new_section_body_html" rows="4" placeholder="<p>Isi section custom di sini.</p>"></textarea></label>
                                    <label class="admin-field"><span>Label Tombol</span><input type="text" name="new_section_button_label" placeholder="Hubungi Kami"></label>
                                    <label class="admin-field"><span>URL Tombol</span><input type="text" name="new_section_button_url" placeholder="/kontak"></label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="admin-template-content-footer-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Konten</button>
                        <button class="admin-btn admin-btn--danger" type="submit" name="form_action" value="reset_page" onclick="return confirm('Kembalikan halaman ini ke konten starter bawaan template?');">Reset ke Bawaan</button>
                    </div>
                </form>
            </div>

            <div class="admin-card admin-template-content-inventory">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Inventory Editable</span>
                        <h2>Ringkasan halaman publik bawaan</h2>
                        <p>Ini membantu admin melihat halaman mana saja yang sudah bisa diubah langsung dari dashboard.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/navigation')); ?>">Kelola Menu</a>
                </div>
                <div class="admin-template-content-table-wrap">
                    <table class="admin-template-content-table">
                        <thead><tr><th>Halaman</th><th>URL</th><th>Status</th><th>Update</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><strong><?= esc((string)($item['label'] ?? '')); ?></strong></td>
                                <td>/<?= esc((string)($item['path'] ?? '')); ?></td>
                                <td><span class="admin-badge"><?= esc((string)($item['status'] ?? 'published')); ?></span></td>
                                <td><?= esc((string)($item['updated_at'] ?? 'Starter')); ?></td>
                                <td><a href="<?= esc((string)($item['edit_url'] ?? '#')); ?>">Edit</a> · <a href="<?= esc((string)($item['public_url'] ?? '#')); ?>" target="_blank" rel="noopener">Preview</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
