<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$filters = [
    'q' => (string)($_GET['q'] ?? ''),
    'category' => (string)($_GET['category'] ?? ''),
    'animal_type' => (string)($_GET['item_type'] ?? $_GET['animal_type'] ?? ''),
    'tier' => (string)($_GET['tier'] ?? ''),
    'location' => (string)($_GET['location'] ?? ''),
    'stock_status' => (string)($_GET['stock_status'] ?? ''),
];

$isServicePage = (($GLOBALS['catalog_page_context'] ?? '') === 'layanan') || route_is('layanan');
$allProducts = filter_products($filters);
if ($isServicePage && trim((string)$filters['animal_type']) === '' && trim((string)$filters['category']) === '' && trim((string)$filters['q']) === '') {
    $allProducts = array_values(array_filter($allProducts, static fn(array $product): bool => product_is_service_like($product) || product_item_type_key($product) === 'custom'));
}
$currentPage = current_page();
$perPage = 12;
$totalProducts = count($allProducts);
$totalPages = total_pages($totalProducts, $perPage);
if ($totalPages > 0 && $currentPage > $totalPages) { $currentPage = $totalPages; }
$products = array_slice($allProducts, ($currentPage - 1) * $perPage, $perPage);
$GLOBALS['pagination_total_pages'] = $totalPages;

$catalogPath = $isServicePage ? 'layanan' : 'katalog';
$templatePageKey = $isServicePage ? 'services' : 'catalog';
$editableCatalogPage = function_exists('template_content_public_page') ? template_content_public_page($templatePageKey) : [];
$sectionValue = static function (string $sectionId, string $field, string $fallback = '') use ($editableCatalogPage): string {
    return function_exists('template_content_section_value') ? template_content_section_value($editableCatalogPage, $sectionId, $field, $fallback) : $fallback;
};
$sectionVisible = static function (string $sectionId) use ($editableCatalogPage): bool {
    return function_exists('template_content_section_visible') ? template_content_section_visible($editableCatalogPage, $sectionId) : true;
};
$breadcrumbLabel = function_exists('business_label') ? ($isServicePage ? business_label('service', 'Layanan') : business_label('catalog', 'Katalog')) : ($isServicePage ? 'Layanan' : 'Katalog');
$activeLabel = $filters['category'] ?: ($filters['animal_type'] ?: 'Semua Produk & Layanan');

if ($isServicePage) {
    $pageTitle = 'Layanan & Jasa';
    if ($filters['category']) {
        $pageTitle = 'Layanan ' . $filters['category'];
    }
    $pageDescription = $sectionValue('hero', 'description', 'Temukan jasa, paket layanan, konsultasi, booking, dan layanan profesional yang bisa disesuaikan dengan bisnis UMKM Anda.');
    $seoKeywords = 'jasa umkm, layanan bisnis, booking layanan, katalog jasa, konsultasi online';
} else {
    $pageTitle = $activeLabel === 'Semua Produk & Layanan' ? 'Katalog Produk & Layanan' : 'Katalog ' . $activeLabel;
    $pageDescription = $sectionValue('hero', 'description', 'Temukan produk fisik, jasa, paket, menu, produk digital, e-book, e-course, dan booking yang bisa disesuaikan dengan bisnis UMKM Anda.');
    $seoKeywords = 'katalog produk umkm, katalog jasa, produk digital, ebook, ecourse, booking, toko online umkm';
}

set_seo([
    'title' => (string)($editableCatalogPage['meta_title'] ?? '') !== '' ? (string)$editableCatalogPage['meta_title'] : $pageTitle . ' - ' . SITE_NAME,
    'description' => (string)($editableCatalogPage['meta_description'] ?? '') !== '' ? limit_chars((string)$editableCatalogPage['meta_description'], 155) : limit_chars($pageDescription, 155),
    'keywords' => $seoKeywords,
    'canonical' => url($catalogPath),
    'robots' => $currentPage > 1 ? 'noindex, follow' : 'index, follow',
    'type' => 'website',
    'image' => asset('images/og-default.jpg'),
]);

breadcrumb_schema([
    ['name' => 'Home', 'url' => url()],
    ['name' => $breadcrumbLabel, 'url' => url($catalogPath)],
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span><?= esc($breadcrumbLabel); ?></span></div>
        <h1><?= esc($sectionValue('hero', 'title', $pageTitle)); ?></h1>
        <p><?= esc($pageDescription); ?></p>
    </div>
</section>

<?php if ($sectionVisible('hero')): ?>
<section class="section alt dynamic-catalog-intro">
    <div class="container">
        <div class="dynamic-block-head"><div><span class="dynamic-mini-label"><?= esc($sectionValue('hero', 'eyebrow', $isServicePage ? 'Layanan Fleksibel' : 'Katalog Fleksibel')); ?></span><h2><?= esc($sectionValue('hero', 'title', $isServicePage ? 'Jasa, Paket Layanan, Konsultasi, dan Booking' : 'Produk, Jasa, Paket, E-book, E-course, dan Digital Download')); ?></h2></div><span class="dynamic-updated-pill">Data diperbarui: <?= esc(date('d M Y')); ?></span></div>
        <div class="dynamic-panel">
            <p><?= esc($sectionValue('hero', 'description', 'Gunakan filter untuk menampilkan item yang paling sesuai: produk fisik, jasa, menu, booking, e-book, e-course, template digital, atau custom order.')); ?></p>
            <div class="dynamic-chip-wrap" aria-label="Rekomendasi filter">
                <a class="dynamic-chip" href="<?= url($catalogPath); ?>">Semua</a>
                <?php if ($isServicePage): ?>
                <a class="dynamic-chip" href="<?= url('layanan?item_type=' . rawurlencode('Jasa')); ?>">Jasa</a>
                <a class="dynamic-chip" href="<?= url('layanan?item_type=' . rawurlencode('Booking')); ?>">Booking</a>
                <a class="dynamic-chip" href="<?= url('layanan?category=' . rawurlencode('Konsultasi')); ?>">Konsultasi</a>
                <?php else: ?>
                <a class="dynamic-chip" href="<?= url('katalog?item_type=' . rawurlencode('Produk Fisik')); ?>">Produk Fisik</a>
                <a class="dynamic-chip" href="<?= url('katalog?item_type=' . rawurlencode('Jasa')); ?>">Jasa</a>
                <a class="dynamic-chip" href="<?= url('katalog?item_type=' . rawurlencode('Produk Digital')); ?>">Produk Digital</a>
                <a class="dynamic-chip" href="<?= url('katalog?item_type=' . rawurlencode('E-book / File Download')); ?>">E-book</a>
                <a class="dynamic-chip" href="<?= url('katalog?item_type=' . rawurlencode('E-course / Kelas Online')); ?>">E-course</a>
                <a class="dynamic-chip" href="<?= url('katalog?item_type=' . rawurlencode('Booking / Reservasi')); ?>">Booking</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container marketplace-layout">
        <aside class="marketplace-filter card">
            <h2><?= esc($sectionValue('filter_panel', 'title', $isServicePage ? 'Filter Layanan' : 'Filter Katalog')); ?></h2>
            <form method="get" action="<?= url($catalogPath); ?>">
                <label>Cari Produk/Jasa</label>
                <input name="q" value="<?= esc($filters['q']); ?>" placeholder="Contoh: paket menu, konsultasi, produk digital...">

                <label>Kategori</label>
                <select name="category"><option value="">Semua kategori</option><?php foreach (product_filter_options('category') as $option): ?><option value="<?= esc($option); ?>" <?= $filters['category'] === $option ? 'selected' : ''; ?>><?= esc($option); ?></option><?php endforeach; ?></select>

                <label>Tipe Item</label>
                <select name="item_type"><option value="">Semua tipe</option><?php foreach (product_animal_type_filter_options() as $option): ?><option value="<?= esc($option); ?>" <?= $filters['animal_type'] === $option ? 'selected' : ''; ?>><?= esc($option); ?></option><?php endforeach; ?></select>

                <label>Kelas / Paket</label>
                <select name="tier"><option value="">Semua kelas</option><?php foreach (array_unique(array_merge(['Ekonomis','Medium','Premium','Best Seller'], product_filter_options('tier'))) as $option): ?><option value="<?= esc($option); ?>" <?= $filters['tier'] === $option ? 'selected' : ''; ?>><?= esc($option); ?></option><?php endforeach; ?></select>

                <label>Area / Kanal</label>
                <select name="location"><option value="">Semua area</option><?php foreach (product_filter_options('location') as $option): ?><option value="<?= esc($option); ?>" <?= $filters['location'] === $option ? 'selected' : ''; ?>><?= esc($option); ?></option><?php endforeach; ?></select>

                <label>Status</label>
                <select name="stock_status">
                    <option value="">Semua status</option>
                    <option value="in_stock" <?= $filters['stock_status'] === 'in_stock' ? 'selected' : ''; ?>>Tersedia</option>
                    <option value="preorder" <?= $filters['stock_status'] === 'preorder' ? 'selected' : ''; ?>>Pre-order / Booking</option>
                    <option value="contact_admin" <?= $filters['stock_status'] === 'contact_admin' ? 'selected' : ''; ?>>Hubungi Admin</option>
                    <option value="out_of_stock" <?= $filters['stock_status'] === 'out_of_stock' ? 'selected' : ''; ?>>Tidak tersedia</option>
                </select>

                <button class="button" type="submit">Terapkan Filter</button>
                <a class="button button-outline" href="<?= url($catalogPath); ?>">Reset</a>
            </form>
        </aside>

        <div class="marketplace-results">
            <div class="section-head"><div><h2><?= (int)$totalProducts; ?> <?= esc($sectionValue('result_intro', 'title', $isServicePage ? 'Layanan Ditemukan' : 'Item Ditemukan')); ?></h2><p><?= esc($sectionValue('result_intro', 'description', 'Bandingkan kategori, tipe item, paket, area, akses digital, dan status sebelum lanjut bertanya atau checkout.')); ?></p></div></div>
            <?php if (!$products): ?>
                <div class="card empty-state dynamic-empty-state"><h3>Item belum ditemukan</h3><p>Coba ubah kata kunci atau filter.</p><div class="dynamic-chip-wrap"><a class="dynamic-chip" href="<?= url($catalogPath); ?>">Lihat Semua</a></div></div>
            <?php else: ?>
                <div class="cards3"><?php foreach ($products as $product): ?><?php require ROOT_PATH . '/components/product-card.php'; ?><?php endforeach; ?></div>
                <?php render_compact_pagination($currentPage, $totalPages); ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($sectionVisible('guide_left') || $sectionVisible('guide_right')): ?>
<section class="section alt">
    <div class="container">
        <div class="dynamic-two-columns">
            <?php if ($sectionVisible('guide_left')): ?>
                <div class="dynamic-panel"><h2><?= esc($sectionValue('guide_left', 'title', $isServicePage ? 'Panduan Memilih Layanan' : 'Panduan Memilih Produk/Jasa')); ?></h2><div><?= (string)($sectionValue('guide_left', 'body_html', '<p>Pilih berdasarkan kebutuhan pelanggan, kategori, harga, benefit, ketersediaan, area layanan, dan metode pemesanan. Untuk produk digital, perhatikan mode akses seperti download, link khusus, atau member area.</p>')); ?></div></div>
            <?php endif; ?>
            <?php if ($sectionVisible('guide_right')): ?>
                <div class="dynamic-panel"><h2><?= esc($sectionValue('guide_right', 'title', $isServicePage ? 'Kanal Layanan Populer' : 'Area Layanan Populer')); ?></h2><div class="dynamic-chip-wrap"><?php foreach (['Online','Jakarta','Bandung','Surabaya','Yogyakarta','Semarang','Area lain'] as $area): ?><a class="dynamic-chip" href="<?= url($catalogPath . '?location=' . rawurlencode($area)); ?>"><?= esc($area); ?></a><?php endforeach; ?></div><div class="editable-template-section-note"><?= (string)$sectionValue('guide_right', 'body_html', ''); ?></div></div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php if (function_exists('template_content_render_custom_sections')) { template_content_render_custom_sections($editableCatalogPage); } ?>

<?php if ($sectionVisible('lead_form')): ?>
<section class="section">
    <div class="container">
        <?php $inquiryContext = ['title'=>$sectionValue('lead_form', 'title', $isServicePage ? 'Butuh Konsultasi Layanan?' : 'Butuh Dibantu Pilih Produk/Jasa?'),'text'=>$sectionValue('lead_form', 'description', 'Isi form singkat jika ingin dibantu memilih produk, layanan, paket, atau jadwal yang paling cocok.'),'source'=>$isServicePage ? 'service-form' : 'catalog-form','category'=>$isServicePage ? 'layanan' : 'katalog','location'=>(string)($filters['location'] ?? ''),'intent'=>'catalog-inquiry','label'=>$isServicePage ? 'Permintaan Layanan' : 'Permintaan Katalog','button'=>$sectionValue('lead_form', 'button_label', 'Kirim Permintaan')]; require ROOT_PATH . '/components/inquiry-form.php'; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
