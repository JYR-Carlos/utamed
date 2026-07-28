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
use App\Http\Controllers\Admin\ComponenteController as AdminSeccionController;
use App\Http\Controllers\Admin\ProgramaController as AdminProgramaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\AssignmentWizardController;

Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    /** @var \App\Models\Usuario\Usuario $user */
    $user = \Illuminate\Support\Facades\Auth::user();

    // Redirigir docentes a su dashboard
    if ($user->hasAnyRole(['Docente Titular', 'Docente Titular Restringido', 'Docente Componente'])) {
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

    // Solo admins y superadmins llegan aquí
    if ($user->hasRole('Administrador') || $user->hasRole('SuperAdmin')) {
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
    }

    // Usuario autenticado sin ningún rol asignado
    return redirect()->route('sin-rol');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('sin-rol', function () {
    return Inertia::render('SinRol');
})->middleware(['auth'])->name('sin-rol');

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

    // Componentes routes
    Route::get('cursos/{curso}/componentes', [AdminSeccionController::class, 'indexByCurso'])
        ->name('cursos.componentes.index');
    Route::get('tipos-componente', fn() => \App\Models\Curso\TipoComponente::select('id', 'nombre')->get())
        ->name('tipos-componente.index');

    // Actividades routes - Get activities for program wizard
    Route::get('cursos/{curso}/actividades/json', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'actividadesJson'])
        ->name('cursos.actividades.json');

    // Resource routes (more general routes go after specific ones)
    Route::resource('facultades', FacultadController::class);
    Route::resource('departamentos', DepartamentoController::class);
    Route::resource('carreras', CarreraController::class);
    Route::resource('planes', PlanController::class)->parameters(['planes' => 'plan']);
    Route::resource('asignaturas', AsignaturaController::class);

    // Copia de curso (BEFORE resource to avoid conflict with {curso} binding)
    Route::get('cursos/{curso}/preview-copia', [CursoController::class, 'previewCopia'])
        ->name('cursos.preview-copia');
    Route::post('cursos/{curso}/copiar', [CursoController::class, 'copiar'])
        ->name('cursos.copiar');

    // Próxima letra de grupo disponible (BEFORE resource to avoid conflict with {curso} binding)
    Route::get('cursos/proxima-letra', [CursoController::class, 'getProximaLetra'])
        ->name('cursos.proxima-letra');

    Route::resource('cursos', CursoController::class);

    Route::get('usuarios/buscar-por-rut', [UsuarioController::class, 'buscarPorRut'])
        ->name('usuarios.buscarPorRut');

    Route::post('usuarios/importar', [UsuarioController::class, 'import'])
        ->name('usuarios.importar');

    Route::resource('usuarios', UsuarioController::class);

    // Curso routes
    Route::get('cursos/{plan}/asignaturas-disponibles', [CursoController::class, 'getAsignaturasByPlan'])
        ->name('cursos.asignaturas-disponibles');
    Route::get('asignaturas/{asignatura}/cursos-anteriores', [CursoController::class, 'getCursosAnteriores'])
        ->name('asignaturas.cursos-anteriores');
    Route::get('asignaturas/{asignatura}/docentes-sugeridos', [CursoController::class, 'getDocentesSugeridos'])
        ->name('asignaturas.docentes-sugeridos');

    // Additional usuario routes
    Route::post('usuarios/{usuario}/change-password', [UsuarioController::class, 'changePassword'])
        ->name('usuarios.change-password');
    Route::post('usuarios/{usuario}/toggle-active', [UsuarioController::class, 'toggleActive'])
        ->name('usuarios.toggle-active');

    // User Permissions Routes
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
    Route::delete('usuarios/{usuario}/roles/{ura}', [AssignmentWizardController::class, 'revokeRole'])
        ->name('usuarios.revoke-role');
    Route::delete('usuarios/{usuario}/permissions/{upe}', [AssignmentWizardController::class, 'revokePermission'])
        ->name('usuarios.revoke-permission');

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

    // Componente Management for Courses
    Route::post('cursos/{curso}/componentes', [AdminSeccionController::class, 'store'])
        ->name('cursos.componentes.store');
    // El {curso} es obligatorio en la URL: sin él estas seis escrituras quedaban
    // ligadas sólo al ID global del componente, sin ámbito ni comprobación de
    // pertenencia (F-2).
    Route::put('cursos/{curso}/componentes/{componente}', [AdminSeccionController::class, 'update'])
        ->name('cursos.componentes.update');
    Route::delete('cursos/{curso}/componentes/{componente}', [AdminSeccionController::class, 'destroy'])
        ->name('cursos.componentes.destroy');
    Route::post('cursos/{curso}/componentes/{componente}/docentes', [AdminSeccionController::class, 'addDocente'])
        ->name('cursos.componentes.docentes.store');
    Route::delete('cursos/{curso}/componentes/{componente}/docentes/{docenteComponente}', [AdminSeccionController::class, 'removeDocente'])
        ->name('cursos.componentes.docentes.destroy');
    Route::put('cursos/{curso}/componentes/{componente}/titular', [AdminSeccionController::class, 'setTitular'])
        ->name('cursos.componentes.titular');
    Route::put('cursos/{curso}/componentes/{componente}/genera-acta', [AdminSeccionController::class, 'toggleGeneraActa'])
        ->name('cursos.componentes.genera-acta');

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
    Route::prefix('jefe-carrera')->name('jefe-carrera.')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Docente\JefeCarreraController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('seguimiento', [\App\Http\Controllers\Docente\JefeCarreraController::class, 'seguimiento'])
            ->name('seguimiento');
        Route::get('metricas', [\App\Http\Controllers\Docente\JefeCarreraController::class, 'metricas'])
            ->name('metricas');
        Route::get('programas/{programaId}/preview', [\App\Http\Controllers\Docente\JefeCarreraController::class, 'programaPreview'])
            ->name('programas.preview');
        Route::post('programas/{programaId}/aprobar', [\App\Http\Controllers\Docente\JefeCarreraController::class, 'aprobarPrograma'])
            ->name('programas.aprobar');
        Route::post('programas/{programaId}/rechazar', [\App\Http\Controllers\Docente\JefeCarreraController::class, 'rechazarPrograma'])
            ->name('programas.rechazar');

        // ── Gestión acotada a la carrera (espejo de rutas admin) ──────────────
        // Planes / Malla
        Route::get('planes', [\App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'index'])
            ->name('planes.index');
        Route::post('planes', [\App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'store'])
            ->name('planes.store');
        Route::put('planes/{plan}', [\App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'update'])
            ->name('planes.update');
        Route::delete('planes/{plan}', [\App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'destroy'])
            ->name('planes.destroy');

        // Editor de malla (asignación de asignaturas a un plan)
        Route::get('planes/{plan}/asignaturas/json', [\App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'mallaJson'])
            ->name('planes.asignaturas.json');
        Route::get('planes/{plan}/asignaturas', [\App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'index'])
            ->name('planes.asignaturas.index');
        Route::post('planes/{plan}/asignaturas', [\App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'store'])
            ->name('planes.asignaturas.store');
        Route::put('planes/{plan}/asignaturas/{asignatura}', [\App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'update'])
            ->name('planes.asignaturas.update');
        Route::delete('planes/{plan}/asignaturas/{asignatura}', [\App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'destroy'])
            ->name('planes.asignaturas.destroy');

        // Asignaturas (catálogo acotado a la carrera)
        Route::get('asignaturas', [\App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'index'])
            ->name('asignaturas.index');
        Route::post('asignaturas', [\App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'store'])
            ->name('asignaturas.store');
        Route::put('asignaturas/{asignatura}', [\App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'update'])
            ->name('asignaturas.update');
        Route::delete('asignaturas/{asignatura}', [\App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'destroy'])
            ->name('asignaturas.destroy');

        // Detalle de carrera (solo lectura)
        Route::get('carrera', [\App\Http\Controllers\Docente\JefeCarrera\CarreraController::class, 'show'])
            ->name('carrera.show');
    });
    Route::get('cursos', [\App\Http\Controllers\Docente\DocenteCursoController::class, 'index'])->name('cursos.index');

    // Calendario académico del docente (vista de solo lectura de actividades a vencer)
    Route::get('calendario', [\App\Http\Controllers\Docente\CalendarioController::class, 'index'])->name('calendario.index');

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

    // ── Gestión de permisos granulares por el DT del curso ──────────────────
    Route::get('cursos/{curso}/permisos-syllabus', [\App\Http\Controllers\Docente\CursoPermisosController::class, 'syllabusIndex'])
        ->name('cursos.permisos-syllabus.index');
    Route::post('cursos/{curso}/permisos-syllabus', [\App\Http\Controllers\Docente\CursoPermisosController::class, 'syllabusSync'])
        ->name('cursos.permisos-syllabus.sync');

    // ── Gestión de permisos en componente colegiado por el DT del componente
    Route::get('cursos/{curso}/componentes/{componente}/permisos', [\App\Http\Controllers\Docente\CursoPermisosController::class, 'componenteIndex'])
        ->name('cursos.componentes.permisos.index');
    Route::post('cursos/{curso}/componentes/{componente}/permisos', [\App\Http\Controllers\Docente\CursoPermisosController::class, 'componenteSync'])
        ->name('cursos.componentes.permisos.sync');

    // ── Delegación granular de permisos por el DT del curso (matriz completa)
    Route::get('cursos/{curso}/delegacion-permisos', [\App\Http\Controllers\Docente\DelegacionPermisosController::class, 'index'])
        ->name('cursos.delegacion-permisos.index');
    Route::post('cursos/{curso}/delegacion-permisos/toggle', [\App\Http\Controllers\Docente\DelegacionPermisosController::class, 'toggle'])
        ->name('cursos.delegacion-permisos.toggle');

    // ── Cambio de titular del componente por el DT del curso (sin acceso admin)
    Route::put('cursos/{curso}/componentes/{componente}/titular', [\App\Http\Controllers\Admin\ComponenteController::class, 'setTitularByDt'])
        ->name('cursos.componentes.titular.docente');

    // ── Centro de asistencia (transversal): elegir curso → componente → tomar asistencia
    Route::get('asistencia', [\App\Http\Controllers\Docente\AsistenciaController::class, 'centro'])
        ->name('asistencia.centro');

    // ── Asistencia por componente (sesión implícita: dia + hora_inicio + hora_fin)
    Route::get('cursos/{curso}/componentes/{componente}/asistencia', [\App\Http\Controllers\Docente\AsistenciaController::class, 'index'])
        ->name('cursos.componentes.asistencia.index');
    Route::post('cursos/{curso}/componentes/{componente}/asistencia', [\App\Http\Controllers\Docente\AsistenciaController::class, 'store'])
        ->name('cursos.componentes.asistencia.store');
    Route::put('cursos/{curso}/componentes/{componente}/asistencia', [\App\Http\Controllers\Docente\AsistenciaController::class, 'update'])
        ->name('cursos.componentes.asistencia.update');
    Route::delete('cursos/{curso}/componentes/{componente}/asistencia', [\App\Http\Controllers\Docente\AsistenciaController::class, 'destroy'])
        ->name('cursos.componentes.asistencia.destroy');

    // Course detail view
    Route::get('cursos/{curso}/docentes', [\App\Http\Controllers\Docente\DocenteCursoController::class, 'docentes'])->name('cursos.docentes');
    Route::get('cursos/{curso}', [\App\Http\Controllers\Docente\DocenteCursoController::class, 'show'])->name('cursos.show');

    // Activity management for courses
    Route::get('cursos/{curso}/actividades', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'show'])->name('cursos.actividades.index');
    Route::get('cursos/{curso}/actividades/json', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'actividadesJson'])->name('cursos.actividades.json');
    Route::post('cursos/{curso}/actividades', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'store'])->name('cursos.actividades.store');
    Route::put('cursos/{curso}/actividades/{actividad}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'update'])->name('cursos.actividades.update');
    Route::patch('cursos/{curso}/actividades/{actividad}/visibilidad', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'toggleVisibilidad'])->name('cursos.actividades.visibilidad.toggle');
    Route::delete('cursos/{curso}/actividades/{actividad}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'destroy'])->name('cursos.actividades.destroy');

    // ── Centro de calificaciones (transversal): elegir curso → componente → actividad → evaluar
    Route::get('calificaciones', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'centroCalificaciones'])
        ->name('calificaciones.centro');

    // Activity evaluation (grading groups and individual students)
    Route::get('cursos/{curso}/actividades/{actividad}/evaluacion', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'showEvaluacion'])->name('cursos.actividades.evaluacion');
    // Notas individuales: ajuste de décimas y recálculo desde la nota grupal.
    // (El CRUD de grupos/integrantes vive más abajo en grupos-create/grupos-delete/estudiante.)
    Route::put('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/integrantes/{asignado}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'updateIntegrante'])->name('cursos.actividades.integrantes.update');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/recalcular-notas', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'recalcularNotasIndividuales'])->name('cursos.actividades.grupos.recalcular');

    // Gestión avanzada de grupos (nuevas funcionalidades)
    Route::post('cursos/{curso}/rubrica', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'storeRubrica'])->name('cursos.rubrica.store');

    Route::post('cursos/{curso}/actividades/{actividad}/grupos-create', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'crearGrupo'])->name('cursos.actividades.grupos.create');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/estudiante', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'agregarEstudianteAGrupo'])->name('cursos.actividades.grupos.estudiante.add');
    Route::delete('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/estudiantes/{estudiante}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'quitarEstudianteDeGrupo'])->name('cursos.actividades.grupos.estudiante.remove');
    Route::delete('cursos/{curso}/actividades/{actividad}/grupos-delete/{grupo}', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'eliminarGrupo'])->name('cursos.actividades.grupos.new.delete');
    Route::get('cursos/{curso}/actividades/{actividad}/grupos-list', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'gruposPorActividad'])->name('cursos.actividades.grupos.list');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos-copy', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'copiarGruposDeActividad'])->name('cursos.actividades.grupos.copy');

    // Gestión de entregas/archivos
    Route::get('cursos/{curso}/actividades/{actividad}/entregas', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'entregasPorActividad'])->name('cursos.actividades.entregas.list');
    Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'entregasPorGrupo'])->name('cursos.actividades.entregas.grupo');
    Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas/{agenda}/descargar', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'descargarEntrega'])->name('cursos.actividades.entregas.descargar');

    // Centro de mensajes del docente (bandeja transversal a todos sus cursos)
    Route::get('mensajes', [\App\Http\Controllers\Docente\MensajesController::class, 'index'])->name('mensajes.index');
    Route::post('mensajes/cursos/{curso}/actividades/{actividad}/enviar', [\App\Http\Controllers\Docente\MensajesController::class, 'send'])->name('mensajes.send');

    // Mensajería (usa agenda.agenda — tipos "Mensaje al profesor" y "Feedback")
    // Nivel 1: vista general del curso
    Route::get('cursos/{curso}/mensajes', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'showMensajesCurso'])->name('cursos.mensajes.index');
    // Nivel 1 + 2: hilo de un estudiante (todos sus grupos en el curso)
    Route::get('cursos/{curso}/estudiantes/{idEstudiante}/mensajes', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'mensajesEstudiante'])->name('cursos.estudiantes.mensajes');
    // Nivel 2: hilo de un grupo específico de una actividad
    Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/mensajes', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'mensajesGrupo'])->name('cursos.actividades.grupos.mensajes');
    // Docente envía feedback a un grupo
    Route::post('cursos/{curso}/grupos/{grupo}/feedback', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'enviarFeedback'])->name('cursos.grupos.feedback');
    // 1. Autor: Juan Y.
    // 2. Fecha: 04/06/2025
    // 3. Ruta nueva: POST para que el docente registre una evaluación sobre un grupo de actividad.
    Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/evaluacion', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'storeEvaluacion'])->name('cursos.actividades.grupos.evaluacion');

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

    // Componentes (for programa components auto-populate)
    Route::get('cursos/{curso}/componentes', [AdminSeccionController::class, 'indexByCurso'])
        ->name('cursos.componentes.index');

});

// Student Routes
Route::prefix('estudiante')
    ->middleware(['auth', 'verified', 'is_estudiante'])
    ->name('estudiante.')
    ->group(function () {
        // rutas generales
        Route::get('dashboard', [\App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');
        Route::get('cursos', [\App\Http\Controllers\Student\CourseController::class, 'index'])->name('cursos.index');

        // Programa (Syllabus) View - MUST be before generic {curso} route
        Route::get('cursos/{curso}/programa', [\App\Http\Controllers\Student\ProgramaController::class, 'show'])->name('cursos.programa.show');
        Route::get('cursos/{curso}', [\App\Http\Controllers\Student\CourseController::class, 'show'])->name('cursos.show');
        //Route::get('cursos/{curso}/actividad', [\App\Http\Controllers\Student\ActivityController::class, 'show'])->name('cursos.actividades.show');
        Route::get('cursos/{curso}/actividad/{actividad}', [\App\Http\Controllers\Student\ActivityController::class, 'show'])
            ->name('cursos.actividades.show');

        // Agenda routes
        Route::controller(\App\Http\Controllers\Student\AgendaController::class)
            ->group(function () {
                
                Route::post(
                    // estudiante/grupos-asignados/{actividadAsignadaGrupo}/agenda
                    'grupos-asignados/{actividadAsignadaGrupo}/agenda',
                    'store'
                )->name('actividades.agenda.store');

                Route::post(
                    // estudiante/agendas/{registroAgenda}/archivos
                    'agendas/{registroAgenda}/archivos',
                    'storeFile'
                )->name('actividades.agenda.agendas.storeFile');
                Route::post(
                    'grupos-asignados/{actividadAsignadaGrupo}/entregas',
                    [\App\Http\Controllers\Student\AgendaController::class, 'storeEntrega']
                )->name('actividades.agenda.storeEntrega');
            });
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
    Route::get('cursos/{curso}/componentes', [AdminSeccionController::class, 'indexByCurso'])->name('cursos.componentes.index');
    Route::get('cursos/{curso}/actividades/json', [\App\Http\Controllers\Docente\DocenteActivityController::class, 'actividadesJson'])->name('cursos.actividades.json');

    Route::get('cursos/{curso}', [\App\Http\Controllers\Ayudante\CourseController::class, 'show'])->name('cursos.show');
});

// API Routes for AJAX/Fetch calls
Route::prefix('api')->middleware(['auth', 'verified'])->group(function () {
    Route::get('docentes', [CursoController::class, 'getDocentes']);
});

require __DIR__ . '/settings.php';
