<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$portfolioLabel = function_exists('business_label') ? business_label('portfolio', 'Portfolio') : 'Portfolio';
$serviceLabel = function_exists('business_label') ? business_label('service', 'Layanan') : 'Layanan';
$catalogLabel = function_exists('business_label') ? business_label('catalog', 'Katalog') : 'Katalog';
$primaryCta = function_exists('business_label') ? business_label('primary_cta', 'Konsultasi') : 'Konsultasi';
$portfolioCategories = function_exists('business_category_rows') ? business_category_rows('portfolio', true) : [];

$showcaseItems = array_values(array_filter(all_products(), static function (array $product): bool {
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
        || str_contains($text, 'layanan')
        || str_contains($text, 'jasa')
        || (function_exists('product_is_service_like') && product_is_service_like($product));
}));

if (!$showcaseItems) {
    $showcaseItems = array_slice(all_products(), 0, 6);
} else {
    $showcaseItems = array_slice($showcaseItems, 0, 9);
}

set_seo([
    'title' => $portfolioLabel . ' - ' . SITE_NAME,
    'description' => limit_chars('Lihat pilihan karya, project, layanan, produk unggulan, case study, dan bukti kerja dari ' . SITE_NAME . '.', 155),
    'keywords' => strtolower($portfolioLabel) . ', case study, project, karya, layanan, produk unggulan, ' . SITE_NAME,
    'canonical' => url('portfolio'),
    'robots' => 'index, follow',
    'type' => 'website',
    'image' => asset('images/og-default.jpg'),
]);

breadcrumb_schema([
    ['name' => 'Home', 'url' => url()],
    ['name' => $portfolioLabel, 'url' => url('portfolio')],
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero">
    <div class="container">
        <div class="breadcrumb"><a href="<?= esc(url()); ?>">Home</a><span>/</span><span><?= esc($portfolioLabel); ?></span></div>
        <h1><?= esc($portfolioLabel); ?> & Showcase</h1>
        <p>Lihat karya, layanan, produk unggulan, case study, dan bukti penawaran yang membantu Anda menilai solusi paling relevan.</p>
        <div class="hero-actions">
            <a class="button" href="<?= esc(url('kontak')); ?>" <?= conversion_link_attrs(['source'=>'portfolio-hero','type'=>'internal','category'=>'portfolio','intent'=>'contact','label'=>$primaryCta]); ?>><?= esc($primaryCta); ?></a>
            <a class="button button-outline" href="<?= esc(url('katalog')); ?>"><?= esc('Lihat ' . $catalogLabel); ?></a>
        </div>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="dynamic-mini-label">Flexible Showcase</span>
                <h2>Kategori <?= esc($portfolioLabel); ?></h2>
                <p>Pilih kategori yang paling relevan untuk melihat jenis karya, project, result, atau bukti layanan yang tersedia.</p>
            </div>
        </div>
        <div class="dynamic-chip-wrap">
            <?php foreach ($portfolioCategories as $row): ?>
                <a class="dynamic-chip" href="<?= esc(url('kontak?topic=' . rawurlencode((string)$row['label']))); ?>"><?= esc((string)$row['label']); ?></a>
            <?php endforeach; ?>
            <?php if (!$portfolioCategories): ?><span class="dynamic-chip">Case Study</span><span class="dynamic-chip">Project Client</span><span class="dynamic-chip">Testimoni</span><?php endif; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Pilihan <?= esc($portfolioLabel); ?>, <?= esc($serviceLabel); ?>, dan <?= esc($catalogLabel); ?></h2>
                <p>Temukan karya, layanan, produk unggulan, paket, atau penawaran yang bisa menjadi gambaran kualitas dan solusi yang tersedia.</p>
            </div>
        </div>

        <?php if (!$showcaseItems): ?>
            <div class="card empty-state">
                <h3>Showcase sedang disiapkan</h3>
                <p>Silakan hubungi tim kami untuk melihat contoh layanan, penawaran, atau hasil kerja yang paling relevan dengan kebutuhan Anda.</p>
                <a class="button" href="<?= esc(url('kontak')); ?>">Hubungi Kami</a>
            </div>
        <?php else: ?>
            <div class="cards3">
                <?php foreach ($showcaseItems as $product): ?>
                    <?php require ROOT_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="dynamic-two-columns">
            <div class="dynamic-panel">
                <h2>Untuk Personal Branding & Portfolio</h2>
                <p>Temukan profil, karya, layanan, bukti hasil, artikel pendukung, dan jalur konsultasi dalam satu alur yang rapi.</p>
            </div>
            <div class="dynamic-panel">
                <h2>Untuk Bisnis yang Ingin Scale</h2>
                <p>Gunakan informasi di halaman ini untuk membandingkan solusi, melihat bukti, lalu menghubungi tim kami melalui jalur yang tersedia.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php $inquiryContext = ['title'=>'Tertarik Bahas Project atau Penawaran?','text'=>'Isi form singkat, tim kami akan membantu mengarahkan ke produk, layanan, atau paket yang paling sesuai.','source'=>'portfolio-form','category'=>'portfolio','intent'=>'portfolio-inquiry','label'=>$portfolioLabel,'button'=>'Kirim Inquiry']; require ROOT_PATH . '/components/inquiry-form.php'; ?>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
