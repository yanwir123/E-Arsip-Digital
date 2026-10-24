<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Arsip
{
    public static function find(int $id): ?array
    {
        $stmt = Database::run(
            'SELECT a.*, u.nama AS uploader_nama, v.nama AS validator_nama
             FROM arsip a
             JOIN users u ON u.id = a.uploader_id
             LEFT JOIN users v ON v.id = a.validator_id
             WHERE a.id = :id LIMIT 1',
            ['id' => $id]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Bangun klausa WHERE untuk arsip yang boleh diakses user + filter.
     *
     * @param array<string, mixed> $params diisi by reference dengan parameter bind
     * @return array<int, string>
     */
    private static function accessibleWhere(array $user, array $filters, array &$params): array
    {
        $where = [];

        if (sees_all_arsip($user)) {
            $where[] = '1 = 1';
        } else {
            $where[]              = '(a.uploader_id = :uid_own OR (a.status = :approved AND EXISTS (
                                        SELECT 1 FROM arsip_akses ax
                                        WHERE ax.arsip_id = a.id AND ax.user_id = :uid_access
                                      )))';
            $params['uid_own']    = (int) $user['id'];
            $params['uid_access'] = (int) $user['id'];
            $params['approved']   = ARSIP_APPROVED;
        }

        if (!empty($filters['status'])) {
            $where[]          = 'a.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[]     = '(a.judul LIKE :q OR a.kode LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return $where;
    }

    /**
     * Daftar arsip yang boleh dilihat oleh user tertentu (dengan paginasi).
     */
    public static function listAccessible(array $user, array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $params = [];
        $where  = self::accessibleWhere($user, $filters, $params);

        $sql = 'SELECT a.*, u.nama AS uploader_nama,
                       (SELECT COUNT(DISTINCT l.user_id)
                        FROM audit_log l
                        JOIN users viewer ON viewer.id = l.user_id
                        WHERE l.target_tabel = \'arsip\' AND l.target_id = a.id
                          AND l.aksi = \'lihat_arsip\'
                          AND viewer.role NOT IN (\'developer\', \'admin\')) AS access_count
                FROM arsip a
                JOIN users u ON u.id = a.uploader_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.created_at DESC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        }

        return Database::run($sql, $params)->fetchAll();
    }

    /**
     * Hitung jumlah arsip yang boleh dilihat user (untuk paginasi).
     */
    public static function countAccessible(array $user, array $filters = []): int
    {
        $params = [];
        $where  = self::accessibleWhere($user, $filters, $params);

        return (int) Database::run(
            'SELECT COUNT(*) FROM arsip a WHERE ' . implode(' AND ', $where),
            $params
        )->fetchColumn();
    }

    /**
     * Arsip yang menunggu validasi (untuk panel admin).
     */
    public static function pending(): array
    {
        return Database::run(
            "SELECT a.*, u.nama AS uploader_nama
             FROM arsip a
             JOIN users u ON u.id = a.uploader_id
             WHERE a.status = :s
             ORDER BY a.created_at ASC",
            ['s' => ARSIP_PENDING]
        )->fetchAll();
    }

    public static function create(array $data): int
    {
        Database::run(
            'INSERT INTO arsip (kode, judul, deskripsi, kategori, file_path, file_asli, file_tipe, file_ukuran, status, uploader_id)
             VALUES (:kode, :judul, :deskripsi, :kategori, :file_path, :file_asli, :file_tipe, :file_ukuran, :status, :uploader_id)',
            [
                'kode'        => $data['kode'] ?? null,
                'judul'       => $data['judul'],
                'deskripsi'   => $data['deskripsi'] ?? null,
                'kategori'    => $data['kategori'] ?? null,
                'file_path'   => $data['file_path'],
                'file_asli'   => $data['file_asli'],
                'file_tipe'   => $data['file_tipe'],
                'file_ukuran' => $data['file_ukuran'],
                'status'      => ARSIP_PENDING,
                'uploader_id' => (int) $data['uploader_id'],
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public static function setStatus(
        int $id,
        string $status,
        int $validatorId,
        ?string $alasan = null,
        ?string $riskLevel = null
    ): void {
        Database::run(
            'UPDATE arsip
             SET status = :status, validator_id = :validator_id, alasan_reject = :alasan,
                 risk_level = COALESCE(:risk_level, risk_level), validated_at = NOW()
             WHERE id = :id',
            [
                'id'           => $id,
                'status'       => $status,
                'validator_id' => $validatorId,
                'alasan'       => $alasan,
                'risk_level'   => $riskLevel,
            ]
        );
    }

    /**
     * Kembalikan arsip yang ditolak ke antrean validasi.
     * Dipakai saat pengunggah memperbaiki arsipnya agar divalidasi ulang.
     */
    public static function reopen(int $id): void
    {
        Database::run(
            'UPDATE arsip
             SET status = :status, validator_id = NULL, alasan_reject = NULL,
                 risk_level = :risk, validated_at = NULL
             WHERE id = :id',
            ['id' => $id, 'status' => ARSIP_PENDING, 'risk' => RISK_HIJAU]
        );
    }

    /**
     * Arsip pending yang usianya melewati batas (jam) tertentu.
     */
    public static function expiredPending(int $hours): array
    {
        $hours = max(1, $hours);

        return Database::run(
            "SELECT id, file_path, judul, uploader_id
             FROM arsip
             WHERE status = :status
               AND created_at < (NOW() - INTERVAL {$hours} HOUR)
             ORDER BY created_at ASC",
            ['status' => ARSIP_PENDING]
        )->fetchAll();
    }

    /**
     * Hapus arsip pending yang kedaluwarsa beserta berkasnya.
     *
     * @return int jumlah arsip yang dihapus
     */
    public static function deleteExpiredPending(int $hours): int
    {
        $rows = self::expiredPending($hours);
        $count = 0;

        foreach ($rows as $row) {
            $file = UPLOAD_PATH . '/' . $row['file_path'];

            if (is_file($file)) {
                @unlink($file);
            }

            self::delete((int) $row['id']);
            audit_log(
                'hapus_arsip_otomatis',
                'arsip',
                (int) $row['id'],
                'Arsip pending kedaluwarsa (>' . $hours . ' jam) dihapus otomatis: ' . $row['judul']
            );
            $count++;
        }

        return $count;
    }

    /**
     * Perbarui metadata arsip. Bila $file diisi, ganti juga berkasnya.
     *
     * @param array{path:string, asli:string, tipe:string, ukuran:int}|null $file
     */
    public static function update(int $id, array $data, ?array $file = null): void
    {
        $sql = 'UPDATE arsip
                SET kode = :kode, judul = :judul, deskripsi = :deskripsi, kategori = :kategori';

        $params = [
            'id'        => $id,
            'kode'      => $data['kode'] ?? null,
            'judul'     => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'kategori'  => $data['kategori'] ?? null,
        ];

        if ($file !== null) {
            $sql .= ', file_path = :file_path, file_asli = :file_asli,
                     file_tipe = :file_tipe, file_ukuran = :file_ukuran';
            $params['file_path']   = $file['path'];
            $params['file_asli']   = $file['asli'];
            $params['file_tipe']   = $file['tipe'];
            $params['file_ukuran'] = $file['ukuran'];
        }

        $sql .= ' WHERE id = :id';

        Database::run($sql, $params);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM arsip WHERE id = :id', ['id' => $id]);
    }

    /**
     * Seluruh path berkas arsip (relatif terhadap storage/uploads).
     * Dipakai oleh pembersih berkas yatim.
     *
     * @return array<int, string>
     */
    public static function allFilePaths(): array
    {
        $rows = Database::run('SELECT file_path FROM arsip')->fetchAll();

        return array_map(static fn ($r) => (string) $r['file_path'], $rows);
    }

    // -----------------------------------------------------------------------
    // Izin akses per-arsip
    // -----------------------------------------------------------------------

    /**
     * @return array<int, int> daftar user_id yang diizinkan
     */
    public static function accessUserIds(int $arsipId): array
    {
        $rows = Database::run(
            'SELECT user_id FROM arsip_akses WHERE arsip_id = :id',
            ['id' => $arsipId]
        )->fetchAll();

        return array_map(static fn ($r) => (int) $r['user_id'], $rows);
    }

    /**
     * Ganti seluruh izin akses sebuah arsip.
     *
     * @param array<int, int> $userIds
     */
    public static function syncAccess(int $arsipId, array $userIds, int $givenBy): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            Database::run('DELETE FROM arsip_akses WHERE arsip_id = :id', ['id' => $arsipId]);

            $stmt = $pdo->prepare(
                'INSERT INTO arsip_akses (arsip_id, user_id, diberikan_oleh)
                 VALUES (:arsip_id, :user_id, :diberikan_oleh)'
            );

            foreach (array_unique(array_map('intval', $userIds)) as $userId) {
                if ($userId <= 0) {
                    continue;
                }
                $stmt->execute([
                    'arsip_id'       => $arsipId,
                    'user_id'        => $userId,
                    'diberikan_oleh' => $givenBy,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Jumlah arsip per status, dibatasi jangkauan akses user tersebut
     * (pengguna biasa hanya menghitung arsipnya sendiri + yang diizinkan).
     */
    public static function countByStatus(array $user): array
    {
        $params = [];
        $where  = self::accessibleWhere($user, [], $params);

        $rows = Database::run(
            'SELECT a.status, COUNT(*) AS total
             FROM arsip a
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY a.status',
            $params
        )->fetchAll();

        $out = array_fill_keys(array_keys(ARSIP_STATUS), 0);

        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['total'];
        }

        return $out;
    }
}
