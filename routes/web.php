<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SessionController;
use App\Models\AlumniProfile;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'alumniCount' => AlumniProfile::verified()->count(),
]))->name('home');

/*
 | Authentication routes (login, registration, password reset, email
 | verification, 2FA) are registered by Fortify; see config/fortify.php.
 */

Route::middleware('auth')->group(function () {
    // Security settings stay reachable before email verification, so a user
    // can always secure their account.
    Route::get('/profile/security', [SecurityController::class, 'show'])->name('profile.security');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/account', [ProfileController::class, 'updateAccount'])
            ->middleware('throttle:sensitive')->name('profile.account.update');

        Route::get('/profile/sessions', [SessionController::class, 'index'])->name('profile.sessions');
        Route::delete('/profile/sessions/others', [SessionController::class, 'destroyOthers'])
            ->middleware('throttle:sensitive')->name('profile.sessions.destroy-others');
        Route::delete('/profile/sessions/{key}', [SessionController::class, 'destroy'])
            ->where('key', '[a-f0-9]{64}')->middleware('throttle:sensitive')->name('profile.sessions.destroy');

        Route::middleware('throttle:search')->group(function () {
            Route::get('/directory', [DirectoryController::class, 'index'])->name('directory');
            Route::get('/alumni/{profile}', [DirectoryController::class, 'show'])->name('alumni.show');
        });
    });
});

/*
 | Fortify can re-display recovery codes at any time. The SRS requires them
 | to be shown once, at generation (see SecurityController), so the read
 | endpoint is shadowed. Regenerating new codes remains available.
 */
Route::get('/user/two-factor-recovery-codes', fn () => abort(404))->middleware('auth');
