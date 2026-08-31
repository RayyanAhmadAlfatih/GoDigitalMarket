<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PRODUCT ENGINE
|--------------------------------------------------------------------------
| Marketplace product helpers
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
*/

function product_with_source(array $product, string $source): array
{
    $product['_source'] = $product['_source'] ?? $source;
    $product['source'] = $product['source'] ?? $source;
    return $product;
}

function product_normalized_slug(string $slug): string
{
    return function_exists('slugify') ? slugify($slug) : strtolower(trim($slug));
}

function product_dedupe_by_slug(array $products): array
{
    $unique = [];
    foreach ($products as $product) {
        $slug = (string)($product['slug'] ?? '');
        if ($slug === '') {
            $slug = slugify((string)($product['title'] ?? 'produk'));
            $product['slug'] = $slug;
        }

        $key = product_normalized_slug($slug);
        if ($key === '') {
            $key = 'produk-' . count($unique);
        }

        // Later items win, so admin/MySQL/JSON products override default example products safely.
        // Normalize the dedupe key so imported products with uppercase SKU/code suffixes
        // still resolve from SEO-friendly lowercase URLs such as /produk/sapi-nq169.
        $unique[$key] = $product;
    }
    return array_values($unique);
}

function all_products(): array
{
    static $products = null;

    if ($products !== null) {
        return $products;
    }

    $managedProducts = array_map(
        static fn(array $product): array => product_with_source($product, (string)($product['source'] ?? 'admin')),
        product_managed_products()
    );

    if ($managedProducts || product_starter_content_initialized()) {
        $products = product_dedupe_by_slug($managedProducts);
        return $products;
    }

    $seedProducts = array_map(
        static fn(array $product): array => product_with_source($product, 'seed'),
        require DATA_PATH . '/products.php'
    );

    $products = product_dedupe_by_slug($seedProducts);

    return $products;
}

/*
|--------------------------------------------------------------------------
| GET PRODUCT BY SLUG
|--------------------------------------------------------------------------
*/

function get_product_by_slug(
    string $slug
): ?array {

    $needle = trim($slug);
    $normalizedNeedle = product_normalized_slug($needle);

    foreach (all_products() as $product) {

        $productSlug = (string)($product['slug'] ?? '');

        if (
            $productSlug === $needle
            || product_normalized_slug($productSlug) === $normalizedNeedle
        ) {

            return $product;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| GET PRODUCT BY ID
|--------------------------------------------------------------------------
*/

function get_product_by_id(
    int $id
): ?array {

    foreach (all_products() as $product) {

        if (
            (int)($product['id'] ?? 0) === $id
        ) {

            return $product;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| GET PRODUCT BY SKU
|--------------------------------------------------------------------------
*/

function get_product_by_sku(
    string $sku
): ?array {

    foreach (all_products() as $product) {

        if (
            strtoupper(
                (string)(
                    $product['sku'] ?? ''
                )
            ) === strtoupper($sku)
        ) {

            return $product;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| FEATURED PRODUCTS
|--------------------------------------------------------------------------
*/

function featured_products(
    int $limit = 8
): array {

    $results = array_filter(

        all_products(),

        static function ($product) {

            return
                (bool)(
                    $product['featured']
                    ?? false
                );
        }
    );

    return array_slice(

        array_values($results),

        0,

        $limit
    );
}

/*
|--------------------------------------------------------------------------
| LATEST PRODUCTS
|--------------------------------------------------------------------------
*/

function latest_products(
    int $limit = 12
): array {

    $products = all_products();

    usort(

        $products,

        static function (
            array $a,
            array $b
        ): int {

            return strtotime(
                $b['published_at'] ?? ''
            ) <=> strtotime(
                $a['published_at'] ?? ''
            );
        }
    );

    return array_slice(
        $products,
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCTS BY CATEGORY
|--------------------------------------------------------------------------
*/

function products_by_category(
    string $category,
    int $limit = 20
): array {

    $results = [];

    foreach (all_products() as $product) {

        if (
            strtolower(
                (string)(
                    $product['category'] ?? ''
                )
            ) === strtolower($category)
        ) {

            $results[] = $product;
        }
    }

    return array_slice(
        $results,
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCTS BY SUBCATEGORY
|--------------------------------------------------------------------------
*/

function products_by_subcategory(
    string $subcategory,
    int $limit = 20
): array {

    $results = [];

    foreach (all_products() as $product) {

        if (
            strtolower(
                (string)(
                    $product['subcategory'] ?? ''
                )
            ) === strtolower($subcategory)
        ) {

            $results[] = $product;
        }
    }

    return array_slice(
        $results,
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| RELATED PRODUCTS
|--------------------------------------------------------------------------
*/

function related_products(
    array $currentProduct,
    int $limit = 4
): array {

    $results = [];

    $currentSlug =
        $currentProduct['slug'] ?? '';

    $currentCategory =
        $currentProduct['category'] ?? '';

    foreach (all_products() as $product) {

        if (
            ($product['slug'] ?? '') ===
            $currentSlug
        ) {

            continue;
        }

        if (
            ($product['category'] ?? '') ===
            $currentCategory
        ) {

            $results[] = $product;
        }
    }

    return array_slice(
        $results,
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| SEARCH PRODUCTS
|--------------------------------------------------------------------------
*/

function search_products(
    string $keyword,
    int $limit = 20
): array {

    $keyword =
        strtolower(trim($keyword));

    if ($keyword === '') {
        return [];
    }

    $results = [];

    foreach (all_products() as $product) {

        $haystack = strtolower(

            implode(

                ' ',

                [

                    $product['title'] ?? '',
                    $product['excerpt'] ?? '',
                    $product['description'] ?? '',
                    $product['content'] ?? '',
                    implode(
                        ' ',
                        $product['seo']['keywords']
                        ?? []
                    ),
                ]
            )
        );

        if (
            str_contains(
                $haystack,
                $keyword
            )
        ) {

            $results[] = $product;
        }
    }

    return array_slice(
        $results,
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT EXISTS
|--------------------------------------------------------------------------
*/

function product_exists(
    string $slug
): bool {

    return
        get_product_by_slug($slug)
        !== null;
}

/*
|--------------------------------------------------------------------------
| PRODUCT URL
|--------------------------------------------------------------------------
*/

function product_permalink(
    array $product
): string {

    return product_url(

        (string)(
            $product['slug'] ?? ''
        )
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT PRICE
|--------------------------------------------------------------------------
*/

function product_price(
    array $product
): int {

    return (int)(

        $product['sale_price']
        ?? $product['price']
        ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| UNIVERSAL ITEM TYPE HELPERS
|--------------------------------------------------------------------------
| Catalog item helpers for physical products, services, digital products,
| bookings, menus, packages, and custom orders. Keep legacy field names
| compatible while presenting friendly labels to UMKM admins and customers.
|--------------------------------------------------------------------------
*/

function product_item_type_definitions(): array
{
    return [
        'physical' => [
            'label' => 'Produk Fisik',
            'admin_hint' => 'Barang yang dikirim ke pelanggan, seperti fashion, makanan kemasan, alat, hampers, atau produk retail.',
            'schema' => 'Product',
            'primary_cta' => 'Pesan Sekarang',
            'secondary_cta' => 'Tanya Produk',
            'price_prefix' => '',
            'supports_shipping' => true,
            'icon' => '📦',
        ],
        'service' => [
            'label' => 'Jasa / Layanan',
            'admin_hint' => 'Layanan profesional, konsultasi, agency, klinik, bengkel, edukasi, atau jasa berbasis permintaan.',
            'schema' => 'Service',
            'primary_cta' => 'Konsultasi Layanan',
            'secondary_cta' => 'Minta Penawaran',
            'price_prefix' => 'Mulai dari ',
            'supports_shipping' => false,
            'icon' => '🧰',
        ],
        'digital' => [
            'label' => 'Produk Digital',
            'admin_hint' => 'E-book, e-course, template, file ZIP, preset, video, audio, atau akses digital yang bisa dibeli online.',
            'schema' => 'Product',
            'primary_cta' => 'Beli & Akses Produk',
            'secondary_cta' => 'Tanya Produk Digital',
            'price_prefix' => '',
            'supports_shipping' => false,
            'icon' => '💾',
        ],
        'course' => [
            'label' => 'E-course / Kelas Online',
            'admin_hint' => 'Kelas online, video pembelajaran, materi belajar, atau program digital dengan akses member.',
            'schema' => 'Course',
            'primary_cta' => 'Daftar & Akses Kelas',
            'secondary_cta' => 'Tanya Kelas',
            'price_prefix' => '',
            'supports_shipping' => false,
            'icon' => '🎓',
        ],
        'ebook' => [
            'label' => 'E-book / File Download',
            'admin_hint' => 'PDF, workbook, panduan, checklist, template, file desain, spreadsheet, atau file digital siap unduh.',
            'schema' => 'Product',
            'primary_cta' => 'Beli & Download',
            'secondary_cta' => 'Tanya E-book',
            'price_prefix' => '',
            'supports_shipping' => false,
            'icon' => '📘',
        ],
        'package' => [
            'label' => 'Paket / Bundle',
            'admin_hint' => 'Paket gabungan produk, jasa, kelas, konsultasi, atau bundle digital.',
            'schema' => 'Product',
            'primary_cta' => 'Ambil Paket Ini',
            'secondary_cta' => 'Tanya Paket',
            'price_prefix' => '',
            'supports_shipping' => false,
            'icon' => '🎁',
        ],
        'menu' => [
            'label' => 'Menu Kuliner',
            'admin_hint' => 'Makanan, minuman, katering, bakery, frozen food, hampers kuliner, atau paket menu.',
            'schema' => 'Product',
            'primary_cta' => 'Pesan Menu',
            'secondary_cta' => 'Tanya Menu',
            'price_prefix' => '',
            'supports_shipping' => true,
            'icon' => '🍱',
        ],
        'booking' => [
            'label' => 'Booking / Reservasi',
            'admin_hint' => 'Appointment, reservasi, travel, rental, kelas offline, konsultasi terjadwal, atau layanan berbasis waktu.',
            'schema' => 'Service',
            'primary_cta' => 'Booking Jadwal',
            'secondary_cta' => 'Cek Ketersediaan',
            'price_prefix' => 'Mulai dari ',
            'supports_shipping' => false,
            'icon' => '📅',
        ],
        'custom' => [
            'label' => 'Custom Order',
            'admin_hint' => 'Pesanan sesuai kebutuhan customer, request quotation, pre-order khusus, atau paket yang perlu diskusi dulu.',
            'schema' => 'Product',
            'primary_cta' => 'Request Custom Order',
            'secondary_cta' => 'Diskusi Kebutuhan',
            'price_prefix' => 'Mulai dari ',
            'supports_shipping' => false,
            'icon' => '✨',
        ],
    ];
}

function product_item_type_aliases(): array
{
    return [
        'produk fisik' => 'physical',
        'fisik' => 'physical',
        'physical' => 'physical',
        'barang' => 'physical',
        'produk retail' => 'physical',
        'jasa' => 'service',
        'layanan' => 'service',
        'jasa & layanan' => 'service',
        'jasa / layanan' => 'service',
        'service' => 'service',
        'layanan profesional' => 'service',
        'digital' => 'digital',
        'produk digital' => 'digital',
        'digital product' => 'digital',
        'file digital' => 'digital',
        'e-course' => 'course',
        'ecourse' => 'course',
        'kelas online' => 'course',
        'course' => 'course',
        'ebook' => 'ebook',
        'e-book' => 'ebook',
        'pdf' => 'ebook',
        'file download' => 'ebook',
        'paket' => 'package',
        'bundle' => 'package',
        'paket / bundle' => 'package',
        'menu' => 'menu',
        'kuliner' => 'menu',
        'menu kuliner' => 'menu',
        'booking' => 'booking',
        'reservasi' => 'booking',
        'booking / reservasi' => 'booking',
        'custom' => 'custom',
        'custom order' => 'custom',
        'request quotation' => 'custom',
    ];
}

function product_item_type_key(array|string|null $productOrType): string
{
    $raw = '';

    if (is_array($productOrType)) {
        $raw = (string)(
            $productOrType['item_type_key']
            ?? $productOrType['item_type']
            ?? $productOrType['digital_product_type']
            ?? $productOrType['animal_type']
            ?? $productOrType['type']
            ?? $productOrType['category']
            ?? ''
        );
    } else {
        $raw = (string)$productOrType;
    }

    $normalized = strtolower(trim($raw));
    $aliases = product_item_type_aliases();

    return $aliases[$normalized] ?? (isset(product_item_type_definitions()[$normalized]) ? $normalized : 'physical');
}

function product_item_type_definition(array|string|null $productOrType): array
{
    $definitions = product_item_type_definitions();
    $key = product_item_type_key($productOrType);

    return $definitions[$key] ?? $definitions['physical'];
}

function product_item_type_label(array|string|null $productOrType): string
{
    return (string)(product_item_type_definition($productOrType)['label'] ?? 'Produk Fisik');
}

function product_item_type_icon(array|string|null $productOrType): string
{
    return (string)(product_item_type_definition($productOrType)['icon'] ?? '📦');
}

function product_is_digital(array $product): bool
{
    return in_array(product_item_type_key($product), ['digital', 'course', 'ebook'], true);
}

function product_is_service_like(array $product): bool
{
    return in_array(product_item_type_key($product), ['service', 'booking'], true);
}

function product_supports_shipping(array $product): bool
{
    return (bool)(product_item_type_definition($product)['supports_shipping'] ?? false);
}

function product_primary_cta_label(array $product): string
{
    $custom = trim((string)($product['cta_primary_label'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return (string)(product_item_type_definition($product)['primary_cta'] ?? 'Pesan Sekarang');
}

function product_secondary_cta_label(array $product): string
{
    $custom = trim((string)($product['cta_secondary_label'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return (string)(product_item_type_definition($product)['secondary_cta'] ?? 'Tanya Detail');
}

function product_price_label(array $product): string
{
    $prefix = (string)(product_item_type_definition($product)['price_prefix'] ?? '');
    $price = product_price($product);

    if ($price <= 0) {
        return 'Hubungi kami';
    }

    return $prefix . rupiah($price);
}

function product_digital_access_mode_label(array $product): string
{
    $mode = (string)($product['digital_access_mode'] ?? 'after_payment');

    return match ($mode) {
        'direct_download' => 'Download setelah pembayaran',
        'access_link' => 'Akses via link khusus',
        'member_area' => 'Akses via member area',
        default => 'Akses setelah pembayaran',
    };
}

function product_digital_type_label(array $product): string
{
    $type = (string)($product['digital_delivery_type'] ?? product_item_type_key($product));

    return match ($type) {
        'ebook' => 'E-book / PDF',
        'course' => 'E-course / Video',
        'template' => 'Template / File kerja',
        'zip' => 'File ZIP',
        'link' => 'Link akses',
        'bundle' => 'Bundle digital',
        default => 'Produk digital',
    };
}

function product_digital_access_notes(array $product): array
{
    if (!product_is_digital($product)) {
        return [];
    }

    $notes = [product_digital_type_label($product), product_digital_access_mode_label($product)];

    $duration = (int)($product['access_duration_days'] ?? 0);
    if ($duration > 0) {
        $notes[] = 'Masa akses ' . $duration . ' hari';
    } else {
        $notes[] = 'Masa akses fleksibel';
    }

    $limit = (int)($product['download_limit'] ?? 0);
    if ($limit > 0) {
        $notes[] = 'Batas download ' . $limit . ' kali';
    }

    if (!empty($product['member_area_enabled'])) {
        $notes[] = 'Siap untuk member area';
    }

    return array_values(array_unique(array_filter($notes)));
}

/*
|--------------------------------------------------------------------------
| PRODUCT FORMATTED PRICE
|--------------------------------------------------------------------------
*/

function formatted_product_price(
    array $product
): string {

    return rupiah(
        product_price($product)
    );
}

/*
|--------------------------------------------------------------------------
| HAS DISCOUNT
|--------------------------------------------------------------------------
*/

function product_has_discount(
    array $product
): bool {

    return
        !empty(
            $product['sale_price']
        )
        &&
        (int)$product['sale_price']
        <
        (int)$product['price'];
}

/*
|--------------------------------------------------------------------------
| DISCOUNT PERCENTAGE
|--------------------------------------------------------------------------
*/

function product_discount_percentage(
    array $product
): int {

    if (
        !product_has_discount($product)
    ) {

        return 0;
    }

    $price =
        (int)$product['price'];

    $salePrice =
        (int)$product['sale_price'];

    return (int) round(

        (
            ($price - $salePrice)
            / $price
        ) * 100
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT STOCK STATUS
|--------------------------------------------------------------------------
*/

function product_in_stock(
    array $product
): bool {

    return
        ($product['stock_status']
            ?? '') === 'in_stock';
}

/*
|--------------------------------------------------------------------------
| PRODUCT LABEL
|--------------------------------------------------------------------------
*/

function product_badge(
    array $product
): string {

    if (
        !product_in_stock($product)
    ) {

        return 'Habis';
    }

    if (
        product_has_discount($product)
    ) {

        return
            'Diskon ' .
            product_discount_percentage(
                $product
            ) . '%';
    }

    if (
        (bool)(
            $product['featured']
            ?? false
        )
    ) {

        return 'Unggulan';
    }

    return 'Tersedia';
}

/*
|--------------------------------------------------------------------------
| PRODUCT CATEGORY LIST
|--------------------------------------------------------------------------
*/

function product_categories(): array
{
    $categories = [];

    if (function_exists('business_category_labels')) {
        $categories = array_merge($categories, business_category_labels('catalog', true));
    }

    foreach (all_products() as $product) {

        $category =
            trim(
                (string)(
                    $product['category']
                    ?? ''
                )
            );

        if ($category !== '') {

            $categories[] =
                $category;
        }
    }

    $categories =
        array_unique($categories);

    sort($categories);

    return array_values(
        $categories
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT META TITLE
|--------------------------------------------------------------------------
*/

function product_meta_title(
    array $product
): string {

    return meta_title(

        $product['seo']['title']
        ?? $product['title']
        ?? ''
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT META DESCRIPTION
|--------------------------------------------------------------------------
*/

function product_meta_description(
    array $product
): string {

    return limit_chars(

        strip_tags(

            $product['seo']['description']
            ?? $product['description']
            ?? ''
        ),

        155
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT SCHEMA READY
|--------------------------------------------------------------------------
*/

function product_schema_ready(array $product): array
{
    $seo = $product['seo'] ?? [];
    if (is_string($seo)) {
        $decoded = json_decode($seo, true);
        $seo = is_array($decoded) ? $decoded : [];
    }

    $schemaMode = trim((string)($seo['schema_mode'] ?? 'auto'));
    $allowedModes = ['auto', 'product_offer', 'product_no_offer', 'service', 'course', 'itempage', 'none'];
    if (!in_array($schemaMode, $allowedModes, true)) {
        $schemaMode = 'auto';
    }

    if (in_array($schemaMode, ['none', 'itempage'], true)) {
        return [];
    }

    $schemaType = (string)($seo['schema_type'] ?? product_item_type_definition($product)['schema'] ?? 'Product');
    if (!in_array($schemaType, ['Product', 'IndividualProduct', 'ProductModel', 'Service', 'Course'], true)) {
        $schemaType = (string)(product_item_type_definition($product)['schema'] ?? 'Product');
    }

    if ($schemaMode === 'product_offer' || $schemaMode === 'product_no_offer') {
        $schemaType = in_array($schemaType, ['IndividualProduct', 'ProductModel'], true) ? $schemaType : 'Product';
    } elseif ($schemaMode === 'service') {
        $schemaType = 'Service';
    } elseif ($schemaMode === 'course') {
        $schemaType = 'Course';
    }

    $offerType = (string)($seo['schema_offer_type'] ?? 'Offer');
    if (!in_array($offerType, ['Offer', 'AggregateOffer', 'none'], true)) {
        $offerType = 'Offer';
    }

    $price = product_price($product);
    $isProductLike = in_array($schemaType, ['Product', 'IndividualProduct', 'ProductModel'], true);
    $includeOffer = false;

    if ($schemaMode === 'product_offer') {
        $includeOffer = $isProductLike && $price > 0 && $offerType !== 'none';
    } elseif ($schemaMode === 'product_no_offer') {
        $includeOffer = false;
    } elseif ($schemaMode === 'auto') {
        $includeOffer = $isProductLike && $price > 0 && $offerType !== 'none';
    }

    $availabilityMap = [
        'in_stock' => 'https://schema.org/InStock',
        'out_of_stock' => 'https://schema.org/OutOfStock',
        'preorder' => 'https://schema.org/PreOrder',
    ];
    $availability = $availabilityMap[(string)($product['stock_status'] ?? 'in_stock')] ?? 'https://schema.org/InStock';

    $images = array_values(array_filter(array_unique(array_merge(
        [(string)($product['image'] ?? '')],
        is_array($product['gallery'] ?? null) ? $product['gallery'] : []
    ))));

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $schemaType,
        'name' => $product['title'] ?? '',
        'description' => $product['description'] ?? $product['excerpt'] ?? '',
        'image' => $images,
        'category' => $product['category'] ?? '',
        'additionalType' => product_item_type_label($product),
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => product_permalink($product),
        ],
    ];

    if ($isProductLike) {
        $schema['sku'] = $product['sku'] ?? '';
        $schema['brand'] = [
            '@type' => 'Brand',
            'name' => $seo['schema_brand'] ?? SITE_NAME,
        ];
    }

    if ($includeOffer) {
        $schema['offers'] = [
            '@type' => $offerType === 'none' ? 'Offer' : $offerType,
            'priceCurrency' => $product['currency'] ?? 'IDR',
            'price' => $price,
            'availability' => $availability,
            'itemCondition' => $seo['schema_condition'] ?? 'https://schema.org/NewCondition',
            'url' => product_permalink($product),
        ];
    }

    if (!empty($product['weight']) && $isProductLike) {
        $schema['weight'] = (string)$product['weight'];
    }

    if (!empty($product['location'])) {
        $schema['areaServed'] = [
            '@type' => 'Place',
            'name' => (string)$product['location'],
        ];
    }

    $additionalProperties = [];

    if (!empty($product['features']) && is_array($product['features'])) {
        $additionalProperties = array_map(static fn($feature): array => [
            '@type' => 'PropertyValue',
            'name' => 'Keunggulan',
            'value' => (string)$feature,
        ], $product['features']);
    }

    if (product_is_digital($product)) {
        foreach (product_digital_access_notes($product) as $note) {
            $additionalProperties[] = [
                '@type' => 'PropertyValue',
                'name' => 'Akses Produk Digital',
                'value' => (string)$note,
            ];
        }
    }

    if ($additionalProperties) {
        $schema['additionalProperty'] = $additionalProperties;
    }

    if ($schemaType === 'Service') {
        $schema['provider'] = [
            '@type' => 'Organization',
            'name' => $seo['schema_brand'] ?? SITE_NAME,
        ];
    }

    if ($schemaType === 'Course') {
        $schema['provider'] = [
            '@type' => 'Organization',
            'name' => $seo['schema_brand'] ?? SITE_NAME,
        ];
    }

    $rating = (float)($seo['rating_value'] ?? 0);
    $reviews = (int)($seo['review_count'] ?? 0);
    if ($rating > 0 && $reviews > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => min(5, max(1, $rating)),
            'reviewCount' => $reviews,
        ];
    }

    return array_filter($schema, static fn($value): bool => $value !== '' && $value !== [] && $value !== null);
}

/*
|--------------------------------------------------------------------------
| FEATURED PRODUCT
|--------------------------------------------------------------------------
*/

function featured_product(): ?array
{
    $products =
        featured_products(1);

    return $products[0] ?? null;
}

/*
|--------------------------------------------------------------------------
| PRODUCT GALLERY
|--------------------------------------------------------------------------
*/

function product_gallery(
    array $product
): array {

    return
        $product['gallery']
        ?? [];
}

/*
|--------------------------------------------------------------------------
| PRODUCT FAQ
|--------------------------------------------------------------------------
*/

function product_faq(
    array $product
): array {

    return
        $product['faq']
        ?? [];
}

/*
|--------------------------------------------------------------------------
| PRODUCT ADMIN STORAGE
|--------------------------------------------------------------------------
| Dynamic marketplace product storage. Uses JSON fallback by default and can
| be migrated to MySQL using the products table in database.sql.
|--------------------------------------------------------------------------
*/

function product_storage_path(): string
{
    if (!is_dir(ROOT_PATH . '/storage')) { @mkdir(ROOT_PATH . '/storage', 0775, true); }
    return ROOT_PATH . '/storage/products.json';
}

function product_upload_dir(): string
{
    $dir = ROOT_PATH . '/assets/uploads/products';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir;
}

function product_upload_url(string $filename): string
{
    return asset('uploads/products/' . ltrim($filename, '/'));
}

function product_json_read(): array
{
    $path = product_storage_path();
    if (!is_file($path)) {
        file_put_contents($path, "[]", LOCK_EX);
    }

    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? array_values($data) : [];
}

function product_write_json(array $products): bool
{
    $ok = (bool) file_put_contents(
        product_storage_path(),
        json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    if ($ok) {
        product_mark_starter_content_initialized();
    }

    return $ok;
}

function product_starter_content_state_path(): string
{
    if (!is_dir(STORAGE_PATH)) { @mkdir(STORAGE_PATH, 0775, true); }
    return STORAGE_PATH . '/starter-content-state.json';
}

function product_starter_content_state(): array
{
    $path = product_starter_content_state_path();
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function product_write_starter_content_state(array $state): bool
{
    return (bool)file_put_contents(
        product_starter_content_state_path(),
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function product_mark_starter_content_initialized(): void
{
    $state = product_starter_content_state();
    $collections = is_array($state['collections'] ?? null) ? $state['collections'] : [];
    $collections['products'] = [
        'initialized' => true,
        'updated_at' => date('c'),
    ];
    $state['collections'] = $collections;
    $state['updated_at'] = date('c');
    @product_write_starter_content_state($state);
}

function product_starter_content_initialized(): bool
{
    $state = product_starter_content_state();
    $products = is_array($state['collections']['products'] ?? null) ? $state['collections']['products'] : [];
    return !empty($products['initialized']);
}

function product_runtime_bootstrap_from_seed_if_needed(): array
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('products')) {
        return ['created' => 0, 'skipped' => 0, 'initialized' => product_starter_content_initialized(), 'storage' => 'mysql'];
    }

    $existing = product_json_read();
    if ($existing !== []) {
        product_mark_starter_content_initialized();
        return ['created' => 0, 'skipped' => count($existing), 'initialized' => true, 'storage' => 'json'];
    }

    if (product_starter_content_initialized()) {
        return ['created' => 0, 'skipped' => 0, 'initialized' => true, 'storage' => 'json'];
    }

    $created = [];
    $now = date('Y-m-d H:i:s');

    foreach (product_seed_products() as $index => $seed) {
        $seed = product_with_source($seed, 'starter-content');
        $seed['source'] = 'starter-content';
        $seed['_source'] = 'starter-content';
        $seed['id'] = (int)($seed['id'] ?? 0) > 0 ? (int)$seed['id'] : (time() + $index + 1);
        $seed['created_at'] = $seed['created_at'] ?? $now;
        $seed['updated_at'] = $now;
        $created[] = $seed;
    }

    if ($created !== [] && product_write_json($created)) {
        return ['created' => count($created), 'skipped' => 0, 'initialized' => true, 'storage' => 'json'];
    }

    return ['created' => 0, 'skipped' => count($created), 'initialized' => false, 'storage' => 'json'];
}

function product_db_error(string $action, Throwable $e): void
{
    error_log('[PRODUCT_DB_' . strtoupper($action) . '] ' . $e->getMessage());
}

function product_db_columns(): array
{
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    if (!function_exists('db_available') || !db_available()) {
        $columns = [];
        return $columns;
    }

    try {
        $stmt = db()->query('SHOW COLUMNS FROM products');
        $rows = $stmt ? $stmt->fetchAll() : [];
        $columns = array_values(array_filter(array_map(
            static fn(array $row): string => (string)($row['Field'] ?? ''),
            $rows ?: []
        )));
    } catch (Throwable $e) {
        product_db_error('columns', $e);
        $columns = [];
    }

    return $columns;
}

function product_db_available_column(string $column): bool
{
    return in_array($column, product_db_columns(), true);
}

function product_db_insert_fields(): array
{
    $preferred = [
        'id',
        'source',
        'sku',
        'title',
        'slug',
        'category',
        'item_type_key',
        'type',
        'subcategory',
        'animal_type',
        'breed',
        'tier',
        'excerpt',
        'description',
        'content',
        'price',
        'sale_price',
        'currency',
        'stock',
        'stock_status',
        'stock_tracking_enabled',
        'stock_reserved_manual',
        'stock_low_threshold',
        'stock_allow_backorder',
        'stock_auto_status',
        'stock_note',
        'weight',
        'age',
        'location',
        'shipping_origin_id',
        'shipping_origin_note',
        'shipping_rule_mode',
        'payment_rule_mode',
        'allowed_payment_gateways',
        'checkout_rule_note',
        'preorder_note',
        'preorder_eta',
        'image',
        'image_alt',
        'gallery',
        'features',
        'featured',
        'status',
        'whatsapp_text',
        'cta_primary_label',
        'cta_secondary_label',
        'digital_delivery_type',
        'digital_access_mode',
        'digital_file_url',
        'digital_access_url',
        'digital_instructions',
        'download_limit',
        'access_duration_days',
        'member_area_enabled',
        'course_modules',
        'course_modules_raw',
        'license_enabled',
        'license_type',
        'license_seats',
        'license_activation_limit',
        'license_duration_days',
        'license_note',
        'license_validation_mode',
        'license_domain_lock',
        'central_license_product_id',
        'subscription_enabled',
        'subscription_billing_cycle',
        'subscription_duration_days',
        'subscription_grace_days',
        'subscription_renewal_mode',
        'subscription_note',
        'published_at',
        'created_at',
        'updated_at',
        'seo',
    ];

    return array_values(array_filter(
        $preferred,
        static fn(string $field): bool => product_db_available_column($field)
    ));
}

function product_db_update_fields(): array
{
    return array_values(array_filter(
        product_db_insert_fields(),
        static fn(string $field): bool => !in_array($field, ['id', 'created_at'], true)
    ));
}

function product_db_payload(array $payload): array
{
    $data = $payload;

    $data['id'] = max(0, (int)($data['id'] ?? 0));
    $data['source'] = trim((string)($data['source'] ?? $data['_source'] ?? 'admin')) ?: 'admin';
    $data['title'] = trim((string)($data['title'] ?? ''));
    $data['slug'] = trim((string)($data['slug'] ?? ''));
    $data['category'] = trim((string)($data['category'] ?? ''));
    $data['item_type_key'] = product_item_type_key($data);
    $data['type'] = product_item_type_label($data['item_type_key']);
    $data['subcategory'] = trim((string)($data['subcategory'] ?? $data['tier'] ?? ''));
    $data['animal_type'] = product_item_type_label($data['item_type_key']);
    $data['breed'] = trim((string)($data['breed'] ?? ''));
    $data['tier'] = trim((string)($data['tier'] ?? $data['subcategory'] ?? ''));
    $data['excerpt'] = trim((string)($data['excerpt'] ?? ''));
    $data['description'] = trim((string)($data['description'] ?? ''));
    $data['content'] = (string)($data['content'] ?? '');
    $data['price'] = max(0, (int)($data['price'] ?? 0));
    $data['sale_price'] = ($data['sale_price'] ?? null) !== null && $data['sale_price'] !== '' ? max(0, (int)$data['sale_price']) : null;
    $data['currency'] = trim((string)($data['currency'] ?? 'IDR')) ?: 'IDR';
    $data['stock'] = trim((string)($data['stock'] ?? ''));
    $data['stock_status'] = trim((string)($data['stock_status'] ?? 'in_stock')) ?: 'in_stock';
    $data['stock_tracking_enabled'] = !empty($data['stock_tracking_enabled']) ? 1 : 0;
    $data['stock_reserved_manual'] = function_exists('inventory_int') ? inventory_int($data['stock_reserved_manual'] ?? 0, 0) : max(0, (int)($data['stock_reserved_manual'] ?? 0));
    $data['stock_low_threshold'] = function_exists('inventory_int') ? inventory_int($data['stock_low_threshold'] ?? 3, 3) : max(0, (int)($data['stock_low_threshold'] ?? 3));
    $data['stock_allow_backorder'] = !empty($data['stock_allow_backorder']) ? 1 : 0;
    $data['stock_auto_status'] = !array_key_exists('stock_auto_status', $data) || !empty($data['stock_auto_status']) ? 1 : 0;
    $data['stock_note'] = function_exists('inventory_multiline_clean') ? inventory_multiline_clean($data['stock_note'] ?? '', 700) : trim((string)($data['stock_note'] ?? ''));
    $data['weight'] = trim((string)($data['weight'] ?? ''));
    $data['age'] = trim((string)($data['age'] ?? ''));
    $data['location'] = trim((string)($data['location'] ?? ''));
    $data['shipping_origin_id'] = trim((string)($data['shipping_origin_id'] ?? ''));
    $data['shipping_origin_note'] = trim((string)($data['shipping_origin_note'] ?? ''));
    $shippingOptions = function_exists('commerce_shipping_policy_options') ? commerce_shipping_policy_options() : ['global' => ''];
    $paymentOptions = function_exists('commerce_payment_policy_options') ? commerce_payment_policy_options() : ['global' => ''];
    $data['shipping_rule_mode'] = array_key_exists((string)($data['shipping_rule_mode'] ?? 'global'), $shippingOptions) ? (string)$data['shipping_rule_mode'] : 'global';
    $data['payment_rule_mode'] = array_key_exists((string)($data['payment_rule_mode'] ?? 'global'), $paymentOptions) ? (string)$data['payment_rule_mode'] : 'global';
    $data['allowed_payment_gateways'] = function_exists('commerce_gateway_list_normalize') ? commerce_gateway_list_normalize($data['allowed_payment_gateways'] ?? []) : (array)($data['allowed_payment_gateways'] ?? []);
    $data['checkout_rule_note'] = function_exists('commerce_rule_multiline_clean') ? commerce_rule_multiline_clean((string)($data['checkout_rule_note'] ?? ''), 900) : trim((string)($data['checkout_rule_note'] ?? ''));
    $data['preorder_note'] = function_exists('commerce_rule_multiline_clean') ? commerce_rule_multiline_clean((string)($data['preorder_note'] ?? ''), 900) : trim((string)($data['preorder_note'] ?? ''));
    $data['preorder_eta'] = function_exists('commerce_rule_clean') ? commerce_rule_clean((string)($data['preorder_eta'] ?? ''), 120) : trim((string)($data['preorder_eta'] ?? ''));
    $data['image'] = trim((string)($data['image'] ?? ''));
    $data['image_alt'] = trim((string)($data['image_alt'] ?? $data['title'] ?? ''));
    $data['featured'] = !empty($data['featured']) ? 1 : 0;
    $data['status'] = trim((string)($data['status'] ?? 'published')) ?: 'published';
    $data['whatsapp_text'] = trim((string)($data['whatsapp_text'] ?? ''));
    $data['cta_primary_label'] = trim((string)($data['cta_primary_label'] ?? ''));
    $data['cta_secondary_label'] = trim((string)($data['cta_secondary_label'] ?? ''));
    $data['digital_delivery_type'] = trim((string)($data['digital_delivery_type'] ?? ''));
    $data['digital_access_mode'] = trim((string)($data['digital_access_mode'] ?? ''));
    $data['digital_file_url'] = trim((string)($data['digital_file_url'] ?? ''));
    $data['digital_access_url'] = trim((string)($data['digital_access_url'] ?? ''));
    $data['digital_instructions'] = trim((string)($data['digital_instructions'] ?? ''));
    $data['download_limit'] = max(0, (int)($data['download_limit'] ?? 0));
    $data['access_duration_days'] = max(0, (int)($data['access_duration_days'] ?? 0));
    $data['member_area_enabled'] = !empty($data['member_area_enabled']) ? 1 : 0;
    $data['course_modules_raw'] = function_exists('member_access_multiline_clean') ? member_access_multiline_clean((string)($data['course_modules_raw'] ?? ''), 5000) : trim((string)($data['course_modules_raw'] ?? ''));
    $data['course_modules'] = function_exists('member_access_parse_course_modules') ? member_access_parse_course_modules($data['course_modules_raw']) : [];
    $data['license_enabled'] = !empty($data['license_enabled']) ? 1 : 0;
    $data['license_type'] = trim((string)($data['license_type'] ?? 'single_site'));
    $data['license_seats'] = max(1, (int)($data['license_seats'] ?? 1));
    $data['license_activation_limit'] = max(1, (int)($data['license_activation_limit'] ?? 1));
    $data['license_duration_days'] = max(0, (int)($data['license_duration_days'] ?? 365));
    $data['license_note'] = function_exists('member_access_multiline_clean') ? member_access_multiline_clean((string)($data['license_note'] ?? ''), 900) : trim((string)($data['license_note'] ?? ''));
    $data['license_validation_mode'] = trim((string)($data['license_validation_mode'] ?? 'global')) ?: 'global';
    if (!in_array($data['license_validation_mode'], ['global', 'local', 'central', 'hybrid'], true)) {
        $data['license_validation_mode'] = 'global';
    }
    $data['license_domain_lock'] = !empty($data['license_domain_lock']) ? 1 : 0;
    $data['central_license_product_id'] = trim((string)($data['central_license_product_id'] ?? ''));
    $data['subscription_enabled'] = !empty($data['subscription_enabled']) ? 1 : 0;
    $cycleOptions = function_exists('subscription_cycle_options') ? subscription_cycle_options() : ['none' => '', 'monthly' => '', 'six_months' => '', 'yearly' => ''];
    $data['subscription_billing_cycle'] = array_key_exists((string)($data['subscription_billing_cycle'] ?? 'none'), $cycleOptions) ? (string)$data['subscription_billing_cycle'] : 'none';
    $data['subscription_duration_days'] = max(0, (int)($data['subscription_duration_days'] ?? 0));
    $data['subscription_grace_days'] = max(0, (int)($data['subscription_grace_days'] ?? 3));
    $data['subscription_renewal_mode'] = trim((string)($data['subscription_renewal_mode'] ?? 'manual_reminder')) ?: 'manual_reminder';
    $data['subscription_note'] = function_exists('member_access_multiline_clean') ? member_access_multiline_clean((string)($data['subscription_note'] ?? ''), 900) : trim((string)($data['subscription_note'] ?? ''));
    $data['published_at'] = trim((string)($data['published_at'] ?? date('Y-m-d H:i:s'))) ?: date('Y-m-d H:i:s');
    $data['created_at'] = trim((string)($data['created_at'] ?? date('Y-m-d H:i:s'))) ?: date('Y-m-d H:i:s');
    $data['updated_at'] = trim((string)($data['updated_at'] ?? date('Y-m-d H:i:s'))) ?: date('Y-m-d H:i:s');

    $seoFallback = is_array($data['seo'] ?? null) ? $data['seo'] : [];
    if (!product_db_available_column('shipping_origin_id')) {
        $seoFallback['shipping_origin_id'] = $data['shipping_origin_id'] ?? '';
        $seoFallback['shipping_origin_note'] = $data['shipping_origin_note'] ?? '';
    }
    foreach (['shipping_rule_mode', 'payment_rule_mode', 'allowed_payment_gateways', 'checkout_rule_note', 'preorder_note', 'preorder_eta', 'stock_tracking_enabled', 'stock_reserved_manual', 'stock_low_threshold', 'stock_allow_backorder', 'stock_auto_status', 'stock_note', 'course_modules', 'course_modules_raw', 'license_enabled', 'license_type', 'license_seats', 'license_activation_limit', 'license_duration_days', 'license_note', 'license_validation_mode', 'license_domain_lock', 'central_license_product_id', 'subscription_enabled', 'subscription_billing_cycle', 'subscription_duration_days', 'subscription_grace_days', 'subscription_renewal_mode', 'subscription_note'] as $policyField) {
        if (!product_db_available_column($policyField)) {
            $seoFallback[$policyField] = $data[$policyField] ?? ($policyField === 'allowed_payment_gateways' ? [] : '');
        }
    }
    $data['seo'] = $seoFallback;

    foreach (['gallery', 'features', 'allowed_payment_gateways', 'course_modules', 'seo'] as $jsonKey) {
        $value = $data[$jsonKey] ?? [];

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            $value = [];
        }

        $data[$jsonKey] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $data;
}

function product_managed_products(): array
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('products')) {
        try {
            $stmt = db()->query('SELECT * FROM products ORDER BY created_at DESC, id DESC');
            $rows = $stmt ? $stmt->fetchAll() : [];

            return array_map('product_normalize_db_row', $rows ?: []);
        } catch (Throwable $e) {
            product_db_error('select', $e);
            return [];
        }
    }

    product_runtime_bootstrap_from_seed_if_needed();

    return product_json_read();
}

function product_normalize_db_row(array $row): array
{
    foreach (['gallery', 'features', 'faq', 'allowed_payment_gateways', 'course_modules', 'seo'] as $jsonKey) {
        if (isset($row[$jsonKey]) && is_string($row[$jsonKey])) {
            $decoded = json_decode($row[$jsonKey], true);
            $row[$jsonKey] = is_array($decoded) ? $decoded : [];
        }
    }

    $row['id'] = (int)($row['id'] ?? 0);
    $row['source'] = trim((string)($row['source'] ?? 'admin')) ?: 'admin';
    $row['_source'] = $row['source'];
    $row['price'] = (int)($row['price'] ?? 0);
    $row['sale_price'] = ($row['sale_price'] ?? null) !== null ? (int)$row['sale_price'] : null;
    $row['featured'] = (bool)($row['featured'] ?? false);
    $row['item_type_key'] = product_item_type_key($row);
    $row['type'] = trim((string)($row['type'] ?? product_item_type_label($row))) ?: product_item_type_label($row);
    $row['animal_type'] = trim((string)($row['animal_type'] ?? $row['type'] ?? product_item_type_label($row))) ?: product_item_type_label($row);
    $row['member_area_enabled'] = !empty($row['member_area_enabled']);
    $row['download_limit'] = max(0, (int)($row['download_limit'] ?? 0));
    $row['access_duration_days'] = max(0, (int)($row['access_duration_days'] ?? 0));
    $seoExtras = is_array($row['seo'] ?? null) ? $row['seo'] : [];
    $row['stock_tracking_enabled'] = !empty($row['stock_tracking_enabled']) || !empty($seoExtras['stock_tracking_enabled']);
    $row['stock_reserved_manual'] = max(0, (int)($row['stock_reserved_manual'] ?? $seoExtras['stock_reserved_manual'] ?? 0));
    $row['stock_low_threshold'] = max(0, (int)($row['stock_low_threshold'] ?? $seoExtras['stock_low_threshold'] ?? 3));
    $row['stock_allow_backorder'] = !empty($row['stock_allow_backorder']) || !empty($seoExtras['stock_allow_backorder']);
    $row['stock_auto_status'] = !array_key_exists('stock_auto_status', $row) ? (bool)($seoExtras['stock_auto_status'] ?? true) : !empty($row['stock_auto_status']);
    $row['stock_note'] = trim((string)($row['stock_note'] ?? $seoExtras['stock_note'] ?? ''));
    $row['course_modules_raw'] = trim((string)($row['course_modules_raw'] ?? $seoExtras['course_modules_raw'] ?? ''));
    $row['course_modules'] = is_array($row['course_modules'] ?? null) && $row['course_modules'] ? $row['course_modules'] : (is_array($seoExtras['course_modules'] ?? null) ? $seoExtras['course_modules'] : []);
    $row['license_enabled'] = !empty($row['license_enabled']) || !empty($seoExtras['license_enabled']);
    $row['license_type'] = trim((string)($row['license_type'] ?? $seoExtras['license_type'] ?? 'single_site'));
    $row['license_seats'] = max(1, (int)($row['license_seats'] ?? $seoExtras['license_seats'] ?? 1));
    $row['license_activation_limit'] = max(1, (int)($row['license_activation_limit'] ?? $seoExtras['license_activation_limit'] ?? 1));
    $row['license_duration_days'] = max(0, (int)($row['license_duration_days'] ?? $seoExtras['license_duration_days'] ?? 365));
    $row['license_note'] = trim((string)($row['license_note'] ?? $seoExtras['license_note'] ?? ''));
    $row['license_validation_mode'] = trim((string)($row['license_validation_mode'] ?? $seoExtras['license_validation_mode'] ?? 'global')) ?: 'global';
    $row['license_domain_lock'] = !empty($row['license_domain_lock']) || !empty($seoExtras['license_domain_lock']);
    $row['central_license_product_id'] = trim((string)($row['central_license_product_id'] ?? $seoExtras['central_license_product_id'] ?? ''));
    $row['subscription_enabled'] = !empty($row['subscription_enabled']) || !empty($seoExtras['subscription_enabled']);
    $row['subscription_billing_cycle'] = trim((string)($row['subscription_billing_cycle'] ?? $seoExtras['subscription_billing_cycle'] ?? 'none')) ?: 'none';
    $row['subscription_duration_days'] = max(0, (int)($row['subscription_duration_days'] ?? $seoExtras['subscription_duration_days'] ?? 0));
    $row['subscription_grace_days'] = max(0, (int)($row['subscription_grace_days'] ?? $seoExtras['subscription_grace_days'] ?? 3));
    $row['subscription_renewal_mode'] = trim((string)($row['subscription_renewal_mode'] ?? $seoExtras['subscription_renewal_mode'] ?? 'manual_reminder')) ?: 'manual_reminder';
    $row['subscription_note'] = trim((string)($row['subscription_note'] ?? $seoExtras['subscription_note'] ?? ''));
    $row['status'] = trim((string)($row['status'] ?? 'published')) ?: 'published';
    $row['shipping_origin_id'] = trim((string)($row['shipping_origin_id'] ?? ''));
    $row['shipping_origin_note'] = trim((string)($row['shipping_origin_note'] ?? ''));
    if ($row['shipping_origin_id'] === '' && is_array($row['seo'] ?? null)) {
        $row['shipping_origin_id'] = trim((string)($row['seo']['shipping_origin_id'] ?? ''));
        $row['shipping_origin_note'] = trim((string)($row['seo']['shipping_origin_note'] ?? ''));
    }
    $seoPolicy = is_array($row['seo'] ?? null) ? $row['seo'] : [];
    $row['shipping_rule_mode'] = trim((string)($row['shipping_rule_mode'] ?? $seoPolicy['shipping_rule_mode'] ?? 'global')) ?: 'global';
    $row['payment_rule_mode'] = trim((string)($row['payment_rule_mode'] ?? $seoPolicy['payment_rule_mode'] ?? 'global')) ?: 'global';
    $row['allowed_payment_gateways'] = function_exists('commerce_gateway_list_normalize')
        ? commerce_gateway_list_normalize($row['allowed_payment_gateways'] ?? $seoPolicy['allowed_payment_gateways'] ?? [])
        : (array)($row['allowed_payment_gateways'] ?? $seoPolicy['allowed_payment_gateways'] ?? []);
    $row['checkout_rule_note'] = trim((string)($row['checkout_rule_note'] ?? $seoPolicy['checkout_rule_note'] ?? ''));
    $row['preorder_note'] = trim((string)($row['preorder_note'] ?? $seoPolicy['preorder_note'] ?? ''));
    $row['preorder_eta'] = trim((string)($row['preorder_eta'] ?? $seoPolicy['preorder_eta'] ?? ''));

    return $row;
}

function product_next_id(): int
{
    $ids = array_map(static fn(array $p): int => (int)($p['id'] ?? 0), product_managed_products());
    return max([time(), ...$ids]) + 1;
}

function product_admin_find(int $id): ?array
{
    foreach (product_managed_products() as $product) {
        if ((int)($product['id'] ?? 0) === $id) {
            return $product;
        }
    }

    return get_product_by_id($id);
}

function product_slug_unique(string $slug, ?int $ignoreId = null): string
{
    $base = slugify($slug ?: 'produk');
    $candidate = $base;
    $counter = 2;

    while (true) {
        $exists = false;

        foreach (all_products() as $product) {
            if (($product['slug'] ?? '') === $candidate && (int)($product['id'] ?? 0) !== (int)$ignoreId) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            return $candidate;
        }

        $candidate = $base . '-' . $counter;
        $counter++;
    }
}

function product_payload_prepare(array $input, ?array $current = null): array
{
    $title = trim((string)($input['title'] ?? ''));
    $rawSlug = trim((string)($input['slug'] ?? ''));
    $slugSource = $rawSlug !== '' ? $rawSlug : $title;
    $slug = product_slug_unique($slugSource, $current['id'] ?? null);
    $galleryRaw = trim((string)($input['gallery_raw'] ?? ''));
    $gallery = array_values(array_filter(array_map('trim', preg_split('/\r
|\r|
/', $galleryRaw))));

    $featuresRaw = trim((string)($input['features_raw'] ?? ''));
    $features = array_values(array_filter(array_map('trim', preg_split('/\r
|\r|
/', $featuresRaw))));

    $price = (int) preg_replace('/\D+/', '', (string)($input['price'] ?? '0'));
    $sale = preg_replace('/\D+/', '', (string)($input['sale_price'] ?? ''));

    $image = trim((string)($input['image_url'] ?? '')) ?: (string)($current['image'] ?? '');
    $itemTypeKey = product_item_type_key($input['item_type_key'] ?? $input['animal_type'] ?? $current['item_type_key'] ?? $current['animal_type'] ?? 'Produk Fisik');
    $itemTypeLabel = product_item_type_label($itemTypeKey);
    $tier = trim((string)($input['tier'] ?? ($input['subcategory'] ?? ($current['tier'] ?? $current['subcategory'] ?? 'Medium'))));
    $digitalAccessMode = trim((string)($input['digital_access_mode'] ?? ($current['digital_access_mode'] ?? 'after_payment')));
    if (!in_array($digitalAccessMode, ['after_payment', 'direct_download', 'access_link', 'member_area'], true)) {
        $digitalAccessMode = 'after_payment';
    }
    $digitalDeliveryType = trim((string)($input['digital_delivery_type'] ?? ($current['digital_delivery_type'] ?? $itemTypeKey)));
    $downloadLimit = max(0, (int)preg_replace('/\D+/', '', (string)($input['download_limit'] ?? ($current['download_limit'] ?? '0'))));
    $accessDuration = max(0, (int)preg_replace('/\D+/', '', (string)($input['access_duration_days'] ?? ($current['access_duration_days'] ?? '0'))));
    $courseModulesRaw = (string)($input['course_modules_raw'] ?? ($current['course_modules_raw'] ?? ''));
    $courseModules = function_exists('member_access_parse_course_modules') ? member_access_parse_course_modules($courseModulesRaw) : [];
    $licenseType = trim((string)($input['license_type'] ?? ($current['license_type'] ?? 'single_site')));
    $licenseSeats = max(1, (int)preg_replace('/\D+/', '', (string)($input['license_seats'] ?? ($current['license_seats'] ?? '1'))));
    $licenseActivationLimit = max(1, (int)preg_replace('/\D+/', '', (string)($input['license_activation_limit'] ?? ($current['license_activation_limit'] ?? '1'))));
    $licenseDurationDays = max(0, (int)preg_replace('/\D+/', '', (string)($input['license_duration_days'] ?? ($current['license_duration_days'] ?? '365'))));
    $licenseValidationMode = trim((string)($input['license_validation_mode'] ?? ($current['license_validation_mode'] ?? 'global')));
    if (!in_array($licenseValidationMode, ['global', 'local', 'central', 'hybrid'], true)) {
        $licenseValidationMode = 'global';
    }
    $subscriptionCycle = trim((string)($input['subscription_billing_cycle'] ?? ($current['subscription_billing_cycle'] ?? 'none')));
    $subscriptionCycles = function_exists('subscription_cycle_options') ? subscription_cycle_options() : ['none' => '', 'monthly' => '', 'six_months' => '', 'yearly' => ''];
    if (!array_key_exists($subscriptionCycle, $subscriptionCycles)) {
        $subscriptionCycle = 'none';
    }
    $subscriptionDurationDays = max(0, (int)preg_replace('/\D+/', '', (string)($input['subscription_duration_days'] ?? ($current['subscription_duration_days'] ?? '0'))));
    $subscriptionGraceDays = max(0, (int)preg_replace('/\D+/', '', (string)($input['subscription_grace_days'] ?? ($current['subscription_grace_days'] ?? '3'))));

    return [
        'id' => (int)($current['id'] ?? product_next_id()),
        'source' => trim((string)($current['source'] ?? $current['_source'] ?? 'admin')) ?: 'admin',
        '_source' => trim((string)($current['_source'] ?? $current['source'] ?? 'admin')) ?: 'admin',
        'sku' => trim((string)($input['sku'] ?? ($current['sku'] ?? ''))),
        'title' => $title,
        'slug' => $slug,
        'category' => trim((string)($input['category'] ?? ($current['category'] ?? product_item_type_label($itemTypeKey)))),
        'item_type_key' => $itemTypeKey,
        'type' => $itemTypeLabel,
        'subcategory' => trim((string)($input['subcategory'] ?? ($current['subcategory'] ?? $tier))),
        'animal_type' => $itemTypeLabel,
        'breed' => trim((string)($input['breed'] ?? ($current['breed'] ?? ''))),
        'tier' => $tier,
        'excerpt' => trim((string)($input['excerpt'] ?? ($current['excerpt'] ?? ''))),
        'description' => trim((string)($input['description'] ?? ($current['description'] ?? ''))),
        'content' => (string)($input['content'] ?? ($current['content'] ?? '')),
        'price' => $price,
        'sale_price' => $sale !== '' ? (int)$sale : null,
        'currency' => 'IDR',
        'stock' => trim((string)($input['stock'] ?? ($current['stock'] ?? '1'))),
        'stock_status' => trim((string)($input['stock_status'] ?? ($current['stock_status'] ?? 'in_stock'))) ?: 'in_stock',
        'stock_tracking_enabled' => array_key_exists('stock_tracking_enabled', $input) ? !empty($input['stock_tracking_enabled']) : !empty($current['stock_tracking_enabled']),
        'stock_reserved_manual' => function_exists('inventory_int') ? inventory_int($input['stock_reserved_manual'] ?? ($current['stock_reserved_manual'] ?? '0'), 0) : max(0, (int)($input['stock_reserved_manual'] ?? ($current['stock_reserved_manual'] ?? 0))),
        'stock_low_threshold' => function_exists('inventory_int') ? inventory_int($input['stock_low_threshold'] ?? ($current['stock_low_threshold'] ?? '3'), 3) : max(0, (int)($input['stock_low_threshold'] ?? ($current['stock_low_threshold'] ?? 3))),
        'stock_allow_backorder' => array_key_exists('stock_allow_backorder', $input) ? !empty($input['stock_allow_backorder']) : !empty($current['stock_allow_backorder']),
        'stock_auto_status' => array_key_exists('stock_auto_status', $input) ? !empty($input['stock_auto_status']) : (!array_key_exists('stock_auto_status', $current ?? []) || !empty($current['stock_auto_status'])),
        'stock_note' => trim((string)($input['stock_note'] ?? ($current['stock_note'] ?? ''))),
        'weight' => trim((string)($input['weight'] ?? ($current['weight'] ?? ''))),
        'age' => trim((string)($input['age'] ?? ($current['age'] ?? ''))),
        'location' => trim((string)($input['location'] ?? ($current['location'] ?? 'Indonesia'))),
        'shipping_origin_id' => trim((string)($input['shipping_origin_id'] ?? ($current['shipping_origin_id'] ?? ''))),
        'shipping_origin_note' => trim((string)($input['shipping_origin_note'] ?? ($current['shipping_origin_note'] ?? ''))),
        'shipping_rule_mode' => trim((string)($input['shipping_rule_mode'] ?? ($current['shipping_rule_mode'] ?? 'global'))) ?: 'global',
        'payment_rule_mode' => trim((string)($input['payment_rule_mode'] ?? ($current['payment_rule_mode'] ?? 'global'))) ?: 'global',
        'allowed_payment_gateways' => function_exists('commerce_gateway_list_normalize') ? commerce_gateway_list_normalize($input['allowed_payment_gateways'] ?? ($current['allowed_payment_gateways'] ?? [])) : (array)($input['allowed_payment_gateways'] ?? ($current['allowed_payment_gateways'] ?? [])),
        'checkout_rule_note' => trim((string)($input['checkout_rule_note'] ?? ($current['checkout_rule_note'] ?? ''))),
        'preorder_note' => trim((string)($input['preorder_note'] ?? ($current['preorder_note'] ?? ''))),
        'preorder_eta' => trim((string)($input['preorder_eta'] ?? ($current['preorder_eta'] ?? ''))),
        'image' => $image,
        'image_alt' => trim((string)($input['image_alt'] ?? ($current['image_alt'] ?? $title))),
        'gallery' => $gallery,
        'features' => $features,
        'featured' => !empty($input['featured']),
        'status' => trim((string)($input['status'] ?? ($current['status'] ?? 'published'))) ?: 'published',
        'whatsapp_text' => trim((string)($input['whatsapp_text'] ?? ($current['whatsapp_text'] ?? 'Halo, saya tertarik dengan ' . $title))),
        'cta_primary_label' => trim((string)($input['cta_primary_label'] ?? ($current['cta_primary_label'] ?? ''))),
        'cta_secondary_label' => trim((string)($input['cta_secondary_label'] ?? ($current['cta_secondary_label'] ?? ''))),
        'digital_delivery_type' => $digitalDeliveryType,
        'digital_access_mode' => $digitalAccessMode,
        'digital_file_url' => trim((string)($input['digital_file_url'] ?? ($current['digital_file_url'] ?? ''))),
        'digital_access_url' => trim((string)($input['digital_access_url'] ?? ($current['digital_access_url'] ?? ''))),
        'digital_instructions' => trim((string)($input['digital_instructions'] ?? ($current['digital_instructions'] ?? ''))),
        'download_limit' => $downloadLimit,
        'access_duration_days' => $accessDuration,
        'member_area_enabled' => !empty($input['member_area_enabled']) || !empty($current['member_area_enabled']),
        'course_modules_raw' => trim($courseModulesRaw),
        'course_modules' => $courseModules,
        'license_enabled' => !empty($input['license_enabled']) || !empty($current['license_enabled']),
        'license_type' => $licenseType !== '' ? $licenseType : 'single_site',
        'license_seats' => $licenseSeats,
        'license_activation_limit' => $licenseActivationLimit,
        'license_duration_days' => $licenseDurationDays,
        'license_note' => trim((string)($input['license_note'] ?? ($current['license_note'] ?? ''))),
        'license_validation_mode' => $licenseValidationMode,
        'license_domain_lock' => !empty($input['license_domain_lock']) || !empty($current['license_domain_lock']),
        'central_license_product_id' => trim((string)($input['central_license_product_id'] ?? ($current['central_license_product_id'] ?? ''))),
        'subscription_enabled' => !empty($input['subscription_enabled']) || !empty($current['subscription_enabled']),
        'subscription_billing_cycle' => $subscriptionCycle,
        'subscription_duration_days' => $subscriptionDurationDays,
        'subscription_grace_days' => $subscriptionGraceDays,
        'subscription_renewal_mode' => trim((string)($input['subscription_renewal_mode'] ?? ($current['subscription_renewal_mode'] ?? 'manual_reminder'))),
        'subscription_note' => trim((string)($input['subscription_note'] ?? ($current['subscription_note'] ?? ''))),
        'published_at' => trim((string)($input['published_at'] ?? ($current['published_at'] ?? date('Y-m-d H:i:s')))),
        'created_at' => (string)($current['created_at'] ?? date('Y-m-d H:i:s')),
        'updated_at' => date('Y-m-d H:i:s'),
        'seo' => [
            'title' => trim((string)($input['meta_title'] ?? ($current['seo']['title'] ?? $title))),
            'description' => trim((string)($input['meta_description'] ?? ($current['seo']['description'] ?? ($input['description'] ?? '')))),
            'keywords' => array_values(array_filter(array_map('trim', explode(',', (string)($input['meta_keywords'] ?? implode(', ', $current['seo']['keywords'] ?? ['produk umkm', 'layanan umkm'])))))),
            'schema_mode' => in_array(trim((string)($input['schema_mode'] ?? ($current['seo']['schema_mode'] ?? 'auto'))), ['auto','product_offer','product_no_offer','service','course','itempage','none'], true) ? trim((string)($input['schema_mode'] ?? ($current['seo']['schema_mode'] ?? 'auto'))) : 'auto',
            'schema_type' => trim((string)($input['schema_type'] ?? ($current['seo']['schema_type'] ?? product_item_type_definition($itemTypeKey)['schema'] ?? 'Product'))),
            'schema_brand' => trim((string)($input['schema_brand'] ?? ($current['seo']['schema_brand'] ?? SITE_NAME))),
            'schema_offer_type' => in_array(trim((string)($input['schema_offer_type'] ?? ($current['seo']['schema_offer_type'] ?? 'Offer'))), ['Offer','AggregateOffer','none'], true) ? trim((string)($input['schema_offer_type'] ?? ($current['seo']['schema_offer_type'] ?? 'Offer'))) : 'Offer',
            'schema_condition' => trim((string)($input['schema_condition'] ?? ($current['seo']['schema_condition'] ?? 'https://schema.org/NewCondition'))),
            'rating_value' => trim((string)($input['rating_value'] ?? ($current['seo']['rating_value'] ?? ''))),
            'review_count' => trim((string)($input['review_count'] ?? ($current['seo']['review_count'] ?? ''))),
        ],
    ];
}

function product_create(array $payload): int
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('products')) {
        try {
            $data = product_db_payload($payload);
            $fields = product_db_insert_fields();

            if (!$fields) {
                throw new RuntimeException('Tabel products tidak memiliki kolom yang bisa diisi.');
            }

            $cols = implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $fields));
            $holders = ':' . implode(', :', $fields);

            $stmt = db()->prepare("INSERT INTO products ($cols) VALUES ($holders)");

            foreach ($fields as $field) {
                $stmt->bindValue(':' . $field, $data[$field] ?? null);
            }

            $stmt->execute();

            $insertId = (int) db()->lastInsertId();
            $createdId = $insertId > 0 ? $insertId : (int)($payload['id'] ?? 0);
            if ($createdId > 0 && function_exists('activity_log_record')) {
                activity_log_record('create', 'product', $createdId, 'Item dibuat.', ['title' => $payload['title'] ?? '', 'slug' => $payload['slug'] ?? '', 'storage' => 'database']);
            }
            return $createdId;
        } catch (Throwable $e) {
            product_db_error('insert', $e);
            return 0;
        }
    }

    $products = product_json_read();
    $products[] = $payload;

    $ok = product_write_json($products);
    if ($ok && function_exists('activity_log_record')) {
        activity_log_record('create', 'product', (int)$payload['id'], 'Item dibuat.', ['title' => $payload['title'] ?? '', 'slug' => $payload['slug'] ?? '', 'storage' => 'json']);
    }

    return $ok ? (int)$payload['id'] : 0;
}

function product_update(int $id, array $payload): bool
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('products')) {
        try {
            $data = product_db_payload($payload);
            $fields = product_db_update_fields();

            if (!$fields) {
                throw new RuntimeException('Tabel products tidak memiliki kolom yang bisa diperbarui.');
            }

            $set = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = :' . $field, $fields));

            $stmt = db()->prepare("UPDATE products SET $set WHERE id = :id");

            foreach ($fields as $field) {
                $stmt->bindValue(':' . $field, $data[$field] ?? null);
            }

            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            $ok = $stmt->execute();
            if ($ok && function_exists('activity_log_record')) {
                activity_log_record('update', 'product', $id, 'Item diperbarui.', ['title' => $payload['title'] ?? '', 'slug' => $payload['slug'] ?? '', 'storage' => 'database']);
            }
            return $ok;
        } catch (Throwable $e) {
            product_db_error('update', $e);
            return false;
        }
    }

    $products = product_json_read();

    foreach ($products as $i => $product) {
        if ((int)($product['id'] ?? 0) === $id) {
            $products[$i] = $payload;
            return product_write_json($products);
        }
    }

    return false;
}

function product_delete(int $id): bool
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('products')) {
        try {
            $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
            $ok = $stmt->execute([$id]);
            if ($ok && function_exists('activity_log_record')) {
                activity_log_record('delete', 'product', $id, 'Item dihapus.', ['storage' => 'database']);
            }
            return $ok;
        } catch (Throwable $e) {
            product_db_error('delete', $e);
            return false;
        }
    }

    $before = product_json_read();
    $deletedProduct = null;
    foreach ($before as $product) {
        if ((int)($product['id'] ?? 0) === $id) {
            $deletedProduct = $product;
            break;
        }
    }
    $products = array_values(array_filter($before, static fn(array $p): bool => (int)($p['id'] ?? 0) !== $id));

    $ok = product_write_json($products);
    if ($ok && function_exists('activity_log_record')) {
        activity_log_record('delete', 'product', $id, 'Item dihapus.', ['title' => $deletedProduct['title'] ?? '', 'slug' => $deletedProduct['slug'] ?? '', 'storage' => 'json']);
    }
    return $ok;
}

function product_filter_options(string $field): array
{
    $values = [];

    if ($field === 'category' && function_exists('business_category_labels')) {
        $values = array_merge($values, business_category_labels('catalog', true));
    }

    foreach (all_products() as $product) {
        $value = trim((string)($product[$field] ?? ''));
        if ($value !== '') { $values[] = $value; }
    }
    $values = array_unique($values);
    sort($values);
    return array_values($values);
}

function product_animal_type_filter_options(): array
{
    $baseOptions = array_map(
        static fn(array $definition): string => (string)$definition['label'],
        product_item_type_definitions()
    );

    $dynamicOptions = product_filter_options('animal_type');

    $options = array_values(array_unique(array_filter(array_merge(
        $baseOptions,
        $dynamicOptions
    ))));

    return $options;
}

function product_matches_animal_type_filter(array $product, string $value): bool
{
    $needle = strtolower(trim($value));

    if ($needle === '') {
        return true;
    }

    $animalType = strtolower(trim((string)($product['animal_type'] ?? '')));

    if ($animalType === $needle) {
        return true;
    }

    $haystack = strtolower(implode(' ', [
        $product['title'] ?? '',
        $product['category'] ?? '',
        $product['subcategory'] ?? '',
        $product['type'] ?? '',
        $product['animal_type'] ?? '',
        $product['breed'] ?? '',
        $product['location'] ?? '',
        $product['excerpt'] ?? '',
        $product['description'] ?? '',
    ]));

    return str_contains($haystack, $needle);
}

function filter_products(array $filters = []): array
{
    $results = all_products();
    $keyword = strtolower(trim((string)($filters['q'] ?? '')));
    if ($keyword !== '') {
        $results = array_filter($results, static function(array $p) use ($keyword): bool {
            $text = strtolower(implode(' ', [$p['title'] ?? '', $p['category'] ?? '', $p['subcategory'] ?? '', $p['animal_type'] ?? '', product_item_type_label($p), $p['breed'] ?? '', $p['location'] ?? '', $p['description'] ?? '']));
            return str_contains($text, $keyword);
        });
    }
    foreach (['category','animal_type','tier','location','stock_status'] as $field) {
        $value = trim((string)($filters[$field] ?? ''));
        if ($value !== '') {
            if ($field === 'animal_type') {
                $results = array_filter($results, static fn(array $p): bool => product_matches_animal_type_filter($p, $value));
                continue;
            }

            $results = array_filter($results, static fn(array $p): bool => strtolower((string)($p[$field] ?? '')) === strtolower($value));
        }
    }
    return array_values($results);
}

function product_seed_products(): array
{
    return array_map(
        static fn(array $product): array => product_with_source($product, 'seed'),
        require DATA_PATH . '/products.php'
    );
}

function product_convert_seed_to_storage(): array
{
    $bootstrap = product_runtime_bootstrap_from_seed_if_needed();
    if ((int)($bootstrap['created'] ?? 0) > 0) {
        return ['created' => (int)$bootstrap['created'], 'skipped' => (int)($bootstrap['skipped'] ?? 0)];
    }

    $created = 0;
    $skipped = 0;
    $existingSlugs = array_flip(array_map(static fn(array $p): string => (string)($p['slug'] ?? ''), product_managed_products()));

    foreach (product_seed_products() as $seed) {
        $slug = (string)($seed['slug'] ?? '');
        if ($slug === '' || isset($existingSlugs[$slug])) {
            $skipped++;
            continue;
        }
        $seed['id'] = product_next_id() + $created;
        $seed['source'] = 'starter-content';
        $seed['_source'] = 'starter-content';
        $seed['created_at'] = $seed['created_at'] ?? date('Y-m-d H:i:s');
        $seed['updated_at'] = date('Y-m-d H:i:s');
        if (product_create($seed) > 0) {
            $created++;
            $existingSlugs[$slug] = true;
        } else {
            $skipped++;
        }
    }

    product_mark_starter_content_initialized();

    return ['created' => $created, 'skipped' => $skipped];
}

function product_source_label(array $product): string
{
    return match ((string)($product['source'] ?? $product['_source'] ?? 'admin')) {
        'seed' => 'Contoh Awal',
        'seed-converted' => 'Konten Awal Tersimpan',
        'starter-content' => 'Konten Awal',
        'import' => 'Import',
        default => 'Admin',
    };
}
