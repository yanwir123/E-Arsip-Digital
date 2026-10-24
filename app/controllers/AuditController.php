<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AuditLog;

final class AuditController extends Controller
{
    public function index(): void
    {
        // Hanya admin & developer yang boleh memeriksa log aktivitas.
        // Developer selalu lolos melalui has_role(). Petugas dan pengguna
        // biasa ditolak (403).
        require_role(ROLE_ADMIN);

        $filters = [
            'aksi'    => (string) $this->input('aksi', ''),
            'q'       => (string) $this->input('q', ''),
            'user_id' => (string) $this->input('user_id', ''),
        ];

        $total     = AuditLog::countAll($filters);
        $paginator = paginate($total, PER_PAGE, (int) $this->input('page', 1));

        $this->view('admin/audit', [
            'title'           => 'Log Aktivitas',
            'logs'            => AuditLog::all($filters, $paginator['per_page'], $paginator['offset']),
            'filters'         => $filters,
            'actions'         => AuditLog::distinctActions(),
            'paginator'       => $paginator,
            'paginationPath'  => 'audit',
            'paginationQuery' => $filters,
        ]);
    }
}
