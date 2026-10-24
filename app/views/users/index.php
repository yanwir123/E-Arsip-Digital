<?php $me = auth_user(); ?>
<div class="page-head reveal">
    <div>
        <h1>Manajemen Pengguna</h1>
        <p class="muted">Kelola akun, role, dan status pengguna sistem.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('users/create')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        Tambah Pengguna
    </a>
</div>

<div class="table-wrap reveal">
    <table class="table">
        <thead>
            <tr><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td data-label="Nama"><?= e($u['nama']) ?></td>
                <td data-label="Username">@<?= e($u['username']) ?></td>
                <td data-label="Email"><?= e($u['email'] ?? '-') ?></td>
                <td data-label="Role"><span class="badge badge-role"><?= e(role_label($u['role'])) ?></span></td>
                <td data-label="Status">
                    <?php if ($u['status'] === 'aktif'): ?>
                        <span class="badge badge-approved">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-rejected">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td data-label="Login Terakhir"><?= e($u['last_login'] ?? '-') ?></td>
                <td class="actions">
                    <a href="<?= e(url('users/' . $u['id'] . '/edit')) ?>">Edit</a>
                    <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                        <form method="post" action="<?= e(url('users/' . $u['id'] . '/delete')) ?>" class="inline"
                              data-confirm="Hapus pengguna ini?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-link text-danger">Hapus</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
