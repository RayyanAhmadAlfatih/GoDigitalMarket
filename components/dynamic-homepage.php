<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$dailyProducts = function_exists('dynamic_v3_contextual_homepage_products') ? dynamic_v3_contextual_homepage_products(3) : dynamic_daily_recommended_products(3);
$homeLatestArticles = function_exists('dynamic_v3_contextual_homepage_articles') ? dynamic_v3_contextual_homepage_articles(3) : (function_exists('latest_articles') ? latest_articles(3) : []);
$weeklyArticles = dynamic_weekly_articles(3);
$latestDynamicProducts = dynamic_latest_products(3);
$popularDynamicProducts = function_exists('dynamic_v3_contextual_homepage_products') ? dynamic_v3_contextual_homepage_products(3) : dynamic_popular_products(3);
$areaLayananUpdate = dynamic_area_layanan_update();
$dynamicFaq = dynamic_rotating_faq(4);
$dynamicUpdatedAt = dynamic_content_updated_at();
$homeLocationProfiles = array_slice(dynamic_v28_location_profiles(), 0, 4);
if ($homeLatestArticles && $weeklyArticles) {
    $latestArticleSlugs = array_map(static fn(array $item): string => (string)($item['slug'] ?? ''), $homeLatestArticles);
    $weeklyArticles = array_values(array_filter($weeklyArticles, static fn(array $item): bool => !in_array((string)($item['slug'] ?? ''), $latestArticleSlugs, true)));
}

?>


<style id="homepage-recommendation-ui-v25">
/* =========================================
HOMEPAGE RECOMMENDATION UI - v25 INLINE SAFETY
Scoped langsung di component agar tidak kalah cache/urutan app.css.
========================================= */
.dynamic-overview-section,
.dynamic-section,
.dynamic-area-layanan-section,
.dynamic-faq-section {
    position: relative !important;
    overflow: hidden !important;
}

.dynamic-overview-section {
    padding: 86px 0 76px !important;
    background: linear-gradient(180deg, color-mix(in srgb,var(--bg) 84%,#ffffff) 0%, #ffffff 100%) !important;
}

.dynamic-section-head {
    max-width: 960px !important;
    margin: 0 auto 42px !important;
    text-align: center !important;
}

.dynamic-section-head .title {
    margin: 10px 0 14px !important;
    color: var(--primary-dark) !important;
    font-size: clamp(32px, 4vw, 48px) !important;
    line-height: 1.18 !important;
    font-weight: 950 !important;
}

.dynamic-section-head .center {
    max-width: 780px !important;
    margin: 0 auto !important;
    color: var(--text-light) !important;
    font-size: 16px !important;
    line-height: 1.85 !important;
}

.dynamic-eyebrow,
.dynamic-mini-label {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: fit-content !important;
    max-width: 100% !important;
    padding: 8px 14px !important;
    border-radius: 999px !important;
    background: color-mix(in srgb,var(--secondary-light) 55%,#ffffff) !important;
    color: var(--primary) !important;
    border: 1px solid var(--border) !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    letter-spacing: .35px !important;
    text-transform: uppercase !important;
    line-height: 1 !important;
}

.dynamic-updated-pill {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-top: 20px !important;
    padding: 12px 18px !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    border: 1px solid var(--border) !important;
    color: var(--text) !important;
    box-shadow: 0 14px 34px rgba(15, 23, 42, .07) !important;
    font-size: 14px !important;
    font-weight: 700 !important;
}

.dynamic-block {
    margin-top: 28px !important;
}

.dynamic-block-head {
    display: flex !important;
    align-items: flex-end !important;
    justify-content: space-between !important;
    gap: 18px !important;
    margin: 0 0 24px !important;
}

.dynamic-block-head h2,
.dynamic-block-head h3 {
    margin: 10px 0 0 !important;
    color: var(--primary-dark) !important;
    font-size: clamp(24px, 3vw, 34px) !important;
    line-height: 1.22 !important;
    font-weight: 950 !important;
}

.dynamic-block-head.compact h3 {
    font-size: 24px !important;
}

.left-title {
    text-align: left !important;
    margin: 8px 0 0 !important;
}

.dynamic-more-link {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 46px !important;
    padding: 12px 20px !important;
    border-radius: 999px !important;
    background: linear-gradient(135deg, var(--button), var(--primary-dark)) !important;
    color: #ffffff !important;
    font-weight: 900 !important;
    text-decoration: none !important;
    box-shadow: 0 14px 32px color-mix(in srgb,var(--primary) 20%,transparent) !important;
    white-space: nowrap !important;
}

.dynamic-card-grid {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 28px !important;
    align-items: stretch !important;
}

.dynamic-card-grid .product,
.dynamic-card-grid .product-card {
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    border-radius: 22px !important;
    background: #ffffff !important;
    border: 1px solid color-mix(in srgb,var(--primary) 8%,transparent) !important;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .08) !important;
}

.dynamic-card-grid .product img,
.dynamic-card-grid .product-card img {
    width: 100% !important;
    height: 260px !important;
    max-height: 260px !important;
    object-fit: cover !important;
    object-position: center !important;
}

.dynamic-card-grid .product-content,
.dynamic-card-grid .product-card-content {
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
    padding: 22px !important;
}

.dynamic-card-grid .product-content h3,
.dynamic-card-grid .product-card-content h3 {
    color: var(--primary-dark) !important;
    font-size: 21px !important;
    line-height: 1.35 !important;
    font-weight: 950 !important;
}

.dynamic-card-grid .product-card-cta,
.dynamic-card-grid .product-content .btn {
    margin-top: auto !important;
}

.dynamic-area-layanan-section {
    padding: 76px 0 !important;
    background:
        radial-gradient(circle at top right, color-mix(in srgb,var(--secondary) 18%,transparent 82%), transparent 30%),
        linear-gradient(180deg, color-mix(in srgb,var(--bg) 90%,#ffffff 10%), #ffffff) !important;
}

.dynamic-area-layanan-card {
    display: grid !important;
    grid-template-columns: minmax(0, 1.6fr) minmax(280px, .8fr) !important;
    gap: 28px !important;
    align-items: center !important;
    padding: 36px !important;
    border-radius: 30px !important;
    background: linear-gradient(135deg,
        color-mix(in srgb,var(--primary-dark) 92%,#111827 8%),
        color-mix(in srgb,var(--primary) 92%,#0f172a 8%)) !important;
    color: #ffffff !important;
    box-shadow: 0 26px 70px color-mix(in srgb,var(--primary) 24%,transparent 76%) !important;
}

.dynamic-area-layanan-card h2 {
    margin: 0 0 14px !important;
    color: #ffffff !important;
    font-size: clamp(28px, 3vw, 38px) !important;
    line-height: 1.22 !important;
    font-weight: 950 !important;
}

.dynamic-area-layanan-card p {
    margin: 0 !important;
    color: rgba(255,255,255,.92) !important;
    font-size: 16px !important;
    line-height: 1.9 !important;
}

.dynamic-area-layanan-card .dynamic-mini-label {
    background: rgba(255,255,255,.15) !important;
    border-color: rgba(255,255,255,.20) !important;
    color: #ffffff !important;
}

.dynamic-area-layanan-meta {
    display: grid !important;
    gap: 12px !important;
    padding: 22px !important;
    border-radius: 22px !important;
    background: rgba(255,255,255,.12) !important;
    border: 1px solid rgba(255,255,255,.20) !important;
}

.dynamic-area-layanan-meta span {
    color: #ffffff !important;
    font-weight: 800 !important;
    line-height: 1.5 !important;
}

.dynamic-area-layanan-meta a {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 46px !important;
    padding: 12px 18px !important;
    border-radius: 999px !important;
    background: var(--secondary) !important;
    color: var(--primary-dark) !important;
    font-weight: 950 !important;
    text-decoration: none !important;
}

.dynamic-two-columns {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 26px !important;
}

.dynamic-panel {
    background: #ffffff !important;
    border: 1px solid #dce8df !important;
    border-radius: 26px !important;
    padding: 26px !important;
    box-shadow: 0 18px 45px rgba(15, 23, 42, .06) !important;
}

.dynamic-list-products {
    display: grid !important;
    gap: 14px !important;
}

.dynamic-list-item {
    display: grid !important;
    grid-template-columns: 92px minmax(0, 1fr) !important;
    gap: 15px !important;
    align-items: center !important;
    padding: 13px !important;
    border-radius: 18px !important;
    background: #f8fbf7 !important;
    border: 1px solid #e2eee6 !important;
    text-decoration: none !important;
}

.dynamic-list-item img {
    width: 92px !important;
    height: 74px !important;
    object-fit: cover !important;
    border-radius: 14px !important;
}

.dynamic-list-item strong {
    display: block !important;
    margin-bottom: 5px !important;
    color: #0f341c !important;
    font-weight: 950 !important;
    line-height: 1.35 !important;
}

.dynamic-list-item small {
    display: block !important;
    color: #647067 !important;
    font-weight: 700 !important;
    line-height: 1.45 !important;
}

.dynamic-faq-section {
    padding: 84px 0 !important;
    background: linear-gradient(180deg, #ffffff, color-mix(in srgb,var(--bg) 84%,#ffffff)) !important;
}

.dynamic-faq-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 20px !important;
    margin-top: 30px !important;
}

.dynamic-faq-card {
    padding: 26px !important;
    border-radius: 24px !important;
    background: #ffffff !important;
    border: 1px solid #e1eadd !important;
    box-shadow: 0 16px 42px rgba(15, 23, 42, .06) !important;
}

.dynamic-faq-card h3 {
    margin: 0 0 12px !important;
    color: #0f341c !important;
    font-size: 19px !important;
    line-height: 1.35 !important;
    font-weight: 950 !important;
}

.dynamic-faq-card p {
    margin: 0 !important;
    color: #4b5d52 !important;
    line-height: 1.85 !important;
}

@media (max-width: 991px) {
    .dynamic-card-grid,
    .dynamic-two-columns,
    .dynamic-area-layanan-card,
    .dynamic-faq-grid {
        grid-template-columns: 1fr !important;
    }

    .dynamic-block-head {
        align-items: flex-start !important;
        flex-direction: column !important;
    }
}
</style>
<!-- HOMEPAGE RECOMMENDATION CONTENT -->
<section class="section dynamic-section dynamic-overview-section">
    <div class="container">
        <div class="dynamic-section-head">
            <span class="dynamic-eyebrow">Pilihan Terbaru</span>
            <h2 class="title">Rekomendasi Produk & Layanan Terkini</h2>
            <p class="center">
                Kami menampilkan pilihan produk dan artikel yang relevan berdasarkan stok, produk unggulan, dan panduan edukasi yang tersedia.
            </p>
            <div class="dynamic-updated-pill">
                Terakhir diperbarui: <strong><?= esc($dynamicUpdatedAt); ?></strong>
            </div>
        </div>

        <?php if ($dailyProducts): ?>
            <div class="dynamic-block">
                <div class="dynamic-block-head">
                    <div>
                        <span class="dynamic-mini-label">Pilihan Hari Ini</span>
                        <h3>Produk Rekomendasi Hari Ini</h3>
                    </div>
                    <a href="<?= url('katalog'); ?>" class="dynamic-more-link">Lihat Semua Produk</a>
                </div>

                <div class="cards3 dynamic-card-grid">
                    <?php foreach ($dailyProducts as $product): ?>
                        <?php require ROOT_PATH . '/components/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- UPDATE KATALOG HARI INI -->
<section class="section alt dynamic-area-layanan-section">
    <div class="container">
        <div class="dynamic-area-layanan-card">
            <div>
                <span class="dynamic-mini-label">Update Area Layanan Hari Ini</span>
                <h2><?= esc($areaLayananUpdate['title'] ?? 'Update Area Layanan Hari Ini'); ?></h2>
                <p><?= esc($areaLayananUpdate['text'] ?? 'Tim kami melakukan pengecekan berkala untuk menjaga kualitas produk dan layanan.'); ?></p>
            </div>
            <div class="dynamic-area-layanan-meta">
                <span>📍 <?= esc($areaLayananUpdate['location'] ?? 'Indonesia'); ?></span>
                <span>🗓 <?= esc($areaLayananUpdate['date'] ?? dynamic_format_date_id()); ?></span>
                <a href="<?= wa_link('saya ingin bertanya update stok area layanan hari ini.'); ?>" target="_blank" rel="nofollow noopener">Tanya Update Area Layanan</a>
            </div>
        </div>
    </div>
</section>


<!-- AREA LAYANAN RINGKAS -->
<?php if (!empty($homeLocationProfiles)): ?>
<section class="section dynamic-home-location-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Area Layanan</span>
                <h2>Temukan Produk dari Lokasi Terdekat</h2>
            </div>
            <a href="<?= url('kontak'); ?>" class="dynamic-more-link">Lihat Semua Lokasi</a>
        </div>
        <div class="home-location-grid">
            <?php foreach ($homeLocationProfiles as $location): ?>
                <a class="home-location-card" href="<?= esc((string)$location['url']); ?>">
                    <strong><?= esc((string)$location['name']); ?></strong>
                    <span><?= esc((string)$location['summary']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- LATEST + POPULAR PRODUCTS -->
<section class="section dynamic-section">
    <div class="container">
        <div class="dynamic-two-columns">
            <?php if ($latestDynamicProducts): ?>
                <div class="dynamic-panel">
                    <div class="dynamic-block-head compact">
                        <div>
                            <span class="dynamic-mini-label">Baru Ditambahkan</span>
                            <h3>Produk Terbaru</h3>
                        </div>
                    </div>
                    <div class="dynamic-list-products">
                        <?php foreach ($latestDynamicProducts as $product): ?>
                            <a href="<?= product_url((string)($product['slug'] ?? '')); ?>" class="dynamic-list-item">
                                <img src="<?= esc((string)($product['image'] ?? asset('images/placeholder-product.svg'))); ?>" alt="<?= esc((string)($product['title'] ?? 'Produk Produk')); ?>" loading="lazy" width="96" height="72">
                                <span>
                                    <strong><?= esc((string)($product['title'] ?? 'Produk Produk')); ?></strong>
                                    <small><?= esc((string)($product['category'] ?? 'Produk')); ?> · <?= esc(rupiah((int)($product['price'] ?? 0))); ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($popularDynamicProducts): ?>
                <div class="dynamic-panel">
                    <div class="dynamic-block-head compact">
                        <div>
                            <span class="dynamic-mini-label">Paling Diminati</span>
                            <h3>Produk Populer / Unggulan</h3>
                        </div>
                    </div>
                    <div class="dynamic-list-products">
                        <?php foreach ($popularDynamicProducts as $product): ?>
                            <a href="<?= product_url((string)($product['slug'] ?? '')); ?>" class="dynamic-list-item">
                                <img src="<?= esc((string)($product['image'] ?? asset('images/placeholder-product.svg'))); ?>" alt="<?= esc((string)($product['title'] ?? 'Produk Produk')); ?>" loading="lazy" width="96" height="72">
                                <span>
                                    <strong><?= esc((string)($product['title'] ?? 'Produk Produk')); ?></strong>
                                    <small><?= !empty($product['featured']) ? 'Produk unggulan' : 'Rekomendasi katalog'; ?> · <?= esc((string)($product['location'] ?? 'Indonesia')); ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>



<!-- LATEST ARTICLES -->
<?php if ($homeLatestArticles): ?>
<section class="section alt dynamic-section home-article-polish-section home-latest-article-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Artikel Terbaru</span>
                <h2 class="title left-title">Artikel Terbaru dari Panduan Kami</h2>
            </div>
            <a href="<?= url('artikel'); ?>" class="dynamic-more-link">Lihat Semua Artikel</a>
        </div>
        <p class="center">Bacaan terbaru untuk membantu pelanggan memahami pilihan produk, layanan, area layanan, dan proses booking sebelum konsultasi.</p>
        <div class="cards3 dynamic-card-grid">
            <?php foreach ($homeLatestArticles as $article): ?>
                <?php require ROOT_PATH . '/components/article-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- WEEKLY ARTICLES -->
<?php if ($weeklyArticles): ?>
<section class="section alt dynamic-section home-article-polish-section home-weekly-polish-section">
    <div class="container">
        <div class="dynamic-block-head">
            <div>
                <span class="dynamic-mini-label">Pilihan Minggu Ini</span>
                <h2 class="title left-title">Artikel Edukasi Pilihan Minggu Ini</h2>
            </div>
            <a href="<?= url('artikel'); ?>" class="dynamic-more-link">Lihat Semua Artikel</a>
        </div>

        <div class="cards3 dynamic-card-grid">
            <?php foreach ($weeklyArticles as $article): ?>
                <?php require ROOT_PATH . '/components/article-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ROTATING FAQ -->
<?php if ($dynamicFaq): ?>
<section class="section dynamic-faq-section">
    <div class="container">
        <h2 class="title">FAQ Produk & Layanan Pilihan</h2>
        <p class="center">Jawaban ringkas untuk pertanyaan umum sebelum memilih produk atau layanan layanan.</p>

        <div class="dynamic-faq-grid">
            <?php foreach ($dynamicFaq as $item): ?>
                <article class="dynamic-faq-card">
                    <h3><?= esc((string)($item['question'] ?? 'Pertanyaan')); ?></h3>
                    <p><?= esc((string)($item['answer'] ?? 'Silakan hubungi admin untuk informasi lebih lanjut.')); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
