# Panduan Backup & Sinkronisasi Data

Fitur Backup & Sinkronisasi Data menyiapkan data U-Growth agar bisa dicadangkan dan dibaca oleh dashboard eksternal seperti Google Sheets, Google Drive, dan Looker Studio.

## Prinsip Aman

- Website tetap berjalan dengan penyimpanan yang sudah aktif.
- Export lokal bisa digunakan meskipun endpoint Google belum disiapkan.
- Sinkronisasi cloud hanya berjalan setelah admin mengisi URL Google Apps Script Web App.
- Token sinkronisasi dikirim lewat header `X-Ugrowth-Token`.
- File export dibuat di folder storage privat dan tidak ikut paket ZIP release.

## Sumber Data Awal

Sumber data yang disiapkan:

- Lead / data masuk form
- Order / checkout
- Analytics / event tracking
- Bukti pembayaran
- Pembeli / member
- Akses produk digital
- Riwayat email

## Alur Google Sheets

1. Buat Google Spreadsheet.
2. Buat Google Apps Script Web App yang menerima payload JSON.
3. Isi Apps Script URL di menu Backup & Sinkronisasi Data.
4. Isi Spreadsheet ID.
5. Aktifkan sumber data yang ingin disinkronkan.
6. Jalankan export lokal dulu untuk cek struktur data.
7. Jalankan sync cloud setelah endpoint siap.

## Alur Google Drive

Google Drive dipakai untuk arsip export atau backup tambahan. Folder ID bisa diisi jika Apps Script akan menyimpan file export ke folder tertentu.

## Rekomendasi Tahap Awal

Mulai dari data berikut:

1. Lead / data masuk form
2. Order / checkout
3. Analytics / event tracking

Setelah data utama stabil, lanjutkan pembeli/member, bukti pembayaran, akses produk digital, dan riwayat email.

## Connector Apps Script siap salin

Menu **Backup & Sync Data** menyediakan kode Google Apps Script siap salin. Kode tersebut akan:

- menerima data dari U-Growth via Web App,
- membuat sheet standar untuk lead, order, analytics, customer, bukti bayar, member access, dan riwayat email,
- menambah header otomatis saat ada kolom baru,
- mengganti isi sheet dengan snapshot terbaru agar data tidak dobel,
- menyimpan backup JSON ke Google Drive jika folder backup diaktifkan.

Gunakan token sinkronisasi yang sama di U-Growth dan Script Properties Apps Script.

## Data Health, Reliability, dan Hardening Sync

Backup & Sync Data sekarang memiliki lapisan reliability tambahan agar admin bisa mengecek kesiapan data sebelum dikirim ke Google Sheets atau Looker Studio.

### Guard yang tersedia

- **Health score sync** membaca endpoint, token, template Apps Script, log sync, dan kondisi sumber data aktif.
- **Payload guard** menambahkan `payload_id`, `row_count`, dan `rows_checksum` setiap kali data dikirim.
- **Batas row per sync** menjaga payload tetap ringan untuk shared hosting dan Google Apps Script.
- **Retry ringan** membantu saat endpoint cloud sempat gagal sesaat.
- **Log privat** menyimpan riwayat export/sync di folder log yang sudah diproteksi.
- **Redaksi data sensitif** mencegah field password, token, secret, session, credential, dan path sensitif ikut terkirim ke sheet.
- **Backup batch** membuat export lokal semua sumber aktif sebelum update besar, migrasi, atau setup dashboard.

### Urutan aman sebelum mengaktifkan sync cloud

1. Buka **Sistem → Cek Sistem** dan pastikan tidak ada error kritikal.
2. Buka **Sistem → Backup & Sync Data**.
3. Klik **Backup Semua Aktif** untuk membuat arsip lokal.
4. Salin ulang kode Google Apps Script terbaru dari dashboard jika connector lama belum memiliki tab `_sync_log`.
5. Jalankan `setupUGrowthSheets` di Apps Script.
6. Isi Web App URL, Spreadsheet ID, dan token.
7. Coba sync satu sumber kecil dulu, misalnya lead atau order.
8. Cek tab `_sync_log` di Google Sheets dan riwayat sync di dashboard.

### Catatan Looker Studio

Koneksi langsung Looker Studio membatasi data maksimal per request dan mewajibkan HTTPS + token. Untuk dashboard besar, pakai filter tanggal di Looker Studio dan pilih source yang memang dibutuhkan agar loading tetap ringan.
