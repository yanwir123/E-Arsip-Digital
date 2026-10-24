<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Catat aktivitas ke audit_log. Bersifat append-only (tidak pernah diubah/dihapus).
 *
 * @param string   $aksi         contoh: login, upload, approve, reject, view, download, delete
 * @param string   $targetTabel  nama tabel yang disentuh, contoh: arsip
 * @param int|null $targetId     id baris yang disentuh
 * @param string   $keterangan   deskripsi singkat / nilai lama->baru
 */
function audit_log(string $aksi, string $targetTabel = '', ?int $targetId = null, string $keterangan = ''): void
{
    try {
        Database::run(
            'INSERT INTO audit_log (user_id, aksi, target_tabel, target_id, keterangan, ip, user_agent)
             VALUES (:user_id, :aksi, :target_tabel, :target_id, :keterangan, :ip, :user_agent)',
            [
                'user_id'      => $_SESSION['user_id'] ?? null,
                'aksi'         => $aksi,
                'target_tabel' => $targetTabel !== '' ? $targetTabel : null,
                'target_id'    => $targetId,
                'keterangan'   => $keterangan !== '' ? $keterangan : null,
                'ip'           => client_ip(),
                 'user_agent'   => substr(user_agent_info() ?: ((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            ]
        );
    } catch (\Throwable $e) {
        // Audit tidak boleh menggagalkan aplikasi; cukup catat ke file error.
        error_log('[audit_log] ' . $e->getMessage());
    }
}

/**
 * Alamat IP klien yang dapat dipercaya.
 *
 * SECURITY: header X-Forwarded-For dan sejenisnya mudah dipalsukan oleh
 * klien. Karena itu header tersebut HANYA dihormati bila koneksi datang
 * langsung dari proxy tepercaya (TRUSTED_PROXIES). Jika tidak, yang dipakai
 * adalah REMOTE_ADDR apa adanya, sehingga rate limit & audit log tidak bisa
 * dikibuli dengan menyetel header secara manual.
 */
function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (TRUSTED_PROXIES === [] || !in_array($remote, TRUSTED_PROXIES, true)) {
        return $remote;
    }

    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
    ];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        // Ambil IP pertama (klien asli) dari daftar terpisah koma.
        $ip = trim(explode(',', (string) $_SERVER[$header])[0]);

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }
    }

    return $remote;
}

/** Label IP yang jelas untuk histori audit. */
function client_ip_label(?string $ip = null): string
{
    $ip = trim((string) ($ip ?? client_ip()));

    if ($ip === '') {
        return 'Tidak terdeteksi';
    }

    if ($ip === '::1') {
        return '::1 (localhost)';
    }

    if ($ip === '127.0.0.1') {
        return '127.0.0.1 (localhost)';
    }

    return $ip;
}

/** Verifikasi Google reCAPTCHA v2; gagal tertutup jika konfigurasi/verifikasi gagal. */
function verify_recaptcha(): bool
{
    $token = trim((string) ($_POST['g-recaptcha-response'] ?? ''));

    if (RECAPTCHA_SECRET_KEY === '' || $token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => client_ip(),
    ]);
    $context = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $payload,
        'timeout' => 8,
    ]]);
    $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    $result = is_string($response) ? json_decode($response, true) : null;

    return is_array($result) && ($result['success'] ?? false) === true;
}

/**
 * Ringkasan User-Agent (browser + platform).
 */
function user_agent_info(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return '';
    }

    $browser = 'Lainnya';
    if (preg_match('#Edg/|Edge#i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('#OPR/|Opera#i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('#Chrome#i', $ua) && !preg_match('#Edg|Edge|OPR#i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('#Firefox#i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('#Safari#i', $ua) && !preg_match('#Chrome#i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('#MSIE|Trident#i', $ua)) {
        $browser = 'IE';
    }

    $os = 'Lainnya';
    if (preg_match('#Windows#i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('#Android#i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('#iPhone|iPad|iOS#i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('#Mac OS|Macintosh#i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('#Linux#i', $ua) && !preg_match('#Android#i', $ua)) {
        $os = 'Linux';
    }

    return $browser . ' / ' . $os;
}
