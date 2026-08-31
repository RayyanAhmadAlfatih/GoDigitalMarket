# U-Growth V32.92 — WordPress Migration Foundation

Fitur ini membantu pemilik bisnis yang sudah punya website WordPress untuk memindahkan konten awal ke U-Growth tanpa langsung membongkar fitur yang sudah berjalan.

## Fokus V32.92

- Upload export WordPress XML/WXR atau CSV.
- Preview aman sebelum import.
- Mapping WordPress `post` menjadi Artikel U-Growth.
- Mapping WordPress `page` menjadi Landing Page draft.
- Membawa SEO title, SEO description, canonical, kategori, tag, author, tanggal, featured image remote, dan legacy URL bila tersedia.
- Membersihkan Gutenberg comments dan shortcode/plugin dasar.
- Menyimpan batch log di `storage/wp-migration/jobs.json`.
- Membuat backup storage sebelum import.
- Rollback batch dari backup JSON.

## Cara pakai singkat

1. Di WordPress lama buka **Tools → Export**.
2. Pilih **Posts** atau **All Content** lalu download file XML.
3. Di U-Growth buka **Admin → Konten & SEO → Migrasi WordPress**.
4. Upload file XML/WXR/CSV.
5. Cek preview: jumlah artikel, halaman/LP, konflik slug, dan catatan shortcode.
6. Jalankan import dengan backup aktif.
7. Review hasil di menu Artikel dan Landing Pages.

## Catatan penting

- V32.92 belum mengaktifkan resolver URL lama root-level dan redirect 301 otomatis penuh. Itu masuk fase V32.93 SEO Preservation Layer.
- Halaman WordPress masuk sebagai Landing Page draft dengan text block aman. Konversi Elementor/native block penuh masuk fase V32.97.
- Jika slug bentrok, admin bisa memilih rename otomatis atau lewati.
