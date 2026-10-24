<div class="card login-card">
    <h1>Daftar</h1>
    <p class="muted">Buat akun &mdash; email Anda akan diverifikasi dengan kode OTP.</p>

    <form method="post" action="<?= e(url('register')) ?>">
        <?= csrf_field() ?>

        <label>Nama Lengkap
            <input type="text" name="nama" value="<?= e(old('nama')) ?>" required autofocus>
        </label>

        <label>Username
            <input type="text" name="username" value="<?= e(old('username')) ?>" required
                   pattern="[a-zA-Z0-9._]{3,50}" title="3-50 karakter (huruf, angka, titik, underscore)">
        </label>

        <label>Email
            <input type="email" name="email" value="<?= e(old('email')) ?>" required>
        </label>

        <label>Password
            <input type="password" name="password" required minlength="8"
                   title="Minimal 8 karakter, memuat huruf dan angka">
        </label>

        <label>Konfirmasi Password
            <input type="password" name="password_confirm" required minlength="8">
        </label>

        <?php if (RECAPTCHA_SITE_KEY !== ''): ?>
            <div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>"></div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block">Daftar</button>
    </form>

    <p class="auth-alt">
        Sudah punya akun? <a href="<?= e(url('login')) ?>">Masuk</a>
    </p>
</div>
<?php if (RECAPTCHA_SITE_KEY !== ''): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
<?php clear_old(); ?>
