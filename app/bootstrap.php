<?php

declare(strict_types=1);

/**
 * Bootstrap aplikasi (murni PHP, tanpa framework).
 * - Mendefinisikan path
 * - Memuat konfigurasi & konstanta
 * - Error reporting
 * - Autoload PSR-4 sederhana (App\\)
 * - Helper global
 * - Session aman
 * - Header keamanan
 * - Deteksi post_max_size terpotong
 */

// ---------------------------------------------------------------------------
// Path dasar
// ---------------------------------------------------------------------------
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');

// ---------------------------------------------------------------------------
// Konfigurasi
// ---------------------------------------------------------------------------
require APP_PATH . '/config/config.php';

// ---------------------------------------------------------------------------
// Error reporting
// ---------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

// ---------------------------------------------------------------------------
// Autoloader PSR-4 sederhana: App\  =>  app/
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------------
// Helper global
// ---------------------------------------------------------------------------
require APP_PATH . '/helpers/helpers.php';
require APP_PATH . '/helpers/csrf.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/password.php';
require APP_PATH . '/helpers/audit.php';
require APP_PATH . '/helpers/jwt.php';
require APP_PATH . '/helpers/mail.php';

// ---------------------------------------------------------------------------
// Session aman
// ---------------------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('EARSIP_SESS');
    session_start();
}

// ---------------------------------------------------------------------------
// Header keamanan
// ---------------------------------------------------------------------------
send_security_headers();

// ---------------------------------------------------------------------------
// Deteksi POST yang terpotong karena melebihi post_max_size
// ---------------------------------------------------------------------------
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    set_flash('error', 'Data yang dikirim melebihi batas ukuran server. Silakan perkecil ukuran berkas, kemudian coba kembali.');

    $path      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

    if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($path, $scriptDir)) {
        $path = substr($path, strlen($scriptDir));
    }

    redirect($path);
}
