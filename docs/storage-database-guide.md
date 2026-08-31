# Panduan Storage & Database

Panduan ini menjelaskan cara memindahkan data website dari file/JSON/JSONL ke MySQL secara bertahap tanpa mematikan fallback file-based.

## Prinsip aman

- File-based tetap aktif sebagai mode default.
- MySQL hanya dipakai jika admin mengaktifkan mode Hybrid/MySQL dan memilih collection yang sudah siap.
- Data penting seperti lead/form submission, order, bukti pembayaran, analytics event, riwayat email, dan log aktivitas admin tetap ditulis ke file sebagai mirror/fallback.
- Jalankan backup sebelum migrasi data.
- Jangan aktifkan semua collection sekaligus. Aktifkan satu per satu, cek dashboard dan data, lalu lanjut ke collection berikutnya.

## Collection yang sudah punya jalur runtime MySQL

1. Produk / Katalog → `products`
2. Artikel → `articles`
3. Landing Page Builder → `ugrowth_landing_pages`
4. Data Masuk Form → `ugrowth_form_submissions`
5. Inbox Lead / Form Sederhana → `ugrowth_inquiries`
6. Order / Checkout → `ugrowth_orders` dan `ugrowth_order_items`
7. Bukti Pembayaran → `ugrowth_payment_proofs`
8. Analytics / Lead Events → `ugrowth_analytics_events`
9. Riwayat Email → `ugrowth_email_logs`
10. Log Aktivitas Admin → `ugrowth_activity_logs`

Collection lain tetap aman di file/JSON sampai fase migrasi berikutnya.

## Urutan yang disarankan

1. Backup file website dan database.
2. Isi koneksi database di `.env`.
3. Import `database.sql`.
4. Import `database/mysql-storage-schema.sql`.
5. Buka menu **Sistem → Migrasi Data MySQL** untuk preview dan migrasi data.
6. Mulai migrasi dari konten inti: produk, artikel, landing page.
7. Lanjutkan data operasional: form submission, inbox lead/inquiry, order, bukti pembayaran.
8. Lanjutkan data analytics/log: analytics events, riwayat email, log aktivitas admin.
9. Buka menu **Sistem → Storage & Database**.
10. Pilih mode **Hybrid**.
11. Aktifkan collection secara bertahap, mulai dari data yang sudah dicek.
12. Cek dashboard, form, checkout, order, payment proof, tracking lead, riwayat email, log sistem, laporan, dan Looker Studio preview.

## Catatan penting

Mode Hybrid adalah pilihan paling aman saat awal migrasi. Data runtime bisa mulai dibaca dari MySQL per collection, tetapi file mirror tetap tersedia untuk cadangan lokal dan rollback ringan.
