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

        if ($action === 'save_product_profile') {
            $productId = (int)($_POST['profile_product_id'] ?? 0);
            $product = ($productId > 0 && function_exists('product_admin_find')) ? product_admin_find($productId) : null;
            if (!$product) {
                throw new RuntimeException('Produk/layanan untuk profil checkout tidak ditemukan.');
            }
            if (!function_exists('checkout_product_profile_from_post') || !function_exists('checkout_product_profile_save')) {
                throw new RuntimeException('Fitur profil checkout per produk belum tersedia.');
            }
            $profile = checkout_product_profile_from_post($_POST, $product);
            if (!checkout_product_profile_save($product, $profile)) {
                throw new RuntimeException('Profil checkout produk belum bisa disimpan. Cek permission folder storage.');
            }
            redirect_302('admin/form-checkout?profile_product_id=' . $productId . '&message=' . rawurlencode('Profil checkout khusus produk berhasil disimpan.') . '#checkout-product-profile');
        }

        if ($action === 'reset_product_profile') {
            $productId = (int)($_POST['profile_product_id'] ?? 0);
            $product = ($productId > 0 && function_exists('product_admin_find')) ? product_admin_find($productId) : null;
            if (!$product) {
                throw new RuntimeException('Produk/layanan untuk reset profil checkout tidak ditemukan.');
            }
            if (!function_exists('checkout_product_profile_delete') || !checkout_product_profile_delete($product)) {
                throw new RuntimeException('Profil checkout produk belum bisa dikembalikan ke global.');
            }
            redirect_302('admin/form-checkout?profile_product_id=' . $productId . '&message=' . rawurlencode('Profil checkout produk kembali mengikuti pengaturan global.') . '#checkout-product-profile');
        }

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

$checkoutProducts = function_exists('product_managed_products') ? product_managed_products() : (function_exists('all_products') ? all_products() : []);
usort($checkoutProducts, static fn(array $a, array $b): int => strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
$profileProductId = max(0, (int)($_GET['profile_product_id'] ?? 0));
$profileProduct = ($profileProductId > 0 && function_exists('product_admin_find')) ? product_admin_find($profileProductId) : null;
$profileSaved = ($profileProduct && function_exists('checkout_product_profile_for_product')) ? checkout_product_profile_for_product($profileProduct) : null;
$profilePresetRequest = strtolower(trim((string)($_GET['preset'] ?? '')));
$profilePresetOptions = function_exists('checkout_product_profile_presets') ? checkout_product_profile_presets() : [];
if ($profilePresetRequest !== '' && !array_key_exists($profilePresetRequest, $profilePresetOptions)) {
    $profilePresetRequest = '';
}
$profileSettings = null;
$profilePresetActive = 'custom';
if ($profileProduct) {
    if ($profilePresetRequest !== '' && !in_array($profilePresetRequest, ['global', 'custom'], true) && function_exists('checkout_product_profile_preset_settings')) {
        $profileSettings = checkout_product_profile_preset_settings($profilePresetRequest, $profileProduct);
        $profilePresetActive = $profilePresetRequest;
    } else {
        $profileSettings = function_exists('checkout_settings_for_product') ? checkout_settings_for_product($profileProduct) : $settings;
        $profilePresetActive = is_array($profileSaved) ? (string)($profileSaved['preset'] ?? 'custom') : 'custom';
    }
}
$profileNeedText = $profileSettings ? implode("\n", (array)($profileSettings['need_options'] ?? [])) : '';
$profileLocationText = $profileSettings ? implode("\n", (array)($profileSettings['location_options'] ?? [])) : '';
$profileShippingText = $profileSettings ? implode("\n", (array)($profileSettings['shipping_method_options'] ?? [])) : '';

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
                        <label class="admin-toggle-option"><input type="checkbox" name="email_enabled" value="1" <?= !empty($settings['email_enabled']) ? 'checked' : ''; ?>> Tampilkan field email</label>
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
                        <label><input type="checkbox" name="planned_date_required" value="1" <?= !empty($settings['planned_date_required']) ? 'checked' : ''; ?>> Rencana tanggal wajib</label>
                        <label><input type="checkbox" name="need_enabled" value="1" <?= !empty($settings['need_enabled']) ? 'checked' : ''; ?>> Tampilkan jenis kebutuhan</label>
                        <label><input type="checkbox" name="need_required" value="1" <?= !empty($settings['need_required']) ? 'checked' : ''; ?>> Jenis kebutuhan wajib</label>
                        <label><input type="checkbox" name="location_enabled" value="1" <?= !empty($settings['location_enabled']) ? 'checked' : ''; ?>> Tampilkan lokasi / kota</label>
                        <label><input type="checkbox" name="location_required" value="1" <?= !empty($settings['location_required']) ? 'checked' : ''; ?>> Lokasi / kota wajib</label>
                        <label><input type="checkbox" name="payment_method_enabled" value="1" <?= !empty($settings['payment_method_enabled']) ? 'checked' : ''; ?>> Tampilkan preferensi pembayaran</label>
                        <label><input type="checkbox" name="payment_method_required" value="1" <?= !empty($settings['payment_method_required']) ? 'checked' : ''; ?>> Preferensi pembayaran wajib</label>
                        <label><input type="checkbox" name="notes_enabled" value="1" <?= !empty($settings['notes_enabled']) ? 'checked' : ''; ?>> Tampilkan catatan pesanan</label>
                        <label><input type="checkbox" name="notes_required" value="1" <?= !empty($settings['notes_required']) ? 'checked' : ''; ?>> Catatan pesanan wajib</label>
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

            <div class="admin-card admin-editor" id="checkout-product-profile">
                <div class="admin-form-head">
                    <span class="admin-badge">Checkout Per Produk / Layanan</span>
                    <h2>Profil Form Checkout Khusus Item</h2>
                    <p>Pengaturan global tetap menjadi fallback. Pilih item lalu gunakan preset Produk Fisik, Digital, Jasa/Layanan, Booking, atau atur field secara custom.</p>
                </div>

                <form method="get" class="admin-form-grid admin-form-row--2" action="<?= esc(url('admin/form-checkout')); ?>">
                    <label>
                        Pilih Produk / Layanan
                        <select name="profile_product_id" required>
                            <option value="">Pilih item...</option>
                            <?php foreach ($checkoutProducts as $checkoutProduct): ?>
                                <?php $checkoutProductId = (int)($checkoutProduct['id'] ?? 0); ?>
                                <?php if ($checkoutProductId <= 0) { continue; } ?>
                                <option value="<?= $checkoutProductId; ?>" <?= $profileProductId === $checkoutProductId ? 'selected' : ''; ?>>
                                    <?= esc((string)($checkoutProduct['title'] ?? 'Item')); ?> · <?= esc(function_exists('product_item_type_label') ? product_item_type_label($checkoutProduct) : (string)($checkoutProduct['type'] ?? 'Item')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="admin-form-actions" style="align-self:end">
                        <button class="admin-btn admin-btn--primary" type="submit">Buka Profil Checkout</button>
                    </div>
                </form>

                <?php if ($profileProduct && $profileSettings): ?>
                    <div class="admin-info-box" style="margin-top:1rem">
                        <strong><?= esc((string)($profileProduct['title'] ?? 'Item')); ?></strong>
                        · <?= esc(function_exists('product_item_type_label') ? product_item_type_label($profileProduct) : (string)($profileProduct['type'] ?? 'Item')); ?>
                        · <?= $profileSaved ? 'Override checkout aktif' : 'Saat ini mengikuti checkout global'; ?>
                    </div>

                    <div class="admin-form-actions" style="margin-top:1rem;flex-wrap:wrap">
                        <?php foreach (['physical' => 'Preset Produk Fisik', 'digital' => 'Preset Produk Digital', 'service' => 'Preset Jasa / Layanan', 'booking' => 'Preset Booking'] as $presetKey => $presetLabel): ?>
                            <a class="admin-btn <?= $profilePresetRequest === $presetKey ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= esc(url('admin/form-checkout?profile_product_id=' . $profileProductId . '&preset=' . $presetKey . '#checkout-product-profile')); ?>"><?= esc($presetLabel); ?></a>
                        <?php endforeach; ?>
                    </div>

                    <form method="post" class="admin-card admin-nested-card" style="margin-top:1rem">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="checkout_action" value="save_product_profile">
                        <input type="hidden" name="profile_product_id" value="<?= $profileProductId; ?>">
                        <input type="hidden" name="profile_preset" value="<?= esc($profilePresetActive); ?>">

                        <div class="admin-form-grid admin-form-row--2">
                            <label>Judul Checkout<input name="headline" value="<?= esc((string)($profileSettings['headline'] ?? '')); ?>" maxlength="140"></label>
                            <label>Label Tombol<input name="button_label" value="<?= esc((string)($profileSettings['button_label'] ?? '')); ?>" maxlength="80"></label>
                            <label class="wide">Intro Checkout<textarea name="intro" rows="3" maxlength="600"><?= esc((string)($profileSettings['intro'] ?? '')); ?></textarea></label>
                            <label class="wide">Catatan di Bawah Tombol<textarea name="summary_note" rows="2" maxlength="500"><?= esc((string)($profileSettings['summary_note'] ?? '')); ?></textarea></label>
                            <label class="wide">Teks Persetujuan<textarea name="consent_text" rows="2" maxlength="260"><?= esc((string)($profileSettings['consent_text'] ?? '')); ?></textarea></label>
                        </div>

                        <h3 style="margin-top:1.25rem">Field Customer & Order</h3>
                        <div class="admin-check-grid">
                            <label><input type="checkbox" name="email_enabled" value="1" <?= !empty($profileSettings['email_enabled']) ? 'checked' : ''; ?>> Tampilkan email</label>
                            <label><input type="checkbox" name="email_required" value="1" <?= !empty($profileSettings['email_required']) ? 'checked' : ''; ?>> Email wajib</label>
                            <label><input type="checkbox" name="quantity_enabled" value="1" <?= !empty($profileSettings['quantity_enabled']) ? 'checked' : ''; ?>> Tampilkan jumlah</label>
                            <label><input type="checkbox" name="planned_date_enabled" value="1" <?= !empty($profileSettings['planned_date_enabled']) ? 'checked' : ''; ?>> Tampilkan rencana tanggal</label>
                            <label><input type="checkbox" name="planned_date_required" value="1" <?= !empty($profileSettings['planned_date_required']) ? 'checked' : ''; ?>> Tanggal wajib</label>
                            <label><input type="checkbox" name="need_enabled" value="1" <?= !empty($profileSettings['need_enabled']) ? 'checked' : ''; ?>> Tampilkan jenis kebutuhan</label>
                            <label><input type="checkbox" name="need_required" value="1" <?= !empty($profileSettings['need_required']) ? 'checked' : ''; ?>> Kebutuhan wajib</label>
                            <label><input type="checkbox" name="location_enabled" value="1" <?= !empty($profileSettings['location_enabled']) ? 'checked' : ''; ?>> Tampilkan lokasi / kota</label>
                            <label><input type="checkbox" name="location_required" value="1" <?= !empty($profileSettings['location_required']) ? 'checked' : ''; ?>> Lokasi wajib</label>
                            <label><input type="checkbox" name="payment_method_enabled" value="1" <?= !empty($profileSettings['payment_method_enabled']) ? 'checked' : ''; ?>> Tampilkan pembayaran</label>
                            <label><input type="checkbox" name="payment_method_required" value="1" <?= !empty($profileSettings['payment_method_required']) ? 'checked' : ''; ?>> Pembayaran wajib</label>
                            <label><input type="checkbox" name="notes_enabled" value="1" <?= !empty($profileSettings['notes_enabled']) ? 'checked' : ''; ?>> Tampilkan catatan</label>
                            <label><input type="checkbox" name="notes_required" value="1" <?= !empty($profileSettings['notes_required']) ? 'checked' : ''; ?>> Catatan wajib</label>
                        </div>

                        <h3 style="margin-top:1.25rem">Alamat & Pengiriman</h3>
                        <div class="admin-check-grid">
                            <label><input type="checkbox" name="address_enabled" value="1" <?= !empty($profileSettings['address_enabled']) ? 'checked' : ''; ?>> Tampilkan alamat</label>
                            <label><input type="checkbox" name="address_required" value="1" <?= !empty($profileSettings['address_required']) ? 'checked' : ''; ?>> Alamat wajib</label>
                            <label><input type="checkbox" name="province_enabled" value="1" <?= !empty($profileSettings['province_enabled']) ? 'checked' : ''; ?>> Provinsi</label>
                            <label><input type="checkbox" name="city_enabled" value="1" <?= !empty($profileSettings['city_enabled']) ? 'checked' : ''; ?>> Kota/Kabupaten</label>
                            <label><input type="checkbox" name="district_enabled" value="1" <?= !empty($profileSettings['district_enabled']) ? 'checked' : ''; ?>> Kecamatan</label>
                            <label><input type="checkbox" name="postal_code_enabled" value="1" <?= !empty($profileSettings['postal_code_enabled']) ? 'checked' : ''; ?>> Kode pos</label>
                            <label><input type="checkbox" name="shipping_method_enabled" value="1" <?= !empty($profileSettings['shipping_method_enabled']) ? 'checked' : ''; ?>> Metode pengiriman</label>
                            <label><input type="checkbox" name="shipping_method_required" value="1" <?= !empty($profileSettings['shipping_method_required']) ? 'checked' : ''; ?>> Pengiriman wajib</label>
                        </div>

                        <div class="admin-form-grid admin-form-row--3" style="margin-top:1.25rem">
                            <label>Opsi Jenis Kebutuhan<small>Satu opsi per baris.</small><textarea name="need_options" rows="8"><?= esc($profileNeedText); ?></textarea></label>
                            <label>Opsi Lokasi / Area<small>Satu opsi per baris.</small><textarea name="location_options" rows="8"><?= esc($profileLocationText); ?></textarea></label>
                            <label>Opsi Metode Pengiriman<small>Dipakai hanya jika shipping aktif.</small><textarea name="shipping_method_options" rows="8"><?= esc($profileShippingText); ?></textarea></label>
                        </div>

                        <div class="admin-form-actions" style="margin-top:1rem">
                            <button class="admin-btn admin-btn--primary" type="submit">Simpan Profil Item</button>
                            <?php if (!empty($profileProduct['slug'])): ?>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(url('checkout?produk=' . rawurlencode((string)$profileProduct['slug']))); ?>" target="_blank" rel="noopener">Preview Checkout Item</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if ($profileSaved): ?>
                        <form method="post" class="admin-form-actions" style="margin-top:.75rem" onsubmit="return confirm('Kembalikan item ini ke form checkout global?');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="checkout_action" value="reset_product_profile">
                            <input type="hidden" name="profile_product_id" value="<?= $profileProductId; ?>">
                            <button class="admin-btn admin-btn--ghost" type="submit">Hapus Override / Ikuti Global</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="admin-info-box" style="margin-top:1rem">Pilih produk atau layanan untuk membuat form checkout khusus item.</div>
                <?php endif; ?>
            </div>

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
