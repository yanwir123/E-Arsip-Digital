<div class="page-head reveal">
    <h1>Daftar Arsip</h1>
    <a class="btn btn-primary" href="<?= e(url('arsip/create')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M4 19h16"/></svg>
        Unggah Arsip
    </a>
</div>

<form method="get" action="<?= e(url('arsip')) ?>" class="filters reveal">
    <input type="search" name="q" placeholder="Cari judul / kode" value="<?= e($filters['q'] ?? '') ?>">
    <select name="status">
        <option value="">Semua Status</option>
        <?php foreach (ARSIP_STATUS as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>>
                <?= e($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        Filter
    </button>
</form>

<div class="table-wrap reveal">
    <?php $showAccessCount = !in_array(auth_user()['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true); ?>
    <table class="table">
        <thead>
            <tr>
                <th>Kode</th><th>Judul</th><th>Kategori</th>
                <th>Status</th><th>Risiko</th><th>Pengunggah</th><th>Tanggal</th><th></th>
                <?php if ($showAccessCount): ?><th>Akses</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($arsip)): ?>
            <tr>
                <td colspan="<?= $showAccessCount ? 9 : 8 ?>" class="empty-cell">
                    <div class="empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7"/><path d="M2 4h20v3H2z"/><path d="M9 12h6"/></svg>
                        <p><?= ($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' ? 'Tidak ada arsip yang cocok dengan filter.' : 'Belum ada arsip.' ?></p>
                        <a class="btn btn-accent btn-sm" href="<?= e(url('arsip/create')) ?>">Unggah Arsip</a>
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($arsip as $a): ?>
                <tr>
                    <td data-label="Kode"><?= e($a['kode'] ?? '-') ?></td>
                    <td data-label="Judul"><a href="<?= e(url('arsip/' . $a['id'])) ?>"><?= e($a['judul']) ?></a></td>
                    <td data-label="Kategori"><?= e($a['kategori'] ?? '-') ?></td>
                    <td data-label="Status"><span class="badge badge-<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></td>
                    <td data-label="Risiko">
                        <?php if ($a['status'] !== ARSIP_PENDING): ?>
                            <span class="badge badge-risk-<?= e($a['risk_level']) ?>"><?= e(risk_label($a['risk_level'])) ?></span>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Pengunggah"><?= e($a['uploader_nama']) ?></td>
                    <td data-label="Tanggal"><?= e($a['created_at']) ?></td>
                    <?php if ($showAccessCount): ?><td data-label="Diakses pengguna lain"><?= e((int) $a['access_count']) ?> pengguna</td><?php endif; ?>
                    <td class="actions">
                        <a href="<?= e(url('arsip/' . $a['id'])) ?>">Detail</a>
                        <?php if ($a['status'] === ARSIP_APPROVED): ?>
                            <a href="<?= e(url('arsip/' . $a['id'] . '/download')) ?>">Unduh</a>
                        <?php endif; ?>
                        <?php if (can_manage_arsip($a)): ?>
                            <a href="<?= e(url('arsip/' . $a['id'] . '/edit')) ?>">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require APP_PATH . '/views/partials/pagination.php'; ?>
