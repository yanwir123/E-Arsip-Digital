<?php
$statusIcon = [
    ARSIP_PENDING  => '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>',
    ARSIP_APPROVED => '<path d="M20 6 9 17l-5-5"/>',
    ARSIP_REJECTED => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
];
$statusAccent = [
    ARSIP_PENDING  => 'stat-accent',
    ARSIP_APPROVED => 'stat-success',
    ARSIP_REJECTED => 'stat-danger',
];
?>
<div class="page-head reveal">
    <div>
        <h1>Dashboard</h1>
        <p class="muted">Halo, <strong><?= e($user['nama']) ?></strong> &mdash; Anda masuk sebagai <?= e(role_label($user['role'])) ?>.</p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('arsip/create')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M4 19h16"/></svg>
        Unggah Arsip
    </a>
</div>

<div class="grid reveal">
    <?php foreach (ARSIP_STATUS as $key => $label): ?>
        <div class="stat <?= e($statusAccent[$key] ?? '') ?>">
            <span class="stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $statusIcon[$key] ?? '' ?></svg>
            </span>
            <span class="stat-value"><?= e($statusCount[$key] ?? 0) ?></span>
            <span class="stat-label"><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="reveal"><?= $scopeAll ? 'Arsip Terbaru' : 'Arsip Anda' ?></h2>
<div class="table-wrap reveal">
    <table class="table">
        <thead>
            <tr>
                <th>Kode</th><th>Judul</th><th>Kategori</th><th>Status</th>
                <?php if ($scopeAll): ?><th>Pengunggah</th><?php endif; ?>
                <th>Tanggal</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($recentArsip)): ?>
            <tr>
                <td colspan="<?= $scopeAll ? 7 : 6 ?>" class="empty-cell">
                    <div class="empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7"/><path d="M2 4h20v3H2z"/><path d="M9 12h6"/></svg>
                        <p>Belum ada arsip yang bisa ditampilkan.</p>
                        <a class="btn btn-accent btn-sm" href="<?= e(url('arsip/create')) ?>">Unggah arsip pertama Anda</a>
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($recentArsip as $a): ?>
                <tr>
                    <td data-label="Kode"><?= e($a['kode'] ?? '-') ?></td>
                    <td data-label="Judul"><a href="<?= e(url('arsip/' . $a['id'])) ?>"><?= e($a['judul']) ?></a></td>
                    <td data-label="Kategori"><?= e($a['kategori'] ?? '-') ?></td>
                    <td data-label="Status"><span class="badge badge-<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></td>
                    <?php if ($scopeAll): ?><td data-label="Pengunggah"><?= e($a['uploader_nama']) ?></td><?php endif; ?>
                    <td data-label="Tanggal"><?= e($a['created_at']) ?></td>
                    <td class="actions">
                        <a class="btn-link" href="<?= e(url('arsip/' . $a['id'])) ?>">Detail</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<p class="reveal">
    <a class="btn" href="<?= e(url('arsip')) ?>">
        Lihat semua arsip (<?= (int) ($arsipTotal ?? count($recentArsip)) ?>)
    </a>
</p>

<?php if ($roleCount !== null): ?>
    <h2 class="reveal">Jumlah Pengguna per Role</h2>
    <?php $roleTints = ['stat-pastel', 'stat-lavender', 'stat-accent', 'stat-success', '']; ?>
    <div class="grid reveal">
        <?php $tintIndex = 0; foreach ($roleCount as $role => $total): ?>
            <div class="stat <?= e($roleTints[$tintIndex % count($roleTints)]) ?>">
                <span class="stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <span class="stat-value"><?= e($total) ?></span>
                <span class="stat-label"><?= e(role_label($role)) ?></span>
            </div>
        <?php $tintIndex++; endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($recentLogs)): ?>
    <h2 class="reveal">Aktivitas Terbaru</h2>
    <div class="table-wrap reveal">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th></tr></thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td data-label="Waktu"><?= e($log['created_at']) ?></td>
                    <td data-label="Pengguna"><?= e($log['user_nama'] ?? '-') ?></td>
                    <td data-label="Aksi"><span class="badge badge-role"><?= e($log['aksi']) ?></span></td>
                    <td data-label="Keterangan"><?= e($log['keterangan'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p><a class="btn" href="<?= e(url('audit')) ?>">Lihat semua log</a></p>
<?php endif; ?>
