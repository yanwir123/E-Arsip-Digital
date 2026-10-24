<?php
$valKode  = old('kode', $arsip['kode'] ?? '');
$valJudul = old('judul', $arsip['judul'] ?? '');
$valKat   = old('kategori', $arsip['kategori'] ?? '');
$valDesk  = old('deskripsi', $arsip['deskripsi'] ?? '');
?>
<div class="page-head reveal">
    <div>
        <h1>Ubah Arsip</h1>
        <p class="muted">Perbarui data arsip. Biarkan bagian File kosong bila tidak ingin mengganti berkas.</p>
    </div>
</div>

<div class="card reveal">
    <form method="post" action="<?= e(url('arsip/' . $arsip['id'])) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>

        <label>Kode Arsip <span class="muted">(opsional)</span>
            <input type="text" name="kode" value="<?= e($valKode) ?>">
        </label>

        <label>Judul <span class="req">*</span>
            <input type="text" name="judul" value="<?= e($valJudul) ?>" required>
        </label>

        <label>Kategori
            <input type="text" name="kategori" value="<?= e($valKat) ?>">
        </label>

        <label>Deskripsi
            <textarea name="deskripsi" rows="4"><?= e($valDesk) ?></textarea>
        </label>

        <label>File Saat Ini
            <input type="text" value="<?= e($arsip['file_asli']) ?> (<?= e(format_size((int) $arsip['file_ukuran'])) ?>)" readonly>
        </label>

        <label>Ganti File <span class="muted">(opsional)</span>
            <input type="file" name="file"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.zip">
            <small class="muted">Maks <?= e(format_size(UPLOAD_MAX_SIZE)) ?>.</small>
        </label>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a class="btn" href="<?= e(url('arsip/' . $arsip['id'])) ?>">Batal</a>
        </div>
    </form>
</div>
<?php clear_old(); ?>
