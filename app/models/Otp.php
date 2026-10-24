<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Penyimpanan & verifikasi kode OTP (verifikasi email saat registrasi).
 */
final class Otp
{
    public const TUJUAN_REGISTRASI = 'registrasi';

    /**
     * Buat kode OTP untuk sebuah email. Kode lama yang belum terpakai
     * untuk tujuan yang sama otomatis dimatikan (didahulukan yang baru).
     */
    public static function generate(string $email, string $tujuan, ?array $data = null): array
    {
        // Nonaktifkan kode lama yang belum terpakai.
        Database::run(
            'UPDATE otp_codes SET used_at = NOW() WHERE email = :email AND tujuan = :tujuan AND used_at IS NULL',
            ['email' => $email, 'tujuan' => $tujuan]
        );

        // Bersihkan kode yang sudah kedaluwarsa agar tabel tidak menumpuk.
        Database::run('DELETE FROM otp_codes WHERE expires_at < NOW()');

        $code = (string) random_int(100000, 999999);

        Database::run(
            'INSERT INTO otp_codes (email, kode, tujuan, data, expires_at)
             VALUES (:email, :kode, :tujuan, :data, :expires_at)',
            [
                'email'      => $email,
                'kode'       => $code,
                'tujuan'     => $tujuan,
                'data'       => $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                'expires_at' => date('Y-m-d H:i:s', time() + OTP_EXPIRY_SECONDS),
            ]
        );

        return [
            'email' => $email,
            'kode'  => $code,
        ];
    }

    /**
     * Ambil data tambahan (kolom `data`) dari kode terbaru yang belum terpakai.
     */
    public static function data(string $email, string $tujuan): ?array
    {
        $stmt = Database::run(
            'SELECT data FROM otp_codes
             WHERE email = :email AND tujuan = :tujuan AND used_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['email' => $email, 'tujuan' => $tujuan]
        );

        $row = $stmt->fetch();

        if (!$row || !is_string($row['data']) || $row['data'] === '') {
            return null;
        }

        $decoded = json_decode($row['data'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Periksa kode OTP. Bila cocok, kode langsung ditandai terpakai.
     */
    public static function check(string $email, string $tujuan, string $kode): bool
    {
        // Batasi percobaan dari kode terbaru yang masih aktif.
        $stmt = Database::run(
            'SELECT id, kode, expires_at, attempts FROM otp_codes
             WHERE email = :email AND tujuan = :tujuan AND used_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['email' => $email, 'tujuan' => $tujuan]
        );

        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $id = (int) $row['id'];

        // Terlalu banyak percobaan -> kunci.
        if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) {
            Database::run('UPDATE otp_codes SET used_at = NOW() WHERE id = :id', ['id' => $id]);

            return false;
        }

        // Kedaluwarsa -> hapus dan tolak.
        if (strtotime((string) $row['expires_at']) < time()) {
            Database::run('DELETE FROM otp_codes WHERE id = :id', ['id' => $id]);

            return false;
        }

        if (!hash_equals((string) $row['kode'], $kode)) {
            Database::run(
                'UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id',
                ['id' => $id]
            );

            return false;
        }

        Database::run(
            'UPDATE otp_codes SET used_at = NOW() WHERE id = :id',
            ['id' => $id]
        );

        return true;
    }

    /**
     * Kode terbaru yang belum terpakai untuk sebuah email, atau null.
     */
    public static function status(string $email, string $tujuan): ?array
    {
        $stmt = Database::run(
            'SELECT kode, expires_at FROM otp_codes
             WHERE email = :email AND tujuan = :tujuan AND used_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['email' => $email, 'tujuan' => $tujuan]
        );

        $row = $stmt->fetch();

        return $row ?: null;
    }

    // -----------------------------------------------------------------------
    // Throttling pengiriman OTP (tabel otp_throttle)
    //
    // Membatasi: (1) jeda minimum antar-permintaan per email, (2) jumlah
    // permintaan per email per jam, (3) jumlah permintaan per IP per jam.
    // Mencegah penyalahgunaan fitur "kirim ulang" untuk spam & pengurasan kuota.
    // -----------------------------------------------------------------------

    /**
     * Pesan kesalahan bila pengiriman OTP harus ditolak, atau null bila boleh.
     */
    public static function throttleError(string $email, string $ip): ?string
    {
        $last = Database::run(
            'SELECT created_at FROM otp_throttle WHERE email = :email ORDER BY id DESC LIMIT 1',
            ['email' => $email]
        )->fetchColumn();

        if (is_string($last) && $last !== '') {
            $wait = OTP_SEND_COOLDOWN_SECONDS - (time() - strtotime($last));

            if ($wait > 0) {
                return 'Mohon tunggu ' . $wait . ' detik sebelum meminta kode verifikasi baru.';
            }
        }

        $since = date('Y-m-d H:i:s', time() - 3600);

        $byEmail = (int) Database::run(
            'SELECT COUNT(*) FROM otp_throttle WHERE email = :email AND created_at > :since',
            ['email' => $email, 'since' => $since]
        )->fetchColumn();

        if ($byEmail >= OTP_SEND_MAX_PER_EMAIL) {
            return 'Permintaan kode untuk alamat email ini telah mencapai batas. Silakan coba kembali dalam satu jam.';
        }

        $byIp = (int) Database::run(
            'SELECT COUNT(*) FROM otp_throttle WHERE ip = :ip AND created_at > :since',
            ['ip' => $ip, 'since' => $since]
        )->fetchColumn();

        if ($byIp >= OTP_SEND_MAX_PER_IP) {
            return 'Permintaan kode dari jaringan ini telah mencapai batas. Silakan coba kembali beberapa saat lagi.';
        }

        return null;
    }

    /**
     * Catat satu pengiriman OTP (dan buang catatan yang sudah lebih dari sehari).
     */
    public static function recordSend(string $email, string $ip): void
    {
        Database::run(
            'INSERT INTO otp_throttle (email, ip, created_at) VALUES (:email, :ip, NOW())',
            ['email' => $email, 'ip' => $ip]
        );

        Database::run(
            'DELETE FROM otp_throttle WHERE created_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', time() - 86400)]
        );
    }

    /**
     * Hapus kode OTP yang sudah kedaluwarsa (pemeliharaan).
     */
    public static function pruneExpired(): int
    {
        return Database::run('DELETE FROM otp_codes WHERE expires_at < NOW()')->rowCount();
    }
}