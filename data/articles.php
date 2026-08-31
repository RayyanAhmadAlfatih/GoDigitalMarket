<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| SAMPLE ARTICLE DATA SOURCE
|--------------------------------------------------------------------------
| Generic SEO article seeds for UMKM Commerce Website Template.
|--------------------------------------------------------------------------
*/

return [
    [
        'id' => 1,
        'title' => 'Cara Membuat Katalog Produk yang Mudah Dipahami Pelanggan',
        'slug' => 'cara-membuat-katalog-produk',
        'category' => 'Panduan Bisnis',
        'excerpt' => 'Panduan singkat menyusun katalog produk atau layanan agar pelanggan cepat paham dan mudah bertanya.',
        'image' => asset('images/default-article.jpg'),
        'author' => SITE_NAME,
        'published_at' => '2026-01-01 08:00:00',
        'updated_at' => '2026-01-01 08:00:00',
        'reading_time' => '5 Menit',
        'featured' => true,
        'keywords' => ['katalog produk', 'website umkm', 'produk umkm'],
        'content' => '<p>Katalog yang rapi membantu pelanggan memahami pilihan produk atau layanan dengan cepat.</p><h2>Tulis Nama yang Jelas</h2><p>Gunakan nama produk yang mudah dicari dan mudah dipahami.</p><h2>Tambahkan Foto dan Benefit</h2><p>Foto, harga, manfaat, dan tombol kontak akan membantu pelanggan mengambil keputusan.</p>',
        'faq' => [
            ['question' => 'Apakah artikel ini bisa diganti?', 'answer' => 'Bisa. Admin dapat membuat, mengedit, dan menghapus artikel sesuai kebutuhan bisnis.'],
        ],
    ],
    [
        'id' => 2,
        'title' => 'Tips Menulis Deskripsi Layanan agar Lebih Meyakinkan',
        'slug' => 'tips-menulis-deskripsi-layanan',
        'category' => 'Jasa & Layanan',
        'excerpt' => 'Pelajari cara menjelaskan layanan, benefit, proses kerja, dan CTA agar calon pelanggan lebih yakin.',
        'image' => asset('images/default-article.jpg'),
        'author' => SITE_NAME,
        'published_at' => '2026-01-02 08:00:00',
        'updated_at' => '2026-01-02 08:00:00',
        'reading_time' => '4 Menit',
        'featured' => true,
        'keywords' => ['deskripsi layanan', 'jasa umkm', 'copywriting jasa'],
        'content' => '<p>Bisnis jasa perlu menjelaskan hasil, proses, durasi, dan cara konsultasi dengan bahasa yang sederhana.</p><h2>Mulai dari Masalah Pelanggan</h2><p>Jelaskan kebutuhan pelanggan terlebih dahulu, lalu tawarkan solusi yang relevan.</p>',
        'faq' => [],
    ],
    [
        'id' => 3,
        'title' => 'Checklist Halaman Checkout untuk Toko Online UMKM',
        'slug' => 'checklist-halaman-checkout-umkm',
        'category' => 'Checkout & Pembayaran',
        'excerpt' => 'Hal penting yang perlu ada di halaman checkout agar transaksi lebih jelas dan minim pertanyaan berulang.',
        'image' => asset('images/default-article.jpg'),
        'author' => SITE_NAME,
        'published_at' => '2026-01-03 08:00:00',
        'updated_at' => '2026-01-03 08:00:00',
        'reading_time' => '5 Menit',
        'featured' => true,
        'keywords' => ['checkout umkm', 'pembayaran manual', 'order online'],
        'content' => '<p>Checkout yang jelas harus memuat ringkasan pesanan, data pelanggan, metode pembayaran, dan instruksi lanjutan.</p><h2>Buat Instruksi Pembayaran Ringkas</h2><p>Gunakan bahasa sederhana agar pelanggan tahu langkah berikutnya setelah order.</p>',
        'faq' => [],
    ],
    [
        'id' => 4,
        'title' => 'Ide Konten Blog untuk Meningkatkan Kepercayaan Pelanggan',
        'slug' => 'ide-konten-blog-untuk-kepercayaan-pelanggan',
        'category' => 'Marketing & SEO',
        'excerpt' => 'Contoh ide konten edukasi, studi kasus, FAQ, dan panduan yang cocok untuk website UMKM.',
        'image' => asset('images/default-article.jpg'),
        'author' => SITE_NAME,
        'published_at' => '2026-01-04 08:00:00',
        'updated_at' => '2026-01-04 08:00:00',
        'reading_time' => '6 Menit',
        'featured' => false,
        'keywords' => ['blog umkm', 'artikel seo', 'konten bisnis'],
        'content' => '<p>Blog bisa membantu menjawab pertanyaan calon pelanggan sekaligus memperkuat SEO website.</p><h2>Mulai dari Pertanyaan Pelanggan</h2><p>Kumpulkan pertanyaan yang sering masuk lewat WhatsApp, lalu ubah menjadi artikel singkat.</p>',
        'faq' => [],
    ],
];
