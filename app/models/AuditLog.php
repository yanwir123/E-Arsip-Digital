<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AuditLog
{
    /**
     * Bangun klausa WHERE dari filter.
     *
     * @param array<string, mixed> $params diisi by reference dengan parameter bind
     * @return array<int, string>
     */
    private static function filterWhere(array $filters, array &$params): array
    {
        $where = [];

        if (!empty($filters['aksi'])) {
            $where[]        = 'l.aksi = :aksi';
            $params['aksi'] = $filters['aksi'];
        }

        if (!empty($filters['user_id'])) {
            $where[]           = 'l.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(l.keterangan LIKE :q_keterangan OR u.nama LIKE :q_nama OR l.ip LIKE :q_ip)';

            $search                 = '%' . $filters['q'] . '%';
            $params['q_keterangan'] = $search;
            $params['q_nama']       = $search;
            $params['q_ip']         = $search;
        }

        return $where;
    }

    public static function all(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        $params = [];
        $where  = self::filterWhere($filters, $params);

        $sql = 'SELECT l.*, u.nama AS user_nama, u.username AS user_username
                FROM audit_log l
                LEFT JOIN users u ON u.id = l.user_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY l.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        return Database::run($sql, $params)->fetchAll();
    }

    /**
     * Hitung jumlah log sesuai filter (untuk paginasi).
     */
    public static function countAll(array $filters = []): int
    {
        $params = [];
        $where  = self::filterWhere($filters, $params);

        $sql = 'SELECT COUNT(*)
                FROM audit_log l
                LEFT JOIN users u ON u.id = l.user_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return (int) Database::run($sql, $params)->fetchColumn();
    }

    public static function recent(int $limit = 10): array
    {
        return Database::run(
            'SELECT l.*, u.nama AS user_nama
             FROM audit_log l
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT ' . max(1, $limit)
        )->fetchAll();
    }

    /**
     * Klausa WHERE untuk histori satu entitas beserta filter opsional.
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params diisi by reference dengan parameter bind
     * @return array<int, string>
     */
    private static function targetWhere(string $tabel, int $targetId, array $filters, array &$params): array
    {
        $where = [
            'l.target_tabel = :target_tabel',
            'l.target_id = :target_id',
        ];

        $params['target_tabel'] = $tabel;
        $params['target_id']    = $targetId;

        if (!empty($filters['aksi'])) {
            $where[]        = 'l.aksi = :aksi';
            $params['aksi'] = (string) $filters['aksi'];
        }

        if (!empty($filters['user_id'])) {
            $where[]           = 'l.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(l.keterangan LIKE :q_keterangan OR u.nama LIKE :q_nama)';

            $search                 = '%' . $filters['q'] . '%';
            $params['q_keterangan'] = $search;
            $params['q_nama']       = $search;
        }

        return $where;
    }

    /**
     * Histori aktivitas untuk satu entitas (mis. satu arsip), terbaru lebih dulu.
     */
    public static function forTarget(
        string $tabel,
        int $targetId,
        array $filters = [],
        int $limit = 20,
        int $offset = 0
    ): array {
        $params = [];
        $where  = self::targetWhere($tabel, $targetId, $filters, $params);

        $sql = 'SELECT l.*, u.nama AS user_nama, u.username AS user_username
                FROM audit_log l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY l.id DESC
                LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        return Database::run($sql, $params)->fetchAll();
    }

    /**
     * Hitung histori satu entitas sesuai filter (untuk paginasi).
     */
    public static function countForTarget(string $tabel, int $targetId, array $filters = []): int
    {
        $params = [];
        $where  = self::targetWhere($tabel, $targetId, $filters, $params);

        $sql = 'SELECT COUNT(*)
                FROM audit_log l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE ' . implode(' AND ', $where);

        return (int) Database::run($sql, $params)->fetchColumn();
    }

    /**
     * Daftar aksi yang pernah terjadi pada satu entitas.
     *
     * @return array<int, string>
     */
    public static function distinctActionsForTarget(string $tabel, int $targetId): array
    {
        return Database::run(
            'SELECT DISTINCT l.aksi
             FROM audit_log l
             WHERE l.target_tabel = :target_tabel AND l.target_id = :target_id
             ORDER BY l.aksi ASC',
            ['target_tabel' => $tabel, 'target_id' => $targetId]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Daftar pengguna yang pernah beraktivitas pada satu entitas.
     */
    public static function usersForTarget(string $tabel, int $targetId, bool $adminOnly = false): array
    {
        $roleFilter = $adminOnly ? "AND u.role IN ('developer', 'admin')" : '';

        return Database::run(
            'SELECT DISTINCT l.user_id, u.nama, u.username
             FROM audit_log l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.target_tabel = :target_tabel
                AND l.target_id = :target_id
                AND l.user_id IS NOT NULL
                ' . $roleFilter . '
             ORDER BY u.nama ASC',
            ['target_tabel' => $tabel, 'target_id' => $targetId]
        )->fetchAll();
    }

    public static function distinctActions(): array
    {
        return Database::run('SELECT DISTINCT aksi FROM audit_log ORDER BY aksi')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}
