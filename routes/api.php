<?php

use App\Api\V1\Controllers\Auth\ChangeExpiredPasswordController;
use App\Api\V1\Controllers\Auth\LoginController;
use App\Api\V1\Controllers\Auth\LogoutController;
use App\Api\V1\Controllers\Auth\MeController;
use App\Api\V1\Controllers\Student\Course\GetCoursesController;
use App\Api\V1\Controllers\Student\Course\ShowCourseController;
use App\Api\V1\Controllers\Student\Dashboard\GetDashboardController;
use App\Api\V1\Controllers\Student\Profile\GetProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Autenticación móvil y ciclo de vida de tokens Bearer
    Route::prefix('auth')->group(function () {
        // Iniciar sesión con RUT y contraseña; emite Bearer token
        Route::post('login', LoginController::class)->middleware('throttle:10,1')->name('api.v1.auth.login');

        // RUTAS DESPROTEGIDAS, sólo necesita ser estudiante para continuar...
        Route::middleware(['auth:sanctum', 'is_student_api'])->group(function () {
            // Revocar token Bearer de la sesión actual
            Route::post('logout', LogoutController::class)->name('api.v1.auth.logout');
            // Identidad y verificación de expiración de clave del usuario actual
            Route::get('me', MeController::class)->name('api.v1.auth.me');
            // Cambio de clave solo si venció o es primer ingreso (sin cambio voluntario en la app)
            Route::post('cambio-password-obligatorio', ChangeExpiredPasswordController::class)
                ->middleware('throttle:10,1')
                ->name('api.v1.auth.cambio-password-obligatorio');
        });
    });

    // Portal móvil del estudiante (acceso exclusivo a alumnos activos con clave vigente)
    Route::middleware(['auth:sanctum', 'is_student_api', 'force_password_change_api'])->prefix('estudiante')->group(function () {
        // Ficha oficial de perfil, cohorte, matrícula y foto de credencial Intranet (solo lectura)
        Route::get('perfil', GetProfileController::class)->name('api.v1.estudiante.perfil');
        // Inicio: cursos del período, entregas próximas, notas recientes y no leídos del Mensajero
        Route::get('dashboard', GetDashboardController::class)->name('api.v1.estudiante.dashboard');
        // Catálogo de asignaturas inscritas del período actual (u opcionalmente solicitado)
        Route::get('cursos', GetCoursesController::class)->name('api.v1.estudiante.cursos');
        // Ficha detallada del curso, asignatura, componentes asignados y unidades
        Route::get('cursos/{curso}', ShowCourseController::class)->name('api.v1.estudiante.cursos.show');
    });
});
