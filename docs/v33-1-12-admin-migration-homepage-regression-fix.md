# V33.1.12 — Admin Migration & Homepage Regression Fix

Versi ini melanjutkan audit V33.1.11 dan memperbaiki temuan regresi dari pengujian manual setelah update LP Builder.

## Fokus perbaikan

1. **UI filter modul migrasi WordPress**
   - Filter status dan cari di WordPress Media Migration, SEO Preservation & Redirect, dan Breadcrumb/Internal Link Migration kini memakai style dashboard U-Growth, bukan tampilan bawaan browser.

2. **Compatibility admin auth lama**
   - Menambahkan alias aman `require_admin_auth()` ke `admin_auth_require()` agar modul migrasi lama seperti Shortcode & Gutenberg Cleaner dan Elementor Safe Import tidak fatal error.

3. **Homepage katalog overflow guard**
   - Grid kartu katalog homepage diperkuat dengan `minmax(0, 1fr)`, `min-width:0`, dan responsive guard agar card tidak melewati lebar layar laptop.

4. **Dynamic content theme guard**
   - Warna dynamic content area layanan tidak lagi memakai warna hijau bawaan niche lama. Background dan CTA mengikuti variabel brand/theme aktif.

## Area yang dijaga

- LP Builder V33.1.11 tetap dipertahankan.
- SEO, analytics, tracking, A/B testing, WordPress Migration Toolkit, Migration Command Center, dynamic content, dan public renderer tidak dibongkar.
- Perubahan bersifat additive/compatibility polish.
