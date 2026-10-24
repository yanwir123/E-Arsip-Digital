<?php
/**
 * Notifikasi flash terstruktur: judul singkat + pesan yang mudah dibaca.
 * Ikon dibuat inline agar tetap sesuai kebijakan CSP (script/style eksternal).
 *
 * @var array<int, array{type:string, title:string, message:string}> $flash
 */

$flashIcons = [
    'success' => '<path d="M20 6 9 17l-5-5"/>',
    'error'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
    'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
];
?>
<?php foreach (get_flash() as $f): ?>
    <?php $type = isset($flashIcons[$f['type']]) ? $f['type'] : 'info'; ?>
    <div class="alert alert-<?= e($type) ?>" role="alert">
        <span class="alert-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><?= $flashIcons[$type] ?></svg>
        </span>
        <div class="alert-content">
            <strong class="alert-title"><?= e($f['title']) ?></strong>
            <p class="alert-message"><?= e($f['message']) ?></p>
        </div>
    </div>
<?php endforeach; ?>
