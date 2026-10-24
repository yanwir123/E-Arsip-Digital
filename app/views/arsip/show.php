<?php
$me      = auth_user();
$isAdmin = in_array($me['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true);
$isStaff = sees_all_arsip($me);
$isOwner = (int) $arsip['uploader_id'] === (int) $me['id'];
$canDelete = $isAdmin || ($isOwner && $arsip['status'] !== ARSIP_APPROVED);
$kind      = preview_kind((string) $arsip['file_tipe']);
$canSeeFile = $isStaff || $isOwner || $arsip['status'] === ARSIP_APPROVED;
?>
<div class="page-head reveal">
    <h1><?= e($arsip['judul']) ?></h1>
    <span class="badge badge-<?= e($arsip['status']) ?>"><?= e(status_label($arsip['status'])) ?></span>
</div>
<div class="table-wrap reveal">
    <table class="table table-detail">
        <tr><th>Kode</th><td><?= e($arsip['kode'] ?? '-') ?></td></tr>
        <tr><th>Kategori</th><td><?= e($arsip['kategori'] ?? '-') ?></td></tr>
        <tr><th>Deskripsi</th><td><?= nl2br(e($arsip['deskripsi'] ?? '-')) ?></td></tr>
        <tr><th>File</th><td><?= e($arsip['file_asli']) ?> (<?= e(format_size((int) $arsip['file_ukuran'])) ?>)</td></tr>
        <tr><th>Pengunggah</th><td><?= e($arsip['uploader_nama']) ?></td></tr>
        <tr><th>Diunggah</th><td><?= e($arsip['created_at']) ?></td></tr>
        <?php if ($arsip['status'] !== ARSIP_PENDING): ?>
            <tr><th>Tingkat Risiko</th><td><span class="badge badge-risk-<?= e($arsip['risk_level']) ?>"><?= e(risk_label($arsip['risk_level'])) ?></span></td></tr>
        <?php endif; ?>
        <?php if ($arsip['validator_nama']): ?>
            <tr><th>Divalidasi oleh</th><td><?= e($arsip['validator_nama']) ?> pada <?= e($arsip['validated_at']) ?></td></tr>
        <?php endif; ?>
        <?php if ($arsip['status'] === ARSIP_REJECTED && $arsip['alasan_reject']): ?>
            <tr><th>Alasan Ditolak</th><td class="text-danger"><?= e($arsip['alasan_reject']) ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="actions reveal">
    <?php if ($canSeeFile): ?>
        <?php if ($kind !== ''): ?>
            <a class="btn" href="<?= e(url('arsip/' . $arsip['id'] . '/preview')) ?>" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                Lihat Berkas
            </a>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('arsip/' . $arsip['id'] . '/download')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 19h16"/></svg>
            Unduh File
        </a>
    <?php endif; ?>

    <?php if ($isAdmin && $arsip['status'] !== ARSIP_REJECTED): ?>
        <a class="btn" href="<?= e(url('admin/arsip/' . $arsip['id'] . '/akses')) ?>">Atur Izin Akses</a>
    <?php endif; ?>

    <?php if ($canDelete): ?>
        <a class="btn" href="<?= e(url('arsip/' . $arsip['id'] . '/edit')) ?>">Edit</a>

        <form method="post" action="<?= e(url('arsip/' . $arsip['id'] . '/delete')) ?>" class="inline delete-form"
              data-confirm="PERINGATAN! Hapus arsip '<?= e($arsip['judul']) ?>' beserta berkasnya? Tindakan ini permanen dan tidak dapat dibatalkan. Klik OK untuk menghapus.">
            <?= csrf_field() ?>
            <input type="text" name="alasan_hapus" class="reason-input" placeholder="Keterangan (opsional)" maxlength="255">
            <button type="submit" class="btn btn-danger">Hapus</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($canSeeFile && $kind !== ''): ?>
    <h2 class="reveal">Pratinjau Berkas</h2>
    <div class="preview-wrap reveal">
        <?php if ($kind === 'image'): ?>
            <img class="preview-img" src="<?= e(url('arsip/' . $arsip['id'] . '/preview')) ?>" alt="Pratinjau <?= e($arsip['judul']) ?>">
        <?php else: ?>
            <iframe class="preview-frame" src="<?= e(url('arsip/' . $arsip['id'] . '/preview')) ?>" title="Pratinjau <?= e($arsip['judul']) ?>"></iframe>
        <?php endif; ?>
    </div>
    <p class="muted reveal">Pratinjau ini disediakan untuk keperluan pemeriksaan &amp; validasi berkas.</p>
<?php endif; ?>

<?php
$historyFilterActive = $historyFilters['q'] !== ''
    || $historyFilters['aksi'] !== ''
    || $historyFilters['user_id'] !== '';
?>
<div class="section-head reveal" id="histori">
    <div>
        <h2>Histori Arsip</h2>
        <p class="muted">Aktivitas terbaru untuk arsip ini. Gunakan filter untuk mempersempit riwayat.</p>
    </div>
    <span class="badge"><?= e($historyPaginator['total']) ?> <?= $historyFilterActive ? 'hasil' : 'aktivitas' ?></span>
</div>

<form method="get" action="<?= e(url('arsip/' . $arsip['id'])) ?>#histori" class="filters reveal">
    <input type="search" name="history_q" placeholder="Cari keterangan / nama" aria-label="Cari histori" value="<?= e($historyFilters['q']) ?>">

    <select name="history_aksi" aria-label="Filter aksi histori">
        <option value="">Semua Aksi</option>
        <?php foreach ($historyActions as $aksi): ?>
            <option value="<?= e($aksi) ?>" <?= $historyFilters['aksi'] === $aksi ? 'selected' : '' ?>>
                <?= e($aksi) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="history_user" aria-label="Filter pengguna histori">
        <option value="">Semua Pengguna</option>
        <?php foreach ($historyUsers as $historyUser): ?>
            <option value="<?= e($historyUser['user_id']) ?>" <?= $historyFilters['user_id'] === (string) $historyUser['user_id'] ? 'selected' : '' ?>>
                <?= e($historyUser['nama'] ?? ('Pengguna #' . $historyUser['user_id'])) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="history_per_page" aria-label="Jumlah histori per halaman">
        <?php foreach ($historyPerPageOptions as $option): ?>
            <option value="<?= e($option) ?>" <?= $historyPerPage === (int) $option ? 'selected' : '' ?>>
                <?= e($option) ?> per halaman
            </option>
        <?php endforeach; ?>
    </select>

    <div class="filter-actions">
        <button class="btn" type="submit">Filter</button>

        <?php if ($historyFilterActive): ?>
            <a class="btn" href="<?= e(url('arsip/' . $arsip['id'])) ?>#histori">Reset</a>
        <?php endif; ?>
    </div>
</form>

<div class="table-wrap reveal">
    <table class="table">
        <thead><tr><th>Waktu</th><th>Oleh</th><th>Aksi</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php if (empty($history)): ?>
            <tr>
                <td colspan="4" class="empty-cell">
                    <div class="empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>
                        <p><?= $historyFilterActive ? 'Tidak ada histori yang cocok dengan filter.' : 'Belum ada histori untuk arsip ini.' ?></p>
                        <?php if ($historyFilterActive): ?>
                            <a class="btn btn-sm" href="<?= e(url('arsip/' . $arsip['id'])) ?>#histori">Hapus Filter</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php else: ?>
        <?php foreach ($history as $h): ?>
            <tr>
                <td data-label="Waktu"><?= e($h['created_at']) ?></td>
                <td data-label="Oleh"><?= e($h['user_nama'] ?? 'Sistem') ?></td>
                <td data-label="Aksi"><span class="badge"><?= e($h['aksi']) ?></span></td>
                <td data-label="Keterangan"><?= e($h['keterangan'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$paginator          = $historyPaginator;
$paginationPath     = 'arsip/' . $arsip['id'];
$paginationQuery    = $historyQuery;
$paginationPageKey  = 'history_page';
$paginationFragment = '#histori';
require APP_PATH . '/views/partials/pagination.php';
?>
