<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

$articleLabel = function_exists('business_label') ? business_label('article', 'Artikel') : 'Artikel';
$categorySlug = trim((string)($_GET['kategori'] ?? ''));
$selectedCategory = $categorySlug !== '' ? article_category_label($categorySlug) : '';
$articles = $selectedCategory !== '' ? articles_by_categories([$selectedCategory], 24) : latest_articles(24);
$editableArticlePage = function_exists('template_content_public_page') ? template_content_public_page('articles') : [];
$sectionValue = static function (string $sectionId, string $field, string $fallback = '') use ($editableArticlePage): string {
    return function_exists('template_content_section_value') ? template_content_section_value($editableArticlePage, $sectionId, $field, $fallback) : $fallback;
};
$sectionVisible = static function (string $sectionId) use ($editableArticlePage): bool {
    return function_exists('template_content_section_visible') ? template_content_section_visible($editableArticlePage, $sectionId) : true;
};
$pageTitle = $selectedCategory !== '' ? $articleLabel . ' ' . $selectedCategory : $sectionValue('hero', 'title', $articleLabel . ' & Panduan Bisnis');
$pageDescription = $sectionValue('hero', 'description', 'Kumpulan artikel edukasi untuk membantu pelanggan memahami produk, layanan, checkout, marketing, dan informasi bisnis Anda.');

set_seo(['title'=>(string)($editableArticlePage['meta_title'] ?? '') !== '' ? (string)$editableArticlePage['meta_title'] : $pageTitle . ' - ' . SITE_NAME,'description'=>(string)($editableArticlePage['meta_description'] ?? '') !== '' ? limit_chars((string)$editableArticlePage['meta_description'],155) : limit_chars($pageDescription,155),'keywords'=>'artikel umkm, panduan bisnis, marketing umkm, checkout online, katalog produk','canonical'=>url('artikel'),'robots'=>'index, follow','type'=>'website','image'=>asset('images/default-article.jpg')]);
breadcrumb_schema([['name'=>'Home','url'=>url()],['name'=>$articleLabel,'url'=>url('artikel')]]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero"><div class="container"><div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span><?= esc($articleLabel); ?></span></div><h1><?= esc($pageTitle); ?></h1><p><?= esc($pageDescription); ?></p></div></section>
<?php if ($sectionVisible('categories')): ?>
<section class="section alt"><div class="container"><span class="section-eyebrow"><?= esc($sectionValue('categories', 'eyebrow', 'Kategori')); ?></span><h2 class="title"><?= esc($sectionValue('categories', 'title', $articleLabel)); ?></h2><p class="center"><?= esc($sectionValue('categories', 'description', 'Update edukasi dan panduan bisnis berdasarkan kategori.')); ?></p><div class="article-category-grid"><a class="article-category-tile <?= $selectedCategory === '' ? 'active' : ''; ?>" href="<?= url('artikel'); ?>"><strong><?= esc('Semua ' . $articleLabel); ?></strong><span>Update edukasi dan panduan bisnis.</span></a><?php foreach (article_category_definitions() as $slug => $definition): ?><a class="article-category-tile <?= $selectedCategory === (string)$definition['label'] ? 'active' : ''; ?>" href="<?= url('artikel?' . http_build_query(['kategori'=>$slug])); ?>"><strong><?= esc((string)$definition['label']); ?></strong><span><?= esc((string)$definition['description']); ?></span></a><?php endforeach; ?></div></div></section>
<?php endif; ?>
<section class="section"><div class="container"><span class="section-eyebrow"><?= esc($sectionValue('article_list', 'eyebrow', $articleLabel)); ?></span><h2 class="title"><?= esc($selectedCategory !== '' ? $articleLabel . ' ' . $selectedCategory : $sectionValue('article_list', 'title', $articleLabel . ' Terbaru')); ?></h2><p class="center"><?= esc($selectedCategory !== '' ? 'Konten sesuai kategori pilihan Anda.' : $sectionValue('article_list', 'description', 'Artikel edukasi siap diganti sesuai niche dan target pelanggan bisnis.')); ?></p><div class="cards3"><?php if ($articles): ?><?php foreach ($articles as $article): ?><?php require ROOT_PATH . '/components/article-card.php'; ?><?php endforeach; ?><?php else: ?><div class="card"><div class="card-content"><h3>Belum ada artikel</h3><p>Silakan tambah artikel dari dashboard admin.</p></div></div><?php endif; ?></div></div></section>
<?php if (function_exists('template_content_render_custom_sections')) { template_content_render_custom_sections($editableArticlePage); } ?>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
