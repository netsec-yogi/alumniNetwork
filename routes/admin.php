<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use Illuminate\Support\Facades\Route;

/*
 | Prefixed /admin, named admin.*, and behind auth + verified email +
 | the access-admin gate (bootstrap/app.php). Mandatory 2FA is enforced by
 | EnsureTwoFactorEnrolled before any of these run. Each controller action
 | still authorises itself against its policy.
 */

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::post('/users', [UserController::class, 'store'])->middleware('password.confirm')->name('users.store');
Route::put('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
Route::post('/users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->middleware('password.confirm')->name('users.reset-two-factor');
// SRS 77: privilege changes need a recent password confirmation.
Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('password.confirm')->name('users.roles');

Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
Route::post('/verification/{verificationRequest}/approve', [VerificationController::class, 'approve'])->name('verification.approve');
Route::post('/verification/{verificationRequest}/reject', [VerificationController::class, 'reject'])->name('verification.reject');

Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
