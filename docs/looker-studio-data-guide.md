# Panduan Data Looker Studio

Looker Studio bisa membaca data U-Growth melalui Google Sheets. Struktur sheet yang direkomendasikan dibuat agar owner bisnis mudah melihat performa website.

## Sheet Utama

- `leads`: data prospek dari form custom dan lead magnet.
- `orders`: data order, status pembayaran, total transaksi, dan kontak pembeli.
- `analytics_events`: event tracking seperti klik CTA, klik WhatsApp, dan campaign.
- `customers`: data pembeli/member.
- `payment_proofs`: metadata bukti pembayaran.
- `member_access`: akses produk digital atau course.
- `email_logs`: riwayat email sistem.

## Dashboard yang Disarankan

1. Dashboard Owner
   - Lead harian
   - Order masuk
   - Order belum bayar
   - Omzet
   - Produk paling diminati

2. Dashboard Marketing
   - Sumber lead
   - CTA yang paling sering diklik
   - Landing page paling menghasilkan
   - Artikel SEO yang menghasilkan lead/order

3. Dashboard Operasional
   - Pembayaran menunggu verifikasi
   - Follow-up pembayaran
   - Member/access produk aktif

## Catatan Implementasi

- Pastikan nama sheet konsisten agar koneksi Looker Studio tidak perlu sering diubah.
- Jangan simpan token, password, atau file bukti bayar mentah di sheet publik.
- Gunakan Google Drive untuk arsip file, dan Google Sheets untuk data tabular.
