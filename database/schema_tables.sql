-- =============================================================================
-- E-Arsip - Skema Tabel (versi ramah DBeaver)
--
-- PRASYARAT:
--   1. Database `E-Arsip` sudah dibuat (di DBeaver: klik kanan server >
--      Create New Database > isi nama `E-Arsip` > OK).
--   2. Database `E-Arsip` sudah DIPILIH sebagai default schema koneksi
--      (klik kanan `E-Arsip` > Set as Default / Set Active Database).
--   3. Jalankan file ini dengan Execute SQL Script (Alt+X), bukan Ctrl+Enter.
--
-- File ini sengaja TIDAK memakai CREATE DATABASE / USE agar aman dijalankan
-- per-statement di DBeaver.
-- Engine : MySQL / MariaDB (InnoDB, utf8mb4)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS otp_throttle;
DROP TABLE IF EXISTS otp_codes;
DROP TABLE IF EXISTS user_bans;
DROP TABLE IF EXISTS arsip_susun;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS arsip_akses;
DROP TABLE IF EXISTS arsip;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS instansi;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Instansi (master data unit kerja / kedinasan)
-- -----------------------------------------------------------------------------
CREATE TABLE instansi (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode       VARCHAR(20)  NOT NULL,
    nama       VARCHAR(200) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_instansi_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Pengguna
-- role: developer (akses penuh), admin (validasi & izin), petugas, user
-- identity_status: belum | menunggu | terverifikasi | ditolak
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama                   VARCHAR(100) NOT NULL,
    username               VARCHAR(50)  NOT NULL,
    email                  VARCHAR(191) NULL,
    foto_profil            VARCHAR(255) NULL,
    instansi_id            INT UNSIGNED NULL,
    no_induk               VARCHAR(50)  NULL,
    nip                    VARCHAR(30)  NULL,
    jabatan                VARCHAR(100) NULL,
    telepon                VARCHAR(20)  NULL,
    alamat                 VARCHAR(255) NULL,
    email_verified_at      DATETIME NULL,
    password               VARCHAR(255) NOT NULL,
    role                   ENUM('developer','admin','petugas','user') NOT NULL DEFAULT 'user',
    status                 ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    must_change_password   TINYINT(1) NOT NULL DEFAULT 0,
    identity_status        ENUM('belum','menunggu','terverifikasi','ditolak') NOT NULL DEFAULT 'belum',
    identity_verified_at   DATETIME NULL,
    identity_verified_by   INT UNSIGNED NULL,
    identity_reject_reason TEXT NULL,
    last_login             DATETIME NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_instansi (instansi_id),
    KEY idx_users_identity_status (identity_status),
    CONSTRAINT fk_users_instansi FOREIGN KEY (instansi_id) REFERENCES instansi (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Arsip
-- status: pending | approved | rejected
-- risk_level: hijau (aman) | kuning (perhatian) | merah (melanggar)
-- file_path menyimpan lokasi relatif di dalam storage/uploads (bukan nama asli)
-- -----------------------------------------------------------------------------
CREATE TABLE arsip (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode          VARCHAR(50)  NULL,
    judul         VARCHAR(200) NOT NULL,
    deskripsi     TEXT NULL,
    kategori      VARCHAR(100) NULL,
    file_path     VARCHAR(255) NOT NULL,
    file_asli     VARCHAR(255) NOT NULL,
    file_tipe     VARCHAR(100) NOT NULL,
    file_ukuran   INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    risk_level    ENUM('hijau','kuning','merah') NOT NULL DEFAULT 'hijau',
    uploader_id   INT UNSIGNED NOT NULL,
    validator_id  INT UNSIGNED NULL,
    alasan_reject TEXT NULL,
    validated_at  DATETIME NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_arsip_status (status),
    KEY idx_arsip_uploader (uploader_id),
    CONSTRAINT fk_arsip_uploader  FOREIGN KEY (uploader_id)  REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_arsip_validator FOREIGN KEY (validator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Izin akses arsip per pengguna
-- -----------------------------------------------------------------------------
CREATE TABLE arsip_akses (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    arsip_id       INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL,
    diberikan_oleh INT UNSIGNED NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_arsip_user (arsip_id, user_id),
    KEY idx_akses_user (user_id),
    CONSTRAINT fk_akses_arsip FOREIGN KEY (arsip_id) REFERENCES arsip (id) ON DELETE CASCADE,
    CONSTRAINT fk_akses_user  FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_akses_giver FOREIGN KEY (diberikan_oleh) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Audit log (append-only)
-- -----------------------------------------------------------------------------
CREATE TABLE audit_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NULL,
    aksi         VARCHAR(50)  NOT NULL,
    target_tabel VARCHAR(50)  NULL,
    target_id    INT UNSIGNED NULL,
    keterangan   TEXT NULL,
    ip           VARCHAR(45)  NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_user (user_id),
    KEY idx_log_aksi (aksi),
    KEY idx_log_target (target_tabel, target_id),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Kode OTP (verifikasi email saat registrasi)
-- tujuan: registrasi | reset_password
-- -----------------------------------------------------------------------------
CREATE TABLE otp_codes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(191) NOT NULL,
    kode        VARCHAR(10)  NOT NULL,
    tujuan      VARCHAR(20)  NOT NULL DEFAULT 'registrasi',
    data        TEXT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    attempts    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otp_email (email),
    KEY idx_otp_tujuan (tujuan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Throttling pengiriman OTP (rate limit kirim/ kirim ulang kode)
-- -----------------------------------------------------------------------------
CREATE TABLE otp_throttle (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(191) NOT NULL,
    ip         VARCHAR(45)  NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otp_throttle_email (email, created_at),
    KEY idx_otp_throttle_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Percobaan login (rate limit login per-IP & per-akun, tahan ganti browser)
-- success: 0 = gagal, 1 = berhasil
-- -----------------------------------------------------------------------------
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip           VARCHAR(45)  NOT NULL DEFAULT '',
    username     VARCHAR(100) NOT NULL DEFAULT '',
    success      TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_ip_time (ip, attempted_at),
    KEY idx_login_user_time (username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Sanksi pelanggaran kode etik (ban sementara / permanen)
-- tingkat    : basic | sedang | berat
-- banned_until NULL = permanen sampai dibuka manual oleh admin/developer
-- -----------------------------------------------------------------------------
CREATE TABLE user_bans (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    arsip_id     INT UNSIGNED NULL,
    tingkat      ENUM('basic','sedang','berat') NOT NULL DEFAULT 'basic',
    alasan       TEXT NULL,
    banned_at    DATETIME NOT NULL,
    banned_until DATETIME NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_oleh  INT UNSIGNED NULL,
    dilepas_oleh INT UNSIGNED NULL,
    dilepas_at   DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ban_user (user_id),
    KEY idx_ban_active (is_active, banned_until),
    CONSTRAINT fk_ban_user     FOREIGN KEY (user_id)      REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ban_arsip    FOREIGN KEY (arsip_id)     REFERENCES arsip (id) ON DELETE SET NULL,
    CONSTRAINT fk_ban_maker    FOREIGN KEY (dibuat_oleh)  REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_ban_releaser FOREIGN KEY (dilepas_oleh) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Penataan arsip oleh petugas (susun ulang sesuai isi arsip)
-- Satu arsip punya satu catatan penataan (diperbarui bila disusun ulang).
-- -----------------------------------------------------------------------------
CREATE TABLE arsip_susun (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    arsip_id      INT UNSIGNED NOT NULL,
    kategori_baru VARCHAR(100) NULL,
    kode_baru     VARCHAR(50)  NULL,
    catatan       TEXT NULL,
    disusun_oleh  INT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_susun_arsip (arsip_id),
    CONSTRAINT fk_susun_arsip   FOREIGN KEY (arsip_id)     REFERENCES arsip (id) ON DELETE CASCADE,
    CONSTRAINT fk_susun_petugas FOREIGN KEY (disusun_oleh) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
