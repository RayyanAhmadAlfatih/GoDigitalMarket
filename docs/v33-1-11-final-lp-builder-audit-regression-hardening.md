# V33.1.11 — Final LP Builder Audit & Regression Hardening

Versi ini fokus audit dan hardening, bukan menambah fitur besar baru. Baseline yang dipakai adalah V33.1.10.

## Fokus Audit

- Mengecek ulang file umum agar update LP Builder tidak mengganggu route publik, admin, SEO, analytics, A/B testing, migration toolkit, dynamic content, dan renderer publik.
- Menambahkan audit otomatis khusus LP Builder di Release Audit.
- Memastikan kontrak preview builder dan public frontend tetap sinkron untuk style block, style item/card, tombol, custom menu, mini footer, HTML safe mode, tracking, SEO, dan A/B bridge.
- Menjaga sidebar builder tetap bersih setelah card Template Gallery dipindahkan dari sidebar kiri ke akses dialog/dropdown topbar.

## Audit Otomatis Baru

Release Audit sekarang punya grup `LP Builder Regression` dengan pengecekan:

1. Marker versi builder dan renderer sinkron.
2. Registry block inti lengkap.
3. Preview dan public render memakai helper style yang sama.
4. Design tombol sampai ke frontend.
5. Custom Menu header/logo/sticky tetap aman.
6. Mini footer custom color tidak dioverride tema.
7. HTML block/full HTML mode tetap disanitasi.
8. Draft, publish, revision, local draft guard, dan publish guard tetap tersedia.
9. SEO, analytics, tracking CTA/form, dan A/B bridge tidak terganggu.
10. Sidebar builder bersih dari card gallery pengganggu.
11. CSS conflict guard untuk LP Builder aktif.

## Catatan Guardrail

- Tidak membongkar modul U-Growth lain.
- Tidak mengubah alur WordPress Migration Toolkit.
- Tidak menambah sistem tracking baru.
- Tidak menghapus fitur Template Gallery; hanya menjaga aksesnya tetap di dialog/dropdown topbar.
- Tidak mengubah storage utama landing page selain marker dan audit helper.

## Rekomendasi Test Manual Setelah Upload

- Buka admin Landing Page Builder, edit LP sample, cek Desktop/Tablet/Mobile.
- Test Custom Menu: logo kiri/tengah/kanan, menu kiri/tengah/kanan, sticky/fixed.
- Test Tombol/CTA: align, warna background, warna teks, radius, ukuran.
- Test Mini Footer: background, warna brand, warna teks, ukuran teks, rata footer.
- Test HTML Block: script/event handler tidak boleh jalan.
- Test publish dan preview live.
- Buka Release Audit dan pastikan grup `LP Builder Regression` tidak punya temuan kritis.
