<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Services\Authorization\GlobalContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use \Illuminate\Support\Facades\Auth;

/**
 * Controlador para gestión de cursos desde perspectiva del docente.
 * 
 * Tablas implicadas:
 * - curso.curso: Cursos ofertados en periodos académicos
 * - curso.seccion: Secciones asignadas al docente responsable
 * - usuario.docente: Perfil docente del usuario autenticado
 * - usuario.usuario_rol_asignación: Roles asignados en contexto del curso
 * - usuario.usuario_permiso_especial: Permisos especiales en contexto del curso
 * - usuario.contexto: Contextos (cursos) donde se aplican roles y permisos
 * 
 * Permite al docente ver sus cursos asignados, gestionar equipos de cátedra,
 * asignar roles y permisos a otros docentes/ayudantes en sus cursos.
 */
class DocenteCursoController extends Controller
{
    /**
     * Muestra listado de cursos asociados al docente autenticado.
     * 
     * Obtiene todos los cursos donde el docente es responsable de alguna sección,
     * con información de asignatura, plan y carrera.
     * 
     * @return \Illuminate\Http\RedirectResponse|\Inertia\Response  Redirección si no es docente, o vista con cursos
     */
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();
        if (!$user->docente) {
            return redirect()->route('dashboard')->with('error', 'No tienes un perfil docente asociado.');
        }

        // Pre-cargar permisos agrupados por contexto (evita N+1 queries en el map)
        $allPermsGrouped = $user->getAllPermissionsGroupedByContext();

        // Obtener cursos donde el docente participa (titular o asignado por componente)
        $cursos = Curso::where(function ($q) use ($user) {
                $q->where('id_docente_titular', $user->docente->id_docente)
                    ->orWhereHas('componentes.docenteComponentes', function ($dq) use ($user) {
                        $dq->where('id_docente', $user->docente->id_docente);
                    });
            })
            ->with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera', 'inscripcionCursos'])
            ->orderBy('fecha_inicio', 'desc')
            ->get()
            ->map(function ($curso) use ($user, $allPermsGrouped) {
                // Verificar si existe algún programa para este curso
                $tienePrograma = \App\Models\Curso\Programa::where('id_curso', $curso->id_curso)
                    ->where('es_actual', true)
                    ->exists();

                // Determinar el semestre: usar semestre_real si existe, sino usar 1 como default
                $semestre = $curso->semestre_real ?? 1;

                // Calcular total de estudiantes inscritos en el curso
                $totalEstudiantes = $curso->inscripcionCursos->count();

                // Permisos granulares del docente en el contexto de este curso
                $esTitular = $curso->id_docente_titular === $user->docente->id_docente;
                $userPermissions = $esTitular
                    ? [] // El titular no necesita permisos explícitos — tiene acceso completo
                    : ($allPermsGrouped->get($curso->id_contexto) ?? collect([]))
                        ->map(fn($perm) => [
                            'id_permiso'    => $perm['id_permiso'],
                            'slug'          => $perm['slug'],
                            'esta_permitido' => (bool) $perm['esta_permitido'],
                        ])
                        ->values()
                        ->all();

                return [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                    'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? 'N/A',
                    'plan_nombre' => $curso->asignacionPlan?->plan?->nombre ?? 'N/A',
                    'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                    'fecha_inicio' => $curso->fecha_inicio,
                    'fecha_fin' => $curso->fecha_fin,
                    'tiene_programa' => $tienePrograma,
                    'es_plantilla' => $curso->es_plantilla,
                    'semestre_real' => $semestre,
                    'total_estudiantes' => $totalEstudiantes,
                    'es_titular_curso' => $esTitular,
                    'userPermissions' => $userPermissions,
                ];
            });

        // Agrupar cursos por semestre
        $cursosPorSemestre = $cursos->groupBy('semestre_real');

        // RBAC Data for modals - Docentes can only delegate specific roles and permissions they possess
        $availableRoles = Rol::whereIn('nombre', ['ayudante', 'estudiante'])->orderBy('nombre')->get();

        $delegablePerms = $this->getDelegablePermissions($user);
        // Only show 'Docencia' and 'Ayudantía' modules for Docentes (Business Rule)
        // Filter by slug prefix instead of module
        $availablePermissions = $delegablePerms->filter(function (\App\Models\Usuario\Permiso $p) {
            return str_starts_with($p->slug, 'cursos') || str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
        })->groupBy(fn() => 'Docencia');

        return Inertia::render('docente/Cursos', [
            'cursosSemestre1' => $cursosPorSemestre->get(1, collect())->values()->toArray(),
            'cursosSemestre2' => $cursosPorSemestre->get(2, collect())->values()->toArray(),
            'availableRoles' => $availableRoles,
            'availablePermissions' => $availablePermissions
        ]);
    }

    /**
     * Muestra información detallada de un curso específico.
     * 
     * @param Curso $curso
     * @return \Inertia\Response
     */
    public function show(Curso $curso)
    {
        // Verificar que el docente tenga acceso al curso usando la Policy
        $this->authorize('viewPrograma', $curso);

        /** @var Usuario $user */
        $user = Auth::user();
        $idDocente = $user->docente->id_docente;

        // Cargar relaciones necesarias
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'inscripcionCursos',
        ]);

        // Calcular estadísticas
        $totalEstudiantes = $curso->inscripcionCursos->count();
        // Verificar si existe algún programa para este curso
        $tienePrograma = \App\Models\Curso\Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->exists();

        // --- Perspectiva de "mi grupo" ---
        $esTitularCurso = $curso->id_docente_titular === $idDocente;

        // Componentes donde el docente está asignado
        $misComponentes = $curso->componentes()
            ->whereHas('docenteComponentes', fn ($q) => $q->where('id_docente', $idDocente))
            ->with([
                'tipoComponente',
                'docenteComponentes' => fn ($q) => $q->orderBy('id_docente_componente'),
                'docenteComponentes.docente.usuario',
            ])
            ->get();

        // Estudiantes inscritos en esos componentes
        $misEstudiantes = \App\Models\Curso\InscripcionComponente::whereIn(
                'id_componente',
                $misComponentes->pluck('id_componente')
            )
            ->with(['estudiante.usuario', 'componente.tipoComponente'])
            ->get()
            ->map(fn ($ic) => [
                'id_inscripcion_componente' => $ic->id_inscripcion_componente,
                'id_componente'             => $ic->id_componente,
                'tipo_componente'           => $ic->componente->tipoComponente->tipo ?? 'N/A',
                'nota_componente'           => $ic->nota_componente,
                'estudiante' => [
                    'id_estudiante' => $ic->estudiante->id_estudiante,
                    'nombre'        => trim(
                        ($ic->estudiante->usuario->nombre1  ?? '') . ' ' .
                        ($ic->estudiante->usuario->nombre2  ?? '') . ' ' .
                        ($ic->estudiante->usuario->apellido1 ?? '') . ' ' .
                        ($ic->estudiante->usuario->apellido2 ?? '')
                    ),
                    'username'      => $ic->estudiante->usuario->username ?? '',
                ],
            ]);

        $misComponentesData = $misComponentes->map(fn ($c) => [
            'id_componente'   => $c->id_componente,
            'tipo_componente' => $c->tipoComponente->tipo ?? 'N/A',
            'es_titular'      => $c->docenteComponentes->first()?->es_titular ?? false,
            'total_docentes'  => $c->docenteComponentes->count(),
            'total_estudiantes' => $misEstudiantes->where('id_componente', $c->id_componente)->count(),
        ]);

        // --- Datos adicionales para el titular ---
        $todosComponentesData = collect();

        if ($esTitularCurso) {
            // Todos los componentes del curso con sus docentes
            $todosComponentes = $curso->componentes()
                ->with([
                    'tipoComponente',
                    'docenteComponentes' => fn ($q) => $q->orderBy('es_titular', 'desc'),
                    'docenteComponentes.docente.usuario',
                    'inscripcionComponentes',
                ])
                ->get();

            $todosComponentesData = $todosComponentes->map(fn ($c) => [
                'id_componente'     => $c->id_componente,
                'tipo_componente'   => $c->tipoComponente->tipo ?? 'N/A',
                'total_estudiantes' => $c->inscripcionComponentes->count(),
                'docentes' => $c->docenteComponentes->map(fn ($dc) => [
                    'id_docente_componente' => $dc->id_docente_componente,
                    'id_docente'            => $dc->id_docente,
                    'id_usuario'            => $dc->docente?->usuario?->id_usuario,
                    'nombre'                => trim(
                        ($dc->docente?->usuario?->nombre1 ?? '') . ' ' .
                        ($dc->docente?->usuario?->nombre2 ?? '') . ' ' .
                        ($dc->docente?->usuario?->apellido1 ?? '') . ' ' .
                        ($dc->docente?->usuario?->apellido2 ?? '')
                    ),
                    'es_titular' => $dc->es_titular,
                ])->values(),
            ]);

        }

        // Permisos granulares del docente en el contexto de este curso
        $userPermissions = $esTitularCurso
            ? [] // El titular tiene acceso completo — no necesita permisos explícitos
            : collect($user->getAllPermissions($curso->id_contexto))
                ->map(fn($perm) => [
                    'id_permiso'    => $perm['id_permiso'],
                    'slug'          => $perm['slug'],
                    'esta_permitido' => (bool) $perm['esta_permitido'],
                ])
                ->values()
                ->all();

        // Actividades del curso (todas las componentes) para el kanban de seguimiento
        $actividades = \App\Models\Agenda\Actividad::whereHas(
                'componente',
                fn ($q) => $q->where('id_curso', $curso->id_curso)
            )
            ->with(['componente.tipoComponente'])
            ->get()
            ->map(fn ($a) => [
                'id_actividad'   => $a->id_actividad,
                'nombre'         => $a->nombre,
                'fecha_limite'   => $a->fecha_limite,
                'tipo_actividad' => $a->tipo_actividad,
                'tipo_entrega'   => $a->tipo_entrega,
                'es_grupal'      => $a->es_grupal,
                'max_integrantes' => $a->max_integrantes,
                'visible'        => $a->visible,
                'id_componente'  => $a->id_componente,
                'componente'     => $a->componente ? [
                    'id_componente'  => $a->componente->id_componente,
                    'tipo_componente' => $a->componente->tipoComponente ? [
                        'nombre' => $a->componente->tipoComponente->tipo,
                    ] : null,
                ] : null,
            ]);

        return Inertia::render('docente/CursoDetalle', [
            'curso' => [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
                'agno_real' => $curso->agno_real,
                'semestre_real' => $curso->semestre_real,
                'estado_interno' => $curso->estado_interno,
                'es_plantilla' => $curso->es_plantilla,
                'tiene_programa' => $tienePrograma,
                'es_titular_curso' => $esTitularCurso,
                'id_docente_titular' => $curso->id_docente_titular,
                'userPermissions' => $userPermissions,
                'asignatura' => [
                    'nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                    'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? 'N/A',
                    'descripcion' => $curso->asignacionPlan?->asignatura?->descripcion ?? '',
                ],
                'plan' => [
                    'nombre' => $curso->asignacionPlan?->plan?->nombre ?? 'N/A',
                    'carrera' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                ],
                'secciones' => [],
                'total_estudiantes' => $totalEstudiantes,
            ],
            'mis_componentes' => $misComponentesData->values(),
            'mis_estudiantes' => $misEstudiantes->values(),
            'todos_componentes' => $todosComponentesData->values(),
            'actividades' => $actividades->values(),
            'mensajesEstudiante' => Inertia::lazy(function () use ($curso) {
                $idEstudiante = request('estudiante_id');
                if (!$idEstudiante) return [];

                $gruposIds = DB::table('agenda.integrante_grupo as ig')
                    ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'ig.id_actividad_asignada_grupo')
                    ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
                    ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
                    ->where('ig.id_estudiante', $idEstudiante)
                    ->where('c.id_curso', $curso->id_curso)
                    ->pluck('ig.id_actividad_asignada_grupo');

                if ($gruposIds->isEmpty()) {
                    return [];
                }

                return DB::table('agenda.agenda as a')
                    ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
                    ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
                    ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
                    ->whereIn('a.id_actividad_asignada_grupo', $gruposIds)
                    ->whereIn('a.tipo_mensaje', ['Mensaje al profesor', 'Feedback'])
                    ->orderBy('a.fecha_envio', 'asc')
                    ->select(
                        'a.id_agenda',
                        'a.fecha_envio',
                        'a.mensaje',
                        'a.tipo_mensaje as tipo_registro',
                        DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as emisor_nombre"),
                        'u.id_usuario as emisor_id_usuario',
                        'act.nombre as actividad_nombre',
                        'act.id_actividad',
                        'aag.id_actividad_asignada_grupo as grupo'
                    )
                    ->get();
            }),
        ]);
    }

    /**
     * Muestra la página de gestión de docentes del curso (solo titular).
     *
     * Incluye la lista de componentes con sus docentes asignados y la
     * matriz de permisos sobre el syllabus/programa de cada colegiado.
     */
    public function docentes(Curso $curso)
    {
        $this->authorize('manageTeam', $curso);

        /** @var Usuario $user */
        $user = Auth::user();
        $idDocente = $user->docente->id_docente;

        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
        ]);

        // Todos los componentes con docentes
        $todosComponentes = $curso->componentes()
            ->with([
                'tipoComponente',
                'docenteComponentes' => fn ($q) => $q->orderBy('es_titular', 'desc'),
                'docenteComponentes.docente.usuario',
                'inscripcionComponentes',
            ])
            ->get();

        $todosComponentesData = $todosComponentes->map(fn ($c) => [
            'id_componente'     => $c->id_componente,
            'tipo_componente'   => $c->tipoComponente->tipo ?? 'N/A',
            'total_estudiantes' => $c->inscripcionComponentes->count(),
            'docentes' => $c->docenteComponentes->map(fn ($dc) => [
                'id_docente_componente' => $dc->id_docente_componente,
                'id_docente'            => $dc->id_docente,
                'id_usuario'            => $dc->docente?->usuario?->id_usuario,
                'nombre'                => trim(
                    ($dc->docente?->usuario?->nombre1 ?? '') . ' ' .
                    ($dc->docente?->usuario?->nombre2 ?? '') . ' ' .
                    ($dc->docente?->usuario?->apellido1 ?? '') . ' ' .
                    ($dc->docente?->usuario?->apellido2 ?? '')
                ),
                'es_titular' => $dc->es_titular,
            ])->values(),
        ]);

        // Colegiados (excluyendo al titular del curso)
        $colegiadosData = DocenteComponente::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
            ->with('docente.usuario')
            ->get()
            ->map(fn ($dc) => [
                'id_docente'  => $dc->id_docente,
                'id_usuario'  => $dc->docente?->usuario?->id_usuario,
                'nombre'      => trim(
                    ($dc->docente?->usuario?->nombre1 ?? '') . ' ' .
                    ($dc->docente?->usuario?->nombre2 ?? '') . ' ' .
                    ($dc->docente?->usuario?->apellido1 ?? '') . ' ' .
                    ($dc->docente?->usuario?->apellido2 ?? '')
                ),
                'username'    => $dc->docente?->usuario?->username ?? '',
                'es_titular'  => $dc->es_titular,
            ])
            ->filter(fn ($d) => $d['id_docente'] !== $idDocente)
            ->unique('id_docente')
            ->values()
            ->map(function ($col) use ($curso) {
                if ($curso->id_contexto) {
                    $special = \App\Models\Usuario\UsuarioPermisoEspecial::where('id_contexto', $curso->id_contexto)
                        ->where('id_usuario', $col['id_usuario'])
                        ->where('esta_activo', true)
                        ->where('fue_borrado', false)
                        ->get();
                    $col['special_permissions'] = $special->map(fn($perm) => [
                        'id_permiso' => $perm->id_permiso,
                        'esta_permitido' => $perm->esta_permitido,
                        'puede_delegar' => $perm->puede_delegar
                    ])->values();
                } else {
                    $col['special_permissions'] = [];
                }
                return $col;
            });

        // Matriz de permisos del syllabus por cada colegiado
        $syllabusPermsMatrix = $this->buildSyllabusPermissionsMatrix($curso, $colegiadosData);

        $delegablePerms = $this->getDelegablePermissions($user, $curso->id_contexto);
        $available_permissions = $delegablePerms->filter(function (\App\Models\Usuario\Permiso $p) {
            return str_starts_with($p->slug, 'cursos') || str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
        })->groupBy(fn() => 'Docencia');

        return Inertia::render('docente/DocentesCurso', [
            'curso' => [
                'id_curso'    => $curso->id_curso,
                'nombre'      => $curso->nombre,
                'cod_curso'   => $curso->cod_curso,
                'asignatura'  => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            ],
            'todos_componentes' => $todosComponentesData->values(),
            'colegiados'        => $colegiadosData->values(),
            'syllabus_matrix'   => $syllabusPermsMatrix,
            'available_permissions' => $available_permissions,
        ]);
    }

    /**
     * Construye la matriz de permisos del syllabus para cada colegiado:
     * { permisos: [{slug, nombre}], docentes: [{id_usuario, perms: {slug: bool}}] }
     */
    private function buildSyllabusPermissionsMatrix(Curso $curso, $colegiados): array
    {
        // Permisos relevantes del syllabus
        $syllabusSlugs = Permiso::where('slug', 'like', 'cursos/programas%')
            ->orderBy('slug')
            ->get();

        $permisosList = $syllabusSlugs->map(fn ($p) => [
            'id_permiso' => $p->id_permiso,
            'slug'       => $p->slug,
            'nombre'     => $p->nombre,
        ])->values();

        if (!$curso->id_contexto || $colegiados->isEmpty()) {
            return ['permisos' => $permisosList, 'docentes' => []];
        }

        $idContexto = $curso->id_contexto;
        $permisoIds = $syllabusSlugs->pluck('id_permiso')->toArray();

        // Consultar permisos especiales activos de cada colegiado en el contexto del curso
        $docentesPerms = [];
        foreach ($colegiados as $col) {
            $activePerms = UsuarioPermisoEspecial::where('id_usuario', $col['id_usuario'])
                ->where('id_contexto', $idContexto)
                ->whereIn('id_permiso', $permisoIds)
                ->where('esta_activo', true)
                ->where('fue_borrado', false)
                ->get()
                ->keyBy('id_permiso');

            $permsMap = [];
            foreach ($syllabusSlugs as $p) {
                $upe = $activePerms->get($p->id_permiso);
                $permsMap[$p->slug] = $upe ? (bool) $upe->esta_permitido : false;
            }

            $docentesPerms[] = [
                'id_usuario' => $col['id_usuario'],
                'nombre'     => $col['nombre'],
                'perms'      => $permsMap,
            ];
        }

        return [
            'permisos' => $permisosList,
            'docentes' => $docentesPerms,
        ];
    }


    /**
     * Get permissions the user is allowed to delegate to others.
     */
    private function getDelegablePermissions(Usuario $user, ?int $idContexto = null)
    {
        $effectiveContextIds = $this->getDelegablePermissionContextIds($idContexto);

        // 1. Get delegable permissions from Roles
        try {
            // Get all roles for this user (regardless of context)
            // Teachers should be able to delegate permissions they possess globally
            $roleAssignments = $user->rolesAsignados()
                ->where('usuario.usuario_rol_asignacion.esta_activo', true)
                ->where('usuario.usuario_rol_asignacion.fue_eliminado', false)
                ->get();

            $rolePerms = collect();
            foreach ($roleAssignments as $role) {
                /** @var \App\Models\Usuario\Rol $role */
                // $role is already a Rol model (from belongsToMany)
                if ($role) {
                    // Get permisos for this role with delegation rights
                    $perms = $role->permisos()
                        ->wherePivot('puede_delegar_permisos', true)
                        ->get();

                    // Filter by slug - include 'cursos' prefix for course/activity management
                    foreach ($perms as $perm) {
                        if (str_starts_with($perm->slug, 'cursos') || str_starts_with($perm->slug, 'actividad:') || str_starts_with($perm->slug, 'curso:')) {
                            $rolePerms->push($perm);
                        }
                    }
                }
            }
            $rolePerms = $rolePerms->unique('id_permiso');
        } catch (\Exception $e) {
            Log::error('Error getting role permissions: ' . $e->getMessage());
            $rolePerms = collect();
        }

        // 2. Get delegable permissions from Special Permissions
        try {
            $specialQuery = UsuarioPermisoEspecial::where('id_usuario', $user->id_usuario)
                ->where('esta_activo', true)
                ->where('fue_borrado', false)
                ->where(function ($query) {
                    $query->where('esta_permitido', true)
                        ->orWhereNull('esta_permitido');
                })
                ->where('puede_delegar', true);

            if ($effectiveContextIds !== null) {
                $specialQuery->whereIn('id_contexto', $effectiveContextIds);
            }

            $assignments = $specialQuery->get();
            $specialPerms = collect();

            foreach ($assignments as $assignment) {
                $perm = $assignment->permiso;
                if ($perm && (str_starts_with($perm->slug, 'actividad:') || str_starts_with($perm->slug, 'curso:'))) {
                    $specialPerms->push($perm);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error getting special permissions: ' . $e->getMessage());
            $specialPerms = collect();
        }

        $allDelegable = $rolePerms->concat($specialPerms)->unique('id_permiso');

        // 3. Subtract explicit DENIES the user has in this context
        $deniedPermIds = UsuarioPermisoEspecial::where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->where('esta_permitido', false);

        if ($effectiveContextIds !== null) {
            $deniedPermIds->whereIn('id_contexto', $effectiveContextIds);
        }

        $deniedPermIds = $deniedPermIds->pluck('id_permiso')->toArray();

        return $allDelegable->reject(function ($perm) use ($deniedPermIds) {
            return in_array($perm->id_permiso, $deniedPermIds);
        })->values();
    }

    private function getDelegablePermissionContextIds(?int $idContexto): ?array
    {
        if (!$idContexto) {
            return null;
        }

        try {
            $globalContextId = app(GlobalContextService::class)->getContextId();
            return array_values(array_unique([$idContexto, $globalContextId]));
        } catch (\Throwable $e) {
            Log::warning('No se pudo resolver contexto global para permisos delegables', [
                'id_contexto' => $idContexto,
                'error' => $e->getMessage(),
            ]);

            return [$idContexto];
        }
    }

    /**
     * Get permission data for a team member in a course context.
     */
    public function getMemberPermissions(Curso $curso, Usuario $usuario)
    {
        // Security: Ensure the teacher owns the course
        $this->authorize('manageTeam', $curso);

        if (!$curso->id_contexto) {
            return response()->json(['roles' => [], 'special_permissions' => []]);
        }

        // ✅ Validar que usuario es miembro del equipo o docente colegiado del curso
        $isMember = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        $isColegiadoDocente = DocenteComponente::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
            ->whereHas('docente', fn ($q) => $q->where('id_usuario', $usuario->id_usuario))
            ->exists();

        if (!$isMember && !$isColegiadoDocente) {
            return response()->json(['error' => 'Usuario no es miembro del equipo de este curso'], 404);
        }

        $idContexto = $curso->id_contexto;

        $roles = $usuario->rolesAsignados()
            ->where('usuario.usuario_rol_asignacion.id_contexto', $idContexto)
            ->where('usuario.usuario_rol_asignacion.esta_activo', true)
            ->where('usuario.usuario_rol_asignacion.fue_eliminado', false)
            ->get()
            ->pluck('id_rol');

        $special = $usuario->permisosEspeciales()
            ->where('usuario.usuario_permiso_especial.id_contexto', $idContexto)
            ->where('usuario.usuario_permiso_especial.esta_activo', true)
            ->where('usuario.usuario_permiso_especial.fue_borrado', false)
            ->get();

        // Fetch delegable perms for the AUTHENTICATED teacher in THIS context (Issue 2)
        /** @var Usuario $teacher */
        $teacher = Auth::user();
        $delegablePerms = $this->getDelegablePermissions($teacher, $idContexto);

        // Filter by relevant modules for Docente view
        $available_permissions = $delegablePerms->filter(function (\App\Models\Usuario\Permiso $p) {
            return str_starts_with($p->slug, 'cursos') || str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
        })->groupBy(fn() => 'Docencia');

        // ✅ Return available roles for teacher (only ayudante and estudiante)
        $available_roles = Rol::whereIn('nombre', ['ayudante', 'estudiante'])
            ->orderBy('nombre')
            ->get()
            ->map(fn($rol) => [
                'id_rol' => $rol->id_rol,
                'nombre' => $rol->nombre
            ])
            ->values();

        // Transform special permissions to include only necessary fields
        $specialPermissionsFormatted = $special->map(function ($perm) {
            return [
                'id_permiso' => $perm->id_permiso,
                'esta_permitido' => $perm->esta_permitido,
                'puede_delegar' => $perm->puede_delegar
            ];
        })->values();

        return response()->json([
            'roles' => $roles,
            'special_permissions' => $specialPermissionsFormatted,
            'available_permissions' => $available_permissions,
            'available_roles' => $available_roles
        ]);
    }

    /**
     * Sync permissions for a team member in a course context.
     */
    public function syncMemberPermissions(Request $request, Curso $curso, Usuario $usuario)
    {
        $this->authorize('manageTeam', $curso);

        $validated = $request->validate([
            'roles' => 'array',
            'special_permissions' => 'array'
        ]);

        if (!$curso->id_contexto) {
            return back()->with('error', 'El curso no tiene un contexto configurado.');
        }

        $idContexto = $curso->id_contexto;
        $adminId = Auth::id();
        /** @var Usuario $user */
        $user = Auth::user();

        // Security: A user cannot modify their own permissions (Issue 3)
        if ($usuario->id_usuario === $user->id_usuario) {
            return back()->with('error', 'No puedes modificar tus propios permisos.');
        }

        // Validar que el usuario target es miembro del equipo o docente colegiado del curso
        $isMember = UsuarioRolAsignacion::where('id_contexto', $idContexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        $isColegiadoDocente = DocenteComponente::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
            ->whereHas('docente', fn ($q) => $q->where('id_usuario', $usuario->id_usuario))
            ->exists();

        if (!$isMember && !$isColegiadoDocente) {
            return back()->with('error', 'Usuario no es miembro del equipo de este curso.');
        }

        // Get delegable permissions FOR THIS CONTEXT to validate (Issue 2)
        $delegablePermIds = $this->getDelegablePermissions($user, $idContexto)->pluck('id_permiso')->toArray();

        DB::beginTransaction();
        try {
            // 1. Sync Roles - Docentes can only manage specific team roles
            $allowedRoleNames = ['ayudante', 'estudiante'];
            $allowedRoleIds = Rol::whereIn('nombre', $allowedRoleNames)->pluck('id_rol')->toArray();

            // Deactivate ONLY allowed roles first to avoid wiping things teachers shouldn't touch
            UsuarioRolAsignacion::where('id_usuario', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->whereIn('id_rol', $allowedRoleIds)
                ->where('esta_activo', true)
                ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

            if (!empty($validated['roles'])) {
                foreach ($validated['roles'] as $rolId) {
                    // Only sync if it's an allowed role for this context management
                    if (in_array($rolId, $allowedRoleIds)) {
                        $rol = Rol::findOrFail($rolId);
                        $usuario->giveRole($rol)
                            ->on($curso)  // Curso implementa HasOwnedContext
                            ->for(365)
                            ->save();
                    }
                }
            }

            // 2. Sync Special Permissions
            // Docentes can now delegate the permissions they possess to team members
            UsuarioPermisoEspecial::where('id_usuario', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->whereIn('id_permiso', $delegablePermIds)
                ->where('esta_activo', true)
                ->update(['esta_activo' => false, 'fue_borrado' => true, 'fecha_fin_real' => now()]);

            if (!empty($validated['special_permissions'])) {
                foreach ($validated['special_permissions'] as $permId => $status) {
                    // Extra security check: ensure teacher is delegating only their own permissions
                    if (!in_array($permId, $delegablePermIds)) {
                        continue;
                    }

                    $isObject = is_array($status);
                    $allowed = $isObject ? ($status['allowed'] ?? null) : $status;
                    // Docentes can now delegate permissions they possess to their team members
                    $canDelegate = false;

                    if ($allowed !== null || $canDelegate) {
                        $permisoModel = \App\Models\Usuario\Permiso::findOrFail($permId);
                        $permisoEnum = \App\Support\Permissions::from($permisoModel->slug);
                        $durationDays = $status['duration_days'] ?? 365;
                        
                        $builder = $usuario->givePermission($permisoEnum)
                            ->on($curso)  // Curso implementa HasOwnedContext
                            ->for($durationDays);
                        
                        if ($canDelegate) {
                            $builder->canDelegate();
                        }
                        
                        if ($allowed === false) {
                            $builder->revoke();
                        }
                        
                        $builder->save();
                    }
                }
            }

            DB::commit();
            return back()->with('success', 'Permisos del ayudante actualizados.');
        } catch (\Exception $e) {
            DB::rollBack();
            // B-10: no exponer el mensaje crudo de la excepción al usuario.
            Log::error('[DocenteCurso] Error al sincronizar permisos de miembro', [
                'id_curso'   => $curso->id_curso,
                'id_usuario' => $usuario->id_usuario,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'No se pudieron actualizar los permisos. Por favor, inténtalo nuevamente.');
        }
    }

}
