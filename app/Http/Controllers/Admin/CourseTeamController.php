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

/**
 * Controlador para la gestión del equipo de un curso.
 * 
 * Tablas implicadas:
 * - curso.curso: Cursos para los que se gestiona el equipo.
 * - usuario.usuario_rol_asignación: Asignaciones de roles a usuarios en contextos.
 * - usuario.usuario_permiso_especial: Permisos especiales de usuarios.
 * - usuario.rol: Roles disponibles (Docente, Ayudante, etc).
 * 
 * Permite agregar, modificar y eliminar miembros del equipo de un curso,
 * así como gestionar sus roles y permisos específicos.
 */
class CourseTeamController extends Controller
{
    private function ensureContext(Curso $curso)
    {
        if (!$curso->id_contexto || $curso->id_contexto == 1) {
            $nombreContexto = "Curso: " . $curso->cod_curso;
            $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                ['contexto_display' => $nombreContexto],
                ['descripcion' => 'Contexto para el curso ' . $curso->cod_curso]
            );
            $curso->update(['id_contexto' => $contexto->id_contexto]);
        }
    }



    /**
     * Obtiene el listado de miembros del equipo (docentes y administrativos) asignados a un curso.
     * 
     * Recupera todas las asignaciones de roles activas en el contexto del curso,
     * resuelve la información de usuario correspondiente (nombre completo, etc.),
     * y retorna un listado JSON estructurado con: id_usuario, nombre_completo, role_name, rut.
     * 
     * @param  Curso  $curso  Curso para el cual obtener los miembros del equipo
     * @return \Illuminate\Http\JsonResponse  JSON con array de miembros del equipo
     */
    public function index(Curso $curso)
    {
        $this->authorize('manageTeam', $curso);

        // Ensure context exists (Lazy Creation)
        $this->ensureContext($curso);

        // Get assignments in this context
        $assignments = UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->with(['rol'])
            ->get();

        // Transform for frontend
        $team = $assignments->map(function ($assignment) {
            // Get user directly by id_usuario
            $user = Usuario::find($assignment->id_usuario);

            \Log::info('Processing assignment', [
                'id_usuario' => $assignment->id_usuario,
                'user_found' => $user ? 'yes' : 'no',
                'user_data' => $user ? [
                    'id' => $user->id_usuario,
                    'nombre1' => $user->nombre1,
                    'apellido1' => $user->apellido1,
                    'rut' => $user->rut,
                    'has_docente' => $user->docente ? 'yes' : 'no',
                    'has_estudiante' => $user->estudiante ? 'yes' : 'no',
                ] : null
            ]);

            if (!$user) {
                \Log::warning('User not found for assignment', ['id_usuario' => $assignment->id_usuario]);
                return null; // Safety check
            }

            // Let's try to get a display name.
            $name = "Usuario " . $user->id_usuario; // Default fallback
            $rut = $user->rut ?? '';

            // Try to find specific profile
            if ($user->nombre1 && $user->apellido1) {
                // Use direct fields first (most reliable)
                $name = trim($user->nombre1 . ' ' . $user->apellido1);
            } elseif ($user->docente) {
                $name = $user->docente->nombre_completo;
            } elseif ($user->estudiante) {
                $name = $user->estudiante->nombre_completo;
            }

            return [
                'id_usuario' => $user->id_usuario,
                'nombre' => $name,
                'role_name' => $assignment->rol->nombre,
                'rut' => $rut
            ];
        })->filter(); // Remove nulls

        return response()->json($team->values());
    }

    /**
     * Agrega un nuevo miembro (usuario con rol específico) al equipo de un curso.
     * 
     * Valida que el usuario exista y el rol sea válido. Crea una nueva asignación de rol
     * en el contexto del curso con fechas planificadas (inicio inmediato, fin en 100 años).
     * Registra quién asignó el rol (usuario autenticado).
     * 
     * @param  Request  $request  Datos de la solicitud: id_usuario, role_name
     * @param  Curso    $curso    Curso al cual agregar el miembro
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de éxito
     */
    public function store(Request $request, Curso $curso)
    {
        $this->authorize('manageTeam', $curso);

        $validated = $request->validate([
            'id_usuario' => ['required', Rule::exists(Usuario::class, 'id_usuario')],
            'role_name' => ['required', 'string', Rule::exists(Rol::class, 'nombre')]
        ]);

        $this->ensureContext($curso);

        $rol = Rol::where('nombre', $validated['role_name'])->first();

        // Security Check: For now, just ensuring we don't overwrite existing
        // In future: Check if auth user has permission to assign this role.

        UsuarioRolAsignación::create([
            'id_usuario' => $validated['id_usuario'],
            'id_contexto' => $curso->id_contexto,
            'id_rol' => $rol->id_rol,
            'asignado_por' => (int) (auth()->id() ?? 1), // Fallback to ID 1 if auth fails (e.g. seeding/testing)
            'creado_por' => (int) (auth()->id() ?? 1),
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada' => now()->addYears(100),
            'esta_activo' => true,
            'fue_eliminado' => false,
            'fecha_creacion' => now(),
        ]);

        return back()->with('success', 'Miembro agregado exitosamente.');
    }

    /**
     * Remueve un miembro del equipo de un curso (marca su asignación como inactiva y eliminada).
     * 
     * Desactiva todas las asignaciones de rol activas del usuario en el contexto del curso,
     * registrando la fecha de eliminación real para auditoría. Mantiene registros históricos
     * mediante soft delete pattern (fue_eliminado = true).
     * 
     * @param  Curso    $curso    Curso del cual remover el miembro
     * @param  Usuario  $usuario  Usuario a remover del equipo
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de éxito
     */
    public function destroy(Curso $curso, Usuario $usuario)
    {
        $this->authorize('manageTeam', $curso);

        if (!$curso->id_contexto) {
            return back()->with('error', 'El curso no tiene un contexto asignado.');
        }

        UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->update([
                'esta_activo' => false,
                'fue_eliminado' => true,
                'fecha_fin_real' => now()
            ]);

        return back()->with('success', 'Miembro removido exitosamente.');
    }

    /**
     * Obtiene los permisos y roles asignados a un miembro específico en el contexto de un curso.
     * 
     * Recupera los roles activos del usuario en el curso y resuelve permisos especiales
     * derivados de su rol(es). Retorna estructura JSON con roles e permisos para frontend.
     * 
     * @param  Curso    $curso    Curso del cual obtener permisos
     * @param  Usuario  $usuario  Usuario cuya información de permisos se solicita
     * @return \Illuminate\Http\JsonResponse  JSON con roles activos y permisos especiales
     */
    public function getMemberPermissions(Curso $curso, Usuario $usuario)
    {
        $this->authorize('manageTeam', $curso);

        if (!$curso->id_contexto) {
            return response()->json(['roles' => [], 'special_permissions' => []]);
        }

        // ✅ Validar que usuario es miembro del equipo
        $isMember = UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Usuario no es miembro del equipo de este curso'], 404);
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
                // Determine relevant permissions by slug instead of module
                // e.g., only keep those starting with 'docencia' or 'ayudantia' if that was the intent
                // For now, just keep all or filter by slug prefix if needed.
                // Replicating previous logic 'Docencia', 'Ayudantía'
                // Assuming slugs might be 'docencia:...' or 'ayudantia:...'
                return str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
            });
        }

        // Just return the permissions flat -> Now grouped for frontend
        $availablePermissions = $availablePermissions->groupBy(fn() => 'General');

        return response()->json([
            'roles' => $roles,
            'special_permissions' => $special,
            'available_permissions' => $availablePermissions
        ]);
    }

    /**
     * Sincroniza (actualiza) los roles y permisos especiales de un miembro del equipo en el contexto del curso.
     * 
     * Realiza validaciones de seguridad RBAC: prohibe que un usuario modifique sus propios permisos,
     * restricciones basadas en tipo de usuario (docentes solo pueden asignar Ayudante/Estudiante),
     * y valida que los permisos a asignar estén disponibles para quien realiza la asignación.
     * Transaccional para consistencia.
     * 
     * @param  Request  $request  Datos de la solicitud: roles (array de ids), special_permissions (array)
     * @param  Curso    $curso    Curso cuyo equipo se actualiza
     * @param  Usuario  $usuario  Usuario cuyo roles y permisos se sincronizan
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de éxito o error
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

        // ✅ Validar que usuario es miembro del equipo
        $isMember = UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        if (!$isMember) {
            return back()->with('error', 'El usuario no es miembro del equipo de este curso.');
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

                UsuarioRolAsignación::where('id_usuario', $usuario->id_usuario)
                    ->where('id_contexto', $idContexto)
                    ->whereIn('id_rol', $allowedRoleIds)
                    ->where('esta_activo', true)
                    ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

                if (!empty($validated['roles'])) {
                    foreach ($validated['roles'] as $rolId) {
                        if (in_array($rolId, $allowedRoleIds)) {
                            UsuarioRolAsignación::updateOrCreate(
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
            } else {
                // For Full Admin: Sync all roles in this context
                UsuarioRolAsignación::where('id_usuario', $usuario->id_usuario)
                    ->where('id_contexto', $idContexto)
                    ->where('esta_activo', true)
                    ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

                if (!empty($validated['roles'])) {
                    foreach ($validated['roles'] as $rolId) {
                        UsuarioRolAsignación::updateOrCreate(
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
            foreach ($validated['special_permissions'] as $permId => $status) {
                $isObject = is_array($status);
                $allowed = $isObject ? ($status['allowed'] ?? null) : $status;
                $canDelegate = $isObject ? ($status['can_delegate'] ?? false) : false;

                // Sync all for admins, or restricted for docentes
                if (in_array($permId, $delegablePermIds)) {
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

            DB::commit();
            return back()->with('success', 'Permisos actualizados correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al sincronizar permisos: ' . $e->getMessage());
        }
    }

    /**
     * Busca usuarios con rol 'ayudante' disponibles para agregar al equipo del curso.
     * 
     * Filtra usuarios que:
     * - Tengan el rol 'ayudante' asignado en cualquier contexto
     * - No estén ya en el equipo del curso
     * - Coincidan con el término de búsqueda (nombre, apellido, RUT)
     * 
     * @param  Request  $request  Parámetros: search (término de búsqueda)
     * @param  Curso    $curso    Curso para el cual buscar ayudantes
     * @return \Illuminate\Http\JsonResponse  JSON con array de usuarios disponibles
     */
    public function searchAssistants(Request $request, Curso $curso)
    {
        try {
            $this->authorize('manageTeam', $curso);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $searchTerm = $request->input('search', '');

        if (strlen($searchTerm) < 3) {
            return response()->json([]);
        }

        try {
            // Obtener el ID del rol 'ayudante'
            $ayudanteRole = Rol::where('nombre', 'ayudante')->first();

            if (!$ayudanteRole) {
                return response()->json([]);
            }

            // Obtener IDs de usuarios que ya están en el equipo del curso
            $existingMemberIds = [];
            if ($curso->id_contexto) {
                $existingMemberIds = UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
                    ->where('esta_activo', true)
                    ->where('fue_eliminado', false)
                    ->pluck('id_usuario')
                    ->toArray();
            }

            // Buscar usuarios con rol 'ayudante'
            $usuariosConAyudante = UsuarioRolAsignación::where('id_rol', $ayudanteRole->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->whereNotIn('id_usuario', $existingMemberIds)
                ->pluck('id_usuario')
                ->unique();

            // Buscar en la tabla Usuario
            $usuarios = Usuario::whereIn('id_usuario', $usuariosConAyudante)
                ->where('esta_activo', true)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('nombre1', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('apellido1', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('apellido2', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('rut', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('username', 'ILIKE', "%{$searchTerm}%");
                })
                ->limit(10)
                ->get();

            // Formatear resultados
            $results = $usuarios->map(function ($user) {
                return [
                    'id_usuario' => $user->id_usuario,
                    'nombre1' => $user->nombre1,
                    'apellido1' => $user->apellido1,
                    'rut' => $user->rut,
                    'username' => $user->username,
                ];
            });

            return response()->json($results);
        } catch (\Exception $e) {
            \Log::error('Error searching assistants: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
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

        $specialQuery = UsuarioPermisoEspecial::where('id_usuario', $user->id_usuario)
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
