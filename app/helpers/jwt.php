<?php

declare(strict_types=1);

/**
 * Bantu pembuatan & verifikasi token JWT (HS256) tanpa library eksternal.
 *
 * Dipakai untuk token verifikasi email / reset password, bukan untuk login.
 * Login tetap memakai session yang sudah ada.
 */

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad  = strlen($data) % 4;

        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($data, true);
    }
}

/**
 * Terbitkan token JWT dengan payload diberikan. Menambah iat & exp otomatis.
 *
 * @param array<string, mixed> $payload
 */
function jwt_encode(array $payload): string
{
    $header  = ['alg' => strtoupper(JWT_ALGORITHM) === 'HS256' ? 'HS256' : 'HS256', 'typ' => 'JWT'];
    $header  = base64url_encode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
    $now     = time();
    $payload = array_merge($payload, [
        'iat' => $now,
        'exp' => $now + JWT_EXPIRES * 60,
    ]);
    $body = base64url_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $signature = base64url_encode(self_jwt_signature("{$header}.{$body}"));

    return "{$header}.{$body}.{$signature}";
}

/**
 * Verifikasi dan dekode token JWT. Mengembalikan payload, atau null bila
 * tidak valid / sudah kedaluwarsa / tanda tangan tidak cocok.
 *
 * @return array<string, mixed>|null
 */
function jwt_decode(string $token): ?array
{
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return null;
    }

    [$headerB64, $bodyB64, $signature] = $parts;

    $expected = base64url_encode(self_jwt_signature("{$headerB64}.{$bodyB64}"));

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode((string) base64url_decode($bodyB64), true);

    if (!is_array($payload)) {
        return null;
    }

    if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}

/**
 * @return string signature HMAC-SHA256
 */
function self_jwt_signature(string $input): string
{
    if (JWT_SECRET === '') {
        // Dipanggil di luar konteks config — mis. skrip CLI uji.
        error_log('[jwt] SECRET_KEY belum diatur di .env');
        return '';
    }

    return hash_hmac('sha256', $input, JWT_SECRET, true);
}