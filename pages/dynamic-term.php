<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$termType = slugify((string)($_GET['term_type'] ?? 'keyword'));
$termSlug = slugify((string)($_GET['term_slug'] ?? ''));

if (!function_exists('dynamic_term_page')) {
    http_response_code(404);
    require PAGES_PATH . '/404.php';
    return;
}

$page = dynamic_term_page($termType, $termSlug, 30);
if (!$page) {
    http_response_code(404);
    require PAGES_PATH . '/404.php';
    return;
}

$canonical = (string)$page['canonical'];
$title = (string)$page['title'];
$description = (string)$page['description'];
$products = (array)$page['products'];
$articles = (array)$page['articles'];

set_seo([
    'title' => $title . ' - ' . SITE_NAME,
    'description' => limit_chars($description, 155),
    'keywords' => implode(', ', array_filter([$page['label'] ?? '', $page['term_type'] ?? '', 'produk', 'jasa', 'artikel', SITE_NAME])),
    'canonical' => $canonical,
    'robots' => 'index, follow',
    'type' => 'website',
]);

if (function_exists('add_schema')) {
    add_schema([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $title,
        'description' => $description,
        'url' => $canonical,
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($products) + count($articles),
            'itemListElement' => array_values(array_map(static function (array $match, int $index): array {
                $item = (array)($match['item'] ?? []);
                $isProduct = (string)($match['type'] ?? '') === 'product';
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => (string)($item['title'] ?? 'Item'),
                    'url' => $isProduct ? product_url((string)($item['slug'] ?? '')) : article_url((string)($item['slug'] ?? '')),
                ];
            }, (array)$page['matches'], array_keys((array)$page['matches']))),
        ],
    ]);
}

breadcrumb_schema([
    ['name' => 'Home', 'url' => url()],
    ['name' => ucfirst((string)$page['term_type']), 'url' => url((string)$page['term_type'])],
    ['name' => (string)$page['label'], 'url' => $canonical],
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero dynamic-term-hero">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span><?= esc($title); ?></span></div>
        <span class="dynamic-mini-label">Dynamic SEO Server-side</span>
        <h1><?= esc($title); ?></h1>
        <p><?= esc($description); ?></p>
        <div class="dynamic-chip-wrap">
            <span class="dynamic-chip"><?= (int)$page['product_count']; ?> produk/jasa</span>
            <span class="dynamic-chip"><?= (int)$page['article_count']; ?> artikel</span>
            <span class="dynamic-chip">Slug: <?= esc((string)$page['slug']); ?></span>
        </div>
    </div>
</section>

<?php if ($products): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Produk & Jasa Terkait</h2>
                <p>Data diambil langsung dari katalog berdasarkan kategori, tag, keyword, slug, dan isi konten.</p>
            </div>
            <a class="button button-outline" href="<?= url('katalog'); ?>">Lihat Katalog</a>
        </div>
        <div class="cards3">
            <?php foreach (array_slice($products, 0, 12) as $product): ?>
                <?php require ROOT_PATH . '/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($articles): ?>
<section class="section alt">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Artikel Pendukung</h2>
                <p>Konten edukasi yang relevan dengan topik ini.</p>
            </div>
            <a class="button button-outline" href="<?= url('artikel'); ?>">Lihat Artikel</a>
        </div>
        <div class="cards3">
            <?php foreach (array_slice($articles, 0, 9) as $article): ?>
                <?php require ROOT_PATH . '/components/article-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="dynamic-two-columns">
            <div class="dynamic-panel">
                <h2>Kenapa halaman ini dinamis?</h2>
                <p>Halaman ini tidak dibuat hardcode satu per satu. Sistem membaca data produk, jasa, artikel, kategori, tag, keyword, dan slug secara server-side, lalu hanya menampilkan halaman jika ada konten terkait.</p>
            </div>
            <div class="dynamic-panel">
                <h2>Butuh dibantu?</h2>
                <p>Klik tombol konsultasi untuk menanyakan stok, harga terbaru, jadwal, atau rekomendasi item yang paling sesuai.</p>
                <a class="button" href="<?= wa_link('Halo admin, saya ingin tanya tentang ' . (string)$page['label']); ?>">Konsultasi via WhatsApp</a>
            </div>
        </div>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
