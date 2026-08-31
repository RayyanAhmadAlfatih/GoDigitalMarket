# V32.94 — Breadcrumb & Internal Link Migration

Versi ini menambahkan lapisan migrasi struktur SEO untuk WordPress ke U-Growth.

## Fokus

- Breadcrumb mapper universal untuk artikel, produk/layanan, dan landing page.
- Support `breadcrumb_path` dari import WordPress bila tersedia.
- Fallback breadcrumb dari kategori/subkategori jika custom path kosong.
- Internal link checker untuk konten artikel, produk/layanan, dan blok landing page.
- Deteksi link lama yang cocok dengan SEO Preservation/legacy URL.
- Safe rewrite manual dengan backup storage sebelum apply.

## Prinsip Aman

Rewrite link tidak berjalan otomatis. Admin harus melakukan dry-run lalu apply secara sadar. Unknown internal link tidak diubah otomatis.
