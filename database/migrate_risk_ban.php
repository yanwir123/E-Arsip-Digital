<?php

declare(strict_types=1);

/**
 * Migration: tingkat risiko arsip + tabel sanksi (ban) pelanggaran kode etik.
 * TIDAK menghapus data yang sudah ada.
 *
 * Jalankan:  php database/migrate_risk_ban.php
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

echo "=== Migration Risiko & Sanksi E-Arsip ===\n\n";

// ---------------------------------------------------------------------------
// 1. Kolom risk_level pada tabel arsip
// ---------------------------------------------------------------------------
$arsipCols = $pdo->query('SHOW COLUMNS FROM arsip')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('risk_level', $arsipCols, true)) {
    $pdo->exec("ALTER TABLE arsip
        ADD COLUMN risk_level ENUM('hijau','kuning','merah') NOT NULL DEFAULT 'hijau' AFTER status");
    echo "OK  tambah kolom arsip.risk_level\n";
} else {
    echo "SKIP arsip.risk_level (sudah ada)\n";
}

// ---------------------------------------------------------------------------
// 2. Tabel user_bans (sanksi ban sementara / permanen)
// ---------------------------------------------------------------------------
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('user_bans', $tables, true)) {
    $pdo->exec(
        'CREATE TABLE user_bans (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      INT UNSIGNED NOT NULL,
            arsip_id     INT UNSIGNED NULL,
            tingkat      ENUM("basic","sedang","berat") NOT NULL DEFAULT "basic",
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
            CONSTRAINT fk_ban_user    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ban_arsip   FOREIGN KEY (arsip_id)   REFERENCES arsip (id) ON DELETE SET NULL,
            CONSTRAINT fk_ban_maker   FOREIGN KEY (dibuat_oleh) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_ban_releaser FOREIGN KEY (dilepas_oleh) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "OK  buat tabel user_bans\n";
} else {
    echo "SKIP tabel user_bans (sudah ada)\n";
}

// ---------------------------------------------------------------------------
// 3. Tabel arsip_susun (penataan arsip oleh petugas - disiapkan, belum aktif)
// ---------------------------------------------------------------------------
if (!in_array('arsip_susun', $tables, true)) {
    $pdo->exec(
        'CREATE TABLE arsip_susun (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            arsip_id     INT UNSIGNED NOT NULL,
            kategori_baru VARCHAR(100) NULL,
            kode_baru    VARCHAR(50)  NULL,
            catatan      TEXT NULL,
            disusun_oleh INT UNSIGNED NOT NULL,
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_susun_arsip (arsip_id),
            CONSTRAINT fk_susun_arsip   FOREIGN KEY (arsip_id)   REFERENCES arsip (id) ON DELETE CASCADE,
            CONSTRAINT fk_susun_petugas FOREIGN KEY (disusun_oleh) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "OK  buat tabel arsip_susun (penataan)\n";
} else {
    echo "SKIP tabel arsip_susun (sudah ada)\n";
}

echo "\nSelesai.\n";
