# V32.98 — Migration Command Center

Versi ini menambahkan pusat komando migrasi WordPress ke U-Growth agar modul-modul migrasi tidak terasa terpencar.

## Menu Baru

Admin → Konten & SEO → Migration Command Center

## Modul yang dipantau

1. Import WordPress
2. SEO Preservation & Redirect
3. Breadcrumb & Internal Link
4. WordPress Media Migration
5. Shortcode & Gutenberg Cleaner
6. Elementor Safe Import
7. Dynamic Content Guard

## Prinsip Aman

- Command Center tidak menjalankan aksi destruktif otomatis.
- Semua perubahan tetap lewat modul masing-masing yang punya preview, dry-run, backup, dan review.
- Redirect 301 tidak diaktifkan otomatis tanpa review.
- Elementor/page builder tetap masuk sebagai draft HTML block aman.
- Dynamic content tetap dicek relevansinya agar tidak random.

## SOP Migrasi Singkat

1. Upload XML/WXR atau CSV dan buat preview.
2. Import artikel dan page setelah preview aman.
3. Review canonical, legacy URL, dan redirect 301.
4. Scan breadcrumb dan internal link.
5. Migrasi media WordPress.
6. Bersihkan shortcode dan Gutenberg residue.
7. Import halaman Elementor/page builder sebagai draft.
8. Validasi dynamic content guard.
9. Submit sitemap dan cek Google Search Console.

## Catatan Launch

V32.98 adalah pusat komando sebelum final audit besar. Setelah versi ini, pengembangan idealnya lanjut ke V33.0 Final WordPress Migration & SEO Redirect Toolkit dengan audit menyeluruh.
