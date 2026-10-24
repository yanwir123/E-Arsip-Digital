<?php

declare(strict_types=1);

/**
 * Escape output untuk mencegah XSS. Selalu pakai ini saat menampilkan data user.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Base URL aplikasi, dihitung dari request saat ini agar portabel
 * (berjalan di port built-in server maupun subfolder Apache/XAMPP).
 */
function base_url(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir    = rtrim($dir, '/');

    return $base = $scheme . '://' . $host . $dir;
}

/**
 * Bentuk URL absolut berdasarkan request saat ini.
 */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/**
 * URL untuk file aset publik.
 */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Redirect dan hentikan eksekusi.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Kirim header keamanan standar. Dipanggil sekali dari bootstrap
 * sebelum ada output apa pun.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "img-src 'self' data:; " .
        "style-src 'self' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "script-src 'self' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; " .
        "frame-src 'self' https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/; " .
        "connect-src 'self' https://www.google.com/recaptcha/; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'none'"
    );

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---------------------------------------------------------------------------
// Flash message (pesan sekali tampil)
// ---------------------------------------------------------------------------
function set_flash(string $type, string $message, ?string $title = null): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'title' => $title, 'message' => $message];
}

/**
 * @return array<int, array{type:string, title:string, message:string}>
 */
function get_flash(): array
{
    $flash  = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    $titles = [
        'success' => 'Berhasil',
        'error'   => 'Perlu Perhatian',
        'info'    => 'Informasi',
    ];

    foreach ($flash as &$item) {
        if (empty($item['title'])) {
            $item['title'] = $titles[$item['type'] ?? ''] ?? 'Informasi';
        }
    }
    unset($item);

    return $flash;
}

// ---------------------------------------------------------------------------
// Menyimpan input lama (agar form tidak kosong setelah gagal validasi)
// ---------------------------------------------------------------------------
function flash_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['_token']);
    $_SESSION['_old'] = $data;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/**
 * Nama tampilan role.
 */
function role_label(string $role): string
{
    return ROLES[$role] ?? ucfirst($role);
}

/**
 * Label status arsip.
 */
function status_label(string $status): string
{
    return ARSIP_STATUS[$status] ?? ucfirst($status);
}

/**
 * Label tingkat risiko arsip (hijau/kuning/merah).
 */
function risk_label(string $risk): string
{
    return RISK_LEVEL[$risk] ?? ucfirst($risk);
}

/**
 * Label tingkat sanksi (basic/sedang/berat).
 */
function penalty_label(string $tingkat): string
{
    return PENALTY_LEVEL[$tingkat] ?? ucfirst($tingkat);
}

/**
 * Label status identitas pengguna (belum/menunggu/terverifikasi/ditolak).
 */
function identity_label(string $status): string
{
    return IDENTITY_LABELS[$status] ?? ucfirst($status);
}

/**
 * Hitung metadata paginasi.
 *
 * @return array{page:int, per_page:int, total:int, pages:int, offset:int}
 */
function paginate(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $page), $pages);

    return [
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $total,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/**
 * URL halaman paginasi sambil mempertahankan query filter yang ada.
 *
 * @param string $pageKey Nama parameter halaman (mis. history_page)
 */
function page_url(string $path, array $query, int $page, string $pageKey = 'page'): string
{
    // Buang filter kosong agar URL rapi (mis. ?status=&q= -> tanpa keduanya).
    $query = array_filter(
        $query,
        static fn ($value) => $value !== '' && $value !== null && $value !== []
    );

    $query[$pageKey] = $page;

    return url($path) . '?' . http_build_query($query);
}

/**
 * Tentukan jenis pratinjau yang bisa ditampilkan langsung di peramban.
 *
 * @return 'pdf'|'image'|''
 */
function preview_kind(string $mime): string
{
    return match ($mime) {
        'application/pdf'          => 'pdf',
        'image/png', 'image/jpeg'  => 'image',
        default                     => '',
    };
}

/**
 * Deteksi MIME asli sebuah berkas berdasarkan isinya (BUKAN tipe yang
 * dilaporkan klien). Mengembalikan null bila tipe tidak dapat diverifikasi
 * dengan andal, sehingga pemanggil dapat menolak berkas tersebut.
 *
 * Catatan keamanan: jangan pernah jatuh ke $_FILES['type'] karena nilainya
 * dikendalikan penyerang dan dapat dipalsukan.
 */
function detect_mime_type(string $path, ?string $ext = null): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $mime = '';

    if (class_exists('finfo')) {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($path);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) @mime_content_type($path);
    }

    $mime = strtolower(trim($mime));

    // Fallback: deteksi dari magic bytes isi berkas (tetap TIDAK memakai
    // tipe yang dilaporkan klien), agar tetap aman walau fileinfo tak aktif.
    if ($mime === '') {
        $mime = (string) (sniff_mime_type($path, $ext) ?? '');
    }

    // Untuk gambar, getimagesize() memberi verifikasi tambahan yang kuat.
    if (
        $ext !== null
        && in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'], true)
        && function_exists('getimagesize')
    ) {
        $info = @getimagesize($path);

        if (is_array($info) && !empty($info['mime'])) {
            $mime = strtolower((string) $info['mime']);
        }
    }

    // Dokumen Office Open XML (docx/xlsx/pptx) sebenarnya arsip ZIP. Sebagian
    // versi libmagic melaporkannya sebagai application/zip; periksa isi ZIP
    // untuk memastikan jenis dokumen sebenarnya.
    $ooxml = [
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'word/document.xml'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xl/workbook.xml'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'ppt/presentation.xml'],
    ];

    if (
        $ext !== null
        && isset($ooxml[$ext])
        && in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream', ''], true)
        && zip_contains($path, $ooxml[$ext][1])
    ) {
        $mime = $ooxml[$ext][0];
    }

    return $mime !== '' ? $mime : null;
}

/**
 * Deteksi MIME dari magic bytes berkas, tanpa bergantung pada ekstensi PHP
 * maupun info dari klien. Mengembalikan null bila tidak dikenali.
 */
function sniff_mime_type(string $path, ?string $ext = null): ?string
{
    $fh = @fopen($path, 'rb');

    if ($fh === false) {
        return null;
    }

    $head = (string) fread($fh, 64);
    fclose($fh);

    if ($head === '') {
        return null;
    }

    if (str_starts_with($head, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }

    if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
        return 'image/png';
    }

    if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
        return 'image/webp';
    }

    if (str_starts_with($head, '%PDF-')) {
        return 'application/pdf';
    }

    if (
        str_starts_with($head, "PK\x03\x04")
        || str_starts_with($head, "PK\x05\x06")
        || str_starts_with($head, "PK\x07\x08")
    ) {
        return 'application/zip';
    }

    // Dokumen Office lama (doc/xls/ppt) memakai wadah OLE2 yang sama,
    // dibedakan lewat ekstensi.
    if (str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
        return match ($ext) {
            'doc'   => 'application/msword',
            'xls'   => 'application/vnd.ms-excel',
            'ppt'   => 'application/vnd.ms-powerpoint',
            default => null,
        };
    }

    // Berkas teks polos: tidak memuat byte NUL dan valid sebagai UTF-8.
    if (!str_contains($head, "\x00") && preg_match('//u', $head) === 1) {
        return 'text/plain';
    }

    return null;
}

/**
 * Periksa apakah sebuah arsip ZIP memuat entri tertentu (untuk verifikasi OOXML).
 */
function zip_contains(string $path, string $entry): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $zip = new \ZipArchive();

    if ($zip->open($path) !== true) {
        return false;
    }

    $found = $zip->locateName($entry) !== false;
    $zip->close();

    return $found;
}

/**
 * Format ukuran file menjadi string yang mudah dibaca.
 */
function format_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}
