# V33.1 — LP Builder UX & Safety Merge

Versi ini menggabungkan kelebihan UX Landing Page Builder referensi ke dalam arsitektur U-Growth tanpa mengganti engine utama U-Growth.

## Prinsip merge

- U-Growth tetap menjadi sumber utama untuk SEO, analytics, tracking, A/B testing, revision, publish guard, sitemap, dan migrasi WordPress.
- Perubahan difokuskan pada workspace builder, pengalaman edit, preview, desain per-block, HTML block, dan Full HTML Expert Mode.
- Perubahan dilakukan secara additive agar logic landing page lama tetap kompatibel.

## Peningkatan utama

1. Workspace builder memakai mode selected block: sidebar menampilkan navigator blok dan editor blok aktif sehingga admin tidak perlu scroll terlalu dalam.
2. Status simpan/autosave dipindahkan ke topbar agar tidak mengganggu area preview.
3. Preview mendukung marker `data-lp-builder-version="v33.1"` dan renderer publik `data-lp-renderer="v33.1"`.
4. HTML Block ditambahkan ke daftar blok agar admin bisa memasukkan section HTML aman.
5. Full HTML Expert Mode ditambahkan untuk import landing page HTML penuh dengan sanitizer.
6. Embed JSON ke JavaScript memakai flag `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, dan `JSON_HEX_QUOT` untuk mengurangi risiko script break/XSS dari konten HTML.
7. Public renderer tetap memakai engine U-Growth dan hanya memakai Full HTML Mode bila diaktifkan dan konten tersedia.

## Guard keamanan HTML

Full HTML Expert Mode dan HTML block membersihkan:

- tag `<script>`
- tag `<iframe>`
- tag `<object>`
- tag `<embed>`
- inline event handler seperti `onclick`
- URL `javascript:`

Jika modul Elementor Safe Import tersedia, sanitizer U-Growth yang sama akan dipakai agar perilakunya konsisten.

## Yang tetap dipertahankan

- SEO landing page
- Analytics/tracking CTA dan form
- A/B testing CTA/Form
- Revision history
- Draft/publish
- Publish guard
- Landing page public route `/lp/{slug}`
- Sitemap/indexable integration
- Elementor Safe Import `html_block`
- Migration Command Center dan modul WordPress migration

## Catatan penggunaan

Full HTML Expert Mode cocok untuk admin teknis yang ingin memindahkan landing page HTML penuh. Untuk admin non-teknis, mode block biasa tetap direkomendasikan karena lebih aman, mudah diedit, dan lebih konsisten dengan tracking/growth system U-Growth.
