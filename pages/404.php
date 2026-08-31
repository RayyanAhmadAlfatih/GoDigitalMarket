<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 404 PAGE
|--------------------------------------------------------------------------
| SEO Friendly Error Page
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

set_seo([
    'title' => '404 Halaman Tidak Ditemukan',
    'description' => 'Halaman yang Anda cari tidak ditemukan.',
    'keywords' => '404',
    'canonical' => strtok(current_url(), '?'),
    'robots' => 'noindex, follow',
    'type' => 'website',
    'image' => asset('images/placeholder-product.svg')
]);

/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';

?>

<!-- 404 -->
<section class="error-page">

    <div class="container">

        <div class="error-wrap">

            <div class="error-code">

                404

            </div>

            <h1>

                Halaman Tidak Ditemukan

            </h1>

            <p>

                Maaf, halaman yang Anda cari
                tidak tersedia atau sudah dipindahkan.

            </p>

            <!-- ACTION -->
            <div class="error-actions">

                <a
                    href="<?= url(); ?>"
                    class="cta">

                    Kembali ke Homepage

                </a>

                <a
                    href="<?= url('katalog'); ?>"
                    class="btn">

                    Lihat Katalog

                </a>

            </div>

            <!-- QUICK LINKS -->
            <div class="error-links">

                <a href="<?= url('katalog'); ?>">
                    Produk Fisik
                </a>

                <a href="<?= url('katalog-paket'); ?>">
                    Paket & Layanan
                </a>

                <a href="<?= url('layanan'); ?>">
                    Paket Layanan
                </a>

                <a href="<?= url('artikel'); ?>">
                    Artikel
                </a>

            </div>

        </div>

    </div>

</section>

<?php

require_once ROOT_PATH . '/components/layout/footer.php';
?>