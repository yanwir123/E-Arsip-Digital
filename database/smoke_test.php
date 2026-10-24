<?php

declare(strict_types=1);

/**
 * Smoke test E-Arsip via HTTP (butuh server dev jalan di BASE).
 * Jalankan: php smoke_test.php [base_url]
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
require APP_PATH . '/config/config.php';

$BASE = $argv[1] ?? 'http://localhost:8000';
$TMP  = sys_get_temp_dir();
$jars = [];

$pass = 0;
$fail = 0;

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function jar(string $name): string
{
    global $TMP;
    return $TMP . '/earsip_cookie_' . $name . '.txt';
}

function request(string $method, string $url, string $jarName, $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => jar($jarName),
        CURLOPT_COOKIEFILE     => jar($jarName),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    // curl_close() tidak lagi diperlukan sejak PHP 8.0 (dan usang di 8.5+).
    return ['code' => $code, 'body' => $body, 'error' => $err];
}

function token(string $html): string
{
    return preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "[PASS] {$label}\n";
    } else {
        $fail++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " -> {$detail}" : '') . "\n";
    }
}

function login(string $jarName, string $user, string $pass): array
{
    global $BASE;
    $r = request('GET', "$BASE/login", $jarName);
    $t = token($r['body']);
    return request('POST', "$BASE/login", $jarName, ['_token' => $t, 'username' => $user, 'password' => $pass]);
}

echo "=== Smoke Test E-Arsip @ {$BASE} ===\n\n";

// --- 0. Server hidup ---
$r = request('GET', "$BASE/login", 'guest');
check('GET /login 200', $r['code'] === 200, "HTTP {$r['code']} {$r['error']}");
$guestToken = token($r['body']);
check('CSRF token ada di halaman login', $guestToken !== '');

// --- 1. Proteksi halaman tanpa login ---
$r = request('GET', "$BASE/dashboard", 'anon');
check('GET /dashboard tanpa login -> halaman login', str_contains($r['body'], 'name="username"'), 'tidak dialihkan');

// --- 2. Login gagal ---
$r = request('GET', "$BASE/login", 'bad');
$badToken = token($r['body']);
$r = request('POST', "$BASE/login", 'bad', ['_token' => $badToken, 'username' => 'admin', 'password' => 'salahbanget']);
check('Login password salah ditolak', str_contains($r['body'], 'Username atau password salah'), "HTTP {$r['code']}");

// --- 2b. CSRF ditolak ---
$r = request('POST', "$BASE/login", 'csrf', ['_token' => 'invalid', 'username' => 'admin', 'password' => 'admin123']);
check('POST tanpa CSRF valid ditolak (419)', $r['code'] === 419, "HTTP {$r['code']}");

// --- 2. Login petugas & admin ---
$r = login('petugas', 'petugas', 'petugas123');
check('Login petugas', str_contains($r['body'], 'Petugas'), "HTTP {$r['code']}");
$petugasToken = token($r['body']);

$r = login('admin', 'admin', 'admin123');
check('Login admin', str_contains($r['body'], 'Administrator'), "HTTP {$r['code']}");
$adminToken = token($r['body']);

// --- 2b. RBAC ---
$r = login('budi', 'budi', 'budi12345');
check('Login user biasa', $r['code'] === 200, "HTTP {$r['code']}");
$budiToken = token($r['body']);

$r = request('GET', "$BASE/users", 'budi');
check('RBAC: user biasa ditolak /users (403)', $r['code'] === 403, "HTTP {$r['code']}");
$r = request('GET', "$BASE/admin/validasi", 'budi');
check('RBAC: user biasa ditolak /admin/validasi (403)', $r['code'] === 403, "HTTP {$r['code']}");
$r = request('GET', "$BASE/audit", 'budi');
check('RBAC: user biasa ditolak /audit (403)', $r['code'] === 403, "HTTP {$r['code']}");
$r = request('GET', "$BASE/audit", 'petugas');
check('RBAC: petugas ditolak /audit (403)', $r['code'] === 403, "HTTP {$r['code']}");
$r = request('GET', "$BASE/audit", 'admin');
check('RBAC: admin boleh /audit (200)', $r['code'] === 200, "HTTP {$r['code']}");

// --- 3. Upload arsip sebagai petugas ---
$tmpFile = "$TMP/earsip_uji.txt";
file_put_contents($tmpFile, "Dokumen uji otomatis E-Arsip\n" . date('c') . "\n");

$r = request('GET', "$BASE/arsip/create", 'petugas');
$t = token($r['body']);
$judul = 'Arsip Uji Otomatis ' . date('His');

$r = request('POST', "$BASE/arsip", 'petugas', [
    '_token'     => $t,
    'kode'       => 'UJI-' . date('His'),
    'judul'      => $judul,
    'kategori'   => 'Pengujian',
    'deskripsi'  => 'Dibuat oleh smoke test',
    'file'       => new CURLFile($tmpFile, 'text/plain', 'earsip_uji.txt'),
]);
check('Upload arsip sebagai petugas', str_contains($r['body'], 'berhasil diunggah'), "HTTP {$r['code']}");

$row = pdo()->query("SELECT * FROM arsip WHERE judul = " . pdo()->quote($judul) . " ORDER BY id DESC LIMIT 1")->fetch();
check('Arsip tersimpan di DB (status pending)', $row && $row['status'] === ARSIP_PENDING, json_encode($row ?: null));
$arsipId = $row['id'] ?? 0;
check('Berkas fisik tersimpan di storage', $row && is_file(STORAGE_PATH . '/uploads/' . $row['file_path']));

// --- 4. User lain tidak bisa membuka arsip ---
if ($arsipId) {
    $r = request('GET', "$BASE/arsip/{$arsipId}", 'budi');
    $blocked = $r['code'] === 403 || str_contains($r['body'], 'tidak memiliki izin');
    check('Izin akses: user lain tidak bisa membaca arsip petugas', $blocked, "HTTP {$r['code']}");
}

// --- 5. Admin approve arsip ---
$r = request('GET', "$BASE/admin/validasi", 'admin');
check('Admin melihat arsip pending di /admin/validasi', str_contains($r['body'], $judul));
$t = token($r['body']);

$r = request('POST', "$BASE/admin/arsip/{$arsipId}/approve", 'admin', ['_token' => $t]);
check('Admin menyetujui arsip', str_contains($r['body'], 'disetujui') || str_contains($r['body'], 'Izin Akses'), "HTTP {$r['code']}");

$status = pdo()->query("SELECT status FROM arsip WHERE id = " . (int) $arsipId)->fetchColumn();
check('Status arsip berubah menjadi approved', $status === ARSIP_APPROVED, "status={$status}");

// --- 6. Beri akses ke budi lalu cek bisa membaca ---
$t = token($r['body']);
$budiId = (int) pdo()->query("SELECT id FROM users WHERE username='budi'")->fetchColumn();
$r = request('POST', "$BASE/admin/arsip/{$arsipId}/akses", 'admin', http_build_query(['_token' => $t, 'user_ids' => [$budiId]]));
$has = (int) pdo()->query("SELECT COUNT(*) FROM arsip_akses WHERE arsip_id = " . (int) $arsipId . " AND user_id = {$budiId}")->fetchColumn();
check('Admin memberi izin akses ke budi', $has === 1, "baris akses={$has}");

$r = request('GET', "$BASE/arsip/{$arsipId}", 'budi');
check('Budi kini bisa membaca arsip yang diizinkan', str_contains($r['body'], $judul), "HTTP {$r['code']}");

// --- 7. Download berkas ---
$r = request('GET', "$BASE/arsip/{$arsipId}/download", 'admin');
check('Download arsip mengembalikan isi file', str_contains($r['body'], 'Dokumen uji otomatis'), "HTTP {$r['code']}");

// --- 7b. Hapus arsip oleh admin ---
$r = request('GET', "$BASE/arsip/{$arsipId}", 'admin');
$t = token($r['body']);
$r = request('POST', "$BASE/arsip/{$arsipId}/delete", 'admin', ['_token' => $t]);
check('Admin menghapus arsip', str_contains($r['body'], 'berhasil dihapus'), "HTTP {$r['code']}");
$exists = pdo()->query("SELECT COUNT(*) FROM arsip WHERE id = " . (int) $arsipId)->fetchColumn();
check('Arsip terhapus dari DB', (int) $exists === 0);

// --- 8. Registrasi + OTP ---
$email = 'smoke_' . time() . '@example.com';
$r = request('GET', "$BASE/register", 'reg');
$t = token($r['body']);
$r = request('POST', "$BASE/register", 'reg', [
    '_token'           => $t,
    'nama'             => 'Pengguna Uji',
    'username'         => 'smoke' . time(),
    'email'            => $email,
    'password'         => 'rahasia123',
    'password_confirm' => 'rahasia123',
]);
check('Registrasi mengirim OTP', str_contains($r['body'], 'verifikasi') || str_contains($r['body'], 'Verifikasi'), "HTTP {$r['code']}");

$otp = pdo()->query("SELECT kode FROM otp_codes WHERE email = " . pdo()->quote($email) . " ORDER BY id DESC LIMIT 1")->fetchColumn();
check('Kode OTP tercatat di DB', is_string($otp) && $otp !== '', "otp=" . var_export($otp, true));

$t = token($r['body']);
$r = request('POST', "$BASE/verify-otp", 'reg', ['_token' => $t, 'email' => $email, 'kode' => $otp]);
check('Verifikasi OTP mengaktifkan akun & login', str_contains($r['body'], 'Akun Anda aktif') || str_contains($r['body'], 'Dashboard'), "HTTP {$r['code']}");

$verified = pdo()->query("SELECT email_verified_at FROM users WHERE email = " . pdo()->quote($email))->fetchColumn();
check('Akun baru terverifikasi di DB', !empty($verified));

// --- 9. Logout ---
$t = token($r['body']);
$r = request('POST', "$BASE/logout", 'reg', ['_token' => $t]);
$r2 = request('GET', "$BASE/dashboard", 'reg');
check('Logout menghapus sesi', str_contains($r2['body'], 'name="username"'), "HTTP {$r2['code']}");

// --- 10. Bersihkan data uji registrasi ---
pdo()->exec("DELETE FROM users WHERE email REGEXP '^smoke_'");

echo "\n=== Hasil: {$pass} PASS, {$fail} FAIL ===\n";
exit($fail === 0 ? 0 : 1);
