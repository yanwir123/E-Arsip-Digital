<?php

declare(strict_types=1);

/**
 * Kebijakan password minimum untuk akun lokal E-Arsip.
 *
 * Mengembalikan pesan kesalahan (string) bila password tidak memenuhi syarat,
 * atau null bila password valid. Panggil di setiap jalur pembuatan/perubahan
 * password (registrasi, tambah/ubah pengguna oleh developer, ganti password).
 */
function password_policy_error(string $password, ?string $username = null): ?string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password harus terdiri atas minimal ' . PASSWORD_MIN_LENGTH . ' karakter.';
    }

    if (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
        return 'Password harus memuat kombinasi huruf dan angka.';
    }

    if (in_array(strtolower($password), password_common_list(), true)) {
        return 'Password yang dipilih terlalu umum dan mudah ditebak. Silakan gunakan kombinasi lain yang lebih aman.';
    }

    if ($username !== null && $username !== '' && strtolower($password) === strtolower($username)) {
        return 'Password tidak boleh sama dengan username akun.';
    }

    return null;
}

/**
 * Daftar password yang paling sering dipakai (kecil, cukup untuk menolak
 * pilihan yang jelas lemah). Dibandingkan dalam huruf kecil.
 *
 * @return array<int, string>
 */
function password_common_list(): array
{
    return [
        'password', 'password1', 'passw0rd', 'administrator', 'admin123',
        'admin1234', 'adminadmin', 'qwerty', 'qwerty123', '12345678',
        '123456789', '1234567890', '11111111', '00000000', 'aaaaaaaa',
        'letmein', 'iloveyou', 'welcome', 'welcome1', 'monkey', 'dragon',
        'football', 'baseball', 'superman', 'batman', 'abc12345', 'user1234',
        'guest123', 'test1234', 'petugas123', 'developer123', 'operator',
        'polri123', 'indonesia', 'jakarta123',
    ];
}
