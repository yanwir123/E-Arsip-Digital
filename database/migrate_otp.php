<?php

declare(strict_types=1);

/**
 * Migration: tambah kolom email & email_verified_at pada users,
 * serta tabel otp_codes. TIDAK menghapus data yang sudah ada.
 *
 * Jalankan:  php database/migrate_otp.php
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require APP_PATH . '/config/config.php';
require APP_PATH . '/core/Database.php';

use App\Core\Database;

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal konek database: {$e->getMessage()}\n");
    exit(1);
}

echo "=== Migration OTP E-Arsip ===\n\n";

// Kolom di tabel users
$columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('email', $columns, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(191) NULL AFTER username, ADD UNIQUE KEY uq_users_email (email)');
    echo "OK  tambah kolom users.email + unique key\n";
} else {
    echo "SKIP users.email (sudah ada)\n";
}

if (!in_array('email_verified_at', $columns, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER email');
    echo "OK  tambah kolom users.email_verified_at\n";
} else {
    echo "SKIP users.email_verified_at (sudah ada)\n";
}

// Pengguna lama dianggap sudah terverifikasi email-nya (dibuat oleh admin/dev).
$pdo->exec('UPDATE users SET email_verified_at = NOW() WHERE email IS NOT NULL AND email_verified_at IS NULL');
echo "OK  backfill email_verified_at untuk pengguna lama\n";

// Tabel otp_codes
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('otp_codes', $tables, true)) {
    $pdo->exec(
        'CREATE TABLE otp_codes (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email       VARCHAR(191) NOT NULL,
            kode        VARCHAR(10)  NOT NULL,
            tujuan      VARCHAR(20)  NOT NULL DEFAULT "registrasi",
            data        TEXT NULL,
            expires_at  DATETIME NOT NULL,
            used_at     DATETIME NULL,
            attempts    INT UNSIGNED NOT NULL DEFAULT 0,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_otp_email (email),
            KEY idx_otp_tujuan (tujuan)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "OK  buat tabel otp_codes\n";
} else {
    echo "SKIP tabel otp_codes (sudah ada)\n";
}

echo "\nSelesai.\n";