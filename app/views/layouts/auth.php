<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
</head>
<body class="auth-body">
    <div class="auth-shell">
        <aside class="auth-visual">
            <a class="brand" href="<?= e(url('/')) ?>">
                <span class="brand-mark"><img src="<?= e(asset('img/logo1.png')) ?>" alt=""></span>
                <span class="brand-text"><b><?= e(APP_NAME) ?></b><small>Sistem Arsip Digital</small></span>
            </a>

            <h2>Kelola arsip digital dengan<br>aman &amp; rapi.</h2>
            <p>Unggah, validasi, dan atur izin akses dokumen dalam satu tempat.</p>

            <ul class="auth-features">
                <li>
                    <span class="tick" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    Verifikasi email dengan kode OTP
                </li>
                <li>
                    <span class="tick" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    Validasi arsip &amp; izin akses per pengguna
                </li>
                <li>
                    <span class="tick" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    Riwayat aktivitas (audit log) lengkap
                </li>
            </ul>
        </aside>

        <section class="auth-panel">
            <div class="auth-wrapper">
                <?php require APP_PATH . '/views/partials/flash.php'; ?>

                <?= $content ?>
            </div>
        </section>
    </div>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
