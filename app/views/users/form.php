<?php
$isEdit     = ($mode ?? 'create') === 'edit' && is_array($user ?? null);
$formAction = $isEdit ? url('users/' . $user['id']) : url('users');
?>
<div class="page-head reveal">
    <div>
        <h1><?= $isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></h1>
        <p class="muted"><?= $isEdit ? 'Perbarui data akun pengguna.' : 'Buat akun baru dan tentukan role-nya.' ?></p>
    </div>
</div>

<div class="card reveal">
    <form method="post" action="<?= e($formAction) ?>" class="form">
        <?= csrf_field() ?>

        <label>Nama <span class="req">*</span>
            <input type="text" name="nama" value="<?= e(old('nama', $user['nama'] ?? '')) ?>" required>
        </label>

        <label>Username <span class="req">*</span>
            <input type="text" name="username" value="<?= e(old('username', $user['username'] ?? '')) ?>" required>
        </label>

        <label>Email
            <input type="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>">
        </label>

        <label>Role
            <select name="role">
                <?php foreach (ROLES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= old('role', $user['role'] ?? ROLE_USER) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Status
            <select name="status">
                <option value="aktif" <?= old('status', $user['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= old('status', $user['status'] ?? 'aktif') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </label>

        <label>Password <?= $isEdit ? '<span class="muted">(kosongkan bila tidak diubah)</span>' : '<span class="req">*</span>' ?>
            <input type="password" name="password" <?= $isEdit ? '' : 'required' ?> minlength="8">
        </label>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a class="btn" href="<?= e(url('users')) ?>">Batal</a>
        </div>
    </form>
</div>
<?php clear_old(); ?>
