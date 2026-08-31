<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();
$GLOBALS['admin_page'] = true;
$message = (string)($_GET['message'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['checkout_action'] ?? 'save');
        if ($action === 'save') {
            $settings = checkout_settings_from_post($_POST);
            if (!checkout_save_settings($settings)) {
                throw new RuntimeException('Pengaturan checkout belum bisa disimpan. Cek permission folder storage.');
            }
            redirect_302('admin/form-checkout?message=' . rawurlencode('Pengaturan checkout berhasil disimpan.'));
        }
        if ($action === 'reset') {
            if (!checkout_save_settings(checkout_default_settings())) {
                throw new RuntimeException('Pengaturan checkout belum bisa direset. Cek permission folder storage.');
            }
            redirect_302('admin/form-checkout?message=' . rawurlencode('Pengaturan checkout dikembalikan ke bawaan.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = checkout_settings();
$needText = implode("\n", (array)$settings['need_options']);
$locationText = implode("\n", (array)$settings['location_options']);
$shippingText = implode("\n", (array)$settings['shipping_method_options']);
$checkoutEnabled = !empty($settings['enabled']);
$enabledFields = 0;
foreach (['quantity_enabled', 'planned_date_enabled', 'need_enabled', 'location_enabled', 'payment_method_enabled', 'notes_enabled', 'address_enabled', 'shipping_method_enabled'] as $fieldFlag) {
    if (!empty($settings[$fieldFlag])) { $enabledFields++; }
}

set_seo([
    'title' => 'Form Checkout - ' . SITE_NAME,
    'description' => 'Pengaturan form checkout, field pembeli, alamat, pengiriman, pembayaran, dan pesan otomatis UMKM.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-checkout-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Checkout Builder</div>
                <h1>Checkout Completion & Field Manager</h1>
                <p>Atur form checkout, field pembeli, alamat pengiriman, metode kirim, pesan sukses, dan template pesan order tanpa edit kode.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('checkout')); ?>" target="_blank" rel="noopener">Lihat Checkout</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/orders')); ?>">Kelola Order</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Status Checkout</span><h2><?= $checkoutEnabled ? 'Aktif' : 'Nonaktif'; ?></h2><p><?= $checkoutEnabled ? 'Form publik bisa menerima order.' : 'Form publik sedang dimatikan admin.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Field Aktif</span><h2><?= (int)$enabledFields; ?>/8</h2><p>Jumlah, tanggal, kebutuhan, lokasi, pembayaran, catatan, alamat, pengiriman.</p></div>
                <div class="admin-card"><span class="admin-badge">Alamat</span><h2><?= !empty($settings['address_enabled']) ? (!empty($settings['address_required']) ? 'Wajib' : 'Opsional') : 'Nonaktif'; ?></h2><p>Produk digital/jasa tetap bisa melewati alamat fisik.</p></div>
                <div class="admin-card"><span class="admin-badge">Pengiriman</span><h2><?= !empty($settings['shipping_method_enabled']) ? 'Aktif' : 'Nonaktif'; ?></h2><p>Engine ongkir manual sudah tersedia di menu Shipping & Ongkir.</p></div>
            </div>

            <form method="post" class="admin-card admin-editor" data-admin-page-tab-scope>
                <?= csrf_field(); ?>
                <input type="hidden" name="checkout_action" value="save">
                <div class="admin-form-head admin-form-head--row">
                    <div>
                        <span class="admin-badge">Field Manager</span>
                        <h2>Pengaturan Form Checkout</h2>
                        <p>Checklist menentukan field mana yang muncul di public checkout. Pengaturan ini shared-hosting friendly dan disimpan sebagai JSON di folder storage.</p>
                    </div>
                    <div class="admin-form-actions admin-form-actions--inline">
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Checkout</button>
                    </div>
                </div>

                <div class="admin-page-subtabs admin-page-subtabs--5" role="tablist" aria-label="Bagian Checkout">
                    <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="checkout-basic"><span>1. Dasar</span><small>Judul & status</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="checkout-fields"><span>2. Field</span><small>Pembeli & order</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="checkout-shipping"><span>3. Alamat</span><small>Pengiriman</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="checkout-options"><span>4. Pilihan</span><small>Dropdown</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="checkout-messages"><span>5. Pesan</span><small>Success & template</small></button>
                </div>
                <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian checkout</span><select data-admin-page-tab-select aria-label="Pilih bagian checkout"><option value="checkout-basic">1. Dasar</option><option value="checkout-fields">2. Field</option><option value="checkout-shipping">3. Alamat</option><option value="checkout-options">4. Pilihan</option><option value="checkout-messages">5. Pesan</option></select></label></div>

                <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="checkout-basic">
                    <div class="admin-form-grid admin-form-row--2">
                        <label class="admin-toggle-option"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan form checkout publik</label>
                        <label class="admin-toggle-option"><input type="checkbox" name="email_required" value="1" <?= !empty($settings['email_required']) ? 'checked' : ''; ?>> Email wajib diisi</label>
                        <label>Judul Checkout<input name="headline" value="<?= esc((string)$settings['headline']); ?>" maxlength="140" placeholder="Lengkapi Data Pemesanan"></label>
                        <label>Label Tombol<input name="button_label" value="<?= esc((string)$settings['button_label']); ?>" maxlength="80" placeholder="Kirim Data Pemesanan"></label>
                        <label class="wide">Intro Checkout<textarea name="intro" rows="4" maxlength="600"><?= esc((string)$settings['intro']); ?></textarea></label>
                        <label class="wide">Catatan di Bawah Tombol<textarea name="summary_note" rows="3" maxlength="500"><?= esc((string)$settings['summary_note']); ?></textarea></label>
                        <label class="wide">Teks Persetujuan Customer<textarea name="consent_text" rows="2" maxlength="260"><?= esc((string)$settings['consent_text']); ?></textarea></label>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="checkout-fields" hidden>
                    <div class="admin-check-grid">
                        <label><input type="checkbox" name="quantity_enabled" value="1" <?= !empty($settings['quantity_enabled']) ? 'checked' : ''; ?>> Tampilkan jumlah / quantity</label>
                        <label><input type="checkbox" name="planned_date_enabled" value="1" <?= !empty($settings['planned_date_enabled']) ? 'checked' : ''; ?>> Tampilkan rencana tanggal</label>
                        <label><input type="checkbox" name="need_enabled" value="1" <?= !empty($settings['need_enabled']) ? 'checked' : ''; ?>> Tampilkan jenis kebutuhan</label>
                        <label><input type="checkbox" name="location_enabled" value="1" <?= !empty($settings['location_enabled']) ? 'checked' : ''; ?>> Tampilkan lokasi / kota</label>
                        <label><input type="checkbox" name="payment_method_enabled" value="1" <?= !empty($settings['payment_method_enabled']) ? 'checked' : ''; ?>> Tampilkan preferensi pembayaran</label>
                        <label><input type="checkbox" name="notes_enabled" value="1" <?= !empty($settings['notes_enabled']) ? 'checked' : ''; ?>> Tampilkan catatan pesanan</label>
                    </div>
                    <div class="admin-info-box" style="margin-top:1rem">Nama dan nomor WhatsApp tetap wajib karena itu data minimal untuk follow-up order.</div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="checkout-shipping" hidden>
                    <div class="admin-check-grid">
                        <label><input type="checkbox" name="address_enabled" value="1" <?= !empty($settings['address_enabled']) ? 'checked' : ''; ?>> Tampilkan field alamat pengiriman</label>
                        <label><input type="checkbox" name="address_required" value="1" <?= !empty($settings['address_required']) ? 'checked' : ''; ?>> Alamat wajib untuk produk fisik</label>
                        <label><input type="checkbox" name="province_enabled" value="1" <?= !empty($settings['province_enabled']) ? 'checked' : ''; ?>> Tampilkan provinsi</label>
                        <label><input type="checkbox" name="city_enabled" value="1" <?= !empty($settings['city_enabled']) ? 'checked' : ''; ?>> Tampilkan kota/kabupaten</label>
                        <label><input type="checkbox" name="district_enabled" value="1" <?= !empty($settings['district_enabled']) ? 'checked' : ''; ?>> Tampilkan kecamatan</label>
                        <label><input type="checkbox" name="postal_code_enabled" value="1" <?= !empty($settings['postal_code_enabled']) ? 'checked' : ''; ?>> Tampilkan kode pos</label>
                        <label><input type="checkbox" name="shipping_method_enabled" value="1" <?= !empty($settings['shipping_method_enabled']) ? 'checked' : ''; ?>> Tampilkan metode pengiriman</label>
                        <label><input type="checkbox" name="shipping_method_required" value="1" <?= !empty($settings['shipping_method_required']) ? 'checked' : ''; ?>> Metode pengiriman wajib dipilih</label>
                    </div>
                    <div class="admin-info-box" style="margin-top:1rem">Produk digital, e-book, course, dan jasa tidak dipaksa masuk flow pengiriman fisik. Mesin ongkir manual memakai field alamat dan metode pengiriman ini.</div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="checkout-options" hidden>
                    <div class="admin-form-grid admin-form-row--3">
                        <label>Opsi Jenis Kebutuhan<small>Satu opsi per baris.</small><textarea name="need_options" rows="10"><?= esc($needText); ?></textarea></label>
                        <label>Opsi Lokasi / Area<small>Satu lokasi per baris.</small><textarea name="location_options" rows="10"><?= esc($locationText); ?></textarea></label>
                        <label>Opsi Metode Pengiriman<small>Satu metode per baris.</small><textarea name="shipping_method_options" rows="10"><?= esc($shippingText); ?></textarea></label>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="checkout-messages" hidden>
                    <div class="admin-form-grid admin-form-row--2">
                        <label class="wide">Pesan Sukses Setelah Checkout<textarea name="success_message" rows="4" maxlength="700"><?= esc((string)$settings['success_message']); ?></textarea></label>
                        <label>Template Pesan Admin<small>Placeholder: {order_ref}, {name}, {phone}, {product}, {quantity}, {shipping_address}, {shipping_method}, {shipping_cost}, {shipping_rule}, {shipping_eta}, {payment_method}, {invoice_total}, {message}</small><textarea name="admin_message_template" rows="12"><?= esc((string)$settings['admin_message_template']); ?></textarea></label>
                        <label>Template Pesan Customer<small>Placeholder juga mendukung {shipping_cost}, {invoice_total}, {order_status_url}, {invoice_url}, {site_name}</small><textarea name="customer_message_template" rows="12"><?= esc((string)$settings['customer_message_template']); ?></textarea></label>
                    </div>
                </section>

                <div class="admin-form-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan Checkout</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('checkout')); ?>" target="_blank" rel="noopener">Preview Public Checkout</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/shipping')); ?>">Atur Ongkir</a>
                </div>
            </form>

            <div class="admin-grid admin-grid--two">
                <div class="admin-card admin-editor">
                    <div class="admin-form-head"><span class="admin-badge">Preview Flow</span><h2>Alur Checkout Saat Ini</h2><p>Ringkasan praktis yang akan dipakai admin UMKM.</p></div>
                    <div class="admin-foundation-list">
                        <div><strong>1. Customer isi checkout</strong><span>Nama, WA, email, kebutuhan, alamat, pengiriman, pembayaran, dan catatan sesuai pengaturan.</span></div>
                        <div><strong>2. Order masuk dashboard</strong><span>Data baru tersimpan ke log order dan bisa dicari dari menu Order.</span></div>
                        <div><strong>3. Admin follow-up</strong><span>Template pesan siap membantu admin follow-up via WhatsApp/email.</span></div>
                        <div><strong>4. Invoice manual</strong><span>Jika order sudah cocok, admin lanjutkan invoice dan instruksi pembayaran.</span></div>
                    </div>
                </div>
                <div class="admin-card admin-editor">
                    <div class="admin-form-head"><span class="admin-badge">Next Bridge</span><h2>Disiapkan untuk Ongkir</h2><p>Field alamat dan metode pengiriman ini sudah tersambung ke Shipping/Ongkir Manual Engine.</p></div>
                    <div class="admin-foundation-list">
                        <div><strong>Asal Pengiriman</strong><span>Atur dari menu Shipping & Ongkir.</span></div>
                        <div><strong>Zona/Kota Tujuan</strong><span>Zona ongkir manual bisa memakai kota, kabupaten, kecamatan, atau keyword alamat customer.</span></div>
                        <div><strong>Produk Digital/Jasa</strong><span>Tetap aman karena tidak dipaksa mengisi alamat fisik.</span></div>
                        <div><strong>Invoice</strong><span>Order menyimpan alamat, metode kirim, ongkir, ETA, dan total estimasi untuk invoice/status.</span></div>
                    </div>
                </div>
            </div>

            <form method="post" class="admin-card" onsubmit="return confirm('Reset pengaturan checkout ke bawaan?');">
                <?= csrf_field(); ?>
                <input type="hidden" name="checkout_action" value="reset">
                <div class="admin-form-head admin-form-head--row">
                    <div><span class="admin-badge">Reset Aman</span><h2>Kembalikan Pengaturan Bawaan</h2><p>Gunakan hanya jika eksperimen field checkout ingin dibersihkan.</p></div>
                    <div class="admin-form-actions"><button class="admin-btn admin-btn--ghost" type="submit">Reset Checkout</button></div>
                </div>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
