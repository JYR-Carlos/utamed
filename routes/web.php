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
use App\Http\Controllers\Admin\SeccionController as AdminSeccionController;
use App\Http\Controllers\Admin\ProgramaController as AdminProgramaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\AssignmentWizardController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    /** @var \App\Models\Usuario\Usuario $user */
    $user = \Illuminate\Support\Facades\Auth::user();

    // Redirigir docentes a su dashboard
    if ($user->hasRole('Docente')) {
        return redirect()->route('docente.dashboard');
    }

    // Redirigir estudiantes a su dashboard (Prioridad Estudiante)
    if ($user->hasRole('Estudiante')) {
        return redirect()->route('estudiante.dashboard');
    }

    // Redirigir ayudantes a su dashboard
    if ($user->hasRole('Ayudante')) {
        return redirect()->route('ayudante.dashboard');
    }

    return Inertia::render('Dashboard', [
        'stats' => [
            'usuarios' => \App\Models\Usuario\Usuario::count(),
            'cursos_total' => \App\Models\Curso\Curso::count(),
            'cursos_pendientes' => \App\Models\Curso\Curso::where('estado_interno', 'ABIERTO')
                ->where(function ($query) {
                    $query->where('estado_acta', '!=', 'ENVIADO')
                        ->orWhereNull('estado_acta');
                })
                ->count(),
            'facultades' => \App\Models\Administrativo\Facultad::count(),
            'carreras' => \App\Models\Administrativo\Carrera::count(),
        ],
    ]);

})->middleware(['auth', 'verified'])->name('dashboard');

// Admin Routes
Route::prefix('admin')->middleware(['auth', 'verified', 'is_admin'])->name('admin.')->group(function () {
    // Programa (Syllabus) routes MUST come before cursos resource to avoid route collision
    Route::get('programas', [AdminProgramaController::class, 'index'])
        ->name('programas.index');
    Route::get('syllabus', [AdminProgramaController::class, 'syllabusIndex'])
        ->name('syllabus.index');
    Route::get('cursos/{curso}/programas', [AdminProgramaController::class, 'indexByCurso'])
        ->name('cursos.programas.index');
    Route::get('cursos/{curso}/programa/json', [AdminProgramaController::class, 'getJson'])
        ->name('cursos.programa.json');
    Route::get('cursos/{curso}/programa', [AdminProgramaController::class, 'show'])
        ->name('cursos.programa.show');
    Route::get('cursos/{curso}/programa/revisar', [AdminProgramaController::class, 'show'])
        ->name('cursos.programa.revisar');
    Route::post('cursos/{curso}/programa', [AdminProgramaController::class, 'store'])
        ->name('cursos.programa.store');
    Route::put('cursos/{curso}/programa/aprobar', [AdminProgramaController::class, 'approve'])
        ->name('cursos.programa.aprobar');
    Route::put('cursos/{curso}/programa/rechazar', [AdminProgramaController::class, 'reject'])
        ->name('cursos.programa.rechazar');
    Route::put('cursos/{curso}/programa/fechas', [AdminProgramaController::class, 'updateDeadlines'])
        ->name('cursos.programa.fechas');
    Route::post('cursos/{curso}/programa/instanciar', [AdminProgramaController::class, 'instantiar'])
        ->name('cursos.programa.instanciar');

    // Secciones routes - Get componentes from sections
    Route::get('cursos/{curso}/secciones', [AdminSeccionController::class, 'indexByCurso'])
        ->name('cursos.secciones.index');
    
    // Actividades routes - Get activities for program wizard
    Route::get('cursos/{curso}/actividades/json', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'getBysCursoJson'])
        ->name('cursos.actividades.json');

    // Resource routes (more general routes go after specific ones)
    Route::resource('facultades', FacultadController::class);
    Route::resource('departamentos', DepartamentoController::class);
    Route::resource('carreras', CarreraController::class);
    Route::resource('planes', PlanController::class);
    Route::resource('asignaturas', AsignaturaController::class);
    Route::resource('cursos', CursoController::class);
    Route::resource('usuarios', UsuarioController::class);

    // Curso routes
    Route::get('cursos/{plan}/asignaturas-disponibles', [CursoController::class, 'getAsignaturasByPlan'])
        ->name('cursos.asignaturas-disponibles');
    Route::get('asignaturas/{asignatura}/docentes-sugeridos', [CursoController::class, 'getDocentesSugeridos'])
        ->name('asignaturas.docentes-sugeridos');

    // Additional usuario routes
    Route::post('usuarios/{usuario}/change-password', [UsuarioController::class, 'changePassword'])
        ->name('usuarios.change-password');
    Route::post('usuarios/{usuario}/toggle-active', [UsuarioController::class, 'toggleActive'])
        ->name('usuarios.toggle-active');

    // Permissions Routes (legacy)
    Route::get('usuarios/{usuario}/permissions', [UsuarioController::class, 'getUserPermissions'])
        ->name('usuarios.permissions');
    Route::post('usuarios/{usuario}/sync-permissions', [UsuarioController::class, 'syncPermissions'])
        ->name('usuarios.syncPermissions');

    // Assignment Wizard API
    Route::get('assignment/context-types', [AssignmentWizardController::class, 'getContextTypes'])
        ->name('assignment.context-types');
    Route::get('assignment/context-types/{type}/objects', [AssignmentWizardController::class, 'getContextObjects'])
        ->name('assignment.context-objects');
    Route::get('assignment/roles', [AssignmentWizardController::class, 'getRoles'])
        ->name('assignment.roles');
    Route::get('assignment/roles/{roleId}/detail', [AssignmentWizardController::class, 'getRoleDetail'])
        ->name('assignment.roles.detail');
    Route::get('assignment/permissions', [AssignmentWizardController::class, 'getPermissions'])
        ->name('assignment.permissions');
    Route::post('usuarios/{usuario}/assign-role', [AssignmentWizardController::class, 'assignRole'])
        ->name('usuarios.assign-role');
    Route::post('usuarios/{usuario}/assign-permission', [AssignmentWizardController::class, 'assignPermission'])
        ->name('usuarios.assign-permission');

    // Detalle Malla (AsignacionPlan) routes
    Route::get('planes/{plan}/asignaturas/json', [AsignacionPlanController::class, 'mallaJson'])
        ->name('planes.asignaturas.json');
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

    // Search assistants (must be before {usuario} routes)
    Route::get('cursos/{curso}/team/search-assistants', [CourseTeamController::class, 'searchAssistants'])
        ->name('cursos.team.search-assistants');

    Route::post('cursos/{curso}/team', [CourseTeamController::class, 'store'])->name('cursos.team.store');
    Route::delete('cursos/{curso}/team/{usuario}', [CourseTeamController::class, 'destroy'])->name('cursos.team.destroy');

    // Permissions management for team members
    Route::get('cursos/{curso}/team/{usuario}/permissions', [CourseTeamController::class, 'getMemberPermissions'])
        ->name('cursos.team.permissions');
    Route::post('cursos/{curso}/team/{usuario}/sync-permissions', [CourseTeamController::class, 'syncMemberPermissions'])
        ->name('cursos.team.sync-permissions');

    // Student Course Enrollments (Inscripciones de Estudiantes en Cursos)
    Route::post('inscripciones_cursos/bulk', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'storeBulk'])
        ->name('inscripciones_cursos.bulk');
    Route::patch('inscripciones_cursos/{inscripcionCurso}/estado', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'updateEstado'])
        ->name('inscripciones_cursos.estado');
    Route::resource('inscripciones_cursos', \App\Http\Controllers\Admin\InscripcionCursoController::class);
    Route::get('inscripciones_cursos/ajax/disponibles', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'getEstudiantesDisponibles'])
        ->name('inscripciones_cursos.disponibles');
    Route::get('inscripciones_cursos/ajax/by-curso', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'getByCurso'])
        ->name('inscripciones_cursos.by-curso');
    Route::get('inscripciones_cursos/export/csv', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'exportCsv'])
        ->name('inscripciones_cursos.export.csv');

    // Section (Seccion) Management for Courses
    Route::post('cursos/{curso}/secciones', [AdminSeccionController::class, 'store'])
        ->name('cursos.secciones.store');
    Route::put('cursos/secciones/{seccion}', [AdminSeccionController::class, 'update'])
        ->name('cursos.secciones.update');
    Route::delete('cursos/secciones/{seccion}', [AdminSeccionController::class, 'destroy'])
        ->name('cursos.secciones.destroy');

    // Helper endpoints for cascading selects
    Route::get('facultades/{facultad}/departamentos', [DepartamentoController::class, 'byFacultad'])
        ->name('facultades.departamentos');
    Route::get('departamentos/{departamento}/carreras', [CarreraController::class, 'byDepartamento'])
        ->name('departamentos.carreras');
    Route::get('carreras/{carrera}/planes', [PlanController::class, 'byCarrera'])
        ->name('carreras.planes');
    Route::get('planes/{plan}/asignaturas-disponibles', [CursoController::class, 'getAsignaturasByPlan'])
        ->name('planes.asignaturas-disponibles');
});

// Docente Routes
Route::prefix('docente')->middleware(['auth', 'verified', 'is_docente'])->name('docente.')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Docente\DashboardController::class, 'index'])->name('dashboard');
    Route::get('cursos', [\App\Http\Controllers\Docente\DocenteCursoController::class, 'index'])->name('cursos.index');

    // Team management (reuse admin course team endpoints but under docente prefix if needed, 
    // or just point to Admin controller if middleware allows or it's context-safe).
    // Let's create specific routes for clarity and potential    // Course Team Management
    Route::get('cursos/{curso}/team', [CourseTeamController::class, 'index'])->name('cursos.team.index');

    // Search assistants (must be before {usuario} routes)
    Route::get('cursos/{curso}/team/search-assistants', [CourseTeamController::class, 'searchAssistants'])
        ->name('cursos.team.search-assistants');

    Route::post('cursos/{curso}/team', [CourseTeamController::class, 'store'])->name('cursos.team.store');
    Route::delete('cursos/{curso}/team/{usuario}', [CourseTeamController::class, 'destroy'])->name('cursos.team.destroy');

    // Permissions management for team members
    Route::get('cursos/{curso}/team/{usuario}/permissions', [\App\Http\Controllers\Docente\DocenteCursoController::class, 'getMemberPermissions'])
        ->name('cursos.team.permissions');
    Route::post('cursos/{curso}/team/{usuario}/sync-permissions', [\App\Http\Controllers\Docente\DocenteCursoController::class, 'syncMemberPermissions'])
        ->name('cursos.team.sync-permissions');

    // Course detail view
    Route::get('cursos/{curso}', [\App\Http\Controllers\Docente\DocenteCursoController::class, 'show'])->name('cursos.show');

    // Activity management for courses
    Route::get('cursos/{curso}/actividades', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'show'])->name('cursos.actividades.index');
    Route::get('cursos/{curso}/actividades/json', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'getBysCursoJson'])->name('cursos.actividades.json');
    Route::post('cursos/{curso}/actividades', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'store'])->name('cursos.actividades.store');
    Route::put('cursos/{curso}/actividades/{actividad}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'update'])->name('cursos.actividades.update');
    Route::delete('cursos/{curso}/actividades/{actividad}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'destroy'])->name('cursos.actividades.destroy');

    // Activity evaluation (grading groups and individual students)
    Route::get('cursos/{curso}/actividades/{actividad}/evaluacion', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'showEvaluacion'])->name('cursos.actividades.evaluacion');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'storeGrupo'])->name('cursos.actividades.grupos.store');
    Route::put('cursos/{curso}/actividades/{actividad}/grupos/{grupo}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'updateGrupo'])->name('cursos.actividades.grupos.update');
    Route::delete('cursos/{curso}/actividades/{actividad}/grupos/{grupo}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'deleteGrupo'])->name('cursos.actividades.grupos.delete');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/integrantes', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'addIntegrante'])->name('cursos.actividades.integrantes.store');
    Route::put('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/integrantes/{asignado}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'updateIntegrante'])->name('cursos.actividades.integrantes.update');
    Route::delete('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/integrantes/{asignado}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'removeIntegrante'])->name('cursos.actividades.integrantes.delete');

    // Program Management
    Route::post('cursos/{curso}/programa', [AdminProgramaController::class, 'store'])
        ->name('cursos.programa.store');
    Route::get('cursos/{curso}/programa/json', [AdminProgramaController::class, 'getJson'])
        ->name('cursos.programa.json');
    Route::get('cursos/{curso}/programa', [\App\Http\Controllers\Administrativo\ProgramaController::class, 'show'])
        ->name('cursos.programa.show');
    Route::delete('cursos/{curso}/programa', [\App\Http\Controllers\Administrativo\ProgramaController::class, 'destroy'])
        ->name('cursos.programa.destroy');
    // Estado transitions: docente marks basic as done, or sends complete for review
    Route::put('cursos/{curso}/programa/completar-basico', [\App\Http\Controllers\Administrativo\ProgramaController::class, 'completarBasico'])
        ->name('cursos.programa.completar-basico');
    Route::put('cursos/{curso}/programa/enviar', [\App\Http\Controllers\Administrativo\ProgramaController::class, 'enviarParaRevision'])
        ->name('cursos.programa.enviar');
    
    // Secciones (for programa components auto-populate)
    Route::get('cursos/{curso}/secciones', [AdminSeccionController::class, 'indexByCurso'])
        ->name('cursos.secciones.index');

    // Student Enrollment (Inscripciones)
    Route::get('inscripciones', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'index'])->name('inscripciones.index');
    Route::get('inscripciones/create', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'create'])->name('inscripciones.create');
    Route::post('inscripciones', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'store'])->name('inscripciones.store');
    Route::get('inscripciones/{inscripcion_curso}/edit', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'edit'])->name('inscripciones.edit');
    // Note: Update and Destroy might be needed if Docentes can manage them, adhering to policy.
    Route::put('inscripciones/{inscripcion_curso}', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'update'])->name('inscripciones.update');
    // Docentes typically don't delete, but policy handles it.
    Route::get('inscripciones/ajax/disponibles', [\App\Http\Controllers\Admin\InscripcionCursoController::class, 'getEstudiantesDisponibles'])
        ->name('inscripciones.disponibles');
});

// Student Routes
Route::prefix('estudiante')->middleware(['auth', 'verified', 'is_estudiante'])->name('estudiante.')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');
    Route::get('cursos', [\App\Http\Controllers\Student\CourseController::class, 'index'])->name('cursos.index');

    // Programa (Syllabus) View - MUST be before generic {curso} route
    Route::get('cursos/{curso}/programa', [\App\Http\Controllers\Student\ProgramaController::class, 'show'])->name('cursos.programa.show');

    // Activities view - MUST be before generic {curso} route
    Route::get('cursos/{curso}/actividades', [\App\Http\Controllers\Student\ActivityController::class, 'index'])->name('cursos.actividades.index');

    Route::get('cursos/{curso}', [\App\Http\Controllers\Student\CourseController::class, 'show'])->name('cursos.show');
});

// Ayudante Routes
Route::prefix('ayudante')->middleware(['auth', 'verified', 'is_ayudante'])->name('ayudante.')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Ayudante\DashboardController::class, 'index'])->name('dashboard');
    Route::get('cursos', [\App\Http\Controllers\Ayudante\CourseController::class, 'index'])->name('cursos.index');

    // Programa (Syllabus) View - MUST be before generic {curso} route
    Route::get('cursos/{curso}/programa', [\App\Http\Controllers\Ayudante\ProgramaController::class, 'show'])->name('cursos.programa.show');
    Route::get('cursos/{curso}/programa/create', [\App\Http\Controllers\Ayudante\ProgramaController::class, 'create'])->name('cursos.programa.create');
    Route::get('cursos/{curso}/programa/editar', [\App\Http\Controllers\Ayudante\ProgramaController::class, 'edit'])->name('cursos.programa.edit');
    Route::get('cursos/{curso}/programa/json', [AdminProgramaController::class, 'getJson'])->name('cursos.programa.json');
    Route::post('cursos/{curso}/programa', [\App\Http\Controllers\Ayudante\ProgramaController::class, 'update'])->name('cursos.programa.update');

    // JSON endpoints used by SyllabusModal wizard
    Route::get('cursos/{curso}/secciones', [AdminSeccionController::class, 'indexByCurso'])->name('cursos.secciones.index');
    Route::get('cursos/{curso}/actividades/json', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'getBysCursoJson'])->name('cursos.actividades.json');

    Route::get('cursos/{curso}', [\App\Http\Controllers\Ayudante\CourseController::class, 'show'])->name('cursos.show');
});

// API Routes for AJAX/Fetch calls
Route::prefix('api')->middleware(['auth', 'verified'])->group(function () {
    Route::get('docentes', [CursoController::class, 'getDocentes']);
});

require __DIR__ . '/settings.php';
