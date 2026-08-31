# V32.96 — Shortcode & Gutenberg Cleaner

Versi ini menambahkan lapisan pembersihan konten WordPress setelah import dan migrasi media.

## Tujuan

Saat artikel/halaman WordPress dipindahkan ke U-Growth, konten lama sering membawa:

- Gutenberg block comment seperti `<!-- wp:paragraph -->`.
- Shortcode plugin seperti `[contact-form-7]`, `[rank_math_toc]`, `[elementor-template]`, `[vc_row]`, `[caption]`, dan lainnya.
- Builder residue dari Elementor, WPBakery, Divi, Fusion Builder, dan plugin sejenis.
- Empty paragraph/tag sisa editor lama.
- Script/style/iframe lama yang sebaiknya direview atau dibersihkan.

V32.96 membuat proses ini aman dengan scan, dry-run, backup, dan apply manual.

## Menu Admin

Buka:

`Admin → Konten & SEO → Shortcode & Gutenberg Cleaner`

Fitur:

- Scan artikel, produk/layanan, dan landing page.
- Hitung Gutenberg comments, shortcode, unknown shortcode, dan risky tags.
- Export laporan JSON/CSV.
- Dry-run cleaning tanpa mengubah storage.
- Apply cleaner dengan backup otomatis.

## Strategi Cleaner

- Gutenberg comment dihapus karena tidak dibutuhkan di frontend U-Growth.
- Shortcode plugin form/TOC/slider/widget lama dihapus karena biasanya tidak bisa berjalan tanpa plugin WordPress.
- Shortcode layout/builder di-unwrap supaya isi teks tetap bertahan.
- Unknown shortcode default-nya dipertahankan untuk review manual.
- Script/style/iframe dapat dihapus jika opsi `Hapus script/style/iframe lama` aktif.

## Backup

Saat apply, backup dibuat ke:

`storage/wp-content-cleaner/backups/`

File yang dibackup:

- `storage/articles.json`
- `storage/products.json`
- `storage/landing-pages.json`
- `storage/template-content.json`

## Posisi di Roadmap Migrasi WordPress

V32.96 melengkapi:

- V32.92 WordPress Migration Foundation
- V32.92.1 Universal Dynamic Content & Import Compatibility
- V32.93 SEO Preservation Layer
- V32.94 Breadcrumb & Internal Link Migration
- V32.95 WordPress Media Migration

Setelah ini roadmap lanjut ke V32.97 — Elementor Safe HTML Block Import.
