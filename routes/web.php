<?php

use App\Http\Controllers\Admin\AsignacionPlanController;
use App\Http\Controllers\Admin\AsignaturaController;
use App\Http\Controllers\Admin\AssignmentWizardController;
use App\Http\Controllers\Admin\CarreraController;
use App\Http\Controllers\Admin\ComponenteController;
use App\Http\Controllers\Admin\ComponenteController as AdminSeccionController;
use App\Http\Controllers\Admin\CourseTeamController;
use App\Http\Controllers\Admin\CursoController;
use App\Http\Controllers\Admin\DepartamentoController;
use App\Http\Controllers\Admin\FacultadController;
use App\Http\Controllers\Admin\InscripcionCursoController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProgramaController as AdminProgramaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Administrativo\ProgramaController;
use App\Http\Controllers\Docente\AsistenciaController;
use App\Http\Controllers\Docente\CalendarioController;
use App\Http\Controllers\Docente\CursoPermisosController;
use App\Http\Controllers\Docente\DashboardController;
use App\Http\Controllers\Docente\DelegacionPermisosController;
use App\Http\Controllers\Docente\DocenteActivityController;
use App\Http\Controllers\Docente\DocenteCursoController;
use App\Http\Controllers\Docente\JefeCarreraController;
use App\Http\Controllers\Docente\MensajeriaController;
use App\Http\Controllers\Docente\MensajesController;
use App\Http\Controllers\Student\ActivityController;
use App\Http\Controllers\Student\AgendaController;
use App\Http\Controllers\Student\CourseController;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Facultad;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    /** @var Usuario $user */
    $user = Auth::user();

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
        $cursosAbiertos = Curso::where('estado_interno', 'ABIERTO');

        return Inertia::render('Dashboard', [
            'stats' => [
                'usuarios' => Usuario::count(),
                'cursos_total' => Curso::count(),
                'cursos_pendientes' => (clone $cursosAbiertos)
                    ->where(function ($query) {
                        $query->where('estado_acta', '!=', 'ENVIADO')
                            ->orWhereNull('estado_acta');
                    })
                    ->count(),
                'facultades' => Facultad::count(),
                'carreras' => Carrera::count(),
            ],
            // Lo que requiere acción, que es la pregunta que el administrador
            // trae al abrir el panel. Antes el dashboard sólo mostraba totales
            // acompañados de líneas de tendencia decorativas.
            'pendientes' => [
                'cursos_sin_syllabus' => (clone $cursosAbiertos)->doesntHave('programas')->count(),
                'cursos_sin_componentes' => (clone $cursosAbiertos)->doesntHave('componentes')->count(),
                'carreras_sin_director' => Carrera::whereNull('fecha_eliminacion')
                    ->doesntHave('jefesDeCarreraActivos')
                    ->count(),
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
    // Asistente de syllabus: pantalla propia, ya no un modal sobre el visor.
    Route::get('cursos/{curso}/programa/editar', [AdminProgramaController::class, 'edit'])
        ->name('cursos.programa.edit');
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
    Route::get('tipos-componente', fn() => TipoComponente::select('id', 'nombre')->get())
        ->name('tipos-componente.index');

    // Actividades routes - Get activities for program wizard
    Route::get('cursos/{curso}/actividades/json', [DocenteActivityController::class, 'actividadesJson'])
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

    // Detección automática de componentes (Intranet → fallback Plan de Estudios),
    // ANTES de crear el curso — cero selección manual en el wizard. (BEFORE
    // resource to avoid conflict with {curso} binding)
    Route::get('cursos/preview-componentes', [CursoController::class, 'previewComponentes'])
        ->name('cursos.preview-componentes');

    // Sincronización masiva (varios cursos sin componentes a la vez): mismo
    // patrón de "revisar antes de confirmar" que la de un curso. (BEFORE
    // resource to avoid conflict with {curso} binding)
    Route::get('cursos/sincronizar-intranet-masivo/preview', [CursoController::class, 'previewSincronizarMasivo'])
        ->name('cursos.sincronizar-intranet-masivo.preview');
    Route::post('cursos/sincronizar-intranet-masivo', [CursoController::class, 'sincronizarMasivo'])
        ->name('cursos.sincronizar-intranet-masivo');

    Route::resource('cursos', CursoController::class);

    Route::get('usuarios/buscar-por-rut', [UsuarioController::class, 'buscarPorRut'])
        ->name('usuarios.buscarPorRut');

    // Importación masiva: plantilla → previsualización → importación. Las tres
    // van antes del resource para que 'usuarios/importar' no se interprete
    // como 'usuarios/{usuario}'.
    Route::get('usuarios/plantilla-importacion', [UsuarioController::class, 'plantillaImportacion'])
        ->name('usuarios.plantilla-importacion');

    Route::post('usuarios/importar/previsualizar', [UsuarioController::class, 'previsualizarImportacion'])
        ->name('usuarios.importar.previsualizar');

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

    // Sincronizar un curso ya existente con Intranet: preview ("mirar antes
    // de tocar") y confirmación (ejecuta sólo lo aceptado).
    Route::get('cursos/{curso}/sincronizar-intranet/preview', [CursoController::class, 'previewSincronizarIntranet'])
        ->name('cursos.sincronizar-intranet.preview');
    Route::post('cursos/{curso}/sincronizar-intranet', [CursoController::class, 'sincronizarIntranet'])
        ->name('cursos.sincronizar-intranet');

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
    Route::post('inscripciones_cursos/bulk', [InscripcionCursoController::class, 'storeBulk'])
        ->name('inscripciones_cursos.bulk');
    Route::patch('inscripciones_cursos/{inscripcionCurso}/estado', [InscripcionCursoController::class, 'updateEstado'])
        ->name('inscripciones_cursos.estado');
    Route::resource('inscripciones_cursos', InscripcionCursoController::class);
    Route::get('inscripciones_cursos/ajax/disponibles', [InscripcionCursoController::class, 'getEstudiantesDisponibles'])
        ->name('inscripciones_cursos.disponibles');
    Route::get('inscripciones_cursos/ajax/by-curso', [InscripcionCursoController::class, 'getByCurso'])
        ->name('inscripciones_cursos.by-curso');
    Route::get('inscripciones_cursos/export/csv', [InscripcionCursoController::class, 'exportCsv'])
        ->name('inscripciones_cursos.export.csv');
    Route::post('cursos/{curso}/inscripcion-automatica', [InscripcionCursoController::class, 'inscripcionAutomatica'])
        ->name('cursos.inscripcion-automatica');

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

// Perfil de docente incompleto (rol asignado, sin ficha usuario.docente).
// Fuera de is_docente a propósito: es el destino cuando esa comprobación
// falla por falta de perfil, no por falta de rol.
Route::get('docente/perfil-incompleto', [DashboardController::class, 'perfilIncompleto'])
    ->middleware(['auth', 'verified'])
    ->name('docente.perfil-incompleto');

// Docente Routes
Route::prefix('docente')->middleware(['auth', 'verified', 'is_docente'])->name('docente.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::prefix('jefe-carrera')->name('jefe-carrera.')->group(function () {
        Route::get('dashboard', [JefeCarreraController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('seguimiento', [JefeCarreraController::class, 'seguimiento'])
            ->name('seguimiento');
        Route::get('metricas', [JefeCarreraController::class, 'metricas'])
            ->name('metricas');
        Route::get('programas/{programaId}/preview', [JefeCarreraController::class, 'programaPreview'])
            ->name('programas.preview');
        Route::post('programas/{programaId}/aprobar', [JefeCarreraController::class, 'aprobarPrograma'])
            ->name('programas.aprobar');
        Route::post('programas/{programaId}/rechazar', [JefeCarreraController::class, 'rechazarPrograma'])
            ->name('programas.rechazar');

        // ── Gestión acotada a la carrera (espejo de rutas admin) ──────────────
        // Planes / Malla
        Route::get('planes', [App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'index'])
            ->name('planes.index');
        Route::post('planes', [App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'store'])
            ->name('planes.store');
        Route::put('planes/{plan}', [App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'update'])
            ->name('planes.update');
        Route::delete('planes/{plan}', [App\Http\Controllers\Docente\JefeCarrera\PlanController::class, 'destroy'])
            ->name('planes.destroy');

        // Editor de malla (asignación de asignaturas a un plan)
        Route::get('planes/{plan}/asignaturas/json', [App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'mallaJson'])
            ->name('planes.asignaturas.json');
        Route::get('planes/{plan}/asignaturas', [App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'index'])
            ->name('planes.asignaturas.index');
        Route::post('planes/{plan}/asignaturas', [App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'store'])
            ->name('planes.asignaturas.store');
        Route::put('planes/{plan}/asignaturas/{asignatura}', [App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'update'])
            ->name('planes.asignaturas.update');
        Route::delete('planes/{plan}/asignaturas/{asignatura}', [App\Http\Controllers\Docente\JefeCarrera\AsignacionPlanController::class, 'destroy'])
            ->name('planes.asignaturas.destroy');

        // Asignaturas (catálogo acotado a la carrera)
        Route::get('asignaturas', [App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'index'])
            ->name('asignaturas.index');
        Route::post('asignaturas', [App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'store'])
            ->name('asignaturas.store');
        Route::put('asignaturas/{asignatura}', [App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'update'])
            ->name('asignaturas.update');
        Route::delete('asignaturas/{asignatura}', [App\Http\Controllers\Docente\JefeCarrera\AsignaturaController::class, 'destroy'])
            ->name('asignaturas.destroy');

        // Detalle de carrera (solo lectura)
        Route::get('carrera', [App\Http\Controllers\Docente\JefeCarrera\CarreraController::class, 'show'])
            ->name('carrera.show');
    });
    Route::get('cursos', [DocenteCursoController::class, 'index'])->name('cursos.index');

    // Calendario académico del docente (vista de solo lectura de actividades a vencer)
    Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario.index');

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
    Route::get('cursos/{curso}/team/{usuario}/permissions', [DocenteCursoController::class, 'getMemberPermissions'])
        ->name('cursos.team.permissions');
    Route::post('cursos/{curso}/team/{usuario}/sync-permissions', [DocenteCursoController::class, 'syncMemberPermissions'])
        ->name('cursos.team.sync-permissions');

    // ── Gestión de permisos granulares por el DT del curso ──────────────────
    Route::get('cursos/{curso}/permisos-syllabus', [CursoPermisosController::class, 'syllabusIndex'])
        ->name('cursos.permisos-syllabus.index');
    Route::post('cursos/{curso}/permisos-syllabus', [CursoPermisosController::class, 'syllabusSync'])
        ->name('cursos.permisos-syllabus.sync');

    // ── Gestión de permisos en componente colegiado por el DT del componente
    Route::get('cursos/{curso}/componentes/{componente}/permisos', [CursoPermisosController::class, 'componenteIndex'])
        ->name('cursos.componentes.permisos.index');
    Route::post('cursos/{curso}/componentes/{componente}/permisos', [CursoPermisosController::class, 'componenteSync'])
        ->name('cursos.componentes.permisos.sync');

    // ── Delegación granular de permisos por el DT del curso (matriz completa)
    Route::get('cursos/{curso}/delegacion-permisos', [DelegacionPermisosController::class, 'index'])
        ->name('cursos.delegacion-permisos.index');
    Route::post('cursos/{curso}/delegacion-permisos/toggle', [DelegacionPermisosController::class, 'toggle'])
        ->name('cursos.delegacion-permisos.toggle');

    // ── Cambio de titular del componente por el DT del curso (sin acceso admin)
    Route::put('cursos/{curso}/componentes/{componente}/titular', [ComponenteController::class, 'setTitularByDt'])
        ->name('cursos.componentes.titular.docente');

    // ── Centro de asistencia (transversal): elegir curso → componente → tomar asistencia
    Route::get('asistencia', [AsistenciaController::class, 'centro'])
        ->name('asistencia.centro');

    // ── Asistencia por componente (sesión implícita: dia + hora_inicio + hora_fin)
    Route::get('cursos/{curso}/componentes/{componente}/asistencia', [AsistenciaController::class, 'index'])
        ->name('cursos.componentes.asistencia.index');
    Route::post('cursos/{curso}/componentes/{componente}/asistencia', [AsistenciaController::class, 'store'])
        ->name('cursos.componentes.asistencia.store');
    Route::put('cursos/{curso}/componentes/{componente}/asistencia', [AsistenciaController::class, 'update'])
        ->name('cursos.componentes.asistencia.update');
    Route::delete('cursos/{curso}/componentes/{componente}/asistencia', [AsistenciaController::class, 'destroy'])
        ->name('cursos.componentes.asistencia.destroy');

    // Course detail view
    Route::get('cursos/{curso}/docentes', [DocenteCursoController::class, 'docentes'])->name('cursos.docentes');
    Route::get('cursos/{curso}', [DocenteCursoController::class, 'show'])->name('cursos.show');

    // Activity management for courses
    Route::get('cursos/{curso}/actividades', [DocenteActivityController::class, 'show'])->name('cursos.actividades.index');
    Route::get('cursos/{curso}/actividades/json', [DocenteActivityController::class, 'actividadesJson'])->name('cursos.actividades.json');
    Route::post('cursos/{curso}/actividades', [DocenteActivityController::class, 'store'])->name('cursos.actividades.store');
    Route::put('cursos/{curso}/actividades/{actividad}', [DocenteActivityController::class, 'update'])->name('cursos.actividades.update');
    Route::patch('cursos/{curso}/actividades/{actividad}/visibilidad', [DocenteActivityController::class, 'toggleVisibilidad'])->name('cursos.actividades.visibilidad.toggle');
    Route::delete('cursos/{curso}/actividades/{actividad}', [DocenteActivityController::class, 'destroy'])->name('cursos.actividades.destroy');

    // Enunciado de la actividad (archivo descriptivo)
    Route::post('cursos/{curso}/actividades/{actividad}/enunciado', [DocenteActivityController::class, 'subirEnunciado'])->name('cursos.actividades.enunciado.store');
    Route::get('cursos/{curso}/actividades/{actividad}/enunciado/descargar', [DocenteActivityController::class, 'descargarEnunciado'])->name('cursos.actividades.enunciado.descargar');

    // ── Centro de calificaciones (transversal): elegir curso → componente → actividad → evaluar
    Route::get('calificaciones', [DocenteActivityController::class, 'centroCalificaciones'])
        ->name('calificaciones.centro');

    // Activity evaluation (grading groups and individual students)
    Route::get('cursos/{curso}/actividades/{actividad}/evaluacion', [DocenteActivityController::class, 'showEvaluacion'])->name('cursos.actividades.evaluacion');
    // Notas individuales: ajuste de décimas y recálculo desde la nota grupal.
    // (El CRUD de grupos/integrantes vive más abajo en grupos-create/grupos-delete/estudiante.)
    Route::put('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/integrantes/{asignado}', [DocenteActivityController::class, 'updateIntegrante'])->name('cursos.actividades.integrantes.update');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/recalcular-notas', [DocenteActivityController::class, 'recalcularNotasIndividuales'])->name('cursos.actividades.grupos.recalcular');

    // Gestión avanzada de grupos (nuevas funcionalidades)
    Route::post('cursos/{curso}/rubrica', [DocenteActivityController::class, 'storeRubrica'])->name('cursos.rubrica.store');

    Route::post('cursos/{curso}/actividades/{actividad}/grupos-create', [DocenteActivityController::class, 'crearGrupo'])->name('cursos.actividades.grupos.create');
    Route::patch('cursos/{curso}/actividades/{actividad}/grupos/{grupo}', [DocenteActivityController::class, 'updateGrupo'])->name('cursos.actividades.grupos.update');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/estudiante', [DocenteActivityController::class, 'agregarEstudianteAGrupo'])->name('cursos.actividades.grupos.estudiante.add');
    Route::delete('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/estudiantes/{estudiante}', [DocenteActivityController::class, 'quitarEstudianteDeGrupo'])->name('cursos.actividades.grupos.estudiante.remove');
    Route::delete('cursos/{curso}/actividades/{actividad}/grupos-delete/{grupo}', [DocenteActivityController::class, 'eliminarGrupo'])->name('cursos.actividades.grupos.new.delete');
    Route::get('cursos/{curso}/actividades/{actividad}/grupos-list', [DocenteActivityController::class, 'gruposPorActividad'])->name('cursos.actividades.grupos.list');
    Route::get('cursos/{curso}/actividades/{actividad}/grupos-origen/{origen}', [DocenteActivityController::class, 'gruposDeActividadOrigen'])->name('cursos.actividades.grupos.origen');
    Route::post('cursos/{curso}/actividades/{actividad}/grupos-copy', [DocenteActivityController::class, 'copiarGruposDeActividad'])->name('cursos.actividades.grupos.copy');

    // Gestión de entregas/archivos
    Route::get('cursos/{curso}/actividades/{actividad}/entregas', [DocenteActivityController::class, 'entregasPorActividad'])->name('cursos.actividades.entregas.list');
    Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas', [DocenteActivityController::class, 'entregasPorGrupo'])->name('cursos.actividades.entregas.grupo');
    Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas/{agenda}/descargar', [DocenteActivityController::class, 'descargarEntrega'])->name('cursos.actividades.entregas.descargar');

    // Centro de mensajes del docente (bandeja transversal a todos sus cursos)
    Route::get('mensajes', [MensajesController::class, 'index'])->name('mensajes.index');
    Route::post('mensajes/cursos/{curso}/actividades/{actividad}/enviar', [MensajesController::class, 'send'])->name('mensajes.send');

    // Mensajería por componente (curso.mensaje) — independiente de agenda.agenda.
    // Se entra desde el curso: la bandeja abre acotada a ese curso y muestra sus
    // componentes como pestañas, con difusiones y un canal por alumno que
    // comparten todos los docentes del componente (colegiados incl.).
    Route::get('cursos/{curso}/mensajeria', [MensajeriaController::class, 'index'])->name('cursos.mensajeria.index');
    Route::post('cursos/{curso}/mensajeria/componentes/{componente}/difusion', [MensajeriaController::class, 'enviarDifusion'])->name('cursos.mensajeria.difusion');
    Route::post('cursos/{curso}/mensajeria/componentes/{componente}/alumnos/{alumno}/mensaje', [MensajeriaController::class, 'enviarMensaje'])->name('cursos.mensajeria.mensaje');

    // Mensajería (usa agenda.agenda — tipos "Mensaje al profesor" y "Feedback")
    // Nivel 1: vista general del curso
    Route::get('cursos/{curso}/mensajes', [DocenteActivityController::class, 'showMensajesCurso'])->name('cursos.mensajes.index');
    // Nivel 1 + 2: hilo de un estudiante (todos sus grupos en el curso)
    Route::get('cursos/{curso}/estudiantes/{idEstudiante}/mensajes', [DocenteActivityController::class, 'mensajesEstudiante'])->name('cursos.estudiantes.mensajes');
    // Nivel 2: hilo de un grupo específico de una actividad
    Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/mensajes', [DocenteActivityController::class, 'mensajesGrupo'])->name('cursos.actividades.grupos.mensajes');
    // Docente envía feedback a un grupo
    Route::post('cursos/{curso}/grupos/{grupo}/feedback', [DocenteActivityController::class, 'enviarFeedback'])->name('cursos.grupos.feedback');
    // 1. Autor: Juan Y.
    // 2. Fecha: 04/06/2025
    // 3. Ruta nueva: POST para que el docente registre una evaluación sobre un grupo de actividad.
    Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/evaluacion', [DocenteActivityController::class, 'storeEvaluacion'])->name('cursos.actividades.grupos.evaluacion');

    // Program Management
    Route::post('cursos/{curso}/programa', [AdminProgramaController::class, 'store'])
        ->name('cursos.programa.store');
    Route::get('cursos/{curso}/programa/json', [AdminProgramaController::class, 'getJson'])
        ->name('cursos.programa.json');
    Route::get('cursos/{curso}/programa', [ProgramaController::class, 'show'])
        ->name('cursos.programa.show');
    // Asistente de syllabus: pantalla propia, ya no un modal sobre el visor.
    // `?tipo=BASICO|COMPLETO` elige el tipo al crear o al promover un básico.
    Route::get('cursos/{curso}/programa/editar', [ProgramaController::class, 'edit'])
        ->name('cursos.programa.edit');
    Route::delete('cursos/{curso}/programa', [ProgramaController::class, 'destroy'])
        ->name('cursos.programa.destroy');
    // Estado transitions: docente marks basic as done, or sends complete for review
    Route::put('cursos/{curso}/programa/completar-basico', [ProgramaController::class, 'completarBasico'])
        ->name('cursos.programa.completar-basico');
    Route::put('cursos/{curso}/programa/enviar', [ProgramaController::class, 'enviarParaRevision'])
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
        Route::get('dashboard', [App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');
        Route::get('cursos', [CourseController::class, 'index'])->name('cursos.index');

        // Programa (Syllabus) View - MUST be before generic {curso} route
        Route::get('cursos/{curso}/programa', [App\Http\Controllers\Student\ProgramaController::class, 'show'])->name('cursos.programa.show');
        Route::get('cursos/{curso}', [CourseController::class, 'show'])->name('cursos.show');
        // Route::get('cursos/{curso}/actividad', [\App\Http\Controllers\Student\ActivityController::class, 'show'])->name('cursos.actividades.show');
        Route::get('cursos/{curso}/actividad/{actividad}', [ActivityController::class, 'show'])
            ->name('cursos.actividades.show');
        Route::get('cursos/{curso}/actividades/{actividad}/enunciado/descargar', [ActivityController::class, 'descargarEnunciado'])
            ->name('cursos.actividades.enunciado.descargar');
        Route::get('cursos/{curso}/actividades/{actividad}/entregas/{agenda}/descargar', [ActivityController::class, 'descargarEntrega'])
            ->name('cursos.actividades.entregas.descargar');

        // Agenda routes
        Route::controller(AgendaController::class)
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
                    [AgendaController::class, 'storeEntrega']
                )->name('actividades.agenda.storeEntrega');
            });

        // Mensajería por componente (curso.mensaje) — avisos del equipo docente
        // y la conversación del alumno con ese equipo. Se entra desde el curso y
        // sólo muestra ese curso. No pasa por agenda.agenda.
        Route::get('cursos/{curso}/mensajeria', [App\Http\Controllers\Student\MensajeriaController::class, 'index'])->name('cursos.mensajeria.index');
        Route::post('cursos/{curso}/mensajeria/componentes/{componente}/mensaje', [App\Http\Controllers\Student\MensajeriaController::class, 'enviar'])->name('cursos.mensajeria.enviar');
    });

// Ayudante Routes
Route::prefix('ayudante')->middleware(['auth', 'verified', 'is_ayudante'])->name('ayudante.')->group(function () {
    Route::get('dashboard', [App\Http\Controllers\Ayudante\DashboardController::class, 'index'])->name('dashboard');
    Route::get('cursos', [App\Http\Controllers\Ayudante\CourseController::class, 'index'])->name('cursos.index');

    // Programa (Syllabus) View - MUST be before generic {curso} route
    Route::get('cursos/{curso}/programa', [App\Http\Controllers\Ayudante\ProgramaController::class, 'show'])->name('cursos.programa.show');
    Route::get('cursos/{curso}/programa/create', [App\Http\Controllers\Ayudante\ProgramaController::class, 'create'])->name('cursos.programa.create');
    Route::get('cursos/{curso}/programa/editar', [App\Http\Controllers\Ayudante\ProgramaController::class, 'edit'])->name('cursos.programa.edit');
    Route::get('cursos/{curso}/programa/json', [AdminProgramaController::class, 'getJson'])->name('cursos.programa.json');
    Route::post('cursos/{curso}/programa', [App\Http\Controllers\Ayudante\ProgramaController::class, 'update'])->name('cursos.programa.update');

    // JSON endpoints used by SyllabusModal wizard
    Route::get('cursos/{curso}/componentes', [AdminSeccionController::class, 'indexByCurso'])->name('cursos.componentes.index');
    Route::get('cursos/{curso}/actividades/json', [DocenteActivityController::class, 'actividadesJson'])->name('cursos.actividades.json');

    // Mensajería por componente — misma bandeja que el docente, acotada al curso
    // desde el que se entra y sólo si el usuario tiene ahí el rol Ayudante.
    Route::get('cursos/{curso}/mensajeria', [App\Http\Controllers\Ayudante\MensajeriaController::class, 'index'])->name('cursos.mensajeria.index');
    Route::post('cursos/{curso}/mensajeria/componentes/{componente}/difusion', [App\Http\Controllers\Ayudante\MensajeriaController::class, 'enviarDifusion'])->name('cursos.mensajeria.difusion');
    Route::post('cursos/{curso}/mensajeria/componentes/{componente}/alumnos/{alumno}/mensaje', [App\Http\Controllers\Ayudante\MensajeriaController::class, 'enviarMensaje'])->name('cursos.mensajeria.mensaje');

    Route::get('cursos/{curso}', [App\Http\Controllers\Ayudante\CourseController::class, 'show'])->name('cursos.show');
});

// API Routes for AJAX/Fetch calls
Route::prefix('api')->middleware(['auth', 'verified'])->group(function () {
    Route::get('docentes', [CursoController::class, 'getDocentes']);
});

require __DIR__ . '/settings.php';
