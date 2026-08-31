<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$product = $product ?? [];
$title = $product['title'] ?? 'Produk / Layanan';
$slug = $product['slug'] ?? slugify($title);
$image = $product['image'] ?? asset('images/placeholder-product.svg');
$description = $product['description'] ?? 'Deskripsi singkat produk atau layanan.';
$category = $product['category'] ?? 'Katalog';
$weight = $product['weight'] ?? null;
$location = $product['location'] ?? 'Indonesia';
$url = $product['url'] ?? product_url($slug);
$itemTypeLabel = function_exists('product_item_type_label') ? product_item_type_label($product) : (string)($product['animal_type'] ?? 'Produk Fisik');
$itemTypeIcon = function_exists('product_item_type_icon') ? product_item_type_icon($product) : '📦';
$schemaType = function_exists('product_is_service_like') && product_is_service_like($product) ? 'Service' : 'Product';
$ctaLabel = function_exists('product_primary_cta_label') ? product_primary_cta_label($product) : 'Info Detail';
?>
<article class="card product-card product-card--<?= esc(product_item_type_key($product)); ?>" itemscope itemtype="https://schema.org/<?= esc($schemaType); ?>">
    <div class="card-image product-image">
        <a class="product-image-link" href="<?= esc($url); ?>" title="<?= esc($title); ?>" itemprop="url">
            <img src="<?= esc($image); ?>" alt="<?= esc(image_alt($title, 'Produk dan layanan UMKM')); ?>" loading="lazy" width="600" height="400" itemprop="image">
        </a>
        <span class="product-type-badge"><?= esc($itemTypeIcon . ' ' . $itemTypeLabel); ?></span>
    </div>
    <div class="card-content product-card-content">
        <div class="product-category"><?= esc($category); ?></div>
        <h3 class="card-title product-title" itemprop="name"><a href="<?= esc($url); ?>" title="<?= esc($title); ?>"><?= esc($title); ?></a></h3>
        <p class="product-description" itemprop="description"><?= esc(limit_words($description, 18)); ?></p>
        <?php if (function_exists('dynamic_v3_relevance_badge') && ($__dynamicBadge = dynamic_v3_relevance_badge($product))): ?>
            <div class="dynamic-relevance-badge"><?= esc($__dynamicBadge); ?></div>
        <?php endif; ?>
        <div class="product-meta">
            <?php if ($weight && function_exists('product_supports_shipping') && product_supports_shipping($product)): ?><span class="product-weight">📦 <?= esc($weight); ?></span><?php endif; ?>
            <span class="product-location">📍 <?= esc($location); ?></span>
            <?php if (function_exists('product_is_digital') && product_is_digital($product)): ?><span class="product-digital-meta">🔐 <?= esc(product_digital_access_mode_label($product)); ?></span><?php endif; ?>
        </div>
        <?php if (function_exists('commerce_policy_badges') && commerce_policy_badges($product)): ?>
            <div class="product-policy-chips">
                <?php foreach (array_slice(commerce_policy_badges($product), 0, 2) as $badge): ?><span><?= esc((string)$badge); ?></span><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="card-price product-price price" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
            <meta itemprop="priceCurrency" content="IDR"><meta itemprop="availability" content="https://schema.org/InStock">
            <span itemprop="price"><?= esc(product_price_label($product)); ?></span>
        </div>
        <div class="product-actions">
            <a href="<?= esc($url); ?>" class="product-card-cta" aria-label="<?= esc($ctaLabel . ' ' . $title); ?>" <?= conversion_link_attrs(['source'=>'product-card','type'=>'internal','category'=>(string)$category,'location'=>(string)$location,'label'=>(string)$title,'intent'=>'product-view']); ?>><?= esc($ctaLabel); ?></a>
        </div>
    </div>
</article>
