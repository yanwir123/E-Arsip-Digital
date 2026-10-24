<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Ban;
use App\Models\LoginAttempt;

/**
 * Manajemen autentikasi & otorisasi (RBAC).
 */

/**
 * Data user yang sedang login (di-cache per request).
 */
function auth_user(): ?array
{
    static $cached = null;
    static $loaded = false;

    if ($loaded) {
        return $cached;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;

    if (!$id) {
        return $cached = null;
    }

    $stmt = Database::run(
        'SELECT id, nama, username, role, status, foto_profil, identity_status, must_change_password
         FROM users WHERE id = :id LIMIT 1',
        ['id' => $id]
    );
    $user = $stmt->fetch();

    // Jika user dihapus / dinonaktifkan, paksa logout.
    if (!$user || $user['status'] !== 'aktif') {
        auth_logout();
        return $cached = null;
    }

    // Jika user sedang terkena sanksi (ban) aktif, paksa logout.
    if (Ban::isBanned((int) $user['id'])) {
        auth_logout();
        return $cached = null;
    }

    return $cached = $user;
}

function auth_check(): bool
{
    return auth_user() !== null;
}

function auth_role(): ?string
{
    return auth_user()['role'] ?? null;
}

function is_developer(): bool
{
    return auth_role() === ROLE_DEVELOPER;
}

/**
 * Cek apakah user punya salah satu role. Developer selalu dianggap punya.
 */
function has_role(string ...$roles): bool
{
    $role = auth_role();

    if ($role === null) {
        return false;
    }

    if ($role === ROLE_DEVELOPER) {
        return true;
    }

    return in_array($role, $roles, true);
}

/**
 * Simpan sesi login dengan aman (cegah session fixation).
 */
function auth_login(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role']    = $user['role'];

    Database::run(
        'UPDATE users SET last_login = NOW() WHERE id = :id',
        ['id' => (int) $user['id']]
    );

    audit_log('login', 'users', (int) $user['id'], 'Pengguna berhasil login');
}

function auth_logout(): void
{
    if (isset($_SESSION['user_id'])) {
        audit_log('logout', 'users', (int) $_SESSION['user_id'], 'Pengguna logout');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/**
 * Wajib login. Jika belum, alihkan ke halaman login.
 */
function require_login(): void
{
    if (!auth_check()) {
        set_flash('error', 'Sesi Anda belum aktif. Silakan masuk terlebih dahulu untuk melanjutkan.');
        redirect('login');
    }

    enforce_password_change();
}

/**
 * Paksa penggantian password bawaan (must_change_password) sebelum pengguna
 * boleh mengakses fitur lain. Hanya aktif di lingkungan produksi agar alur
 * pengembangan/pengujian tidak terganggu.
 */
function enforce_password_change(): void
{
    if (APP_ENV !== 'production') {
        return;
    }

    $user = auth_user();

    if ($user === null || (int) ($user['must_change_password'] ?? 0) !== 1) {
        return;
    }

    $path = current_request_path();

    // Izinkan halaman yang diperlukan untuk mengganti password & keluar.
    if ($path === 'logout' || str_starts_with($path, 'profil') || str_starts_with($path, 'foto')) {
        return;
    }

    set_flash('error', 'Demi keamanan akun, silakan ganti password bawaan Anda sebelum melanjutkan.');
    redirect('profil?edit=password');
}

/**
 * Path relatif request saat ini (tanpa base dir / query string).
 */
function current_request_path(): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $dir  = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));

    if ($dir !== '/' && $dir !== '' && str_starts_with($path, $dir)) {
        $path = substr($path, strlen($dir));
    }

    return trim($path, '/');
}

/**
 * Susun pesan sanksi (ban) yang mudah dipahami pengguna.
 */
function ban_message(array $ban): string
{
    $tingkat = PENALTY_LEVEL[$ban['tingkat']] ?? $ban['tingkat'];
    $sampai  = $ban['banned_until']
        ? 'sampai ' . date('d M Y H:i', strtotime((string) $ban['banned_until']))
        : 'tanpa batas waktu (permanen)';

    return "Akun Anda untuk sementara dibatasi karena terindikasi melanggar kode etik "
        . "(tingkat: {$tingkat}). Pembatasan berlaku {$sampai}. "
        . "Untuk mengajukan pembukaan akun, silakan menghubungi petugas dan datang langsung ke instansi terkait.";
}

/**
 * Wajib memiliki salah satu role.
 */
function require_role(string ...$roles): void
{
    require_login();

    if (!has_role(...$roles)) {
        http_response_code(403);
        $file = APP_PATH . '/views/errors/403.php';

        if (is_file($file)) {
            require $file;
        } else {
            echo '403 Forbidden';
        }

        exit;
    }
}

// ---------------------------------------------------------------------------
// Rate limit login (berbasis database: login_attempts)
//
// Dihitung per-akun (username) DAN per-IP agar penyerang tidak bisa
// mengunci akun orang lain maupun menguras password satu akun.
// ---------------------------------------------------------------------------

/**
 * Sisa waktu (detik) sampai boleh mencoba login lagi. 0 = tidak terkunci.
 */
function login_lock_remaining(string $ip, string $username): int
{
    $byIp    = LoginAttempt::countByIp($ip, LOGIN_LOCKOUT_SECONDS);
    $byUser  = LoginAttempt::countByUsername($username, LOGIN_LOCKOUT_SECONDS);

    if ($byIp < LOGIN_MAX_ATTEMPTS && $byUser < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }

    $latest = LoginAttempt::latestAt($ip, $username, LOGIN_LOCKOUT_SECONDS);

    if ($latest === null) {
        return LOGIN_LOCKOUT_SECONDS;
    }

    $sisa = LOGIN_LOCKOUT_SECONDS - (time() - strtotime($latest));

    return max(1, $sisa);
}

/**
 * Catat satu percobaan login gagal untuk kombinasi IP + username.
 */
function register_failed_login(string $ip, string $username): void
{
    LoginAttempt::recordFailure($ip, $username);
}

/**
 * Bersihkan riwayat gagal (dipanggil setelah login sukses).
 */
function clear_login_attempts(string $ip, string $username): void
{
    LoginAttempt::clear($ip, $username);
}

/**
 * Apakah role ini boleh melihat SELURUH arsip (tanpa penyaringan pengunggah)?
 * - Developer & Admin: melihat seluruh arsip.
 * - Petugas & Pengguna: hanya arsip unggahannya sendiri + yang diberi izin.
 */
function sees_all_arsip(array $user): bool
{
    return in_array($user['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true);
}

/**
 * Cek izin membaca sebuah arsip:
 * - Developer & Admin: selalu boleh.
 * - Pengunggah: boleh melihat arsipnya sendiri.
 * - Pengguna lain: hanya bila terdaftar di tabel arsip_akses.
 */
function can_access_arsip(array $arsip): bool
{
    $user = auth_user();

    if ($user === null) {
        return false;
    }

    if (sees_all_arsip($user)) {
        return true;
    }

    if ((int) $arsip['uploader_id'] === (int) $user['id']) {
        return true;
    }

    $stmt = Database::run(
        'SELECT 1 FROM arsip_akses WHERE arsip_id = :arsip_id AND user_id = :user_id LIMIT 1',
        ['arsip_id' => (int) $arsip['id'], 'user_id' => (int) $user['id']]
    );

    return (bool) $stmt->fetchColumn();
}

/**
 * Cek apakah user boleh MENGUBAH (edit/hapus) sebuah arsip:
 * - Developer & Admin: selalu boleh.
 * - Pengunggah: hanya bila arsip belum disetujui.
 */
function can_manage_arsip(array $arsip): bool
{
    $user = auth_user();

    if ($user === null) {
        return false;
    }

    if (in_array($user['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true)) {
        return true;
    }

    return (int) $arsip['uploader_id'] === (int) $user['id']
        && $arsip['status'] !== ARSIP_APPROVED;
}
