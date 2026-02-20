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

        // Obtener secciones del docente
        $secciones = Seccion::where('id_docente', $user->docente->id_docente)
            ->get();

        // Obtener ids de cursos únicos
        $cursoIds = $secciones->pluck('id_curso')->unique();

        // Consultar cursos con información adicional
        $cursos = Curso::whereIn('id_curso', $cursoIds)
            ->with(['asignacionPlan.asignatura'])
            ->orderBy('fecha_inicio', 'desc')
            ->get()
            ->map(function ($curso) {
                // Verificar si existe algún programa para este curso
                $tienePrograma = \App\Models\Administrativo\Programa::where('id_curso', $curso->id_curso)
                    ->whereNull('fecha_eliminacion')
                    ->exists();

                return [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                    'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? 'N/A',
                    'fecha_inicio' => $curso->fecha_inicio,
                    'fecha_fin' => $curso->fecha_fin,
                    'tiene_programa' => $tienePrograma,
                    'es_plantilla' => $curso->es_plantilla,
                    'semestre_real' => $curso->semestre_real
                ];
            });

        // Agrupar cursos por semestre
        $cursosPorSemestre = $cursos->groupBy('semestre_real');

        // RBAC Data for modals - Docentes can only delegate specific roles and permissions they possess
        $availableRoles = Rol::whereIn('nombre', ['Ayudante', 'Estudiante'])->orderBy('nombre')->get();

        $delegablePerms = $this->getDelegablePermissions($user);
        // Only show 'Docencia' and 'Ayudantía' modules for Docentes (Business Rule)
        // Filter by slug prefix instead of module
        $availablePermissions = $delegablePerms->filter(function (\App\Models\Usuario\Permiso $p) {
            return str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
        })->groupBy(fn() => 'General');

        return Inertia::render('docente/Cursos', [
            'cursosSemestre1' => $cursosPorSemestre->get(1, collect()),
            'cursosSemestre2' => $cursosPorSemestre->get(2, collect()),
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
        // Verificar que el docente tenga acceso al curso
        $this->authorizeAccess($curso);

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
        // 1. Get delegable permissions from Roles
        try {
            $roleAssignments = $user->rolesAsignados()
                ->where('esta_activo', true)
                ->where('fue_eliminado', false);

            if ($idContexto) {
                $roleAssignments = $roleAssignments->where('id_contexto', $idContexto);
            }

            $roleAssignments = $roleAssignments->get();

            $rolePerms = collect();
            foreach ($roleAssignments as $assignment) {
                $role = $assignment->rol;
                if ($role) {
                    // Get permisos for this role with delegation rights
                    $perms = $role->permisos()
                        ->wherePivot('puede_delegar_permisos', true)
                        ->get();

                    // Filter by slug
                    foreach ($perms as $perm) {
                        if (str_starts_with($perm->slug, 'actividad:') || str_starts_with($perm->slug, 'curso:')) {
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

            if ($idContexto) {
                $specialQuery->where('id_contexto', $idContexto);
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

        if ($idContexto) {
            $deniedPermIds->where('id_contexto', $idContexto);
        }

        $deniedPermIds = $deniedPermIds->pluck('id_permiso')->toArray();

        return $allDelegable->reject(function ($perm) use ($deniedPermIds) {
            return in_array($perm->id_permiso, $deniedPermIds);
        })->values();
    }

    /**
     * Get permission data for a team member in a course context.
     */
    public function getMemberPermissions(Curso $curso, Usuario $usuario)
    {
        // Security: Ensure the teacher owns the course
        $this->authorizeAccess($curso);

        if (!$curso->id_contexto) {
            return response()->json(['roles' => [], 'special_permissions' => []]);
        }

        $idContexto = $curso->id_contexto;

        $roles = $usuario->rolesAsignados()
            ->where('id_contexto', $idContexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->pluck('id_rol');

        $special = $usuario->permisosEspeciales()
            ->where('id_contexto', $idContexto)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->get(['id_permiso', 'esta_permitido', 'puede_delegar']);

        // Fetch delegable perms for the AUTHENTICATED teacher in THIS context (Issue 2)
        /** @var Usuario $teacher */
        $teacher = Auth::user();
        $delegablePerms = $this->getDelegablePermissions($teacher, $idContexto);

        // Filter by relevant modules for Docente view
        $available_permissions = $delegablePerms->filter(function (\App\Models\Usuario\Permiso $p) {
            return str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
        })->groupBy(fn() => 'General');

        return response()->json([
            'roles' => $roles,
            'special_permissions' => $special,
            'available_permissions' => $available_permissions
        ]);
    }

    /**
     * Sync permissions for a team member in a course context.
     */
    public function syncMemberPermissions(Request $request, Curso $curso, Usuario $usuario)
    {
        $this->authorizeAccess($curso);

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
            $allowedRoleNames = ['Ayudante', 'Estudiante'];
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
                        UsuarioRolAsignacion::updateOrCreate(
                            [
                                'id_usuario' => $usuario->id_usuario,
                                'id_contexto' => $idContexto,
                                'id_rol' => $rolId,
                            ],
                            [
                                'asignado_por' => (int) ($adminId ?? 1),
                                'creado_por' => (int) ($adminId ?? 1),
                                'fecha_inicio_planificada' => now(),
                                'fecha_fin_planificada' => now()->addYears(100),
                                'esta_activo' => true,
                                'fue_eliminado' => false,
                                'fecha_fin_real' => null,
                                'fecha_creacion' => now(),
                            ]
                        );
                    }
                }
            }

            // 2. Sync Special Permissions
            // We only update permissions the docente is allowed to delegate.
            // Deactivate only DELEGABLE ones first?
            UsuarioPermisoEspecial::where('id_usuario', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->whereIn('id_permiso', $delegablePermIds)
                ->where('esta_activo', true)
                ->update(['esta_activo' => false, 'fue_borrado' => true, 'fecha_fin_real' => now()]);

            if (!empty($validated['special_permissions'])) {
                foreach ($validated['special_permissions'] as $permId => $status) {
                    // Extra security check
                    if (!in_array($permId, $delegablePermIds)) {
                        continue;
                    }

                    $isObject = is_array($status);
                    $allowed = $isObject ? ($status['allowed'] ?? null) : $status;
                    // For now, docentes cannot GRANT delegation to others unless we want to allow hierarchical delegation.
                    // The user said "Para la vista de administrador... puede marcar la opción ¿Puede delegar permisos?".
                    // This implies teachers don't necessarily have this power over others unless they are admins.
                    // But if they HAVE it, they might want to delegate it.
                    // However, to keep it simple and follow the request:
                    $canDelegate = false; // Docentes can't grant delegation right now via this UI

                    if ($allowed !== null || $canDelegate) {
                        UsuarioPermisoEspecial::updateOrCreate(
                            [
                                'id_usuario' => $usuario->id_usuario,
                                'id_contexto' => $idContexto,
                                'id_permiso' => $permId,
                            ],
                            [
                                'creado_por' => $adminId,
                                'esta_permitido' => ($allowed === null) ? null : (bool) $allowed,
                                'puede_delegar' => (bool) $canDelegate,
                                'esta_activo' => true,
                                'fue_borrado' => false,
                                'fecha_fin_real' => null,
                                'fecha_fin_planificada' => now()->addYears(100),
                                'fecha_creacion' => now()
                            ]
                        );
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

    /**
     * Helper to authorize access to course management.
     * 
     * Verifica que el docente autenticado tenga al menos una sección asignada en el curso.
     */
    private function authorizeAccess(Curso $curso)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente asociado.');
        }

        // Verificar si el docente tiene alguna sección en este curso
        $tieneSeccion = Seccion::where('id_curso', $curso->id_curso)
            ->where('id_docente', $user->docente->id_docente)
            ->exists();

        if (!$tieneSeccion) {
            abort(403, 'No tienes permiso para gestionar este curso.');
        }
    }
}
