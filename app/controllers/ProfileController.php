<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Instansi;
use App\Models\User;

final class ProfileController extends Controller
{
    private const FOTO_MAX_SIZE = 2 * 1024 * 1024; // 2 MB

    /** @var array<string, array<int, string>> */
    private const FOTO_ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function index(): void
    {
        require_login();

        $user = auth_user();
        $row  = User::find((int) $user['id']) ?? $user;

        $instansiNama = null;

        if (!empty($row['instansi_id'])) {
            $ins = Instansi::find((int) $row['instansi_id']);
            $instansiNama = $ins ? ($ins['kode'] . ' - ' . $ins['nama']) : null;
        }

        // Mode tampilan: '' (lihat data), 'biodata' (form biodata), 'password'.
        $edit = (string) $this->input('edit', '');

        if (!in_array($edit, ['', 'biodata', 'password'], true)) {
            $edit = '';
        }

        $this->view('dashboard/profile', [
            'title'        => 'Profil Saya',
            'user'         => $row,
            'instansi'     => Instansi::all(),
            'instansiNama' => $instansiNama,
            'edit'         => $edit,
        ]);
    }

    public function update(): void
    {
        require_login();
        verify_csrf();

        $user = auth_user();
        $id   = (int) $user['id'];
        $before = User::find($id);

        $nama     = (string) $this->input('nama', '');
        $instansi = (string) $this->input('instansi_id', '');
        $noInduk  = (string) $this->input('no_induk', '');
        $nip      = (string) $this->input('nip', '');
        $jabatan  = (string) $this->input('jabatan', '');
        $telepon  = (string) $this->input('telepon', '');
        $alamat   = (string) $this->input('alamat', '');

        $_SESSION['_old'] = [
            'nama'        => $nama,
            'instansi_id' => $instansi,
            'no_induk'    => $noInduk,
            'nip'         => $nip,
            'jabatan'     => $jabatan,
            'telepon'     => $telepon,
            'alamat'      => $alamat,
        ];

        if ($nama === '') {
            set_flash('error', 'Nama lengkap belum diisi. Silakan lengkapi kolom nama.');
            redirect('profil');
        }

        $instansiId = null;

        if ($instansi !== '') {
            if (!Instansi::exists((int) $instansi)) {
                set_flash('error', 'Instansi yang dipilih tidak valid. Silakan pilih instansi dari daftar yang tersedia.');
                redirect('profil');
            }

            $instansiId = (int) $instansi;
        }

        $data = [
            'nama'        => $nama,
            'instansi_id' => $instansiId,
            'no_induk'    => $noInduk,
            'nip'         => $nip,
            'jabatan'     => $jabatan,
            'telepon'     => $telepon,
            'alamat'      => $alamat,
        ];

        $hasChanges = !$before
            || (string) ($before['nama'] ?? '') !== $nama
            || (string) ($before['instansi_id'] ?? '') !== (string) ($instansiId ?? '')
            || (string) ($before['no_induk'] ?? '') !== $noInduk
            || (string) ($before['nip'] ?? '') !== $nip
            || (string) ($before['jabatan'] ?? '') !== $jabatan
            || (string) ($before['telepon'] ?? '') !== $telepon
            || (string) ($before['alamat'] ?? '') !== $alamat;

        // Unggah foto profil bila ada (opsional).
        if (!empty($_FILES['foto_profil']['name'])) {
            try {
                $data['foto_profil'] = $this->storePhoto($_FILES['foto_profil']);
            } catch (\Throwable $e) {
                set_flash('error', $e->getMessage());
                redirect('profil');
            }

            $hasChanges = true;
        }

        if (!$hasChanges) {
            clear_old();
            set_flash('info', 'Tidak ada perubahan pada biodata profil. Notifikasi tidak dikirim.');
            redirect('profil');
        }

        User::updateProfile($id, $data);

        // Hapus berkas foto lama setelah penggantian berhasil agar tidak menumpuk.
        if (
            isset($data['foto_profil'])
            && !empty($before['foto_profil'])
            && $before['foto_profil'] !== $data['foto_profil']
        ) {
            $old = UPLOAD_PATH . '/foto/' . basename((string) $before['foto_profil']);

            if (is_file($old)) {
                @unlink($old);
            }
        }

        audit_log('ubah_profil', 'users', $id, 'Memperbarui biodata profil');

        // Susun daftar perubahan untuk notifikasi email ke pemilik akun.
        $changes = [];

        if ($before) {
            $fieldLabels = [
                'nama'     => 'Nama',
                'no_induk' => 'Nomor Induk',
                'nip'      => 'NIP',
                'jabatan'  => 'Jabatan',
                'telepon'  => 'Telepon',
                'alamat'   => 'Alamat',
            ];

            foreach ($fieldLabels as $field => $label) {
                $oldVal = (string) ($before[$field] ?? '');
                $newVal = (string) ($data[$field] ?? '');

                if ($oldVal !== $newVal) {
                    $changes[] = $label . ': &quot;' . e($oldVal === '' ? '-' : $oldVal)
                        . '&quot; &rarr; &quot;' . e($newVal === '' ? '-' : $newVal) . '&quot;';
                }
            }

            $oldIns = (string) ($before['instansi_id'] ?? '');
            $newIns = (string) ($data['instansi_id'] ?? '');

            if ($oldIns !== $newIns) {
                $changes[] = 'Instansi: &quot;' . e($this->instansiLabel($oldIns))
                    . '&quot; &rarr; &quot;' . e($this->instansiLabel($newIns)) . '&quot;';
            }

            if (isset($data['foto_profil'])) {
                $changes[] = 'Foto profil diperbarui';
            }
        }

        $email    = (string) ($before['email'] ?? '');
        $terkirim = false;

        if (!empty($changes) && $email !== '') {
            $waktu = date('d-m-Y H:i');
            $pesan = 'Biodata profil Anda telah diperbarui pada ' . $waktu . '. Rincian perubahan: '
                . implode('; ', $changes) . '. Jika Anda tidak merasa melakukan perubahan ini, segera hubungi administrator.';

            $terkirim = send_notification(
                $email,
                'Perubahan profil - ' . APP_NAME,
                '<p>' . $pesan . '</p>',
                'ubah-profil'
            );

            audit_log(
                'notifikasi_ubah_profil',
                'users',
                $id,
                'Notifikasi perubahan profil ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ') . $email
            );
        }

        clear_old();

        if (empty($changes)) {
            set_flash('info', 'Tidak ada perubahan pada biodata profil. Data yang tersimpan masih sama dengan yang Anda masukkan.');
        } elseif ($terkirim) {
            set_flash('success', 'Biodata profil berhasil diperbarui. Ringkasan perubahan telah dikirim ke alamat email Anda.');
        } else {
            set_flash(
                'info',
                'Biodata profil berhasil diperbarui.'
                . ($email !== ''
                    ? ' Namun, email ringkasan perubahan gagal dikirim. Periksa konfigurasi email server.'
                    : ' Alamat email belum diisi, jadi ringkasan perubahan tidak dikirim.')
            );
        }

        redirect('profil');
    }

    public function submit(): void
    {
        require_login();
        verify_csrf();

        $user = User::find((int) auth_user()['id']);

        if (!$user) {
            set_flash('error', 'Data akun tidak ditemukan. Silakan masuk kembali atau hubungi administrator.');
            redirect('profil');
        }

        if ($user['identity_status'] === IDENTITY_TERVERIFIKASI) {
            set_flash('info', 'Identitas Anda telah terverifikasi. Tidak diperlukan pengajuan ulang.');
            redirect('profil');
        }

        // Biodata inti wajib lengkap sebelum diajukan.
        foreach (['instansi_id', 'no_induk', 'jabatan', 'telepon', 'alamat'] as $field) {
            if (empty($user[$field])) {
                set_flash('error', 'Biodata Anda belum lengkap. Mohon lengkapi instansi, nomor induk, jabatan, telepon, dan alamat sebelum mengajukan verifikasi.');
                redirect('profil');
            }
        }

        User::submitIdentity((int) $user['id']);
        audit_log('ajukan_identitas', 'users', (int) $user['id'], 'Mengajukan verifikasi identitas');

        set_flash('success', 'Pengajuan verifikasi identitas berhasil dikirim. Mohon menunggu proses pemeriksaan oleh admin atau petugas.');
        redirect('profil');
    }

    public function updatePassword(): void
    {
        require_login();
        verify_csrf();

        $user    = auth_user();
        $current = (string) ($_POST['password_lama'] ?? '');
        $new     = (string) ($_POST['password_baru'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $row = User::find((int) $user['id']);

        if (!password_verify($current, $row['password'])) {
            set_flash('error', 'Password saat ini tidak sesuai. Silakan periksa kembali.');
            redirect('profil');
        }

        $passwordError = password_policy_error($new, (string) $row['username']);

        if ($passwordError !== null) {
            set_flash('error', $passwordError);
            redirect('profil');
        }

        if ($new !== $confirm) {
            set_flash('error', 'Konfirmasi password tidak cocok. Pastikan kedua kolom password baru diisi dengan nilai yang sama.');
            redirect('profil');
        }

        User::updatePassword((int) $user['id'], $new);
        audit_log('ubah_password', 'users', (int) $user['id'], 'Pengguna mengubah password sendiri');

        // Notifikasi keamanan via email ke pemilik akun.
        $email    = (string) ($row['email'] ?? '');
        $terkirim = false;

        if ($email !== '') {
            $waktu = date('d-m-Y H:i');
            $ip    = client_ip();
            $pesan = 'Password akun ' . APP_NAME . ' Anda telah diubah pada ' . $waktu
                . ' dari alamat IP ' . $ip . '. Jika Anda tidak merasa melakukan perubahan ini, '
                . 'segera ubah password atau hubungi administrator.';

            $terkirim = send_notification(
                $email,
                'Password diubah - ' . APP_NAME,
                '<p>' . nl2br(e($pesan)) . '</p>',
                'ubah-password'
            );

            audit_log(
                'notifikasi_ubah_password',
                'users',
                (int) $user['id'],
                'Notifikasi perubahan password ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ') . $email
            );
        }

        if ($terkirim) {
            set_flash('success', 'Password berhasil diperbarui. Notifikasi keamanan telah dikirim ke alamat email Anda.');
        } else {
            set_flash(
                'info',
                'Password berhasil diperbarui.'
                . ($email !== ''
                    ? ' Namun, email notifikasi keamanan gagal dikirim. Periksa konfigurasi email server.'
                    : ' Alamat email belum diisi, jadi notifikasi keamanan tidak dikirim.')
            );
        }
        redirect('profil');
    }

    /**
     * Sajikan foto profil pengguna tertentu.
     */
    public function photo(string $id): void
    {
        require_login();

        $me      = auth_user();
        $target  = (int) $id;
        $viewer  = (int) $me['id'];

        // Hanya pemilik, admin, developer, atau petugas yang boleh melihat.
        $isStaff = has_role(ROLE_ADMIN, ROLE_PETUGAS) || $me['role'] === ROLE_DEVELOPER;

        if ($target !== $viewer && !$isStaff) {
            http_response_code(403);
            exit('Akses ditolak.');
        }

        $row = User::find($target);

        if (!$row || empty($row['foto_profil'])) {
            http_response_code(404);
            exit('Foto tidak ditemukan.');
        }

        $path = UPLOAD_PATH . '/foto/' . basename((string) $row['foto_profil']);

        if (!is_file($path)) {
            http_response_code(404);
            exit('Foto tidak ditemukan.');
        }

        header('Content-Type: ' . $this->photoMime($path));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    private function storePhoto(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Foto profil gagal diunggah (kode: ' . ($file['error'] ?? '?') . '). Silakan coba kembali.');
        }

        if ((int) $file['size'] <= 0 || (int) $file['size'] > self::FOTO_MAX_SIZE) {
            throw new \RuntimeException('Ukuran foto melebihi batas maksimum ' . format_size(self::FOTO_MAX_SIZE) . '. Silakan pilih foto dengan ukuran lebih kecil.');
        }

        $asli = basename((string) $file['name']);
        $ext  = strtolower(pathinfo($asli, PATHINFO_EXTENSION));

        if (!isset(self::FOTO_ALLOWED[$ext])) {
            throw new \RuntimeException('Format foto tidak didukung. Gunakan berkas berformat JPG, PNG, atau WEBP.');
        }

        // Verifikasi isi berkas berdasarkan konten (bukan tipe dari klien).
        $mime = detect_mime_type((string) $file['tmp_name'], $ext);

        if ($mime === null) {
            throw new \RuntimeException(
                'Jenis foto tidak dapat diverifikasi oleh server. ' .
                'Pastikan ekstensi PHP "fileinfo" aktif, kemudian coba kembali.'
            );
        }

        if (!in_array($mime, self::FOTO_ALLOWED[$ext], true)) {
            throw new \RuntimeException('Isi berkas tidak sesuai dengan format foto yang dipilih. Silakan periksa kembali berkas Anda.');
        }

        $dir = UPLOAD_PATH . '/foto';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Sistem gagal menyiapkan penyimpanan foto. Silakan coba kembali atau hubungi administrator.');
        }

        $nama = bin2hex(random_bytes(16)) . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nama)) {
            throw new \RuntimeException('Foto profil gagal disimpan. Silakan coba kembali.');
        }

        return $nama;
    }

    /**
     * Label instansi untuk keterangan perubahan (kode - nama).
     */
    private function instansiLabel(string $id): string
    {
        if ($id === '' || $id === '0') {
            return '-';
        }

        $ins = Instansi::find((int) $id);

        return $ins ? ($ins['kode'] . ' - ' . $ins['nama']) : '-';
    }

    private function photoMime(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($path);

            if ($mime !== '') {
                return $mime;
            }
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
