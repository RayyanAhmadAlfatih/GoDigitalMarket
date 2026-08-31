<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (empty($product) || !is_array($product)) {
    return;
}

$currentProductForDynamic = $product;
$dynamicContext = isset($productContext) && is_array($productContext) ? $productContext : (function_exists('dynamic_product_context') ? dynamic_product_context($currentProductForDynamic) : []);
$dynamicSeoParagraphs = isset($productSeoParagraphs) && is_array($productSeoParagraphs) ? $productSeoParagraphs : (function_exists('dynamic_product_seo_paragraphs') ? dynamic_product_seo_paragraphs($currentProductForDynamic) : []);
$dynamicTrustItems = isset($productTrustItems) && is_array($productTrustItems) ? $productTrustItems : (function_exists('dynamic_product_trust_items') ? dynamic_product_trust_items($currentProductForDynamic) : []);
$dynamicFaqItems = isset($productFaqItems) && is_array($productFaqItems) ? $productFaqItems : (function_exists('dynamic_product_faq') ? dynamic_product_faq($currentProductForDynamic, 6) : []);
$dynamicBookingGuide = isset($productBookingGuide) && is_array($productBookingGuide) ? $productBookingGuide : (function_exists('dynamic_product_booking_guide') ? dynamic_product_booking_guide($currentProductForDynamic) : []);
$dynamicContextLinks = isset($productContextLinks) && is_array($productContextLinks) ? $productContextLinks : (function_exists('dynamic_product_context_links') ? dynamic_product_context_links($currentProductForDynamic) : []);
$dynamicInternalLinks = isset($productInternalLinks) && is_array($productInternalLinks) ? $productInternalLinks : (function_exists('dynamic_v26_product_links') ? dynamic_v26_product_links($currentProductForDynamic, 8) : []);
$dynamicSameLocationProducts = isset($locationProducts) && is_array($locationProducts) ? $locationProducts : (function_exists('dynamic_products_by_location') ? dynamic_products_by_location($currentProductForDynamic, 3) : []);
$dynamicSimilarProducts = isset($semanticProducts) && is_array($semanticProducts) ? $semanticProducts : (function_exists('dynamic_products_by_semantic_match') ? dynamic_products_by_semantic_match($currentProductForDynamic, 3) : []);
$dynamicRelatedArticles = isset($productRelatedArticles) && is_array($productRelatedArticles) ? $productRelatedArticles : (function_exists('dynamic_product_related_articles') ? dynamic_product_related_articles($currentProductForDynamic, 3) : []);
$dynamicRelatedProducts = isset($dynamicRelatedProducts) && is_array($dynamicRelatedProducts) ? $dynamicRelatedProducts : ($dynamicSimilarProducts ?: []);
$dynamicTitle = (string)($dynamicContext['title'] ?? $currentProductForDynamic['title'] ?? 'Produk ini');
$dynamicTerms = (array)($dynamicContext['terms'] ?? (function_exists('dynamic_product_universal_terms') ? dynamic_product_universal_terms($currentProductForDynamic) : []));
$dynamicGuideTitle = (string)($dynamicBookingGuide['title'] ?? $dynamicTerms['guide_title'] ?? 'Panduan Memilih Produk / Layanan Ini');
$dynamicConfirmLabel = (string)($dynamicBookingGuide['confirm_label'] ?? $dynamicTerms['confirm_label'] ?? 'Cek Sebelum Melanjutkan');
$dynamicReadyTitle = (string)($dynamicBookingGuide['ready_title'] ?? $dynamicTerms['ready_title'] ?? 'Siap Melanjutkan?');
$dynamicReadyText = (string)($dynamicBookingGuide['ready_text'] ?? $dynamicTerms['ready_text'] ?? 'Gunakan form agar kebutuhan tercatat rapi di dashboard admin.');
$dynamicRelatedTitle = (string)($dynamicBookingGuide['related_title'] ?? $dynamicTerms['related_title'] ?? 'Rekomendasi Terkait');
$dynamicRelatedText = (string)($dynamicBookingGuide['related_text'] ?? $dynamicTerms['related_text'] ?? 'Lihat juga pilihan lain yang masih relevan.');
$dynamicCatalogUrl = !empty($dynamicContext['is_service']) ? url('layanan') : url('katalog');

$dynamicRenderProductCards = static function (array $items) use ($currentProductForDynamic): void {
    $originalProduct = $GLOBALS['product'] ?? null;
    foreach ($items as $dynamicProductCardItem) {
        if (!is_array($dynamicProductCardItem)) {
            continue;
        }
        if ((string)($dynamicProductCardItem['slug'] ?? '') === (string)($currentProductForDynamic['slug'] ?? '')) {
            continue;
        }
        $product = $dynamicProductCardItem;
        require ROOT_PATH . '/components/product-card.php';
    }
    if (is_array($originalProduct)) {
        $GLOBALS['product'] = $originalProduct;
    }
};

$dynamicRenderCompactProductList = static function (array $items) use ($currentProductForDynamic): void {
    foreach ($items as $item) {
        if (!is_array($item) || (string)($item['slug'] ?? '') === (string)($currentProductForDynamic['slug'] ?? '')) {
            continue;
        }
        $badge = function_exists('dynamic_v3_relevance_badge') ? dynamic_v3_relevance_badge($item) : '';
        ?>
        <a class="dynamic-list-item" href="<?= esc(function_exists('product_url') ? product_url((string)($item['slug'] ?? '')) : url('produk/' . (string)($item['slug'] ?? ''))); ?>">
            <img src="<?= esc((string)($item['image'] ?? asset('images/placeholder-product.svg'))); ?>" alt="<?= esc((string)($item['title'] ?? 'Produk terkait')); ?>" loading="lazy" width="88" height="72">
            <span><strong><?= esc((string)($item['title'] ?? 'Produk terkait')); ?></strong><small><?= esc(function_exists('product_price_label') ? product_price_label($item) : (function_exists('rupiah') ? rupiah((int)($item['price'] ?? 0)) : (string)($item['price'] ?? ''))); ?> · <?= esc((string)($item['category'] ?? 'Katalog')); ?></small><?php if ($badge !== ''): ?><em><?= esc($badge); ?></em><?php endif; ?></span>
        </a>
        <?php
    }
};
?>

<?php if ($dynamicSeoParagraphs || $dynamicTrustItems): ?>
<section class="section alt dynamic-detail-section dynamic-product-detail-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Info Produk / Layanan</span>
                <h2>Ringkasan & Panduan Keputusan</h2>
            </div>
            <span class="dynamic-updated-pill">Data diperbarui: <?= esc(function_exists('dynamic_content_updated_at') ? dynamic_content_updated_at() : date('d M Y')); ?></span>
        </div>

        <div class="dynamic-two-columns">
            <?php if ($dynamicSeoParagraphs): ?>
                <div class="dynamic-panel">
                    <h3>Ringkasan Kontekstual</h3>
                    <?php foreach ($dynamicSeoParagraphs as $paragraph): ?>
                        <p><?= esc((string)$paragraph); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="dynamic-panel">
                <h3>Info Cepat</h3>
                <ul class="dynamic-check-list">
                    <li>Jenis: <?= esc((string)($dynamicContext['item_type'] ?? $dynamicContext['animal_type'] ?? 'Produk / Layanan')); ?></li>
                    <li>Kategori: <?= esc((string)($dynamicContext['category'] ?? 'Katalog')); ?></li>
                    <?php if (!empty($dynamicContext['subcategory'])): ?><li>Topik/Subkategori: <?= esc((string)$dynamicContext['subcategory']); ?></li><?php endif; ?>
                    <?php if (!empty($dynamicContext['tier'])): ?><li>Paket/Kelas: <?= esc((string)$dynamicContext['tier']); ?></li><?php endif; ?>
                    <?php if (!empty($dynamicContext['location'])): ?><li>Area/Kanal: <?= esc((string)$dynamicContext['location']); ?></li><?php endif; ?>
                    <?php if (!empty($dynamicContext['weight'])): ?><li>Spesifikasi: <?= esc((string)$dynamicContext['weight']); ?></li><?php endif; ?>
                </ul>
            </div>
        </div>

        <?php if ($dynamicTrustItems): ?>
            <div class="dynamic-trust-grid">
                <?php foreach ($dynamicTrustItems as $trust): ?>
                    <div class="dynamic-faq-card">
                        <h3><?= esc((string)($trust['title'] ?? 'Info')); ?></h3>
                        <p><?= esc((string)($trust['text'] ?? '')); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($dynamicBookingGuide['suitable_for']) || !empty($dynamicBookingGuide['steps'])): ?>
<section class="section product-buyer-guide-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Panduan Pengambilan Keputusan</span>
                <h2><?= esc($dynamicGuideTitle); ?></h2>
            </div>
            <?php if (!empty($dynamicContextLinks)): ?>
                <div class="dynamic-chip-wrap compact-chip-wrap">
                    <?php foreach (array_slice($dynamicContextLinks, 0, 4) as $link): ?>
                        <a class="dynamic-chip" href="<?= esc((string)($link['url'] ?? '#')); ?>"><?= esc((string)($link['label'] ?? 'Terkait')); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="buyer-guide-grid">
            <?php if (!empty($dynamicBookingGuide['suitable_for'])): ?>
                <div class="dynamic-panel buyer-guide-card">
                    <h3>Cocok untuk</h3>
                    <ul class="dynamic-check-list">
                        <?php foreach ((array)$dynamicBookingGuide['suitable_for'] as $item): ?>
                            <li><?= esc((string)$item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($dynamicBookingGuide['confirmations'])): ?>
                <div class="dynamic-panel buyer-guide-card">
                    <h3><?= esc($dynamicConfirmLabel); ?></h3>
                    <ul class="dynamic-check-list">
                        <?php foreach ((array)$dynamicBookingGuide['confirmations'] as $item): ?>
                            <li><?= esc((string)$item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($dynamicBookingGuide['steps'])): ?>
            <div class="booking-step-grid">
                <?php foreach ((array)$dynamicBookingGuide['steps'] as $index => $step): ?>
                    <?php if (!is_array($step)) { continue; } ?>
                    <div class="booking-step-card">
                        <span><?= esc((string)($index + 1)); ?></span>
                        <h3><?= esc((string)($step['title'] ?? 'Tahap')); ?></h3>
                        <p><?= esc((string)($step['text'] ?? '')); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($dynamicBookingGuide['price_factors'])): ?>
            <div class="dynamic-panel price-factor-panel">
                <h3>Faktor yang Mempengaruhi Harga / Penawaran</h3>
                <div class="dynamic-chip-wrap">
                    <?php foreach ((array)$dynamicBookingGuide['price_factors'] as $factor): ?>
                        <span class="dynamic-chip"><?= esc((string)$factor); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($dynamicInternalLinks)): ?>
<section class="section">
    <div class="container">
        <div class="dynamic-panel internal-link-panel">
            <div class="dynamic-block-head compact-head">
                <div>
                    <span class="dynamic-mini-label">Jelajahi Terkait</span>
                    <h2>Halaman yang Membantu Sebelum Melanjutkan</h2>
                </div>
            </div>
            <div class="internal-link-grid">
                <?php foreach (function_exists('dynamic_v26_link_groups') ? dynamic_v26_link_groups($dynamicInternalLinks) : ['Terkait' => $dynamicInternalLinks] as $group => $links): ?>
                    <div class="internal-link-group">
                        <h3><?= esc((string)$group); ?></h3>
                        <?php foreach ($links as $link): ?>
                            <a href="<?= esc((string)($link['url'] ?? '#')); ?>">
                                <strong><?= esc((string)($link['label'] ?? 'Terkait')); ?></strong>
                                <?php if (!empty($link['text'])): ?>
                                    <span><?= esc((string)$link['text']); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($dynamicRelatedArticles): ?>
<section class="section alt product-related-guide-section article-related-polish-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Artikel Terkait</span>
                <h2>Panduan yang Berkaitan dengan Item Ini</h2>
                <p>Baca panduan berikut untuk membantu memahami konteks, manfaat, dan pertimbangan sebelum melanjutkan.</p>
            </div>
            <a class="dynamic-more-link" href="<?= esc(url('artikel')); ?>">Lihat semua artikel</a>
        </div>
        <div class="cards3 dynamic-card-grid">
            <?php foreach ($dynamicRelatedArticles as $article): ?>
                <?php if (!is_array($article)) { continue; } ?>
                <?php require ROOT_PATH . '/components/article-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section checkout-cta-section-v2911" id="order-product">
    <div class="container">
        <div class="dynamic-panel checkout-product-cta-panel">
            <div>
                <span class="dynamic-mini-label">Form Lanjutan</span>
                <h2><?= esc($dynamicReadyTitle); ?></h2>
                <p><?= esc($dynamicReadyText); ?></p>
            </div>
            <a
                class="cta"
                href="<?= esc(order_checkout_url($currentProductForDynamic, ['source' => 'product-detail-section'])); ?>"
                <?= conversion_link_attrs([
                    'source' => 'product-detail-section',
                    'type' => 'checkout_click',
                    'channel' => 'checkout',
                    'category' => (string)($dynamicContext['category'] ?? ''),
                    'location' => (string)($dynamicContext['location'] ?? ''),
                    'label' => $dynamicTitle,
                    'intent' => 'open-checkout',
                ]); ?>>
                <?= esc(function_exists('product_primary_cta_label') ? product_primary_cta_label($currentProductForDynamic) : 'Lanjutkan'); ?>
            </a>
        </div>
    </div>
</section>

<?php if ($dynamicSameLocationProducts || $dynamicSimilarProducts): ?>
<section class="section alt dynamic-product-recommendation-section">
    <div class="container">
        <div class="dynamic-two-columns">
            <?php if ($dynamicSameLocationProducts): ?>
                <div class="dynamic-panel">
                    <h2>Pilihan Lain di <?= esc((string)($dynamicContext['location'] ?? 'Area/Kanal yang Sama')); ?></h2>
                    <div class="dynamic-list-products">
                        <?php $dynamicRenderCompactProductList($dynamicSameLocationProducts); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($dynamicSimilarProducts): ?>
                <div class="dynamic-panel">
                    <h2>Rekomendasi Sejenis</h2>
                    <div class="dynamic-list-products">
                        <?php $dynamicRenderCompactProductList($dynamicSimilarProducts); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($dynamicRelatedProducts): ?>
<section class="section alt dynamic-related-product-card-section">
    <div class="container">
        <h2 class="title"><?= esc($dynamicRelatedTitle); ?></h2>
        <p class="center"><?= esc($dynamicRelatedText); ?></p>
        <div class="cards3 dynamic-card-grid">
            <?php $dynamicRenderProductCards($dynamicRelatedProducts); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($dynamicFaqItems): ?>
<section class="section dynamic-product-faq-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">FAQ Dinamis</span>
                <h2>Pertanyaan yang Sering Ditanyakan</h2>
            </div>
            <span class="dynamic-updated-pill">Update: <?= esc(function_exists('dynamic_content_updated_at') ? dynamic_content_updated_at() : date('d M Y')); ?></span>
        </div>
        <div class="faq-wrap dynamic-faq-grid">
            <?php foreach ($dynamicFaqItems as $faqItem): ?>
                <div class="faq-item dynamic-faq-card">
                    <h3><?= esc((string)($faqItem['question'] ?? 'Pertanyaan')); ?></h3>
                    <p><?= esc((string)($faqItem['answer'] ?? '')); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $product = $currentProductForDynamic; ?>
