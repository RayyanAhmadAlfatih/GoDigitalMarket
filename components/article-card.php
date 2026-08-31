<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$article = $article ?? [];
$title = $article['title'] ?? 'Artikel Bisnis UMKM';
$slug = $article['slug'] ?? slugify($title);
$image = $article['image'] ?? asset('images/placeholder-article.svg');
$excerpt = $article['excerpt'] ?? 'Artikel edukasi untuk membantu pelanggan memahami produk, layanan, dan bisnis Anda.';
$category = $article['category'] ?? 'Artikel';
$author = $article['author'] ?? SITE_NAME;
$publishedAt = $article['published_at'] ?? date('Y-m-d');
$readingTime = $article['reading_time'] ?? '5 Menit';
$url = $article['url'] ?? article_url($slug);
?>
<article class="card article-card" itemscope itemtype="https://schema.org/Article">
    <div class="card-image article-image">
        <a href="<?= esc($url); ?>" title="<?= esc($title); ?>" itemprop="url"><img src="<?= esc($image); ?>" alt="<?= esc(image_alt($title, 'Artikel bisnis UMKM')); ?>" loading="lazy" width="600" height="400" itemprop="image"></a>
    </div>
    <div class="card-content article-content-card">
        <div class="article-category"><?= esc($category); ?></div>
        <h3 class="card-title article-title" itemprop="headline"><a href="<?= esc($url); ?>" title="<?= esc($title); ?>"><?= esc($title); ?></a></h3>
        <p class="article-excerpt" itemprop="description"><?= esc(limit_words($excerpt, 22)); ?></p>
        <?php if (function_exists('dynamic_v3_relevance_badge') && ($__dynamicBadge = dynamic_v3_relevance_badge($article))): ?>
            <div class="dynamic-relevance-badge"><?= esc($__dynamicBadge); ?></div>
        <?php endif; ?>
        <div class="article-meta">
            <span class="article-author" itemprop="author" itemscope itemtype="https://schema.org/Organization">✍ <span itemprop="name"><?= esc($author); ?></span></span>
            <span class="article-date">📅 <time datetime="<?= esc($publishedAt); ?>" itemprop="datePublished"><?= esc(date('d M Y', strtotime($publishedAt))); ?></time></span>
            <span class="article-reading-time">⏱ <?= esc($readingTime); ?></span>
        </div>
        <div class="article-actions"><a href="<?= esc($url); ?>" class="article-readmore-button" <?= conversion_link_attrs(['source'=>'article-card','type'=>'internal','category'=>(string)$category,'label'=>(string)$title,'intent'=>'article-read']); ?>>Baca Artikel</a></div>
    </div>
    <meta itemprop="mainEntityOfPage" content="<?= esc($url); ?>"><meta itemprop="publisher" content="<?= esc(SITE_NAME); ?>">
</article>
