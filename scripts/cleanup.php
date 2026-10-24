<?php

declare(strict_types=1);

/**
 * CLI pembersih otomatis E-Arsip.
 *
 * Tugas:
 *   1. Menghapus arsip berstatus "pending" yang belum divalidasi melebihi
 *      batas jam (default 24 jam, lihat PENDING_AUTO_DELETE_HOURS / .env).
 *   2. Menonaktifkan sanksi (ban) yang sudah kedaluwarsa.
 *   3. Menghapus kode OTP yang sudah kedaluwarsa.
 *   4. Memangkas catatan rate limit lama (login_attempts & otp_throttle).
 *   5. Menghapus berkas fisik yatim (orphan) di storage/uploads yang tidak
 *      lagi dirujuk oleh tabel arsip / users.
 *
 * Jalankan manual:
 *   php scripts/cleanup.php
 *
 * Jadwalkan otomatis (Windows Task Scheduler), contoh harian:
 *   schtasks /create /tn "E-Arsip Cleanup" /sc daily /st 01:00 /tr "C:\php\php.exe C:\path\ke\E-Arsip\scripts\cleanup.php"
 */

// ---------------------------------------------------------------------------
// Bootstrap minimal (tanpa session / header, aman untuk CLI & cron)
// ---------------------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');

require APP_PATH . '/config/config.php';

// Autoloader PSR-4 sederhana: App\ => app/
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers/helpers.php';
require APP_PATH . '/helpers/audit.php';

use App\Models\Arsip;
use App\Models\Ban;
use App\Models\LoginAttempt;
use App\Models\Otp;
use App\Models\User;

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    http_response_code(403);
    exit('Script ini hanya boleh dijalankan dari CLI.');
}

try {
    \App\Core\Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal konek database: {$e->getMessage()}\n");
    exit(1);
}

$now = date('Y-m-d H:i:s');
echo "=== E-Arsip Cleanup ({$now}) ===\n\n";

// 1. Hapus arsip pending kedaluwarsa
echo 'Batas umur pending: ' . PENDING_AUTO_DELETE_HOURS . " jam\n";
$deleted = Arsip::deleteExpiredPending(PENDING_AUTO_DELETE_HOURS);
echo "OK  arsip pending dihapus : {$deleted}\n";

// 2. Nonaktifkan ban yang sudah lewat tanggal
$expired = Ban::expireOverdue();
echo "OK  blokir kedaluwarsa    : {$expired}\n";

// 3. Hapus kode OTP kedaluwarsa
$otp = Otp::pruneExpired();
echo "OK  OTP kedaluwarsa       : {$otp}\n";

// 4. Pangkas catatan rate limit lama
$attempts = LoginAttempt::prune(LOGIN_LOCKOUT_SECONDS);
echo "OK  login_attempts prune  : {$attempts}\n";
$throttle = (int) \App\Core\Database::run(
    'DELETE FROM otp_throttle WHERE created_at < :cutoff',
    ['cutoff' => date('Y-m-d H:i:s', time() - 86400)]
)->rowCount();
echo "OK  otp_throttle prune    : {$throttle}\n";

// 5. Hapus berkas yatim (tidak lagi dirujuk DB)
$orphans = cleanup_orphan_files();
echo "OK  berkas yatim dihapus  : {$orphans}\n";

echo "\nSelesai.\n";

/**
 * Hapus berkas di storage/uploads yang tidak lagi dirujuk oleh tabel arsip
 * (untuk berkas arsip) maupun users (untuk foto profil).
 */
function cleanup_orphan_files(): int
{
    if (!is_dir(UPLOAD_PATH)) {
        return 0;
    }

    $arsipSet = array_flip(Arsip::allFilePaths());
    $fotoSet  = array_flip(User::allPhotoNames());
    $deleted  = 0;

    $fotoDir = str_replace('\\', '/', UPLOAD_PATH . '/foto');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOAD_PATH, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        $name = $file->getFilename();

        // Jangan sentuh berkas penanda / tersembunyi.
        if (str_starts_with($name, '.')) {
            continue;
        }

        if (str_starts_with($path, $fotoDir . '/')) {
            if (!isset($fotoSet[$name])) {
                if (@unlink($path)) {
                    $deleted++;
                }
            }

            continue;
        }

        $relative = ltrim(substr($path, strlen(str_replace('\\', '/', UPLOAD_PATH))), '/');

        if (!isset($arsipSet[$relative])) {
            if (@unlink($path)) {
                $deleted++;
            }
        }
    }

    return $deleted;
}
