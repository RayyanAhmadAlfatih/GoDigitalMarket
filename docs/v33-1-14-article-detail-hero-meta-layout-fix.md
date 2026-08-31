# V33.1.14 — Article Detail Hero Meta Layout Fix

## Tujuan
Memindahkan metadata artikel dan ringkasan/excerpt dari area hero biru ke card pembuka di bawah hero agar tampilan artikel detail lebih rapi dan tidak terlalu padat di hero.

## Perubahan
- `pages/artikel-detail.php`
  - Hero artikel sekarang hanya menampilkan breadcrumb dan judul artikel.
  - Metadata artikel: kategori, tanggal publish, tanggal update, estimasi baca, dan author dipindahkan ke section khusus di bawah hero.
  - Excerpt/deskripsi artikel juga dipindahkan ke section bawah hero.
- `assets/css/app.css`
  - Menambahkan style `article-hero-meta-section` dan `article-hero-meta-card`.
  - Layout responsive untuk desktop dan mobile.
  - Tetap memakai warna brand/theme, bukan hardcoded niche lama.

## Guardrail
- SEO, canonical, breadcrumb schema, FAQ schema, dynamic summary, dynamic topic links, content restriction, dan dynamic article detail tidak dibongkar.
- Struktur artikel utama tetap aman.
