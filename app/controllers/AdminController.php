<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Arsip;
use App\Models\AuditLog;
use App\Models\Ban;
use App\Models\User;

final class AdminController extends Controller
{
    public function validasi(): void
    {
        require_role(ROLE_ADMIN);

        // Bersihkan arsip pending yang kedaluwarsa (juga dijalankan oleh CLI).
        Arsip::deleteExpiredPending(PENDING_AUTO_DELETE_HOURS);

        $this->view('admin/validasi', [
            'title'   => 'Validasi Arsip',
            'pending' => Arsip::pending(),
        ]);
    }

    public function akses(string $id): void
    {
        require_role(ROLE_ADMIN);

        $arsip = Arsip::find((int) $id);

        if (!$arsip) {
            set_flash('error', 'Arsip yang Anda maksud tidak ditemukan. Silakan periksa kembali daftar arsip.');
            redirect('admin/validasi');
        }

        // Kandidat penerima akses: seluruh user (kecuali developer).
        $users = array_values(array_filter(
            User::all(),
            static fn ($u) => $u['role'] !== ROLE_DEVELOPER && $u['status'] === 'aktif'
        ));

        $this->view('admin/akses', [
            'title'      => 'Atur Izin Akses',
            'arsip'      => $arsip,
            'users'      => $users,
            'access_ids' => Arsip::accessUserIds((int) $arsip['id']),
            'history'    => AuditLog::forTarget('arsip', (int) $arsip['id']),
        ]);
    }

    public function approve(string $id): void
    {
        require_role(ROLE_ADMIN);
        verify_csrf();

        $arsip = Arsip::find((int) $id);

        if (!$arsip) {
            set_flash('error', 'Arsip yang Anda maksud tidak ditemukan. Silakan periksa kembali daftar arsip.');
            redirect('admin/validasi');
        }

        // Tingkat risiko konten: hijau (aman) atau kuning (perhatian).
        $risk = (string) $this->input('risk_level', RISK_HIJAU);

        if (!array_key_exists($risk, RISK_LEVEL) || $risk === RISK_MERAH) {
            $risk = RISK_HIJAU;
        }

        $validator = auth_user();

        Arsip::setStatus((int) $arsip['id'], ARSIP_APPROVED, (int) $validator['id'], null, $risk);
        audit_log(
            'approve_arsip',
            'arsip',
            (int) $arsip['id'],
            'Menyetujui arsip: ' . $arsip['judul'] . ' | risiko: ' . (RISK_LEVEL[$risk] ?? $risk)
        );

        // Beri tahu pengunggah bahwa arsipnya disetujui.
        $uploader = User::find((int) $arsip['uploader_id']);
        $email    = (string) ($uploader['email'] ?? '');
        $terkirim = false;

        if ($email !== '') {
            $pesan = 'Arsip Anda "' . $arsip['judul'] . '" telah disetujui oleh '
                . $validator['nama'] . ' (' . role_label($validator['role']) . ') pada '
                . date('d-m-Y H:i') . '. Tingkat risiko: ' . (RISK_LEVEL[$risk] ?? $risk) . '.';

            $terkirim = send_notification(
                $email,
                'Arsip disetujui - ' . APP_NAME,
                '<p>' . nl2br(e($pesan)) . '</p>',
                'approve-arsip'
            );
        }

        audit_log(
            'notifikasi_approve_arsip',
            'arsip',
            (int) $arsip['id'],
            'Notifikasi persetujuan arsip ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                . ($email !== '' ? $email : 'pengunggah tanpa email')
        );

        set_flash(
            'success',
            'Arsip berhasil disetujui. Silakan lanjutkan dengan pengaturan izin akses.'
            . ($terkirim
                ? ' Email pemberitahuan telah dikirim ke pengunggah.'
                : ' Email pemberitahuan gagal dikirim; periksa alamat email pengunggah.')
        );
        redirect('admin/arsip/' . $arsip['id'] . '/akses');
    }

    public function reject(string $id): void
    {
        require_role(ROLE_ADMIN);
        verify_csrf();

        $arsip  = Arsip::find((int) $id);
        $alasan = (string) $this->input('alasan', '');
        $tingkat = (string) $this->input('tingkat', '');

        if (!$arsip) {
            set_flash('error', 'Arsip yang Anda maksud tidak ditemukan. Silakan periksa kembali daftar arsip.');
            redirect('admin/validasi');
        }

        if ($alasan === '') {
            set_flash('error', 'Alasan penolakan belum diisi. Mohon berikan keterangan sebelum menolak arsip.');
            redirect('admin/validasi');
        }

        $adaSanksi = array_key_exists($tingkat, PENALTY_LEVEL);
        $validator = auth_user();
        $uploader  = User::find((int) $arsip['uploader_id']);
        $email     = (string) ($uploader['email'] ?? '');

        // Arsip ditolak. Bila melanggar kode etik, tandai risiko merah.
        Arsip::setStatus(
            (int) $arsip['id'],
            ARSIP_REJECTED,
            (int) $validator['id'],
            $alasan,
            $adaSanksi ? RISK_MERAH : RISK_KUNING
        );

        $ket = 'Menolak arsip: ' . $arsip['judul'] . ' | Alasan: ' . $alasan;

        // Notifikasi penolakan dikirim ke pengunggah, termasuk saat tidak ada
        // sanksi. Sebelumnya email hanya dikirim bila arsip memicu blokir.
        $terkirim = false;

        if ($email !== '') {
            $alasanEmail = rtrim($alasan);

            if ($alasanEmail !== '' && !preg_match('/[.!?]$/', $alasanEmail)) {
                $alasanEmail .= '.';
            }

            $pesan = 'Arsip Anda "' . $arsip['judul'] . '" telah ditolak oleh '
                . $validator['nama'] . ' (' . role_label($validator['role']) . ') pada '
                . date('d-m-Y H:i') . '. Alasan: ' . $alasanEmail
                . ($adaSanksi ? ' Sanksi juga diterapkan pada akun Anda.' : '');

            $terkirim = send_notification(
                $email,
                'Arsip ditolak - ' . APP_NAME,
                '<p>' . nl2br(e($pesan)) . '</p>',
                'reject-arsip'
            );
        }

        audit_log(
            'notifikasi_reject_arsip',
            'arsip',
            (int) $arsip['id'],
            'Notifikasi penolakan arsip ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                . ($email !== '' ? $email : 'pengunggah tanpa email')
        );

        if ($adaSanksi) {
            $banId = Ban::create(
                (int) $arsip['uploader_id'],
                $tingkat,
                $alasan,
                (int) $arsip['id'],
                (int) $validator['id']
            );

            $ket .= ' | Sanksi: ' . (PENALTY_LEVEL[$tingkat] ?? $tingkat);

            // Kirim notifikasi sanksi ke pengguna yang diblokir.
            $ban = Ban::activeForUser((int) $arsip['uploader_id']);

            if ($uploader && $ban) {
                $pesanBan     = ban_message($ban) . ' Arsip yang terkait: "' . $arsip['judul'] . '".';
                $banTerkirim  = false;

                if ($email !== '') {
                    $banTerkirim = send_notification(
                        $email,
                        'Blokir sementara akun ' . APP_NAME,
                        '<p>' . nl2br(e($pesanBan)) . '</p>',
                        'blokir-akun'
                    );
                }

                audit_log(
                    'notifikasi_blokir',
                    'user_bans',
                    (int) $banId,
                    'Notifikasi blokir ' . ($banTerkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                        . ($email !== '' ? $email : 'pengguna tanpa email')
                        . ' (user_id=' . $uploader['id'] . ')'
                );
            }
        }

        audit_log('reject_arsip', 'arsip', (int) $arsip['id'], $ket);

        $statusEmail = $terkirim
            ? ' Email notifikasi telah dikirim ke pengunggah.'
            : ($email !== ''
                ? ' Email notifikasi gagal dikirim; periksa konfigurasi email.'
                : ' Pengunggah belum memiliki alamat email.');

        set_flash('success', ($adaSanksi
            ? 'Arsip telah ditolak dan sanksi telah diterapkan kepada pengunggah sesuai ketentuan yang berlaku.'
            : 'Arsip telah ditolak.') . $statusEmail);
        redirect('admin/validasi');
    }

    public function bans(): void
    {
        require_role(ROLE_ADMIN);

        $this->view('admin/bans', [
            'title'  => 'Blokir Pengguna',
            'bans'   => Ban::all(true),
        ]);
    }

    public function releaseBan(string $id): void
    {
        require_role(ROLE_ADMIN);
        verify_csrf();

        if (Ban::release((int) $id, (int) auth_user()['id'])) {
            // Ambil info ban untuk notifikasi.
            $ban = \App\Core\Database::run('SELECT * FROM user_bans WHERE id = :id', ['id' => $id])->fetch();
            $terkirim = false;
            $email    = '';

            if ($ban) {
                $uploader = User::find((int) $ban['user_id']);
                $email    = (string) ($uploader['email'] ?? '');

                if ($email !== '') {
                    $terkirim = send_notification(
                        $email,
                        'Blokir akun dibuka - ' . APP_NAME,
                        '<p>Blokir akun Anda telah dibuka oleh ' . e(APP_NAME) . '. Silakan login kembali.</p>',
                        'buka-blokir'
                    );
                }
            }

            audit_log(
                'notifikasi_buka_blokir',
                'user_bans',
                (int) $id,
                'Notifikasi pembukaan blokir ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                    . ($email !== '' ? $email : 'pengguna tanpa email')
            );

            audit_log('buka_blokir', 'user_bans', (int) $id, 'Membuka blokir pengguna');
            set_flash(
                'success',
                'Blokir akun berhasil dibuka. Pengguna telah dapat mengakses sistem kembali.'
                . ($terkirim
                    ? ' Email pemberitahuan telah dikirim.'
                    : ($email !== '' ? ' Email pemberitahuan gagal dikirim.' : ' Pengguna belum memiliki alamat email.'))
            );
        } else {
            set_flash('error', 'Data blokir tidak ditemukan atau sudah dibuka sebelumnya.');
        }

        redirect('admin/blokir');
    }

    public function updateAkses(string $id): void
    {
        require_role(ROLE_ADMIN);
        verify_csrf();

        $arsip = Arsip::find((int) $id);

        if (!$arsip) {
            set_flash('error', 'Arsip yang Anda maksud tidak ditemukan. Silakan periksa kembali daftar arsip.');
            redirect('admin/validasi');
        }

        $userIds = array_map('intval', (array) ($_POST['user_ids'] ?? []));
        $beforeIds = Arsip::accessUserIds((int) $arsip['id']);

        $beforeIds = array_values(array_unique($beforeIds));
        $userIds   = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));

        $addedIds   = array_values(array_diff($userIds, $beforeIds));
        $removedIds = array_values(array_diff($beforeIds, $userIds));

        if ($addedIds === [] && $removedIds === []) {
            set_flash('info', 'Tidak ada perubahan izin akses. Notifikasi tidak dikirim.');
            redirect('admin/arsip/' . $arsip['id'] . '/akses');
        }

        Arsip::syncAccess((int) $arsip['id'], $userIds, (int) auth_user()['id']);

        // Notifikasi dikirim kepada pemilik arsip, bukan kepada user penerima
        // akses. Pemilik adalah uploader yang bertanggung jawab atas arsip.
        $owner      = User::find((int) $arsip['uploader_id']);
        $ownerEmail = trim((string) ($owner['email'] ?? ''));
        $editor     = auth_user();
        $berhasil   = 0;
        $gagal      = 0;

        $detail = [];
        if ($addedIds !== []) {
            $detail[] = '<li><strong>' . count($addedIds) . '</strong> pengguna menerima izin akses baru.</li>';
        }
        if ($removedIds !== []) {
            $detail[] = '<li><strong>' . count($removedIds) . '</strong> pengguna tidak lagi memiliki izin akses.</li>';
        }

        if ($ownerEmail !== '') {
            $pesan = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:auto;color:#172033">'
                . '<div style="background:#12355b;color:#ffffff;padding:22px 26px">'
                . '<div style="font-size:20px;font-weight:700">' . e(APP_NAME) . '</div>'
                . '<div style="font-size:12px;margin-top:5px;opacity:.85">Pemberitahuan Administratif</div>'
                . '</div>'
                . '<div style="border:1px solid #dce3ec;border-top:0;padding:26px">'
                . '<p>Yth. ' . e((string) ($owner['nama'] ?? 'Pemilik Arsip')) . ',</p>'
                . '<p>Administrator <strong>' . e((string) ($editor['nama'] ?? 'Administrator')) . '</strong> telah memperbarui pengaturan izin akses untuk arsip berikut:</p>'
                . '<p style="background:#f3f6fa;border-left:4px solid #12355b;padding:14px 16px"><strong>' . e($arsip['judul']) . '</strong><br>'
                . '<span style="font-size:13px;color:#526174">Kode arsip: ' . e((string) ($arsip['kode'] ?? '-')) . '</span></p>'
                . '<ul>' . implode('', $detail) . '</ul>'
                . '<p>Perubahan mulai berlaku sejak ' . e(date('d-m-Y H:i')) . '. Silakan masuk ke sistem untuk meninjau pengaturan akses arsip tersebut.</p>'
                . '<p style="font-size:13px;color:#526174">Pesan ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.</p>'
                . '<p>Hormat kami,<br><strong>Administrator ' . e(APP_NAME) . '</strong></p>'
                . '</div></div>';

            $terkirim = send_notification(
                $ownerEmail,
                'Pembaruan izin akses arsip - ' . APP_NAME,
                $pesan,
                'akses-arsip-pemilik'
            );
            $terkirim ? $berhasil++ : $gagal++;
        } else {
            $gagal++;
        }

        audit_log(
            'notifikasi_akses_arsip',
            'arsip',
            (int) $arsip['id'],
            'Notifikasi pemilik arsip ' . ($berhasil ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                . ($ownerEmail !== '' ? $ownerEmail : 'pemilik tanpa email')
        );

        audit_log(
            'atur_akses',
            'arsip',
            (int) $arsip['id'],
            'Memperbarui izin akses arsip: ' . $arsip['judul'] . ' | jumlah user: ' . count(array_unique($userIds))
        );

        set_flash(
            'success',
            'Pengaturan izin akses arsip berhasil disimpan.'
             . ' Notifikasi pemilik arsip ' . ($berhasil ? 'berhasil dikirim.' : 'gagal dikirim.')
        );
        redirect('admin/arsip/' . $arsip['id'] . '/akses');
    }

    public function identitas(): void
    {
        require_role(ROLE_ADMIN, ROLE_PETUGAS);

        $status = (string) $this->input('status', IDENTITY_MENUNGGU);

        if (!in_array($status, IDENTITY_STATUSES, true) && $status !== 'semua') {
            $status = IDENTITY_MENUNGGU;
        }

        $this->view('admin/identitas', [
            'title'  => 'Validasi Identitas',
            'users'  => User::allWithIdentity($status === 'semua' ? null : $status),
            'counts' => User::identityCounts(),
            'status' => $status,
        ]);
    }

    public function approveIdentity(string $id): void
    {
        require_role(ROLE_ADMIN, ROLE_PETUGAS);
        verify_csrf();

        $user = User::find((int) $id);

        if (!$user) {
            set_flash('error', 'Data pengguna tidak ditemukan. Silakan periksa kembali daftar pengguna.');
            redirect('admin/identitas');
        }

        User::approveIdentity((int) $user['id'], (int) auth_user()['id']);
        audit_log('verifikasi_identitas', 'users', (int) $user['id'], 'Menyetujui verifikasi identitas: ' . $user['nama']);

        $email    = (string) ($user['email'] ?? '');
        $terkirim = false;

        if ($email !== '') {
            $terkirim = send_notification(
                $email,
                'Identitas terverifikasi - ' . APP_NAME,
                '<p>Biodata/identitas Anda telah <b>disetujui</b> oleh ' . e(APP_NAME) . '. Akun Anda kini terverifikasi.</p>',
                'verifikasi-identitas'
            );
        }

        audit_log(
            'notifikasi_verifikasi_identitas',
            'users',
            (int) $user['id'],
            'Notifikasi verifikasi identitas ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                . ($email !== '' ? $email : 'pengguna tanpa email')
        );

        set_flash(
            'success',
            'Identitas pengguna ' . $user['nama'] . ' berhasil diverifikasi.'
            . ($terkirim
                ? ' Email pemberitahuan telah dikirim.'
                : ($email !== '' ? ' Email pemberitahuan gagal dikirim.' : ' Pengguna belum memiliki alamat email.'))
        );
        redirect('admin/identitas');
    }

    public function rejectIdentity(string $id): void
    {
        require_role(ROLE_ADMIN, ROLE_PETUGAS);
        verify_csrf();

        $user = User::find((int) $id);

        if (!$user) {
            set_flash('error', 'Data pengguna tidak ditemukan. Silakan periksa kembali daftar pengguna.');
            redirect('admin/identitas');
        }

        $alasan = (string) $this->input('alasan', '');

        if ($alasan === '') {
            $alasan = 'Biodata belum sesuai dengan ketentuan. Silakan lengkapi dan ajukan kembali.';
        }

        User::rejectIdentity((int) $user['id'], (int) auth_user()['id'], $alasan);
        audit_log('tolak_identitas', 'users', (int) $user['id'], 'Menolak verifikasi identitas: ' . $user['nama'] . ' | alasan: ' . $alasan);

        $email    = (string) ($user['email'] ?? '');
        $terkirim = false;

        if ($email !== '') {
            $terkirim = send_notification(
                $email,
                'Verifikasi identitas ditolak - ' . APP_NAME,
                '<p>Verifikasi identitas Anda <b>ditolak</b>.</p><p>Alasan: ' . e($alasan) . '</p><p>Silakan perbaiki biodata dan ajukan kembali.</p>',
                'tolak-identitas'
            );
        }

        audit_log(
            'notifikasi_tolak_identitas',
            'users',
            (int) $user['id'],
            'Notifikasi penolakan identitas ' . ($terkirim ? 'berhasil dikirim ke ' : 'gagal dikirim ke ')
                . ($email !== '' ? $email : 'pengguna tanpa email')
        );

        set_flash(
            'success',
            'Pengajuan verifikasi identitas ' . $user['nama'] . ' telah ditolak.'
            . ($terkirim
                ? ' Email pemberitahuan telah dikirim.'
                : ($email !== '' ? ' Email pemberitahuan gagal dikirim.' : ' Pengguna belum memiliki alamat email.'))
        );
        redirect('admin/identitas');
    }
}
