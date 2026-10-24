<?php $title = '403'; ?>
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
                    <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                </svg>
            </div>
            <h1 class="error-code">403</h1>
            <p class="muted">Anda tidak memiliki hak akses untuk halaman ini.</p>
            <a class="btn btn-primary btn-block" href="<?= e(url('dashboard')) ?>">Kembali ke Dashboard</a>
        </div>
    </div>
</body>
</html>
