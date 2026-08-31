# V33.1.13 — Migration Admin Form Controls Polish

## Fokus patch

Versi ini melanjutkan audit regresi V33.1.12 berdasarkan temuan UI di halaman admin migrasi.

## Perbaikan

1. Field form pada `admin/seo-preservation` diperkuat agar `input`, `select`, dan `textarea` pada form tambah redirect tidak tampil bawaan browser.
2. Field filter pada `admin/wp-content-cleaner` sekarang ikut style dashboard.
3. Styling form admin migration diperluas secara aman ke halaman:
   - `admin/wp-media-migration`
   - `admin/seo-preservation`
   - `admin/internal-link-migration`
   - `admin/wp-content-cleaner`
   - `admin/wp-elementor-import`
   - `admin/wp-migration`
4. Checkbox helper pada form cleaner dan migration utility diberi style yang konsisten.

## Guardrail

- Tidak ada perubahan destructive pada data, route, SEO, LP Builder, analytics, tracking, atau migration core.
- Patch hanya memperkuat CSS admin untuk kelompok halaman migrasi.
