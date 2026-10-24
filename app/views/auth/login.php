<div class="card login-card">
    <h1>SELAMAT DATANG</h1>
    <p class="muted">Gunakan akun Anda untuk mengakses <?= e(APP_NAME) ?>.</p>

    <form method="post" action="<?= e(url('login')) ?>">
        <?= csrf_field() ?>

        <label>Username
            <input type="text" name="username" value="<?= e(old('username')) ?>" required autofocus>
        </label>

        <label>Password
            <input type="password" name="password" required>
        </label>

        <?php if (RECAPTCHA_SITE_KEY !== ''): ?>
            <div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>"></div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
    </form>

    <p class="auth-alt">
        Belum punya akun? <a href="<?= e(url('register')) ?>">Daftar</a>
    </p>
</div>
<?php if (RECAPTCHA_SITE_KEY !== ''): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
<?php clear_old(); ?>
