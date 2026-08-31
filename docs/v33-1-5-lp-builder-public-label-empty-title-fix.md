# V33.1.5 — LP Builder Public Labels & Empty Title Fix

Versi ini memperbaiki temuan pada public render landing page setelah V33.1.4.

## Fokus perbaikan

- Nama/label internal block seperti Form Lead, Masalah Customer, Benefit, Paket Penawaran, FAQ, Testimoni, dan sejenisnya tidak lagi ditampilkan otomatis di frontend.
- Funnel goal seperti awareness/trust/lead tetap disimpan sebagai data attribute untuk tracking, audit, analytics, dan A/B testing, tetapi tidak terlihat oleh visitor.
- Judul section tidak lagi memakai fallback/default ketika admin mengosongkan headline.
- Preview builder mengikuti perilaku public render: headline/section title kosong tidak memunculkan teks default.
- Renderer tetap menjaga tracking, CTA signal, A/B testing, SEO, sitemap, migration modules, dan public `/lp/{slug}`.

## Catatan

Jika admin ingin menampilkan label kecil di atas judul, gunakan field **Eyebrow/Label kecil** secara sadar. Sistem tidak lagi menampilkan nama block otomatis.
