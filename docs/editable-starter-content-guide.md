# Editable Starter Content Guide

Panduan ini menjelaskan cara kerja konten awal yang tampil di website publik.

## Prinsip utama

Semua konten yang tampil di frontend harus bisa dikontrol dari dashboard admin.

Area yang termasuk konten editable:

- Produk / jasa / katalog: **Admin → Produk**
- Artikel SEO: **Admin → Artikel**
- Beranda: **Admin → Beranda**
- Halaman bawaan seperti Tentang Kami, Kontak, Privacy Policy, Terms: **Admin → Konten Template**
- Menu, header, footer: **Admin → Menu & Navigasi**
- Brand, warna, logo, favicon: **Admin → Brand & Warna**
- Landing page: **Admin → Landing Pages**

## Produk dan artikel awal

Produk dan artikel contoh disiapkan sebagai starter content yang tersimpan di runtime admin. Artinya admin bisa:

- mengedit judul, slug, harga, kategori, gambar, dan SEO;
- menyembunyikan konten dengan status;
- menghapus konten;
- mengganti semua konten dengan data bisnis sendiri.

File `data/products.php` dan `data/articles.php` tetap ada sebagai fallback aman, tetapi frontend tidak lagi bergantung permanen ke seed read-only setelah konten runtime aktif.

## Kalau ingin mulai dari nol

Masuk ke **Admin → Website Starter Wizard**, pilih **Bangun dari Nol**, lalu kosongkan atau hapus produk/artikel contoh dari menu Produk dan Artikel sesuai kebutuhan.

## Saat migrasi ke MySQL

Setelah data produk/artikel sudah rapi di JSON runtime, gunakan **Admin → Migrasi Data MySQL** untuk memindahkan data ke tabel runtime. Aktifkan collection di **Admin → Storage & Database** secara bertahap.
