<div class="page-head reveal">
    <div>
        <h1>Unggah Arsip</h1>
        <p class="muted">Arsip akan berstatus <strong>Menunggu Validasi</strong> sampai disetujui admin.</p>
    </div>
</div>

<div class="card reveal">
    <form method="post" action="<?= e(url('arsip')) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>

        <label>Kode Arsip <span class="muted">(opsional)</span>
            <input type="text" name="kode" value="<?= e(old('kode')) ?>">
        </label>

        <label>Judul <span class="req">*</span>
            <input type="text" name="judul" value="<?= e(old('judul')) ?>" required>
        </label>

        <label>Kategori
            <input type="text" name="kategori" value="<?= e(old('kategori')) ?>">
        </label>

        <label>Deskripsi
            <textarea name="deskripsi" rows="4"><?= e(old('deskripsi')) ?></textarea>
        </label>

        <label>File <span class="req">*</span>
            <input type="file" name="file" required
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.zip">
            <small class="muted">Maks <?= e(format_size(UPLOAD_MAX_SIZE)) ?>.</small>
        </label>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Unggah</button>
            <a class="btn" href="<?= e(url('arsip')) ?>">Batal</a>
        </div>
    </form>
</div>
<?php clear_old(); ?>
