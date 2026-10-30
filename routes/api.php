<?php

use App\Api\V1\Controllers\Auth\LoginController;
use App\Api\V1\Controllers\Auth\LogoutController;
use App\Api\V1\Controllers\Auth\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', LoginController::class)->middleware('throttle:10,1')->name('api.v1.auth.login');

        Route::middleware(['auth:sanctum', 'is_student_api'])->group(function () {
            Route::post('logout', LogoutController::class)->name('api.v1.auth.logout');
            Route::get('me', MeController::class)->name('api.v1.auth.me');
        });
    });
});
