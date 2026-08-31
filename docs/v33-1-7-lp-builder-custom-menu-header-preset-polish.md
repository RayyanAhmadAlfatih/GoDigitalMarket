# V33.1.7 — LP Builder Custom Menu Header & Sidebar Preset Polish

Update konservatif setelah V33.1.6 untuk memperbaiki workflow Custom Menu dan sidebar builder.

## Perubahan

- Custom Menu yang ditambahkan dari tombol + Menu Custom otomatis masuk ke urutan paling atas.
- Default Custom Menu baru menggunakan mode header dan posisi sticky.
- Preview builder kini merender Custom Menu header, bukan card link, ketika `menu_style=header`.
- Sticky/fixed menu diperkuat di preview dan frontend.
- Path gambar lokal seperti `assets/images/logo.webp` dan `assets/uploads/...` diterima oleh sanitizer URL sehingga logo menu tidak hilang saat disimpan.
- Helper sidebar dipadatkan dan menjelaskan penggunaan Preset System.
- Accordion Preset System default tertutup.

## Guardrail

Tidak ada perubahan destruktif pada SEO, analytics, tracking, A/B testing, WordPress Migration Toolkit, public renderer utama, atau data storage.
