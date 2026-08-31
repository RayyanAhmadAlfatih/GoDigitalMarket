# Panduan Menu & Fitur Admin

Halaman **Sistem → Menu & Fitur Admin** dipakai untuk menyembunyikan menu dashboard yang belum relevan, tumpang tindih, atau belum ingin dipakai oleh admin harian.

## Prinsip aman

Feature toggle ini hanya menyembunyikan item dari sidebar admin. Pengaturan ini tidak menghapus:

- file halaman,
- route,
- core/helper,
- data storage,
- tabel MySQL,
- produk,
- artikel,
- order,
- analytics.

Karena itu aman dipakai sebagai tahap awal sebelum memutuskan menghapus modul secara permanen.

## Menu yang dikunci

Beberapa menu inti tidak bisa disembunyikan agar owner tidak terkunci dari dashboard, misalnya:

- Brand & Warna,
- Menu & Fitur Admin,
- Manajemen User & Role,
- Keamanan.

## Alur penggunaan

1. Buka **Sistem → Menu & Fitur Admin**.
2. Centang **Sembunyikan** pada menu yang belum diperlukan.
3. Simpan pengaturan.
4. Refresh dashboard dan cek sidebar.
5. Jika semua aman selama beberapa waktu, baru audit dependency bila ingin menghapus file/source secara permanen.

## Catatan update

Saat update versi U-Growth, route dan file tetap aman karena fitur ini tidak menghapus source code. Jika ada menu baru dari versi update, menu tersebut akan muncul otomatis sampai owner menyembunyikannya lagi.

## Status audit menu

Mulai patch **Feature Toggle Audit Status Polish**, audit bawaan U-Growth membedakan status menu dengan lebih jelas:

- **Aktif di sidebar**: menu tampil normal dan route masih tersedia.
- **Disembunyikan oleh owner**: menu sengaja disembunyikan dari sidebar, tetapi route, file, helper, dan data tetap aman.
- **Dikunci sistem**: menu inti tidak boleh disembunyikan agar owner tidak terkunci dari dashboard.
- **Belum terdaftar di menu**: route/modul ada tetapi belum terhubung ke registry menu, sehingga perlu dicek.

Menu yang disembunyikan oleh owner tidak menurunkan skor audit selama route, page, core helper, function, dan bantuan dashboard tetap tersedia.
