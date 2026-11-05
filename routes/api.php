<?php

use App\Api\V1\Controllers\Auth\ChangeExpiredPasswordController;
use App\Api\V1\Controllers\Auth\LoginController;
use App\Api\V1\Controllers\Auth\LogoutController;
use App\Api\V1\Controllers\Auth\MeController;
use App\Api\V1\Controllers\Student\Activity\DownloadActivityStatementController;
use App\Api\V1\Controllers\Student\Activity\DownloadActivitySubmissionController;
use App\Api\V1\Controllers\Student\Activity\GetCourseActivitiesController;
use App\Api\V1\Controllers\Student\Activity\ShowActivityController;
use App\Api\V1\Controllers\Student\Activity\ShowActivityEvaluationController;
use App\Api\V1\Controllers\Student\Agenda\CancelActivitySubmissionController;
use App\Api\V1\Controllers\Student\Agenda\GetActivityAgendaController;
use App\Api\V1\Controllers\Student\Agenda\MarkActivityAgendaReadController;
use App\Api\V1\Controllers\Student\Agenda\StoreActivityMessageController;
use App\Api\V1\Controllers\Student\Agenda\StoreActivitySubmissionController;
use App\Api\V1\Controllers\Student\Course\GetCoursesController;
use App\Api\V1\Controllers\Student\Course\ShowCourseController;
use App\Api\V1\Controllers\Student\Course\ShowCoursePerformanceController;
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
        Route::get('cursos/{curso}', ShowCourseController::class)
            ->middleware('is_enrolled_in_course')
            ->name('api.v1.estudiante.cursos.show');
        // Rendimiento académico individual y asistencia acumulada por curso (APP-07)
        Route::get('cursos/{curso}/rendimiento', ShowCoursePerformanceController::class)
            ->middleware('is_enrolled_in_course')
            ->name('api.v1.estudiante.cursos.rendimiento');

        // Actividades del curso, detalle con rúbrica y descarga de enunciado (APP-10)
        Route::get('cursos/{curso}/actividades', GetCourseActivitiesController::class)
            ->middleware('is_enrolled_in_course')
            ->name('api.v1.estudiante.cursos.actividades.index');
        Route::get('cursos/{curso}/actividades/{actividad}', ShowActivityController::class)
            ->middleware('is_enrolled_in_course')
            ->name('api.v1.estudiante.cursos.actividades.show');
        Route::get('cursos/{curso}/actividades/{actividad}/enunciado', DownloadActivityStatementController::class)
            ->middleware('is_enrolled_in_course')
            ->name('api.v1.estudiante.cursos.actividades.enunciado');
        Route::get('cursos/{curso}/actividades/{actividad}/evaluacion', ShowActivityEvaluationController::class)
            ->middleware('is_enrolled_in_course')
            ->name('api.v1.estudiante.cursos.actividades.evaluacion');
        Route::get('cursos/{curso}/actividades/{actividad}/entregas/{agenda}/descargar', DownloadActivitySubmissionController::class)
            ->middleware('is_enrolled_in_course')
            ->name('api.v1.estudiante.cursos.actividades.entregas.descargar');

        // Agenda de actividad, interacciones y entregas de tareas (APP-12)
        Route::prefix('cursos/{curso}/actividades/{actividad}')->middleware('is_enrolled_in_course')->group(function () {
            Route::get('agenda', GetActivityAgendaController::class)->name('api.v1.estudiante.cursos.actividades.agenda.show');
            Route::post('agenda', StoreActivityMessageController::class)->name('api.v1.estudiante.cursos.actividades.agenda.store');
            Route::post('agenda/leida', MarkActivityAgendaReadController::class)->name('api.v1.estudiante.cursos.actividades.agenda.leida');
            Route::post('entregas', StoreActivitySubmissionController::class)->name('api.v1.estudiante.cursos.actividades.entregas.store');
            Route::delete('entregas/{agenda}', CancelActivitySubmissionController::class)->name('api.v1.estudiante.cursos.actividades.entregas.cancel');
        });
    });
});
