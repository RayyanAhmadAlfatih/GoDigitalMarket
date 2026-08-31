<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DYNAMIC LANDING PAGE COMPONENT
|--------------------------------------------------------------------------
| Shared dynamic content renderer for layanan / produk fisik / paket layanan
| landing pages. Route files only set $landingProfileKey.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$landingProfileKey = $landingProfileKey ?? 'layanan';
$profile = dynamic_landing_profile((string)$landingProfileKey);
$landingProducts = dynamic_landing_products($profile, 6);
$landingArticles = dynamic_landing_articles($profile, 3);
$landingFaq = dynamic_landing_faq($profile);
$landingSteps = dynamic_landing_service_steps($profile);
$landingPackages = dynamic_landing_package_options($profile);
$landingLocationCards = dynamic_landing_location_cards($profile);
$landingRelatedLinks = dynamic_landing_related_links($profile);
$landingInternalLinks = dynamic_v26_landing_links($profile, 10);
$canonicalMap = [
    'produk-fisik' => 'katalog',
    'paket-layanan' => 'katalog-paket',
    'layanan' => 'layanan',
    'layanan-paket' => 'layanan-paket',
    'layanan-layanan' => 'layanan-layanan',
];
$canonicalPath = $canonicalMap[$profile['key']] ?? 'layanan';
$canonicalUrl = url($canonicalPath);
$relatedCatalogUrl = match ($profile['key']) {
    'produk-fisik' => url('katalog?animal_type=Produk Fisik'),
    'paket-layanan' => url('katalog?animal_type=Paket'),
    'layanan-paket' => url('katalog?animal_type=Paket&category=Layanan'),
    'layanan-layanan' => url('katalog?animal_type=Layanan&category=Layanan'),
    default => url('katalog?category=Layanan'),
};

set_seo([
    'title' => $profile['title'] . ' - ' . SITE_NAME,
    'description' => limit_chars((string)$profile['description'], 155),
    'keywords' => implode(', ', array_unique(array_merge($profile['keywords'] ?? [], ['produk', 'layanan', 'Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta']))),
    'canonical' => $canonicalUrl,
    'robots' => 'index, follow',
    'type' => 'website',
    'image' => DEFAULT_OG_IMAGE,
    'url' => $canonicalUrl,
]);

breadcrumb_schema([
    ['name' => 'Home', 'url' => url()],
    ['name' => (string)$profile['short_title'], 'url' => $canonicalUrl],
]);

if (function_exists('add_schema')) {
    add_schema(dynamic_item_list_schema_array($landingProducts, (string)$profile['title'], $canonicalUrl));
    add_schema(dynamic_service_schema_array($profile, $canonicalUrl));
    add_schema(dynamic_v26_webpage_schema_array((string)$profile['title'], (string)$profile['description'], $canonicalUrl, $landingInternalLinks, 'CollectionPage'));
    add_schema(dynamic_v26_navigation_schema_array($landingInternalLinks, 'Halaman Pendukung ' . (string)$profile['short_title']));
}

if ($landingFaq && function_exists('faq_schema')) {
    faq_schema($landingFaq);
}

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero dynamic-landing-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= url(); ?>">Home</a>
            <span>/</span>
            <span><?= esc((string)$profile['short_title']); ?></span>
        </div>
        <h1><?= esc((string)$profile['title']); ?></h1>
        <p><?= esc((string)$profile['description']); ?></p>
        <div class="dynamic-hero-actions">
            <a class="cta" href="<?= wa_link((string)$profile['cta']); ?>" target="_blank" rel="nofollow noopener">Konsultasi WhatsApp</a>
            <a class="btn" href="<?= esc($relatedCatalogUrl); ?>">Lihat Katalog Terkait</a>
        </div>
    </div>
</section>

<section class="section alt dynamic-overview-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Pilihan Terbaru</span>
                <h2><?= esc((string)$profile['short_title']); ?> Terbaru</h2>
            </div>
            <span class="dynamic-updated-pill">Update: <?= esc(dynamic_content_updated_at()); ?></span>
        </div>

        <div class="dynamic-two-columns">
            <div class="dynamic-panel">
                <h3>Ringkasan Layanan</h3>
                <p><?= esc((string)$profile['description']); ?></p>
                <p>Di halaman ini Anda bisa melihat rekomendasi produk, panduan singkat, area layanan, FAQ, dan artikel terkait sebelum konsultasi dengan admin.</p>
            </div>
            <div class="dynamic-panel">
                <h3>Topik Terkait</h3>
                <div class="dynamic-chip-wrap">
                    <?php foreach (($profile['keywords'] ?? []) as $keyword): ?>
                        <span class="dynamic-chip"><?= esc((string)$keyword); ?></span>
                    <?php endforeach; ?>
                    <span class="dynamic-chip">layanan area lokal</span>
                    <span class="dynamic-chip">harga produk</span>
                    <span class="dynamic-chip">konsultasi layanan</span>
                </div>

                <?php if (!empty($landingRelatedLinks)): ?>
                    <div class="landing-related-links">
                        <?php foreach (array_slice($landingRelatedLinks, 0, 5) as $link): ?>
                            <a href="<?= esc((string)$link['url']); ?>"><?= esc((string)$link['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="title">Rekomendasi Produk <?= esc((string)$profile['short_title']); ?></h2>
        <p class="center">Pilihan berikut membantu Anda membandingkan produk berdasarkan kategori, jenis produk/layanan, dan kebutuhan layanan.</p>

        <?php if ($landingProducts): ?>
            <div class="cards3">
                <?php foreach ($landingProducts as $product): ?>
                    <?php require ROOT_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
            <div class="center dynamic-section-cta">
                <a class="btn" href="<?= esc($relatedCatalogUrl); ?>">Buka Semua Produk Terkait</a>
            </div>
        <?php else: ?>
            <div class="dynamic-panel empty-state">
                <h3>Produk belum tersedia</h3>
                <p>Silakan cek katalog utama atau hubungi admin untuk mendapatkan rekomendasi stok terbaru.</p>
                <a class="btn" href="<?= url('katalog'); ?>">Buka Katalog</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Edukasi</span>
                <h2>Panduan Memilih <?= esc((string)$profile['short_title']); ?></h2>
            </div>
        </div>

        <div class="dynamic-trust-grid">
            <?php foreach (($profile['education'] ?? []) as $index => $education): ?>
                <div class="dynamic-faq-card">
                    <h3>Tips <?= $index + 1; ?></h3>
                    <p><?= esc((string)$education); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($landingPackages)): ?>
<section class="section">
    <div class="container">
        <h2 class="title">Pilihan Kebutuhan <?= esc((string)$profile['short_title']); ?></h2>
        <p class="center">Gunakan ringkasan ini untuk menentukan arah konsultasi sebelum memilih produk.</p>
        <div class="landing-package-grid">
            <?php foreach ($landingPackages as $package): ?>
                <div class="landing-package-card">
                    <h3><?= esc((string)$package['title']); ?></h3>
                    <p><?= esc((string)$package['text']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($landingSteps)): ?>
<section class="section alt">
    <div class="container">
        <h2 class="title">Alur Konsultasi & Booking</h2>
        <p class="center">Langkahnya dibuat sederhana supaya pelanggan mudah cek kebutuhan, stok, dan rekomendasi terbaik.</p>
        <div class="landing-step-grid">
            <?php foreach ($landingSteps as $index => $step): ?>
                <div class="landing-step-card">
                    <span><?= esc((string)($index + 1)); ?></span>
                    <h3><?= esc((string)$step['title']); ?></h3>
                    <p><?= esc((string)$step['text']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section dynamic-area-layanan-section">
    <div class="container">
        <div class="dynamic-area-layanan-card">
            <div>
                <span class="dynamic-mini-label">Area Layanan</span>
                <h2>Area Layanan & Titik Layanan</h2>
                <p>Konsultasikan kebutuhan <?= esc(strtolower((string)$profile['short_title'])); ?> berdasarkan lokasi terdekat, stok terbaru, dan jadwal survey atau pengiriman.</p>
            </div>
            <div class="dynamic-area-layanan-meta">
                <?php foreach (($profile['locations'] ?? []) as $location): ?>
                    <span><?= esc((string)$location); ?></span>
                <?php endforeach; ?>
                <a href="<?= wa_link((string)$profile['cta']); ?>" target="_blank" rel="nofollow noopener">Tanya Lokasi Terdekat</a>
            </div>
        </div>

        <?php if (!empty($landingLocationCards)): ?>
            <div class="landing-location-grid">
                <?php foreach ($landingLocationCards as $card): ?>
                    <a class="landing-location-card" href="<?= esc((string)$card['url']); ?>">
                        <strong><?= esc((string)$card['title']); ?></strong>
                        <span><?= esc((string)$card['text']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($landingArticles): ?>
<section class="section alt article-related-polish-section landing-article-polish-section">
    <div class="container">
        <h2 class="title">Artikel Terkait <?= esc((string)$profile['short_title']); ?></h2>
        <p class="center">Baca juga panduan terkait agar lebih mudah memilih layanan yang sesuai.</p>
        <div class="cards3">
            <?php foreach ($landingArticles as $article): ?>
                <?php require ROOT_PATH . '/components/article-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <h2 class="title">FAQ <?= esc((string)$profile['short_title']); ?></h2>
        <div class="faq-wrap">
            <?php foreach ($landingFaq as $faq): ?>
                <div class="faq-item">
                    <h3><?= esc((string)$faq['question']); ?></h3>
                    <p><?= esc((string)$faq['answer']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($landingRelatedLinks)): ?>
<section class="section alt">
    <div class="container">
        <h2 class="title">Lanjutkan ke Halaman Terkait</h2>
        <p class="center">Beberapa halaman berikut membantu membandingkan produk, membaca panduan, atau menghubungi admin.</p>
        <div class="landing-link-grid">
            <?php foreach ($landingRelatedLinks as $link): ?>
                <a href="<?= esc((string)$link['url']); ?>"><?= esc((string)$link['label']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<?php if (!empty($landingInternalLinks)): ?>
<section class="section alt">
    <div class="container">
        <div class="dynamic-panel internal-link-panel">
            <div class="dynamic-block-head compact-head">
                <div>
                    <span class="dynamic-mini-label">Pilihan Lanjutan</span>
                    <h2>Halaman Pendukung <?= esc((string)$profile['short_title']); ?></h2>
                </div>
            </div>
            <div class="internal-link-grid">
                <?php foreach (dynamic_v26_link_groups($landingInternalLinks) as $group => $links): ?>
                    <div class="internal-link-group">
                        <h3><?= esc((string)$group); ?></h3>
                        <?php foreach ($links as $link): ?>
                            <a href="<?= esc((string)$link['url']); ?>">
                                <strong><?= esc((string)$link['label']); ?></strong>
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

<section class="section">
    <div class="container">
        <?php
        $inquiryContext = [
            'title' => 'Minta Rekomendasi ' . (string)$profile['short_title'],
            'text' => 'Isi form singkat agar admin bisa membantu cek stok, area layanan, dan pilihan paket yang paling cocok.',
            'source' => 'landing-form-' . (string)$profile['key'],
            'category' => (string)$profile['key'],
            'intent' => 'landing-inquiry',
            'label' => 'Permintaan ' . (string)$profile['short_title'],
            'button' => 'Kirim Permintaan',
        ];
        require ROOT_PATH . '/components/inquiry-form.php';
        ?>
    </div>
</section>

<section class="cta-section">
    <div class="container">
        <h2>Butuh Rekomendasi <?= esc((string)$profile['short_title']); ?>?</h2>
        <p>Admin siap membantu memilih produk atau paket sesuai budget, lokasi, dan jadwal kebutuhan Anda.</p>
        <a class="cta" href="<?= wa_link((string)$profile['cta']); ?>" target="_blank" rel="nofollow noopener">Chat WhatsApp</a>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
