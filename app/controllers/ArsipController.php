<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Arsip;
use App\Models\AuditLog;
use App\Models\User;

final class ArsipController extends Controller
{
    /**
     * Ekstensi & MIME yang diizinkan untuk arsip.
     */
    private const ALLOWED = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'txt'  => ['text/plain'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    public function index(): void
    {
        require_login();

        $filters = [
            'status' => (string) $this->input('status', ''),
            'q'      => (string) $this->input('q', ''),
        ];

        $total     = Arsip::countAccessible(auth_user(), $filters);
        $paginator = paginate($total, PER_PAGE, (int) $this->input('page', 1));

        $this->view('arsip/index', [
            'title'           => 'Daftar Arsip',
            'arsip'           => Arsip::listAccessible(auth_user(), $filters, $paginator['per_page'], $paginator['offset']),
            'filters'         => $filters,
            'paginator'       => $paginator,
            'paginationPath'  => 'arsip',
            'paginationQuery' => $filters,
        ]);
    }

    public function create(): void
    {
        require_login();

        $this->view('arsip/create', [
            'title' => 'Unggah Arsip',
        ]);
    }

    public function store(): void
    {
        require_login();
        verify_csrf();

        $judul     = (string) $this->input('judul', '');
        $kode      = (string) $this->input('kode', '');
        $kategori  = (string) $this->input('kategori', '');
        $deskripsi = (string) $this->input('deskripsi', '');

        flash_old(compact('judul', 'kode', 'kategori', 'deskripsi'));

        if ($judul === '') {
            set_flash('error', 'Judul arsip belum diisi. Silakan lengkapi kolom judul sebelum melanjutkan.');
            redirect('arsip/create');
        }

        try {
            $file = $this->storeUploadedFile($_FILES['file'] ?? []);
        } catch (\Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect('arsip/create');
        }

        $id = Arsip::create([
            'kode'        => $kode !== '' ? $kode : null,
            'judul'       => $judul,
            'deskripsi'   => $deskripsi,
            'kategori'    => $kategori,
            'file_path'   => $file['path'],
            'file_asli'   => $file['asli'],
            'file_tipe'   => $file['tipe'],
            'file_ukuran' => $file['ukuran'],
            'uploader_id' => (int) auth_user()['id'],
        ]);

        audit_log('upload_arsip', 'arsip', $id, 'Mengunggah arsip: ' . $judul);

        clear_old();
        set_flash('success', 'Arsip berhasil diunggah. Dokumen Anda kini menunggu proses validasi oleh administrator.');
        redirect('arsip/' . $id);
    }

    public function edit(string $id): void
    {
        require_login();

        $arsip = Arsip::find((int) $id);

        if (!$arsip) {
            set_flash('error', 'Arsip yang Anda maksud tidak ditemukan. Silakan periksa kembali daftar arsip.');
            redirect('arsip');
        }

        if (!can_manage_arsip($arsip)) {
            set_flash('error', 'Anda tidak memiliki hak akses untuk mengubah arsip ini. Silakan hubungi administrator apabila memerlukan bantuan.');
            redirect('arsip/' . $arsip['id']);
        }

        $this->view('arsip/edit', [
            'title' => 'Ubah Arsip',
            'arsip' => $arsip,
        ]);
    }

    public function update(string $id): void
    {
        require_login();
        verify_csrf();

        $arsip = Arsip::find((int) $id);

        if (!$arsip) {
            set_flash('error', 'Arsip yang Anda maksud tidak ditemukan. Silakan periksa kembali daftar arsip.');
            redirect('arsip');
        }

        if (!can_manage_arsip($arsip)) {
            set_flash('error', 'Anda tidak memiliki hak akses untuk mengubah arsip ini. Silakan hubungi administrator apabila memerlukan bantuan.');
            redirect('arsip/' . $arsip['id']);
        }

        $judul     = (string) $this->input('judul', '');
        $kode      = (string) $this->input('kode', '');
        $kategori  = (string) $this->input('kategori', '');
        $deskripsi = (string) $this->input('deskripsi', '');

        flash_old(compact('judul', 'kode', 'kategori', 'deskripsi'));

        if ($judul === '') {
            set_flash('error', 'Judul arsip belum diisi. Silakan lengkapi kolom judul sebelum melanjutkan.');
            redirect('arsip/' . $arsip['id'] . '/edit');
        }

        // Ganti berkas hanya bila user memilih file baru.
        $newFile = null;

        if (isset($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newFile = $this->storeUploadedFile($_FILES['file']);
            } catch (\Throwable $e) {
                set_flash('error', $e->getMessage());
                redirect('arsip/' . $arsip['id'] . '/edit');
            }
        }

        $kodeBaru = $kode !== '' ? $kode : null;
        $perubahan = [];

        if ($judul !== (string) $arsip['judul']) {
            $perubahan[] = 'judul diubah menjadi "' . $judul . '"';
        }
        if ($kodeBaru !== ($arsip['kode'] ?? null)) {
            $perubahan[] = 'kode diperbarui';
        }
        if ($kategori !== (string) ($arsip['kategori'] ?? '')) {
            $perubahan[] = 'kategori diperbarui';
        }
        if ($deskripsi !== (string) ($arsip['deskripsi'] ?? '')) {
            $perubahan[] = 'deskripsi diperbarui';
        }
        if ($newFile !== null) {
            $perubahan[] = 'berkas/file diganti';
        }

        if ($perubahan === []) {
            clear_old();
            set_flash('info', 'Tidak ada perubahan pada arsip. Data tidak disimpan ulang dan notifikasi tidak dikirim.');
            redirect('arsip/' . $arsip['id']);
        }

        Arsip::update((int) $arsip['id'], [
            'kode'      => $kodeBaru,
            'judul'     => $judul,
            'deskripsi' => $deskripsi,
            'kategori'  => $kategori,
        ], $newFile);

        // Hapus berkas lama setelah penggantian berhasil.
        if ($newFile !== null) {
            $old = UPLOAD_PATH . '/' . $arsip['file_path'];

            if (is_file($old)) {
                @unlink($old);
            }

            audit_log('ganti_file_arsip', 'arsip', (int) $arsip['id'], 'Mengganti berkas arsip: ' . $judul);
        }

        audit_log('ubah_arsip', 'arsip', (int) $arsip['id'], 'Mengubah data arsip: ' . $judul);

        // Arsip yang sebelumnya ditolak, bila diperbaiki pengunggah, masuk
        // kembali ke antrean validasi.
        if (
            $arsip['status'] === ARSIP_REJECTED
            && !in_array(auth_user()['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true)
        ) {
            Arsip::reopen((int) $arsip['id']);
            audit_log('ajukan_ulang_arsip', 'arsip', (int) $arsip['id'], 'Mengajukan ulang arsip yang ditolak: ' . $judul);
        }

        // Kirim notifikasi ke pemilik arsip bila diubah oleh pihak lain
        // (admin/petugas/developer): siapa yang mengubah dan kapan.
        $editor   = auth_user();
        $uploader = User::find((int) $arsip['uploader_id']);

        if ($uploader && (int) $uploader['id'] !== (int) $editor['id']) {
            $waktu = date('d-m-Y H:i');
            $pesan = 'Arsip Anda "' . $judul . '" telah ' . ($newFile !== null ? 'diperbarui berkasnya' : 'diubah')
                . ' oleh ' . $editor['nama'] . ' (' . role_label($editor['role']) . ') pada ' . $waktu . '. '
                . 'Rincian perubahan: ' . implode(', ', $perubahan) . '.';

            $email    = (string) ($uploader['email'] ?? '');
            $terkirim = false;

            if ($email !== '') {
                $terkirim = send_notification(
                    $email,
                    'Pembaruan arsip - ' . APP_NAME,
                    '<p>' . nl2br(e($pesan)) . '</p>',
                    'ubah-arsip'
                );
            }

            audit_log(
                'notifikasi_ubah_arsip',
                'arsip',
                (int) $arsip['id'],
                'Notifikasi perubahan arsip ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                    . ($email !== '' ? $email : 'pengunggah tanpa email')
            );
        }

        clear_old();
        set_flash('success', 'Perubahan pada arsip berhasil disimpan.');
        redirect('arsip/' . $arsip['id']);
    }

    public function show(string $id): void
    {
        require_login();

        $arsip = Arsip::find((int) $id);

        if (!$arsip) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Tidak Ditemukan'], '');
            return;
        }

        if (!can_access_arsip($arsip)) {
            set_flash('error', 'Anda tidak memiliki izin untuk melihat arsip ini. Silakan ajukan permohonan akses kepada administrator.');
            redirect('arsip');
        }

        // Jangan catat "lihat_arsip" saat pengguna hanya berpindah halaman atau
        // memfilter histori. Jika tidak, total dan urutan histori berubah
        // setiap kali paginasi diklik.
        $isHistoryRequest = $this->input('history_page') !== null
            || $this->input('history_per_page') !== null
            || $this->input('history_q') !== null
            || $this->input('history_aksi') !== null
            || $this->input('history_user') !== null;

        if (!$isHistoryRequest) {
            audit_log('lihat_arsip', 'arsip', (int) $arsip['id'], 'Melihat detail arsip: ' . $arsip['judul']);
        }

        // Filter histori arsip. Parameter diberi awalan history_ agar tidak
        // bentrok dengan parameter lain di halaman detail arsip.
        $historyFilters = [
            'aksi'    => trim((string) $this->input('history_aksi', '')),
            'user_id' => trim((string) $this->input('history_user', '')),
            'q'       => trim((string) $this->input('history_q', '')),
        ];

        $perPageOptions = [5, 10, 25, 50];
        $historyPerPage = (int) $this->input('history_per_page', 10);

        if (!in_array($historyPerPage, $perPageOptions, true)) {
            $historyPerPage = 10;
        }

        $historyTotal     = AuditLog::countForTarget('arsip', (int) $arsip['id'], $historyFilters);
        $historyPaginator = paginate($historyTotal, $historyPerPage, (int) $this->input('history_page', 1));
        $historyQuery     = [
            'history_aksi'      => $historyFilters['aksi'],
            'history_user'      => $historyFilters['user_id'],
            'history_q'         => $historyFilters['q'],
            'history_per_page'  => $historyPerPage,
        ];

        $this->view('arsip/show', [
            'title'            => $arsip['judul'],
            'arsip'            => $arsip,
            'history'          => AuditLog::forTarget(
                'arsip',
                (int) $arsip['id'],
                $historyFilters,
                $historyPaginator['per_page'],
                $historyPaginator['offset']
            ),
            'historyFilters'       => $historyFilters,
            'historyQuery'         => $historyQuery,
            'historyPaginator'     => $historyPaginator,
            'historyActions'       => AuditLog::distinctActionsForTarget('arsip', (int) $arsip['id']),
            'historyUsers'         => AuditLog::usersForTarget('arsip', (int) $arsip['id']),
            'historyPerPage'        => $historyPerPage,
            'historyPerPageOptions' => $perPageOptions,
        ]);

    }

    public function download(string $id): void
    {
        require_login();

        $arsip = Arsip::find((int) $id);

        if (!$arsip || !can_access_arsip($arsip)) {
            http_response_code(403);
            exit('Akses ditolak.');
        }

        $path = UPLOAD_PATH . '/' . $arsip['file_path'];

        if (!is_file($path)) {
            http_response_code(404);
            exit('File tidak ditemukan di server.');
        }

        audit_log('unduh_arsip', 'arsip', (int) $arsip['id'], 'Mengunduh arsip: ' . $arsip['judul']);

        // Nama unduhan diamankan dari header injection & karakter non-ASCII.
        $namaAsli = basename((string) $arsip['file_asli']);
        $namaAman = str_replace(['"', '\\', "\r", "\n"], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $namaAsli));

        header('Content-Type: ' . $arsip['file_tipe']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $namaAman . '"; filename*=UTF-8\'\'' . rawurlencode($namaAsli));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function preview(string $id): void
    {
        require_login();

        $arsip = Arsip::find((int) $id);

        if (!$arsip || !can_access_arsip($arsip)) {
            http_response_code(403);
            exit('Akses ditolak.');
        }

        $path = UPLOAD_PATH . '/' . $arsip['file_path'];

        if (!is_file($path)) {
            http_response_code(404);
            exit('File tidak ditemukan di server.');
        }

        audit_log('lihat_berkas', 'arsip', (int) $arsip['id'], 'Pratinjau berkas arsip: ' . $arsip['judul']);

        // Hapus header keamanan bawaan agar berkas bisa tampil inline di
        // halaman aplikasi sendiri, lalu pasang kebijakan yang lebih aman.
        header_remove('X-Frame-Options');
        header_remove('Content-Security-Policy');
        header("Content-Security-Policy: frame-ancestors 'self'");
        header('X-Content-Type-Options: nosniff');

        // Nama file diamankan dari header injection & karakter non-ASCII.
        $namaAsli = basename((string) $arsip['file_asli']);
        $namaAman = str_replace(['"', '\\', "\r", "\n"], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $namaAsli));

        $tipe        = (string) $arsip['file_tipe'];
        $previewable = preview_kind($tipe) !== '';

        header('Content-Type: ' . $tipe);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($previewable ? 'inline' : 'attachment')
            . '; filename="' . $namaAman . '"; filename*=UTF-8\'\'' . rawurlencode($namaAsli));

        readfile($path);
        exit;
    }

    public function destroy(string $id): void
    {
        require_login();
        verify_csrf();

        $user  = auth_user();
        $arsip = Arsip::find((int) $id);

        if (!$arsip) {
            set_flash('error', 'Arsip yang Anda maksud tidak ditemukan. Silakan periksa kembali daftar arsip.');
            redirect('arsip');
        }

        $isOwner = (int) $arsip['uploader_id'] === (int) $user['id'];
        $isAdmin = in_array($user['role'], [ROLE_DEVELOPER, ROLE_ADMIN], true);

        if (!$isAdmin && !$isOwner) {
            set_flash('error', 'Anda tidak memiliki hak akses untuk menghapus arsip ini.');
            redirect('arsip');
        }

        // Pemilik hanya boleh menghapus arsip yang belum disetujui.
        if (!$isAdmin && $arsip['status'] === ARSIP_APPROVED) {
            set_flash('error', 'Arsip yang telah disetujui tidak dapat dihapus secara mandiri. Silakan hubungi administrator untuk proses penghapusan.');
            redirect('arsip/' . $arsip['id']);
        }

        $file = UPLOAD_PATH . '/' . $arsip['file_path'];
        if (is_file($file)) {
            @unlink($file);
        }

        $uploader = User::find((int) $arsip['uploader_id']);
        $admin    = $user;
        $alasanHapus = (string) $this->input('alasan_hapus', 'Tidak ada keterangan');

        Arsip::delete((int) $arsip['id']);
        audit_log('hapus_arsip', 'arsip', (int) $arsip['id'], 'Menghapus arsip: ' . $arsip['judul']);

        // Notifikasi ke pemilik arsip (bila yang menghapus bukan dirinya sendiri).
        if ($uploader && (int) $uploader['id'] !== (int) $user['id']) {
            $waktu = date('d-m-Y H:i');
            $msg = 'Arsip Anda "' . $arsip['judul'] . '" telah dihapus oleh '
                . $admin['nama'] . ' (' . role_label($admin['role']) . ') pada ' . $waktu . '. '
                . 'Keterangan: ' . $alasanHapus . '.';

            $email    = (string) ($uploader['email'] ?? '');
            $terkirim = false;

            if ($email !== '') {
                $terkirim = send_notification(
                    $email,
                    'Penghapusan arsip - ' . APP_NAME,
                    '<p>' . nl2br(e($msg)) . '</p>',
                    'hapus-arsip'
                );
            }

            audit_log(
                'notifikasi_hapus_arsip',
                'arsip',
                (int) $arsip['id'],
                'Notifikasi penghapusan ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                    . ($email !== '' ? $email : 'pengunggah tanpa email')
            );
        }

        set_flash('success', 'Arsip berhasil dihapus dari sistem.');
        redirect('arsip');
    }

    /**
     * Tangani file upload dengan aman.
     *
     * @return array{path:string, asli:string, tipe:string, ukuran:int}
     */
    private function storeUploadedFile(array $file): array
    {
        if (!isset($file['error'])) {
            throw new \RuntimeException('Berkas arsip belum dipilih. Silakan pilih berkas yang akan diunggah.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage((int) $file['error']));
        }

        if ($file['size'] <= 0) {
            throw new \RuntimeException('Berkas yang dipilih kosong. Silakan pilih berkas lain.');
        }

        if ($file['size'] > UPLOAD_MAX_SIZE) {
            throw new \RuntimeException('Ukuran berkas melebihi batas maksimum ' . format_size(UPLOAD_MAX_SIZE) . '. Silakan pilih berkas yang lebih kecil.');
        }

        $asli = basename((string) $file['name']);
        $ext  = strtolower(pathinfo($asli, PATHINFO_EXTENSION));

        if (!isset(self::ALLOWED[$ext])) {
            throw new \RuntimeException('Format berkas .' . $ext . ' tidak didukung. Silakan gunakan format berkas yang telah disediakan.');
        }

        // Verifikasi MIME asli dari isi berkas (JANGAN memercayai tipe yang
        // dilaporkan klien). Bila tipe tak dapat diverifikasi, tolak unggahan.
        $mime = detect_mime_type((string) $file['tmp_name'], $ext);

        if ($mime === null) {
            throw new \RuntimeException(
                'Jenis berkas tidak dapat diverifikasi oleh server. ' .
                'Pastikan ekstensi PHP "fileinfo" aktif, kemudian coba kembali.'
            );
        }

        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            throw new \RuntimeException('Isi berkas tidak sesuai dengan ekstensinya. Silakan periksa kembali berkas yang dipilih.');
        }

        $sub   = date('Y/m');
        $dir   = UPLOAD_PATH . '/' . $sub;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Sistem gagal menyiapkan penyimpanan berkas. Silakan coba beberapa saat lagi atau hubungi administrator.');
        }

        $nama = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $nama;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Berkas gagal diproses oleh server. Silakan coba unggah kembali.');
        }

        return [
            'path'   => $sub . '/' . $nama,
            'asli'   => $asli,
            'tipe'   => $mime,
            'ukuran' => (int) $file['size'],
        ];
    }

    /**
     * Terjemahkan kode error upload PHP menjadi pesan yang mudah dipahami.
     */
    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE
                => 'Ukuran berkas melebihi batas yang diizinkan server. Silakan pilih berkas yang lebih kecil.',
            UPLOAD_ERR_PARTIAL
                => 'Berkas hanya terunggah sebagian. Silakan ulangi proses unggah.',
            UPLOAD_ERR_NO_FILE
                => 'Berkas arsip belum dipilih. Silakan pilih berkas yang akan diunggah.',
            UPLOAD_ERR_NO_TMP_DIR
                => 'Folder sementara server tidak tersedia. Silakan coba kembali atau hubungi administrator.',
            UPLOAD_ERR_CANT_WRITE
                => 'Server gagal menyimpan berkas. Silakan coba kembali beberapa saat lagi.',
            UPLOAD_ERR_EXTENSION
                => 'Proses unggah dihentikan oleh konfigurasi server. Silakan hubungi administrator.',
            default
                => 'Berkas gagal diunggah. Silakan coba kembali.',
        };
    }
}
