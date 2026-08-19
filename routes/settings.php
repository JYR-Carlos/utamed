<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
// use App\Http\Controllers\Settings\TwoFactorAuthenticationController; // 2FA retirado (ver más abajo)
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    // 2FA retirado junto con Features::twoFactorAuthentication() en config/fortify.php.
    // Esta ruta la registra la aplicación (no Fortify), así que no desaparece sola:
    // sin el trait TwoFactorAuthenticatable en Usuario, show() reventaba al llamar a
    // hasEnabledTwoFactorAuthentication(). Descomentar al reimplementar la feature.
    // Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
    //     ->name('two-factor.show');
});
