<div class="page-head reveal">
    <div>
        <h1>Blokir Pengguna</h1>
        <p class="muted">Sanksi aktif akibat pelanggaran kode etik. Buka blokir untuk memulihkan akses login.</p>
    </div>
    <span class="badge badge-pending"><?= e(count($bans)) ?> aktif</span>
</div>

<?php if (empty($bans)): ?>
    <div class="card reveal">
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>
            <p>Tidak ada pengguna yang sedang diblokir.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap reveal">
        <table class="table">
            <thead>
                <tr><th>Pengguna</th><th>Tingkat</th><th>Alasan</th><th>Mulai</th><th>Berakhir</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($bans as $b): ?>
                <tr>
                    <td data-label="Pengguna">
                        <span>
                            <strong><?= e($b['user_nama']) ?></strong><br>
                            <small class="muted">@<?= e($b['user_username']) ?></small>
                        </span>
                    </td>
                    <td data-label="Tingkat"><span class="badge badge-penalty-<?= e($b['tingkat']) ?>"><?= e(penalty_label($b['tingkat'])) ?></span></td>
                    <td data-label="Alasan"><?= e($b['alasan'] ?? '-') ?></td>
                    <td data-label="Mulai"><?= e($b['banned_at']) ?></td>
                    <td data-label="Berakhir"><?= $b['banned_until'] ? e($b['banned_until']) : '<em>Permanen</em>' ?></td>
                    <td class="actions">
                        <form method="post" action="<?= e(url('admin/blokir/' . $b['id'] . '/buka')) ?>" class="inline"
                              data-confirm="Buka blokir untuk <?= e($b['user_nama']) ?>?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-primary">Buka Blokir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
