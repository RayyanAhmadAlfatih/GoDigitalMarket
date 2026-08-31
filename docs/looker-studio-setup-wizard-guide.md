# Panduan Setup Wizard Looker Studio

Panduan ini menjelaskan alur singkat agar data U-Growth bisa divisualisasikan di Looker Studio.

## Jalur yang tersedia

U-Growth menyediakan dua jalur visualisasi:

1. **Google Sheets → Looker Studio**
   - Paling mudah untuk mayoritas pengguna.
   - Data disinkronkan ke spreadsheet, lalu spreadsheet dibaca oleh Looker Studio.

2. **Koneksi langsung Looker Studio**
   - Menggunakan Community Connector berbasis Google Apps Script.
   - Looker Studio membaca endpoint API U-Growth dengan token.

## Langkah cepat koneksi langsung

1. Buka menu **Sistem → Backup & Sync Data**.
2. Aktifkan opsi **Koneksi langsung Looker Studio**.
3. Isi token khusus Looker Studio.
4. Salin kode **Looker Studio Community Connector** dari dashboard.
5. Tempel kode ke Google Apps Script.
6. Deploy sebagai Community Connector.
7. Buat data source di Looker Studio.
8. Masukkan API URL dan token.
9. Pilih source data seperti `orders`, `form_submissions`, atau `seo_profit_attribution`.
10. Buat chart dari blueprint dashboard yang disarankan.

## Source data awal yang disarankan

- `orders` untuk dashboard penjualan dan pembayaran.
- `form_submissions` untuk dashboard lead dan CRM.
- `seo_profit_attribution` untuk dashboard SEO yang berdampak ke omzet.
- `cta_results` untuk performa CTA.
- `member_access` untuk produk digital dan akses pembeli.

## Debugging

Di bagian **Looker Studio Setup Wizard**, admin bisa melihat:

- skor kesiapan setup,
- langkah yang sudah siap,
- source data yang bisa dites,
- jumlah record dan field,
- URL test API untuk status, sources, schema, dan preview.

Gunakan URL test hanya untuk debugging. Jangan membagikan token ke pihak yang tidak dipercaya.

## Catatan keamanan

- Token connector tidak disimpan di file source release.
- Endpoint Looker Studio wajib token.
- Jika koneksi langsung belum aktif, endpoint akan menolak request.
- Untuk production, gunakan domain HTTPS.
