<?php
$status     = (string) ($user['identity_status'] ?? IDENTITY_BELUM);
$hasFoto    = !empty($user['foto_profil']);
$namaInit   = strtoupper(function_exists('mb_substr') ? mb_substr((string) $user['nama'], 0, 1) : substr((string) $user['nama'], 0, 1));
$statusBadge = match ($status) {
    IDENTITY_TERVERIFIKASI => 'badge-approved',
    IDENTITY_DITOLAK       => 'badge-rejected',
    IDENTITY_MENUNGGU      => 'badge-pending',
    default                => 'badge-role',
};
$edit      = (string) ($edit ?? '');
$profilUrl = url('profil');
$bisaAjukan = $status !== IDENTITY_TERVERIFIKASI && $status !== IDENTITY_MENUNGGU;
?>
<div class="page-head reveal">
    <div>
        <h1>Profil Saya</h1>
        <p class="muted">Data pribadi, foto, dan keamanan akun Anda.</p>
    </div>
    <span class="badge <?= e($statusBadge) ?>"><?= e(identity_label($status)) ?></span>
</div>

<?php if ($status === IDENTITY_TERVERIFIKASI): ?>
    <div class="alert alert-success reveal">Identitas Anda sudah terverifikasi. Terima kasih.</div>
<?php elseif ($status === IDENTITY_MENUNGGU): ?>
    <div class="alert alert-info reveal">Biodata Anda sedang menunggu verifikasi admin/petugas.</div>
<?php elseif ($status === IDENTITY_DITOLAK): ?>
    <div class="alert alert-error reveal">
        Verifikasi identitas ditolak: <?= e($user['identity_reject_reason'] ?? '-') ?>. Silakan perbaiki biodata lalu ajukan kembali.
    </div>
<?php else: ?>
    <div class="alert alert-info reveal">Biodata Anda belum lengkap. Klik "Edit Profil" untuk melengkapinya, lalu ajukan verifikasi.</div>
<?php endif; ?>

<div class="profile-head reveal">
    <div class="profile-avatar">
        <?php if ($hasFoto): ?>
            <img class="avatar-img avatar-lg" src="<?= e(url('foto/' . $user['id'])) ?>" alt="Foto profil">
        <?php else: ?>
            <span class="avatar avatar-lg"><?= e($namaInit) ?></span>
        <?php endif; ?>
    </div>
    <div class="profile-meta">
        <h2 class="profile-name"><?= e($user['nama']) ?></h2>
        <p class="muted">@<?= e($user['username']) ?> &middot; <?= e(role_label($user['role'])) ?></p>
        <p class="muted"><?= e($user['email'] ?? '-') ?></p>
    </div>
</div>

<?php if ($edit === ''): ?>
    <?php // ---------- Mode lihat: tampilkan data pribadi (rapih) ---------- ?>
    <h2 class="reveal">Data Pribadi</h2>
    <div class="table-wrap reveal">
        <table class="table table-detail">
            <tr><th>Nama Lengkap</th><td><?= e($user['nama']) ?></td></tr>
            <tr><th>Username</th><td>@<?= e($user['username']) ?></td></tr>
            <tr><th>Email</th><td><?= e($user['email'] ?? '-') ?></td></tr>
            <tr><th>Instansi</th><td><?= e($instansiNama ?? '-') ?></td></tr>
            <tr><th>Nomor Induk</th><td><?= e($user['no_induk'] ?? '-') ?: '-' ?></td></tr>
            <tr><th>NIP</th><td><?= e($user['nip'] ?? '-') ?: '-' ?></td></tr>
            <tr><th>Jabatan</th><td><?= e($user['jabatan'] ?? '-') ?: '-' ?></td></tr>
            <tr><th>Telepon</th><td><?= e($user['telepon'] ?? '-') ?: '-' ?></td></tr>
            <tr><th>Alamat</th><td><?= nl2br(e($user['alamat'] ?? '-') ?: '-') ?></td></tr>
        </table>
    </div>

    <div class="actions reveal">
        <a class="btn btn-primary" href="<?= e($profilUrl) ?>?edit=biodata">Edit Profil</a>
        <a class="btn" href="<?= e($profilUrl) ?>?edit=password">Ubah Password</a>

        <?php if ($bisaAjukan): ?>
            <form method="post" action="<?= e(url('profil/submit')) ?>" class="inline"
                  data-confirm="Ajukan biodata untuk diverifikasi?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-accent">Ajukan Verifikasi</button>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($edit === 'biodata'): ?>
    <?php // ---------- Mode edit biodata ---------- ?>
    <h2 class="reveal">Edit Biodata</h2>
    <div class="card reveal">
        <form method="post" action="<?= e($profilUrl) ?>" class="form" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <label>Nama Lengkap <span class="req">*</span>
                <input type="text" name="nama" value="<?= e(old('nama', $user['nama'])) ?>" required>
            </label>

            <label>Instansi
                <select name="instansi_id">
                    <option value="">- Pilih Instansi -</option>
                    <?php foreach ($instansi as $ins): ?>
                        <option value="<?= e($ins['id']) ?>" <?= (string) old('instansi_id', $user['instansi_id'] ?? '') === (string) $ins['id'] ? 'selected' : '' ?>>
                            <?= e($ins['kode'] . ' - ' . $ins['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Nomor Induk
                <input type="text" name="no_induk" value="<?= e(old('no_induk', $user['no_induk'] ?? '')) ?>" placeholder="Mis. 19850101">
            </label>

            <label>NIP
                <input type="text" name="nip" value="<?= e(old('nip', $user['nip'] ?? '')) ?>" placeholder="Nomor Induk Pegawai (opsional)">
            </label>

            <label>Jabatan
                <input type="text" name="jabatan" value="<?= e(old('jabatan', $user['jabatan'] ?? '')) ?>" placeholder="Mis. Staf Kearsipan">
            </label>

            <label>Telepon
                <input type="text" name="telepon" value="<?= e(old('telepon', $user['telepon'] ?? '')) ?>" placeholder="Mis. 08123456789">
            </label>

            <label>Alamat
                <input type="text" name="alamat" value="<?= e(old('alamat', $user['alamat'] ?? '')) ?>" placeholder="Alamat domisili">
            </label>

            <label>Foto Profil <span class="muted">(JPG/PNG/WEBP, maks 2 MB)</span>
                <input type="file" name="foto_profil" accept="image/jpeg,image/png,image/webp">
            </label>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                <a class="btn" href="<?= e($profilUrl) ?>">Batal</a>
            </div>
        </form>
    </div>

<?php elseif ($edit === 'password'): ?>
    <?php // ---------- Mode ubah password ---------- ?>
    <h2 class="reveal">Ubah Password</h2>
    <div class="card reveal">
        <form method="post" action="<?= e(url('profil/password')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Password Lama
                <input type="password" name="password_lama" required>
            </label>
            <label>Password Baru
                <input type="password" name="password_baru" required minlength="8">
            </label>
            <label>Konfirmasi Password Baru
                <input type="password" name="password_confirm" required minlength="8">
            </label>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                <a class="btn" href="<?= e($profilUrl) ?>">Batal</a>
            </div>
        </form>
    </div>
<?php endif; ?>
<?php clear_old(); ?>
