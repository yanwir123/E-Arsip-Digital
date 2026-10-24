<?php

declare(strict_types=1);

/**
 * Proteksi CSRF: token disimpan di session dan wajib dikirim di setiap form POST.
 */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    // Token yang dikirim ke klien adalah HMAC dari rahasia sesi dengan APP_KEY,
    // sehingga nilai yang tersimpan di session tidak bisa dipakai langsung.
    return hash_hmac('sha256', $_SESSION['_csrf'], APP_KEY);
}

/**
 * Field hidden siap pakai untuk diletakkan di dalam <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verifikasi token dari request. Hentikan dengan 419 bila tidak valid.
 */
function verify_csrf(): void
{
    $token    = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = hash_hmac('sha256', $_SESSION['_csrf'] ?? '', APP_KEY);

    if (!is_string($token) || $token === '' || !hash_equals($expected, $token)) {
        http_response_code(419);
        exit('Sesi tidak valid atau token CSRF salah. Silakan muat ulang halaman.');
    }
}
