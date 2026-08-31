# V32.92.1 — Universal Dynamic Content & Import Compatibility Consolidation

Versi ini mengkonsolidasikan pembelajaran dari integrasi website existing ke U-Growth, terutama kasus data lama yang memiliki format slug berbeda dan kebutuhan dynamic content yang harus relevan lintas niche.

## Fokus utama

1. Dynamic content tetap universal, bukan khusus satu niche.
2. Detail produk, artikel, homepage, dan landing dinamis memakai konteks kategori, tag, keyword, slug, title, content, tipe item, dan lokasi bila ada.
3. Detail page memakai relevance guard agar rekomendasi tidak random.
4. Resolver produk lebih aman terhadap data import lama, termasuk slug yang memiliki perbedaan huruf besar/kecil.
5. WordPress Migration Foundation V32.92 tetap dipertahankan sebagai fondasi migrasi berikutnya.

## Kompatibilitas import

- Slug produk dapat ditemukan melalui slug asli maupun slug hasil normalisasi.
- Data lama yang memiliki kode suffix uppercase tetap dapat diakses lewat URL lowercase.
- Logic ini penting untuk migrasi dari website existing, CSV, JSON lama, maupun WordPress export yang datanya tidak selalu konsisten.

## Dynamic Content Guard

Menu baru/aktif:

`Admin → Konten & SEO → Dynamic Content Guard`

Menu ini membantu audit:

- Guard Score
- item kuat / aman / lemah
- token konteks
- rekomendasi apakah data perlu dilengkapi kategori, tag, atau keyword

## Batasan versi ini

V32.92.1 belum mengaktifkan legacy URL resolver dan redirect 301 penuh. Fitur tersebut masuk fase berikutnya:

`V32.93 — SEO Preservation Layer`

