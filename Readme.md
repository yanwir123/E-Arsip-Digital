# E-Arsip — Sistem Arsip Digital

E-Arsip adalah aplikasi web manajemen arsip digital berbasis **PHP murni (vanilla)** dengan pola arsitektur **MVC sederhana** tanpa framework. Aplikasi mendukung unggah arsip, validasi oleh admin, pengaturan izin akses per pengguna, verifikasi identitas, verifikasi email berbasis OTP, sistem sanksi (blokir) akibat pelanggaran, serta audit log _append-only_.

> Tema antarmuka mengikuti gaya **"Slush"** (lihat `DESIGN.md`): bersih, kartu putih, aksen lembut, dan animasi _reveal_ halus.

---

## Daftar Isi

1. [Fitur Utama](#fitur-utama)
2. [Peran & Hak Akses (RBAC)](#peran--hak-akses-rbac)
3. [Teknologi & Kebutuhan Sistem](#teknologi--kebutuhan-sistem)
4. [Struktur Direktori](#struktur-direktori)
5. [Instalasi](#instalasi)
6. [Konfigurasi `.env`](#konfigurasi-env)
7. [Database](#database)
8. [Daftar Route](#daftar-route)
9. [Keamanan](#keamanan)
10. [Pengujian & Utilitas](#pengujian--utilitas)
11. [Audit & Rekomendasi](AUDIT.md)
12. [Troubleshooting](#troubleshooting)

---

## Fitur Utama

- **Autentikasi & Registrasi**
  - Login berbasis sesi dengan **rate limit per-IP dan per-akun** (tersimpan di
    database, tahan ganti browser/sesi) + lockout sementara.
  - **Kebijakan password**: minimal 8 karakter, wajib huruf + angka, menolak
    password umum dan password yang sama dengan username.
  - Registrasi akun dengan **verifikasi email via kode OTP 6 digit** (juga tersedia tautan verifikasi token).
  - **Throttling OTP**: jeda minimum, batas per-email dan per-IP per jam.
  - Akun seed dapat **dipaksa ganti password** saat login pertama (produksi).
- **Manajemen Arsip**
  - Unggah arsip dengan judul, kode, deskripsi, dan berkas (PDF/DOC/DOCX/XLS/XLSX/PPT/PPTX/JPG/JPEG/PNG/WEBP/ZIP/TXT).
  - Alur status: `pending` → `approved` / `rejected`, lengkap dengan catatan penolakan.
  - Pratinjau dan unduh berkas (dengan pemeriksaan hak akses).
  - Pengaturan izin akses **per pengguna per arsip**.
- **Validasi & Risiko Konten**
  - Admin menentukan tingkat risiko saat validasi: `hijau` (aman), `kuning` (perhatian), `merah` (melanggar).
  - Arsip berisiko `merah` dapat memicu sanksi bagi pengunggah.
- **Verifikasi Identitas**
  - Pengguna melengkapi biodata (instansi, nomor induk, NIP, jabatan, telepon, alamat, foto profil).
  - Status identitas: `belum`, `menunggu`, `terverifikasi`, `ditolak` (beserta alasan).
- **Sanksi / Blokir**
  - Tingkat sanksi: `basic` (ringan), `sedang`, `berat`; durasi dalam hari (0 = permanen).
  - Halaman khusus untuk membuka blokir.
- **Audit Log**
  - Mencatat aktivitas penting (login, unggah, validasi, perubahan akses, dsb.) secara _append-only_.
  - Filter berdasarkan kata kunci dan jenis aksi.
- **Manajemen Pengguna**
  - Khusus developer: tambah/ubah/hapus pengguna, atur role dan status.
- **Penghapusan Otomatis**
  - Arsip `pending` yang melewati batas waktu akan dihapus otomatis oleh skrip terjadwal.

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

- `auth_user()` — pengguna yang sedang login (dengan cache per-request).
- `has_role(...$roles)` — developer selalu lolos.
- `is_developer()`, `is_admin()`, `can_manage_arsip()`, `can_access_arsip()`.
- Logika blokir/`logout` bila akun nonaktif atau sedang diblokir.

---

## Teknologi & Kebutuhan Sistem

- **PHP**: 8.1+ (dikembangkan & diuji pada **PHP 8.5**). Autoload **PSR-4** (`App\` → `app/`), tanpa Composer.
- **Database**: **MySQL 8 / MariaDB**. Driver `pdo_mysql`.
- **Web server**: Apache (dengan `.htaccess`) atau PHP built-in server untuk pengembangan.
- **Ekstensi PHP yang disarankan**:
  - Wajib: `pdo_mysql`, `openssl`, `json`, `session`.
  - **Sangat disarankan**: `fileinfo` (deteksi MIME paling akurat), `mbstring` (penanganan teks multibyte), `curl` (dipakai `database/smoke_test.php`).
  - Aplikasi tetap **aman** bila `fileinfo` nonaktif: validasi MIME otomatis memakai cadangan deteksi _magic bytes_.
- **Frontend**: HTML + CSS + JS murni (tanpa build tool). Aset di `public/assets/`.

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
│   └── assets/                  # css/app.css, js/app.js
├── scripts/
│   └── cleanup.php              # Bersihkan pending kedaluwarsa, ban, OTP, berkas yatim (CLI)
├── storage/
│   ├── uploads/                 # Berkas arsip (DI LUAR web root)
│   ├── logs/                    # Log (mis. mail.log)
│   └── ...
├── .env.example                 # Contoh konfigurasi
├── AUDIT.md                     # Audit keamanan & roadmap peningkatan
├── DESIGN.md                    # Referensi gaya desain
└── Readme.md                    # Dokumen ini
```

> **PENTING:** _Document root_ web server **harus** diarahkan ke folder `public/`, bukan ke root proyek. Jika tidak, berkas sensitif seperti `.env`, `app/`, `database/`, dan `storage/` berpotensi dapat diakses langsung.

---

## Instalasi

### 1. Prasyarat

Pastikan PHP dan MySQL terpasang. Aktifkan ekstensi berikut di `php.ini`:

```ini
extension=pdo_mysql
extension=openssl
extension=fileinfo
extension=mbstring
extension=curl
```

### 2. Clone / Salin Proyek

Letakkan proyek di direktori kerja, mis. `E-Arsip/`.

### 3. Salin Konfigurasi

```powershell
Copy-Item .env.example .env
```

Lalu sunting `.env` (lihat bagian [Konfigurasi `.env`](#konfigurasi-env)).

### 4. Buat Database & Impor Skema

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS E-Arsip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p E-Arsip < database/schema_tables.sql
```

### 5. Jalankan Migrasi (opsional, hanya untuk database lama)

Bila database sudah ada dari versi sebelumnya (tidak impor `schema_tables.sql`):

```powershell
php database/migrate_otp.php
php database/migrate_profil.php
php database/migrate_risk_ban.php
php database/migrate_security.php
```

### 6. Isi Data Awal (Seeder)

```powershell
php database/seed.php
```

Seeder membuat akun awal (password default — **segera ganti di produksi**).

### 7. Jalankan Server

**Pengembangan (PHP built-in server):**

```powershell
php -S localhost:8000 -t public public/router.php
```

Buka `http://localhost:8000`.

**Produksi (Apache):** arahkan `DocumentRoot` ke `.../E-Arsip/public` dan pastikan `AllowOverride All` agar `.htaccess` aktif. Berkas `.env` berada di luar web root sehingga tidak dapat diakses publik.

### 8. Penjadwalan Pembersihan (cron / Task Scheduler)

Jalankan `scripts/cleanup.php` secara berkala, mis. tiap jam:

```
php scripts/cleanup.php
```

Skrip ini menghapus arsip `pending` yang melewati `PENDING_AUTO_DELETE_HOURS`,
menutup blokir yang berakhir, menghapus kode OTP kedaluwarsa, memangkas catatan
rate limit lama, dan membersihkan berkas yatim di `storage/uploads`.

---

## Konfigurasi `.env`

| Kunci                                                            | Default                 | Keterangan                                                                             |
| ---------------------------------------------------------------- | ----------------------- | -------------------------------------------------------------------------------------- |
| `APP_NAME`                                                       | `E-Arsip`               | Nama aplikasi (tampil di UI).                                                          |
| `APP_ENV`                                                        | `local`                 | Lingkungan aplikasi.                                                                   |
| `APP_DEBUG`                                                      | `true`                  | **Set `false` di produksi** agar pesan error tidak bocor.                              |
| `APP_URL`                                                        | `http://localhost:8000` | Base URL untuk pembuatan tautan.                                                       |
| `DATABASE_URL`                                                   | –                       | URL koneksi tunggal `mysql://user:pass@host:port/nama_db`. Bila diisi, menimpa `DB_*`. |
| `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS`                | –                       | Koneksi terpisah (dipakai bila `DATABASE_URL` kosong).                                 |
| `APP_KEY`                                                        | –                       | Kunci HMAC untuk **token CSRF**. **Wajib acak ≥ 32 karakter.**                         |
| `UPLOAD_MAX_SIZE`                                                | `10485760`              | Batas ukuran unggah arsip (byte). 10 MB default.                                       |
| `LOGIN_MAX_ATTEMPTS`                                             | `5`                     | Maksimum percobaan login gagal **per-IP & per-akun** sebelum dikunci.                  |
| `LOGIN_LOCKOUT_SECONDS`                                          | `300`                   | Jendela waktu sekaligus durasi kunci sementara.                                        |
| `PASSWORD_MIN_LENGTH`                                            | `8`                     | Panjang minimum password (wajib huruf + angka).                                        |
| `OTP_SEND_MAX_PER_EMAIL`                                         | `5`                     | Maksimum kirim OTP per email per jam.                                                  |
| `OTP_SEND_MAX_PER_IP`                                            | `10`                    | Maksimum kirim OTP per IP per jam.                                                     |
| `OTP_SEND_COOLDOWN_SECONDS`                                      | `60`                    | Jeda minimum antar-permintaan OTP.                                                     |
| `TRUSTED_PROXIES`                                                | _(kosong)_              | Daftar IP proxy tepercaya (dipisah koma). Kosong = `X-Forwarded-For` diabaikan.        |
| `MAIL_DRIVER`                                                    | `log`\*                 | `smtp` \| `mail` \| `log`. `log` menyimpan ke `storage/logs/mail.log`.                 |
| `SMTP_HOST`/`SMTP_PORT`/`SMTP_USER`/`SMTP_PASS`/`SMTP_FROM_NAME` | –                       | Konfigurasi SMTP.                                                                      |
| `SECRET_KEY`                                                     | –                       | Kunci JWT (verifikasi email). **Gunakan acak & panjang.**                              |
| `ALGORITHM`                                                      | `HS256`                 | Algoritma JWT.                                                                         |
| `ACCESS_TOKEN_EXPIRE_MINUTES`                                    | `60`                    | Masa berlaku token JWT.                                                                |
| `OTP_EXPIRY_SECONDS`                                             | `600`                   | Masa berlaku OTP.                                                                      |
| `OTP_MAX_ATTEMPTS`                                               | `5`                     | Maksimum percobaan OTP.                                                                |
| `PENDING_AUTO_DELETE_HOURS`                                      | `24`                    | Umur arsip pending sebelum dihapus otomatis.                                           |
| `PENALTY_BASIC_DAYS`/`PENALTY_SEDANG_DAYS`/`PENALTY_BERAT_DAYS`  | `1`/`7`/`30`            | Durasi blokir per tingkat (0 = permanen).                                              |

`*` Jika `SMTP_HOST` diisi dan `MAIL_DRIVER` kosong, driver otomatis menjadi `smtp`.

> Menjalankan aplikasi tanpa `.env` akan memakai nilai default, **kecuali**
> `APP_KEY`/`SECRET_KEY`: aplikasi **menolak start** bila keduanya lemah/kosong
> (lihat [Keamanan](#keamanan)).

---

## Database

### Tabel Utama

- `users` — akun, role, status, biodata, `identity_status`, `last_login`, `foto_profil`, `must_change_password`.
- `arsip` — metadata arsip (`judul`, `kode`, `deskripsi`, `file_path`, `file_tipe`, `file_ukuran`, `status`, `catatan_validasi`, `risk`, `uploader_id`).
- `arsip_akses` — izin akses per pengguna per arsip (`arsip_id`, `user_id`, `diberikan_oleh`).
- `otp_codes` — kode OTP registrasi (`email`, `kode`, `expires_at`, `attempts`).
- `otp_throttle` — riwayat/penghitung pengiriman OTP (rate limit kirim ulang).
- `login_attempts` — percobaan login (`ip`, `username`, `success`, `attempted_at`) untuk rate limit.
- `user_bans` — blokir (`user_id`, `tingkat`, `alasan`, `banned_at`, `banned_until`).
- `audit_log` — log aktivitas (`user_id`, `aksi`, `target_tabel`, `target_id`, `keterangan`, `ip`, `created_at`).
- `instansi` — master instansi.

### Aturan Penting

- Berkas arsip disimpan di `storage/uploads/YYYY/MM/<hash>.<ext>` (**di luar web root**), diakses via controller (bukan URL statis).
- Nama berkas diacak (hash) sehingga tidak dapat ditebak.
- Nama tabel seragam _lowercase_ agar portabel lintas OS.

---

## Daftar Route

Semua route didefinisikan di `public/index.php`.

### Publik / Autentikasi

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

### Dashboard & Profil (butuh login)

| Metode | Path               | Aksi                             |
| ------ | ------------------ | -------------------------------- |
| GET    | `/dashboard`       | Dashboard                        |
| GET    | `/profil`          | Halaman profil                   |
| POST   | `/profil`          | Perbarui biodata (termasuk foto) |
| POST   | `/profil/password` | Ubah password                    |
| POST   | `/profil/submit`   | Ajukan verifikasi identitas      |
| GET    | `/foto/{id}`       | Tampilkan foto profil            |

### Arsip

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

### Panel Admin

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

### Manajemen Pengguna (developer) & Audit

| Metode | Path                 | Aksi              |
| ------ | -------------------- | ----------------- |
| GET    | `/users`             | Daftar pengguna   |
| GET    | `/users/create`      | Form tambah       |
| POST   | `/users`             | Simpan pengguna   |
| GET    | `/users/{id}/edit`   | Form edit         |
| POST   | `/users/{id}`        | Perbarui pengguna |
| POST   | `/users/{id}/delete` | Hapus pengguna    |
| GET    | `/audit`             | Log aktivitas     |

---

## Keamanan

E-Arsip telah menerapkan beberapa lapisan keamanan:

- **CSRF**: `csrf_token()` / `csrf_field()` / `verify_csrf()` berbasis HMAC `APP_KEY`. Semua form POST memakai token.
- **XSS**: Semua output dinamis di-escape melalui helper `e()`.
- **SQL Injection**: Seluruh query memakai _prepared statement_ (PDO).
- **Password**: Disimpan dengan `password_hash()` (bcrypt), diverifikasi dengan `password_verify()`. Kebijakan minimum: 8 karakter, wajib huruf + angka, menolak password umum/sama dengan username (`app/helpers/password.php`).
- **Rate limit login**: berbasis **database** (`login_attempts`), dihitung per-IP **dan** per-akun sehingga tidak bisa dilewati dengan menghapus cookie/sesi. Akun seed dipaksa ganti password saat login pertama di produksi.
- **Throttling OTP**: tabel `otp_throttle` membatasi jeda, jumlah per-email/jam, dan jumlah per-IP/jam untuk mencegah _email bombing_.
- **Alamat IP tepercaya**: `client_ip()` hanya memakai `X-Forwarded-For` bila berasal dari `TRUSTED_PROXIES`; jika tidak, `REMOTE_ADDR` apa adanya (anti-spoofing audit log & rate limit).
- **Validasi unggahan**: Daftar putih ekstensi + **verifikasi MIME dari isi berkas** (`detect_mime_type()`) — memakai `finfo` (bila aktif), lalu _magic bytes_ sebagai cadangan. **Tipe yang dilaporkan klien (`$_FILES['type']`) TIDAK pernah dipercaya**; berkas yang tipenya tak terverifikasi ditolak. Nama berkas diacak dan disimpan **di luar web root**.
- **Kunci rahasia**: `APP_KEY` & `SECRET_KEY` wajib acak ≥ 32 karakter. Aplikasi **menolak berjalan** bila kunci masih placeholder/lemah (fail-fast di `app/config/config.php`).
- **Header keamanan**: `X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Referrer-Policy`, dan CSP (lihat `app/bootstrap.php`).
- **Proteksi berkas sensitif**: `.htaccess` di root proyek menolak seluruh akses (jaring pengaman bila `DocumentRoot` salah), `public/.htaccess` memblokir berkas tersembunyi & sensitif (`.env`, `.log`, `.sql`, `.bak`, dll.), dan `storage/.htaccess` menolak semua akses langsung ke berkas arsip/foto.
- **Eksekusi di folder upload**: diblokir oleh `.htaccess`.
- **Blokir pengguna**: akun yang diblokir/nonaktif otomatis dipaksa logout.
- **Audit log**: catatan aktivitas yang bersifat _append-only_ (hanya admin/developer yang dapat melihat; petugas & pengguna biasa 403).

---

## Pengujian & Utilitas

### Uji Asap (Smoke Test)

Menjalankan skenario end-to-end via HTTP (registrasi, login, unggah, validasi, izin akses, dsb.):

```powershell
php -S localhost:8000 -t public public/router.php   # terminal 1
php database/smoke_test.php http://localhost:8000   # terminal 2
```

> Ekstensi `curl` dibutuhkan (aktifkan `extension=curl` bila belum). Disarankan menjalankan uji pada **database terpisah** (mis. `earsip_smoke`) agar data produksi tidak terpengaruh.

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

Hasil audit kesiapan produksi (temuan, perbaikan yang sudah diterapkan, catatan
keamanan yang sudah terpenuhi, serta **roadmap** prioritas) dipindahkan ke
**[AUDIT.md](AUDIT.md)** agar README ini tetap ringkas.

**Ringkasan terkini:**

- Uji asap end-to-end: **28 PASS / 0 FAIL**; seluruh berkas lolos `php -l`.
- Sudah diperbaiki pada pass terakhir: RBAC `/audit` (user → 403), rate limit
  login berbasis DB (per-IP & per-akun), throttling OTP, kebijakan password,
  anti-spoofing `client_ip()`, pembersihan berkas yatim, dan migrasi keamanan.
- **Sebelum deploy:** set `APP_DEBUG=false` & `APP_ENV=production`, putar
  password akun seed, dan siapkan backup.

---

## Troubleshooting

| Gejala                                                          | Penyebab & Solusi                                                                                                                    |
| --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `Call to undefined function curl_init()` saat smoke test        | Ekstensi `curl` mati. Aktifkan `extension=curl` di `php.ini`.                                                                        |
| Unggahan gambar/dokumen valid ditolak, atau MIME tak terdeteksi | Aktifkan `extension=fileinfo` (deteksi MIME lebih akurat). Aplikasi tetap aman walau tanpa `fileinfo` berkat cadangan _magic bytes_. |
| Aplikasi gagal start: "APP_KEY/SECRET_KEY lemah"                | Isi `APP_KEY` & `SECRET_KEY` dengan string acak ≥ 32 karakter, mis. `php -r "echo bin2hex(random_bytes(32));"`.                      |
| Halaman CSS/JS tidak termuat / rute 404                         | Pastikan _document root_ web server diarahkan ke folder `public/`, dan `AllowOverride All` aktif (Apache).                           |
| `.env` dapat dibuka di browser                                  | _Document root_ salah (menunjuk ke root proyek, bukan `public/`). Segera perbaiki.                                                   |
| Login selalu gagal setelah beberapa percobaan                   | Rate limit **berbasis database** aktif (per-IP & per-akun). Tunggu `LOGIN_LOCKOUT_SECONDS`, atau kosongkan tabel `login_attempts`.   |
| IP di audit log salah saat di belakang proxy/CDN                | Set `TRUSTED_PROXIES` dengan IP proxy (dipisah koma). Jika dibiarkan kosong, `X-Forwarded-For` sengaja diabaikan.                    |
| OTP gagal dikirim (pesan sibuk)                                 | Throttling OTP aktif. Tunggu `OTP_SEND_COOLDOWN_SECONDS`; batasnya `OTP_SEND_MAX_PER_EMAIL`/`OTP_SEND_MAX_PER_IP` per jam.           |
| Email OTP tidak terkirim                                        | Periksa `MAIL_DRIVER`. Gunakan `log` untuk menguji (kode tersimpan di `storage/logs/mail.log`) atau lengkapi kredensial SMTP.        |
| Data `pending` hilang sendiri                                   | Skrip `scripts/cleanup.php` menghapus arsip pending kedaluwarsa (`PENDING_AUTO_DELETE_HOURS`).                                       |
| Tabel tidak lengkap / error kolom                               | Pastikan `schema_tables.sql` diimpor, lalu jalankan seluruh skrip di `database/migrate_*.php`.                                       |

---
