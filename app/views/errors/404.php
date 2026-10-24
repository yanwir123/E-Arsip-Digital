<?php $title = '404'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <div class="login-card text-center">
            <div class="error-sticker-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </div>
            <h1 class="error-code">404</h1>
            <p class="muted">Halaman yang Anda cari tidak ditemukan.</p>
            <a class="btn btn-primary btn-block" href="<?= e(url('dashboard')) ?>">Kembali ke Dashboard</a>
        </div>
    </div>
</body>
</html>
