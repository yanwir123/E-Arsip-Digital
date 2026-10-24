<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

final class UserController extends Controller
{
    public function index(): void
    {
        require_role(ROLE_DEVELOPER);

        $this->view('users/index', [
            'title' => 'Manajemen Pengguna',
            'users' => User::all(),
        ]);
    }

    public function create(): void
    {
        require_role(ROLE_DEVELOPER);

        $this->view('users/form', [
            'title' => 'Tambah Pengguna',
            'mode'  => 'create',
            'user'  => null,
        ]);
    }

    public function store(): void
    {
        require_role(ROLE_DEVELOPER);
        verify_csrf();

        $data = $this->validate();

        User::create($data);
        audit_log('tambah_user', 'users', null, 'Menambah pengguna: ' . $data['username'] . ' (' . $data['role'] . ')');

        clear_old();
        set_flash('success', 'Akun pengguna berhasil ditambahkan ke sistem.');
        redirect('users');
    }

    public function edit(string $id): void
    {
        require_role(ROLE_DEVELOPER);

        $user = User::find((int) $id);

        if (!$user) {
            set_flash('error', 'Data pengguna tidak ditemukan. Silakan periksa kembali daftar pengguna.');
            redirect('users');
        }

        $this->view('users/form', [
            'title' => 'Ubah Pengguna',
            'mode'  => 'edit',
            'user'  => $user,
        ]);
    }

    public function update(string $id): void
    {
        require_role(ROLE_DEVELOPER);
        verify_csrf();

        $user = User::find((int) $id);

        if (!$user) {
            set_flash('error', 'Data pengguna tidak ditemukan. Silakan periksa kembali daftar pengguna.');
            redirect('users');
        }

        $data = $this->validate((int) $user['id']);
        User::update((int) $user['id'], $data);

        if (!empty($data['password'])) {
            User::updatePassword((int) $user['id'], $data['password']);
        }

        audit_log('ubah_user', 'users', (int) $user['id'], 'Mengubah data pengguna: ' . $data['username']);

        clear_old();
        set_flash('success', 'Data pengguna berhasil diperbarui.');
        redirect('users');
    }

    public function destroy(string $id): void
    {
        require_role(ROLE_DEVELOPER);
        verify_csrf();

        $user = User::find((int) $id);

        if (!$user) {
            set_flash('error', 'Data pengguna tidak ditemukan. Silakan periksa kembali daftar pengguna.');
            redirect('users');
        }

        if ((int) $user['id'] === (int) auth_user()['id']) {
            set_flash('error', 'Akun yang sedang Anda gunakan tidak dapat dihapus.');
            redirect('users');
        }

        try {
            $pdo = \App\Core\Database::connection();
            $pdo->beginTransaction();
            User::delete((int) $user['id']);
            $pdo->commit();
        } catch (\PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // FK arsip.uploader_id = ON DELETE RESTRICT: pengguna masih punya arsip.
            set_flash('error', 'Akun pengguna tidak dapat dihapus karena masih memiliki arsip terunggah. Silakan hapus atau pindahkan arsip tersebut terlebih dahulu.');
            redirect('users');
        }

        // Hapus foto profil fisik setelah baris DB terhapus.
        if (!empty($user['foto_profil'])) {
            $foto = UPLOAD_PATH . '/foto/' . basename((string) $user['foto_profil']);

            if (is_file($foto)) {
                @unlink($foto);
            }
        }

        audit_log('hapus_user', 'users', (int) $user['id'], 'Menghapus pengguna: ' . $user['username']);

        set_flash('success', 'Akun pengguna berhasil dihapus dari sistem.');
        redirect('users');
    }

    /**
     * Validasi input user. Password wajib saat create, opsional saat edit.
     */
    private function validate(?int $exceptId = null): array
    {
        $nama     = (string) $this->input('nama', '');
        $username = (string) $this->input('username', '');
        $email    = strtolower((string) $this->input('email', ''));
        $role     = (string) $this->input('role', ROLE_USER);
        $status   = (string) $this->input('status', 'aktif');
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];

        if ($nama === '') {
            $errors[] = 'Nama lengkap belum diisi. Silakan lengkapi kolom nama.';
        }

        if ($username === '' || !preg_match('/^[a-zA-Z0-9._]{3,50}$/', $username)) {
            $errors[] = 'Username harus terdiri atas 3-50 karakter dan hanya boleh memuat huruf, angka, titik, serta garis bawah.';
        } elseif (User::usernameExists($username, $exceptId)) {
            $errors[] = 'Username tersebut sudah digunakan. Silakan pilih username lain.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format alamat email tidak valid. Periksa kembali penulisannya.';
        } elseif ($email !== '' && User::emailExists($email, $exceptId)) {
            $errors[] = 'Alamat email tersebut sudah terdaftar. Gunakan alamat email lain.';
        }

        if (!array_key_exists($role, ROLES)) {
            $errors[] = 'Peran pengguna yang dipilih tidak valid.';
        }

        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $errors[] = 'Status akun yang dipilih tidak valid.';
        }

        if ($exceptId === null) {
            $passwordError = password_policy_error($password, $username);

            if ($passwordError !== null) {
                $errors[] = $passwordError;
            }
        } elseif ($password !== '') {
            $passwordError = password_policy_error($password, $username);

            if ($passwordError !== null) {
                $errors[] = $passwordError;
            }
        }

        if ($errors) {
            flash_old(compact('nama', 'username', 'email', 'role', 'status'));
            set_flash('error', implode(' ', $errors));
            redirect($exceptId === null ? 'users/create' : 'users/' . $exceptId . '/edit');
        }

        return [
            'nama'     => $nama,
            'username' => $username,
            'email'    => $email,
            'role'     => $role,
            'status'   => $status,
            'password' => $password,
        ];
    }
}
