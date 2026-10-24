<div class="page-head reveal">
    <div>
        <h1>Validasi Identitas</h1>
        <p class="muted">Periksa dan setujui biodata/identitas yang diajukan pengguna, petugas, maupun admin.</p>
    </div>
    <span class="badge badge-pending"><?= e($counts[IDENTITY_MENUNGGU] ?? 0) ?> menunggu</span>
</div>

<form method="get" action="<?= e(url('admin/identitas')) ?>" class="filters reveal">
    <select name="status">
        <option value="<?= e(IDENTITY_MENUNGGU) ?>" <?= $status === IDENTITY_MENUNGGU ? 'selected' : '' ?>>Menunggu Verifikasi</option>
        <option value="<?= e(IDENTITY_BELUM) ?>" <?= $status === IDENTITY_BELUM ? 'selected' : '' ?>>Belum Lengkap</option>
        <option value="<?= e(IDENTITY_TERVERIFIKASI) ?>" <?= $status === IDENTITY_TERVERIFIKASI ? 'selected' : '' ?>>Terverifikasi</option>
        <option value="<?= e(IDENTITY_DITOLAK) ?>" <?= $status === IDENTITY_DITOLAK ? 'selected' : '' ?>>Ditolak</option>
        <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua</option>
    </select>
    <button class="btn" type="submit">Tampilkan</button>
</form>

<?php if (empty($users)): ?>
    <div class="card reveal">
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <p>Tidak ada data identitas pada kategori ini.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap reveal">
        <table class="table">
            <thead>
                <tr><th>Pengguna</th><th>Instansi</th><th>No. Induk / NIP</th><th>Jabatan</th><th>Kontak</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td data-label="Pengguna">
                        <span class="user-cell">
                            <?php if (!empty($u['foto_profil'])): ?>
                                <img class="avatar-img avatar-sm" src="<?= e(url('foto/' . $u['id'])) ?>" alt="">
                            <?php else: ?>
                                <span class="avatar avatar-sm"><?= e(strtoupper(substr((string) $u['nama'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <span>
                                <strong><?= e($u['nama']) ?></strong><br>
                                <small class="muted">@<?= e($u['username']) ?> &middot; <?= e(role_label($u['role'])) ?></small>
                            </span>
                        </span>
                    </td>
                    <td data-label="Instansi">
                        <?= $u['instansi_nama'] ? e($u['instansi_nama']) : '<span class="muted">-</span>' ?>
                    </td>
                    <td data-label="No. Induk / NIP">
                        <small><?= $u['no_induk'] ? 'NI: ' . e($u['no_induk']) : '' ?></small><br>
                        <small><?= $u['nip'] ? 'NIP: ' . e($u['nip']) : '' ?></small>
                        <?= (!$u['no_induk'] && !$u['nip']) ? '<span class="muted">-</span>' : '' ?>
                    </td>
                    <td data-label="Jabatan"><?= $u['jabatan'] ? e($u['jabatan']) : '<span class="muted">-</span>' ?></td>
                    <td data-label="Kontak">
                        <small><?= $u['telepon'] ? e($u['telepon']) : '<span class="muted">-</span>' ?></small><br>
                        <small class="muted"><?= e($u['email'] ?? '-') ?></small>
                    </td>
                    <td data-label="Status">
                        <span class="badge badge-identity-<?= e($u['identity_status']) ?>"><?= e(identity_label($u['identity_status'])) ?></span>
                        <?php if ($u['identity_status'] === IDENTITY_DITOLAK && !empty($u['identity_reject_reason'])): ?>
                            <br><small class="muted"><?= e($u['identity_reject_reason']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if ($u['identity_status'] === IDENTITY_MENUNGGU): ?>
                            <form method="post" action="<?= e(url('admin/identitas/' . $u['id'] . '/approve')) ?>" class="inline"
                                  data-confirm="Setujui identitas <?= e($u['nama']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-primary">Setujui</button>
                            </form>
                            <form method="post" action="<?= e(url('admin/identitas/' . $u['id'] . '/reject')) ?>" class="reject-form">
                                <?= csrf_field() ?>
                                <input type="text" name="alasan" placeholder="Alasan penolakan" required>
                                <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
