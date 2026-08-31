# Google Sheets Apps Script Connector

Panduan ini menjelaskan cara menghubungkan U-Growth ke Google Spreadsheet tanpa mencocokkan tabel secara manual.

## Alur singkat

1. Admin membuat Google Spreadsheet kosong.
2. Admin membuka **Extensions → Apps Script**.
3. Admin menyalin kode connector dari menu **Sistem → Backup & Sync Data**.
4. Admin menempel kode ke Apps Script.
5. Admin mengisi `SYNC_TOKEN` di Script Properties.
6. Admin menjalankan `setupUGrowthSheets` sekali untuk membuat tab standar.
7. Admin deploy sebagai Web App.
8. URL Web App ditempel ke pengaturan U-Growth.
9. Admin menjalankan tombol **Kirim ke Cloud** dari U-Growth.

## Sheet standar

Connector menyiapkan sheet berikut:

- `leads`
- `orders`
- `analytics_events`
- `customers`
- `payment_proofs`
- `member_access`
- `email_logs`

Header akan disiapkan otomatis. Jika payload U-Growth membawa kolom baru, connector akan menambah header baru tanpa merusak kolom lama.

## Token keamanan

Google Apps Script Web App tidak selalu menyediakan akses header request ke script. Karena itu U-Growth mengirim token di dua tempat:

- header `X-Ugrowth-Token` untuk endpoint umum,
- body payload `auth.token` untuk Google Apps Script.

Nilai token di pengaturan U-Growth harus sama dengan `SYNC_TOKEN` pada Script Properties.

## Mode sinkronisasi

Mode default adalah `replace`, yaitu isi sheet diganti dengan snapshot terbaru dari U-Growth. Mode ini dipilih agar dashboard Looker Studio tidak mudah dobel data saat admin menekan sync beberapa kali.

## Google Drive backup

Jika `DRIVE_FOLDER_ID` diisi dan tujuan Google Drive diaktifkan, connector akan menyimpan payload JSON sebagai arsip di folder Drive tersebut.

## Looker Studio

Setelah sheet berisi data, Looker Studio dapat membaca spreadsheet sebagai data source. Sheet yang paling penting untuk dashboard owner biasanya:

- `leads`
- `orders`
- `analytics_events`
- `customers`
- `payment_proofs`
- `member_access`

## Template Dashboard

Connector Apps Script juga menyediakan menu **Setup Template Dashboard**. Menu ini membuat tab panduan berikut di Spreadsheet:

- `_dashboard_guide`
- `_field_dictionary`
- `_chart_blueprint`

Tab ini tidak berisi token atau data rahasia. Isinya adalah panduan membuat scorecard, chart, filter, dan pertanyaan bisnis untuk dashboard Looker Studio.

## Reliability Guard Connector

Connector Google Sheets terbaru menambahkan:

- `UGROWTH_CONNECTOR_VERSION` untuk memudahkan pengecekan versi kode yang ditempel di Apps Script.
- `UGROWTH_MAX_ROWS_PER_REQUEST` agar payload besar tidak membuat Apps Script timeout.
- `payload_id` dan checksum untuk membantu audit sync.
- Tab `_sync_log` otomatis untuk melihat riwayat sync dari U-Growth.
- Sanitasi nama field agar header sheet tetap bersih.

Setelah mengganti kode Apps Script, jalankan ulang `setupUGrowthSheets` agar tab standar dan `_sync_log` dibuat.
