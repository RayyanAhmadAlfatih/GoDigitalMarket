<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DEDICATED CHECKOUT / FORM ORDER PAGE - Template
|--------------------------------------------------------------------------
| Focused order page for admin follow-up links and product detail CTA.
| This is intentionally noindex because it is a conversion utility page,
| not a search landing page.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$productSlug = trim((string)($_GET['produk'] ?? $_GET['product'] ?? ''));
$product = $productSlug !== '' ? get_product_by_slug($productSlug) : null;
$productTitle = $product ? (string)($product['title'] ?? 'Produk') : 'Produk / Layanan Pilihan';
$productCategory = $product ? (string)($product['category'] ?? 'produk') : 'produk';
$productLocation = $product ? (string)($product['location'] ?? '') : '';
$productUrl = $product ? product_url((string)($product['slug'] ?? '')) : url('katalog');
$productImage = $product ? (string)($product['image'] ?? asset('images/placeholder-product.svg')) : asset('images/placeholder-product.svg');
$productPrice = $product ? (int)($product['price'] ?? 0) : 0;
$source = order_clean((string)($_GET['source'] ?? 'checkout-page'), 80);
$checkoutSettings = function_exists('checkout_settings_for_product')
    ? checkout_settings_for_product($product)
    : (function_exists('checkout_settings') ? checkout_settings() : []);
$checkoutHeadline = (string)($checkoutSettings['headline'] ?? 'Lengkapi Data Pemesanan');
$checkoutIntro = (string)($checkoutSettings['intro'] ?? 'Isi data awal agar admin bisa membantu cek stok, jadwal, lokasi, invoice, dan langkah berikutnya.');
$checkoutSummaryNote = (string)($checkoutSettings['summary_note'] ?? 'Pembayaran belum otomatis di tahap ini.');
$shippingNeeded = function_exists('checkout_shipping_needed_for_product') ? checkout_shipping_needed_for_product($product) : true;

set_seo([
    'title' => 'Form Pemesanan - ' . $productTitle,
    'description' => 'Isi form pemesanan awal agar admin bisa membantu konfirmasi stok, jadwal, lokasi, dan langkah berikutnya.',
    'keywords' => 'form pemesanan, checkout, order produk, order layanan',
    'canonical' => strtok(current_url(), '?') ?: url('checkout'),
    'robots' => 'noindex, nofollow',
    'type' => 'website',
    'image' => $productImage,
    'url' => current_url(),
]);

if (function_exists('conversion_store_event')) {
    conversion_store_event(conversion_normalize_event([
        'source' => $source,
        'type' => 'checkout_viewed',
        'channel' => 'checkout',
        'category' => $productCategory,
        'location' => $productLocation,
        'intent' => 'order-page-view',
        'label' => $productTitle,
        'page_path' => current_uri() . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : ''),
        'target_url' => current_url(),
    ]));
}

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero checkout-hero checkout-hero--template">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= url(); ?>">Home</a>
            <span>/</span>
            <?php if ($product): ?>
                <a href="<?= esc($productUrl); ?>"><?= esc($productTitle); ?></a>
                <span>/</span>
            <?php endif; ?>
            <span>Form Pemesanan</span>
        </div>
        <span class="dynamic-mini-label">Form Pemesanan</span>
        <h1><?= esc($checkoutHeadline); ?></h1>
        <p><?= esc($checkoutIntro); ?></p>
    </div>
</section>

<section class="section checkout-page-section">
    <div class="container">
        <div class="checkout-layout-template">
            <aside class="checkout-summary-card">
                <span class="dynamic-mini-label">Ringkasan Pilihan</span>
                <?php if ($product): ?>
                    <img src="<?= esc($productImage); ?>" alt="<?= esc($productTitle); ?>" loading="eager" width="560" height="420">
                    <h2><?= esc($productTitle); ?></h2>
                    <?php if ($productPrice > 0): ?><p class="checkout-price"><?= esc(rupiah($productPrice)); ?></p><?php endif; ?>
                    <div class="checkout-summary-list">
                        <span><strong>Kategori</strong><?= esc($productCategory); ?></span>
                        <?php if ($productLocation !== ''): ?><span><strong>Lokasi</strong><?= esc($productLocation); ?></span><?php endif; ?>
                        <?php if (!empty($product['weight'])): ?><span><strong>Spesifikasi</strong><?= esc((string)$product['weight']); ?></span><?php endif; ?>
                    </div>
                    <a class="dynamic-chip" href="<?= esc($productUrl); ?>">Lihat detail produk</a>
                <?php else: ?>
                    <h2>Belum memilih produk spesifik</h2>
                    <p>Admin tetap bisa menindaklanjuti kebutuhan Anda. Pilih kebutuhan, lokasi, dan catatan pesanan sejelas mungkin.</p>
                    <a class="dynamic-chip" href="<?= url('katalog'); ?>">Lihat katalog produk</a>
                <?php endif; ?>
                <div class="checkout-trust-list">
                    <span>✅ Data masuk ke dashboard admin</span>
                    <span>✅ Admin follow-up stok & jadwal</span>
                    <span>✅ Invoice dikirim setelah konfirmasi</span>
                    <span>✅ <?= esc($shippingNeeded ? 'Alamat & pengiriman bisa dicatat' : 'Item ini tidak membutuhkan pengiriman fisik'); ?></span>
                    <span>✅ <?= esc($checkoutSummaryNote); ?></span>
                </div>
            </aside>

            <div class="checkout-form-wrap">
                <?php
                $orderContext = [
                    'title' => $product ? 'Form Pemesanan ' . $productTitle : $checkoutHeadline,
                    'text' => $checkoutIntro,
                    'source' => $source,
                    'category' => $productCategory,
                    'location' => $productLocation,
                    'intent' => 'dedicated-checkout-order',
                    'label' => $product ? 'Checkout Produk: ' . $productTitle : 'Checkout Umum',
                    'product_title' => $product ? $productTitle : '',
                    'product_slug' => $product ? (string)($product['slug'] ?? '') : '',
                    'product_url' => $product ? $productUrl : '',
                    'price' => $productPrice,
                    'product' => $product,
                    'shipping_needed' => $shippingNeeded,
                    'need' => $product ? 'Booking Produk Ini' : '',
                    'button' => (string)($checkoutSettings['button_label'] ?? 'Kirim Data Pemesanan'),
                ];
                require ROOT_PATH . '/components/order-form.php';
                ?>
            </div>
        </div>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
