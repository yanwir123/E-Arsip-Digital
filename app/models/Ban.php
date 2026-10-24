<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Sanksi (ban) pelanggaran kode etik pengguna.
 */
final class Ban
{
    /**
     * Buat sanksi baru untuk seorang pengguna.
     * banned_until = NULL berarti permanen sampai dibuka manual.
     */
    public static function create(
        int $userId,
        string $tingkat,
        ?string $alasan,
        ?int $arsipId,
        int $dibuatOleh
    ): int {
        $days = PENALTY_DAYS[$tingkat] ?? 0;
        $until = $days > 0 ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null;

        Database::run(
            'INSERT INTO user_bans (user_id, arsip_id, tingkat, alasan, banned_at, banned_until, is_active, dibuat_oleh)
             VALUES (:user_id, :arsip_id, :tingkat, :alasan, NOW(), :banned_until, 1, :dibuat_oleh)',
            [
                'user_id'      => $userId,
                'arsip_id'     => $arsipId,
                'tingkat'      => $tingkat,
                'alasan'       => $alasan,
                'banned_until' => $until,
                'dibuat_oleh'  => $dibuatOleh,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Sanksi aktif milik seorang pengguna (belum dibuka & belum kedaluwarsa).
     */
    public static function activeForUser(int $userId): ?array
    {
        $row = Database::run(
            'SELECT * FROM user_bans
             WHERE user_id = :user_id
               AND is_active = 1
               AND (banned_until IS NULL OR banned_until > NOW())
             ORDER BY id DESC LIMIT 1',
            ['user_id' => $userId]
        )->fetch();

        return $row ?: null;
    }

    public static function isBanned(int $userId): bool
    {
        return self::activeForUser($userId) !== null;
    }

    /**
     * Daftar sanksi (default: yang masih aktif).
     */
    public static function all(bool $onlyActive = true, int $limit = 200): array
    {
        $sql = 'SELECT b.*, u.nama AS user_nama, u.username AS user_username,
                       a.judul AS arsip_judul,
                       m.nama AS dibuat_nama, r.nama AS dilepas_nama
                FROM user_bans b
                JOIN users u ON u.id = b.user_id
                LEFT JOIN arsip a ON a.id = b.arsip_id
                LEFT JOIN users m ON m.id = b.dibuat_oleh
                LEFT JOIN users r ON r.id = b.dilepas_oleh';

        if ($onlyActive) {
            $sql .= ' WHERE b.is_active = 1 AND (b.banned_until IS NULL OR b.banned_until > NOW())';
        }

        $sql .= ' ORDER BY b.id DESC LIMIT ' . max(1, $limit);

        return Database::run($sql)->fetchAll();
    }

    /**
     * Tandai sanksi kedaluwarsa sebagai nonaktif (dipanggil oleh cron/CLI).
     *
     * @return int jumlah baris yang diperbarui
     */
    public static function expireOverdue(): int
    {
        $stmt = Database::run(
            'UPDATE user_bans
             SET is_active = 0
             WHERE is_active = 1 AND banned_until IS NOT NULL AND banned_until <= NOW()'
        );

        return $stmt->rowCount();
    }

    /**
     * Buka sanksi secara manual oleh admin/developer.
     */
    public static function release(int $id, int $adminId): bool
    {
        $stmt = Database::run(
            'UPDATE user_bans
             SET is_active = 0, dilepas_oleh = :admin, dilepas_at = NOW()
             WHERE id = :id AND is_active = 1',
            ['admin' => $adminId, 'id' => $id]
        );

        return $stmt->rowCount() > 0;
    }
}
