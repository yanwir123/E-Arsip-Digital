<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pengirim email sederhana tanpa library eksternal.
 *
 * Mendukung 3 mode (MAIL_DRIVER):
 *   - smtp : konek ke SMTP_HOST dengan STARTTLS (587) atau TLS implisit (465)
 *            + AUTH LOGIN (Gmail: harus pakai App Password)
 *   - mail : fungsi mail() bawaan PHP (bergantung konfigurasi sendmail server)
 *   - log  : menulis isi email ke storage/logs/mail.log (untuk pengembangan/uji)
 */
final class Mailer
{
    /**
     * Tulis seluruh data ke stream.
     *
     * fwrite() pada socket/TLS dapat menulis hanya sebagian (partial write).
     * Sisa byte wajib dikirim ulang; jika tidak, email bisa terpotong ketika
     * template pesan diperbesar/diperbarui.
     *
     * @param resource $stream
     */
    private static function writeAll($stream, string $data): bool
    {
        $length  = strlen($data);
        $written = 0;

        while ($written < $length) {
            $chunk = @fwrite($stream, substr($data, $written));

            if ($chunk === false || $chunk === 0) {
                return false;
            }

            $written += $chunk;
        }

        return true;
    }

    /**
     * @param resource $stream
     */
    private static function sendLine($stream, string $line): bool
    {
        return self::writeAll($stream, $line . "\r\n");
    }

    /**
     * @param resource $stream
     * @param string|array<int, string> $expected kode respon yang dianggap sukses
     */
    private static function expect($stream, array|string $expected): bool
    {
        $expected = (array) $expected;
        $response = '';

        // Tangani respon berbaris-banyak (kode + "-" sebagai kelanjutan).
        do {
            $line = fgets($stream, 1024);

            if ($line === false) {
                return false;
            }

            $response = trim($line);

            if (strlen($response) >= 4 && $response[3] === ' ') {
                break;
            }
        } while (true);

        $code = substr($response, 0, 3);

        foreach ($expected as $exp) {
            if ($code === $exp) {
                return true;
            }
        }

        self::logError('[mailer] respon tidak sesuai. Diharapkan: ' . implode('|', $expected) . ', dapat: ' . $response);

        return false;
    }

    /**
     * Kirim email.
     */
    public static function send(string $to, string $subject, string $html): bool
    {
        $html = trim($html);
        $to = trim($to);
        $subject = trim($subject);

        if ($html === '') {
            self::logError('[mailer] isi email kosong; email tidak dikirim.');

            return false;
        }

        if ($subject === '') {
            self::logError('[mailer] subjek email kosong; email tidak dikirim.');

            return false;
        }

        if (str_contains($subject, "\r") || str_contains($subject, "\n")) {
            self::logError('[mailer] subjek email mengandung baris baru (header injection); email tidak dikirim.');

            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::logError('[mailer] alamat email tidak valid: "' . $to . '"');

            return false;
        }

        $driver = strtolower((string) MAIL_DRIVER);

        // SMTP butuh ekstensi openssl untuk STARTTLS. Jangan diam-diam
        // memakai driver log: email yang hanya masuk log akan membuat
        // pengguna mengira OTP/notifikasi sudah terkirim.
        if ($driver === 'smtp' && !extension_loaded('openssl')) {
            self::logError('[mailer] ekstensi openssl tidak aktif. Email TIDAK dikirim. Aktifkan "extension=openssl" di php.ini lalu restart server.');

            return false;
        }

        return match ($driver) {
            'mail'   => self::sendWithMail($to, $subject, $html),
            'log'    => self::logMail($to, $subject, $html),
            default  => self::sendSmtp($to, $subject, $html),
        };
    }

    /**
     * Ubah HTML notifikasi menjadi teks biasa sebagai alternatif bagi
     * penerima/filter yang tidak menampilkan HTML.
     */
    private static function htmlToText(string $html): string
    {
        // Buang isi <style>/<script> agar tidak ikut menjadi teks.
        $text = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        // Pertahankan pemisah baris pada elemen blok.
        $text = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr)\s*/?>#i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Susun header dan body MIME multipart/alternative (teks + HTML).
     *
     * @return array{0:string,1:string} [headers, body]
     */
    private static function buildMessage(string $to, string $subject, string $html): array
    {
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromName   = '=?UTF-8?B?' . base64_encode((string) SMTP_FROM_NAME) . '?=';
        $fromDomain = str_contains(SMTP_USER, '@')
            ? substr(SMTP_USER, strrpos(SMTP_USER, '@') + 1)
            : 'localhost';
        $messageId  = bin2hex(random_bytes(12)) . '@' . $fromDomain;
        $safeTo     = str_replace(["\r", "\n"], '', $to);
        $safeFrom   = str_replace(["\r", "\n"], '', SMTP_USER);
        $boundary   = '=_EArsip_' . bin2hex(random_bytes(16));

        $headers =
            "From: {$fromName} <{$safeFrom}>\r\n" .
            "Reply-To: {$fromName} <{$safeFrom}>\r\n" .
            "To: <{$safeTo}>\r\n" .
            "Subject: {$subjectEnc}\r\n" .
            'Date: ' . date('r') . "\r\n" .
            "Message-ID: <{$messageId}>\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $text = self::htmlToText($html);

        $body =
            "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($text), 76, "\r\n") .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($html), 76, "\r\n") .
            "--{$boundary}--\r\n";

        return [$headers, $body];
    }

    private static function sendSmtp(string $to, string $subject, string $html): bool
    {
        if (SMTP_HOST === '' || SMTP_USER === '') {
            self::logError('[mailer] SMTP_HOST/SMTP_USER belum diatur.');

            return false;
        }

        $host = strtolower(SMTP_HOST);
        $implicitTls = SMTP_PORT === '465';
        $errno = 0;
        $errstr = '';
        $conn = @stream_socket_client(
            ($implicitTls ? 'ssl://' : 'tcp://') . $host . ':' . SMTP_PORT,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );

        if (!$conn) {
            self::logError("[mailer] koneksi SMTP gagal: {$errstr} ({$errno})");

            return false;
        }

        stream_set_timeout($conn, 15);

        if (!self::expect($conn, '220')) {
            fclose($conn);

            return false;
        }

        self::sendLine($conn, 'EHLO ' . (gethostname() ?: 'localhost'));

        if (!self::expect($conn, '250')) {
            fclose($conn);

            return false;
        }

        // Gmail menggunakan STARTTLS pada 587, TLS implisit pada 465.
        if (!$implicitTls) {
            self::sendLine($conn, 'STARTTLS');
        }

        if ($implicitTls || self::expect($conn, '220')) {
            if (!$implicitTls && !@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                self::logError('[mailer] STARTTLS gagal. Email TIDAK dikirim. Cek ekstensi openssl dan dukungan TLS di server.');
                fclose($conn);

                return false;
            }

            self::sendLine($conn, 'EHLO localhost');

            if (!self::expect($conn, '250')) {
                fclose($conn);

                return false;
            }
        } else {
            self::logError('[mailer] server SMTP tidak menerima STARTTLS. Email TIDAK dikirim.');
            fclose($conn);

            return false;
        }

        // AUTH LOGIN
        self::sendLine($conn, 'AUTH LOGIN');

        if (!self::expect($conn, '334')) {
            fclose($conn);

            return false;
        }

        self::sendLine($conn, base64_encode(SMTP_USER));

        if (!self::expect($conn, '334')) {
            fclose($conn);

            return false;
        }

        self::sendLine($conn, base64_encode(SMTP_PASS));

        if (!self::expect($conn, '235')) {
            fclose($conn);
            self::logError('[mailer] autentikasi SMTP ditolak. Cek SMTP_USER/SMTP_PASS (Gmail: gunakan App Password).');

            return false;
        }

        // Pengirim
        self::sendLine($conn, 'MAIL FROM:<' . SMTP_USER . '>');

        if (!self::expect($conn, '250')) {
            self::logError('[mailer] alamat pengirim ditolak server SMTP: ' . SMTP_USER);
            fclose($conn);

            return false;
        }

        // Penerima
        self::sendLine($conn, 'RCPT TO:<' . $to . '>');

        if (!self::expect($conn, ['250', '251'])) {
            self::logError('[mailer] alamat penerima ditolak server SMTP: ' . $to);
            fclose($conn);

            return false;
        }

        // DATA
        self::sendLine($conn, 'DATA');

        if (!self::expect($conn, '354')) {
            self::logError('[mailer] server SMTP tidak siap menerima isi email untuk: ' . $to);
            fclose($conn);

            return false;
        }

        [$headers, $body] = self::buildMessage($to, $subject, $html);

        if (!self::sendLine($conn, $headers . "\r\n" . $body)) {
            self::logError('[mailer] gagal mengirim isi email (koneksi terputus) untuk: ' . $to);
            fclose($conn);

            return false;
        }

        if (!self::sendLine($conn, '.')) {
            self::logError('[mailer] gagal mengirim penanda akhir pesan untuk: ' . $to);
            fclose($conn);

            return false;
        }

        if (!self::expect($conn, '250')) {
            self::logError('[mailer] server SMTP menolak email untuk: ' . $to);
            fclose($conn);

            return false;
        }

        self::sendLine($conn, 'QUIT');
        fclose($conn);

        return true;
    }

    private static function sendWithMail(string $to, string $subject, string $html): bool
    {
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromName = '=?UTF-8?B?' . base64_encode((string) SMTP_FROM_NAME) . '?=';
        $headers =
            "From: {$fromName} <" . SMTP_USER . ">\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n";

        return @mail($to, $subjectEnc, chunk_split(base64_encode($html)), $headers);
    }

    private static function logMail(string $to, string $subject, string $html): bool
    {
        $dir = STORAGE_PATH . '/logs';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('-', 60),
            $html
        );

        return (bool) @file_put_contents(STORAGE_PATH . '/logs/mail.log', $line, FILE_APPEND | LOCK_EX);
    }

    private static function logError(string $message): void
    {
        error_log($message);
    }
}
