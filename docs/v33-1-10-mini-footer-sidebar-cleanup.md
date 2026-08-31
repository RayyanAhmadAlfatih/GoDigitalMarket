# V33.1.10 — Mini Footer Custom Color & Sidebar Gallery Cleanup

## Fokus patch

- Memperkuat render public mini footer agar background footer custom tidak kalah oleh theme coverage global.
- Memperkuat warna brand mini footer dengan inline style aman setelah sanitasi warna.
- Menandai mini footer custom dengan class `landing-mini-footer--custom` supaya aturan tema bawaan tidak menimpa background custom.
- Menghapus card `Template Gallery` dari sidebar kiri LP Builder agar editor blok lebih mudah ditemukan saat scroll.
- Akses Template Gallery tetap tersedia dari dropdown tombol Publish/topbar.
- Template custom kini menyimpan dan menerapkan pengaturan mini footer agar desain tidak hilang saat dipakai ulang.

## Guardrail

Patch ini tidak membongkar SEO, analytics, tracking, A/B testing, WordPress Migration Toolkit, Migration Command Center, dynamic content, public renderer, revision/draft/publish, maupun sanitizer HTML.
