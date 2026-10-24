<?php

declare(strict_types=1);

/**
 * Migrasi fitur profil + identitas pengguna.
 *
 * Idempoten: aman dijalankan berulang kali.
 *   - Membuat tabel `instansi` (25 data dummy) bila belum ada.
 *   - Menambah kolom biodata pada tabel `users` bila belum ada.
 *   - Menambah index + foreign key bila belum ada.
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
    fwrite(STDERR, 'Gagal koneksi: ' . $e->getMessage() . "\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 1. Tabel instansi
// ---------------------------------------------------------------------------
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS instansi (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kode VARCHAR(20) NOT NULL,
        nama VARCHAR(200) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_instansi_kode (kode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

// Pastikan tipe id UNSIGNED agar cocok dengan users.instansi_id.
try {
    $pdo->exec('ALTER TABLE instansi MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
} catch (Throwable $e) {
    // diabaikan
}

if ((int) $pdo->query('SELECT COUNT(*) FROM instansi')->fetchColumn() === 0) {
    $data = [
        ['INST-001', 'Dinas Pendidikan'],
        ['INST-002', 'Dinas Kesehatan'],
        ['INST-003', 'Dinas Pekerjaan Umum dan Penataan Ruang'],
        ['INST-004', 'Dinas Perhubungan'],
        ['INST-005', 'Dinas Sosial'],
        ['INST-006', 'Dinas Kependudukan dan Pencatatan Sipil'],
        ['INST-007', 'Dinas Komunikasi dan Informatika'],
        ['INST-008', 'Dinas Koperasi dan Usaha Kecil Menengah'],
        ['INST-009', 'Dinas Tenaga Kerja'],
        ['INST-010', 'Dinas Lingkungan Hidup'],
        ['INST-011', 'Dinas Pemberdayaan Perempuan dan Perlindungan Anak'],
        ['INST-012', 'Dinas Ketahanan Pangan dan Pertanian'],
        ['INST-013', 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu'],
        ['INST-014', 'Dinas Kebudayaan dan Pariwisata'],
        ['INST-015', 'Dinas Perpustakaan dan Kearsipan'],
        ['INST-016', 'Badan Kepegawaian dan Pengembangan SDM'],
        ['INST-017', 'Badan Perencanaan Pembangunan Daerah'],
        ['INST-018', 'Badan Pengelola Keuangan dan Aset Daerah'],
        ['INST-019', 'Sekretariat Daerah'],
        ['INST-020', 'Sekretariat DPRD'],
        ['INST-021', 'Kecamatan Kota'],
        ['INST-022', 'Kelurahan Mawar'],
        ['INST-023', 'Kelurahan Melati'],
        ['INST-024', 'Kelurahan Anggrek'],
        ['INST-025', 'Rumah Sakit Umum Daerah'],
    ];

    $ins = $pdo->prepare('INSERT INTO instansi (kode, nama) VALUES (?, ?)');

    foreach ($data as $row) {
        $ins->execute($row);
    }

    echo 'OK: instansi + ' . count($data) . " baris\n";
} else {
    echo "SKIP: instansi sudah berisi data\n";
}

// ---------------------------------------------------------------------------
// 2. Kolom biodata pada users
// ---------------------------------------------------------------------------
$cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
$add  = [];

if (!in_array('foto_profil', $cols, true))          $add[] = 'ADD COLUMN foto_profil VARCHAR(255) NULL AFTER email';
if (!in_array('instansi_id', $cols, true))          $add[] = 'ADD COLUMN instansi_id INT UNSIGNED NULL AFTER foto_profil';
if (!in_array('no_induk', $cols, true))             $add[] = 'ADD COLUMN no_induk VARCHAR(50) NULL AFTER instansi_id';
if (!in_array('nip', $cols, true))                  $add[] = 'ADD COLUMN nip VARCHAR(30) NULL AFTER no_induk';
if (!in_array('jabatan', $cols, true))              $add[] = 'ADD COLUMN jabatan VARCHAR(100) NULL AFTER nip';
if (!in_array('telepon', $cols, true))              $add[] = 'ADD COLUMN telepon VARCHAR(20) NULL AFTER jabatan';
if (!in_array('alamat', $cols, true))               $add[] = 'ADD COLUMN alamat VARCHAR(255) NULL AFTER telepon';
if (!in_array('identity_status', $cols, true))      $add[] = "ADD COLUMN identity_status ENUM('belum','menunggu','terverifikasi','ditolak') NOT NULL DEFAULT 'belum' AFTER status";
if (!in_array('identity_verified_at', $cols, true)) $add[] = 'ADD COLUMN identity_verified_at DATETIME NULL AFTER identity_status';
if (!in_array('identity_verified_by', $cols, true)) $add[] = 'ADD COLUMN identity_verified_by INT UNSIGNED NULL AFTER identity_verified_at';
if (!in_array('identity_reject_reason', $cols, true)) $add[] = 'ADD COLUMN identity_reject_reason TEXT NULL AFTER identity_verified_by';

if ($add) {
    $pdo->exec('ALTER TABLE users ' . implode(', ', $add));
    echo "OK: kolom users ditambahkan\n";
} else {
    echo "SKIP: kolom users sudah lengkap\n";
}

// ---------------------------------------------------------------------------
// 3. Index + foreign key
// ---------------------------------------------------------------------------
try {
    $pdo->exec('ALTER TABLE users ADD KEY idx_users_instansi (instansi_id), ADD KEY idx_users_identity_status (identity_status)');
    echo "OK: index ditambahkan\n";
} catch (Throwable $e) {
    echo "SKIP: index sudah ada\n";
}

try {
    $pdo->exec('ALTER TABLE users ADD CONSTRAINT fk_users_instansi FOREIGN KEY (instansi_id) REFERENCES instansi (id) ON DELETE SET NULL');
    echo "OK: foreign key ditambahkan\n";
} catch (Throwable $e) {
    echo "SKIP: foreign key sudah ada\n";
}

echo "Selesai.\n";
