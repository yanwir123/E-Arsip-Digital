<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Catatan percobaan login (tabel login_attempts) untuk rate limit berbasis
 * database, sehingga tidak bisa dilewati hanya dengan menghapus cookie/sesi
 * atau berganti browser.
 */
final class LoginAttempt
{
    /**
     * Jumlah kegagalan dari sebuah IP dalam jendela waktu terakhir.
     */
    public static function countByIp(string $ip, int $windowSeconds): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip = :ip AND success = 0 AND attempted_at > :cutoff',
            ['ip' => $ip, 'cutoff' => self::cutoff($windowSeconds)]
        )->fetchColumn();
    }

    /**
     * Jumlah kegagalan untuk sebuah username dalam jendela waktu terakhir.
     */
    public static function countByUsername(string $username, int $windowSeconds): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = :username AND success = 0 AND attempted_at > :cutoff',
            ['username' => $username, 'cutoff' => self::cutoff($windowSeconds)]
        )->fetchColumn();
    }

    /**
     * Waktu percobaan terakhir (ip atau username) dalam jendela waktu.
     */
    public static function latestAt(string $ip, string $username, int $windowSeconds): ?string
    {
        $row = Database::run(
            'SELECT MAX(attempted_at) AS latest FROM login_attempts
             WHERE (ip = :ip OR username = :username) AND attempted_at > :cutoff',
            [
                'ip'       => $ip,
                'username' => $username,
                'cutoff'   => self::cutoff($windowSeconds),
            ]
        )->fetch();

        $latest = $row['latest'] ?? null;

        return is_string($latest) && $latest !== '' ? $latest : null;
    }

    /**
     * Catat satu percobaan gagal.
     */
    public static function recordFailure(string $ip, string $username): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip, username, success, attempted_at)
             VALUES (:ip, :username, 0, NOW())',
            ['ip' => $ip, 'username' => $username]
        );
    }

    /**
     * Hapus riwayat gagal untuk kombinasi ip+username (dipanggil saat sukses).
     */
    public static function clear(string $ip, string $username): void
    {
        Database::run(
            'DELETE FROM login_attempts WHERE ip = :ip OR username = :username',
            ['ip' => $ip, 'username' => $username]
        );
    }

    /**
     * Buang catatan yang sudah lebih lama dari jendela waktu (pemeliharaan).
     */
    public static function prune(int $windowSeconds): int
    {
        return Database::run(
            'DELETE FROM login_attempts WHERE attempted_at < :cutoff',
            ['cutoff' => self::cutoff($windowSeconds)]
        )->rowCount();
    }

    private static function cutoff(int $windowSeconds): string
    {
        return date('Y-m-d H:i:s', time() - max(1, $windowSeconds));
    }
}
