<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$context = is_array($orderContext ?? null) ? $orderContext : [];
$product = is_array($context['product'] ?? null) ? $context['product'] : null;
$checkoutSettings = function_exists('checkout_settings_for_product')
    ? checkout_settings_for_product($product)
    : (function_exists('checkout_settings') ? checkout_settings() : []);
$title = (string)($context['title'] ?? ($checkoutSettings['headline'] ?? 'Ajukan Pemesanan'));
$text = (string)($context['text'] ?? ($checkoutSettings['intro'] ?? 'Isi data pemesanan awal agar admin bisa menindaklanjuti dengan informasi produk, stok, dan jadwal yang jelas.'));
$source = (string)($context['source'] ?? 'product-order-form');
$category = (string)($context['category'] ?? 'produk');
$location = (string)($context['location'] ?? '');
$intent = (string)($context['intent'] ?? 'order-draft');
$label = (string)($context['label'] ?? $title);
$productTitle = (string)($context['product_title'] ?? $context['item_title'] ?? '');
$productSlug = (string)($context['product_slug'] ?? '');
$productUrl = (string)($context['product_url'] ?? $context['item_url'] ?? current_url());
$price = (int)($context['price'] ?? 0);
$button = (string)($context['button'] ?? ($checkoutSettings['button_label'] ?? 'Ajukan Pemesanan'));
$needDefault = (string)($context['need'] ?? '');
$formId = 'order-form-' . substr(md5($source . $title . $productTitle), 0, 8);
$needOptions = (array)($context['need_options'] ?? ($checkoutSettings['need_options'] ?? [
    'Booking Produk Ini',
    'Tanya Stok & Harga Terbaru',
    'Minta Video / Foto Terbaru',
    'Survey Area Layanan',
    'Kirim ke Lokasi Saya',
    'Paket Produk Keluarga',
    'Paket Layanan',
    'Paket komunitas / Perusahaan',
]));
$locationOptions = (array)($context['location_options'] ?? ($checkoutSettings['location_options'] ?? [
    'Jakarta Selatan',
    'Tangerang Selatan',
    'Depok',
    'Bekasi',
    'Bandung',
    'Surabaya',
    'Bali',
]));
$globalPaymentOptions = function_exists('order_payment_methods') ? order_payment_methods() : [
    'Konsultasi Dulu',
    'Transfer Setelah Deal',
    'QRIS Setelah Invoice',
    'Tunai Saat Survey/Kirim',
    'Belum Memilih',
];
$paymentOptions = function_exists('commerce_payment_options_for_product') ? commerce_payment_options_for_product($product, $globalPaymentOptions) : $globalPaymentOptions;
$paymentDefault = (string)($context['payment_method'] ?? (function_exists('commerce_payment_default_for_product') ? commerce_payment_default_for_product($product, $paymentOptions) : 'Konsultasi Dulu'));
$shippingOptions = (array)($context['shipping_method_options'] ?? ($checkoutSettings['shipping_method_options'] ?? ['Konfirmasi Ongkir Dulu', 'Kirim Kurir / Ekspedisi', 'Ambil di Tempat']));
$shippingPolicy = function_exists('commerce_shipping_policy') ? commerce_shipping_policy($product) : ['mode' => 'global', 'label' => '', 'note' => '', 'free_shipping' => false, 'force_manual_confirmation' => false];
$productPolicy = function_exists('commerce_policy_normalize_product') ? commerce_policy_normalize_product($product) : ['payment_rule_mode' => 'global', 'checkout_rule_note' => ''];
$policyBadges = function_exists('commerce_policy_badges') ? commerce_policy_badges($product) : [];
$preorderStatus = function_exists('commerce_preorder_status') ? commerce_preorder_status($product) : ['enabled' => false];
$shippingNeeded = array_key_exists('shipping_needed', $context)
    ? (bool)$context['shipping_needed']
    : (function_exists('checkout_shipping_needed_for_product') ? checkout_shipping_needed_for_product($product) : true);
$emailEnabled = !array_key_exists('email_enabled', $checkoutSettings) || !empty($checkoutSettings['email_enabled']);
$quantityEnabled = !function_exists('checkout_field_enabled') || checkout_field_enabled('quantity', $checkoutSettings);
$plannedDateEnabled = !function_exists('checkout_field_enabled') || checkout_field_enabled('planned_date', $checkoutSettings);
$needEnabled = !function_exists('checkout_field_enabled') || checkout_field_enabled('need', $checkoutSettings);
$locationEnabled = !function_exists('checkout_field_enabled') || checkout_field_enabled('location', $checkoutSettings);
$paymentMethodEnabled = !function_exists('checkout_field_enabled') || checkout_field_enabled('payment_method', $checkoutSettings);
$notesEnabled = !function_exists('checkout_field_enabled') || checkout_field_enabled('notes', $checkoutSettings);
$addressEnabled = $shippingNeeded && (!function_exists('checkout_field_enabled') || checkout_field_enabled('address', $checkoutSettings));
$shippingMethodEnabled = $shippingNeeded && (!function_exists('checkout_field_enabled') || checkout_field_enabled('shipping_method', $checkoutSettings));
$emailRequired = $emailEnabled && !empty($checkoutSettings['email_required']);
$plannedDateRequired = $plannedDateEnabled && !empty($checkoutSettings['planned_date_required']);
$needRequired = $needEnabled && !empty($checkoutSettings['need_required']);
$locationRequired = $locationEnabled && !empty($checkoutSettings['location_required']);
$paymentMethodRequired = $paymentMethodEnabled && !empty($checkoutSettings['payment_method_required']);
$notesRequired = $notesEnabled && !empty($checkoutSettings['notes_required']);
$addressRequired = $addressEnabled && !empty($checkoutSettings['address_required']);
$shippingRequired = $shippingMethodEnabled && !empty($checkoutSettings['shipping_method_required']);
$summaryNote = (string)($checkoutSettings['summary_note'] ?? 'Ini belum pembayaran otomatis. Setelah order terkirim, Anda akan mendapat nomor referensi dan bisa lanjut konfirmasi via WhatsApp.');
$consentText = (string)($checkoutSettings['consent_text'] ?? 'Saya bersedia dihubungi admin melalui WhatsApp/telepon/email terkait pemesanan ini.');
$shippingEstimatorPayload = function_exists('shipping_public_estimator_payload')
    ? shipping_public_estimator_payload($product, $price)
    : ['enabled' => false];
if (!empty($shippingPolicy['free_shipping']) || !empty($shippingPolicy['force_manual_confirmation'])) {
    $shippingEstimatorPayload['enabled'] = false;
}
$allowedGatewayLabels = function_exists('commerce_allowed_gateway_labels') ? commerce_allowed_gateway_labels($product) : [];
$shippingEstimatorJson = json_encode($shippingEstimatorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="order-card order-card--template" id="<?= esc($formId); ?>-wrap">
    <div class="order-card__intro">
        <span class="dynamic-mini-label">Order Awal</span>
        <h2><?= esc($title); ?></h2>
        <p><?= esc($text); ?></p>
        <?php if ($productTitle !== ''): ?>
            <div class="order-product-summary">
                <strong><?= esc($productTitle); ?></strong>
                <?php if ($price > 0): ?><span><?= esc(rupiah($price)); ?></span><?php endif; ?>
                <?php if ($location !== ''): ?><small>Area: <?= esc($location); ?></small><?php endif; ?>
                <?php if (!$shippingNeeded): ?><small>Pengiriman fisik tidak diperlukan untuk item ini.</small><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($policyBadges || !empty($shippingPolicy['note']) || !empty($preorderStatus['enabled']) || $allowedGatewayLabels): ?>
            <div class="order-policy-note">
                <?php if ($policyBadges): ?>
                    <div class="order-policy-badges">
                        <?php foreach ($policyBadges as $badge): ?><span><?= esc((string)$badge); ?></span><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($shippingPolicy['note'])): ?><p><?= esc((string)$shippingPolicy['note']); ?></p><?php endif; ?>
                <?php if (!empty($preorderStatus['enabled'])): ?><p><b>Pre-order:</b> <?= esc(trim(((string)($preorderStatus['eta'] ?? '') !== '' ? (string)$preorderStatus['eta'] . ' · ' : '') . (string)($preorderStatus['note'] ?? 'Admin akan konfirmasi jadwal.'))); ?></p><?php endif; ?>
                <?php if ($allowedGatewayLabels): ?><p><b>Gateway disiapkan:</b> <?= esc(implode(', ', $allowedGatewayLabels)); ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (array_key_exists('enabled', $checkoutSettings) && empty($checkoutSettings['enabled'])): ?>
        <div class="admin-alert admin-alert--warning">Form checkout sedang dinonaktifkan oleh admin.</div>
    <?php else: ?>
    <form
        class="order-form order-form--template"
        action="<?= esc(url('order-submit')); ?>"
        method="post"
        data-order-form="1"
        data-conversion-track="1"
        data-lead-source="<?= esc($source); ?>"
        data-lead-channel="checkout"
        data-lead-type="order_submit"
        data-lead-category="<?= esc($category); ?>"
        data-lead-location="<?= esc($location); ?>"
        data-lead-intent="<?= esc($intent); ?>"
        data-lead-label="<?= esc($label); ?>"
        aria-label="<?= esc($label); ?>">
        <?= csrf_field(); ?>
        <input type="hidden" name="source" value="<?= esc($source); ?>">
        <input type="hidden" name="category" value="<?= esc($category); ?>">
        <input type="hidden" name="intent" value="<?= esc($intent); ?>">
        <input type="hidden" name="label" value="<?= esc($label); ?>">
        <input type="hidden" name="product_title" value="<?= esc($productTitle); ?>">
        <input type="hidden" name="product_slug" value="<?= esc($productSlug); ?>">
        <input type="hidden" name="product_url" value="<?= esc($productUrl); ?>">
        <input type="hidden" name="price" value="<?= esc((string)$price); ?>">
        <input type="hidden" name="shipping_required" value="<?= $shippingNeeded ? '1' : '0'; ?>">
        <input type="hidden" name="commerce_shipping_policy" value="<?= esc((string)($shippingPolicy['mode'] ?? 'global')); ?>">
        <input type="hidden" name="commerce_payment_policy" value="<?= esc((string)($productPolicy['payment_rule_mode'] ?? 'global')); ?>">
        <input type="hidden" name="shipping_quote_source" value="manual" data-shipping-field="quote_source">
        <input type="hidden" name="shipping_provider" value="" data-shipping-field="provider">
        <input type="hidden" name="shipping_courier" value="" data-shipping-field="courier">
        <input type="hidden" name="shipping_service" value="" data-shipping-field="service">
        <input type="hidden" name="shipping_service_label" value="" data-shipping-field="service_label">
        <input type="hidden" name="shipping_quote_option_id" value="" data-shipping-field="option_id">
        <input type="hidden" name="shipping_cache_key" value="" data-shipping-field="cache_key">
        <input type="hidden" name="shipping_destination_code" value="" data-shipping-field="destination_code">
        <?php if (!$shippingNeeded): ?><input type="hidden" name="shipping_method" value="Digital / Tidak Perlu Pengiriman"><?php endif; ?>
        <?php if (!$quantityEnabled): ?><input type="hidden" name="quantity" value="1"><?php endif; ?>
        <?php if (!$paymentMethodEnabled): ?><input type="hidden" name="payment_method" value="Belum Memilih"><?php endif; ?>
        <input type="hidden" name="page_path" value="<?= esc(current_uri() . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : '')); ?>">
        <input type="hidden" name="checkout_profile_source" value="<?= esc((string)($checkoutSettings['_profile_source'] ?? 'global')); ?>">
        <input type="hidden" name="checkout_profile_preset" value="<?= esc((string)($checkoutSettings['_profile_preset'] ?? 'global')); ?>">

        <div class="inquiry-hp" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="order-form__grid">
            <label>
                <span>Nama</span>
                <input type="text" name="name" placeholder="Nama pemesan" autocomplete="name" required>
            </label>
            <label>
                <span>Nomor WhatsApp/Telepon</span>
                <input type="tel" name="phone" placeholder="08xxxxxxxxxx" autocomplete="tel" required>
            </label>
            <?php if ($emailEnabled): ?>
                <label>
                    <span>Email <?= $emailRequired ? '' : '<small>(opsional)</small>'; ?></span>
                    <input type="email" name="email" placeholder="nama@email.com" autocomplete="email" <?= $emailRequired ? 'required' : ''; ?>>
                </label>
            <?php endif; ?>
            <?php if ($quantityEnabled): ?>
                <label>
                    <span>Jumlah / Kebutuhan</span>
                    <input type="number" name="quantity" min="1" max="999" value="1" required>
                </label>
            <?php endif; ?>
            <?php if ($plannedDateEnabled): ?>
                <label>
                    <span>Rencana Tanggal</span>
                    <input type="date" name="planned_date" <?= $plannedDateRequired ? 'required' : ''; ?>>
                </label>
            <?php endif; ?>
            <?php if ($needEnabled): ?>
                <label>
                    <span>Jenis Kebutuhan</span>
                    <select name="need" <?= $needRequired ? 'required' : ''; ?>>
                        <option value="">Pilih kebutuhan</option>
                        <?php foreach ($needOptions as $option): ?>
                            <option value="<?= esc((string)$option); ?>" <?= $needDefault === (string)$option ? 'selected' : ''; ?>><?= esc((string)$option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="need" value="<?= esc($needDefault ?: ($productTitle !== '' ? 'Pemesanan ' . $productTitle : 'Pemesanan')); ?>">
            <?php endif; ?>
            <?php if ($locationEnabled): ?>
                <label>
                    <span>Lokasi / Kota</span>
                    <select name="location" <?= $locationRequired ? 'required' : ''; ?>>
                        <option value="">Pilih lokasi / area</option>
                        <?php foreach ($locationOptions as $option): ?>
                            <option value="<?= esc((string)$option); ?>" <?= $location === (string)$option ? 'selected' : ''; ?>><?= esc((string)$option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="location" value="<?= esc($location); ?>">
            <?php endif; ?>
            <?php if ($paymentMethodEnabled): ?>
                <label>
                    <span>Preferensi Pembayaran</span>
                    <select name="payment_method" <?= $paymentMethodRequired ? 'required' : ''; ?>>
                        <?php foreach ($paymentOptions as $option): ?>
                            <option value="<?= esc((string)$option); ?>" <?= $paymentDefault === (string)$option ? 'selected' : ''; ?>><?= esc((string)$option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <?php if ($shippingMethodEnabled): ?>
                <label>
                    <span>Metode Pengiriman <?= $shippingRequired ? '' : '<small>(opsional)</small>'; ?></span>
                    <select name="shipping_method" <?= $shippingRequired ? 'required' : ''; ?>>
                        <option value="">Pilih metode pengiriman</option>
                        <?php foreach ($shippingOptions as $option): ?>
                            <option value="<?= esc((string)$option); ?>"><?= esc((string)$option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>

        <?php if ($addressEnabled): ?>
            <div class="order-form__grid order-form__grid--shipping">
                <label class="order-form__wide">
                    <span>Alamat Lengkap <?= $addressRequired ? '' : '<small>(opsional)</small>'; ?></span>
                    <input type="text" name="address_line" placeholder="Nama jalan, nomor rumah, patokan, gedung, atau detail alamat" maxlength="240" <?= $addressRequired ? 'required' : ''; ?>>
                </label>
                <?php if (!empty($checkoutSettings['province_enabled'])): ?>
                    <label>
                        <span>Provinsi <?= $addressRequired ? '' : '<small>(opsional)</small>'; ?></span>
                        <input type="text" name="province" placeholder="Contoh: Jawa Barat" maxlength="120" <?= $addressRequired ? 'required' : ''; ?>>
                    </label>
                <?php endif; ?>
                <?php if (!empty($checkoutSettings['city_enabled'])): ?>
                    <label>
                        <span>Kota/Kabupaten <?= $addressRequired ? '' : '<small>(opsional)</small>'; ?></span>
                        <input type="text" name="city" placeholder="Contoh: Depok" maxlength="120" <?= $addressRequired ? 'required' : ''; ?>>
                    </label>
                <?php endif; ?>
                <?php if (!empty($checkoutSettings['district_enabled'])): ?>
                    <label>
                        <span>Kecamatan <?= $addressRequired ? '' : '<small>(opsional)</small>'; ?></span>
                        <input type="text" name="district" placeholder="Contoh: Sukmajaya" maxlength="120" <?= $addressRequired ? 'required' : ''; ?>>
                    </label>
                <?php endif; ?>
                <?php if (!empty($checkoutSettings['postal_code_enabled'])): ?>
                    <label>
                        <span>Kode Pos <small>(opsional)</small></span>
                        <input type="text" name="postal_code" placeholder="Contoh: 16411" maxlength="20">
                    </label>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($shippingNeeded && $shippingMethodEnabled && !empty($shippingEstimatorPayload['enabled'])): ?>
            <div class="order-shipping-estimator" data-shipping-estimator='<?= esc((string)$shippingEstimatorJson); ?>'>
                <div class="order-shipping-estimator__main">
                    <strong>Estimasi Ongkir</strong>
                    <span data-shipping-estimator-status><?= !empty($shippingEstimatorPayload['api_enabled']) ? 'Isi alamat, lalu klik Cek Ongkir agar kuota API tetap hemat.' : 'Isi kota/kabupaten dan metode pengiriman untuk melihat estimasi.'; ?></span>
                </div>
                <div class="order-shipping-estimator__side">
                    <div class="order-shipping-estimator__amount" data-shipping-estimator-amount>Konfirmasi admin</div>
                    <button class="order-shipping-estimator__button" type="button" data-shipping-estimator-check>Cek Ongkir</button>
                </div>
                <div class="order-shipping-estimator__results" data-shipping-estimator-results hidden></div>
            </div>
        <?php endif; ?>

        <?php if ($notesEnabled): ?>
            <label class="order-form__message">
                <span>Catatan Pesanan</span>
                <textarea name="message" rows="4" placeholder="Contoh: ingin booking dulu, minta foto terbaru, jadwal survey, estimasi kirim, atau catatan khusus lainnya." <?= $notesRequired ? 'required' : ''; ?>></textarea>
            </label>
        <?php else: ?>
            <input type="hidden" name="message" value="">
        <?php endif; ?>

        <label class="form-consent-check">
            <input type="checkbox" name="consent_contact" value="1" required>
            <span><?= esc($consentText); ?></span>
        </label>

        <div class="order-form__actions">
            <button class="cta" type="submit"><?= esc($button); ?></button>
            <small><?= esc($summaryNote); ?></small>
        </div>
        <div class="order-form__status" role="status" aria-live="polite"></div>
    </form>
    <?php endif; ?>
</div>
<?php unset($orderContext); ?>
