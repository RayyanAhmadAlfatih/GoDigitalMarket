# Panduan Koneksi Langsung Looker Studio

Panduan ini membantu pemilik website menghubungkan data U-Growth langsung ke Looker Studio melalui Community Connector berbasis Google Apps Script.

## Kapan memakai koneksi langsung?

Gunakan koneksi langsung jika ingin dashboard Looker Studio membaca data dari website tanpa lewat Google Spreadsheet.

Untuk kebutuhan yang sangat sederhana, Google Spreadsheet tetap lebih mudah. Untuk dashboard yang ingin membaca endpoint website secara langsung, gunakan connector ini.

## Alur singkat

1. Buka menu **Sistem → Backup & Sync Data**.
2. Aktifkan **Koneksi Langsung Looker Studio**.
3. Isi token khusus Looker Studio.
4. Salin kode **Looker Studio Community Connector**.
5. Buka Google Apps Script dan tempel kode connector.
6. Deploy connector dan buat data source di Looker Studio.
7. Isi URL endpoint U-Growth dan token.
8. Pilih sumber data seperti order, lead, analytics, atau SEO profit.

## Sumber data yang disiapkan

- Order / Checkout
- Lead / Data Masuk Form
- Analytics / Event Tracking
- Landing Page Analytics
- Offer & CTA Testing
- CTA Result Tracker
- SEO Profit Attribution
- Lead Priority Scoring
- Pembeli / Member
- Akses Produk Digital
- Bukti Pembayaran
- Riwayat Email

## Keamanan

Endpoint langsung wajib memakai token. Jangan membagikan token ke publik dan jangan menaruh token di artikel, halaman publik, atau dokumentasi publik.

## Catatan dashboard

Untuk dashboard owner, kombinasi yang paling penting biasanya:

- lead harian,
- order masuk,
- omzet,
- order belum bayar,
- channel/source lead,
- performa landing page,
- CTA terbaik,
- halaman SEO yang menghasilkan lead/order,
- akses produk digital/member.

## Preview data dan dashboard pack

Menu Backup & Sync Data menyediakan preview schema, contoh baris, dan blueprint dashboard agar admin bisa memastikan data siap divisualisasikan sebelum membuat chart di Looker Studio.

## Hardening Connector Langsung

Connector langsung memakai beberapa guard agar lebih aman dan stabil:

- URL API harus memakai HTTPS.
- Token koneksi wajib diisi.
- Source data dibersihkan agar hanya berisi huruf, angka, dan underscore.
- Jumlah row per request dibatasi agar Looker Studio dan hosting tetap responsif.
- Connector mencoba ulang satu kali jika request pertama gagal.
- Endpoint API mengirim header `no-store`, `noindex`, dan `nosniff`.

Jika dashboard terasa berat, kurangi jumlah visual dalam satu halaman dashboard, gunakan filter tanggal, atau mulai dari Google Sheets sebagai cache visual.
