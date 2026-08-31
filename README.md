# U-Growth Web Template

U-Growth Web Template adalah template website UMKM berbasis PHP yang dirancang untuk membantu bisnis membuat website profil, katalog produk/jasa, artikel SEO, landing page, checkout, form prospek, tracking marketing, dan laporan pertumbuhan bisnis.

## Fitur Utama

- Pengaturan brand, logo, warna, header, menu, footer, dan homepage.
- Katalog produk, jasa, layanan, produk digital, portfolio, dan artikel SEO.
- Landing Page Builder dengan blok, smart preset, template gallery, countdown, publish guard, performance guard, dan rekomendasi improvement.
- Form Builder untuk kontak, lead magnet, konsultasi, booking, survey, dan checkout sederhana.
- Order, invoice, bukti pembayaran, payment gateway bridge, ongkir manual/API, inventory, fulfillment, digital delivery, member area, dan license/subscription mode.
- Marketing & Analytics Center untuk setup tracking, pixel, landing page analytics, CTA result, optimization, profit attribution, dan action plan.
- Sistem admin dengan first-run installer, role, permission, activity log, keamanan, SMTP, backup/restore, maintenance, readiness check, migrasi MySQL bertahap, dan Migrasi WordPress Foundation.

## Instalasi Cepat

1. Upload semua file ke hosting, VPS, atau localhost.
2. Buka `/install` untuk menjalankan **Installer & First Run Setup**.
3. Pastikan checklist server aman, terutama folder `storage`, `logs`, `cache`, `assets/uploads`, dan file `.env` bisa ditulis.
4. Isi URL website, nama bisnis, email, WhatsApp, preset warna, lalu buat akun owner.
5. Setelah setup tersimpan, buka `/admin/login` untuk masuk dashboard.
6. Mulai dari menu **Pengaturan Web** untuk mengatur identitas bisnis.
7. Lanjutkan ke katalog, artikel, landing page, form, checkout, dan analytics sesuai kebutuhan bisnis. Untuk pengguna yang pindah dari WordPress, buka **Konten & SEO → Migrasi WordPress** untuk preview file XML/WXR atau CSV sebelum import.
8. Opsional manual: copy `.env.example` menjadi `.env`, isi `APP_URL`, lalu buat password owner kuat jika tidak memakai installer.
9. Opsional: jika ingin memakai MySQL runtime, import `database.sql` dan `database/mysql-storage-schema.sql`, lalu migrasikan dan aktifkan collection dari menu **Sistem → Migrasi Data MySQL** dan **Sistem → Storage & Database**.

## Catatan Produksi

- Ganti semua konten contoh sebelum website dipublikasikan.
- Pastikan folder `storage`, `logs`, `cache`, dan `assets/uploads` writable.
- Pastikan `.env`, `storage`, `logs`, `cache`, `core`, `pages`, `components`, `data`, dan `docs` tidak bisa diakses langsung dari browser.
- Untuk server Nginx, terapkan aturan hardening yang setara dengan `.htaccess` karena Nginx tidak membaca `.htaccess`.
- Jalankan cek sistem dan kesiapan rilis dari dashboard sebelum launching.

## Prinsip Template

Template ini dibuat agar dapat dipakai untuk banyak jenis bisnis UMKM. Semua konten contoh, label, warna, menu, halaman, landing page, form, dan pengaturan marketing sebaiknya disesuaikan dengan brand dan kebutuhan masing-masing bisnis.


## V32.96 — Shortcode & Gutenberg Cleaner

Menambahkan menu **Konten & SEO → Shortcode & Gutenberg Cleaner** untuk scan dan membersihkan sisa shortcode/plugin WordPress, Gutenberg block comment, builder residue, empty paragraph, serta tag berisiko dari konten hasil migrasi. Cleaner memakai preview/dry-run, backup storage otomatis, export laporan, dan apply manual agar konten lama bisa dibersihkan tanpa merusak data yang sudah berjalan.

## V32.95 — WordPress Media Migration

Menambahkan menu **Konten & SEO → WordPress Media Migration** untuk scan gambar remote dari WordPress lama, terutama `wp-content/uploads`, download batch ke `assets/uploads/wp-migration`, simpan media map remote → lokal, dan rewrite URL gambar secara aman setelah backup storage. Fitur ini melanjutkan V32.94 Breadcrumb & Internal Link Migration tanpa menghapus fitur SEO Preservation, Dynamic Content Guard, atau Migrasi WordPress.

Flow aman: scan → dry-run download → download batch → dry-run rewrite → apply rewrite dengan backup.

## V32.92.1 — Universal Dynamic Content & Import Compatibility Consolidation

Update ini menggabungkan fondasi migrasi WordPress V32.92 dengan pembelajaran dari integrasi website existing: slug import lama dibuat lebih aman, dynamic content dibuat lebih relevan lintas niche, dan menu Dynamic Content Guard tersedia di Konten & SEO untuk audit rekomendasi artikel/produk/landing page.

Catatan: data dan setting live niche tertentu tidak dibawa ke template universal. Yang dikonsolidasikan adalah logic, guard, route, dan kompatibilitas import.



## V32.93 — SEO Preservation Layer

Tambahan utama:

- Admin **Konten & SEO → SEO Preservation & Redirect**.
- Legacy URL resolver sebelum 404 untuk URL WordPress lama seperti `/judul-artikel-lama`.
- Redirect map internal 301/302/307/308 berbasis `storage/seo-preservation/redirects.json`.
- Canonical helper untuk artikel, produk, dan landing page.
- Sitemap mengikuti canonical URL jika masih satu domain.
- Auto-scan legacy URL dari konten import, tetapi kandidat redirect dibuat inactive agar aman direview dulu.

Gunakan V32.93 setelah V32.92.1 sebagai fondasi menuju migrasi WordPress yang lebih aman secara SEO.


## V32.94 — Breadcrumb & Internal Link Migration

Menambahkan admin Breadcrumb & Internal Link Migration untuk mapping breadcrumb WordPress, audit internal link lama, rekomendasi rewrite link, dan safe rewrite dengan backup storage. Fitur ini melanjutkan V32.93 SEO Preservation Layer tanpa mengubah fitur lama secara destruktif.

## V32.97 — Elementor Safe HTML Block Import

Versi ini menambahkan modul **Elementor Safe Import** untuk memindahkan halaman WordPress/Page Builder ke Landing Page Builder U-Growth sebagai HTML block aman. Modul ini mendeteksi Elementor, Gutenberg, WPBakery, Divi, shortcode builder, dan widget kompleks. Semua halaman hasil import dibuat sebagai draft agar dapat direview sebelum publish.


## V32.98 — Migration Command Center

Versi ini menambahkan Admin → Konten & SEO → Migration Command Center sebagai pusat komando migrasi WordPress ke U-Growth. Modul ini memantau Import WordPress, SEO Preservation, Breadcrumb/Internal Link, Media Migration, Shortcode/Gutenberg Cleaner, Elementor Safe Import, dan Dynamic Content Guard dalam satu dashboard dengan health score, checklist, dan export JSON/CSV. Semua aksi tetap dilakukan melalui modul masing-masing dengan preview, dry-run, backup, dan review manual.


## V33.0 — Final WordPress Migration & SEO Redirect Toolkit Audit

Versi ini melakukan final audit terhadap seluruh modul migrasi WordPress ke U-Growth dari V32.92 sampai V32.98. Audit mencakup PHP lint, route public/admin, sitemap, legacy alias, dynamic content, release audit, storage/security, dan tes simulasi import WordPress. V33.0 juga memoles laporan WordPress Media Migration agar gambar lokal tidak terlihat seperti file download yang hilang. Detail audit tersedia di `docs/v33-0-final-launch-audit.md`.

## V33.1 — LP Builder UX & Safety Merge

Versi ini memoles Landing Page Builder U-Growth dengan workspace selected-block yang lebih nyaman, status simpan di topbar, HTML Block, Full HTML Expert Mode yang disanitasi, dan proteksi embed JSON ke JavaScript. Engine utama U-Growth tetap dipertahankan: SEO, analytics, tracking, A/B testing, revision, publish guard, sitemap, Elementor Safe Import, dan modul migrasi WordPress tidak diganti. Detail tersedia di `docs/v33-1-lp-builder-ux-safety-merge.md`.



## V33.1.2 — LP Builder Button Alignment Fix

- Memperbaiki kontrol `Rata tombol` pada LP Builder agar kiri/tengah/kanan benar-benar terlihat di preview dan public render.
- Menambahkan mapping aman `left/right` ke `flex-start/flex-end` untuk CSS flex.
- Tetap menjaga SEO, analytics, tracking, A/B testing, migrasi WP, dan renderer publik U-Growth.

## V33.1.1 — LP Builder Per-Block Design & Typography Polish

Versi ini melanjutkan V33.1 dengan merge konservatif untuk Landing Page Builder. Fokusnya menambah kontrol desain dan typography per bagian tanpa mengubah engine growth U-Growth: SEO, analytics, tracking, A/B testing, revisi, publish guard, public renderer, dan modul migrasi WordPress tetap dipertahankan.

Tambahan utama:
- kontrol desain section, judul, deskripsi/teks, tombol, dan card/item per block;
- item/card repeater sekarang punya panel desain masing-masing;
- public renderer membaca CSS variables yang sudah disanitasi;
- preview builder mengikuti sebagian besar styling public render;
- guard sanitasi tetap aktif untuk HTML Block dan Full HTML Expert Mode.


### V33.1.3 — LP Builder Card/Item Repeater UX Polish

V33.1.3 memperhalus pengalaman mengedit card/item di Landing Page Builder tanpa mengubah engine SEO, analytics, tracking, A/B testing, public renderer, dan modul migrasi WordPress. Repeater card/item sekarang lebih ringkas dengan accordion per item, tombol buka/tutup semua, duplikat item, ringkasan item di header, serta kontrol desain & typography item tetap tersedia.


## V33.1.5 — LP Builder Public Labels & Empty Title Fix

Patch aman setelah V33.1.3 untuk memperbaiki klik blok dari area preview, menyembunyikan label internal funnel goal seperti awareness/trust/lead dari frontend public, dan memperkuat renderer public agar item/card lama berbentuk string tidak memicu fatal error. Tetap mempertahankan SEO, analytics, tracking, A/B testing, Migration Command Center, dan fitur WordPress migration.


## V33.1.5 — LP Builder Public Labels & Empty Title Fix

- Public landing page tidak lagi menampilkan nama internal block secara otomatis.
- Judul section kosong tidak lagi diganti fallback/default seperti Benefit, FAQ, Form Lead, dan sejenisnya.
- Preview builder mengikuti public render agar admin melihat hasil lebih akurat.
- Tracking, analytics, A/B testing, SEO, sitemap, dan modul migrasi WP tetap dipertahankan.



## V33.1.6 - LP Builder Section Design, Mini Footer & Custom Menu Header Polish

- Section background/text design now applies consistently in builder preview and public `/lp/{slug}` render.
- Mini footer/no-distraction footer text can be edited per landing page from Pengaturan.
- Builder success toast is hidden because save status already lives in the topbar.
- Workspace helper text is simplified.
- Custom Menu block can render as top header menu with normal, sticky, or fixed behavior, while card-link mode remains available for SEO/internal-link use.
- Block move actions were hardened so every block type can be reordered safely without losing U-Growth SEO, analytics, tracking, A/B testing, or migration modules.


## V33.1.7 - LP Builder Custom Menu Header & Sidebar Preset Polish

- Custom Menu baru otomatis ditempatkan di bagian paling atas sebagai header menu.
- Preview dan public renderer mendukung posisi normal/sticky/fixed dengan lebih kuat.
- Logo menu dari path lokal seperti `assets/...` sekarang tetap tersimpan dan tampil di frontend.
- Helper sidebar dibuat lebih kecil dan menjelaskan Preset System.
- Preset System default tertutup agar sidebar lebih ringkas.


## V33.1.9 - LP Builder Sticky Menu, Button Color & Mini Footer Frontend Fix

Patch dari V33.1.8 untuk memperbaiki hasil uji frontend:
- Rata daftar menu custom header sekarang independen dari posisi logo, sehingga menu kiri/tengah/kanan tidak lagi terdorong ke kanan oleh logo.
- Sticky header menu diperkuat dengan CSS dan fallback JavaScript ringan saat parent layout/browser mengganggu `position: sticky`.
- Warna tombol dan warna teks tombol dari builder dipaksa ikut di frontend public.
- Background mini footer/no-distraction footer dipaksa ikut di frontend public dan tidak kembali ke biru default tema.

## V33.1.8 - LP Builder Menu Logo, Sticky Header, Preview Button & Mini Footer Polish

- Memperbaiki live preview rata kiri/tengah/kanan untuk block Tombol/CTA dan tombol lain yang memakai kontrol button alignment.
- Memperkuat Custom Menu mode header sticky/fixed pada builder preview dan public frontend.
- Menambahkan item menu khusus logo saja dengan dukungan URL logo, alt logo, URL/anchor, dan posisi logo.
- Menambahkan pengaturan rata menu header dan posisi logo menu.
- Menambahkan desain mini footer: background, warna teks, warna brand, ukuran teks, dan rata footer.
- Tetap menjaga SEO, analytics, tracking, A/B testing, WordPress Migration Toolkit, dynamic content, dan renderer publik.


## V33.1.10 - Mini Footer Custom Color & Sidebar Gallery Cleanup

Patch ini memperkuat mini footer focus mode agar background footer dan warna brand footer dari builder tidak lagi dioverride warna tema bawaan. Card Template Gallery di sidebar kiri workspace LP Builder juga dihapus karena akses galeri sudah tersedia dari dropdown Publish/topbar dan halaman daftar landing page.

## V33.1.11 - Final LP Builder Audit & Regression Hardening

Versi ini melakukan audit dan hardening setelah rangkaian update LP Builder V33.1.0 sampai V33.1.10. Release Audit sekarang memiliki grup khusus `LP Builder Regression` untuk mengecek marker builder/public, registry block, preview/public style contract, tombol, custom menu logo/sticky, mini footer custom color, HTML safe mode, draft/publish/revision guard, SEO/analytics/A-B bridge, kebersihan sidebar, dan CSS conflict guard. Tidak ada fitur besar baru yang ditambahkan; fokusnya menjaga semua update LP Builder tidak mengganggu modul U-Growth lain.


## V33.1.12 — Admin Migration & Homepage Regression Fix

Patch regresi setelah audit LP Builder: memperbaiki style filter admin migrasi WordPress, alias compatibility `require_admin_auth()` untuk modul cleaner/import lama, guard overflow kartu katalog homepage, dan dynamic content area layanan agar mengikuti warna brand/theme aktif.

## V33.1.13 — Migration Admin Form Controls Polish

Patch regresi UI admin setelah audit V33.1.12. Fokusnya membuat semua field input/select/textarea/checkbox di modul migrasi WordPress tampil konsisten dengan desain dashboard, terutama pada halaman SEO Preservation & Redirect dan Shortcode/Gutenberg Cleaner.

- Memperluas styling `admin-filter-form` ke modul WP Content Cleaner dan Elementor Import.
- Memperkuat styling input/select/textarea pada halaman migrasi agar tidak tampil bawaan browser.
- Menjaga modul LP Builder, SEO, analytics, tracking, redirect, dan migration toolkit tetap additive tanpa menghapus baseline.

## V33.1.14 — Article Detail Hero Meta Layout Fix

Patch polish public artikel detail setelah regression audit. Metadata artikel dan excerpt/deskripsi tidak lagi tampil di dalam hero biru, tetapi dipindahkan ke card pembuka di bawah hero. Hero artikel menjadi lebih clean: breadcrumb + judul saja.

File utama yang berubah:
- `pages/artikel-detail.php`
- `assets/css/app.css`
- `docs/v33-1-14-article-detail-hero-meta-layout-fix.md`

Guardrail tetap dipertahankan: SEO, canonical, breadcrumb schema, FAQ schema, dynamic article summary, internal link topic, content restriction, dan dynamic article detail tidak dibongkar.


## V33.1.15 — Final Stabilization & Public Launch Audit

Audit stabilisasi setelah rangkaian perbaikan LP Builder dan regresi admin/public. Paket rilis dibersihkan dari artefak runtime lokal, wording admin dipoles agar lebih siap dipakai pengguna umum, marker LP Builder/public renderer disinkronkan, dan Release Audit kembali divalidasi sebagai checkpoint stabil sebelum masuk fase preset/template produksi berikutnya.

Detail: `docs/v33-1-15-final-stabilization-public-launch-audit.md`

## V33.1.16 — Jodit Upload, Schema Guard & Dynamic Term Content Patch

Update ini menambahkan upload gambar langsung dari toolbar Jodit untuk artikel dan deskripsi produk/jasa, pilihan schema produk yang lebih aman untuk produk harga berubah-ubah, serta dynamic term page server-side berbasis kategori, tag, keyword/topik, slug, dan konten existing.

Detail: `docs/v33.1.16-jodit-schema-dynamic-content-patch.md`
# GoDigitalMarket
