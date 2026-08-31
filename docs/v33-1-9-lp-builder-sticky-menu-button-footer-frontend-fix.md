# V33.1.9 — LP Builder Sticky Menu, Button Color & Mini Footer Frontend Fix

Patch lanjutan dari V33.1.8 berdasarkan laporan uji frontend.

## Fix utama

1. Custom Menu header
   - Daftar menu kini punya wrapper sendiri (`lp-custom-menu__links`) agar alignment kiri/tengah/kanan tidak dipengaruhi posisi logo.
   - Logo tetap bisa diposisikan kiri/tengah/kanan lewat `lp-custom-menu__logo-slot`.
   - Sticky diperkuat di CSS dan ditambah fallback JS ringan yang mengubah sticky menjadi fixed hanya ketika menu sudah mencapai posisi atas.

2. Tombol/CTA
   - Warna background tombol dan warna teks tombol dari builder dipaksa berlaku di public frontend dengan CSS override lebih kuat.

3. Mini footer
   - Background, warna teks, warna brand, ukuran teks, dan alignment mini footer kini dibuat sebagai inline CSS variable + direct style agar tidak kalah dari default tema.

## Guardrail

Patch ini tidak membongkar SEO, analytics, tracking CTA/form, A/B testing, WordPress Migration Toolkit, Migration Command Center, dynamic content, public renderer, revision/draft/publish, atau sanitizer HTML.
