<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Instansi
{
    public static function all(): array
    {
        return Database::run('SELECT id, kode, nama FROM instansi ORDER BY nama')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::run('SELECT id, kode, nama FROM instansi WHERE id = :id LIMIT 1', ['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function exists(int $id): bool
    {
        return (bool) Database::run('SELECT 1 FROM instansi WHERE id = :id LIMIT 1', ['id' => $id])->fetchColumn();
    }
}
