<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Otp;
use App\Models\User;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (auth_check()) {
            redirect('dashboard');
        }

        $this->view('auth/login', [
            'title' => 'Masuk',
        ], 'layouts/auth');
    }

    public function login(): void
    {
        verify_csrf();

        if (!verify_recaptcha()) {
            set_flash('error', 'Verifikasi reCAPTCHA gagal. Silakan coba lagi.');
            redirect('login');
        }

        $username = (string) $this->input('username', '');
        $password = (string) ($_POST['password'] ?? '');

        flash_old(['username' => $username]);

        if ($username === '' || $password === '') {
            set_flash('error', 'Username dan password belum diisi. Silakan lengkapi kedua kolom tersebut.');
            redirect('login');
        }

        // Tolak lebih awal bila sedang terkunci karena terlalu banyak gagal.
        $ip        = client_ip();
        $remaining = login_lock_remaining($ip, $username);

        if ($remaining > 0) {
            audit_log('login_terkunci', 'users', null, 'Percobaan login saat terkunci untuk username: ' . $username);
            set_flash('error', 'Terlalu banyak percobaan masuk yang tidak berhasil. Silakan coba kembali dalam ' . ceil($remaining / 60) . ' menit.');
            redirect('login');
        }

        $user = User::findByUsername($username);

        // Pesan error dibuat umum agar tidak membocorkan username mana yang ada.
        if (!$user || !password_verify($password, $user['password'])) {
            register_failed_login($ip, $username);
            audit_log('login_gagal', 'users', $user['id'] ?? null, 'Percobaan login gagal untuk username: ' . $username);
            set_flash('error', 'Username atau password tidak sesuai. Silakan periksa kembali data masuk Anda.');
            redirect('login');
        }

        if ($user['status'] !== 'aktif') {
            set_flash('error', 'Akun Anda sedang tidak aktif. Silakan hubungi administrator untuk informasi lebih lanjut.');
            redirect('login');
        }

        // Tolak jika pengguna sedang terkena sanksi (ban) aktif.
        $ban = \App\Models\Ban::activeForUser((int) $user['id']);

        if ($ban !== null) {
            audit_log('login_diblokir', 'users', (int) $user['id'], 'Percobaan login akun terblokir: ' . $username);
            set_flash('error', ban_message($ban));
            redirect('login');
        }

        clear_login_attempts($ip, $username);
        clear_old();
        auth_login($user);

        set_flash('success', 'Selamat datang, ' . $user['nama'] . '. Anda berhasil masuk ke sistem.');
        redirect('dashboard');
    }

    public function logout(): void
    {
        verify_csrf();
        auth_logout();
        set_flash('success', 'Anda telah berhasil keluar dari sistem. Terima kasih telah menggunakan layanan kami.');
        redirect('login');
    }

    // -------------------------------------------------------------------
    // Registrasi dengan verifikasi OTP via email
    // -------------------------------------------------------------------

    public function showRegister(): void
    {
        if (auth_check()) {
            redirect('dashboard');
        }

        $this->view('auth/register', [
            'title' => 'Daftar',
        ], 'layouts/auth');
    }

    public function register(): void
    {
        verify_csrf();

        if (!verify_recaptcha()) {
            set_flash('error', 'Verifikasi reCAPTCHA gagal. Silakan coba lagi.');
            redirect('register');
        }

        if (auth_check()) {
            redirect('dashboard');
        }

        $nama     = (string) $this->input('nama', '');
        $username = (string) $this->input('username', '');
        $email    = strtolower((string) $this->input('email', ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        flash_old(compact('nama', 'username', 'email'));

        $errors = [];

        if ($nama === '') {
            $errors[] = 'Nama lengkap belum diisi. Silakan lengkapi kolom nama.';
        }

        if ($username === '' || !preg_match('/^[a-zA-Z0-9._]{3,50}$/', $username)) {
            $errors[] = 'Username harus terdiri atas 3-50 karakter dan hanya boleh memuat huruf, angka, titik, serta garis bawah.';
        } elseif (User::usernameExists($username)) {
            $errors[] = 'Username tersebut sudah digunakan. Silakan pilih username lain.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format alamat email tidak valid. Periksa kembali penulisannya.';
        } elseif (User::emailExists($email)) {
            $errors[] = 'Alamat email tersebut sudah terdaftar. Silakan gunakan alamat email lain.';
        }

        $passwordError = password_policy_error($password, $username);

        if ($passwordError !== null) {
            $errors[] = $passwordError;
        }

        if ($password !== $confirm) {
            $errors[] = 'Konfirmasi password tidak cocok. Pastikan kedua kolom password diisi dengan nilai yang sama.';
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect('register');
        }

        // Hemat waktu: kirim OTP dulu, akun dibuat setelah verifikasi.
        $draft = [
            'nama'          => $nama,
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];

        $_SESSION['_reg_draft'] = $draft;

        $ip = client_ip();

        // Batasi pengiriman OTP agar tidak dipakai untuk spam email.
        $throttle = Otp::throttleError($email, $ip);

        if ($throttle !== null) {
            audit_log('otp_dibatasi', 'otp_codes', null, 'Permintaan OTP dibatasi untuk: ' . $email);
            set_flash('error', $throttle);
            redirect('register');
        }

        $otp = Otp::generate($email, Otp::TUJUAN_REGISTRASI, $draft);
        Otp::recordSend($email, $ip);

        if (!send_otp_email($email, $otp['kode'])) {
            set_flash('error', 'Kode verifikasi gagal dikirim. Silakan coba kembali atau hubungi administrator.');
            unset($_SESSION['_reg_draft']);
            redirect('register');
        }

        audit_log('otp_terkirim', 'otp_codes', null, 'Mengirim kode OTP registrasi ke: ' . $email);

        set_flash('success', 'Kode verifikasi telah dikirim ke ' . $email . '. Silakan periksa kotak masuk atau folder spam Anda.');
        redirect('verify-otp');
    }

    public function showVerifyOtp(): void
    {
        if (auth_check()) {
            redirect('dashboard');
        }

        $email = (string) ($_SESSION['_reg_draft']['email'] ?? $this->input('email', ''));

        if ($email === '') {
            set_flash('error', 'Sesi pendaftaran tidak ditemukan. Silakan melakukan pendaftaran terlebih dahulu.');
            redirect('register');
        }

        $this->view('auth/verify-otp', [
            'title' => 'Verifikasi Email',
            'email' => $email,
        ], 'layouts/auth');
    }

    public function verifyOtp(): void
    {
        verify_csrf();

        $email = strtolower((string) $this->input('email', ''));
        $kode  = preg_replace('/\D/', '', (string) $this->input('kode', ''));

        if ($email === '' || $kode === '') {
            set_flash('error', 'Alamat email dan kode verifikasi belum lengkap. Silakan isi kedua kolom tersebut.');
            redirect('verify-otp');
        }

        // Ambil data pendaftaran SEBELUM Otp::check menandai kode terpakai.
        $draft = Otp::data($email, Otp::TUJUAN_REGISTRASI);

        if (!Otp::check($email, Otp::TUJUAN_REGISTRASI, $kode)) {
            audit_log('otp_salah', 'otp_codes', null, 'Kode OTP salah/kedaluwarsa untuk: ' . $email);
            set_flash('error', 'Kode verifikasi tidak sesuai atau telah kedaluwarsa. Periksa kembali atau minta kode baru.');
            redirect('verify-otp');
        }

        $this->activateRegistration($email, $draft);
    }

    public function verifyOtpToken(string $token): void
    {
        if (auth_check()) {
            redirect('dashboard');
        }

        $payload = jwt_decode($token);

        if (
            !is_array($payload) ||
            ($payload['purpose'] ?? '') !== 'verify_email' ||
            !isset($payload['email'], $payload['otp'])
        ) {
            set_flash('error', 'Tautan verifikasi tidak valid atau telah kedaluwarsa. Silakan lakukan pendaftaran ulang.');
            redirect('register');
        }

        $email = strtolower((string) $payload['email']);

        // Ambil data pendaftaran SEBELUM Otp::check menandai kode terpakai.
        // Penting agar tautan email tetap berfungsi meski dibuka dari
        // perangkat/browser lain (tanpa sesi pendaftaran).
        $draft = Otp::data($email, Otp::TUJUAN_REGISTRASI);

        if (!Otp::check($email, Otp::TUJUAN_REGISTRASI, (string) $payload['otp'])) {
            set_flash('error', 'Tautan verifikasi telah digunakan atau kedaluwarsa. Silakan daftar ulang untuk mendapatkan tautan baru.');
            redirect('register');
        }

        $this->activateRegistration($email, $draft);
    }

    public function resendOtp(): void
    {
        verify_csrf();

        $draft = $_SESSION['_reg_draft'] ?? null;

        if (!is_array($draft) || !isset($draft['email'])) {
            set_flash('error', 'Sesi pendaftaran tidak ditemukan atau telah berakhir. Silakan lakukan pendaftaran ulang.');
            redirect('register');
        }

        $email = strtolower((string) $draft['email']);

        if (User::emailExists($email)) {
            unset($_SESSION['_reg_draft']);
            set_flash('error', 'Alamat email tersebut sudah terdaftar. Silakan masuk menggunakan akun yang ada.');
            redirect('login');
        }

        $ip = client_ip();
        $throttle = Otp::throttleError($email, $ip);

        if ($throttle !== null) {
            audit_log('otp_dibatasi', 'otp_codes', null, 'Kirim ulang OTP dibatasi untuk: ' . $email);
            set_flash('error', $throttle);
            redirect('verify-otp');
        }

        $otp = Otp::generate($email, Otp::TUJUAN_REGISTRASI, $draft);
        Otp::recordSend($email, $ip);

        if (!send_otp_email($email, $otp['kode'])) {
            set_flash('error', 'Email verifikasi gagal dikirim. Silakan coba kembali beberapa saat lagi.');
            redirect('verify-otp');
        }

        audit_log('otp_ulang', 'otp_codes', null, 'Mengirim ulang kode OTP ke: ' . $email);

        set_flash('success', 'Kode verifikasi baru telah dikirim ke ' . $email . '. Silakan periksa kotak masuk Anda.');
        redirect('verify-otp');
    }

    /**
     * Buat akun dari data pendaftaran yang tersimpan, lalu auto-login.
     *
     * @param array<string, mixed>|null $draft Data pendaftaran; bila null,
     *        diambil dari tabel otp_codes lalu dari sesi.
     */
    private function activateRegistration(string $email, ?array $draft = null): void
    {
        $draft ??= Otp::data($email, Otp::TUJUAN_REGISTRASI)
            ?? (is_array($_SESSION['_reg_draft'] ?? null) ? $_SESSION['_reg_draft'] : null);

        if (!is_array($draft) || !isset($draft['nama'], $draft['username'], $draft['password_hash'])) {
            set_flash('error', 'Data pendaftaran tidak ditemukan atau telah berakhir. Silakan lakukan pendaftaran ulang.');
            unset($_SESSION['_reg_draft']);
            redirect('register');
        }

        if (User::usernameExists($draft['username']) || User::emailExists($email)) {
            set_flash('error', 'Username atau alamat email telah digunakan. Silakan masuk menggunakan akun lain.');
            unset($_SESSION['_reg_draft']);
            redirect('login');
        }

        $id = User::create([
            'nama'              => $draft['nama'],
            'username'          => $draft['username'],
            'email'             => $email,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'password_hash'     => $draft['password_hash'],
            'role'              => ROLE_USER,
            'status'            => 'aktif',
        ]);

        audit_log('daftar', 'users', $id, 'Akun baru terverifikasi: ' . $draft['username'] . ' (' . $email . ')');

        unset($_SESSION['_reg_draft']);

        $user = User::find($id);

        auth_login($user);
        set_flash('success', 'Selamat datang, ' . $user['nama'] . '. Akun Anda telah aktif dan siap digunakan.');
        redirect('dashboard');
    }
}
