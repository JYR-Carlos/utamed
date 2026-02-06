<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\UsuarioRolAsignación;
use App\Models\Usuario\UsuarioPermisoEspecial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
        $user = auth()->user();
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
            ->with(['asignatura'])
            ->orderBy('fecha_inicio', 'desc')
            ->get()
            ->map(function ($curso) {
                // Verificar si existe algún programa para este curso
                $tienePrograma = DB::table('Programa')
                    ->where('id_curso', $curso->id_curso)
                    ->whereNull('fecha_eliminacion')
                    ->exists();

                return [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'asignatura_nombre' => $curso->asignatura?->nombre ?? 'N/A',
                    'cod_asignatura' => $curso->asignatura?->cod_asignatura ?? 'N/A',
                    'fecha_inicio' => $curso->fecha_inicio,
                    'fecha_fin' => $curso->fecha_fin,
                    'tiene_programa' => $tienePrograma,
                    'es_plantilla' => $curso->es_plantilla
                ];
            });

        // RBAC Data for modals - Docentes can only delegate specific roles and permissions they possess
        $availableRoles = Rol::whereIn('nombre', ['Ayudante', 'Estudiante'])->orderBy('nombre')->get();

        $delegablePerms = $this->getDelegablePermissions($user);
        // Only show 'Docencia' and 'Ayudantía' modules for Docentes (Business Rule)
        // Filter by slug prefix instead of module
        $availablePermissions = $delegablePerms->filter(function ($p) {
            return str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
        })->groupBy(fn() => 'General');

        return Inertia::render('docente/Cursos', [
            'cursos' => $cursos,
            'availableRoles' => $availableRoles,
            'availablePermissions' => $availablePermissions
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
            \Log::error('Error getting role permissions: ' . $e->getMessage());
            $rolePerms = collect();
        }

        // 2. Get delegable permissions from Special Permissions
        try {
            $specialQuery = UsuarioPermisoEspecial::where('id_usuario_recipiente', $user->id_usuario)
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
            \Log::error('Error getting special permissions: ' . $e->getMessage());
            $specialPerms = collect();
        }

        $allDelegable = $rolePerms->concat($specialPerms)->unique('id_permiso');

        // 3. Subtract explicit DENIES the user has in this context
        $deniedPermIds = UsuarioPermisoEspecial::where('id_usuario_recipiente', $user->id_usuario)
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
        $teacher = auth()->user();
        $delegablePerms = $this->getDelegablePermissions($teacher, $idContexto);

        // Filter by relevant modules for Docente view
        $available_permissions = $delegablePerms->filter(function ($p) {
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
        $adminId = auth()->id();
        /** @var Usuario $user */
        $user = auth()->user();

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
            UsuarioRolAsignación::where('id_usuario_recipiente', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->whereIn('id_rol', $allowedRoleIds)
                ->where('esta_activo', true)
                ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

            if (!empty($validated['roles'])) {
                foreach ($validated['roles'] as $rolId) {
                    // Only sync if it's an allowed role for this context management
                    if (in_array($rolId, $allowedRoleIds)) {
                        UsuarioRolAsignación::updateOrCreate(
                            [
                                'id_usuario_recipiente' => $usuario->id_usuario,
                                'id_contexto' => $idContexto,
                                'id_rol' => $rolId,
                                'id_usuario_asignador' => $adminId
                            ],
                            [
                                'fecha_inicio_planificada' => now(),
                                'fecha_fin_planificada' => now()->addYears(100),
                                'esta_activo' => true,
                                'fue_eliminado' => false,
                                'fecha_fin_real' => null,
                                'fecha_creacion' => now(),
                                'asignado_por' => (int) ($adminId ?? 1)
                            ]
                        );
                    }
                }
            }

            // 2. Sync Special Permissions
            // We only update permissions the docente is allowed to delegate.
            // Deactivate only DELEGABLE ones first?
            UsuarioPermisoEspecial::where('id_usuario_recipiente', $usuario->id_usuario)
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
                                'id_usuario_recipiente' => $usuario->id_usuario,
                                'id_contexto' => $idContexto,
                                'id_permiso' => $permId,
                                'id_usuario_asignador' => $adminId
                            ],
                            [
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
     */
    private function authorizeAccess(Curso $curso)
    {
        $user = auth()->user();
        if (!$user->docente || $curso->id_docente !== $user->docente->id_docente) {
            abort(403, 'No tienes permiso para gestionar este curso.');
        }
    }
}
