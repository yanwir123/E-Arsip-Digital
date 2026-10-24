<?php

/**
 * Router untuk PHP built-in server (khusus pengembangan).
 *
 * Jalankan dari root proyek:
 *   php -S localhost:8000 -t public public/router.php
 *
 * File statis (CSS/JS/gambar) dilayani apa adanya; sisanya diarahkan
 * ke front controller index.php, meniru perilaku .htaccess Apache.
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path));

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
