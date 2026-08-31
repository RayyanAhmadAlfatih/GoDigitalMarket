# Panduan Installer & First Run Setup

Halaman `/install` membantu pemilik website menyelesaikan setup awal setelah source code diupload ke hosting atau VPS.

## Kapan installer terbuka?

Installer otomatis terbuka jika akun owner belum siap. Setelah akun owner dibuat dan password aman tersimpan, installer akan terkunci otomatis.

Jika suatu saat perlu membuka lagi, login sebagai owner lalu buka `/install`, atau aktifkan sementara:

```env
INSTALLER_ENABLED=true
```

Setelah selesai, ubah kembali menjadi:

```env
INSTALLER_ENABLED=false
```

## Langkah setup awal

1. Upload source code ke hosting/VPS.
2. Pastikan permission folder `storage`, `logs`, `cache`, dan `assets/uploads` writable oleh PHP.
3. Buka `https://domain-anda.com/install`.
4. Cek checklist server.
5. Isi URL website final, nama bisnis, email, WhatsApp, dan preset warna. Preset warna yang dipilih saat install langsung disimpan ke pengaturan brand.
6. Buat akun owner dengan password kuat.
7. Klik **Simpan Setup Awal**.
8. Login ke dashboard melalui `/admin/login`.
9. Lanjutkan setup dari menu **Brand & Warna**, **Website Starter Wizard**, **Storage & Database**, dan **Kesiapan Website**.

## Catatan keamanan

- Jangan biarkan `INSTALLER_ENABLED=true` setelah setup selesai.
- Gunakan password owner yang panjang dan unik.
- Jalankan **Kesiapan Website** sebelum website live.
- Jika memakai MySQL, import `database.sql` dan `database/mysql-storage-schema.sql`, lalu migrasikan data dari menu **Migrasi Data MySQL**.
