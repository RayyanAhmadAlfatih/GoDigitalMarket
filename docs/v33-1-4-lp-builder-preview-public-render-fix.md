# V33.1.4 — LP Builder Preview Click & Public Render Fix

## Fokus

Patch ini memperbaiki temuan LP Builder setelah V33.1.3:

1. Blok di area preview builder bisa diklik untuk memilih blok aktif di sidebar.
2. Preview memberi highlight pada blok aktif.
3. Label internal funnel seperti `awareness`, `trust`, dan `lead` tidak tampil di frontend public.
4. Public renderer lebih tahan terhadap item/card lama yang masih berbentuk string.
5. Block yang baru ditambahkan tetap disinkronkan ke hidden `blocks_json` saat render/submit.

## Guardrail

Patch ini tidak membongkar SEO, analytics, tracking CTA/form, A/B testing, revision, draft/publish, sitemap, Elementor Safe Import, Migration Command Center, dan modul migrasi WordPress.

## Catatan Teknis

Fatal error public sebelumnya muncul karena `landing_page_item_style_attrs()` menerima string dari item legacy, sementara function-nya expect array. Sekarang function menerima `mixed` dan langsung return string kosong jika item bukan array.

Label internal funnel goal tetap tersedia sebagai atribut data untuk tracking/audit, tetapi pseudo badge public disembunyikan lewat CSS supaya tidak terlihat oleh visitor.
