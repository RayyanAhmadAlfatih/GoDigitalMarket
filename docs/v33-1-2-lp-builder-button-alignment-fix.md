# V33.1.2 — LP Builder Button Alignment Fix

Patch kecil dan aman setelah V33.1.1. Fokusnya memperbaiki kontrol **Rata tombol** di Landing Page Builder, terutama block hero.

## Perbaikan

- Opsi `left`, `center`, dan `right` sekarang dipetakan ke CSS flex yang valid: `flex-start`, `center`, dan `flex-end`.
- Preview builder menambahkan wrapper `.lpw-pv-actions` untuk tombol hero agar alignment terlihat langsung di canvas.
- Public renderer menambahkan `--lp-button-justify` dan `data-lp-button-align` agar halaman live `/lp/{slug}` konsisten dengan preview.
- Marker builder/render dinaikkan ke `v33.1.2`.

## Guard

Patch ini tidak mengubah logic SEO, analytics, A/B testing, tracking, WordPress migration, dynamic content, atau public route utama U-Growth.
