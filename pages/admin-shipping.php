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
        $action = (string)($_POST['shipping_action'] ?? 'save');
        if ($action === 'save') {
            $settings = shipping_settings_from_post($_POST);
            if (!shipping_save_settings($settings)) {
                throw new RuntimeException('Pengaturan ongkir belum bisa disimpan. Cek permission folder storage.');
            }
            redirect_302('admin/shipping?message=' . rawurlencode('Pengaturan ongkir berhasil disimpan.'));
        }
        if ($action === 'reset') {
            if (!shipping_save_settings(shipping_default_settings())) {
                throw new RuntimeException('Pengaturan ongkir belum bisa direset. Cek permission folder storage.');
            }
            redirect_302('admin/shipping?message=' . rawurlencode('Pengaturan ongkir dikembalikan ke bawaan.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = shipping_settings();
$originLocations = array_values((array)($settings['origin_locations'] ?? []));
while (count($originLocations) < 5) {
    $originLocations[] = ['active' => false, 'default' => false, 'id' => '', 'name' => '', 'city' => '', 'province' => '', 'api_origin_code' => '', 'api_origin_label' => '', 'keywords' => [], 'note' => ''];
}
$originLocations = array_slice($originLocations, 0, 10);
$rules = array_values((array)($settings['rules'] ?? []));
while (count($rules) < 6) {
    $rules[] = ['active' => false, 'name' => '', 'keywords' => [], 'base_cost' => 0, 'per_kg' => 0, 'eta' => '', 'note' => ''];
}
$rules = array_slice($rules, 0, 12);

$destinationRules = array_values((array)($settings['api_destination_rules'] ?? []));
while (count($destinationRules) < 8) {
    $destinationRules[] = ['active' => false, 'label' => '', 'keywords' => [], 'code' => '', 'provider' => (string)($settings['api_provider'] ?? 'rajaongkir')];
}
$destinationRules = array_slice($destinationRules, 0, 16);

$activeRules = count(array_filter((array)$settings['rules'], static fn($rule): bool => !empty($rule['active'])));
$activeDestinationRules = count(array_filter((array)$settings['api_destination_rules'], static fn($rule): bool => !empty($rule['active'])));
$activeOrigins = count(array_filter((array)$settings['origin_locations'], static fn($rule): bool => !empty($rule['active'])));
$apiReady = function_exists('shipping_api_any_origin_ready') ? shipping_api_any_origin_ready($settings) : shipping_api_ready($settings);
$shippingMode = (string)($settings['shipping_mode'] ?? 'manual');
$provider = (string)($settings['api_provider'] ?? 'rajaongkir');
$providerLabels = [
    'rajaongkir' => 'RajaOngkir Starter',
    'komerce' => 'RajaOngkir Komerce',
    'api_co_id' => 'API.co.id',
    'binderbyte' => 'BinderByte',
    'custom' => 'Custom Provider',
];

$testCity = shipping_clean((string)($_GET['test_city'] ?? 'Depok'), 120) ?: 'Depok';
$testQty = max(1, min(99, (int)($_GET['test_qty'] ?? 1)));
$testPrice = shipping_money_int($_GET['test_price'] ?? 100000);
$testEstimate = shipping_estimate([
    'city' => $testCity,
    'location' => $testCity,
    'quantity' => $testQty,
    'price' => $testPrice,
    'shipping_method' => 'Kirim Kurir / Ekspedisi',
    'shipping_required' => '1',
]);
$pickupKeywords = implode(', ', (array)($settings['pickup_keywords'] ?? []));
$apiCouriers = implode(', ', (array)($settings['api_couriers'] ?? []));

set_seo([
    'title' => 'Shipping & Ongkir API Bridge - ' . SITE_NAME,
    'description' => 'Pengaturan ongkir manual, mode API, mode hybrid, provider, cache, rate limit, fallback, dan estimasi ongkir checkout.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-shipping-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Commerce Shipping</div>
                <h1>Multi-Origin Shipping & Warehouse Routing</h1>
                <p>Atur mode manual/API/hybrid, multi gudang, cache ongkir, dan routing asal pengiriman per produk agar estimasi ongkir makin akurat.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/form-checkout')); ?>">Form Checkout</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('checkout')); ?>" target="_blank" rel="noopener">Tes Checkout</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Mode Ongkir</span><h2><?= esc(strtoupper($shippingMode)); ?></h2><p><?= $shippingMode === 'hybrid' ? 'API dulu, fallback manual jika gagal.' : ($shippingMode === 'api' ? 'Fokus tarif provider API.' : 'Manual rule-based tanpa API.'); ?></p></div>
                <div class="admin-card"><span class="admin-badge">Provider</span><h2><?= esc((string)($providerLabels[$provider] ?? $provider)); ?></h2><p><?= $apiReady ? 'API siap dipakai.' : 'Lengkapi API key, kode asal/gudang, dan mapping tujuan.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Asal/Gudang</span><h2><?= (int)$activeOrigins; ?></h2><p>Produk bisa diarahkan dari gudang/kota berbeda.</p></div>
                <div class="admin-card"><span class="admin-badge">Zona Manual</span><h2><?= (int)$activeRules; ?></h2><p>Fallback dan estimasi manual checkout.</p></div>
                <div class="admin-card"><span class="admin-badge">Mapping Tujuan</span><h2><?= (int)$activeDestinationRules; ?></h2><p>Kota/kecamatan → kode provider agar customer tidak perlu tahu kode wilayah.</p></div>
            </div>

            <form method="post" class="admin-card admin-editor" data-admin-page-tab-scope>
                <?= csrf_field(); ?>
                <input type="hidden" name="shipping_action" value="save">
                <div class="admin-form-head admin-form-head--row">
                    <div>
                        <span class="admin-badge">Ongkir Engine</span>
                        <h2>Pengaturan Shipping</h2>
                        <p>Manual tetap aman untuk semua hosting. API dan Hybrid bisa dipakai saat toko butuh tarif ekspedisi lebih real-time.</p>
                    </div>
                    <div class="admin-form-actions admin-form-actions--inline">
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Ongkir</button>
                    </div>
                </div>

                <div class="admin-page-subtabs admin-page-subtabs--6" role="tablist" aria-label="Bagian Ongkir">
                    <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="shipping-basic"><span>1. Dasar</span><small>Mode umum</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="shipping-origins"><span>2. Gudang</span><small>Asal produk</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="shipping-api"><span>3. API</span><small>Provider</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="shipping-map"><span>4. Mapping</span><small>Kode tujuan</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="shipping-rules"><span>5. Manual</span><small>Zona fallback</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="shipping-cache"><span>6. Cache</span><small>Kuota aman</small></button>
                </div>
                <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian ongkir</span><select data-admin-page-tab-select aria-label="Pilih bagian ongkir"><option value="shipping-basic">1. Dasar</option><option value="shipping-origins">2. Gudang</option><option value="shipping-api">3. API</option><option value="shipping-map">4. Mapping</option><option value="shipping-rules">5. Manual</option><option value="shipping-cache">6. Cache</option></select></label></div>

                <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="shipping-basic">
                    <div class="admin-form-grid admin-form-row--3">
                        <label class="admin-toggle-option"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan engine ongkir</label>
                        <label class="admin-toggle-option"><input type="checkbox" name="checkout_preview_enabled" value="1" <?= !empty($settings['checkout_preview_enabled']) ? 'checked' : ''; ?>> Tampilkan estimasi di checkout</label>
                        <label class="admin-toggle-option"><input type="checkbox" name="round_weight_up" value="1" <?= !empty($settings['round_weight_up']) ? 'checked' : ''; ?>> Bulatkan berat ke atas</label>
                        <label>Mode Ongkir<select name="shipping_mode"><option value="manual" <?= $shippingMode === 'manual' ? 'selected' : ''; ?>>Manual saja</option><option value="api" <?= $shippingMode === 'api' ? 'selected' : ''; ?>>API saja</option><option value="hybrid" <?= $shippingMode === 'hybrid' ? 'selected' : ''; ?>>Hybrid: API + fallback manual</option></select></label>
                        <label>Asal Global Fallback<input name="origin_city" value="<?= esc((string)$settings['origin_city']); ?>" maxlength="100" placeholder="Depok"><small>Dipakai jika multi gudang dimatikan atau produk belum cocok.</small></label>
                        <label>Provinsi Global<input name="origin_province" value="<?= esc((string)$settings['origin_province']); ?>" maxlength="100" placeholder="Jawa Barat"></label>
                        <label>Mode Pilih Gudang<select name="origin_selection_mode"><option value="product_first" <?= (($settings['origin_selection_mode'] ?? 'product_first') === 'product_first') ? 'selected' : ''; ?>>Produk dulu, lalu auto keyword</option><option value="keyword_auto" <?= (($settings['origin_selection_mode'] ?? '') === 'keyword_auto') ? 'selected' : ''; ?>>Auto keyword saja</option><option value="global_only" <?= (($settings['origin_selection_mode'] ?? '') === 'global_only') ? 'selected' : ''; ?>>Global fallback saja</option></select></label>
                        <label>Berat Default Produk <small>kg, dipakai jika field berat kosong/tidak terbaca</small><input name="default_weight_kg" value="<?= esc((string)$settings['default_weight_kg']); ?>" inputmode="decimal" placeholder="1"></label>
                        <label class="wide">Catatan Public Ongkir<textarea name="public_note" rows="3" maxlength="500"><?= esc((string)$settings['public_note']); ?></textarea></label>
                        <label class="admin-toggle-option"><input type="checkbox" name="multi_origin_enabled" value="1" <?= !empty($settings['multi_origin_enabled']) ? 'checked' : ''; ?>> Aktifkan multi gudang / multi asal pengiriman</label>
                    </div>
                    <div class="admin-info-box">Best practice: pakai <b>Hybrid</b> untuk toko produk fisik. Jika API limit/down, customer tetap dapat estimasi dari manual ongkir.</div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="shipping-origins" hidden>
                    <div class="admin-info-box">Roadmap multi-origin sudah masuk di sini: satu toko bisa punya produk dari gudang/kota berbeda. Pilih gudang default, isi kode asal API per gudang, lalu di menu Produk pilih gudang asal per item.</div>
                    <div class="admin-shipping-rule-list">
                        <?php foreach ($originLocations as $index => $origin): ?>
                            <div class="admin-card admin-shipping-rule">
                                <div class="admin-form-grid admin-form-row--3">
                                    <label class="admin-toggle-option"><input type="checkbox" name="origin_location_active[<?= (int)$index; ?>]" value="1" <?= !empty($origin['active']) ? 'checked' : ''; ?>> Gudang aktif</label>
                                    <label class="admin-toggle-option"><input type="radio" name="origin_location_default" value="<?= (int)$index; ?>" <?= !empty($origin['default']) ? 'checked' : ''; ?>> Jadikan default</label>
                                    <label>ID Gudang<input name="origin_location_id[]" value="<?= esc((string)($origin['id'] ?? '')); ?>" maxlength="80" placeholder="gudang-depok"><small>Biarkan otomatis jika belum paham.</small></label>
                                    <label>Nama Gudang<input name="origin_location_name[]" value="<?= esc((string)($origin['name'] ?? '')); ?>" maxlength="120" placeholder="Gudang Depok"></label>
                                    <label>Kota/Kabupaten Asal<input name="origin_location_city[]" value="<?= esc((string)($origin['city'] ?? '')); ?>" maxlength="100" placeholder="Depok"></label>
                                    <label>Provinsi Asal<input name="origin_location_province[]" value="<?= esc((string)($origin['province'] ?? '')); ?>" maxlength="100" placeholder="Jawa Barat"></label>
                                    <label>Kode Asal API<input name="origin_location_api_code[]" value="<?= esc((string)($origin['api_origin_code'] ?? '')); ?>" maxlength="80" placeholder="city id / district code / village code"></label>
                                    <label>Label Asal API<input name="origin_location_api_label[]" value="<?= esc((string)($origin['api_origin_label'] ?? '')); ?>" maxlength="120" placeholder="Depok"></label>
                                    <label class="wide">Keyword Auto Routing<textarea name="origin_location_keywords[]" rows="2" placeholder="depok, gudang depok, produk area depok"><?= esc(implode(', ', (array)($origin['keywords'] ?? []))); ?></textarea></label>
                                    <label class="wide">Catatan Gudang<input name="origin_location_note[]" value="<?= esc((string)($origin['note'] ?? '')); ?>" maxlength="220" placeholder="Contoh: dipakai untuk produk ready stock area Depok."></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="shipping-api" hidden>
                    <div class="admin-form-grid admin-form-row--3">
                        <label class="admin-toggle-option"><input type="checkbox" name="api_enabled" value="1" <?= !empty($settings['api_enabled']) ? 'checked' : ''; ?>> Aktifkan API ongkir</label>
                        <label>Provider<select name="api_provider"><option value="rajaongkir" <?= $provider === 'rajaongkir' ? 'selected' : ''; ?>>RajaOngkir Starter</option><option value="komerce" <?= $provider === 'komerce' ? 'selected' : ''; ?>>RajaOngkir Komerce</option><option value="api_co_id" <?= $provider === 'api_co_id' ? 'selected' : ''; ?>>API.co.id</option><option value="binderbyte" <?= $provider === 'binderbyte' ? 'selected' : ''; ?>>BinderByte</option><option value="custom" <?= $provider === 'custom' ? 'selected' : ''; ?>>Custom Provider</option></select></label>
                        <label>API Key <small><?= esc(shipping_mask_secret((string)($settings['api_key'] ?? ''))); ?></small><input type="password" name="api_key" value="" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti"></label>
                        <label class="wide">Endpoint Custom <small>opsional; kosongkan untuk default provider</small><input name="api_base_url" value="<?= esc((string)$settings['api_base_url']); ?>" placeholder="https://..." maxlength="240"></label>
                        <label>Kode Asal Global Provider <small>fallback jika gudang belum punya kode</small><input name="api_origin_code" value="<?= esc((string)$settings['api_origin_code']); ?>" maxlength="80" placeholder="Contoh: 115"></label>
                        <label>Label Asal Global API<input name="api_origin_label" value="<?= esc((string)$settings['api_origin_label']); ?>" maxlength="120" placeholder="Depok"></label>
                        <label class="wide">Kurir API <small>pisahkan koma</small><input name="api_couriers" value="<?= esc($apiCouriers); ?>" placeholder="jne, jnt, sicepat, pos, tiki, anteraja"></label>
                    </div>
                    <div class="admin-foundation-list" style="margin-top:1rem">
                        <div><strong>Status API</strong><span><?= $apiReady ? 'Siap dipakai di checkout.' : 'Belum siap. API key, mode API/Hybrid, dan minimal kode asal global/gudang harus lengkap.'; ?></span></div>
                        <div><strong>API key aman</strong><span>Key disimpan di server/storage dan tidak pernah dikirim ke JavaScript public.</span></div>
                        <div><strong>Provider code</strong><span>Setiap provider punya kode wilayah sendiri. Mapping tujuan membantu customer cukup mengetik kota/kecamatan.</span></div>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="shipping-map" hidden>
                    <div class="admin-info-box">Isi mapping kota/kecamatan ke kode tujuan provider. Contoh: label <b>Depok</b>, keyword <b>depok, sukmajaya, cimanggis</b>, kode <b>115</b> untuk RajaOngkir city id. Sesuaikan kode dengan provider yang dipakai.</div>
                    <div class="admin-shipping-rule-list">
                        <?php foreach ($destinationRules as $index => $rule): ?>
                            <div class="admin-card admin-shipping-rule">
                                <div class="admin-form-grid admin-form-row--3">
                                    <label class="admin-toggle-option"><input type="checkbox" name="api_destination_active[<?= (int)$index; ?>]" value="1" <?= !empty($rule['active']) ? 'checked' : ''; ?>> Mapping aktif</label>
                                    <label>Label Tujuan<input name="api_destination_label[]" value="<?= esc((string)($rule['label'] ?? '')); ?>" placeholder="Depok" maxlength="120"></label>
                                    <label>Kode Tujuan Provider<input name="api_destination_code[]" value="<?= esc((string)($rule['code'] ?? '')); ?>" placeholder="city id / district code / village code" maxlength="80"></label>
                                    <label class="wide">Keyword Customer<textarea name="api_destination_keywords[]" rows="2" placeholder="depok, sukmajaya, cimanggis, sawangan"><?= esc(implode(', ', (array)($rule['keywords'] ?? []))); ?></textarea></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="shipping-rules" hidden>
                    <div class="admin-info-box">Zona manual tetap dipakai untuk mode Manual dan fallback Hybrid. Keyword boleh lebih dari satu, pisahkan koma.</div>
                    <div class="admin-shipping-rule-list">
                        <?php foreach ($rules as $index => $rule): ?>
                            <div class="admin-card admin-shipping-rule">
                                <div class="admin-form-grid admin-form-row--3">
                                    <label class="admin-toggle-option"><input type="checkbox" name="rule_active[<?= (int)$index; ?>]" value="1" <?= !empty($rule['active']) ? 'checked' : ''; ?>> Zona aktif</label>
                                    <label>Nama Zona<input name="rule_name[]" value="<?= esc((string)($rule['name'] ?? '')); ?>" placeholder="Jabodetabek Ring 1" maxlength="100"></label>
                                    <label>Keyword Kota<textarea name="rule_keywords[]" rows="2" placeholder="jakarta, depok, bogor"><?= esc(implode(', ', (array)($rule['keywords'] ?? []))); ?></textarea></label>
                                    <label>Biaya Dasar<input name="rule_base_cost[]" value="<?= esc((string)((int)($rule['base_cost'] ?? 0))); ?>" inputmode="numeric" placeholder="15000"></label>
                                    <label>Tarif per kg<input name="rule_per_kg[]" value="<?= esc((string)((int)($rule['per_kg'] ?? 0))); ?>" inputmode="numeric" placeholder="4000"></label>
                                    <label>Estimasi Tiba<input name="rule_eta[]" value="<?= esc((string)($rule['eta'] ?? '')); ?>" maxlength="80" placeholder="1-2 hari"></label>
                                    <label class="wide">Catatan Zona<input name="rule_note[]" value="<?= esc((string)($rule['note'] ?? '')); ?>" maxlength="220" placeholder="Estimasi awal, admin bisa validasi ulang sebelum invoice."></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="shipping-cache" hidden>
                    <div class="admin-form-grid admin-form-row--3">
                        <label class="admin-toggle-option"><input type="checkbox" name="api_session_cache_enabled" value="1" <?= !empty($settings['api_session_cache_enabled']) ? 'checked' : ''; ?>> Aktifkan session cache</label>
                        <label class="admin-toggle-option"><input type="checkbox" name="api_fallback_to_manual" value="1" <?= !empty($settings['api_fallback_to_manual']) ? 'checked' : ''; ?>> Fallback manual jika API gagal</label>
                        <label class="admin-toggle-option"><input type="checkbox" name="fallback_enabled" value="1" <?= !empty($settings['fallback_enabled']) ? 'checked' : ''; ?>> Pakai fallback manual jika kota belum cocok</label>
                        <label>TTL Cache Sukses <small>menit</small><input name="api_cache_ttl_minutes" value="<?= esc((string)((int)$settings['api_cache_ttl_minutes'])); ?>" inputmode="numeric" placeholder="720"></label>
                        <label>TTL Cache Error <small>menit</small><input name="api_error_cache_minutes" value="<?= esc((string)((int)$settings['api_error_cache_minutes'])); ?>" inputmode="numeric" placeholder="10"></label>
                        <label>Rate Limit/session/jam<input name="api_rate_limit_per_hour" value="<?= esc((string)((int)$settings['api_rate_limit_per_hour'])); ?>" inputmode="numeric" placeholder="20"></label>
                        <label>Timeout API <small>detik</small><input name="api_timeout_seconds" value="<?= esc((string)((int)$settings['api_timeout_seconds'])); ?>" inputmode="numeric" placeholder="8"></label>
                        <label>Handling Fee <small>opsional, masuk ke ongkir</small><input name="handling_fee" value="<?= esc((string)((int)$settings['handling_fee'])); ?>" inputmode="numeric" placeholder="0"></label>
                        <label>Free Shipping Threshold <small>0 = nonaktif</small><input name="free_shipping_threshold" value="<?= esc((string)((int)$settings['free_shipping_threshold'])); ?>" inputmode="numeric" placeholder="0"></label>
                        <label>Fallback Biaya Dasar<input name="fallback_base_cost" value="<?= esc((string)((int)$settings['fallback_base_cost'])); ?>" inputmode="numeric" placeholder="25000"></label>
                        <label>Fallback Tarif per kg<input name="fallback_per_kg" value="<?= esc((string)((int)$settings['fallback_per_kg'])); ?>" inputmode="numeric" placeholder="5000"></label>
                        <label>Fallback ETA<input name="fallback_eta" value="<?= esc((string)$settings['fallback_eta']); ?>" maxlength="80" placeholder="Konfirmasi admin"></label>
                        <label class="wide">Keyword metode tanpa ongkir <small>pisahkan koma, contoh: ambil, pickup, digital</small><textarea name="pickup_keywords" rows="3"><?= esc($pickupKeywords); ?></textarea></label>
                        <label class="admin-toggle-option"><input type="checkbox" name="api_debug_log" value="1" <?= !empty($settings['api_debug_log']) ? 'checked' : ''; ?>> Simpan catatan tes API <small>aktifkan hanya saat tes</small></label>
                    </div>
                    <div class="admin-foundation-list" style="margin-top:1rem">
                        <div><strong>Cache key</strong><span>Provider + asal + tujuan + kurir + berat. Jadi request yang sama tidak bolak-balik tembak API.</span></div>
                        <div><strong>Error cache</strong><span>Jika API limit/down, error disimpan sebentar agar tidak spam hit.</span></div>
                        <div><strong>Order snapshot</strong><span>Hasil pilihan ekspedisi disimpan ke order sehingga invoice tidak berubah-ubah.</span></div>
                    </div>
                </section>

                <div class="admin-form-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan Ongkir</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('checkout')); ?>" target="_blank" rel="noopener">Preview Checkout</a>
                </div>
            </form>

            <div class="admin-grid admin-grid--two">
                <div class="admin-card admin-editor">
                    <div class="admin-form-head"><span class="admin-badge">Simulator</span><h2>Cek Estimasi Cepat</h2><p>Coba kota, quantity, dan subtotal. Jika API siap dan mapping cocok, hasil bisa dari API/cache; jika tidak, fallback manual.</p></div>
                    <form method="get" class="admin-form-grid admin-form-row--3">
                        <label>Kota Tujuan<input name="test_city" value="<?= esc($testCity); ?>" placeholder="Depok"></label>
                        <label>Quantity<input name="test_qty" value="<?= esc((string)$testQty); ?>" inputmode="numeric"></label>
                        <label>Harga Produk<input name="test_price" value="<?= esc((string)$testPrice); ?>" inputmode="numeric"></label>
                        <div class="admin-form-actions"><button class="admin-btn admin-btn--soft" type="submit">Hitung Simulasi</button></div>
                    </form>
                    <div class="admin-foundation-list" style="margin-top:1rem">
                        <div><strong>Sumber</strong><span><?= esc((string)($testEstimate['quote_source'] ?? 'manual')); ?><?= !empty($testEstimate['provider']) ? ' · ' . esc((string)$testEstimate['provider']) : ''; ?><?= !empty($testEstimate['cache_status']) && $testEstimate['cache_status'] !== 'none' ? ' · ' . esc((string)$testEstimate['cache_status']) : ''; ?></span></div>
                        <div><strong>Layanan/Zona</strong><span><?= esc((string)($testEstimate['service_label'] ?: $testEstimate['rule_name'] ?: 'Konfirmasi manual')); ?></span></div>
                        <div><strong>Berat tagihan</strong><span><?= esc((string)$testEstimate['billable_weight_kg']); ?> kg</span></div>
                        <div><strong>Ongkir</strong><span><?= esc(rupiah((int)$testEstimate['total'])); ?><?= !empty($testEstimate['free_shipping_applied']) ? ' · free ongkir aktif' : ''; ?></span></div>
                        <div><strong>ETA</strong><span><?= esc((string)($testEstimate['eta'] ?: 'Konfirmasi admin')); ?></span></div>
                        <?php if (!empty($testEstimate['api_error_note'])): ?><div><strong>Catatan API</strong><span><?= esc((string)$testEstimate['api_error_note']); ?></span></div><?php endif; ?>
                    </div>
                </div>
                <div class="admin-card admin-editor">
                    <div class="admin-form-head"><span class="admin-badge">Checkout Bridge</span><h2>Yang Otomatis Nyambung</h2><p>Setting ini langsung dipakai di checkout, order, invoice, dan template pesan.</p></div>
                    <div class="admin-foundation-list">
                        <div><strong>Cek Ongkir Button</strong><span>Customer klik tombol cek ongkir. Sistem tidak menembak API saat user baru mengetik.</span></div>
                        <div><strong>Smart Cache</strong><span>Hasil ongkir tersimpan di session dan file cache sesuai TTL.</span></div>
                        <div><strong>Hybrid Fallback</strong><span>Jika API gagal/limit, engine manual tetap memberi estimasi awal.</span></div>
                        <div><strong>Multi-Origin</strong><span>Produk bisa memakai gudang asal berbeda, lalu origin tersebut ikut masuk cache/API.</span></div>
                        <div><strong>Snapshot Order</strong><span>Origin, provider, kurir, layanan, ETA, cache status, dan total ongkir tersimpan ke order.</span></div>
                    </div>
                </div>
            </div>

            <form method="post" class="admin-card" onsubmit="return confirm('Reset pengaturan ongkir ke bawaan? API key dan semua mapping juga akan dibersihkan.');">
                <?= csrf_field(); ?>
                <input type="hidden" name="shipping_action" value="reset">
                <div class="admin-form-head admin-form-head--row">
                    <div><span class="admin-badge">Reset Aman</span><h2>Kembalikan Ongkir Bawaan</h2><p>Gunakan jika eksperimen provider, zona, atau cache ingin dibersihkan.</p></div>
                    <div class="admin-form-actions"><button class="admin-btn admin-btn--ghost" type="submit">Reset Ongkir</button></div>
                </div>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
