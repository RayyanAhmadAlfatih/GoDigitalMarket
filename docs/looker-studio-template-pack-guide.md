# Panduan Template Dashboard Looker Studio

Panduan ini menjelaskan cara memakai data U-Growth untuk membuat dashboard visual yang mudah dibaca owner dan tim.

## Alur yang disarankan

1. Buka menu **Sistem → Backup & Sync Data**.
2. Siapkan Google Sheets connector atau Looker Studio Direct Connector.
3. Jalankan setup sheet standar di Apps Script.
4. Jalankan **Setup Template Dashboard** di menu U-Growth pada Google Sheets.
5. Gunakan tab panduan berikut sebagai referensi saat membuat dashboard di Looker Studio:
   - `_dashboard_guide`
   - `_field_dictionary`
   - `_chart_blueprint`

## Dashboard pack

### Owner Overview
Untuk membaca kondisi bisnis harian: omzet, order, lead, invoice tertunda, dan action plan prioritas.

### Sales & Payment
Untuk memantau order, status pembayaran, bukti bayar, dan tagihan yang harus difollow-up.

### Lead & CRM
Untuk melihat sumber lead, kualitas lead, status follow-up, dan campaign yang menghasilkan prospek terbaik.

### SEO Profit
Untuk melihat halaman SEO yang menghasilkan traffic, lead, order, dan revenue.

### CTA & Campaign
Untuk menilai eksperimen offer, CTA, campaign, dan conversion rate.

### Member & Digital Product
Untuk memantau akses produk digital, member aktif, dan akses yang hampir berakhir.

## Prinsip visualisasi

- Pakai scorecard untuk angka utama seperti omzet, lead, order, dan tagihan tertunda.
- Pakai time series untuk tren harian/mingguan.
- Pakai bar chart untuk membandingkan channel, status, produk, atau halaman.
- Pakai table untuk action plan, daftar invoice, dan prioritas follow-up.
- Pakai filter tanggal, status pembayaran, source, campaign, dan produk agar dashboard mudah dipakai.

## Catatan keamanan

Token dan credential tidak boleh ditulis di sheet. Simpan token di pengaturan U-Growth, `.env`, atau Script Properties Google Apps Script.
