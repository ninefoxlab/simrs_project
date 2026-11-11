# Deploy ke Vercel + TiDB Cloud (MySQL gratis)

Aplikasi berjalan di Vercel memakai runtime komunitas [`vercel-php`](https://github.com/vercel-community/php) (PHP 8.4), dengan database **TiDB Cloud Starter**, yaitu layanan gratis yang kompatibel MySQL.

Yang sudah disiapkan di repo:

| File | Fungsi |
|---|---|
| `vercel.json` | Semua request diarahkan ke `api/index.php`, region Singapura (`sin1`) |
| `api/index.php` | Router: meneruskan ke `public/*.php`, menyajikan `/assets/*`, memblokir folder lain |
| `config/database.php` | Koneksi dari environment variable `DB_*`, TLS untuk TiDB |
| `app/session.php` | Session login disimpan di tabel `simrs_sessions` (wajib di serverless) |
| `database/migrate.php` | Membuat semua tabel dari `schema.sql` tanpa perlu klien `mysql` |

Di Vercel, aplikasi berada di root domain: `https://<project>.vercel.app/login.php`.

## 1. Buat database TiDB Cloud

1. Buka https://tidbcloud.com, atau di dashboard Vercel pilih **Storage → Marketplace → TiDB Cloud**.
2. Buat cluster **Starter** (gratis). Pilih region **AWS Singapore (ap-southeast-1)**, supaya dekat dengan region Vercel `sin1`.
3. Klik **Connect** dan catat:
   - Host, misalnya `gateway01.ap-southeast-1.prod.aws.tidbcloud.com`
   - Port `4000`
   - User, misalnya `xxxxxxxx.root`
   - Password (buat atau generate di halaman itu)

## 2. Isi tabel & akun dari laptop

Jalankan dari folder repo. Ganti nilai `DB_*` dengan milik Anda:

```bash
export DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com DB_PORT=4000 DB_USER=xxxxxxxx.root DB_PASSWORD='password-anda' DB_NAME=simrs_db DB_SSL=1
```

```bash
php database/migrate.php
```

```bash
php database/seed_users.php
```

- `migrate.php` membuat database `simrs_db`, 70 tabel, serta role & permission.
- `seed_users.php` membuat 15 akun demo. Password-nya ditulis ke `database/AKUN_DEMO_CLOUD.md`, yang tidak ikut di-commit maupun di-upload.

## 3. Deploy di Vercel

1. https://vercel.com/new → **Import** repo GitHub `ninefoxlab/simrs_project`.
2. Framework Preset: **Other**. Biarkan Build Command dan Output Directory kosong.
3. Isi **Environment Variables**:

   | Nama | Nilai |
   |---|---|
   | `DB_HOST` | host TiDB |
   | `DB_PORT` | `4000` |
   | `DB_USER` | user TiDB |
   | `DB_PASSWORD` | password TiDB |
   | `DB_NAME` | `simrs_db` |
   | `DB_SSL` | `1` |

4. Klik **Deploy**. Setelah selesai, buka `https://<project>.vercel.app/`.

## 4. Isi data contoh

Login sebagai `admin` dengan password dari `AKUN_DEMO_CLOUD.md`, lalu buka `https://<project>.vercel.app/isi_master_data_demo.php` dan klik tombolnya.

## Catatan

- `vercel-php` adalah runtime komunitas, tidak didukung resmi oleh Vercel.
- Paket Vercel **Hobby** hanya untuk penggunaan non-komersial. Untuk dipakai rumah sakit sungguhan, gunakan paket berbayar.
- Jangan simpan data pasien asli di layanan gratis. Data kesehatan termasuk data pribadi spesifik menurut UU PDP.
- Mode lokal tetap seperti biasa (`/simrs/public`, session berbasis file). Mode Vercel aktif otomatis karena Vercel menyetel variabel `VERCEL=1`.
- Uji mode Vercel di laptop:

  ```bash
  APP_BASE_URL= SESSION_DRIVER=database php -S 127.0.0.1:8090 api/index.php
  ```
