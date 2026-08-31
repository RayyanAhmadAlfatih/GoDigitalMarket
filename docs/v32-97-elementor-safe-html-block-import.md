# V32.97 — Elementor Safe HTML Block Import

Versi ini menambahkan lapisan migrasi WordPress untuk halaman yang dibuat memakai Elementor atau page builder lain. Fokusnya bukan menjanjikan desain 100% identik, tetapi memastikan konten SEO dari halaman lama bisa masuk ke U-Growth sebagai Landing Page draft yang aman untuk direview.

## Fitur utama

- Menu admin: **Konten & SEO → Elementor Safe Import**.
- Scan job WordPress Migration yang berisi `page` atau landing page.
- Deteksi page builder: Elementor, Gutenberg, WPBakery, Divi, Shortcode Builder, Classic/HTML.
- Deteksi widget kompleks seperti slider, popup, form plugin, accordion/tabs, countdown, WooCommerce, dan custom JavaScript.
- Import sebagai **HTML block aman** di Landing Page Builder.
- Mode campuran: hero native sederhana + HTML fallback.
- Sanitizer membuang script, iframe, object, embed, form/plugin input lama, inline event handler, dan URL `javascript:`.
- Semua hasil import default menjadi **draft** agar admin bisa review sebelum publish.

## Prinsip aman

1. Jangan membawa JavaScript/plugin WordPress lama ke U-Growth.
2. Jangan langsung publish halaman hasil page builder.
3. Gunakan Form Builder U-Growth untuk mengganti form Contact Form 7/WPForms/Elementor Form.
4. Widget kompleks tetap diberi warning agar admin tahu bagian mana yang perlu dicek manual.
5. Jika tampilan lama tidak persis sama, prioritaskan konten SEO, canonical, redirect, internal link, dan CTA tracking.

## Alur penggunaan

1. Upload dan preview WXR/XML di menu **Migrasi WordPress**.
2. Buka **Elementor Safe Import**.
3. Pilih job migrasi.
4. Scan page builder.
5. Pilih mode:
   - `HTML block aman` untuk migrasi cepat dan konservatif.
   - `Campuran native + HTML fallback` untuk membuat hero sederhana + HTML block.
6. Import sebagai draft.
7. Review halaman di Landing Page Builder.
8. Ganti form lama dengan Form Builder U-Growth.
9. Setelah aman, publish dan hubungkan redirect/canonical dari SEO Preservation.

## Catatan roadmap

V32.97 adalah tahap safe import. Konversi penuh widget Elementor ke blok native U-Growth tetap bisa dikembangkan bertahap, tetapi tidak dipaksakan di versi ini agar fitur stabil dan shared-hosting friendly.
