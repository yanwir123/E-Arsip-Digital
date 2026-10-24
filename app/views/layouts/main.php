<?php
/** @var string $content */
$user = auth_user();
$nama    = (string) ($user['nama'] ?? '');
$initial = $nama !== ''
    ? strtoupper(function_exists('mb_substr') ? mb_substr($nama, 0, 1) : substr($nama, 0, 1))
    : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
</head>
<body class="app-body">
<div class="marquee" aria-hidden="true">
    <div class="marquee-inner">
        <span><?= e(APP_NAME) ?> &nbsp;&#9679;&nbsp; Sistem Arsip Digital &nbsp;&#9679;&nbsp; Aman &nbsp;&#9679;&nbsp; Tertib &nbsp;&#9679;&nbsp; Terverifikasi &nbsp;&#9679;&nbsp;</span>
        <span><?= e(APP_NAME) ?> &nbsp;&#9679;&nbsp; Sistem Arsip Digital &nbsp;&#9679;&nbsp; Aman &nbsp;&#9679;&nbsp; Tertib &nbsp;&#9679;&nbsp; Terverifikasi &nbsp;&#9679;&nbsp;</span>
    </div>
</div>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e(url('dashboard')) ?>">
            <span class="brand-mark"><img src="<?= e(asset('img/logo1.png')) ?>" alt=""></span>
            <span class="brand-text">
                <b><?= e(APP_NAME) ?></b>
                <small>Sistem Arsip Digital</small>
            </span>
        </a>

        <?php if ($user): ?>
        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="main-nav" aria-label="Buka menu navigasi">
            <span></span><span></span><span></span>
        </button>

        <nav class="nav" id="main-nav" data-nav>
            <a class="nav-link" href="<?= e(url('dashboard')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                Dashboard
            </a>
            <a class="nav-link" href="<?= e(url('arsip')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7"/><path d="M2 4h20v3H2z"/><path d="M9 12h6"/></svg>
                Arsip
            </a>
            <a class="nav-link" href="<?= e(url('arsip/create')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M4 19h16"/></svg>
                Unggah
            </a>
            <?php if (has_role(ROLE_ADMIN)): ?>
                <a class="nav-link" href="<?= e(url('admin/validasi')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>
                    Validasi
                </a>
                <a class="nav-link" href="<?= e(url('admin/blokir')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
                    Blokir
                </a>
            <?php endif; ?>
            <?php if (has_role(ROLE_ADMIN) || $user['role'] === ROLE_PETUGAS): ?>
                <a class="nav-link" href="<?= e(url('admin/identitas')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5 18c0-2 2-3 4-3s4 1 4 3"/><path d="M15 9h4M15 13h4"/></svg>
                    Identitas
                </a>
            <?php endif; ?>
            <?php if (has_role(ROLE_ADMIN)): ?>
                <a class="nav-link" href="<?= e(url('audit')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    Log
                </a>
            <?php endif; ?>
            <?php if (has_role(ROLE_DEVELOPER)): ?>
                <a class="nav-link" href="<?= e(url('users')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Pengguna
                </a>
            <?php endif; ?>
        </nav>

        <div class="userbox">
            <button type="button" class="user-btn" data-menu-toggle aria-expanded="false" aria-haspopup="true">
                <?php if (!empty($user['foto_profil'])): ?>
                    <img class="avatar-img avatar-sm" src="<?= e(url('foto/' . $user['id'])) ?>" alt="">
                <?php else: ?>
                    <span class="avatar" aria-hidden="true"><?= e($initial) ?></span>
                <?php endif; ?>
                <span class="user-name"><?= e($user['nama']) ?></span>
                <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="user-menu" data-menu role="menu">
                <div class="user-menu-head">
                    <strong><?= e($user['nama']) ?></strong>
                    <span>@<?= e($user['username']) ?> &middot; <?= e(role_label($user['role'])) ?></span>
                </div>
                <a href="<?= e(url('profil')) ?>" role="menuitem">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Profil Saya
                </a>
                <form method="post" action="<?= e(url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="danger" role="menuitem">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>

<main class="container">
    <?php require APP_PATH . '/views/partials/flash.php'; ?>

    <?= $content ?>
</main>

<footer class="site-footer">
    <span><?= e(APP_NAME) ?> &middot; Sistem Arsip Digital</span>
    <span class="muted">&copy; <?= e(date('Y')) ?></span>
</footer>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
