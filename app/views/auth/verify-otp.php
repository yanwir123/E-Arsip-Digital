<?php
$email = (string) $email;
if ($email === '') {
    $email = (string) old('email', (string) ($_POST['email'] ?? ''));
}
?>
<div class="card login-card">
    <h1>Verifikasi Email</h1>
    <p class="muted">
        Masukkan kode 6 digit yang dikirim ke
        <strong><?= e($email) ?></strong>.
        Anda juga bisa membuka tautan "Verifikasi Akun" di email tersebut.
    </p>

    <form method="post" action="<?= e(url('verify-otp')) ?>">
        <?= csrf_field() ?>

        <?php if ($email !== ''): ?>
            <input type="hidden" name="email" value="<?= e($email) ?>">
        <?php else: ?>
            <label>Email
                <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
            </label>
        <?php endif; ?>

        <label>Kode OTP
            <input type="text" name="kode" inputmode="numeric" pattern="[0-9]{6}"
                   maxlength="6" placeholder="123456" required autocomplete="one-time-code">
        </label>

        <button type="submit" class="btn btn-primary btn-block">Verifikasi</button>
    </form>
</div>

<?php if ($email !== ''): ?>
<div class="card login-card">
    <p class="muted">Tidak menerima email? Periksa folder Spam, atau kirim ulang.</p>
    <form method="post" action="<?= e(url('verify-otp/resend')) ?>" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <button type="submit" class="btn">Kirim Ulang Kode</button>
    </form>
</div>
<?php endif; ?>
<?php clear_old(); ?>
