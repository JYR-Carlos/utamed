<?php

use App\Api\V1\Controllers\Auth\ChangeExpiredPasswordController;
use App\Api\V1\Controllers\Auth\LoginController;
use App\Api\V1\Controllers\Auth\LogoutController;
use App\Api\V1\Controllers\Auth\MeController;
use App\Api\V1\Controllers\Student\Calendar\GetAcademicCalendarController;
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
use App\Api\V1\Controllers\Student\Mensajero\GetCourseMessagingController;
use App\Api\V1\Controllers\Student\Mensajero\StoreCourseMessageController;
use App\Api\V1\Controllers\Student\Profile\GetProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Autenticación
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', LoginController::class)
            ->middleware('throttle:10,1')
            ->name('login');

        Route::middleware(['auth:sanctum', 'is_student_api'])->group(function () {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::get('me', MeController::class)->name('me');

            Route::post('cambio-password-obligatorio', ChangeExpiredPasswordController::class)
                ->middleware('throttle:10,1')
                ->name('cambio-password-obligatorio');
        });
    });

    // Portal del estudiante
    Route::prefix('estudiante')
        ->name('estudiante.')
        ->middleware(['auth:sanctum', 'is_student_api', 'force_password_change_api'])
        ->group(function () {

            // Perfil y dashboard
            Route::get('perfil', GetProfileController::class)->name('perfil');
            Route::get('dashboard', GetDashboardController::class)->name('dashboard');
            Route::get('calendario', GetAcademicCalendarController::class)->name('calendario');

            // Cursos
            Route::prefix('cursos')->name('cursos.')->group(function () {
                Route::get('/', GetCoursesController::class)->name('index');

                Route::middleware('is_enrolled_in_course')->group(function () {
                    Route::get('{curso}', ShowCourseController::class)->name('show');
                    Route::get('{curso}/rendimiento', ShowCoursePerformanceController::class)
                        ->name('rendimiento');

                    // Actividades
                    Route::prefix('{curso}/actividades')->name('actividades.')->group(function () {
                        Route::get('/', GetCourseActivitiesController::class)->name('index');
                        Route::get('{actividad}', ShowActivityController::class)->name('show');
                        Route::get('{actividad}/enunciado', DownloadActivityStatementController::class)
                            ->name('enunciado');
                        Route::get('{actividad}/evaluacion', ShowActivityEvaluationController::class)
                            ->name('evaluacion');
                        Route::get('{actividad}/entregas/{agenda}/descargar', DownloadActivitySubmissionController::class)
                            ->name('entregas.descargar');

                        // Agenda y entregas
                        Route::prefix('{actividad}')->group(function () {
                            Route::get('agenda', GetActivityAgendaController::class)
                                ->name('agenda.show');
                            Route::post('agenda', StoreActivityMessageController::class)
                                ->name('agenda.store');
                            Route::post('agenda/leida', MarkActivityAgendaReadController::class)
                                ->name('agenda.leida');

                            Route::post('entregas', StoreActivitySubmissionController::class)
                                ->name('entregas.store');
                            Route::delete('entregas/{agenda}', CancelActivitySubmissionController::class)
                                ->name('entregas.cancel');
                        });
                    });

                    // Mensajero
                    Route::get('{curso}/mensajero', GetCourseMessagingController::class)
                        ->name('mensajero.show');
                    Route::post('{curso}/mensajero/componentes/{componente}/mensaje', StoreCourseMessageController::class)
                        ->name('mensajero.componentes.mensaje.store');
                });
            });
        });
});
