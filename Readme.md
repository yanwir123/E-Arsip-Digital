<div align="center">

# 🗂️ E-Arsip — Sistem Arsip Digital

**Kelola arsip digital dengan aman & rapi.**
Unggah, validasi, dan atur izin akses dokumen dalam satu tempat.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8%20%2F%20MariaDB-4479A1?logo=mysql&logoColor=white)
![Arsitektur](https://img.shields.io/badge/Arsitektur-MVC%20Vanilla-black)
![Smoke Test](https://img.shields.io/badge/Smoke%20Test-28%20PASS%20%2F%200%20FAIL-brightgreen)

</div>

E-Arsip adalah aplikasi web manajemen arsip digital berbasis **PHP murni (vanilla)** dengan pola **MVC sederhana** tanpa framework. Aplikasi mendukung unggah arsip, validasi oleh admin, pengaturan izin akses per pengguna, verifikasi identitas, verifikasi email berbasis OTP, sistem sanksi (blokir), serta audit log _append-only_.

> Tema antarmuka mengikuti gaya **"Slush"** (lihat `DESIGN.md`): bersih, kartu putih, aksen lembut, dan animasi _reveal_ halus.

---

## Daftar Isi

1. [Tampilan Aplikasi](#tampilan-aplikasi)
2. [Fitur Utama](#fitur-utama)
3. [Peran & Hak Akses (RBAC)](#peran--hak-akses-rbac)
4. [Teknologi & Kebutuhan Sistem](#teknologi--kebutuhan-sistem)
5. [Struktur Direktori](#struktur-direktori)
6. [Instalasi](#instalasi)
7. [Konfigurasi `.env`](#konfigurasi-env)
8. [Database](#database)
9. [Daftar Route](#daftar-route)
10. [Keamanan](#keamanan)
11. [Pengujian & Utilitas](#pengujian--utilitas)
12. [Audit & Rekomendasi](#audit--rekomendasi)
13. [Troubleshooting](#troubleshooting)

---

## Tampilan Aplikasi

### Halaman Login

Halaman masuk dengan penjelasan singkat fitur di sisi kiri, kolom username/password, serta verifikasi reCAPTCHA.

<p align="center">
  <img src="public/assets/img/01-login.png" alt="Halaman Login E-Arsip" width="820">
</p>

### Dashboard

Ringkasan jumlah arsip **Menunggu Validasi**, **Disetujui**, dan **Ditolak**, disertai daftar arsip terbaru.

<p align="center">
  <img src="public/assets/img/02-dashboard.png" alt="Dashboard E-Arsip" width="820">
</p>

### Daftar Arsip

Pencarian berdasarkan judul/kode, filter status, serta label **status** dan **risiko** (Aman / Perhatian / Melanggar) pada setiap arsip.

<p align="center">
  <img src="public/assets/img/03-daftar-arsip.png" alt="Daftar Arsip E-Arsip" width="820">
</p>

### Unggah Arsip

Formulir unggah dengan kode (opsional), judul, kategori, deskripsi, dan berkas (maks. 10 MB). Arsip berstatus _Menunggu Validasi_ hingga disetujui admin.

<p align="center">
  <img src="public/assets/img/05-unggah-arsip.png" alt="Form Unggah Arsip E-Arsip" width="820">
</p>

### Manajemen Pengguna _(khusus developer)_

Kelola akun, role, dan status pengguna, lengkap dengan informasi login terakhir.

<p align="center">
  <img src="public/assets/img/04-manajemen-pengguna.png" alt="Manajemen Pengguna E-Arsip" width="820">
</p>

---

## Fitur Utama

### 🔐 Autentikasi & Registrasi
- Login berbasis sesi dengan **rate limit per-IP dan per-akun** (tersimpan di database, tahan ganti browser/sesi) + lockout sementara.
- **Kebijakan password**: minimal 8 karakter, wajib huruf + angka, menolak password umum dan password yang sama dengan username.
- Registrasi dengan **verifikasi email via kode OTP 6 digit** (tersedia juga tautan verifikasi token).
- **Throttling OTP**: jeda minimum, batas per-email dan per-IP per jam.
- Akun seed dapat **dipaksa ganti password** saat login pertama (produksi).

### 📁 Manajemen Arsip
- Unggah arsip dengan judul, kode, kategori, deskripsi, dan berkas (PDF/DOC/DOCX/XLS/XLSX/PPT/PPTX/JPG/JPEG/PNG/WEBP/ZIP/TXT).
- Alur status: `pending` → `approved` / `rejected`, lengkap dengan catatan penolakan.
- Pratinjau dan unduh berkas (dengan pemeriksaan hak akses).
- Pengaturan izin akses **per pengguna per arsip**.

### ✅ Validasi & Risiko Konten
- Admin menentukan tingkat risiko saat validasi: `hijau` (aman), `kuning` (perhatian), `merah` (melanggar).
- Arsip berisiko `merah` dapat memicu sanksi bagi pengunggah.

### 🪪 Verifikasi Identitas
- Pengguna melengkapi biodata (instansi, nomor induk, NIP, jabatan, telepon, alamat, foto profil).
- Status identitas: `belum`, `menunggu`, `terverifikasi`, `ditolak` (beserta alasan).

### 🚫 Sanksi / Blokir
- Tingkat sanksi: `basic` (ringan), `sedang`, `berat`; durasi dalam hari (0 = permanen).
- Halaman khusus untuk membuka blokir.

### 📜 Audit Log
- Mencatat aktivitas penting (login, unggah, validasi, perubahan akses, dsb.) secara _append-only_.
- Filter berdasarkan kata kunci dan jenis aksi.

### 👥 Manajemen Pengguna & Pembersihan Otomatis
- Khusus developer: tambah/ubah/hapus pengguna, atur role dan status.
- Arsip `pending` yang melewati batas waktu dihapus otomatis oleh skrip terjadwal.

---

## Peran & Hak Akses (RBAC)

Definisi role ada di `app/config/config.php` (`ROLE_*`).

| Role        | Label         | Kemampuan Inti                                                                                  |
| ----------- | ------------- | ----------------------------------------------------------------------------------------------- |
| `developer` | Pengembang    | Akses penuh ke seluruh sistem; **melewati semua pemeriksaan** `has_role()`. Manajemen pengguna. |
| `admin`     | Administrator | Validasi arsip, atur izin akses, verifikasi identitas, kelola blokir, lihat audit log.          |
| `petugas`   | Petugas       | Mengunggah & mengelola arsip, membantu validasi/identitas (sesuai rancangan).                   |
| `user`      | Pengguna      | Mengunggah arsip dan membaca arsip yang diizinkan untuk dirinya.                                |

Pemeriksaan hak akses utama ada di `app/helpers/auth.php`:

- `auth_user()` — pengguna yang sedang login (cache per-request).
- `has_role(...$roles)` — developer selalu lolos.
- `is_developer()`, `is_admin()`, `can_manage_arsip()`, `can_access_arsip()`.
- Logika blokir/logout otomatis bila akun nonaktif atau sedang diblokir.

---

## Teknologi & Kebutuhan Sistem

| Komponen   | Keterangan                                                                                    |
| ---------- | --------------------------------------------------------------------------------------------- |
| PHP        | 8.1+ (dikembangkan & diuji pada **PHP 8.5**). Autoload **PSR-4** (`App\` → `app/`), tanpa Composer. |
| Database   | **MySQL 8 / MariaDB** dengan driver `pdo_mysql`.                                              |
| Web server | Apache (dengan `.htaccess`) atau PHP built-in server untuk pengembangan.                      |
| Frontend   | HTML + CSS + JS murni (tanpa build tool). Aset di `public/assets/`.                           |

**Ekstensi PHP**

- **Wajib**: `pdo_mysql`, `openssl`, `json`, `session`.
- **Sangat disarankan**: `fileinfo` (deteksi MIME paling akurat), `mbstring` (teks multibyte), `curl` (dipakai `database/smoke_test.php`).
- Aplikasi tetap **aman** bila `fileinfo` nonaktif: validasi MIME otomatis memakai cadangan deteksi _magic bytes_.

---

## Struktur Direktori

```
E-Arsip/
├── app/
│   ├── bootstrap.php            # Bootstrap: sesi, helper, konstanta, error handling
│   ├── config/config.php        # Loader .env + seluruh konstanta aplikasi
│   ├── core/                    # Router, Database, Controller, Mailer
│   ├── controllers/             # Auth, Arsip, Admin, User, Profile, Dashboard, Audit
│   ├── models/                  # User, Arsip, Otp, Ban, AuditLog, Instansi, LoginAttempt
│   ├── helpers/                 # helpers, auth, password, csrf, jwt, mail, audit
│   └── views/                   # Layout, partials, halaman (auth, arsip, admin, dll.)
├── database/
│   ├── schema_tables.sql        # Skema tabel (impor ini dulu)
│   ├── migrate_otp.php          # Migrasi tabel OTP
│   ├── migrate_profil.php       # Migrasi kolom profil/biodata
│   ├── migrate_risk_ban.php     # Migrasi risiko & sanksi/blokir
│   ├── migrate_security.php     # Migrasi kolom/tabel keamanan (rate limit, dll.)
│   ├── seed.php                 # Seeder akun awal
│   └── smoke_test.php           # Uji asap (end-to-end via HTTP)
├── public/                      # DOCUMENT ROOT (arahkan web server ke sini)
│   ├── index.php                # Front controller + definisi route
│   ├── router.php               # Router untuk PHP built-in server
│   ├── .htaccess                # Front-controller + blokir eksekusi PHP di upload
│   └── assets/
│       ├── css/app.css          # Gaya antarmuka
│       ├── js/app.js            # Skrip antarmuka
│       └── img/                 # Gambar & tangkapan layar (dipakai di README)
├── scripts/
│   └── cleanup.php              # Bersihkan pending kedaluwarsa, ban, OTP, berkas yatim (CLI)
├── storage/
│   ├── uploads/                 # Berkas arsip (DI LUAR web root)
│   └── logs/                    # Log (mis. mail.log)
├── .env.example                 # Contoh konfigurasi
├── AUDIT.md                     # Audit keamanan & roadmap peningkatan
├── DESIGN.md                    # Referensi gaya desain
└── Readme.md                    # Dokumen ini
```

> ⚠️ **PENTING:** _Document root_ web server **harus** diarahkan ke folder `public/`, bukan ke root proyek. Jika tidak, berkas sensitif seperti `.env`, `app/`, `database/`, dan `storage/` berpotensi dapat diakses langsung.

---

## Instalasi

### 1. Prasyarat

Pastikan PHP dan MySQL terpasang, lalu aktifkan ekstensi berikut di `php.ini`:

```ini
extension=pdo_mysql
extension=openssl
extension=fileinfo
extension=mbstring
extension=curl
```

### 2. Salin Proyek & Konfigurasi

Letakkan proyek di direktori kerja (mis. `E-Arsip/`), lalu salin konfigurasi:

```powershell
Copy-Item .env.example .env
```

Sunting `.env` (lihat [Konfigurasi `.env`](#konfigurasi-env)).

### 3. Buat Database & Impor Skema

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS E-Arsip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p E-Arsip < database/schema_tables.sql
```

### 4. Jalankan Migrasi _(opsional — hanya untuk database lama)_

Gunakan bila database berasal dari versi sebelumnya (tidak mengimpor `schema_tables.sql`):

```powershell
php database/migrate_otp.php
php database/migrate_profil.php
php database/migrate_risk_ban.php
php database/migrate_security.php
```

### 5. Isi Data Awal (Seeder)

```powershell
php database/seed.php
```

Seeder membuat akun awal dengan password default — **segera ganti di produksi**.

### 6. Jalankan Server

**Pengembangan (PHP built-in server):**

```powershell
php -S localhost:8000 -t public public/router.php
```

Buka <http://localhost:8000>.

**Produksi (Apache):** arahkan `DocumentRoot` ke `.../E-Arsip/public` dan pastikan `AllowOverride All` aktif agar `.htaccess` berfungsi. Berkas `.env` berada di luar web root sehingga tidak dapat diakses publik.

### 7. Jadwalkan Pembersihan (cron / Task Scheduler)

Jalankan secara berkala, mis. tiap jam:

```
php scripts/cleanup.php
```

Skrip ini menghapus arsip `pending` yang melewati `PENDING_AUTO_DELETE_HOURS`, menutup blokir yang berakhir, menghapus OTP kedaluwarsa, memangkas catatan rate limit lama, dan membersihkan berkas yatim di `storage/uploads`.

---

## Konfigurasi `.env`

### Aplikasi

| Kunci       | Default                 | Keterangan                                                |
| ----------- | ----------------------- | --------------------------------------------------------- |
| `APP_NAME`  | `E-Arsip`               | Nama aplikasi (tampil di UI).                             |
| `APP_ENV`   | `local`                 | Lingkungan aplikasi.                                      |
| `APP_DEBUG` | `true`                  | **Set `false` di produksi** agar pesan error tidak bocor. |
| `APP_URL`   | `http://localhost:8000` | Base URL untuk pembuatan tautan.                          |
| `APP_KEY`   | –                       | Kunci HMAC token CSRF. **Wajib acak ≥ 32 karakter.**      |

### Database

| Kunci                                             | Default | Keterangan                                                                             |
| ------------------------------------------------- | ------- | -------------------------------------------------------------------------------------- |
| `DATABASE_URL`                                    | –       | URL koneksi tunggal `mysql://user:pass@host:port/nama_db`. Bila diisi, menimpa `DB_*`. |
| `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS` | –       | Koneksi terpisah (dipakai bila `DATABASE_URL` kosong).                                 |

### Keamanan & Rate Limit

| Kunci                         | Default    | Keterangan                                                                      |
| ----------------------------- | ---------- | ------------------------------------------------------------------------------- |
| `LOGIN_MAX_ATTEMPTS`          | `5`        | Maks. percobaan login gagal **per-IP & per-akun** sebelum dikunci.              |
| `LOGIN_LOCKOUT_SECONDS`       | `300`      | Jendela waktu sekaligus durasi kunci sementara.                                 |
| `PASSWORD_MIN_LENGTH`         | `8`        | Panjang minimum password (wajib huruf + angka).                                 |
| `TRUSTED_PROXIES`             | _(kosong)_ | IP proxy tepercaya (dipisah koma). Kosong = `X-Forwarded-For` diabaikan.        |
| `SECRET_KEY`                  | –          | Kunci JWT (verifikasi email). **Gunakan acak & panjang.**                       |
| `ALGORITHM`                   | `HS256`    | Algoritma JWT.                                                                  |
| `ACCESS_TOKEN_EXPIRE_MINUTES` | `60`       | Masa berlaku token JWT.                                                         |

### OTP & Email

| Kunci                                                            | Default | Keterangan                                                             |
| ---------------------------------------------------------------- | ------- | ---------------------------------------------------------------------- |
| `OTP_EXPIRY_SECONDS`                                             | `600`   | Masa berlaku OTP.                                                      |
| `OTP_MAX_ATTEMPTS`                                               | `5`     | Maks. percobaan memasukkan OTP.                                        |
| `OTP_SEND_MAX_PER_EMAIL`                                         | `5`     | Maks. kirim OTP per email per jam.                                     |
| `OTP_SEND_MAX_PER_IP`                                            | `10`    | Maks. kirim OTP per IP per jam.                                        |
| `OTP_SEND_COOLDOWN_SECONDS`                                      | `60`    | Jeda minimum antar-permintaan OTP.                                     |
| `MAIL_DRIVER`                                                    | `log`\* | `smtp` \| `mail` \| `log`. `log` menyimpan ke `storage/logs/mail.log`. |
| `SMTP_HOST`/`SMTP_PORT`/`SMTP_USER`/`SMTP_PASS`/`SMTP_FROM_NAME` | –       | Konfigurasi SMTP.                                                      |

\* Jika `SMTP_HOST` diisi dan `MAIL_DRIVER` kosong, driver otomatis menjadi `smtp`.

### Arsip & Sanksi

| Kunci                                                           | Default      | Keterangan                                    |
| --------------------------------------------------------------- | ------------ | --------------------------------------------- |
| `UPLOAD_MAX_SIZE`                                               | `10485760`   | Batas ukuran unggah (byte). Default 10 MB.    |
| `PENDING_AUTO_DELETE_HOURS`                                     | `24`         | Umur arsip pending sebelum dihapus otomatis.  |
| `PENALTY_BASIC_DAYS`/`PENALTY_SEDANG_DAYS`/`PENALTY_BERAT_DAYS` | `1`/`7`/`30` | Durasi blokir per tingkat (0 = permanen).     |

> Aplikasi tanpa `.env` memakai nilai default, **kecuali** `APP_KEY`/`SECRET_KEY`: aplikasi **menolak start** bila keduanya lemah/kosong (lihat [Keamanan](#keamanan)).
> Contoh membuat kunci acak: `php -r "echo bin2hex(random_bytes(32));"`

---

## Database

### Tabel Utama

| Tabel            | Fungsi                                                                                                                       |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `users`          | Akun, role, status, biodata, `identity_status`, `last_login`, `foto_profil`, `must_change_password`.                          |
| `arsip`          | Metadata arsip (`judul`, `kode`, `deskripsi`, `file_path`, `file_tipe`, `file_ukuran`, `status`, `catatan_validasi`, `risk`, `uploader_id`). |
| `arsip_akses`    | Izin akses per pengguna per arsip (`arsip_id`, `user_id`, `diberikan_oleh`).                                                 |
| `otp_codes`      | Kode OTP registrasi (`email`, `kode`, `expires_at`, `attempts`).                                                             |
| `otp_throttle`   | Riwayat/penghitung pengiriman OTP (rate limit kirim ulang).                                                                  |
| `login_attempts` | Percobaan login (`ip`, `username`, `success`, `attempted_at`) untuk rate limit.                                              |
| `user_bans`      | Blokir (`user_id`, `tingkat`, `alasan`, `banned_at`, `banned_until`).                                                        |
| `audit_log`      | Log aktivitas (`user_id`, `aksi`, `target_tabel`, `target_id`, `keterangan`, `ip`, `created_at`).                            |
| `instansi`       | Master instansi.                                                                                                             |

### Aturan Penting

- Berkas arsip disimpan di `storage/uploads/YYYY/MM/<hash>.<ext>` (**di luar web root**) dan diakses lewat controller, bukan URL statis.
- Nama berkas diacak (hash) sehingga tidak dapat ditebak.
- Nama tabel seragam _lowercase_ agar portabel lintas OS.

---

## Daftar Route

Semua route didefinisikan di `public/index.php`.

<details open>
<summary><b>Publik / Autentikasi</b></summary>

| Metode | Path                        | Aksi                                        |
| ------ | --------------------------- | ------------------------------------------- |
| GET    | `/`                         | Dashboard (redirect login bila belum masuk) |
| GET    | `/login`                    | Halaman login                               |
| POST   | `/login`                    | Proses login                                |
| POST   | `/logout`                   | Logout (CSRF)                               |
| GET    | `/register`                 | Halaman daftar                              |
| POST   | `/register`                 | Proses daftar (kirim OTP)                   |
| GET    | `/verify-otp`               | Halaman verifikasi OTP                      |
| POST   | `/verify-otp`               | Proses verifikasi OTP                       |
| POST   | `/verify-otp/resend`        | Kirim ulang OTP                             |
| GET    | `/verify-otp/token/{token}` | Verifikasi via tautan token                 |

</details>

<details>
<summary><b>Dashboard & Profil</b> (butuh login)</summary>

| Metode | Path               | Aksi                             |
| ------ | ------------------ | -------------------------------- |
| GET    | `/dashboard`       | Dashboard                        |
| GET    | `/profil`          | Halaman profil                   |
| POST   | `/profil`          | Perbarui biodata (termasuk foto) |
| POST   | `/profil/password` | Ubah password                    |
| POST   | `/profil/submit`   | Ajukan verifikasi identitas      |
| GET    | `/foto/{id}`       | Tampilkan foto profil            |

</details>

<details>
<summary><b>Arsip</b></summary>

| Metode | Path                   | Aksi                         |
| ------ | ---------------------- | ---------------------------- |
| GET    | `/arsip`               | Daftar arsip                 |
| GET    | `/arsip/create`        | Form unggah                  |
| POST   | `/arsip`               | Simpan arsip (unggah berkas) |
| GET    | `/arsip/{id}/edit`     | Form edit                    |
| POST   | `/arsip/{id}`          | Perbarui arsip               |
| GET    | `/arsip/{id}/preview`  | Pratinjau berkas             |
| GET    | `/arsip/{id}/download` | Unduh berkas                 |
| GET    | `/arsip/{id}`          | Detail arsip                 |
| POST   | `/arsip/{id}/delete`   | Hapus arsip                  |

</details>

<details>
<summary><b>Panel Admin</b></summary>

| Metode | Path                            | Aksi                           |
| ------ | ------------------------------- | ------------------------------ |
| GET    | `/admin/validasi`               | Daftar arsip menunggu validasi |
| GET    | `/admin/identitas`              | Daftar pengajuan identitas     |
| POST   | `/admin/identitas/{id}/approve` | Setujui identitas              |
| POST   | `/admin/identitas/{id}/reject`  | Tolak identitas                |
| GET    | `/admin/blokir`                 | Daftar blokir aktif            |
| POST   | `/admin/blokir/{id}/buka`       | Buka blokir                    |
| GET    | `/admin/arsip/{id}/akses`       | Kelola izin akses              |
| POST   | `/admin/arsip/{id}/approve`     | Setujui arsip                  |
| POST   | `/admin/arsip/{id}/reject`      | Tolak arsip                    |
| POST   | `/admin/arsip/{id}/akses`       | Simpan izin akses              |

</details>

<details>
<summary><b>Manajemen Pengguna</b> (developer) & <b>Audit</b></summary>

| Metode | Path                 | Aksi              |
| ------ | -------------------- | ----------------- |
| GET    | `/users`             | Daftar pengguna   |
| GET    | `/users/create`      | Form tambah       |
| POST   | `/users`             | Simpan pengguna   |
| GET    | `/users/{id}/edit`   | Form edit         |
| POST   | `/users/{id}`        | Perbarui pengguna |
| POST   | `/users/{id}/delete` | Hapus pengguna    |
| GET    | `/audit`             | Log aktivitas     |

</details>

---

## Keamanan

E-Arsip menerapkan beberapa lapisan keamanan:

| Lapisan                  | Penjelasan                                                                                                                                                                                  |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **CSRF**                 | `csrf_token()` / `csrf_field()` / `verify_csrf()` berbasis HMAC `APP_KEY`. Semua form POST memakai token.                                                                                   |
| **XSS**                  | Semua output dinamis di-escape melalui helper `e()`.                                                                                                                                        |
| **SQL Injection**        | Seluruh query memakai _prepared statement_ (PDO).                                                                                                                                           |
| **Password**             | `password_hash()` (bcrypt) + `password_verify()`. Minimum 8 karakter, huruf + angka, menolak password umum/sama dengan username (`app/helpers/password.php`).                                |
| **Rate limit login**     | Berbasis **database** (`login_attempts`), per-IP **dan** per-akun sehingga tidak bisa dilewati dengan menghapus cookie/sesi. Akun seed dipaksa ganti password saat login pertama di produksi. |
| **Throttling OTP**       | Tabel `otp_throttle` membatasi jeda, jumlah per-email/jam, dan per-IP/jam untuk mencegah _email bombing_.                                                                                   |
| **IP tepercaya**         | `client_ip()` hanya memakai `X-Forwarded-For` dari `TRUSTED_PROXIES`; selain itu memakai `REMOTE_ADDR` (anti-spoofing audit log & rate limit).                                              |
| **Validasi unggahan**    | Daftar putih ekstensi + **verifikasi MIME dari isi berkas** (`detect_mime_type()`) via `finfo`, lalu _magic bytes_ sebagai cadangan. Tipe dari klien (`$_FILES['type']`) **tidak pernah dipercaya**. Nama berkas diacak dan disimpan **di luar web root**. |
| **Kunci rahasia**        | `APP_KEY` & `SECRET_KEY` wajib acak ≥ 32 karakter. Aplikasi **menolak berjalan** bila kunci placeholder/lemah (fail-fast di `app/config/config.php`).                                      |
| **Header keamanan**      | `X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Referrer-Policy`, dan CSP (lihat `app/bootstrap.php`).                                                                               |
| **Proteksi berkas**      | `.htaccess` root menolak semua akses (jaring pengaman bila `DocumentRoot` salah); `public/.htaccess` memblokir berkas sensitif (`.env`, `.log`, `.sql`, `.bak`, dll.); `storage/.htaccess` menolak akses langsung ke arsip/foto. |
| **Folder upload**        | Eksekusi PHP diblokir oleh `.htaccess`.                                                                                                                                                     |
| **Blokir pengguna**      | Akun yang diblokir/nonaktif otomatis dipaksa logout.                                                                                                                                        |
| **Audit log**            | _Append-only_; hanya admin/developer yang dapat melihat (petugas & pengguna biasa mendapat 403).                                                                                            |

---

## Pengujian & Utilitas

### Uji Asap (Smoke Test)

Menjalankan skenario end-to-end via HTTP (registrasi, login, unggah, validasi, izin akses, dsb.):

```powershell
php -S localhost:8000 -t public public/router.php   # terminal 1
php database/smoke_test.php http://localhost:8000   # terminal 2
```

> Butuh ekstensi `curl`. Disarankan memakai **database terpisah** (mis. `earsip_smoke`) agar data produksi tidak terpengaruh.

### Pembersihan Terjadwal

```powershell
php scripts/cleanup.php
```

### Lint Sintaks

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

---

## Audit & Rekomendasi

Hasil audit kesiapan produksi (temuan, perbaikan, catatan keamanan, dan **roadmap**) ada di **[AUDIT.md](AUDIT.md)**.

**Ringkasan terkini**

- ✅ Uji asap end-to-end: **28 PASS / 0 FAIL**; seluruh berkas lolos `php -l`.
- ✅ Sudah diperbaiki: RBAC `/audit` (user → 403), rate limit login berbasis DB (per-IP & per-akun), throttling OTP, kebijakan password, anti-spoofing `client_ip()`, pembersihan berkas yatim, dan migrasi keamanan.
- ⚠️ **Sebelum deploy:** set `APP_DEBUG=false` & `APP_ENV=production`, putar password akun seed, dan siapkan backup.

---

## Troubleshooting

| Gejala                                                          | Penyebab & Solusi                                                                                                                    |
| --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `Call to undefined function curl_init()` saat smoke test        | Ekstensi `curl` mati. Aktifkan `extension=curl` di `php.ini`.                                                                        |
| Unggahan valid ditolak, atau MIME tak terdeteksi                | Aktifkan `extension=fileinfo` (deteksi lebih akurat). Tanpa `fileinfo`, aplikasi tetap aman berkat cadangan _magic bytes_.           |
| Aplikasi gagal start: "APP_KEY/SECRET_KEY lemah"                | Isi `APP_KEY` & `SECRET_KEY` dengan string acak ≥ 32 karakter, mis. `php -r "echo bin2hex(random_bytes(32));"`.                      |
| CSS/JS tidak termuat / rute 404                                 | Pastikan _document root_ mengarah ke `public/` dan `AllowOverride All` aktif (Apache).                                               |
| `.env` dapat dibuka di browser                                  | _Document root_ salah (menunjuk ke root proyek). Segera perbaiki.                                                                    |
| Login selalu gagal setelah beberapa percobaan                   | Rate limit berbasis database aktif. Tunggu `LOGIN_LOCKOUT_SECONDS`, atau kosongkan tabel `login_attempts`.                           |
| IP di audit log salah saat di belakang proxy/CDN                | Set `TRUSTED_PROXIES` dengan IP proxy (dipisah koma). Bila kosong, `X-Forwarded-For` sengaja diabaikan.                              |
| OTP gagal dikirim (pesan sibuk)                                 | Throttling OTP aktif. Tunggu `OTP_SEND_COOLDOWN_SECONDS`; batas `OTP_SEND_MAX_PER_EMAIL`/`OTP_SEND_MAX_PER_IP` per jam.              |
| Email OTP tidak terkirim                                        | Periksa `MAIL_DRIVER`. Gunakan `log` untuk uji (kode di `storage/logs/mail.log`) atau lengkapi kredensial SMTP.                      |
| Data `pending` hilang sendiri                                   | `scripts/cleanup.php` menghapus arsip pending kedaluwarsa (`PENDING_AUTO_DELETE_HOURS`).                                             |
| Tabel tidak lengkap / error kolom                               | Pastikan `schema_tables.sql` diimpor, lalu jalankan semua `database/migrate_*.php`.                                                  |

---

<div align="center">

**E-Arsip** · Sistem Arsip Digital · © 2026

</div>
