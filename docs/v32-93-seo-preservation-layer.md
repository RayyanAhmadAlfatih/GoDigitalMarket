# V32.93 — SEO Preservation Layer

Versi ini menambahkan lapisan SEO preservation untuk migrasi WordPress ke U-Growth tanpa merusak fitur lama.

## Fokus

- Legacy URL resolver sebelum 404.
- Redirect 301/302/307/308 internal berbasis JSON.
- Old URL map dari `legacy_url`, `original_url`, `old_url`, `redirect_from`, `wp_original_url`, dan `wp_guid`.
- Canonical helper untuk artikel, produk, dan landing page.
- Sitemap mengikuti canonical URL jika masih satu domain.
- Admin page: **Konten & SEO → SEO Preservation & Redirect**.

## Strategi URL

### Preserve URL lama

Gunakan bila URL lama WordPress sudah punya ranking/backlink kuat.

Contoh:

- URL lama: `/strategi-list-building-yang-efektif`
- Artikel tetap dikelola di U-Growth.
- Canonical: `/strategi-list-building-yang-efektif`
- Resolver U-Growth melayani URL itu sebelum 404.

### Redirect 301

Gunakan bila URL lama mau dipindah ke struktur U-Growth.

Contoh:

- Source: `/strategi-list-building-yang-efektif`
- Target: `/artikel/strategi-list-building-yang-efektif`
- Code: `301`

## Penyimpanan

Redirect map disimpan di:

`storage/seo-preservation/redirects.json`

Folder tersebut dibuat privat dengan `.htaccess`.

## Guardrail

- Redirect root `/` tidak diizinkan.
- Target tidak boleh sama dengan source.
- Auto-scan legacy URL membuat kandidat redirect dalam status `inactive`, jadi owner/admin harus review dulu sebelum redirect aktif.
- Existing route U-Growth tetap diprioritaskan. Resolver legacy hanya jalan sebelum 404.

## Integrasi Migrasi WordPress

V32.92 sudah membawa `legacy_url`, `canonical_url`, `original_url`, dan metadata migrasi. V32.93 membuat field ini benar-benar berdampak ke:

- public route,
- canonical,
- sitemap,
- redirect manager,
- audit admin.
