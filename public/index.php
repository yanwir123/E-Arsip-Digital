<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\ArsipController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Core\Router;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

$router = new Router();

// ---------------------------------------------------------------------------
// Publik / Auth
// ---------------------------------------------------------------------------
$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Registrasi + verifikasi OTP
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/verify-otp', [AuthController::class, 'showVerifyOtp']);
$router->post('/verify-otp', [AuthController::class, 'verifyOtp']);
$router->post('/verify-otp/resend', [AuthController::class, 'resendOtp']);
$router->get('/verify-otp/token/{token}', [AuthController::class, 'verifyOtpToken']);

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/profil', [ProfileController::class, 'index']);
$router->post('/profil', [ProfileController::class, 'update']);
$router->post('/profil/password', [ProfileController::class, 'updatePassword']);
$router->post('/profil/submit', [ProfileController::class, 'submit']);
$router->get('/foto/{id}', [ProfileController::class, 'photo']);

// ---------------------------------------------------------------------------
// Arsip (semua role yang login)
// ---------------------------------------------------------------------------
$router->get('/arsip', [ArsipController::class, 'index']);
$router->get('/arsip/create', [ArsipController::class, 'create']);
$router->post('/arsip', [ArsipController::class, 'store']);
$router->get('/arsip/{id}/edit', [ArsipController::class, 'edit']);
$router->post('/arsip/{id}', [ArsipController::class, 'update']);
$router->get('/arsip/{id}/preview', [ArsipController::class, 'preview']);
$router->get('/arsip/{id}/download', [ArsipController::class, 'download']);
$router->get('/arsip/{id}', [ArsipController::class, 'show']);
$router->post('/arsip/{id}/delete', [ArsipController::class, 'destroy']);

// ---------------------------------------------------------------------------
// Panel Admin (validasi arsip & pemberian izin akses)
// ---------------------------------------------------------------------------
$router->get('/admin/validasi', [AdminController::class, 'validasi']);
$router->get('/admin/identitas', [AdminController::class, 'identitas']);
$router->post('/admin/identitas/{id}/approve', [AdminController::class, 'approveIdentity']);
$router->post('/admin/identitas/{id}/reject', [AdminController::class, 'rejectIdentity']);
$router->get('/admin/blokir', [AdminController::class, 'bans']);
$router->post('/admin/blokir/{id}/buka', [AdminController::class, 'releaseBan']);
$router->get('/admin/arsip/{id}/akses', [AdminController::class, 'akses']);
$router->post('/admin/arsip/{id}/approve', [AdminController::class, 'approve']);
$router->post('/admin/arsip/{id}/reject', [AdminController::class, 'reject']);
$router->post('/admin/arsip/{id}/akses', [AdminController::class, 'updateAkses']);

// ---------------------------------------------------------------------------
// Manajemen pengguna (khusus developer)
// ---------------------------------------------------------------------------
$router->get('/users', [UserController::class, 'index']);
$router->get('/users/create', [UserController::class, 'create']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}/edit', [UserController::class, 'edit']);
$router->post('/users/{id}', [UserController::class, 'update']);
$router->post('/users/{id}/delete', [UserController::class, 'destroy']);

// ---------------------------------------------------------------------------
// Audit log (admin & developer)
// ---------------------------------------------------------------------------
$router->get('/audit', [AuditController::class, 'index']);

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
    $uri = substr($uri, strlen($scriptDir));
}

$router->dispatch($method, $uri);
