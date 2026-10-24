<div class="page-head reveal">
    <h1>Izin Akses: <?= e($arsip['judul']) ?></h1>
    <span class="badge badge-<?= e($arsip['status']) ?>"><?= e(status_label($arsip['status'])) ?></span>
</div>
<p class="muted">Centang pengguna yang boleh mengakses arsip ini.</p>

<div class="card reveal">
    <form method="post" action="<?= e(url('admin/arsip/' . $arsip['id'] . '/akses')) ?>" class="form">
        <?= csrf_field() ?>

        <div class="checklist">
            <?php foreach ($users as $u): ?>
                <label class="check-item">
                    <input type="checkbox" name="user_ids[]" value="<?= e($u['id']) ?>"
                        <?= in_array((int) $u['id'], $access_ids, true) ? 'checked' : '' ?>>
                    <?= e($u['nama']) ?> <span class="muted">(<?= e(role_label($u['role'])) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Simpan Izin</button>
            <a class="btn" href="<?= e(url('arsip/' . $arsip['id'])) ?>">Kembali</a>
        </div>
    </form>
</div>

<h2 class="reveal">Riwayat Perubahan Izin</h2>
<div class="table-wrap reveal">
    <table class="table">
        <thead><tr><th>Waktu</th><th>Oleh</th><th>Aksi</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr>
                <td data-label="Waktu"><?= e($h['created_at']) ?></td>
                <td data-label="Oleh"><?= e($h['user_nama'] ?? '-') ?></td>
                <td data-label="Aksi"><?= e($h['aksi']) ?></td>
                <td data-label="Keterangan"><?= e($h['keterangan'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
