<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::run('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::run('SELECT * FROM users WHERE username = :u LIMIT 1', ['u' => $username]);

        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::run('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]);

        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT 1 FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return (bool) Database::run($sql . ' LIMIT 1', $params)->fetchColumn();
    }

    public static function all(): array
    {
        return Database::run(
            'SELECT id, nama, username, email, role, status, last_login, created_at
             FROM users ORDER BY role, nama'
        )->fetchAll();
    }

    /**
     * Buat pengguna baru.
     *
     * Terima salah satu dari:
     * - `password`      : password polos (akan di-hash di sini), atau
     * - `password_hash` : hash yang sudah jadi (dipakai alur registrasi OTP).
     */
    public static function create(array $data): int
    {
        $hash = $data['password_hash'] ?? null;

        if (!is_string($hash) || $hash === '') {
            $hash = password_hash((string) ($data['password'] ?? ''), PASSWORD_DEFAULT);
        }

        Database::run(
            'INSERT INTO users (nama, username, email, email_verified_at, password, role, status)
             VALUES (:nama, :username, :email, :email_verified_at, :password, :role, :status)',
            [
                'nama'              => $data['nama'],
                'username'          => $data['username'],
                'email'             => $data['email'] ?? null,
                'email_verified_at' => $data['email_verified_at'] ?? null,
                'password'          => $hash,
                'role'              => $data['role'],
                'status'            => $data['status'] ?? 'aktif',
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE users SET nama = :nama, username = :username, email = :email, role = :role, status = :status
             WHERE id = :id',
            [
                'id'       => $id,
                'nama'     => $data['nama'],
                'username' => $data['username'],
                'email'    => $data['email'] ?? null,
                'role'     => $data['role'],
                'status'   => $data['status'],
            ]
        );
    }

    public static function updatePassword(int $id, string $plain): void
    {
        Database::run(
            'UPDATE users SET password = :password, must_change_password = 0 WHERE id = :id',
            ['id' => $id, 'password' => password_hash($plain, PASSWORD_DEFAULT)]
        );
    }

    /**
     * Perbarui biodata profil milik sendiri (tanpa mengubah role/status).
     */
    public static function updateProfile(int $id, array $data): void
    {
        $sql =
            'UPDATE users SET nama = :nama, instansi_id = :instansi_id, no_induk = :no_induk,
                nip = :nip, jabatan = :jabatan, telepon = :telepon, alamat = :alamat';

        $params = [
            'id'          => $id,
            'nama'        => $data['nama'],
            'instansi_id' => $data['instansi_id'] !== null ? (int) $data['instansi_id'] : null,
            'no_induk'    => $data['no_induk'] !== '' ? $data['no_induk'] : null,
            'nip'         => $data['nip'] !== '' ? $data['nip'] : null,
            'jabatan'     => $data['jabatan'] !== '' ? $data['jabatan'] : null,
            'telepon'     => $data['telepon'] !== '' ? $data['telepon'] : null,
            'alamat'      => $data['alamat'] !== '' ? $data['alamat'] : null,
        ];

        if (!empty($data['foto_profil'])) {
            $sql .= ', foto_profil = :foto_profil';
            $params['foto_profil'] = $data['foto_profil'];
        }

        $sql .= ' WHERE id = :id';

        Database::run($sql, $params);
    }

    /**
     * Ajukan biodata untuk diverifikasi admin/petugas.
     */
    public static function submitIdentity(int $id): void
    {
        Database::run(
            "UPDATE users SET identity_status = 'menunggu', identity_reject_reason = NULL,
                identity_verified_at = NULL, identity_verified_by = NULL
             WHERE id = :id",
            ['id' => $id]
        );
    }

    public static function approveIdentity(int $id, int $verifierId): void
    {
        Database::run(
            "UPDATE users SET identity_status = 'terverifikasi', identity_reject_reason = NULL,
                identity_verified_at = NOW(), identity_verified_by = :by
             WHERE id = :id",
            ['id' => $id, 'by' => $verifierId]
        );
    }

    public static function rejectIdentity(int $id, int $verifierId, string $reason): void
    {
        Database::run(
            "UPDATE users SET identity_status = 'ditolak', identity_reject_reason = :reason,
                identity_verified_at = NOW(), identity_verified_by = :by
             WHERE id = :id",
            ['id' => $id, 'by' => $verifierId, 'reason' => $reason]
        );
    }

    /**
     * Daftar pengguna beserta info identitas (untuk panel validasi admin).
     *
     * @param string|null $status filter identity_status, null = semua
     */
    public static function allWithIdentity(?string $status = null): array
    {
        $sql =
            'SELECT u.id, u.nama, u.username, u.email, u.role, u.status, u.foto_profil,
                    u.no_induk, u.nip, u.jabatan, u.telepon, u.alamat,
                    u.identity_status, u.identity_reject_reason, u.identity_verified_at,
                    i.nama AS instansi_nama, i.kode AS instansi_kode,
                    v.nama AS verifier_nama
             FROM users u
             LEFT JOIN instansi i ON i.id = u.instansi_id
             LEFT JOIN users v ON v.id = u.identity_verified_by';

        $params = [];

        if ($status !== null) {
            $sql .= ' WHERE u.identity_status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY FIELD(u.identity_status, \'menunggu\', \'belum\', \'ditolak\', \'terverifikasi\'), u.nama';

        return Database::run($sql, $params)->fetchAll();
    }

    public static function identityCounts(): array
    {
        $rows = Database::run(
            'SELECT identity_status, COUNT(*) AS total FROM users GROUP BY identity_status'
        )->fetchAll();

        $out = array_fill_keys(IDENTITY_STATUSES, 0);

        foreach ($rows as $row) {
            $out[$row['identity_status']] = (int) $row['total'];
        }

        return $out;
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM users WHERE id = :id', ['id' => $id]);
    }

    /**
     * Seluruh nama berkas foto profil. Dipakai pembersih berkas yatim.
     *
     * @return array<int, string>
     */
    public static function allPhotoNames(): array
    {
        $rows = Database::run(
            "SELECT foto_profil FROM users WHERE foto_profil IS NOT NULL AND foto_profil <> ''"
        )->fetchAll();

        return array_map(static fn ($r) => (string) $r['foto_profil'], $rows);
    }

    public static function countByRole(): array
    {
        $rows = Database::run('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll();
        $out  = array_fill_keys(array_keys(ROLES), 0);

        foreach ($rows as $row) {
            $out[$row['role']] = (int) $row['total'];
        }

        return $out;
    }

    public static function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT 1 FROM users WHERE username = :u';
        $params = ['u' => $username];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return (bool) Database::run($sql . ' LIMIT 1', $params)->fetchColumn();
    }
}
