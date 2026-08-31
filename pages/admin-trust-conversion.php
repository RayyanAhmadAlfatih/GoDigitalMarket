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

        $action = (string)($_POST['form_action'] ?? 'save');
        if ($action === 'reset') {
            trust_conversion_reset_settings();
            redirect_302('admin/trust-conversion?message=' . rawurlencode('Trust & conversion block sudah dikembalikan ke bawaan template.'));
        }

        trust_conversion_save_settings(trust_conversion_settings_from_post($_POST));
        redirect_302('admin/trust-conversion?message=' . rawurlencode('Trust & conversion block berhasil disimpan.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = trust_conversion_settings();
$summary = trust_conversion_summary($settings);
$blockTypes = trust_conversion_block_types();
$blocks = (array)($settings['blocks'] ?? []);
$enabledBlocks = (int)($summary['enabled_blocks'] ?? 0);
$totalItems = (int)($summary['total_items'] ?? 0);
$positions = [
    'after_hero' => 'Setelah hero homepage',
    'after_intro' => 'Setelah pengantar homepage',
    'before_lead_form' => 'Sebelum form konsultasi',
    'after_content' => 'Setelah konten utama',
];

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Trust & Conversion Block - ' . SITE_NAME,
    'description' => 'Builder section testimoni, FAQ, benefit, garansi, badge trust, before-after, dan CTA untuk meningkatkan kepercayaan dan konversi website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<style>
.trust-admin-hero{background:radial-gradient(circle at 15% 10%,rgba(255,255,255,.18),transparent 28%),linear-gradient(135deg,#0f4c81,#1d4ed8 48%,#0f172a);color:#fff}.trust-admin-hero h1,.trust-admin-hero p{color:#fff}.trust-admin-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:1.2rem;align-items:start}.trust-admin-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem;margin-bottom:1rem}.trust-admin-summary-card{border:1px solid #dbe7f0;border-radius:22px;background:#fff;padding:1rem}.trust-admin-summary-card strong{display:block;color:#1557e6;font-size:1.85rem;line-height:1}.trust-admin-summary-card span{display:block;margin-top:.35rem;color:#64748b;font-weight:800}.trust-admin-blocks{display:grid;gap:1rem}.trust-admin-block{border:1px solid #dbe7f0;border-radius:26px;background:#fff;box-shadow:0 18px 44px rgba(15,23,42,.05);overflow:hidden}.trust-admin-block__head{display:grid;grid-template-columns:auto 1fr auto;gap:.85rem;align-items:center;padding:1rem 1.1rem;background:linear-gradient(180deg,#fff,#f8fbff);border-bottom:1px solid #e2edf6}.trust-admin-block__icon{width:44px;height:44px;border-radius:16px;background:#eaf1ff;color:#1557e6;display:inline-flex;align-items:center;justify-content:center;font-weight:950}.trust-admin-block__head h3{margin:.05rem 0;color:#0f172a}.trust-admin-block__head p{margin:0;color:#64748b}.trust-admin-block__body{padding:1.1rem}.trust-admin-items{display:grid;gap:.75rem}.trust-admin-item{display:grid;grid-template-columns:34px minmax(0,1fr);gap:.75rem;padding:.85rem;border:1px solid #e2edf6;border-radius:20px;background:#fbfdff}.trust-admin-item strong{width:34px;height:34px;border-radius:999px;background:#fff;border:1px solid #dbe7f0;display:inline-flex;align-items:center;justify-content:center;color:#1557e6}.trust-admin-item .admin-form-grid{gap:.65rem}.trust-admin-settings{display:grid;gap:.75rem}.trust-admin-preview{display:grid;gap:.75rem}.trust-admin-preview div{border:1px solid #dbe7f0;border-radius:18px;padding:.85rem;background:#fff}.trust-admin-preview strong{display:block}.trust-admin-type-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .65rem;border-radius:999px;background:#eff6ff;color:#1557e6;font-size:.78rem;font-weight:900}.trust-admin-side{position:sticky;top:88px}.trust-admin-type-list{display:grid;gap:.5rem}.trust-admin-type-list div{display:flex;justify-content:space-between;gap:.75rem;border:1px solid #e2edf6;border-radius:16px;padding:.7rem;background:#fff}.trust-admin-footer-actions{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;justify-content:flex-end;margin-top:1rem}.trust-admin-reset{display:inline}.trust-admin-checks{display:grid;gap:.65rem}.trust-admin-checks label{display:grid;grid-template-columns:22px minmax(0,1fr);gap:.7rem;align-items:flex-start;justify-content:start;width:100%;border:1px solid #e2edf6;border-radius:18px;background:#fff;padding:.85rem 1rem;text-align:left}.trust-admin-checks input{width:18px;height:18px;margin:.12rem 0 0;accent-color:#1557e6}.trust-admin-checks span{display:block;font-weight:900;color:#0f172a;text-align:left}.trust-admin-checks small{display:block;color:#64748b;margin-top:.12rem;text-align:left}@media(max-width:1100px){.trust-admin-grid{grid-template-columns:1fr}.trust-admin-side{position:static}.trust-admin-summary{grid-template-columns:1fr}.trust-admin-block__head{grid-template-columns:auto 1fr}.trust-admin-block__head .admin-check-card{grid-column:1/-1}.trust-admin-footer-actions{justify-content:stretch}.trust-admin-footer-actions .admin-btn{width:100%;justify-content:center}}
</style>

<main id="main-content" class="admin-shell admin-trust-conversion-shell">
    <section class="admin-hero trust-admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Trust & Conversion</div>
                <h1>Trust & Conversion Block Builder</h1>
                <p>Buat section testimoni, FAQ, benefit, garansi, badge trust, before-after, dan CTA dari dashboard agar pengunjung lebih yakin sebelum mengambil aksi.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/homepage')); ?>">Atur Urutan Homepage</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <form method="post" class="trust-admin-grid">
                <?= csrf_field(); ?>
                <div>
                    <div class="trust-admin-summary">
                        <div class="trust-admin-summary-card"><strong><?= (int)($summary['total_blocks'] ?? 0); ?></strong><span>Total block tersedia</span></div>
                        <div class="trust-admin-summary-card"><strong><?= $enabledBlocks; ?></strong><span>Block aktif</span></div>
                        <div class="trust-admin-summary-card"><strong><?= $totalItems; ?></strong><span>Poin trust aktif</span></div>
                    </div>

                    <div class="admin-card" style="margin-bottom:1rem">
                        <div class="admin-form-head">
                            <span class="admin-badge">Pengaturan Tampil</span>
                            <h2>Atur Posisi dan Status Section</h2>
                            <p>Aktifkan builder dan pilih apakah tampil di homepage. Urutan utama homepage bisa disusun dari menu Atur Beranda, sementara pilihan posisi ini tetap aman sebagai fallback.</p>
                        </div>
                        <div class="admin-form-grid">
                            <label class="admin-field">
                                <span>Posisi di Homepage</span>
                                <select name="insert_position">
                                    <?php foreach ($positions as $key => $label): ?>
                                        <option value="<?= esc($key); ?>" <?= (string)($settings['insert_position'] ?? 'before_lead_form') === $key ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="trust-admin-checks admin-field--wide">
                                <label>
                                    <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>>
                                    <span>Aktifkan Trust & Conversion Builder<small>Jika dimatikan, semua block dari builder ini tidak tampil.</small></span>
                                </label>
                                <label>
                                    <input type="checkbox" name="homepage_enabled" value="1" <?= !empty($settings['homepage_enabled']) ? 'checked' : ''; ?>>
                                    <span>Tampilkan di Homepage<small>Homepage tetap mengikuti posisi yang dipilih. Landing page builder lama tidak terganggu.</small></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="admin-card" style="margin-bottom:1rem">
                        <div class="admin-form-head">
                            <span class="admin-badge">Isi Block</span>
                            <h2>Kelola Section Kepercayaan & CTA</h2>
                            <p>Setiap block bisa diaktifkan/nonaktifkan. Ubah judul, deskripsi, CTA, dan poin-poinnya sesuai bisnis.</p>
                        </div>
                    </div>

                    <div class="trust-admin-blocks">
                        <?php foreach ($blocks as $index => $block): ?>
                            <?php
                                $type = (string)($block['type'] ?? 'benefits');
                                $typeDef = (array)($blockTypes[$type] ?? $blockTypes['benefits']);
                                $items = (array)($block['items'] ?? []);
                                for ($i = count($items); $i < 6; $i++) {
                                    $items[] = ['title' => '', 'text' => '', 'meta' => ''];
                                }
                            ?>
                            <article class="trust-admin-block">
                                <div class="trust-admin-block__head">
                                    <span class="trust-admin-block__icon"><?= (int)$index + 1; ?></span>
                                    <div>
                                        <span class="trust-admin-type-pill"><?= esc((string)($typeDef['label'] ?? $type)); ?></span>
                                        <h3><?= esc((string)($block['title'] ?? $typeDef['label'] ?? 'Block')); ?></h3>
                                        <p><?= esc((string)($typeDef['description'] ?? '')); ?></p>
                                    </div>
                                    <label class="admin-check-card" style="margin:0">
                                        <input type="checkbox" name="block_enabled[<?= (int)$index; ?>]" value="1" <?= !empty($block['enabled']) ? 'checked' : ''; ?>>
                                        <span>Aktif</span>
                                    </label>
                                </div>
                                <div class="trust-admin-block__body">
                                    <input type="hidden" name="block_id[<?= (int)$index; ?>]" value="<?= esc((string)($block['id'] ?? '')); ?>">
                                    <input type="hidden" name="block_type[<?= (int)$index; ?>]" value="<?= esc($type); ?>">
                                    <div class="admin-form-grid">
                                        <label class="admin-field">
                                            <span>Urutan</span>
                                            <input type="number" name="block_order[<?= (int)$index; ?>]" value="<?= esc((string)($block['order'] ?? (($index + 1) * 10))); ?>" min="1" max="999">
                                        </label>
                                        <label class="admin-field">
                                            <span>Label kecil</span>
                                            <input type="text" name="block_badge[<?= (int)$index; ?>]" value="<?= esc((string)($block['badge'] ?? '')); ?>" maxlength="80">
                                        </label>
                                        <label class="admin-field admin-field--wide">
                                            <span>Judul Section</span>
                                            <input type="text" name="block_title[<?= (int)$index; ?>]" value="<?= esc((string)($block['title'] ?? '')); ?>" maxlength="160">
                                        </label>
                                        <label class="admin-field admin-field--wide">
                                            <span>Deskripsi Section</span>
                                            <textarea name="block_description[<?= (int)$index; ?>]" rows="2" maxlength="360"><?= esc((string)($block['description'] ?? '')); ?></textarea>
                                        </label>
                                        <label class="admin-field">
                                            <span>Teks Tombol CTA</span>
                                            <input type="text" name="block_cta_label[<?= (int)$index; ?>]" value="<?= esc((string)($block['cta_label'] ?? '')); ?>" maxlength="50" placeholder="Contoh: Konsultasi Sekarang">
                                        </label>
                                        <label class="admin-field">
                                            <span>Link CTA</span>
                                            <input type="text" name="block_cta_url[<?= (int)$index; ?>]" value="<?= esc((string)($block['cta_url'] ?? '/kontak')); ?>" placeholder="/kontak atau https://...">
                                        </label>
                                    </div>

                                    <div class="admin-form-head" style="margin-top:1rem">
                                        <span class="admin-badge">Poin Isi</span>
                                        <h3>Item di Dalam Section</h3>
                                        <p>Kosongkan item yang tidak dipakai. Maksimal 6 item per block agar tampilan tetap rapi.</p>
                                    </div>
                                    <div class="trust-admin-items">
                                        <?php foreach (array_slice($items, 0, 6) as $itemIndex => $item): ?>
                                            <div class="trust-admin-item">
                                                <strong><?= (int)$itemIndex + 1; ?></strong>
                                                <div class="admin-form-grid">
                                                    <label class="admin-field">
                                                        <span><?= esc((string)($typeDef['item_title'] ?? 'Judul')); ?></span>
                                                        <input type="text" name="item_title[<?= (int)$index; ?>][<?= (int)$itemIndex; ?>]" value="<?= esc((string)($item['title'] ?? '')); ?>" maxlength="90">
                                                    </label>
                                                    <label class="admin-field admin-field--wide">
                                                        <span><?= esc((string)($typeDef['item_text'] ?? 'Keterangan')); ?></span>
                                                        <textarea name="item_text[<?= (int)$index; ?>][<?= (int)$itemIndex; ?>]" rows="2" maxlength="360"><?= esc((string)($item['text'] ?? '')); ?></textarea>
                                                    </label>
                                                    <label class="admin-field">
                                                        <span><?= esc((string)($typeDef['item_meta'] ?? 'Catatan')); ?></span>
                                                        <input type="text" name="item_meta[<?= (int)$index; ?>][<?= (int)$itemIndex; ?>]" value="<?= esc((string)($item['meta'] ?? '')); ?>" maxlength="120">
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="trust-admin-footer-actions">
                        <button class="admin-btn admin-btn--primary" type="submit" name="form_action" value="save">Simpan Trust Block</button>
                        <button class="admin-btn admin-btn--soft" type="submit" name="form_action" value="reset" onclick="return confirm('Kembalikan trust & conversion block ke bawaan template?');">Reset Bawaan</button>
                    </div>
                </div>

                <aside class="trust-admin-side">
                    <div class="admin-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Panduan Singkat</span>
                            <h2>Urutan yang Disarankan</h2>
                            <p>Untuk homepage umum, aktifkan benefit, testimoni, FAQ, dan CTA dulu. Garansi, badge, dan before-after bisa menyusul.</p>
                        </div>
                        <div class="trust-admin-preview">
                            <div><strong>1. Benefit</strong><span>Jawab “kenapa harus pilih bisnis ini”.</span></div>
                            <div><strong>2. Testimoni</strong><span>Bangun bukti sosial dan rasa percaya.</span></div>
                            <div><strong>3. FAQ</strong><span>Kurangi pertanyaan berulang dan keberatan awal.</span></div>
                            <div><strong>4. CTA</strong><span>Arahkan ke WhatsApp, form, katalog, atau checkout.</span></div>
                        </div>
                    </div>

                    <div class="admin-card" style="margin-top:1rem">
                        <div class="admin-form-head">
                            <span class="admin-badge">Status Block</span>
                            <h2>Ringkasan Tipe</h2>
                        </div>
                        <div class="trust-admin-type-list">
                            <?php foreach ($blockTypes as $typeKey => $typeDef): ?>
                                <div><span><?= esc((string)($typeDef['label'] ?? $typeKey)); ?></span><strong><?= (int)(($summary['by_type'][$typeKey] ?? 0)); ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-card" style="margin-top:1rem">
                        <div class="admin-form-head">
                            <span class="admin-badge">Catatan UX</span>
                            <h2>Tips Copy</h2>
                            <p>Pakai bahasa spesifik sesuai bisnis. Hindari klaim berlebihan. Testimoni dan garansi sebaiknya sesuai fakta yang bisa dipertanggungjawabkan.</p>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
