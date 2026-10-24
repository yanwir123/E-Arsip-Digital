<?php

declare(strict_types=1);

/**
 * Seeder akun awal untuk 4 role.
 * Jalankan dari terminal:  php database/seed.php
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require APP_PATH . '/config/config.php';
require APP_PATH . '/core/Database.php';

use App\Core\Database;

$seed = [
    ['nama' => 'Pengembang Sistem', 'username' => 'developer', 'email' => 'developer@example.com', 'password' => 'dev12345',  'role' => 'developer'],
    ['nama' => 'Administrator',     'username' => 'admin',     'email' => 'admin@example.com',     'password' => 'admin123',  'role' => 'admin'],
    ['nama' => 'Petugas Arsip',     'username' => 'petugas',   'email' => 'petugas@example.com',   'password' => 'petugas123', 'role' => 'petugas'],
    ['nama' => 'Budi Santoso',      'username' => 'budi',      'email' => 'budi@example.com',      'password' => 'budi12345', 'role' => 'user'],
    ['nama' => 'Sari Wulandari',    'username' => 'sari',      'email' => 'sari@example.com',      'password' => 'sari12345', 'role' => 'user'],
];

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal konek database: {$e->getMessage()}\n");
    fwrite(STDERR, "Periksa konfigurasi DB_* di file .env\n");
    exit(1);
}

echo "=== Seeder E-Arsip ===\n\n";

foreach ($seed as $u) {
    $exists = Database::run('SELECT id FROM users WHERE username = :u', ['u' => $u['username']])->fetch();

    if ($exists) {
        echo "SKIP  {$u['username']} (sudah ada)\n";
        continue;
    }

    Database::run(
        'INSERT INTO users (nama, username, email, email_verified_at, password, role, status, must_change_password)
         VALUES (:nama, :username, :email, NOW(), :password, :role, :status, 1)',
        [
            'nama'     => $u['nama'],
            'username' => $u['username'],
            'email'    => $u['email'],
            'password' => password_hash($u['password'], PASSWORD_DEFAULT),
            'role'     => $u['role'],
            'status'   => 'aktif',
        ]
    );

    echo "OK    {$u['username']} / {$u['password']}  ({$u['role']})\n";
}

echo "\nSelesai. Ganti password bawaan sebelum dipakai sungguhan.\n";
