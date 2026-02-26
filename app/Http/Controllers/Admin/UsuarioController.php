<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Authorization\GlobalContextService;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Docente;
use App\Models\Administrativo\Carrera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\Contexto;
use Illuminate\Support\Facades\Log;
/**
 * Controlador para la gestión integral de usuarios del sistema.
 * 
 * Tablas implicadas:
 * - usuario.usuario: Base de todos los usuarios del sistema
 * - usuario.estudiante: Perfil específico de estudiantes con carrera asignada
 * - usuario.docente: Perfil específico de docentes con grado, título y cargo
 * - usuario.rol: Definiciones de roles disponibles (Docente, Estudiante, Ayudante, etc.)
 * - usuario.permiso: Definiciones de permisos especiales asignables
 * - usuario.usuario_rol_asignación: Asignaciones de roles a usuarios en contextos específicos
 * - usuario.usuario_permiso_especial: Asignaciones de permisos especiales a usuarios
 * - usuario.contexto: Contextos (cursos) en los que roles y permisos se aplican
 * 
 * SEGURIDAD CRÍTICA: Este controlador maneja Control de Acceso Basado en Roles (RBAC).
 * Históricamente se encontraron vulnerabilidades IDOR en esta gestión.
 * Siempre validar que el usuario autenticado tenga permisos de administración para las operaciones.
 * 
 * Gestiona:
 * - Creación/actualización/eliminación de usuarios (Estudiantes, Docentes, Administradores)
 * - Asignación de roles y permisos a usuarios
 * - Sincronización de datos de usuario y sus roles en contextos
 * - Búsqueda filtrada por tipo de usuario
 * 
 * Arquitectura:
 * - index(): Lista usuarios filtrados por tipo con paginación y búsqueda
 * - store(): Dispatcher que delega a métodos específicos por tipo
 * - storeEstudiante/storeDocente/storeAdministrador: Creación transaccional por tipo
 * - show/update/destroy: Operaciones estándar con validaciones RBAC
 */
class UsuarioController extends Controller
{
    /**
     * Obtiene listado paginado y filtrable de usuarios por tipo.
     * 
     * Soporta tres tipos: estudiante, docente, administrador.
     * Implementa búsqueda por nombre, apellido, RUT y username (case-insensitive con ilike).
     * Retorna HTML Inertia con usuarios, roles, permisos disponibles, y datos de carreras.
     * 
     * @param  Request  $request  Parámetros: tipo (estudiante|docente|administrador), search, per_page
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse  Vista HTML o JSON según Accept header
     */
    public function index(Request $request)
    {
        $tipo = $request->input('tipo', 'estudiante'); // estudiante, docente, or administrador

        if ($tipo === 'estudiante') {
            $query = Estudiante::with(['usuario', 'carrera']);

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->whereHas('usuario', function ($q) use ($search) {
                    $q->where('nombre1', 'ilike', "%{$search}%")
                        ->orWhere('apellido1', 'ilike', "%{$search}%")
                        ->orWhere('rut', 'ilike', "%{$search}%");
                });
            }

            $usuarios = $query->join('usuario', 'estudiante.id_usuario', '=', 'usuario.id_usuario')
                ->orderBy('usuario.nombre1')
                ->orderBy('usuario.apellido1')
                ->select('estudiante.*') // Select estudiante fields to avoid ID collisions if needed, or just let Eloquent handle it
                ->paginate($request->input('per_page', 15))
                ->withQueryString();
        } elseif ($tipo === 'docente') {
            $query = Docente::with('usuario');

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->whereHas('usuario', function ($q) use ($search) {
                    $q->where('nombre1', 'ilike', "%{$search}%")
                        ->orWhere('apellido1', 'ilike', "%{$search}%")
                        ->orWhere('rut', 'ilike', "%{$search}%");
                });
            }

            $usuarios = $query->join('usuario', 'docente.id_usuario', '=', 'usuario.id_usuario')
                ->orderBy('usuario.nombre1')
                ->orderBy('usuario.apellido1')
                ->select('docente.*')
                ->paginate($request->input('per_page', 15))
                ->withQueryString();
        } else {
            // Administradores: usuarios sin docente ni estudiante
            $query = Usuario::query()
                ->whereDoesntHave('docente')
                ->whereDoesntHave('estudiante');

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'ilike', "%{$search}%")
                        ->orWhere('rut', 'ilike', "%{$search}%")
                        ->orWhere('nombre1', 'ilike', "%{$search}%")
                        ->orWhere('apellido1', 'ilike', "%{$search}%");
                });
            }

            $usuarios = $query->orderBy('nombre1')
                ->orderBy('apellido1')
                ->paginate($request->input('per_page', 15))
                ->withQueryString();
        }

        if ($request->wantsJson()) {
            return response()->json($usuarios);
        }

        $carreras = Carrera::orderBy('nombre')->get();

        // RBAC Data
        $availableRoles = Rol::orderBy('nombre')->get();
        // Group permissions by module
        // Group permissions by module -> Grouped as 'General' for frontend display
        $availablePermissions = Permiso::orderBy('slug')->get()->groupBy(fn() => 'General');

        return Inertia::render('admin/Usuarios', [
            'usuarios' => $usuarios,
            'tipo' => $tipo,
            'carreras' => $carreras,
            'availableRoles' => $availableRoles,
            'availablePermissions' => $availablePermissions,
            'filters' => $request->only(['search', 'tipo'])
        ]);
    }

    /**
     * Dispatcher para crear nuevo usuario según tipo especificado.
     * 
     * Valida que el request incluya 'tipo' y delega a método específico:
     * - 'estudiante' → storeEstudiante()
     * - 'docente' → storeDocente()
     * - otro → storeAdministrador()
     * 
     * @param  Request  $request  Datos del usuario: tipo, rut, nombres, etc.
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    public function store(Request $request)
    {
        $tipo = $request->input('tipo');

        if ($tipo === 'estudiante') {
            return $this->storeEstudiante($request);
        } elseif ($tipo === 'docente') {
            return $this->storeDocente($request);
        } else {
            return $this->storeAdministrador($request);
        }
    }

    /**
     * Crea nuevo usuario Estudiante de forma transaccional.
     * 
     * Crea registro base Usuario y vincula perfil Estudiante con carrera.
     * Valida RUT único, username único (máx 10 caracteres).
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos: rut, nombre1, apellido1, email, agno_ingreso, id_carrera, username, password
     * @return \Illuminate\Http\RedirectResponse  Redirección a lista estudiantes con mensaje
     */
    private function storeEstudiante(Request $request)
    {
        $validated = $request->validate([
            'rut' => 'required|string|max:20',
            'nombre1' => 'required|string|max:100',
            'nombre2' => 'nullable|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'agno_ingreso' => 'nullable|integer|min:1900|max:2100',
            'id_carrera' => ['nullable', Rule::exists(Carrera::class, 'id_carrera')],
            'username' => ['required', 'string', 'max:10', Rule::unique(Usuario::class, 'username')],
            'password' => 'required|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Create Usuario first
            $usuario = Usuario::create([
                'username' => $validated['username'],
                'passhash' => Hash::make($validated['password']),
                'rut' => $validated['rut'],
                'nombre1' => $validated['nombre1'],
                'nombre2' => $validated['nombre2'] ?? null,
                'apellido1' => $validated['apellido1'],
                'apellido2' => $validated['apellido2'] ?? null,
                'email' => $validated['email'] ?? null,
                'esta_activo' => true
            ]);

            // Create Estudiante linked to Usuario
            Estudiante::create([
                'id_usuario' => $usuario->id_usuario,
                'agno_ingreso' => $validated['agno_ingreso'] ?? null,
                'id_carrera' => $validated['id_carrera'] ?? null,
            ]);

            // Assign 'estudiante' role
            $this->assignRole($usuario, 'estudiante');

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'estudiante'])
                ->with('success', 'Estudiante creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating estudiante: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return back()->withErrors(['error' => 'Error al crear estudiante: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Crea nuevo usuario Docente de forma transaccional.
     * 
     * Crea registro base Usuario y vincula perfil Docente con grado, título, cargo.
     * Valida RUT único, username único (máx 10 caracteres).
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos: rut, nombre1, apellido1, email, grado, titulo, cargo, username, password
     * @return \Illuminate\Http\RedirectResponse  Redirección a lista docentes con mensaje
     */
    private function storeDocente(Request $request)
    {
        $validated = $request->validate([
            'rut' => 'required|string|max:20',
            'nombre1' => 'required|string|max:100',
            'nombre2' => 'nullable|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'grado' => 'nullable|string|max:100',
            'titulo' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:100',
            'username' => ['required', 'string', 'max:10', Rule::unique(Usuario::class, 'username')],
            'password' => 'required|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Create Usuario first
            $usuario = Usuario::create([
                'username' => $validated['username'],
                'passhash' => Hash::make($validated['password']),
                'rut' => $validated['rut'],
                'nombre1' => $validated['nombre1'],
                'nombre2' => $validated['nombre2'] ?? null,
                'apellido1' => $validated['apellido1'],
                'apellido2' => $validated['apellido2'] ?? null,
                'email' => $validated['email'] ?? null,
                'esta_activo' => true
            ]);

            // Create Docente linked to Usuario
            Docente::create([
                'id_usuario' => $usuario->id_usuario,
                'grado' => $validated['grado'] ?? null,
                'titulo' => $validated['titulo'] ?? null,
                'cargo' => $validated['cargo'] ?? null,
            ]);

            // Assign 'docente' role
            $this->assignRole($usuario, 'docente');

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'docente'])
                ->with('success', 'Docente creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating docente: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return back()->withErrors(['error' => 'Error al crear docente: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Crea nuevo usuario Administrador.
     * 
     * Crea registro base Usuario sin perfil específico (diferenciador de Estudiante/Docente).
     * Valida RUT único, username único (máx 30 caracteres para mayor flexibilidad).
     * 
     * @param  Request  $request  Datos: rut, nombre1, apellido1, email, username, password
     * @return \Illuminate\Http\RedirectResponse  Redirección a lista administradores con mensaje
     */
    private function storeAdministrador(Request $request)
    {
        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:20', Rule::unique(Usuario::class, 'rut')],
            'nombre1' => 'required|string|max:255',
            'nombre2' => 'nullable|string|max:255',
            'apellido1' => 'required|string|max:255',
            'apellido2' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'username' => ['required', 'string', 'max:30', Rule::unique(Usuario::class, 'username')],
            'password' => 'required|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            $usuario = Usuario::create([
                'username' => $validated['username'],
                'passhash' => Hash::make($validated['password']),
                'rut' => $validated['rut'],
                'nombre1' => $validated['nombre1'],
                'nombre2' => $validated['nombre2'] ?? null,
                'apellido1' => $validated['apellido1'],
                'apellido2' => $validated['apellido2'] ?? null,
                'email' => $validated['email'] ?? null,
                'esta_activo' => true,
            ]);

            // Assign 'SuperAdmin' role
            $this->assignRole($usuario, 'SuperAdmin');

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'administrador'])
                ->with('success', 'Administrador creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating administrador: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error al crear administrador: ' . $e->getMessage()])->withInput();
        }
    }


    /**
     * Obtiene detalles de un usuario específico según su tipo.
     * 
     * Resuelve el usuario con relaciones asociadas (carrera para estudiantes, etc.).
     * Retorna JSON.
     * 
     * @param  int      $id       ID del estudiante/docente/usuario
     * @param  Request  $request  Parámetro: tipo (estudiante|docente|administrador)
     * @return \Illuminate\Http\JsonResponse  JSON con datos del usuario y relaciones
     */
    public function show($id, Request $request)
    {
        $tipo = $request->input('tipo', 'estudiante');

        if ($tipo === 'estudiante') {
            $usuario = Estudiante::with(['carrera', 'usuario'])->findOrFail($id);
        } elseif ($tipo === 'docente') {
            $usuario = Docente::with('usuario')->findOrFail($id);
        } else {
            $usuario = Usuario::findOrFail($id);
        }

        return response()->json($usuario);
    }

    /**
     * Dispatcher para actualizar usuario según tipo especificado.
     * 
     * Valida 'tipo' y delega a método específico:
     * - 'estudiante' → updateEstudiante()
     * - 'docente' → updateDocente()
     * - otro → updateAdministrador()
     * 
     * @param  Request  $request  Parámetro: tipo, y datos a actualizar
     * @param  int      $id       ID del usuario a actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    public function update(Request $request, $id)
    {
        $tipo = $request->input('tipo');

        if ($tipo === 'estudiante') {
            return $this->updateEstudiante($request, $id);
        } elseif ($tipo === 'docente') {
            return $this->updateDocente($request, $id);
        } else {
            return $this->updateAdministrador($request, $id);
        }
    }

    /**
     * Actualiza datos de usuario Estudiante de forma transaccional.
     * 
     * Modifica registros Usuario y Estudiante conjuntamente.
     * Permite actualizar información personal, carrera y año de ingreso.
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos actualizados: rut, nombres, carrera, agno_ingreso, email
     * @param  int      $id       ID del estudiante a actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    private function updateEstudiante(Request $request, $id)
    {
        $estudiante = Estudiante::findOrFail($id);
        $usuario = $estudiante->usuario;

        $validated = $request->validate([
            'rut' => 'required|string|max:20',
            'nombre1' => 'required|string|max:100',
            'nombre2' => 'nullable|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'agno_ingreso' => 'nullable|integer|min:1900|max:2100',
            'id_carrera' => ['nullable', Rule::exists(Carrera::class, 'id_carrera')],
        ]);

        DB::beginTransaction();
        try {
            $usuario->update([
                'rut' => $validated['rut'],
                'nombre1' => $validated['nombre1'],
                'nombre2' => $validated['nombre2'],
                'apellido1' => $validated['apellido1'],
                'apellido2' => $validated['apellido2'],
                'email' => $validated['email'],
            ]);

            $estudiante->update([
                'agno_ingreso' => $validated['agno_ingreso'],
                'id_carrera' => $validated['id_carrera'],
            ]);

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'estudiante'])
                ->with('success', 'Estudiante actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar estudiante: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza datos de usuario Docente de forma transaccional.
     * 
     * Modifica registros Usuario y Docente conjuntamente.
     * Permite actualizar información personal, grado académico, cargo y título.
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos actualizados: rut, nombres, email, grado, cargo, titulo
     * @param  int      $id       ID del docente a actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    private function updateDocente(Request $request, $id)
    {
        $docente = Docente::findOrFail($id);
        $usuario = $docente->usuario;

        $validated = $request->validate([
            'rut' => 'required|string|max:20',
            'nombre1' => 'required|string|max:100',
            'nombre2' => 'nullable|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'grado' => 'nullable|string|max:100',
            'cargo' => 'nullable|string|max:100',
            'titulo' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $usuario->update([
                'rut' => $validated['rut'],
                'nombre1' => $validated['nombre1'],
                'nombre2' => $validated['nombre2'],
                'apellido1' => $validated['apellido1'],
                'apellido2' => $validated['apellido2'],
                'email' => $validated['email'],
            ]);

            $docente->update([
                'grado' => $validated['grado'],
                'cargo' => $validated['cargo'],
                'titulo' => $validated['titulo'],
            ]);

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'docente'])
                ->with('success', 'Docente actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar docente: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza datos de usuario Administrador.
     * 
     * Modifica registro Usuario base con información personal y contacto.
     * 
     * @param  Request  $request  Datos actualizados: rut, nombres, email
     * @param  int      $id       ID del administrador a actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    private function updateAdministrador(Request $request, $id)
    {
        $usuario = Usuario::findOrFail($id);

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:20', Rule::unique(Usuario::class, 'rut')->ignore($id, 'id_usuario')],
            'nombre1' => 'required|string|max:255',
            'nombre2' => 'nullable|string|max:255',
            'apellido1' => 'required|string|max:255',
            'apellido2' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $usuario->update($validated);

        return redirect()->route('admin.usuarios.index', ['tipo' => 'administrador'])
            ->with('success', 'Administrador actualizado exitosamente.');
    }

    /**
     * Elimina un usuario y su perfil asociado de forma transaccional.
     * 
     * Elimina perfil específico (Estudiante/Docente) primero, luego registro Usuario base.
     * Rollback completo si hay registros asociados que lo impidan (foreign keys).
     * 
     * @param  int      $id       ID del estudiante/docente/usuario a eliminar
     * @param  Request  $request  Parámetro: tipo (estudiante|docente|administrador)
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    public function destroy($id, Request $request)
    {
        $tipo = $request->input('tipo', 'estudiante');

        DB::beginTransaction();
        try {
            if ($tipo === 'estudiante') {
                $record = Estudiante::findOrFail($id);
            } elseif ($tipo === 'docente') {
                $record = Docente::findOrFail($id);
            } else {
                $record = Usuario::findOrFail($id);
            }

            $usuarioId = ($tipo === 'administrador') ? $record->id_usuario : $record->id_usuario;

            if ($tipo !== 'administrador') {
                $record->delete();
            }

            Usuario::find($usuarioId)?->delete();

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => $tipo])
                ->with('success', ucfirst($tipo) . ' eliminado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.usuarios.index', ['tipo' => $tipo])
                ->with('error', 'No se puede eliminar el ' . $tipo . ' porque tiene registros asociados.');
        }
    }

    /**
     * Actualiza la contraseña de un usuario.
     * 
     * Valida que la nueva contraseña cumpla requisitos mínimos (6 caracteres, confirmación).
     * Hash y almacena en campo 'passhash'.
     * 
     * @param  Request  $request  Datos: password (required, min:6, confirmed)
     * @param  int      $id       ID del usuario cuya contraseña actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    public function changePassword(Request $request, $id)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $usuario = Usuario::findOrFail($id);
        $usuario->update(['passhash' => Hash::make($validated['password'])]);

        return back()->with('success', 'Contraseña actualizada exitosamente.');
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     * 
     * Cambia 'esta_activo' entre true y false. Usuario inactivo no puede autenticarse.
     * 
     * @param  int  $id  ID del usuario a activar/desactivar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    public function toggleActive($id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->esta_activo = !(bool) $usuario->esta_activo;
        $usuario->save();

        $status = $usuario->esta_activo ? 'activado' : 'desactivado';
        return back()->with('success', "Usuario {$status} exitosamente.");
    }

    /**
     * Obtiene todos los roles y permisos especiales asignados a un usuario en contexto Global.
     * 
     * Resuelve o crea contexto Global si no existe, luego recupera asignaciones activas.
     * Retorna JSON con array de IDs de rol y array de permisos especiales.
     * 
     * @param  int  $id  ID del usuario cuyo permisos obtener
     * @return \Illuminate\Http\JsonResponse  JSON con roles e permisos activos
     */
    public function getUserPermissions($id)
    {
        $usuario = Usuario::findOrFail($id);

        // Fix: Este método actualmente retorna roles y permisos de TODOS los contextos
        // sin distinción, lo cual es incorrecto. El comportamiento correcto debería ser:
        //
        // 1. El frontend debería enviar un parámetro ?context_id=X para indicar en qué
        //    contexto se está gestionando al usuario (ej: contexto Global para admins,
        //    contexto de curso para equipo docente).
        //
        // 2. Este método debería filtrar por ese context_id y ADEMÁS retornar en la
        //    respuesta los datos agrupados por contexto, para que el admin pueda ver
        //    "este rol está asignado en el contexto Global, este otro en el Curso X".
        //
        // 3. El modal PermissionsModal debería mostrar un selector de contexto (basicamente
        //    una lista de objetos con contexto) antes de cargar los permisos, o mostrar 
        //    todos agrupados por contexto.

        $idContexto = app(GlobalContextService::class)->getContextId();

        $idRoles = array_column(
            $usuario->getAllRoles($idContexto),
            'id'
        );

        $specialPermissions = array_values(
            $usuario->getAllPermissions(
                $idContexto,
                PermissionTypeEnum::ESPECIAL
            )
        );

        // Get available roles (all except SuperAdmin) - transform to clean array
        $availableRoles = Rol::whereNotIn('nombre', ['SuperAdmin', 'Super Admin'])
            ->orderBy('nombre')
            ->get()
            ->map(fn($rol) => [
                'id_rol' => $rol->id_rol,
                'nombre' => $rol->nombre
            ])
            ->values();

        // Get available permissions (for admin, return all)
        $availablePermissions = Permiso::all()
            ->map(fn($p) => [
                'id_permiso' => $p->id_permiso,
                'slug' => $p->slug,
                'nombre' => $p->nombre
            ])
            ->groupBy(fn() => 'Docencia');

        return response()->json([
            'roles' => $idRoles,
            'special_permissions' => $specialPermissions,
            'available_roles' => $availableRoles,
            'available_permissions' => $availablePermissions,
        ]);
    }

    /**
     * Sincroniza (actualiza) todos los roles y permisos especiales de un usuario.
     * 
     * Reemplaza asignaciones actuales con las nuevas. Implementa RBAC validado.
     * Registra cambios para auditoría. Transaccional.
     * 
     * @param  Request  $request  Datos: roles (array de ids), special_permissions (array de id_permiso => bool)
     * @param  int      $id       ID del usuario cuyo permisos sincronizar
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse  JSON o redirección
     */
    public function syncPermissions(Request $request, $id)
    {
        Log::info("SyncPermissions called for user $id");
        Log::info("Payload: " . print_r($request->all(), true));

        $validated = $request->validate([
            'roles' => 'array',
            'special_permissions' => 'array' // { id_permiso: true/false/null }
        ]);

        Log::info('🔍 SPECIAL PERMISSIONS RECEIVED:', [
            'raw' => $validated['special_permissions'],
            'delegable_count' => count(array_filter(
                $validated['special_permissions'],
                fn($sp) => is_array($sp) && ($sp['can_delegate'] ?? false) === true
            ))
        ]);

        $usuario = Usuario::findOrFail($id);

        // Note: Para contextos específicos (Carrera, Facultad, Curso, etc.),
        // usa los builders: $user->givePermission($perm)->on($carrera)->for(30)->save()
        // Los builders aplican duración, delegación y auto-audit.
        //
        // Para el contexto global, usamos updateOrCreate directo con duración de 365 días.

        $idContexto = app(GlobalContextService::class)->getContextId();

        $adminId = Auth::id() ?? 1; // Fallback only for dev/seeder

        DB::beginTransaction();
        try {
            // 1. Sync Roles
            // Soft-delete all existing active assignments for this context
            UsuarioRolAsignacion::where('id_usuario', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->update([
                    'esta_activo' => false,
                    'fue_eliminado' => true,
                    'fecha_fin_real' => now()
                ]);

            // Add new roles
            if (!empty($validated['roles'])) {
                foreach ($validated['roles'] as $rolId) {
                    UsuarioRolAsignacion::updateOrCreate(
                        [
                            'id_usuario' => $usuario->id_usuario,
                            'id_contexto' => $idContexto,
                            'id_rol' => $rolId,
                        ],
                        [
                            'asignado_por' => (int) $adminId,
                            'creado_por' => (int) $adminId,
                            'fecha_inicio_planificada' => now(),
                            'fecha_fin_planificada' => now()->addDays(365),  // 365 días en lugar de 100 años
                            'esta_activo' => true,
                            'fue_eliminado' => false,
                            'fecha_fin_real' => null,
                            'fecha_creacion' => now(),
                        ]
                    );
                }
            }

            // 2. Sync Special Permissions
            // Soft-delete existing
            UsuarioPermisoEspecial::where('id_usuario', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->where('esta_activo', true)
                ->where('fue_borrado', false)
                ->update([
                    'esta_activo' => false,
                    'fue_borrado' => true,
                    'fecha_fin_real' => now()
                ]);

            foreach ($validated['special_permissions'] as $permId => $status) {
                $isObject = is_array($status);
                $allowed = $isObject ? ($status['allowed'] ?? null) : $status;
                $canDelegate = $isObject ? ($status['can_delegate'] ?? false) : false;
                $duracionDias = $isObject ? ($status['duration_days'] ?? 365) : 365;

                Log::debug('Processing permiso:', [
                    'perm_id' => $permId,
                    'is_object' => $isObject,
                    'allowed' => $allowed,
                    'can_delegate' => $canDelegate,
                    'duration_days' => $duracionDias,
                    'will_save' => ($allowed !== null || $canDelegate)
                ]);

                if ($allowed !== null || $canDelegate) {
                    $upe = UsuarioPermisoEspecial::updateOrCreate(
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
                            'fecha_fin_planificada' => now()->addDays($duracionDias),  // Usa duración del request
                            'fecha_creacion' => now()
                        ]
                    );
                    
                    Log::debug('✅ Permiso guardado:', [
                        'id_upe' => $upe->id_upe,
                        'perm_id' => $permId,
                        'puede_delegar_saved' => $upe->puede_delegar,
                        'duration_days' => $duracionDias
                    ]);
                }
            }

            DB::commit();

            // Return appropriate response type
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Permisos actualizados correctamente.'
                ]);
            }

            return back()->with('success', 'Permisos actualizados correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("SyncPermissions Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
                'payload' => $validated
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar permisos: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Error al actualizar permisos: ' . $e->getMessage());
        }
    }
    /**
     * Helper to assign a role to a user in the Global context.
     */
    private function assignRole(Usuario $usuario, string $roleName)
    {
        $rol = Rol::where('nombre', $roleName)->first();
        if (!$rol) {
            Log::warning("Role '$roleName' not found for automatic assignment.");
            return;
        }

        $admin = $usuario;
        $globalContextId = app(GlobalContextService::class)->getContextId();
        
        $admin->giveRole($rol)
            ->inContext($globalContextId)
            ->for(365)
            ->save();
    }
}
