<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Arsip;
use App\Models\AuditLog;
use App\Models\User;

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (!auth_check()) {
            redirect('login');
        }

        $user = auth_user();

        $data = [
            'title'       => 'Dashboard',
            'user'        => $user,
            'scopeAll'    => sees_all_arsip($user),
            'statusCount' => Arsip::countByStatus($user),
            'arsipTotal'  => Arsip::countAccessible($user),
            'recentArsip' => Arsip::listAccessible($user, [], 10),
            'roleCount'   => in_array($user['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true)
                                ? User::countByRole()
                                : null,
            'recentLogs'  => in_array($user['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true)
                                ? AuditLog::recent(8)
                                : [],
        ];

        $this->view('dashboard/index', $data);
    }
}
