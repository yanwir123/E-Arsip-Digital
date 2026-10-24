<?php

declare(strict_types=1);

/**
 * Migration: penguatan keamanan.
 *   - Kolom users.must_change_password (paksa ganti password bawaan).
 *   - Tabel otp_throttle (rate limit pengiriman OTP).
 *   - Tabel login_attempts (rate limit login per-IP & per-akun).
 *
 * Aman dijalankan berulang kali (idempotent) dan TIDAK menghapus data.
 *
 * Jalankan:  php database/migrate_security.php
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

echo "=== Migration Keamanan E-Arsip ===\n\n";

// 1. Kolom users.must_change_password
$columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('must_change_password', $columns, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
    echo "OK  tambah kolom users.must_change_password\n";
} else {
    echo "SKIP users.must_change_password (sudah ada)\n";
}

// 2. Tabel baru
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('otp_throttle', $tables, true)) {
    $pdo->exec(
        'CREATE TABLE otp_throttle (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email      VARCHAR(191) NOT NULL,
            ip         VARCHAR(45)  NOT NULL DEFAULT "",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_otp_throttle_email (email, created_at),
            KEY idx_otp_throttle_ip (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "OK  buat tabel otp_throttle\n";
} else {
    echo "SKIP tabel otp_throttle (sudah ada)\n";
}

if (!in_array('login_attempts', $tables, true)) {
    $pdo->exec(
        'CREATE TABLE login_attempts (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip           VARCHAR(45)  NOT NULL DEFAULT "",
            username     VARCHAR(100) NOT NULL DEFAULT "",
            success      TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_login_ip_time (ip, attempted_at),
            KEY idx_login_user_time (username, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "OK  buat tabel login_attempts\n";
} else {
    echo "SKIP tabel login_attempts (sudah ada)\n";
}

echo "\nSelesai.\n";
