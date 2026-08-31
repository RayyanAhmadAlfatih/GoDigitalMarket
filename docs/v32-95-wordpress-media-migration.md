# V32.95 — WordPress Media Migration

Fitur ini menambahkan lapisan migrasi media WordPress ke U-Growth secara aman. Fokusnya adalah gambar lama dari `wp-content/uploads` yang muncul di artikel, produk/layanan, gallery, dan landing page.

## Prinsip Aman

1. Scan dulu semua URL gambar.
2. Download gambar WordPress secara batch agar aman untuk shared hosting.
3. Simpan origin map remote URL → local asset.
4. Rewrite URL hanya jika file lokal sudah ada.
5. Backup `storage/articles.json`, `storage/products.json`, dan `storage/landing-pages.json` sebelum rewrite.

## Menu Admin

Buka: **Konten & SEO → WordPress Media Migration**.

Menu ini menyediakan:

- media migration score,
- total gambar WordPress remote,
- gambar yang sudah didownload,
- gambar siap rewrite,
- export JSON/CSV,
- dry-run download,
- download batch,
- dry-run rewrite,
- apply rewrite dengan backup.

## Catatan

V32.95 belum memaksa semua gambar remote harus dipindah. Untuk migrasi website ranking, strategi aman adalah memindahkan gambar penting terlebih dahulu, lalu rewrite setelah dicek. Fase berikutnya tetap bisa melanjutkan Shortcode/Gutenberg cleaner dan Elementor safe HTML import.
