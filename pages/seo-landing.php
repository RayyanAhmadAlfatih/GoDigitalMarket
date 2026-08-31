<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$landingPrefix = slugify((string)($_GET['landing_prefix'] ?? ''));
$landingSlug = slugify((string)($_GET['landing_slug'] ?? ''));

if (!in_array($landingPrefix, seo_landing_allowed_prefixes(), true) || $landingSlug === '') {
    http_response_code(404);
    require PAGES_PATH . '/404.php';
    return;
}

$landing = seo_landing_find($landingPrefix, $landingSlug, true);

if (!$landing || !(bool)($landing['enabled'] ?? false) || (int)($landing['product_count'] ?? 0) < 1) {
    if ($landingPrefix === 'kategori' && function_exists('dynamic_term_page')) {
        $_GET['term_type'] = 'kategori';
        $_GET['term_slug'] = $landingSlug;
        require PAGES_PATH . '/dynamic-term.php';
        return;
    }

    http_response_code(404);
    require PAGES_PATH . '/404.php';
    return;
}

$products = array_values((array)($landing['products'] ?? []));
$filters = (array)($landing['filters'] ?? []);
$canonical = (string)($landing['canonical'] ?? url((string)$landing['path']));
$faq = seo_landing_faq($landing);
$internalLinks = seo_landing_internal_links($landing, 8);
$relatedArticles = function_exists('latest_articles') ? latest_articles(3) : [];

set_seo([
    'title' => (string)$landing['title'] . ' - ' . SITE_NAME,
    'description' => (string)$landing['description'],
    'keywords' => implode(', ', array_filter([
        'produk',
        'jasa layanan',
        $filters['animal_type'] ?? '',
        $filters['breed'] ?? '',
        $filters['tier'] ?? '',
        $filters['location'] ?? '',
    ])),
    'canonical' => $canonical,
    'robots' => (string)($landing['robots'] ?? 'index, follow'),
    'type' => 'website',
]);

if (function_exists('add_schema')) {
    add_schema(dynamic_item_list_schema_array($products, (string)$landing['title'], $canonical));
    add_schema(dynamic_v26_webpage_schema_array((string)$landing['title'], (string)$landing['description'], $canonical, $internalLinks, 'CollectionPage'));
    add_schema(dynamic_v26_navigation_schema_array($internalLinks, 'Link Terkait Landing SEO'));
}

if (function_exists('faq_schema')) {
    faq_schema($faq);
}

breadcrumb_schema([
    ['name' => 'Home', 'url' => url()],
    ['name' => ucfirst($landingPrefix), 'url' => url($landingPrefix === 'katalog' ? 'katalog' : ($landingPrefix === 'lokasi' ? 'kontak' : $landingPrefix))],
    ['name' => (string)$landing['title'], 'url' => $canonical],
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero seo-landing-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= url(); ?>">Home</a>
            <span>/</span>
            <a href="<?= esc(url($landingPrefix === 'katalog' ? 'katalog' : ($landingPrefix === 'lokasi' ? 'kontak' : $landingPrefix))); ?>"><?= esc(ucfirst($landingPrefix)); ?></a>
            <span>/</span>
            <span><?= esc((string)$landing['title']); ?></span>
        </div>
        <span class="dynamic-mini-label">Clean SEO Landing</span>
        <h1><?= esc((string)$landing['h1']); ?></h1>
        <p><?= esc((string)$landing['summary']); ?></p>
        <div class="dynamic-chip-wrap" aria-label="Filter landing">
            <?php foreach (['service' => 'Layanan', 'animal_type' => 'Jenis', 'breed' => 'Breed', 'tier' => 'Kelas', 'location' => 'Lokasi'] as $key => $label): ?>
                <?php if (trim((string)($filters[$key] ?? '')) !== ''): ?>
                    <span class="dynamic-chip"><?= esc($label); ?>: <?= esc((string)$filters[$key]); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
            <span class="dynamic-chip"><?= (int)$landing['product_count']; ?> produk terkait</span>
        </div>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="dynamic-two-columns">
            <div class="dynamic-panel">
                <h2>Ringkasan Halaman</h2>
                <p>Halaman ini dibuat dari registry SEO yang hanya aktif ketika ada produk terkait. Tujuannya menjaga URL tetap bersih, canonical jelas, dan mengurangi risiko halaman tipis atau duplikatif.</p>
                <p>Gunakan daftar produk di bawah untuk membandingkan pilihan, lalu konfirmasi stok, spesifikasi, lokasi, dan jadwal melalui admin.</p>
            </div>
            <div class="dynamic-panel">
                <h2>Arah Cepat</h2>
                <div class="dynamic-chip-wrap">
                    <a class="dynamic-chip" href="<?= url('katalog'); ?>">Katalog Utama</a>
                    <a class="dynamic-chip" href="<?= url('order-status'); ?>">Cek Order</a>
                    <a class="dynamic-chip" href="<?= url('kontak'); ?>">Kontak Admin</a>
                    <?php if (!empty($filters['location'])): ?>
                        <a class="dynamic-chip" href="<?= url('katalog?location=' . rawurlencode((string)$filters['location'])); ?>">Filter Aktif</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Produk Terkait</h2>
                <p><?= (int)$landing['product_count']; ?> produk cocok dengan halaman ini. Data tetap mengikuti katalog utama.</p>
            </div>
            <a class="button button-outline" href="<?= url('katalog'); ?>">Lihat Semua Katalog</a>
        </div>

        <div class="cards3">
            <?php foreach (array_slice($products, 0, 12) as $product): ?>
                <?php require ROOT_PATH . '/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <?php if (count($products) > 12): ?>
            <div class="center" style="margin-top:22px">
                <a class="button" href="<?= url('katalog?' . http_build_query(array_filter([
                    'category' => ($filters['service'] ?? '') === 'produk' ? 'Produk Fisik' : '',
                    'animal_type' => $filters['animal_type'] ?? '',
                    'tier' => $filters['tier'] ?? '',
                    'location' => $filters['location'] ?? '',
                ], static fn($value): bool => trim((string)$value) !== ''))); ?>">Lihat Filter Lengkap</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="dynamic-two-columns">
            <div class="dynamic-panel internal-link-panel">
                <div class="dynamic-block-head compact-head">
                    <div>
                        <span class="dynamic-mini-label">Internal Linking</span>
                        <h2>Halaman Terkait</h2>
                    </div>
                </div>
                <div class="internal-link-list compact-internal-links">
                    <?php foreach ($internalLinks as $link): ?>
                        <a href="<?= esc((string)$link['url']); ?>">
                            <strong><?= esc((string)$link['label']); ?></strong>
                            <span><?= esc((string)$link['text']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dynamic-panel">
                <h2>Artikel Pendukung</h2>
                <div class="catalog-local-list">
                    <?php foreach ($relatedArticles as $article): ?>
                        <a href="<?= article_url((string)($article['slug'] ?? '')); ?>" class="catalog-local-item">
                            <strong><?= esc((string)($article['title'] ?? 'Artikel')); ?></strong>
                            <span><?= esc(limit_chars((string)($article['excerpt'] ?? ''), 90)); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$relatedArticles): ?>
                        <p>Artikel pendukung belum tersedia.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php
        $inquiryContext = [
            'title' => 'Butuh Dibantu Pilih dari Halaman Ini?',
            'text' => 'Isi inquiry singkat agar admin membantu memilih produk sesuai kebutuhan, lokasi, dan budget.',
            'source' => 'seo-landing-' . (string)$landing['key'],
            'category' => (string)($filters['service'] ?? $landingPrefix),
            'location' => (string)($filters['location'] ?? ''),
            'intent' => 'seo-landing-inquiry',
            'label' => 'Permintaan Landing Page',
            'button' => 'Kirim Permintaan',
        ];
        require ROOT_PATH . '/components/inquiry-form.php';
        ?>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <h2 class="title">FAQ <?= esc((string)$landing['title']); ?></h2>
        <p class="center">Jawaban singkat sebelum konsultasi stok dan jadwal terbaru ke admin.</p>
        <div class="faq-wrap">
            <?php foreach ($faq as $item): ?>
                <div class="faq-item">
                    <h3><?= esc((string)$item['question']); ?></h3>
                    <p><?= esc((string)$item['answer']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
