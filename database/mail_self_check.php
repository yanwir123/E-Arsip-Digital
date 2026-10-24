<?php

declare(strict_types=1);

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', '587');
define('SMTP_USER', 'test@example.com');
define('SMTP_PASS', 'test');
define('SMTP_FROM_NAME', 'E-Arsip');
define('MAIL_DRIVER', 'smtp');

require dirname(__DIR__) . '/app/core/Mailer.php';

$mailer = new ReflectionClass(App\Core\Mailer::class);

assert(App\Core\Mailer::send('', 'Subjek', '<p>Pesan</p>') === false);
assert(App\Core\Mailer::send('bukan-email', 'Subjek', '<p>Pesan</p>') === false);
assert(App\Core\Mailer::send('user@example.com', '', '<p>Pesan</p>') === false);

assert(App\Core\Mailer::send('user@example.com', "Subjek\r\nBcc: korban@example.com", '<p>Pesan</p>') === false);

echo "Pemeriksaan validasi email lulus.\n";
