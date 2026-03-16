<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Contexto;
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

        // Obtener cursos a través de las secciones del docente
        // La relación es: Docente → Secciones → Cursos
        $cursos = Curso::join('curso.seccion', 'curso.curso.id_curso', '=', 'curso.seccion.id_curso')
            ->where('curso.seccion.id_docente', $user->docente->id_docente)
            ->distinct()
            ->select('curso.curso.*')
            ->with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera', 'secciones.inscripcionSecciones'])
            ->orderBy('curso.curso.fecha_inicio', 'desc')
            ->get()
            ->map(function ($curso) {
                // Verificar si existe algún programa para este curso
                $tienePrograma = \App\Models\Administrativo\Programa::where('id_curso', $curso->id_curso)
                    ->whereNull('fecha_eliminacion')
                    ->exists();

                // Determinar el semestre: usar semestre_real si existe, sino usar 1 como default
                $semestre = $curso->semestre_real ?? 1;

                // Calcular total de estudiantes inscritos en el curso (todas sus secciones)
                $totalEstudiantes = $curso->secciones->sum(function ($seccion) {
                    return $seccion->inscripcionSecciones->count();
                });

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
                    'total_estudiantes' => $totalEstudiantes
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

        // Cargar relaciones necesarias
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'secciones.tipoSeccion',
            'secciones.inscripcionSecciones'
        ]);

        // Calcular estadísticas
        $totalEstudiantes = $curso->secciones->reduce(function ($carry, $seccion) {
            return $carry + $seccion->inscripcionSecciones->count();
        }, 0);
        // Verificar si existe algún programa para este curso
        $tienePrograma = \App\Models\Administrativo\Programa::where('id_curso', $curso->id_curso)
            ->whereNull('fecha_eliminacion')
            ->exists();

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
                'asignatura' => [
                    'nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                    'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? 'N/A',
                    'descripcion' => $curso->asignacionPlan?->asignatura?->descripcion ?? '',
                ],
                'plan' => [
                    'nombre' => $curso->asignacionPlan?->plan?->nombre ?? 'N/A',
                    'carrera' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                ],
                'secciones' => $curso->secciones->map(function ($seccion) {
                    return [
                        'id_seccion' => $seccion->id_seccion,
                        'tipo' => $seccion->tipoSeccion?->nombre ?? 'N/A',
                        'total_estudiantes' => $seccion->inscripcionSecciones->count(),
                    ];
                }),
                'total_estudiantes' => $totalEstudiantes,
            ]
        ]);
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

        // ✅ Validar que usuario es miembro del equipo
        $isMember = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        if (!$isMember) {
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
                        $permiso = \App\Models\Usuario\Permiso::findOrFail($permId);
                        $durationDays = $status['duration_days'] ?? 365;
                        
                        $builder = $usuario->givePermission($permiso)
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
            return back()->with('error', 'Error al actualizar permisos: ' . $e->getMessage());
        }
    }

}
