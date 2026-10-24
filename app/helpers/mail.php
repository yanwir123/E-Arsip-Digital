<?php

declare(strict_types=1);

use App\Core\Mailer;

/**
 * Kirim email HTML melalui Mailer.
 */
function send_mail(string $to, string $subject, string $html): bool
{
    return Mailer::send($to, $subject, $html);
}

/**
 * Kirim email notifikasi dan catat kegagalan agar tidak lagi terlihat
 * seolah-olah email selalu berhasil dikirim.
 *
 * @param string $context Label singkat untuk log server, mis. "validasi-arsip".
 */
function send_notification(string $to, string $subject, string $html, string $context = ''): bool
{
    $to = trim($to);

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[notif] alamat email tidak valid: "' . $to . '"' . ($context !== '' ? ' [' . $context . ']' : ''));

        return false;
    }

    try {
        $sent = send_mail($to, $subject, $html);
    } catch (\Throwable $e) {
        error_log('[notif] ' . ($context !== '' ? $context . ': ' : '') . $e->getMessage());

        return false;
    }

    if (!$sent) {
        error_log('[notif] gagal mengirim "' . $subject . '" ke ' . $to . ($context !== '' ? ' [' . $context . ']' : ''));
    }

    return $sent;
}

/**
 * Kirim email berisi kode OTP beserta tautan verifikasi bertanda tangan JWT.
 */
function send_otp_email(string $to, string $code): bool
{
    $token = jwt_encode([
        'purpose' => 'verify_email',
        'email'   => $to,
        'otp'     => $code,
    ]);

    $link = url('verify-otp/token/' . $token);
    $menit = ceil(OTP_EXPIRY_SECONDS / 60);

    $html =
        '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:auto;background:#dceeff;padding:24px">' .
            '<div style="background:#ffffff;border:1px solid #000000;border-radius:20px;overflow:hidden">' .
                '<div style="background:#000000;color:#ffffff;padding:14px 20px;font-size:18px;font-weight:700;letter-spacing:.02em">' . e(APP_NAME) . '</div>' .
                '<div style="padding:20px;color:#000000">' .
                    '<p>Halo,</p>' .
                    '<p>Kode verifikasi Anda untuk mengaktifkan akun:</p>' .
                    '<p style="font-size:26px;font-weight:700;letter-spacing:6px;color:#000000;background:#e9ccff;border:1px solid #000000;border-radius:16px;text-align:center;padding:12px 0;margin:16px 0">' . e($code) . '</p>' .
                    '<p style="color:#555555;font-size:13px">Kode berlaku ' . e((string) $menit) . ' menit. Jangan bagikan kode ini kepada siapa pun.</p>' .
                    '<p>atau klik tautan berikut (tanda tangan JWT, berlaku ' . e((string) JWT_EXPIRES) . ' menit):</p>' .
                    '<p><a href="' . e($link) . '" style="display:inline-block;background:#000000;color:#ffffff;padding:11px 22px;border:1px solid #000000;border-radius:1600px;text-decoration:none;font-weight:700">Verifikasi Akun</a></p>' .
                '</div>' .
            '</div>' .
        '</div>';

    return send_mail($to, 'Kode OTP ' . APP_NAME, $html);
}