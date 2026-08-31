<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$slug = (string)($_GET['slug'] ?? segment(2) ?? '');
$product = get_product_by_slug($slug);
if (!$product) {
    http_response_code(404);
    require ROOT_PATH . '/pages/404.php';
    exit;
}

$productContext = function_exists('dynamic_product_context') ? dynamic_product_context($product) : [];
$productSeoParagraphs = function_exists('dynamic_product_seo_paragraphs') ? dynamic_product_seo_paragraphs($product) : [];
$productTrustItems = function_exists('dynamic_product_trust_items') ? dynamic_product_trust_items($product) : [];
$productFaqItems = function_exists('dynamic_product_faq') ? dynamic_product_faq($product, 6) : [];
$productBookingGuide = function_exists('dynamic_product_booking_guide') ? dynamic_product_booking_guide($product) : [];
$productContextLinks = function_exists('dynamic_product_context_links') ? dynamic_product_context_links($product) : [];
$productInternalLinks = function_exists('dynamic_v26_product_links') ? dynamic_v26_product_links($product, 8) : [];
$productRelatedArticles = function_exists('dynamic_product_related_articles') ? dynamic_product_related_articles($product, 3) : [];
$locationProducts = function_exists('dynamic_products_by_location') ? dynamic_products_by_location($product, 3) : [];
$semanticProducts = function_exists('dynamic_products_by_semantic_match') ? dynamic_products_by_semantic_match($product, 3) : [];
$baseRelatedProducts = function_exists('related_products') ? related_products($product, 4) : [];
$dynamicRelatedProducts = $semanticProducts ?: array_values(array_filter($baseRelatedProducts, static function (array $item) use ($product): bool {
    return (string)($item['slug'] ?? '') !== (string)($product['slug'] ?? '');
}));

$productTitle = (string)($productContext['title'] ?? $product['title'] ?? 'Produk / Layanan');
$productCategory = (string)($productContext['category'] ?? $product['category'] ?? 'Katalog');
$itemTypeKey = function_exists('product_item_type_key') ? product_item_type_key($product) : (string)($productContext['item_type_key'] ?? 'physical');
$itemTypeLabel = function_exists('product_item_type_label') ? product_item_type_label($product) : (string)($productContext['item_type'] ?? 'Produk / Layanan');
$itemTypeIcon = function_exists('product_item_type_icon') ? product_item_type_icon($product) : (string)($productContext['item_type_icon'] ?? '📦');
$isDigital = function_exists('product_is_digital') ? product_is_digital($product) : !empty($productContext['is_digital']);
$isServiceLike = function_exists('product_is_service_like') ? product_is_service_like($product) : !empty($productContext['is_service']);
$productDescription = trim((string)($product['seo']['description'] ?? $product['excerpt'] ?? $product['description'] ?? ''));
if ($productDescription === '') {
    $productDescription = function_exists('dynamic_text_clean') ? dynamic_text_clean((string)($productSeoParagraphs[0] ?? '')) : (string)($productSeoParagraphs[0] ?? '');
}
if ($productDescription === '') {
    $productDescription = 'Detail produk atau layanan.';
}
$productCanonical = function_exists('seo_preservation_product_canonical') ? seo_preservation_product_canonical($product) : product_permalink($product);
$productImage = (string)($product['image'] ?? asset('images/placeholder-product.svg'));
$productPrimaryCta = function_exists('product_primary_cta_label') ? product_primary_cta_label($product) : 'Pesan Sekarang';
$productSecondaryCta = function_exists('product_secondary_cta_label') ? product_secondary_cta_label($product) : 'Tanya Detail';
$productCatalogLabel = function_exists('business_label') ? ($isServiceLike ? business_label('service', 'Layanan') : business_label('catalog', 'Katalog')) : ($isServiceLike ? 'Layanan' : 'Katalog');
$productCatalogUrl = $isServiceLike ? url('layanan') : url('katalog');
$productTerms = (array)($productContext['terms'] ?? (function_exists('dynamic_product_universal_terms') ? dynamic_product_universal_terms($product) : []));
$productBreadcrumbTrail = function_exists('breadcrumb_migration_trail') ? breadcrumb_migration_trail($product, $isServiceLike ? 'service' : 'product', $productTitle, $productCanonical) : [
    ['name' => 'Home', 'url' => url()],
    ['name' => $productCatalogLabel, 'url' => $productCatalogUrl],
    ['name' => $productTitle, 'url' => $productCanonical],
];
$productSeoProfile = function_exists('dynamic_v3_item_profile') ? dynamic_v3_item_profile($product, 'product') : ['tokens' => []];
$productKeywords = array_values(array_unique(array_filter(array_merge(
    [$productTitle, $productCategory, $itemTypeLabel, (string)($productContext['main_keyword'] ?? '')],
    array_keys((array)($productSeoProfile['tokens'] ?? [])),
    (array)($productContext['keywords'] ?? [])
), static fn(mixed $value): bool => trim((string)$value) !== '')));

set_seo([
    'title' => trim((string)($product['seo']['title'] ?? '')) ?: ($productTitle . ' - ' . SITE_NAME),
    'description' => limit_chars($productDescription, 155),
    'keywords' => implode(', ', array_slice($productKeywords, 0, 18)),
    'canonical' => $productCanonical,
    'robots' => 'index, follow',
    'type' => 'product',
    'image' => $productImage,
    'url' => $productCanonical,
]);

if (function_exists('add_schema')) {
    $productSchema = product_schema_ready($product);
    if (!empty($productSchema)) {
        if (empty($productSchema['description'])) {
            $productSchema['description'] = function_exists('dynamic_text_clean') ? dynamic_text_clean((string)($productSeoParagraphs[0] ?? $productDescription)) : $productDescription;
        }
        $productSchema['additionalType'] = $itemTypeLabel;
        add_schema($productSchema);
    }

    if (!empty($productBookingGuide['steps']) && function_exists('dynamic_product_howto_schema_array')) {
        add_schema(dynamic_product_howto_schema_array($product, $productBookingGuide));
    }

    if (!empty($productInternalLinks) && function_exists('dynamic_v26_webpage_schema_array') && function_exists('dynamic_v26_navigation_schema_array')) {
        add_schema(dynamic_v26_webpage_schema_array($productTitle, $productDescription, $productCanonical, $productInternalLinks, 'ItemPage'));
        add_schema(dynamic_v26_navigation_schema_array($productInternalLinks, 'Jalur Lanjutan ' . $productTitle));
    }
}

if ($productFaqItems && function_exists('faq_schema')) {
    faq_schema($productFaqItems);
}

breadcrumb_schema($productBreadcrumbTrail);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
if (function_exists('content_restriction_allowed')) {
    $restrictionStatus = content_restriction_allowed('product', $product);
    if (empty($restrictionStatus['allowed'])) {
        content_restriction_render_gate($restrictionStatus, $productTitle);
        require_once ROOT_PATH . '/components/layout/footer.php';
        return;
    }
}
?>

<section class="mini-hero mini-hero--catalog-item">
    <div class="container">
        <?php if (function_exists('breadcrumb_migration_render')) { breadcrumb_migration_render($productBreadcrumbTrail); } ?>
        <span class="product-type-pill"><?= esc($itemTypeIcon . ' ' . $itemTypeLabel); ?></span>
        <h1><?= esc($productTitle); ?></h1>
        <p><?= esc($productDescription); ?></p>
    </div>
</section>

<!-- U-Growth Dynamic Product Detail Merge v4.1 active: above-fold relevance summary -->
<section class="section product-decision-summary-section" data-dynamic-product-merge="v4.1">
    <div class="container">
        <div class="product-decision-summary">
            <div class="product-decision-main">
                <span class="dynamic-mini-label">Konten Dinamis Relevan</span>
                <h2><?= esc((string)($productTerms['decision_title'] ?? 'Ringkasan & Panduan Cepat')); ?></h2>
                <p><?= esc(function_exists('dynamic_text_clean') ? dynamic_text_clean((string)($productSeoParagraphs[0] ?? $productDescription)) : (string)($productSeoParagraphs[0] ?? $productDescription)); ?></p>
                <?php if (!empty($productContextLinks)): ?>
                    <div class="dynamic-chip-wrap compact-chip-wrap">
                        <?php foreach (array_slice($productContextLinks, 0, 5) as $link): ?>
                            <a class="dynamic-chip" href="<?= esc((string)($link['url'] ?? '#')); ?>"><?= esc((string)($link['label'] ?? 'Terkait')); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="product-decision-side">
                <div><strong><?= esc($itemTypeIcon . ' ' . $itemTypeLabel); ?></strong><span>Jenis item</span></div>
                <div><strong><?= esc($productCategory); ?></strong><span>Kategori</span></div>
                <?php if (!empty($productContext['location'])): ?><div><strong><?= esc((string)$productContext['location']); ?></strong><span>Area/Kanal</span></div><?php endif; ?>
                <?php if (!empty($productContext['tier'])): ?><div><strong><?= esc((string)$productContext['tier']); ?></strong><span>Kelas/Paket</span></div><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="product-detail-grid">
            <div class="product-detail-image">
                <img src="<?= esc($productImage); ?>" alt="<?= esc((string)($product['image_alt'] ?? $productTitle)); ?>" loading="eager" width="1000" height="800">
                <?php $gallery = array_values(array_filter($product['gallery'] ?? [])); if ($gallery): ?><div class="product-gallery"><?php foreach ($gallery as $galleryImage): ?><img src="<?= esc((string)$galleryImage); ?>" alt="<?= esc((string)($product['image_alt'] ?? $productTitle)); ?>" loading="lazy" width="180" height="140"><?php endforeach; ?></div><?php endif; ?>
            </div>
            <div class="product-detail-content">
                <span class="product-category"><?= esc($productCategory); ?></span>
                <h2><?= esc($productTitle); ?></h2>
                <div class="price"><?= esc(product_price_label($product)); ?></div>
                <div class="product-detail-meta">
                    <div class="meta-item"><strong>Jenis Item:</strong><span><?= esc($itemTypeIcon . ' ' . $itemTypeLabel); ?></span></div>
                    <?php if (!empty($product['weight']) && (!$isDigital || product_supports_shipping($product))): ?><div class="meta-item"><strong>Spesifikasi:</strong><span><?= esc((string)$product['weight']); ?></span></div><?php endif; ?>
                    <?php if (!empty($product['location'])): ?><div class="meta-item"><strong><?= $isDigital ? 'Kanal Akses:' : 'Area/Kanal:'; ?></strong><span><?= esc((string)$product['location']); ?></span></div><?php endif; ?>
                    <?php if (!empty($product['tier'])): ?><div class="meta-item"><strong>Kelas/Paket:</strong><span><?= esc((string)$product['tier']); ?></span></div><?php endif; ?>
                    <div class="meta-item"><strong>Status:</strong><?php $stockStatus=(string)($product['stock_status'] ?? 'in_stock'); $stockLabel=match($stockStatus){'out_of_stock'=>'Tidak tersedia','preorder'=>'Pre-order / Booking','contact_admin'=>'Hubungi Admin',default=>'Tersedia'}; ?><span class="stock-badge stock-badge-<?= esc($stockStatus); ?>"><?= esc($stockLabel); ?></span><?php if ($stockStatus === 'contact_admin'): ?><p class="stock-note"><span style="color:red;">Untuk ketersediaan, harga terbaru, akses, jadwal, atau detail layanan, silakan hubungi admin.</span></p><?php endif; ?></div>
                </div>

                <?php if (function_exists('commerce_policy_badges') && commerce_policy_badges($product)): ?>
                    <div class="commerce-policy-public">
                        <?php foreach (commerce_policy_badges($product) as $badge): ?><span><?= esc((string)$badge); ?></span><?php endforeach; ?>
                        <?php $shippingPolicyPublic = function_exists('commerce_shipping_policy') ? commerce_shipping_policy($product) : []; ?>
                        <?php if (!empty($shippingPolicyPublic['note'])): ?><p><?= esc((string)$shippingPolicyPublic['note']); ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isDigital): ?>
                    <div class="digital-access-box">
                        <div>
                            <span class="digital-access-eyebrow">Akses Produk Digital</span>
                            <h3><?= esc(product_digital_access_mode_label($product)); ?></h3>
                            <p><?= esc(trim((string)($product['digital_instructions'] ?? '')) ?: 'Akses produk digital akan diberikan setelah pembayaran dikonfirmasi. Detail akses lanjutan akan diinformasikan oleh admin.'); ?></p>
                        </div>
                        <ul>
                            <?php foreach (product_digital_access_notes($product) as $note): ?><li><?= esc($note); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="product-description"><?php if (!empty($product['content'])): ?><?= strip_tags((string)$product['content'], '<p><br><strong><b><em><i><u><s><del><ul><ol><li><h2><h3><h4><blockquote><a><img><table><thead><tbody><tr><th><td><hr>'); ?><?php else: ?><p><?= nl2br(esc((string)($product['description'] ?? $product['excerpt'] ?? ''))); ?></p><?php endif; ?></div>
                <div class="product-features"><h3>Keunggulan / Benefit</h3><ul><?php $features = !empty($product['features']) ? $product['features'] : ['Informasi bisa dikelola dari admin','Bisa diarahkan ke WhatsApp, inquiry, atau checkout','Cocok untuk berbagai jenis bisnis dan niche']; foreach ($features as $feature): ?><li><?= esc((string)$feature); ?></li><?php endforeach; ?></ul></div>
                <div class="product-actions"><a href="<?= esc(order_checkout_url($product, ['source'=>'product-detail'])); ?>" class="cta" <?= conversion_link_attrs(['source'=>'product-detail','type'=>'checkout_click','channel'=>'checkout','category'=>$productCategory,'location'=>(string)($product['location'] ?? ''),'label'=>$productTitle,'intent'=>'open-checkout']); ?>><?= esc($productPrimaryCta); ?></a><a href="#product-inquiry-form" class="cta secondary" <?= conversion_link_attrs(['source'=>'product-detail','type'=>'inquiry_click','channel'=>'form','category'=>$productCategory,'location'=>(string)($product['location'] ?? ''),'label'=>$productTitle,'intent'=>'product-inquiry']); ?>><?= esc($productSecondaryCta); ?></a></div>
            </div>
        </div>
    </div>
</section>

<?php if (is_file(ROOT_PATH . '/components/dynamic-product-detail.php')) { require ROOT_PATH . '/components/dynamic-product-detail.php'; } ?>

<?php if ($isDigital): ?>
<section class="section alt digital-journey-section">
    <div class="container">
        <div class="dynamic-two-columns">
            <div class="dynamic-panel"><h2>Siap untuk Produk Digital</h2><p>Item digital bisa dipakai untuk e-book, e-course, template, file ZIP, video, audio, atau link akses. Order berbayar dapat dihubungkan dengan akses produk dan member login.</p></div>
            <div class="dynamic-panel"><h2>Mode Akses</h2><div class="dynamic-chip-wrap"><?php foreach (product_digital_access_notes($product) as $note): ?><span class="dynamic-chip"><?= esc($note); ?></span><?php endforeach; ?></div></div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section alt" id="product-inquiry-form"><div class="container"><?php $inquiryContext=['title'=>$isDigital ? 'Tanya Produk Digital' : ($isServiceLike ? 'Konsultasi Layanan' : 'Tanya Detail Produk'),'text'=>$isDigital ? 'Isi form berikut jika ingin bertanya isi produk, akses member, file, atau cara pembelian.' : ($isServiceLike ? 'Isi form berikut untuk konsultasi scope layanan, jadwal, budget, atau kebutuhan khusus.' : 'Isi form berikut untuk bertanya harga, stok, jadwal, area layanan, atau kebutuhan khusus.'),'source'=>'product-detail-form','category'=>$productCategory,'location'=>(string)($product['location'] ?? ''),'intent'=>'product-inquiry','label'=>$productTitle,'item_title'=>$productTitle,'item_url'=>$productCanonical,'need'=>$productCategory,'button'=>$isServiceLike ? 'Kirim Konsultasi' : 'Kirim Pertanyaan']; require ROOT_PATH . '/components/inquiry-form.php'; ?></div></section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
