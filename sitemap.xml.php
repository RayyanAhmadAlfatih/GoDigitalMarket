<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    ['loc' => url(''), 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => url('katalog'), 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => url('layanan'), 'changefreq' => 'weekly', 'priority' => '0.85'],
    ['loc' => url('portfolio'), 'changefreq' => 'monthly', 'priority' => '0.75'],
    ['loc' => url('artikel'), 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => url('tentang-kami'), 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => url('kontak'), 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => url('privacy-policy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
    ['loc' => url('terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
];

foreach (all_products() as $product) {
    if (($product['status'] ?? 'published') !== 'draft') {
        $urls[] = ['loc' => function_exists('seo_preservation_product_canonical') ? seo_preservation_product_canonical($product) : product_url((string)($product['slug'] ?? '')), 'changefreq' => 'weekly', 'priority' => '0.8'];
    }
}

foreach (all_articles() as $article) {
    $urls[] = ['loc' => function_exists('seo_preservation_article_canonical') ? seo_preservation_article_canonical($article) : article_url((string)($article['slug'] ?? '')), 'changefreq' => 'monthly', 'priority' => '0.7'];
}


if (function_exists('landing_page_all')) {
    foreach (landing_page_all(true) as $page) {
        if ((string)($page['status'] ?? '') === 'published' && !empty($page['indexable'])) {
            $urls[] = ['loc' => function_exists('seo_preservation_landing_canonical') ? seo_preservation_landing_canonical($page) : landing_page_url((string)($page['slug'] ?? '')), 'changefreq' => 'monthly', 'priority' => '0.65'];
        }
    }
}

if (function_exists('custom_form_read_forms')) {
    foreach (custom_form_read_forms() as $form) {
        if ((string)($form['status'] ?? 'draft') === 'active') {
            $urls[] = ['loc' => url('form/' . (string)($form['slug'] ?? '')), 'changefreq' => 'monthly', 'priority' => '0.5'];
        }
    }
}

if (function_exists('dynamic_term_sitemap_urls')) {
    $urls = dynamic_term_sitemap_urls($urls);
}

if (function_exists('universal_seo_sitemap_urls')) {
    $urls = universal_seo_sitemap_urls($urls);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "
";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $item): ?>
    <url>
        <loc><?= htmlspecialchars((string)$item['loc'], ENT_XML1, 'UTF-8'); ?></loc>
        <changefreq><?= htmlspecialchars((string)$item['changefreq'], ENT_XML1, 'UTF-8'); ?></changefreq>
        <priority><?= htmlspecialchars((string)$item['priority'], ENT_XML1, 'UTF-8'); ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
