# Product Detail Dynamic Merge v4

Versi ini menggabungkan kembali logic dynamic SEO product detail dari web qurban ke U-Growth tanpa hardcode niche.

## Tujuan

- Produk detail U-Growth tetap universal untuk produk fisik, jasa/layanan, produk digital, e-course, e-book, menu kuliner, booking/reservasi, paket, dan custom order.
- Dynamic content tidak random dan tetap mengikuti Relevance Guard v3/v4.
- Logic kaya dari product detail hewan qurban dibawa ke U-Growth sebagai renderer universal.
- Schema dinamis disiapkan sebelum `layout/head.php`, sehingga FAQ, HowTo, WebPage, dan Navigation schema bisa ikut keluar di `<head>`.

## Area yang disentuh

- `pages/product-detail.php`
- `components/dynamic-product-detail.php`
- `core/dynamic-content.php`
- `pages/admin-dynamic-content-guard.php`
- `core/release-audit.php`

## Fitur yang aktif di product detail

- Ringkasan kontekstual produk/layanan.
- Info cepat berdasarkan tipe item, kategori, subkategori, lokasi/kanal, tier, dan spesifikasi.
- Trust item universal.
- Panduan pengambilan keputusan:
  - produk fisik: memilih & memesan,
  - layanan: konsultasi & scope,
  - digital/course/ebook: akses & penggunaan,
  - booking: jadwal/reservasi,
  - custom order: request kebutuhan.
- Internal link panel yang tetap relevan.
- Artikel terkait produk.
- Rekomendasi lokasi/kanal yang sama.
- Rekomendasi semantik dengan threshold lebih ketat.
- FAQ dinamis universal.
- CTA checkout/inquiry tetap memakai sistem U-Growth.
- Member/content restriction dan commerce policy tetap dipertahankan.

## Guard relevansi

Product detail memakai threshold lebih ketat daripada homepage. Jika tipe item berbeda dan kategorinya tidak cocok, skor relevansi diberi penalti agar rekomendasi lintas niche tidak muncul hanya karena kata umum seperti online, checkout, atau contoh.

Homepage tetap boleh lebih luas, tetapi detail artikel/produk/landing page harus presisi.

## Catatan implementasi

Nama legacy seperti `dynamic_infer_animal_type()` dan key `animal_type` tetap dipertahankan demi kompatibilitas data lama, tetapi isi labelnya sudah universal dan tidak lagi qurban-only.
