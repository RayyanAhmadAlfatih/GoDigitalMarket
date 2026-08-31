# Dynamic Content Relevance Guard v3

Versi ini mengaktifkan guard relevansi untuk dynamic content U-Growth agar konten yang tampil di homepage, detail artikel, detail produk, dan dynamic landing page tidak random.

## Tujuan

1. Mengurangi thin content tanpa membuat halaman terasa spam.
2. Membuat halaman tetap terlihat hidup walaupun admin belum update manual.
3. Menjaga rekomendasi tetap sesuai niche bisnis, kategori, tag, keyword, slug, judul, isi konten, tipe produk/jasa, lokasi, dan focus keyword.
4. Menyiapkan fondasi aman sebelum migrasi WordPress besar, karena artikel WP lama bisa sangat banyak.

## Prinsip Guard

- Homepage boleh menampilkan konten lebih luas, tetapi tetap mengikuti mode/niche bisnis utama.
- Detail artikel, detail produk, dan landing page memakai threshold relevansi lebih ketat.
- Jika skor relevansi terlalu rendah, detail page tidak memaksa fallback random.
- Rekomendasi memiliki metadata `_dynamic_relevance` berisi score, label, reasons, dan tokens.
- Card artikel/produk bisa menampilkan badge relevansi agar admin mudah melihat alasan konten muncul.

## File yang ditambahkan/diubah

- `core/dynamic-content.php`
- `components/article-card.php`
- `components/product-card.php`
- `components/dynamic-homepage.php`
- `pages/admin-dynamic-content-guard.php`
- `core/admin-menu.php`
- `core/admin-help.php`
- `core/release-audit.php`
- `index.php`
- `assets/css/app.css`

## Admin Menu Baru

`Admin → Konten & SEO → Dynamic Content Guard`

Menu ini menampilkan:

- Guard Score
- jumlah artikel dan produk yang dicek
- status kuat/aman/perlu data
- token context count
- skor relevansi terbaik
- export CSV/JSON

## Cara Kerja Scoring

Score dihitung dari:

- kategori yang sama
- subkategori/tipe yang sama
- lokasi yang sama
- tag/keyword/focus keyword
- slug
- judul
- excerpt/deskripsi/konten
- business mode dan label niche

## Catatan

Engine ini tetap rule-based ringan agar aman untuk shared hosting. Tidak memakai API AI, embedding, atau server eksternal.
