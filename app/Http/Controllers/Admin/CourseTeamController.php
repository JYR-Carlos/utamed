<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Services\Authorization\GlobalContextService;

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
        $assignments = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->with(['rol'])
            ->get();

        // Transform for frontend
        $team = $assignments->map(function ($assignment) {
            // Get user directly by id_usuario
            $user = Usuario::find($assignment->id_usuario);

            Log::info('Processing assignment', [
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
                Log::warning('User not found for assignment', ['id_usuario' => $assignment->id_usuario]);
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

        // Restricción por tipo de usuario que asigna:
        // - Docente: solo puede asignar rol 'ayudante'
        // - Admin: puede asignar cualquier rol excepto SuperAdmin
        /** @var Usuario $currentUser */
        $currentUser = Auth::user();
        $isDocente = $currentUser && $currentUser->docente;

        if ($isDocente) {
            $rolesPermitidos = ['ayudante'];
            if (!in_array(strtolower($validated['role_name']), $rolesPermitidos)) {
                return back()->with('error', 'Como docente solo puedes asignar el rol de Ayudante.');
            }
        } else {
            // Admin no puede asignar SuperAdmin
            $rolesProhibidos = ['superadmin', 'super admin'];
            if (in_array(strtolower($validated['role_name']), $rolesProhibidos)) {
                return back()->with('error', 'No puedes asignar el rol SuperAdmin.');
            }
        }

        $rol = Rol::where('nombre', $validated['role_name'])->first();

        // Check for existing assignment (including soft deleted)
        $existingAssignment = UsuarioRolAsignacion::where('id_usuario', $validated['id_usuario'])
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rol->id_rol)
            ->first();

        if ($existingAssignment) {
            // Reactivate existing assignment
            $existingAssignment->update([
                'esta_activo' => true,
                'fue_eliminado' => false,
                'fecha_fin_real' => null,
                'asignado_por' => (int) (Auth::id() ?? 1),
                'fecha_inicio_planificada' => now(),
                'fecha_fin_planificada' => now()->addDays(365),  // 365 días en lugar de 100 años
            ]);
        } else {
            // Create new assignment using RoleAssignmentBuilder
            $usuarioTarget = Usuario::findOrFail($validated['id_usuario']);
            
            $usuarioTarget->giveRole($rol)
                ->on($curso)       // ← Curso implementa HasOwnedContext
                ->for(365)         // ← 365 días
                ->save();
        }

        return back()->with('success', 'Miembro agregado exitosamente.');
    }

    /**
     * Remueve un miembro del equipo de un curso (marca su asignación como inactiva y eliminada).
     * 
     * Si se pasa `role_name` en el cuerpo de la petición, elimina solo esa asignación de rol.
     * Si no se pasa, elimina todas las asignaciones del usuario en el contexto (comportamiento legacy).
     * Registra la fecha de eliminación real para auditoría (soft delete pattern).
     * 
     * @param  Request  $request  Opcional: role_name del rol a eliminar
     * @param  Curso    $curso    Curso del cual remover el miembro
     * @param  Usuario  $usuario  Usuario a remover del equipo
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de éxito
     */
    public function destroy(Request $request, Curso $curso, Usuario $usuario)
    {
        $this->authorize('manageTeam', $curso);

        if (!$curso->id_contexto) {
            return back()->with('error', 'El curso no tiene un contexto asignado.');
        }

        $query = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false);

        // Si se especifica un rol concreto, solo se elimina esa asignación
        $roleName = $request->input('role_name');
        if ($roleName) {
            $rol = Rol::where('nombre', $roleName)->first();
            if ($rol) {
                $query->where('id_rol', $rol->id_rol);
            }
        }

        $query->update([
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
        $isMember = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Usuario no es miembro del equipo de este curso'], 404);
        }

        $idContexto = $curso->id_contexto;

        // Get roles assigned to this user in this context
        $roles = $usuario->rolesAsignados()
            ->where('usuario.usuario_rol_asignacion.id_contexto', $idContexto)
            ->where('usuario.usuario_rol_asignacion.esta_activo', true)
            ->where('usuario.usuario_rol_asignacion.fue_eliminado', false)
            ->get()
            ->pluck('id_rol');

        // ✅ FIXED: Query directly to UsuarioPermisoEspecial table to get special permissions
        // WITH correct schema qualification for PostgreSQL
        $special = UsuarioPermisoEspecial::where('usuario.usuario_permiso_especial.id_usuario', $usuario->id_usuario)
            ->where('usuario.usuario_permiso_especial.id_contexto', $idContexto)
            ->where('usuario.usuario_permiso_especial.esta_activo', true)
            ->where('usuario.usuario_permiso_especial.fue_borrado', false)
            ->select('usuario.usuario_permiso_especial.*')
            ->get();

        Log::info('📥 getMemberPermissions - Special permissions loaded:', [
            'user_id' => $usuario->id_usuario,
            'context_id' => $idContexto,
            'special_count' => $special->count(),
            'special_ids' => $special->pluck('id_permiso')->toArray()
        ]);

        // Load permissions user has through roles in THIS context
        $rolePermsInContext = collect();
        
        $userRolesInContext = UsuarioRolAsignacion::where('id_usuario', $usuario->id_usuario)
            ->where('id_contexto', $idContexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->with('rol.permisos')
            ->get();

        foreach ($userRolesInContext as $assignment) {
            foreach ($assignment->rol->permisos as $perm) {
                $rolePermsInContext->push($perm);
            }
        }
        
        $rolePermsInContext = $rolePermsInContext->unique('id_permiso')->values();

        // Fetch delegable perms for the AUTHENTICATED user in THIS context
        /** @var Usuario $currentUser */
        $currentUser = Auth::user();
        $delegablePerms = $this->getDelegablePermissions($currentUser, $idContexto);

        // Filter by relevant modules IF the user is a Docente (Business Rule)
        $isDocente = $currentUser && $currentUser->docente;

        $availablePermissions = $delegablePerms;
        if ($isDocente) {
            $availablePermissions = $availablePermissions->filter(function ($p) {
                return str_starts_with($p->slug, 'actividad:') || str_starts_with($p->slug, 'curso:');
            });
        }

        // Group permissions for frontend - use 'Docencia' module name so it passes PermissionsModal filters
        $availablePermissions = $availablePermissions->groupBy(fn() => 'Docencia');

        // ✅ Return available roles for the modal to display
        // For Docente: only Ayudante and Estudiante
        // For Admin: all roles
        $availableRoles = [];
        if ($isDocente) {
            $availableRoles = Rol::whereIn('nombre', ['ayudante', 'estudiante'])
                ->orderBy('nombre')
                ->get()
                ->map(fn($rol) => [
                    'id_rol' => $rol->id_rol,
                    'nombre' => $rol->nombre
                ])
                ->values();
        } else {
            // Admin can assign all roles except SuperAdmin
            $availableRoles = Rol::whereNotIn('nombre', ['SuperAdmin', 'Super Admin'])
                ->orderBy('nombre')
                ->get()
                ->map(fn($rol) => [
                    'id_rol' => $rol->id_rol,
                    'nombre' => $rol->nombre
                ])
                ->values();
        }

        // Transform special permissions to include only necessary fields
        // Return them as-is from the database (no consolidation)
        $specialPermissionsFormatted = $special->map(function ($perm) {
            return [
                'id_permiso' => $perm->id_permiso,
                'esta_permitido' => $perm->esta_permitido,
                'puede_delegar' => (bool) $perm->puede_delegar,  // Convert to boolean for frontend
                'can_delegate' => (bool) $perm->puede_delegar,  // Alias for frontend (PermissionsModal.svelte uses this name)
                'source' => 'special'  // Indicates it comes from special permissions (UPE)
            ];
        })->values();

        // Add permissions from roles (marked as role-based)
        $rolePermissionsFormatted = $rolePermsInContext->map(function ($perm) {
            return [
                'id_permiso' => $perm->id_permiso,
                'esta_permitido' => true,  // Role permissions are always allowed
                'puede_delegar' => (bool) ($perm->pivot?->puede_delegar_permisos ?? false),
                'can_delegate' => (bool) ($perm->pivot?->puede_delegar_permisos ?? false),  // Alias for frontend
                'source' => 'role'  // Indicates it comes from a role, not directly editable
            ];
        })->values();

        // Merge, but avoid duplicates (UPE/special overrides role)
        $allPermissions = $specialPermissionsFormatted->concat($rolePermissionsFormatted)
            ->unique('id_permiso')  // Keep UPE if exists, discard role equivalent
            ->values();

        Log::info('📤 getMemberPermissions response:', [
            'user_id' => $usuario->id_usuario,
            'context_id' => $idContexto,
            'special_permissions_count' => $specialPermissionsFormatted->count(),
            'special_permission_ids' => $specialPermissionsFormatted->pluck('id_permiso')->toArray(),
            'all_permissions_count' => $allPermissions->count(),
            'all_permission_ids' => $allPermissions->pluck('id_permiso')->toArray(),
        ]);

        return response()->json([
            'roles' => $roles,
            'special_permissions' => $allPermissions,
            'available_permissions' => $availablePermissions,
            'available_roles' => $availableRoles
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
        $isMember = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        if (!$isMember) {
            return back()->with('error', 'El usuario no es miembro del equipo de este curso.');
        }

        $idContexto = $curso->id_contexto;
        $adminId = Auth::id();
        /** @var Usuario $currentUser */
        $currentUser = Auth::user();

        // Security: A user cannot modify their own permissions
        if ($usuario->id_usuario === $currentUser->id_usuario) {
            return back()->with('error', 'No puedes modificar tus propios permisos.');
        }

        // Get delegable permissions FOR THIS CONTEXT to validate
        $delegablePermIds = $this->getDelegablePermissions($currentUser, $idContexto)->pluck('id_permiso')->toArray();

        try {
            DB::transaction(function () use ($idContexto, $usuario, $currentUser, $validated, $delegablePermIds, $curso, $adminId) {
                // 1. Sync Roles - Restricted if user is Docente
                $isDocente = $currentUser && $currentUser->docente;
                if ($isDocente) {
                    $allowedRoleIds = Rol::whereIn('nombre', ['ayudante', 'estudiante'])->pluck('id_rol')->toArray();

                    UsuarioRolAsignacion::where('id_usuario', $usuario->id_usuario)
                        ->where('id_contexto', $idContexto)
                        ->whereIn('id_rol', $allowedRoleIds)
                        ->where('esta_activo', true)
                        ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

                    if (!empty($validated['roles'])) {
                        foreach ($validated['roles'] as $rolId) {
                            if (in_array($rolId, $allowedRoleIds)) {
                                $rol = Rol::findOrFail($rolId);
                                $usuario->giveRole($rol)
                                    ->on($curso)       // ← Curso implementa HasOwnedContext
                                    ->for(365)
                                    ->save();
                            }
                        }
                    }
                } else {
                    // For Full Admin: Sync all roles in this context
                    UsuarioRolAsignacion::where('id_usuario', $usuario->id_usuario)
                        ->where('id_contexto', $idContexto)
                        ->where('esta_activo', true)
                        ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

                    if (!empty($validated['roles'])) {
                        foreach ($validated['roles'] as $rolId) {
                            $rol = Rol::findOrFail($rolId);
                            $usuario->giveRole($rol)
                                ->on($curso)       // ← Curso implementa HasOwnedContext
                                ->for(365)
                                ->save();
                        }
                    }
                }

                // 2. Sync Special Permissions
                // First, deactivate all existing permissions for this user in this context
                UsuarioPermisoEspecial::where('id_usuario', $usuario->id_usuario)
                    ->where('id_contexto', $idContexto)
                    ->where('esta_activo', true)
                    ->where('fue_borrado', false)
                    ->update([
                        'esta_activo' => false,
                        'fue_borrado' => true,
                        'fecha_fin_real' => now()
                    ]);

                // Then, create new permissions for those in the request
                foreach ($validated['special_permissions'] as $permId => $status) {
                    // Convert key to int if it comes as string from form data
                    $permId = (int) $permId;
                    
                    $isObject = is_array($status);
                    $allowed = $isObject ? ($status['allowed'] ?? null) : $status;
                    $canDelegate = $isObject ? ($status['can_delegate'] ?? false) : false;

                    // Only sync delegable permissions (security check)
                    if (in_array($permId, $delegablePermIds)) {
                        // Skip if permission is set to null (inherit) - don't create UPE
                        if ($allowed === null) {
                            continue;
                        }

                        $permiso = \App\Models\Usuario\Permiso::findOrFail($permId);
                        $durationDays = $status['duration_days'] ?? 365;
                        
                        try {
                            // Get the permission enum case - use ONLY the direct permission, don't expand wildcards
                            // This way, if user assigns programas:* (ID 64), we save ID 64, not all its expanded components
                            $permissionEnumOrArray = \App\Support\Permissions::fromSlug($permiso->slug);
                            
                            // If it returns an array (multiple matching cases), find the one that matches permId exactly
                            $permissionEnum = $permissionEnumOrArray;
                            if (is_array($permissionEnumOrArray)) {
                                // Search for the exact permission case that corresponds to $permId
                                $matchedCase = null;
                                foreach ($permissionEnumOrArray as $case) {
                                    // Find permission in DB by enum value to get its ID
                                    $foundPerm = \App\Models\Usuario\Permiso::where('slug', $case->value)->first();
                                    
                                    if ($foundPerm && $foundPerm->id_permiso === $permId) {
                                        $matchedCase = $case;
                                        break;
                                    }
                                }
                                
                                // If we found an exact match, use it
                                // Otherwise fall back to the original enum we loaded
                                if ($matchedCase) {
                                    $permissionEnum = $matchedCase;
                                } else {
                                    // Shouldn't happen but fallback to from() for specific slug
                                    $permissionEnum = \App\Support\Permissions::from($permiso->slug);
                                }
                            }
                            
                            try {
                                $builder = $usuario->givePermission($permissionEnum)
                                    ->on($curso)       // ← Curso implementa HasOwnedContext
                                    ->for($durationDays);
                                
                                if ($canDelegate) {
                                    $builder->canDelegate();
                                }
                                
                                if ($allowed === false) {
                                    $builder->revoke();
                                }
                                
                                $builder->save();
                            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                                Log::warning("Permission enum case not found in database: {$permissionEnum->value}", [
                                    'enum_case' => $permissionEnum->name,
                                    'error' => $e->getMessage()
                                ]);
                                // Skip this permission and continue with others
                                continue;
                            }
                        } catch (\ValueError $e) {
                            Log::error("Invalid permission slug: {$permiso->slug}", [
                                'error' => $e->getMessage(),
                                'permId' => $permId
                            ]);
                            // Skip this permission and continue with others
                            continue;
                        }
                    }
                }
            });

            return back()->with('success', 'Permisos actualizados correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al sincronizar permisos:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            $ayudanteRole = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();

            if (!$ayudanteRole) {
                return response()->json([]);
            }

            // Obtener IDs de usuarios que ya están en el equipo del curso
            $existingMemberIds = [];
            if ($curso->id_contexto) {
                $existingMemberIds = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
                    ->where('esta_activo', true)
                    ->where('fue_eliminado', false)
                    ->pluck('id_usuario')
                    ->toArray();
            }

            // Buscar en la tabla Usuario — cualquier usuario activo puede ser ayudante
            $usuarios = Usuario::where('esta_activo', true)
                ->whereNotIn('id_usuario', $existingMemberIds)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('nombre1', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('nombre2', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('apellido1', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('apellido2', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('rut', 'ILIKE', "%{$searchTerm}%")
                        ->orWhere('username', 'ILIKE', "%{$searchTerm}%");
                })
                ->limit(10)
                ->get();

            // Formatear resultados
            $results = $usuarios->map(function ($user) {
                $nombreCompleto = trim(collect([
                    $user->nombre1,
                    $user->nombre2,
                    $user->apellido1,
                    $user->apellido2,
                ])->filter()->implode(' '));

                return [
                    'id_usuario'     => $user->id_usuario,
                    'nombre_completo' => $nombreCompleto ?: $user->username,
                    'rut'            => $user->rut ?? '',
                    'username'       => $user->username,
                ];
            });

            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Error searching assistants: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getDelegablePermissions(Usuario $user, ?int $idContexto = null)
    {
        $effectiveContextIds = $this->getDelegablePermissionContextIds($idContexto);

        // If it's a super admin, return all
        if (!$user->docente) {
            return \App\Models\Usuario\Permiso::all();
        }

        // Apply same docente logic as DocenteCursoController
        // Get all roles for the user (regardless of context)
        $roleQuery = $user->rolesAsignados()
            ->where('usuario.usuario_rol_asignacion.esta_activo', true)
            ->where('usuario.usuario_rol_asignacion.fue_eliminado', false);

        $rolePerms = $roleQuery->with([
            'permisos' => function ($query) {
                $query->wherePivot('puede_delegar_permisos', true);
            }
        ])
            ->get()
            ->pluck('permisos')
            ->flatten()
            ->unique('id_permiso');

        $specialQuery = UsuarioPermisoEspecial::where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->where(function ($query) {
                $query->where('esta_permitido', true)->orWhereNull('esta_permitido');
            })
            ->where('puede_delegar', true);
        if ($effectiveContextIds !== null) {
            $specialQuery->whereIn('id_contexto', $effectiveContextIds);
        }

        $specialPerms = $specialQuery->with('permiso')->get()->pluck('permiso');

        return $rolePerms->concat($specialPerms)->unique('id_permiso')->values();
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
}
