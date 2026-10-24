<div class="page-head reveal">
    <div>
        <h1>Validasi Arsip</h1>
        <p class="muted">Setujui atau tolak arsip yang diunggah pengguna.</p>
    </div>
    <span class="badge badge-pending"><?= e(count($pending)) ?> menunggu</span>
</div>

<?php if (empty($pending)): ?>
    <div class="card reveal">
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>
            <p>Tidak ada arsip yang menunggu validasi.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap reveal">
        <table class="table">
            <thead>
                <tr><th>Judul</th><th>Pengunggah</th><th>File</th><th>Tanggal</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $a): ?>
                <tr>
                    <td data-label="Judul">
                        <span>
                            <a href="<?= e(url('arsip/' . $a['id'])) ?>"><?= e($a['judul']) ?></a>
                            <?php if ($a['kode']): ?><br><small class="muted"><?= e($a['kode']) ?></small><?php endif; ?>
                        </span>
                    </td>
                    <td data-label="Pengunggah"><?= e($a['uploader_nama']) ?></td>
                    <td data-label="File">
                        <?php if (preview_kind((string) $a['file_tipe']) !== ''): ?>
                            <a href="<?= e(url('arsip/' . $a['id'] . '/preview')) ?>" target="_blank" rel="noopener"><?= e($a['file_asli']) ?></a>
                        <?php else: ?>
                            <?= e($a['file_asli']) ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Tanggal"><?= e($a['created_at']) ?></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= e(url('arsip/' . $a['id'] . '/preview')) ?>" target="_blank" rel="noopener">Periksa</a>

                        <form method="post" action="<?= e(url('admin/arsip/' . $a['id'] . '/approve')) ?>" class="reject-form">
                            <?= csrf_field() ?>
                            <select name="risk_level" aria-label="Tingkat risiko">
                                <option value="<?= e(RISK_HIJAU) ?>">Risiko: Aman</option>
                                <option value="<?= e(RISK_KUNING) ?>">Risiko: Perhatian</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Setujui</button>
                        </form>

                        <form method="post" action="<?= e(url('admin/arsip/' . $a['id'] . '/reject')) ?>" class="reject-form">
                            <?= csrf_field() ?>
                            <input type="text" name="alasan" placeholder="Alasan penolakan" required>
                            <select name="tingkat" aria-label="Sanksi pelanggaran kode etik">
                                <option value="">Tanpa sanksi</option>
                                <?php foreach (PENALTY_LEVEL as $key => $label): ?>
                                    <option value="<?= e($key) ?>">Sanksi: <?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
