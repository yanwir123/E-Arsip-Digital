<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Koneksi database tunggal (singleton) berbasis PDO.
 * Selalu gunakan prepared statement melalui kelas ini untuk mencegah SQL injection.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw new RuntimeException('Koneksi database gagal: ' . $e->getMessage(), 0, $e);
            }

            throw new RuntimeException('Koneksi database gagal. Periksa konfigurasi database (.env) dan pastikan ekstensi pdo_mysql aktif di PHP server (bukan hanya CLI).');
        }

        return self::$pdo;
    }

    /**
     * Helper singkat untuk menjalankan query berparameter.
     */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }
}
