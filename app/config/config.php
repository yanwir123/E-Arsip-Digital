<?php

declare(strict_types=1);

/**
 * Memuat file .env sederhana (tanpa library) ke $_ENV dan putenv().
 */
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value);

        // Buang tanda kutip pembungkus jika ada.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

/**
 * Ambil nilai environment dengan nilai default.
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $value,
    };
}

load_env(BASE_PATH . '/.env');

// ---------------------------------------------------------------------------
// Konstanta aplikasi
// ---------------------------------------------------------------------------
define('APP_NAME',  (string) env('APP_NAME', 'E-Arsip'));
define('APP_ENV',   (string) env('APP_ENV', 'local'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));
define('APP_URL',   rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'));
define('APP_KEY',   (string) env('APP_KEY', ''));

// APP_KEY dipakai sebagai kunci HMAC untuk token CSRF. Wajib kuat.
// Aplikasi MENOLAK berjalan dengan kunci lemah/placeholder agar tidak ada
// instalasi yang diam-diam tidak aman.
if (strlen(APP_KEY) < 32 || str_contains(APP_KEY, 'ubah-kunci-ini')) {
    throw new \RuntimeException(
        'APP_KEY lemah atau belum diatur. Set APP_KEY berupa string acak minimal 32 karakter di .env ' .
        '(contoh pembuatan: php -r "echo bin2hex(random_bytes(32));").'
    );
}

// ---------------------------------------------------------------------------
// Konstanta database
//
// Prioritas: DATABASE_URL (mis. mysql+pymysql://user:pass@host:port/nama_db)
// Jika tidak ada, gunakan variabel DB_* terpisah.
// ---------------------------------------------------------------------------
$dbHost = (string) env('DB_HOST', '127.0.0.1');
$dbPort = (string) env('DB_PORT', '3306');
$dbName = (string) env('DB_NAME', 'e_arsip');
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', '');

$databaseUrl = (string) env('DATABASE_URL', '');

if ($databaseUrl !== '') {
    $parts = parse_url($databaseUrl);

    if (is_array($parts) && isset($parts['host'])) {
        // Skema bisa berupa "mysql", "mysql+pymysql", "pdo-mysql", dsb.
        $dbHost = $parts['host'];
        $dbPort = isset($parts['port']) ? (string) $parts['port'] : $dbPort;
        $dbUser = isset($parts['user']) ? urldecode($parts['user']) : $dbUser;
        $dbPass = isset($parts['pass']) ? urldecode($parts['pass']) : $dbPass;

        if (isset($parts['path'])) {
            $dbName = urldecode(ltrim($parts['path'], '/'));
        }
    } else {
        error_log('[config] DATABASE_URL tidak dapat diparse: ' . $databaseUrl);
    }
}

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

// ---------------------------------------------------------------------------
// Konstanta upload
// ---------------------------------------------------------------------------
define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', 10 * 1024 * 1024));
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');

// ---------------------------------------------------------------------------
// Rate limit login (berbasis tabel login_attempts, per-IP & per-akun)
//
// LOGIN_MAX_ATTEMPTS   : jumlah kegagalan dalam LOGIN_LOCKOUT_SECONDS sebelum
//                        akun/IP terkunci sementara.
// LOGIN_LOCKOUT_SECONDS: panjang jendela waktu sekaligus durasi kunci.
// ---------------------------------------------------------------------------
define('LOGIN_MAX_ATTEMPTS', max(1, (int) env('LOGIN_MAX_ATTEMPTS', 5)));
define('LOGIN_LOCKOUT_SECONDS', max(30, (int) env('LOGIN_LOCKOUT_SECONDS', 300)));

// ---------------------------------------------------------------------------
// Rate limit pengiriman OTP (tabel otp_throttle)
//
// Mencegah penyalahgunaan kirim-ulang OTP untuk spam email & pengurasan kuota.
// ---------------------------------------------------------------------------
define('OTP_SEND_MAX_PER_EMAIL', max(1, (int) env('OTP_SEND_MAX_PER_EMAIL', 5)));
define('OTP_SEND_MAX_PER_IP', max(1, (int) env('OTP_SEND_MAX_PER_IP', 10)));
define('OTP_SEND_COOLDOWN_SECONDS', max(10, (int) env('OTP_SEND_COOLDOWN_SECONDS', 60)));

// ---------------------------------------------------------------------------
// Kebijakan password
// ---------------------------------------------------------------------------
define('PASSWORD_MIN_LENGTH', max(8, (int) env('PASSWORD_MIN_LENGTH', 8)));

// ---------------------------------------------------------------------------
// Proxy tepercaya (untuk penentuan alamat IP klien)
//
// Isi dengan daftar IP reverse-proxy/load-balancer yang boleh dipercaya
// memuat header X-Forwarded-For (dipisah koma). Kosong = header tersebut
// DIABAIKAN dan hanya REMOTE_ADDR dipakai (aman untuk deployment langsung).
// ---------------------------------------------------------------------------
define('TRUSTED_PROXIES', array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', ''))
))));

// Google reCAPTCHA v2 checkbox.
define('RECAPTCHA_SITE_KEY', (string) env('RECAPTCHA_SITE_KEY', env('TURNSTILE_SITE_KEY', '')));
define('RECAPTCHA_SECRET_KEY', (string) env('RECAPTCHA_SECRET_KEY', env('TURNSTILE_SECRET_KEY', '')));

// ---------------------------------------------------------------------------
// SMTP (email)
// ---------------------------------------------------------------------------
define('SMTP_HOST', (string) env('SMTP_HOST', ''));
define('SMTP_PORT', (string) env('SMTP_PORT', '587'));
define('SMTP_USER', (string) env('SMTP_USER', ''));
define('SMTP_PASS', (string) env('SMTP_PASS', ''));
define('SMTP_FROM_NAME', (string) env('SMTP_FROM_NAME', APP_NAME));

// MAIL_DRIVER: smtp (kirim asli) | mail (fungsi mail()) | log (simpan ke file)
define('MAIL_DRIVER', (string) env('MAIL_DRIVER', SMTP_HOST !== '' ? 'smtp' : 'log'));

// ---------------------------------------------------------------------------
// JWT (token verifikasi email / reset password)
// ---------------------------------------------------------------------------
define('JWT_SECRET', (string) env('SECRET_KEY', env('JWT_SECRET', '')));
define('JWT_ALGORITHM', (string) env('ALGORITHM', 'HS256'));
define('JWT_EXPIRES', max(1, (int) env('ACCESS_TOKEN_EXPIRE_MINUTES', 60)));

// SECRET_KEY menandatangani token JWT (verifikasi email). Tolak kunci lemah
// agar token tidak dapat dipalsukan/dibuat ulang pihak lain.
if (strlen(JWT_SECRET) < 32 || str_contains(JWT_SECRET, 'ubah-jadi-string')) {
    throw new \RuntimeException(
        'SECRET_KEY lemah atau belum diatur. Set SECRET_KEY berupa string acak minimal 32 karakter di .env ' .
        '(contoh pembuatan: php -r "echo bin2hex(random_bytes(32));").'
    );
}

// ---------------------------------------------------------------------------
// OTP (kode sekali pakai saat registrasi)
// ---------------------------------------------------------------------------
define('OTP_EXPIRY_SECONDS', max(120, (int) env('OTP_EXPIRY_SECONDS', 600)));
define('OTP_MAX_ATTEMPTS', max(1, (int) env('OTP_MAX_ATTEMPTS', 5)));

// ---------------------------------------------------------------------------
// Definisi role (RBAC). 3 role pengguna + 1 role pengembang.
// ---------------------------------------------------------------------------
const ROLE_DEVELOPER = 'developer'; // akses penuh ke seluruh sistem
const ROLE_ADMIN     = 'admin';     // validasi arsip & pemberian izin akses
const ROLE_PETUGAS   = 'petugas';   // mengelola/mengunggah arsip
const ROLE_USER      = 'user';      // mengunggah & membaca arsip yang diizinkan

const ROLES = [
    ROLE_DEVELOPER => 'Pengembang',
    ROLE_ADMIN     => 'Administrator',
    ROLE_PETUGAS   => 'Petugas',
    ROLE_USER      => 'Pengguna',
];

// Status identitas pengguna
const IDENTITY_BELUM         = 'belum';
const IDENTITY_MENUNGGU      = 'menunggu';
const IDENTITY_TERVERIFIKASI = 'terverifikasi';
const IDENTITY_DITOLAK       = 'ditolak';

const IDENTITY_STATUSES = [
    IDENTITY_BELUM,
    IDENTITY_MENUNGGU,
    IDENTITY_TERVERIFIKASI,
    IDENTITY_DITOLAK,
];

const IDENTITY_LABELS = [
    IDENTITY_BELUM         => 'Belum Lengkap',
    IDENTITY_MENUNGGU      => 'Menunggu Verifikasi',
    IDENTITY_TERVERIFIKASI => 'Terverifikasi',
    IDENTITY_DITOLAK       => 'Ditolak',
];

// Status arsip
const ARSIP_PENDING  = 'pending';
const ARSIP_APPROVED = 'approved';
const ARSIP_REJECTED = 'rejected';

const ARSIP_STATUS = [
    ARSIP_PENDING  => 'Menunggu Validasi',
    ARSIP_APPROVED => 'Disetujui',
    ARSIP_REJECTED => 'Ditolak',
];

// ---------------------------------------------------------------------------
// Tingkat risiko konten arsip (ditentukan admin saat validasi).
// hijau  = aman, disimpan normal
// kuning = perhatian, disimpan dengan catatan
// merah  = melanggar, ditolak & pengunggah diberi sanksi
// ---------------------------------------------------------------------------
const RISK_HIJAU  = 'hijau';
const RISK_KUNING = 'kuning';
const RISK_MERAH  = 'merah';

const RISK_LEVEL = [
    RISK_HIJAU  => 'Aman',
    RISK_KUNING => 'Perhatian',
    RISK_MERAH  => 'Melanggar',
];

// ---------------------------------------------------------------------------
// Sanksi pelanggaran kode etik (ban sementara).
// Durasi ban dinyatakan dalam hari. 0 = permanen sampai dibuka manual
// oleh admin/developer.
// ---------------------------------------------------------------------------
const PENALTY_BASIC  = 'basic';
const PENALTY_SEDANG = 'sedang';
const PENALTY_BERAT  = 'berat';

const PENALTY_LEVEL = [
    PENALTY_BASIC  => 'Ringan (basic)',
    PENALTY_SEDANG => 'Sedang',
    PENALTY_BERAT  => 'Berat',
];

// Durasi ban per tingkat (hari). Ubah sesuai kebijakan dinas.
define('PENALTY_DAYS', [
    PENALTY_BASIC  => (int) env('PENALTY_BASIC_DAYS', 1),
    PENALTY_SEDANG => (int) env('PENALTY_SEDANG_DAYS', 7),
    PENALTY_BERAT  => (int) env('PENALTY_BERAT_DAYS', 30),
]);

// Batas umur arsip pending sebelum dihapus otomatis (jam).
define('PENDING_AUTO_DELETE_HOURS', max(1, (int) env('PENDING_AUTO_DELETE_HOURS', 24)));

// Jumlah baris per halaman untuk daftar berhalaman.
define('PER_PAGE', max(1, (int) env('PER_PAGE', 15)));
