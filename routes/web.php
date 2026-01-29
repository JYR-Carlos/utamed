<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\Admin\FacultadController;
use App\Http\Controllers\Admin\DepartamentoController;
use App\Http\Controllers\Admin\CarreraController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\AsignaturaController;
use App\Http\Controllers\Admin\AsignacionPlanController;
use App\Http\Controllers\Admin\CursoController;
use App\Http\Controllers\Admin\CourseTeamController;
use App\Http\Controllers\Admin\UsuarioController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard', [
        'stats' => [
            'usuarios' => \App\Models\Usuario\Usuario::count(),
            'cursos' => \App\Models\Curso\Curso::count(),
            'facultades' => \App\Models\Administrativo\Facultad::count(),
            'carreras' => \App\Models\Administrativo\Carrera::count(),
        ]
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin Routes
Route::prefix('admin')->middleware(['auth', 'verified'])->name('admin.')->group(function () {
    // Resource routes
    Route::resource('facultades', FacultadController::class);
    Route::resource('departamentos', DepartamentoController::class);
    Route::resource('carreras', CarreraController::class);
    Route::resource('planes', PlanController::class);
    Route::resource('asignaturas', AsignaturaController::class);
    Route::resource('cursos', CursoController::class);
    Route::resource('usuarios', UsuarioController::class);

    // Additional usuario routes
    Route::post('usuarios/{usuario}/change-password', [UsuarioController::class, 'changePassword'])
        ->name('usuarios.change-password');
    Route::post('usuarios/{usuario}/toggle-active', [UsuarioController::class, 'toggleActive'])
        ->name('usuarios.toggle-active');

    // Permissions Routes
    Route::get('usuarios/{usuario}/permissions', [UsuarioController::class, 'getUserPermissions'])
        ->name('usuarios.permissions');
    Route::post('usuarios/{usuario}/sync-permissions', [UsuarioController::class, 'syncPermissions'])
        ->name('usuarios.syncPermissions');

    // Detalle Malla (AsignacionPlan) routes
    Route::get('planes/{plan}/asignaturas', [AsignacionPlanController::class, 'index'])
        ->name('planes.asignaturas.index');
    Route::post('planes/{plan}/asignaturas', [AsignacionPlanController::class, 'store'])
        ->name('planes.asignaturas.store');
    Route::put('planes/{plan}/asignaturas/{asignatura}', [AsignacionPlanController::class, 'update'])
        ->name('planes.asignaturas.update');
    Route::delete('planes/{plan}/asignaturas/{asignatura}', [AsignacionPlanController::class, 'destroy'])
        ->name('planes.asignaturas.destroy');

    // Assign Docente to Curso
    Route::post('cursos/{curso}/docente', [CursoController::class, 'assignDocente'])
        ->name('cursos.docente.assign');
    Route::delete('cursos/{curso}/docente', [CursoController::class, 'unassignDocente'])
        ->name('cursos.docente.unassign');

    // Course Team Management
    Route::get('cursos/{curso}/team', [CourseTeamController::class, 'index'])->name('cursos.team.index');
    Route::post('cursos/{curso}/team', [CourseTeamController::class, 'store'])->name('cursos.team.store');
    Route::delete('cursos/{curso}/team/{usuario}', [CourseTeamController::class, 'destroy'])->name('cursos.team.destroy');

    // Helper endpoints for cascading selects
    Route::get('facultades/{facultad}/departamentos', [DepartamentoController::class, 'byFacultad'])
        ->name('facultades.departamentos');
    Route::get('departamentos/{departamento}/carreras', [CarreraController::class, 'byDepartamento'])
        ->name('departamentos.carreras');
    Route::get('carreras/{carrera}/planes', [PlanController::class, 'byCarrera'])
        ->name('carreras.planes');
});

require __DIR__ . '/settings.php';

