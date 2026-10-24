<div class="page-head reveal">
    <div>
        <h1>Log Aktivitas</h1>
        <p class="muted">Riwayat seluruh aktivitas sistem (append-only).</p>
    </div>
</div>

<form method="get" action="<?= e(url('audit')) ?>" class="filters reveal">
    <input type="search" name="q" placeholder="Cari keterangan / nama / IP" value="<?= e($filters['q'] ?? '') ?>">
    <select name="aksi">
        <option value="">Semua Aksi</option>
        <?php foreach (($actions ?? []) as $aksi): ?>
            <option value="<?= e($aksi) ?>" <?= ($filters['aksi'] ?? '') === $aksi ? 'selected' : '' ?>><?= e($aksi) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
</form>

<div class="table-wrap reveal">
    <table class="table">
        <thead>
            <tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Target</th><th>Keterangan</th><th>IP Pengguna</th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6" class="empty-cell">
                    <div class="empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
                        <p>Tidak ada log aktivitas yang cocok.</p>
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td data-label="Waktu"><?= e($log['created_at']) ?></td>
                    <td data-label="Pengguna"><?= e($log['user_nama'] ?? '-') ?></td>
                    <td data-label="Aksi"><span class="badge"><?= e($log['aksi']) ?></span></td>
                    <td data-label="Target"><?= e(($log['target_tabel'] ?? '') . ($log['target_id'] ? '#' . $log['target_id'] : '')) ?></td>
                    <td data-label="Keterangan"><?= e($log['keterangan'] ?? '') ?></td>
                    <td data-label="IP Pengguna" title="Alamat IP saat aktivitas dicatat"><?= e(client_ip_label($log['ip'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require APP_PATH . '/views/partials/pagination.php'; ?>
