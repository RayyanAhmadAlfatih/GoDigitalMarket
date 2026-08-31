<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$slug = (string)($_GET['slug'] ?? segment(2) ?? '');
$article = get_article_by_slug($slug);
if (!$article) {
    http_response_code(404);
    require ROOT_PATH . '/pages/404.php';
    exit;
}

$articleLabel = function_exists('business_label') ? business_label('article', 'Artikel') : 'Artikel';
$title = trim((string)($article['title'] ?? $articleLabel));
$description = trim((string)($article['meta_description'] ?? '')) ?: trim((string)($article['excerpt'] ?? 'Artikel bisnis UMKM.'));
$image = trim((string)($article['image'] ?? '')) ?: asset('images/default-article.jpg');
$canonical = function_exists('seo_preservation_article_canonical') ? seo_preservation_article_canonical($article) : (trim((string)($article['canonical_url'] ?? '')) ?: article_permalink($article));
$articleBodyRaw = (string)($article['content'] ?? '');
$dynamicArticleEnhancement = function_exists('dynamic_article_content_with_toc')
    ? dynamic_article_content_with_toc($articleBodyRaw)
    : ['content' => $articleBodyRaw, 'toc' => []];
$dynamicArticleContent = (string)($dynamicArticleEnhancement['content'] ?? $articleBodyRaw);
$dynamicArticleToc = is_array($dynamicArticleEnhancement['toc'] ?? null) ? $dynamicArticleEnhancement['toc'] : [];
$dynamicArticleFaqItems = function_exists('dynamic_article_faq') ? dynamic_article_faq($article, 5) : (array)($article['faq'] ?? []);
$dynamicArticleSummaryPoints = function_exists('dynamic_article_summary_points') ? dynamic_article_summary_points($article, strip_tags($dynamicArticleContent)) : [];
$dynamicArticleTopicLinks = function_exists('dynamic_article_topic_links') ? dynamic_article_topic_links($article) : [];
$publishedAtRaw = trim((string)($article['published_at'] ?? ''));
$updatedAtRaw = trim((string)($article['updated_at'] ?? ''));
$publishedAtLabel = function_exists('dynamic_format_date_id') ? dynamic_format_date_id($publishedAtRaw ?: null) : date('d M Y', strtotime($publishedAtRaw ?: 'now'));
$updatedAtLabel = function_exists('dynamic_format_date_id') ? dynamic_format_date_id($updatedAtRaw ?: $publishedAtRaw ?: null) : date('d M Y', strtotime($updatedAtRaw ?: $publishedAtRaw ?: 'now'));
$readingTime = trim((string)($article['reading_time'] ?? '')) ?: estimate_reading_time($articleBodyRaw);
$author = trim((string)($article['author'] ?? SITE_NAME)) ?: SITE_NAME;
$category = trim((string)($article['category'] ?? $articleLabel)) ?: $articleLabel;
$breadcrumbTitle = trim((string)($article['breadcrumb_title'] ?? '')) ?: $title;
$articleTitleForView = $title;
$articleBreadcrumbTrail = function_exists('breadcrumb_migration_trail') ? breadcrumb_migration_trail($article, 'article', $articleTitleForView, $canonical) : [
    ['name' => 'Home', 'url' => url()],
    ['name' => $articleLabel, 'url' => url('artikel')],
    ['name' => $breadcrumbTitle, 'url' => $canonical],
];

set_seo([
    'title' => trim((string)($article['meta_title'] ?? '')) ?: ($title . ' - ' . SITE_NAME),
    'description' => limit_chars($description, 155),
    'keywords' => implode(', ', array_filter(array_merge((array)($article['keywords'] ?? []), array_map('trim', explode(',', (string)($article['meta_keywords'] ?? '')))))),
    'canonical' => $canonical,
    'robots' => (string)($article['robots'] ?? 'index, follow'),
    'type' => 'article',
    'image' => $image,
    'url' => $canonical,
]);

breadcrumb_schema($articleBreadcrumbTrail);

if ($dynamicArticleFaqItems && function_exists('faq_schema')) {
    faq_schema($dynamicArticleFaqItems);
}

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';

if (function_exists('content_restriction_allowed')) {
    $restrictionStatus = content_restriction_allowed('article', $article);
    if (empty($restrictionStatus['allowed'])) {
        content_restriction_render_gate($restrictionStatus, $articleTitleForView);
        require_once ROOT_PATH . '/components/layout/footer.php';
        return;
    }
}
?>

<section class="mini-hero article-detail-hero article-detail-hero--clean">
    <div class="container">
        <?php if (function_exists('breadcrumb_migration_render')) { breadcrumb_migration_render($articleBreadcrumbTrail); } ?>
        <h1><?= esc($articleTitleForView); ?></h1>
    </div>
</section>

<section class="section article-hero-meta-section">
    <div class="container">
        <div class="article-hero-meta-card">
            <div class="article-meta article-meta--rich" aria-label="Informasi artikel">
                <span>📂 <?= esc($category); ?></span>
                <span>📅 <?= esc($publishedAtLabel); ?></span>
                <span>🔄 Update <?= esc($updatedAtLabel); ?></span>
                <span>⏱ <?= esc($readingTime); ?></span>
                <span>✍️ <?= esc($author); ?></span>
            </div>
            <p class="article-hero-description"><?= esc($description); ?></p>
        </div>
    </div>
</section>

<?php if ($dynamicArticleSummaryPoints || $dynamicArticleTopicLinks): ?>
<section class="section alt article-summary-section dynamic-article-summary-section">
    <div class="container">
        <div class="dynamic-two-columns">
            <?php if ($dynamicArticleSummaryPoints): ?>
                <div class="dynamic-panel">
                    <span class="dynamic-mini-label">Ringkasan Artikel</span>
                    <h2>Poin Penting Sebelum Membaca Detail</h2>
                    <ul class="dynamic-summary-list">
                        <?php foreach ($dynamicArticleSummaryPoints as $point): ?>
                            <li><?= esc((string)$point); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($dynamicArticleTopicLinks): ?>
                <div class="dynamic-panel internal-link-panel">
                    <span class="dynamic-mini-label">Arah Cepat</span>
                    <h2>Topik Terkait Artikel Ini</h2>
                    <div class="dynamic-chip-wrap">
                        <?php foreach ($dynamicArticleTopicLinks as $link): ?>
                            <a class="dynamic-chip" href="<?= esc((string)($link['url'] ?? url('artikel'))); ?>"><?= esc((string)($link['label'] ?? 'Link terkait')); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container article-detail">
        <img src="<?= esc($image); ?>" alt="<?= esc((string)($article['image_alt'] ?? $title)); ?>" loading="eager" width="1200" height="630">

        <?php if ($dynamicArticleToc): ?>
            <div class="dynamic-panel internal-link-panel article-toc-panel">
                <div class="dynamic-block-head compact-head">
                    <div>
                        <span class="dynamic-mini-label">Daftar Isi</span>
                        <h2>Bagian Penting Artikel Ini</h2>
                    </div>
                </div>
                <div class="dynamic-chip-wrap">
                    <?php foreach ($dynamicArticleToc as $tocItem): ?>
                        <a class="dynamic-chip" href="#<?= esc((string)($tocItem['id'] ?? '')); ?>"><?= esc((string)($tocItem['title'] ?? 'Bagian artikel')); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="article-content">
            <?= strip_tags($dynamicArticleContent, '<p><br><strong><b><em><i><u><s><del><ul><ol><li><h2><h3><h4><blockquote><a><img><table><thead><tbody><tr><th><td><hr>'); ?>
        </div>
    </div>
</section>

<?php if (is_file(ROOT_PATH . '/components/dynamic-article-detail.php')) { require ROOT_PATH . '/components/dynamic-article-detail.php'; } ?>

<section class="section alt">
    <div class="container">
        <?php
        $inquiryContext = [
            'title' => 'Ingin Konsultasi Setelah Membaca Artikel?',
            'text' => 'Sampaikan kebutuhan Anda, admin akan membantu memberi arahan produk, layanan, atau paket yang sesuai.',
            'source' => 'article-detail-form',
            'category' => $category,
            'intent' => 'article-inquiry',
            'label' => $articleTitleForView,
            'button' => 'Kirim Pertanyaan',
        ];
        require ROOT_PATH . '/components/inquiry-form.php';
        ?>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
