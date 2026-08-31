<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (empty($article) || !is_array($article)) {
    return;
}

$dynamicArticleForDetail = $article;
$dynamicArticleFaqItems = $dynamicArticleFaqItems ?? (function_exists('dynamic_article_faq') ? dynamic_article_faq($dynamicArticleForDetail, 4) : []);
$dynamicArticleRelatedProducts = function_exists('dynamic_article_related_products') ? dynamic_article_related_products($dynamicArticleForDetail, 3) : [];
$dynamicArticleRelatedArticles = function_exists('dynamic_article_related_articles') ? dynamic_article_related_articles($dynamicArticleForDetail, 3) : [];
$dynamicArticleNextSteps = function_exists('dynamic_article_next_steps') ? dynamic_article_next_steps($dynamicArticleForDetail) : [];
$dynamicArticleTopicLinks = function_exists('dynamic_article_topic_links') ? dynamic_article_topic_links($dynamicArticleForDetail) : [];
$dynamicArticleLocationLinks = function_exists('dynamic_template_article_location_links') ? dynamic_template_article_location_links($dynamicArticleForDetail, 4) : [];
$dynamicArticleTitle = (string)($dynamicArticleForDetail['title'] ?? 'Artikel ini');
?>

<?php if ($dynamicArticleNextSteps || $dynamicArticleTopicLinks): ?>
<section class="section alt dynamic-article-action-section">
    <div class="container">
        <div class="dynamic-two-columns">
            <?php if ($dynamicArticleNextSteps): ?>
                <div class="dynamic-panel">
                    <span class="dynamic-mini-label">Langkah Berikutnya</span>
                    <h2>Setelah Membaca Artikel Ini</h2>
                    <div class="dynamic-list-products">
                        <?php foreach ($dynamicArticleNextSteps as $step): ?>
                            <div class="dynamic-list-item dynamic-list-item--text-only">
                                <div>
                                    <strong><?= esc((string)($step['title'] ?? 'Langkah')); ?></strong>
                                    <small><?= esc((string)($step['text'] ?? '')); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($dynamicArticleTopicLinks || $dynamicArticleLocationLinks): ?>
                <div class="dynamic-panel internal-link-panel">
                    <span class="dynamic-mini-label">Halaman Terkait</span>
                    <h2>Topik yang Bisa Dilanjutkan</h2>
                    <div class="dynamic-chip-wrap">
                        <?php foreach ($dynamicArticleTopicLinks as $link): ?>
                            <a class="dynamic-chip" href="<?= esc((string)($link['url'] ?? url('artikel'))); ?>"><?= esc((string)($link['label'] ?? 'Link terkait')); ?></a>
                        <?php endforeach; ?>
                        <?php foreach ($dynamicArticleLocationLinks as $location): ?>
                            <a class="dynamic-chip" href="<?= esc((string)($location['url'] ?? url('katalog'))); ?>"><?= esc((string)($location['name'] ?? 'Area layanan')); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($dynamicArticleRelatedProducts): ?>
<section class="section dynamic-article-product-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Rekomendasi Terkait</span>
                <h2>Produk atau Layanan yang Relevan</h2>
            </div>
            <a class="dynamic-more-link" href="<?= esc(url('katalog')); ?>">Lihat katalog</a>
        </div>
        <p class="center">Rekomendasi ini dibuat dari konteks artikel: <?= esc($dynamicArticleTitle); ?>.</p>
        <div class="cards3 dynamic-card-grid">
            <?php foreach ($dynamicArticleRelatedProducts as $dynamicRelatedProduct): ?>
                <?php if (!is_array($dynamicRelatedProduct)) { continue; } ?>
                <?php $product = $dynamicRelatedProduct; require ROOT_PATH . '/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($dynamicArticleRelatedArticles): ?>
<section class="section alt dynamic-article-related-section article-related-polish-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Artikel Terkait</span>
                <h2>Bacaan Lanjutan yang Masih Relevan</h2>
            </div>
            <a class="dynamic-more-link" href="<?= esc(url('artikel')); ?>">Lihat semua artikel</a>
        </div>
        <div class="cards3 dynamic-card-grid">
            <?php foreach ($dynamicArticleRelatedArticles as $article): ?>
                <?php require ROOT_PATH . '/components/article-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($dynamicArticleFaqItems): ?>
<section class="section dynamic-article-faq-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">FAQ Artikel</span>
                <h2>Pertanyaan Lanjutan Setelah Membaca</h2>
            </div>
            <span class="dynamic-updated-pill">Update: <?= esc(function_exists('dynamic_content_updated_at') ? dynamic_content_updated_at() : date('d M Y')); ?></span>
        </div>
        <div class="dynamic-faq-grid">
            <?php foreach ($dynamicArticleFaqItems as $faqItem): ?>
                <article class="dynamic-faq-card">
                    <h3><?= esc((string)($faqItem['question'] ?? 'Pertanyaan')); ?></h3>
                    <p><?= esc((string)($faqItem['answer'] ?? '')); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
