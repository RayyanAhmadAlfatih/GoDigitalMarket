<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$homepageSettings = function_exists('homepage_settings') ? homepage_settings() : [];
$homepageLandingPage = function_exists('homepage_selected_landing_page') ? homepage_selected_landing_page($homepageSettings) : null;

if ($homepageLandingPage && function_exists('landing_page_render_blocks')) {
    $page = $homepageLandingPage;
    if (function_exists('landing_page_ab_prepare_public_page')) {
        $page = landing_page_ab_prepare_public_page($page);
    }

    $focusMode = (string)($page['layout_mode'] ?? 'focus') === 'focus';
    $navOnly = !empty($page['show_nav_only']);
    $GLOBALS['landing_page_nav_only'] = $navOnly;
    $GLOBALS['landing_page_focus_no_header'] = $focusMode && !empty($page['hide_header']) && !$navOnly;
    $GLOBALS['landing_page_focus_footer'] = $focusMode && !empty($page['hide_footer']);
    $GLOBALS['landing_page_disable_floating_wa'] = $focusMode && !empty($page['hide_floating_wa']);
    $GLOBALS['landing_page_public'] = $page;
    $GLOBALS['landing_page_homepage_source'] = true;

    $canonical = url('');
    $metaTitle = trim((string)($page['meta_title'] ?? '')) ?: (string)($page['title'] ?? SITE_NAME);
    $metaDescription = trim((string)($page['meta_description'] ?? '')) ?: limit_words(strip_tags(json_encode($page['blocks'] ?? [], JSON_UNESCAPED_UNICODE) ?: ''), 24);
    $ogImage = trim((string)($page['og_image'] ?? '')) ?: DEFAULT_OG_IMAGE;

    set_seo([
        'title' => meta_title($metaTitle),
        'description' => $metaDescription,
        'keywords' => (string)($page['meta_keywords'] ?? DEFAULT_META_KEYWORDS),
        'canonical' => $canonical,
        'image' => $ogImage,
        'url' => $canonical,
        'robots' => 'index, follow',
        'type' => 'website',
    ]);

    if (function_exists('breadcrumb_schema')) {
        breadcrumb_schema([
            ['name' => 'Home', 'url' => $canonical],
        ]);
    }

    if (function_exists('add_schema')) {
        add_schema([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => (string)($page['title'] ?? SITE_NAME),
            'description' => $metaDescription,
            'url' => $canonical,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => SITE_NAME,
                'url' => SITE_URL,
            ],
        ]);
    }

    if (function_exists('landing_page_register_block_schemas')) {
        landing_page_register_block_schemas($page, $canonical, $metaDescription);
    }

    require_once ROOT_PATH . '/components/layout/head.php';
    require_once ROOT_PATH . '/components/layout/header.php';
    ?>

<main id="main-content" class="landing-page-builder landing-page-builder--homepage <?= $focusMode ? 'landing-page-builder--focus' : 'landing-page-builder--website'; ?>" data-landing-page="<?= esc((string)($page['slug'] ?? '')); ?>" data-homepage-source="landing-page">
    <?php landing_page_render_blocks($page); ?>

    <?php if (!$navOnly && function_exists('dynamic_daily_recommended_products') && is_file(ROOT_PATH . '/components/dynamic-homepage.php')) { require ROOT_PATH . '/components/dynamic-homepage.php'; } ?>

<script>
window.__MARKETING_PAGE_EVENT__ = Object.assign({}, window.__MARKETING_PAGE_EVENT__ || {}, {
    source: 'homepage-landing-page',
    type: 'homepage_landing_page_view',
    channel: 'homepage',
    category: 'landing-page-homepage',
    intent: 'homepage-landing-page-view',
    label: <?= json_encode((string)($page['tracking_label'] ?? $page['title'] ?? 'Homepage Landing Page'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    landing_page_slug: <?= json_encode((string)($page['slug'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    landing_page_id: <?= json_encode((string)($page['id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    page_path: window.location.pathname + window.location.search,
    event_id: <?= json_encode('home_lp_view_' . substr(md5((string)($page['slug'] ?? '') . date('YmdH')), 0, 16), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    ...<?= json_encode(function_exists('landing_page_ab_page_event_payload') ? landing_page_ab_page_event_payload() : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
});
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
<?php
    return;
}

$homepageMode = (string)($homepageSettings['mode'] ?? 'catalog');
$homepageHero = (array)($homepageSettings['hero'] ?? []);
$editableHomePage = function_exists('template_content_public_page') ? template_content_public_page('home') : [];
$homeHeroSection = function_exists('template_content_section') ? template_content_section($editableHomePage, 'hero') : [];
$homepageModes = function_exists('homepage_modes') ? homepage_modes() : [];
$homepageModeLabel = (string)($homepageModes[$homepageMode]['label'] ?? 'Homepage');
$heroTitle = (string)($homeHeroSection['title'] ?? $homepageHero['title'] ?? 'Bangun Website Produk, Jasa, dan Landing Page dalam Satu Template');
$heroDescription = (string)($homeHeroSection['description'] ?? $homepageHero['description'] ?? DEFAULT_META_DESCRIPTION);
$featuredCatalogSection = (array)($homepageSettings['featured_catalog'] ?? []);
$latestArticleSection = (array)($homepageSettings['latest_articles'] ?? []);
$featuredProducts = featured_products((int)($featuredCatalogSection['limit'] ?? 6));
$latestArticles = latest_articles((int)($latestArticleSection['limit'] ?? 3));
$servicesHighlightSection = (array)($homepageSettings['services_highlight'] ?? []);
$portfolioHighlightSection = (array)($homepageSettings['portfolio_highlight'] ?? []);
$homepageProductsAll = function_exists('all_products') ? all_products() : [];
$homepageServiceItems = array_values(array_filter($homepageProductsAll, static function (array $product): bool {
    if (($product['status'] ?? 'published') === 'draft') {
        return false;
    }

    $text = strtolower(implode(' ', array_map('strval', [
        $product['category'] ?? '',
        $product['type'] ?? '',
        $product['animal_type'] ?? '',
        $product['breed'] ?? '',
        $product['title'] ?? '',
    ])));

    return (function_exists('product_is_service_like') && product_is_service_like($product))
        || str_contains($text, 'layanan')
        || str_contains($text, 'jasa')
        || str_contains($text, 'service')
        || str_contains($text, 'konsultasi')
        || str_contains($text, 'booking');
}));
if (!$homepageServiceItems) {
    $homepageServiceItems = $featuredProducts;
}
$homepageServiceItems = array_slice($homepageServiceItems, 0, max(1, min(12, (int)($servicesHighlightSection['limit'] ?? 3))));

$homepagePortfolioItems = array_values(array_filter($homepageProductsAll, static function (array $product): bool {
    if (($product['status'] ?? 'published') === 'draft') {
        return false;
    }

    $text = strtolower(implode(' ', array_map('strval', [
        $product['category'] ?? '',
        $product['type'] ?? '',
        $product['animal_type'] ?? '',
        $product['breed'] ?? '',
        $product['title'] ?? '',
    ])));

    return !empty($product['featured'])
        || str_contains($text, 'portfolio')
        || str_contains($text, 'case')
        || str_contains($text, 'project')
        || str_contains($text, 'showcase')
        || str_contains($text, 'hasil')
        || (function_exists('product_is_service_like') && product_is_service_like($product));
}));
if (!$homepagePortfolioItems) {
    $homepagePortfolioItems = $featuredProducts;
}
$homepagePortfolioItems = array_slice($homepagePortfolioItems, 0, max(1, min(12, (int)($portfolioHighlightSection['limit'] ?? 3))));
$GLOBALS['preload_image'] = asset('images/og-default.jpg');

set_seo([
    'title' => (string)($editableHomePage['meta_title'] ?? '') !== '' ? (string)$editableHomePage['meta_title'] : SITE_NAME . ' - ' . $homepageModeLabel,
    'description' => (string)($editableHomePage['meta_description'] ?? '') !== '' ? (string)$editableHomePage['meta_description'] : $heroDescription,
    'keywords' => DEFAULT_META_KEYWORDS,
    'canonical' => url(''),
    'robots' => 'index, follow',
    'type' => 'website',
    'image' => asset('images/og-default.jpg'),
]);

if (function_exists('add_schema')) {
    add_schema([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => SITE_NAME,
        'url' => url(''),
        'description' => $heroDescription,
    ]);
}

$sectionEnabled = static function (string $key) use ($homepageSettings, $editableHomePage): bool {
    $homepageEnabled = !empty($homepageSettings['sections'][$key]);
    $templateEnabled = function_exists('template_content_section_visible') ? template_content_section_visible($editableHomePage, $key) : true;
    return $homepageEnabled && $templateEnabled;
};
$sectionValue = static function (string $sectionId, string $field, string $fallback = '') use ($editableHomePage): string {
    return function_exists('template_content_section_value') ? template_content_section_value($editableHomePage, $sectionId, $field, $fallback) : $fallback;
};

$homepageHref = static function (string $urlValue): string {
    return function_exists('homepage_url_to_href') ? homepage_url_to_href($urlValue) : url(ltrim($urlValue, '/'));
};

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<?php
$renderHeroSection = static function () use ($homepageMode, $sectionValue, $homepageHero, $heroTitle, $heroDescription, $homepageHref, $homepageSettings): void {
    $heroTracking = (array)($homepageSettings['cta_tracking']['homepage_hero'] ?? []);
    $heroTrackingAttrs = '';
    if (!empty($heroTracking)) {
        $heroTrackingAttrs = ' data-cta-deployment-id="' . esc((string)($heroTracking['deployment_id'] ?? '')) . '" data-offer-variant-id="' . esc((string)($heroTracking['variant_id'] ?? '')) . '" data-cta-placement="' . esc((string)($heroTracking['placement'] ?? 'homepage_hero')) . '"';
    }
    ?>
<section class="hero hero--mode-<?= esc($homepageMode); ?>">
    <div class="overlay">
        <div class="container">
            <span class="hero-badge">✨ <?= esc($sectionValue('hero', 'eyebrow', (string)($homepageHero['eyebrow'] ?? 'Website siap jualan'))); ?></span>
            <h1><?= esc($heroTitle); ?></h1>
            <p><?= esc($heroDescription); ?></p>
            <div class="hero-actions">
                <a href="<?= esc($homepageHref($sectionValue('hero', 'button_url', (string)($homepageHero['primary_url'] ?? '/katalog')))); ?>" class="btn" <?= conversion_link_attrs(['source'=>'homepage-hero','type'=>'internal','category'=>'homepage','label'=>$sectionValue('hero', 'button_label', (string)($homepageHero['primary_label'] ?? 'Lihat Katalog')),'intent'=>'homepage-primary']); ?><?= $heroTrackingAttrs; ?>><?= esc($sectionValue('hero', 'button_label', (string)($homepageHero['primary_label'] ?? 'Lihat Katalog'))); ?></a>
                <a href="<?= esc($homepageHref($sectionValue('hero', 'secondary_button_url', (string)($homepageHero['secondary_url'] ?? '/kontak')))); ?>" class="cta" <?= conversion_link_attrs(['source'=>'homepage-hero','type'=>'internal','category'=>'homepage','label'=>$sectionValue('hero', 'secondary_button_label', (string)($homepageHero['secondary_label'] ?? 'Konsultasi')),'intent'=>'homepage-secondary']); ?>><?= esc($sectionValue('hero', 'secondary_button_label', (string)($homepageHero['secondary_label'] ?? 'Konsultasi'))); ?></a>
            </div>
        </div>
    </div>
</section>
    <?php
};

$renderTrustbarSection = static function () use ($homepageSettings): void {
    ?>
<section class="trustbar">
    <div class="container">
        <div class="trust-grid">
            <?php foreach (array_slice((array)($homepageSettings['trust_items'] ?? []), 0, 4) as $item): ?>
                <div class="trust-item"><h3><?= esc((string)($item['title'] ?? 'Info')); ?></h3><p><?= esc((string)($item['text'] ?? 'Keterangan singkat bisnis Anda.')); ?></p></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
    <?php
};

$renderProfileIntroSection = static function () use ($homepageSettings, $sectionValue, $homepageMode): void {
    $profileIntro = (array)($homepageSettings['profile_intro'] ?? []);
    ?>
<section class="section homepage-mode-intro">
    <div class="container">
        <span class="section-eyebrow"><?= esc($sectionValue('profile_intro', 'eyebrow', (string)($profileIntro['eyebrow'] ?? 'Beranda fleksibel'))); ?></span>
        <h2 class="title"><?= esc($sectionValue('profile_intro', 'title', (string)($profileIntro['title'] ?? 'Pilih Gaya Beranda Sesuai Cara Bisnis Anda Berjualan'))); ?></h2>
        <p class="center"><?= esc($sectionValue('profile_intro', 'description', (string)($profileIntro['description'] ?? 'Beranda bisa disesuaikan dengan kebutuhan bisnis Anda.'))); ?></p>
        <div class="cards3">
            <?php foreach (function_exists('homepage_mode_cards') ? homepage_mode_cards($homepageMode) : [] as $card): ?>
                <div class="card"><div class="card-content"><h3><?= esc((string)($card['title'] ?? 'Info')); ?></h3><p><?= esc((string)($card['text'] ?? 'Keterangan singkat.')); ?></p></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
    <?php
};

$renderFeaturedCatalogSection = static function () use ($sectionValue, $featuredCatalogSection, $featuredProducts, $homepageHref): void {
    ?>
<section class="section">
    <div class="container">
        <span class="section-eyebrow"><?= esc($sectionValue('featured_catalog', 'eyebrow', (string)($featuredCatalogSection['eyebrow'] ?? 'Katalog pilihan'))); ?></span>
        <h2 class="title"><?= esc($sectionValue('featured_catalog', 'title', (string)($featuredCatalogSection['title'] ?? 'Contoh Katalog Produk & Layanan'))); ?></h2>
        <p class="center"><?= esc($sectionValue('featured_catalog', 'description', (string)($featuredCatalogSection['description'] ?? 'Katalog contoh ini bisa diganti sesuai bisnis masing-masing.'))); ?></p>
        <div class="cards3">
            <?php foreach ($featuredProducts as $product): ?>
                <?php require ROOT_PATH . '/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <p class="center"><a class="btn" href="<?= esc($homepageHref($sectionValue('featured_catalog', 'button_url', (string)($featuredCatalogSection['button_url'] ?? '/katalog')))); ?>"><?= esc($sectionValue('featured_catalog', 'button_label', (string)($featuredCatalogSection['button_label'] ?? 'Buka Semua Katalog'))); ?></a></p>
    </div>
</section>
    <?php
};

$renderServicesHighlightSection = static function () use ($servicesHighlightSection, $homepageServiceItems, $homepageHref): void {
    ?>
<section class="section alt homepage-services-highlight">
    <div class="container">
        <span class="section-eyebrow"><?= esc((string)($servicesHighlightSection['eyebrow'] ?? 'Layanan pilihan')); ?></span>
        <h2 class="title"><?= esc((string)($servicesHighlightSection['title'] ?? 'Layanan yang Bisa Disesuaikan dengan Kebutuhan Anda')); ?></h2>
        <p class="center"><?= esc((string)($servicesHighlightSection['description'] ?? 'Tampilkan jasa, konsultasi, booking, paket layanan, atau penawaran custom yang paling penting.')); ?></p>
        <div class="cards3">
            <?php foreach ($homepageServiceItems as $product): ?>
                <?php require ROOT_PATH . '/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <p class="center"><a class="btn" href="<?= esc($homepageHref((string)($servicesHighlightSection['button_url'] ?? '/layanan'))); ?>"><?= esc((string)($servicesHighlightSection['button_label'] ?? 'Lihat Semua Layanan')); ?></a></p>
    </div>
</section>
    <?php
};

$renderBusinessFitSection = static function () use ($homepageSettings, $sectionValue): void {
    $businessFit = (array)($homepageSettings['business_fit'] ?? []);
    ?>
<section class="section alt homepage-business-fit">
    <div class="container">
        <span class="section-eyebrow"><?= esc($sectionValue('business_fit', 'eyebrow', (string)($businessFit['eyebrow'] ?? 'Cocok untuk banyak bisnis'))); ?></span>
        <h2 class="title"><?= esc($sectionValue('business_fit', 'title', (string)($businessFit['title'] ?? 'Fleksibel untuk Banyak Jenis Bisnis'))); ?></h2>
        <p class="center"><?= esc($sectionValue('business_fit', 'description', (string)($businessFit['description'] ?? 'Template ini disiapkan agar bisa dipakai berbagai jenis bisnis.'))); ?></p>
        <div class="cards3">
            <div class="card"><div class="card-content"><h3>Company Profile</h3><p>Tampilkan profil bisnis, layanan, portofolio, testimoni, dan kontak utama.</p></div></div>
            <div class="card"><div class="card-content"><h3>Sales Page</h3><p>Buat halaman penawaran dengan headline, benefit, FAQ, CTA, dan form lead.</p></div></div>
            <div class="card"><div class="card-content"><h3>Katalog & Checkout</h3><p>Kelola produk atau jasa, lalu arahkan pembeli ke WhatsApp atau form checkout.</p></div></div>
        </div>
    </div>
</section>
    <?php
};

$renderLatestArticlesSection = static function () use ($sectionValue, $latestArticleSection, $latestArticles): void {
    ?>
<section class="section">
    <div class="container">
        <span class="section-eyebrow"><?= esc($sectionValue('latest_articles', 'eyebrow', (string)($latestArticleSection['eyebrow'] ?? 'Artikel & edukasi'))); ?></span>
        <h2 class="title"><?= esc($sectionValue('latest_articles', 'title', (string)($latestArticleSection['title'] ?? 'Artikel Terbaru'))); ?></h2>
        <p class="center"><?= esc($sectionValue('latest_articles', 'description', (string)($latestArticleSection['description'] ?? 'Gunakan blog untuk edukasi pelanggan, menjawab FAQ, dan memperkuat SEO bisnis.'))); ?></p>
        <div class="cards3">
            <?php foreach ($latestArticles as $article): ?>
                <?php require ROOT_PATH . '/components/article-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
    <?php
};

$renderPortfolioHighlightSection = static function () use ($portfolioHighlightSection, $homepagePortfolioItems, $homepageHref): void {
    ?>
<section class="section homepage-portfolio-highlight">
    <div class="container">
        <span class="section-eyebrow"><?= esc((string)($portfolioHighlightSection['eyebrow'] ?? 'Portfolio & showcase')); ?></span>
        <h2 class="title"><?= esc((string)($portfolioHighlightSection['title'] ?? 'Bukti Karya, Project, atau Penawaran Unggulan')); ?></h2>
        <p class="center"><?= esc((string)($portfolioHighlightSection['description'] ?? 'Tampilkan karya, case study, project, produk unggulan, atau bukti hasil agar pengunjung lebih yakin.')); ?></p>
        <div class="cards3">
            <?php foreach ($homepagePortfolioItems as $product): ?>
                <?php require ROOT_PATH . '/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <p class="center"><a class="btn" href="<?= esc($homepageHref((string)($portfolioHighlightSection['button_url'] ?? '/portfolio'))); ?>"><?= esc((string)($portfolioHighlightSection['button_label'] ?? 'Lihat Portfolio')); ?></a></p>
    </div>
</section>
    <?php
};

$renderLeadFormSection = static function () use ($homepageSettings, $sectionValue): void {
    $leadForm = (array)($homepageSettings['lead_form'] ?? []);
    $formTracking = (array)($homepageSettings['cta_tracking']['homepage_bottom'] ?? []);
    ?>
<section id="form-konsultasi" class="section alt">
    <div class="container">
        <?php
        $inquiryContext = [
            'title' => $sectionValue('lead_form', 'title', (string)($leadForm['title'] ?? 'Butuh Bantuan Memilih Produk atau Layanan?')),
            'text' => $sectionValue('lead_form', 'description', (string)($leadForm['text'] ?? 'Isi form singkat ini untuk konsultasi atau permintaan penawaran.')),
            'source' => 'homepage-form',
            'category' => 'produk-layanan',
            'intent' => 'homepage-inquiry',
            'label' => 'Permintaan Homepage',
            'button' => $sectionValue('lead_form', 'button_label', (string)($leadForm['button'] ?? 'Kirim Permintaan')),
            'cta_deployment_id' => (string)($formTracking['deployment_id'] ?? ''),
            'offer_variant_id' => (string)($formTracking['variant_id'] ?? ''),
            'cta_placement' => (string)($formTracking['placement'] ?? 'homepage_bottom'),
        ];
        require ROOT_PATH . '/components/inquiry-form.php';
        ?>
    </div>
</section>
    <?php
};

$renderHomepageSection = static function (string $sectionKey) use ($sectionEnabled, $renderHeroSection, $renderTrustbarSection, $renderProfileIntroSection, $renderFeaturedCatalogSection, $renderServicesHighlightSection, $renderBusinessFitSection, $renderLatestArticlesSection, $renderPortfolioHighlightSection, $renderLeadFormSection, $editableHomePage): void {
    if (!in_array($sectionKey, ['trust_conversion', 'custom_sections'], true) && !$sectionEnabled($sectionKey)) {
        return;
    }

    if ($sectionKey === 'trust_conversion' && !$sectionEnabled('trust_conversion')) {
        return;
    }

    if ($sectionKey === 'custom_sections' && !$sectionEnabled('custom_sections')) {
        return;
    }

    match ($sectionKey) {
        'hero' => $renderHeroSection(),
        'trustbar' => $renderTrustbarSection(),
        'profile_intro' => $renderProfileIntroSection(),
        'featured_catalog' => $renderFeaturedCatalogSection(),
        'services_highlight' => $renderServicesHighlightSection(),
        'business_fit' => $renderBusinessFitSection(),
        'latest_articles' => $renderLatestArticlesSection(),
        'trust_conversion' => function_exists('trust_conversion_render_homepage_blocks') ? trust_conversion_render_homepage_blocks() : null,
        'custom_sections' => function_exists('template_content_render_custom_sections') ? template_content_render_custom_sections($editableHomePage) : null,
        'lead_form' => $renderLeadFormSection(),
        'portfolio_highlight' => $renderPortfolioHighlightSection(),
        default => null,
    };
};

foreach ((function_exists('homepage_ordered_sections') ? homepage_ordered_sections($homepageSettings) : ['hero','trustbar','profile_intro','featured_catalog','business_fit','latest_articles','custom_sections','trust_conversion','lead_form']) as $homepageSectionKey) {
    $renderHomepageSection((string)$homepageSectionKey);
}

if (function_exists('dynamic_daily_recommended_products') && is_file(ROOT_PATH . '/components/dynamic-homepage.php')) {
    require ROOT_PATH . '/components/dynamic-homepage.php';
}
?>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
