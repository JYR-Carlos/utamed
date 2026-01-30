<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignación;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

use App\Models\Usuario\UsuarioPermisoEspecial;

class CourseTeamController extends Controller
{
    private function ensureContext(Curso $curso)
    {
        if (!$curso->id_contexto || $curso->id_contexto == 1) {
            $nombreContexto = "Curso: " . $curso->cod_curso;
            $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                ['nombre' => $nombreContexto],
                ['descripcion' => 'Contexto para el curso ' . $curso->cod_curso]
            );
            $curso->update(['id_contexto' => $contexto->id_contexto]);
        }
    }

    /**
     * Get team members for a specific curso.
     */
    public function index(Curso $curso)
    {
        // Ensure context exists (Lazy Creation)
        $this->ensureContext($curso);

        // Get assignments in this context
        $assignments = UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->with(['usuario', 'rol'])
            ->get();

        // Transform for frontend
        $team = $assignments->map(function ($assignment) {
            $user = $assignment->usuario;
            if (!$user)
                return null; // Safety check

            // Let's try to get a display name.
            $name = $user->nombre_usuario ?? "Usuario " . $user->id_usuario;
            $rut = $user->nombre_usuario; // Often RUT is username
            // Try to find specific profile
            if ($user->docente) {
                $name = $user->docente->nombre_completo;
            } elseif ($user->estudiante) {
                $name = $user->estudiante->nombre_completo;
            } elseif ($user->administrativo) {
                $name = $user->administrativo->nombre_completo;
            }

            return [
                'id_usuario' => $user->id_usuario,
                'nombre_completo' => $name,
                'role_name' => $assignment->rol->nombre,
                'rut' => $rut
            ];
        })->filter(); // Remove nulls

        return response()->json($team->values());
    }

    /**
     * Add a member to the team.
     */
    public function store(Request $request, Curso $curso)
    {
        $validated = $request->validate([
            'id_usuario' => ['required', Rule::exists(Usuario::class, 'id_usuario')],
            'role_name' => ['required', 'string', Rule::exists(Rol::class, 'nombre')]
        ]);

        $this->ensureContext($curso);

        $rol = Rol::where('nombre', $validated['role_name'])->first();

        // Security Check: For now, just ensuring we don't overwrite existing
        // In future: Check if auth user has permission to assign this role.

        UsuarioRolAsignación::create([
            'id_usuario_recipiente' => $validated['id_usuario'],
            'id_contexto' => $curso->id_contexto,
            'id_rol' => $rol->id_rol,
            'id_usuario_asignador' => auth()->id() ?? 1, // Fallback to ID 1 if auth fails (e.g. seeding/testing)
            'fecha_inicio_planificada' => now(),
            'esta_activo' => true,
            'fue_eliminado' => false
        ]);

        return back()->with('success', 'Miembro agregado exitosamente.');
    }

    public function destroy(Curso $curso, Usuario $usuario)
    {
        if (!$curso->id_contexto) {
            return back()->with('error', 'El curso no tiene un contexto asignado.');
        }

        UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario_recipiente', $usuario->id_usuario)
            ->update([
                'esta_activo' => false,
                'fue_eliminado' => true,
                'fecha_fin_real' => now()
            ]);

        return back()->with('success', 'Miembro removido exitosamente.');
    }

    /**
     * Get permission data for a team member in a course context.
     */
    public function getMemberPermissions(Curso $curso, Usuario $usuario)
    {
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

        // Fetch delegable perms for the AUTHENTICATED user in THIS context
        /** @var Usuario $currentUser */
        $currentUser = auth()->user();
        $delegablePerms = $this->getDelegablePermissions($currentUser, $idContexto);

        // Filter by relevant modules IF the user is a Docente (Business Rule)
        $isDocente = $currentUser && $currentUser->docente;

        $availablePermissions = $delegablePerms;
        if ($isDocente) {
            $availablePermissions = $availablePermissions->filter(function ($p) {
                return in_array($p->modulo, ['Docencia', 'Ayudantía']);
            });
        }
        $availablePermissions = $availablePermissions->groupBy('modulo');

        return response()->json([
            'roles' => $roles,
            'special_permissions' => $special,
            'available_permissions' => $availablePermissions
        ]);
    }

    /**
     * Sync permissions for a team member in a course context.
     */
    public function syncMemberPermissions(Request $request, Curso $curso, Usuario $usuario)
    {
        $validated = $request->validate([
            'roles' => 'array',
            'special_permissions' => 'array'
        ]);

        if (!$curso->id_contexto) {
            return back()->with('error', 'El curso no tiene un contexto configurado.');
        }

        $idContexto = $curso->id_contexto;
        $adminId = auth()->id();
        /** @var Usuario $currentUser */
        $currentUser = auth()->user();

        // Security: A user cannot modify their own permissions
        if ($usuario->id_usuario === $currentUser->id_usuario) {
            return back()->with('error', 'No puedes modificar tus propios permisos.');
        }

        // Get delegable permissions FOR THIS CONTEXT to validate
        $delegablePermIds = $this->getDelegablePermissions($currentUser, $idContexto)->pluck('id_permiso')->toArray();

        DB::beginTransaction();
        try {
            // 1. Sync Roles - Restricted if user is Docente
            $isDocente = $currentUser && $currentUser->docente;
            if ($isDocente) {
                $allowedRoleIds = Rol::whereIn('nombre', ['Ayudante', 'Estudiante'])->pluck('id_rol')->toArray();

                UsuarioRolAsignación::where('id_usuario_recipiente', $usuario->id_usuario)
                    ->where('id_contexto', $idContexto)
                    ->whereIn('id_rol', $allowedRoleIds)
                    ->where('esta_activo', true)
                    ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

                if (!empty($validated['roles'])) {
                    foreach ($validated['roles'] as $rolId) {
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
                                    'esta_activo' => true,
                                    'fue_eliminado' => false,
                                    'fecha_fin_real' => null
                                ]
                            );
                        }
                    }
                }
            } else {
                // For Full Admin: Sync all roles in this context
                UsuarioRolAsignación::where('id_usuario_recipiente', $usuario->id_usuario)
                    ->where('id_contexto', $idContexto)
                    ->where('esta_activo', true)
                    ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

                if (!empty($validated['roles'])) {
                    foreach ($validated['roles'] as $rolId) {
                        UsuarioRolAsignación::updateOrCreate(
                            [
                                'id_usuario_recipiente' => $usuario->id_usuario,
                                'id_contexto' => $idContexto,
                                'id_rol' => $rolId,
                                'id_usuario_asignador' => $adminId
                            ],
                            [
                                'fecha_inicio_planificada' => now(),
                                'esta_activo' => true,
                                'fue_eliminado' => false,
                                'fecha_fin_real' => null
                            ]
                        );
                    }
                }
            }

            // 2. Sync Special Permissions
            foreach ($validated['special_permissions'] as $permId => $status) {
                $isObject = is_array($status);
                $allowed = $isObject ? ($status['allowed'] ?? null) : $status;
                $canDelegate = $isObject ? ($status['can_delegate'] ?? false) : false;

                // Sync all for admins, or restricted for docentes
                if (in_array($permId, $delegablePermIds)) {
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
                            'fecha_fin_real' => null
                        ]
                    );
                }
            }

            DB::commit();
            return back()->with('success', 'Permisos actualizados correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al sincronizar permisos: ' . $e->getMessage());
        }
    }

    private function getDelegablePermissions(Usuario $user, ?int $idContexto = null)
    {
        // If it's a super admin, return all
        if (!$user->docente) {
            return \App\Models\Usuario\Permiso::all();
        }

        // Apply same docente logic as DocenteCursoController
        $roleQuery = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false);
        if ($idContexto)
            $roleQuery->where('id_contexto', $idContexto);

        $rolePerms = $roleQuery->with([
            'rol.permisos' => function ($query) {
                $query->wherePivot('puede_delegar_permisos', true);
            }
        ])
            ->get()
            ->pluck('rol.permisos')
            ->flatten()
            ->unique('id_permiso');

        $specialQuery = UsuarioPermisoEspecial::where('id_usuario_recipiente', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->where(function ($query) {
                $query->where('esta_permitido', true)->orWhereNull('esta_permitido');
            })
            ->where('puede_delegar', true);
        if ($idContexto)
            $specialQuery->where('id_contexto', $idContexto);

        $specialPerms = $specialQuery->with('permiso')->get()->pluck('permiso');

        return $rolePerms->concat($specialPerms)->unique('id_permiso')->values();
    }
}
