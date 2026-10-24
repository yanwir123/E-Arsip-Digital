<?php
/**
 * Bagian <head> yang dipakai bersama oleh semua layout & halaman error.
 * Variabel opsional: $title (judul halaman tanpa nama aplikasi).
 */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#dceeff">
<title><?= e(($title ?? '') !== '' ? $title . ' - ' . APP_NAME : APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Antonio:wght@600;700&family=Inter:wght@500;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
