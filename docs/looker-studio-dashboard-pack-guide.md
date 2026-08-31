# Looker Studio Dashboard Pack Guide

Panduan ini membantu admin mengubah data U-Growth menjadi dashboard visual yang mudah dibaca owner atau tim marketing.

## Jalur koneksi yang didukung

1. **Google Sheets sebagai jembatan**
   - Cocok untuk user non-teknis.
   - Data dari U-Growth dikirim ke Google Sheets.
   - Looker Studio membaca sheet tersebut.

2. **Koneksi langsung Looker Studio**
   - Cocok untuk tim yang ingin dashboard membaca endpoint U-Growth langsung.
   - Menggunakan Community Connector berbasis Apps Script.
   - Wajib HTTPS dan token aktif.

## Data source yang disiapkan

- Order / Checkout
- Lead / Data Masuk Form
- Analytics / Event Tracking
- Landing Page Analytics
- Offer & CTA Testing
- CTA Placement
- CTA Result Tracker
- SEO Profit Attribution
- Profit Action Dashboard
- SEO Campaign Calendar
- Lead Priority Scoring
- Internal Link & CTA Injection
- SEO Content Refresh
- SEO Money Page Optimizer
- Pembeli / Member
- Akses Produk Digital
- Bukti Pembayaran
- Riwayat Email

## Dashboard yang disarankan

### Owner Overview
Untuk melihat lead, order, omzet, conversion, dan action plan prioritas.

### Lead & CRM Dashboard
Untuk melihat lead masuk, sumber lead, lead score, dan status follow-up.

### Sales & Payment Dashboard
Untuk melihat order masuk, status pembayaran, bukti bayar, produk terlaris, dan order belum bayar.

### SEO Profit Dashboard
Untuk melihat halaman SEO yang menghasilkan traffic, lead, order, dan revenue.

### CTA & Campaign Dashboard
Untuk melihat eksperimen CTA, campaign aktif, conversion rate, dan prioritas perbaikan.

### Member & Digital Product Dashboard
Untuk melihat akses produk digital, member aktif, akses expired, dan produk digital yang paling sering dibeli.

## Cara pakai di U-Growth

1. Buka **Sistem → Backup & Sync Data**.
2. Isi Google Apps Script URL atau aktifkan Looker Studio Direct.
3. Buka section **Looker Studio Data Preview**.
4. Pilih sumber data seperti `orders` atau `form_submissions`.
5. Cek field, contoh data, dan rekomendasi visual.
6. Gunakan Google Sheets atau Community Connector untuk membuat data source di Looker Studio.
7. Buat dashboard sesuai blueprint yang tersedia.

## Catatan penting

- Jika data masih kosong, dashboard tetap bisa disiapkan, tetapi grafik baru akan muncul setelah data masuk.
- Untuk koneksi langsung, domain wajib HTTPS agar Apps Script dan Looker Studio dapat mengambil data.
- Jangan membagikan token koneksi kepada pihak yang tidak berkepentingan.

## Template pack Google Sheets

Selain blueprint di dashboard admin, Google Sheets connector bisa membuat tab panduan dashboard secara otomatis. Jalankan fungsi `setupUGrowthDashboardTemplate` dari menu U-Growth di Spreadsheet setelah sheet standar dibuat.
