<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }
$query = trim((string)($_GET['q'] ?? ''));
set_seo(['title'=>$query ? 'Hasil Pencarian: ' . $query : 'Pencarian Produk & Artikel','description'=>'Cari produk, layanan, paket, booking, digital product, dan artikel edukasi.','keywords'=>'search produk umkm, cari layanan, cari artikel bisnis','canonical'=>url('search'),'robots'=>$query ? 'noindex, follow' : 'index, follow','type'=>'website','image'=>asset('images/og-default.jpg')]);
$productResults = $query !== '' ? search_products($query) : [];
$articleResults = $query !== '' ? search_articles($query) : [];
require_once ROOT_PATH . '/components/layout/head.php'; require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero"><div class="container"><div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span>Search</span></div><h1>Pencarian Produk & Artikel</h1><p>Cari produk, layanan, paket, booking, digital product, dan artikel edukasi.</p></div></section>
<section class="section"><div class="container"><div class="search-page-panel"><form action="<?= url('search'); ?>" method="get" class="search-page-form"><label class="search-page-label" for="searchPageInput">Cari produk atau artikel</label><div class="search-page-control"><input id="searchPageInput" type="text" name="q" value="<?= esc($query); ?>" placeholder="Contoh: paket menu, konsultasi, checkout..." autocomplete="off"><button type="submit" class="search-page-button">Cari</button></div></form><?php if ($query !== ''): ?><div class="search-page-info"><span>Hasil pencarian untuk</span><strong>“<?= esc($query); ?>”</strong></div><?php endif; ?></div><?php if ($query !== ''): ?><?php if ($productResults): ?><div class="search-section"><h2 class="title">Produk & Layanan</h2><div class="cards3"><?php foreach ($productResults as $product): ?><?php require ROOT_PATH . '/components/product-card.php'; ?><?php endforeach; ?></div></div><?php endif; ?><?php if ($articleResults): ?><div class="search-section"><h2 class="title">Artikel</h2><div class="cards3"><?php foreach ($articleResults as $article): ?><?php require ROOT_PATH . '/components/article-card.php'; ?><?php endforeach; ?></div></div><?php endif; ?><?php if (!$productResults && !$articleResults): ?><div class="card empty-state"><h3>Belum ada hasil</h3><p>Coba gunakan kata kunci lain.</p></div><?php endif; ?><?php endif; ?></div></section>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
