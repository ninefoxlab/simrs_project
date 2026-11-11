# SIMRS RSUD NTB

Sistem Informasi Manajemen Rumah Sakit berbasis PHP native + MySQL: registrasi & admisi, rekam medis elektronik (EMR), order medis (CPOE), laboratorium, radiologi, farmasi, keperawatan, IGD, rawat inap, billing & klaim, persediaan gudang, laporan, dan audit trail. Akses dibatasi per role (15 role, dari IT Admin sampai Pimpinan).

## Kebutuhan

- PHP 8.2+ dengan ekstensi `pdo_mysql`
- MySQL 8 / MariaDB

## Menjalankan secara lokal

1. Buat database `simrs_db` beserta role & permission:

   ```bash
   mysql -uroot < database/schema.sql
   ```

2. Sesuaikan koneksi di `config/database.php` bila user/password MySQL berbeda.

3. Buat akun demo (satu akun per role). Password acak ditulis ke `database/AKUN_DEMO.md` (tidak ikut di-commit):

   ```bash
   php database/seed_users.php
   ```

4. Jalankan server dari folder **induk** repo ini, karena URL aplikasi adalah `/simrs/public`. Folder hasil clone harus bernama `simrs` (`git clone <url> simrs`):

   ```bash
   cd ..
   php -S 127.0.0.1:8080 -t . simrs/router.php
   ```

   Buka http://127.0.0.1:8080/simrs/public/login.php

   `router.php` hanya membuka folder `public/`. Server bawaan PHP tidak membaca `.htaccess`, jadi `config/`, `app/`, dan `database/` diblokir lewat router ini. Di Apache/XAMPP, letakkan folder `simrs` di `htdocs`.

5. Login sebagai `admin`, lalu buka `public/isi_master_data_demo.php` untuk mengisi contoh unit, penjamin, obat, kamar, gudang, dan barang.

## Struktur

| Folder | Isi |
|---|---|
| `config/` | Konfigurasi aplikasi & koneksi database |
| `app/` | Bootstrap, autentikasi, permission, menu, helper |
| `app/pages/` | Halaman dashboard, pengguna, audit, modul generik, workflow pelayanan |
| `public/` | Entry point (login, index, aksi form), aset CSS/JS, seeder demo |
| `database/` | `schema.sql` dan `seed_users.php` |

## Catatan

- Data demo adalah simulasi, bukan data resmi rumah sakit.
- Jangan gunakan akun demo atau password bersama di lingkungan produksi.
