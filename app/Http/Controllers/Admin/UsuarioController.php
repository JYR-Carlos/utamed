<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use App\Models\Usuario\UsuarioRolAsignación;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\Contexto;

class UsuarioController extends Controller
{
    /**
     * Display a listing of usuarios.
     */
    public function index(Request $request)
    {
        $tipo = $request->input('tipo', 'estudiante'); // estudiante, docente, or administrador

        if ($tipo === 'estudiante') {
            $query = Estudiante::query()
                ->join('utamed.Usuario', 'utamed.Estudiante.id_usuario', '=', 'utamed.Usuario.id_usuario')
                ->select(
                    'utamed.Estudiante.*',
                    'utamed.Usuario.id_usuario',
                    'utamed.Usuario.rut',
                    'utamed.Usuario.nombre1',
                    'utamed.Usuario.nombre2',
                    'utamed.Usuario.apellido1',
                    'utamed.Usuario.apellido2',
                    'utamed.Usuario.email',
                    'utamed.Usuario.esta_activo',
                    'utamed.Usuario.username'
                )
                ->with('carrera');

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('utamed.Usuario.nombre1', 'ilike', "%{$search}%")
                        ->orWhere('utamed.Usuario.apellido1', 'ilike', "%{$search}%")
                        ->orWhere('utamed.Usuario.rut', 'ilike', "%{$search}%");
                });
            }

            $usuarios = $query->orderBy('utamed.Usuario.nombre1')
                ->orderBy('utamed.Usuario.apellido1')
                ->paginate($request->input('per_page', 15))
                ->withQueryString();
        } elseif ($tipo === 'docente') {
            $query = Docente::query()
                ->join('utamed.Usuario', 'utamed.Docente.id_usuario', '=', 'utamed.Usuario.id_usuario')
                ->select(
                    'utamed.Docente.*',
                    'utamed.Usuario.id_usuario',
                    'utamed.Usuario.rut',
                    'utamed.Usuario.nombre1',
                    'utamed.Usuario.nombre2',
                    'utamed.Usuario.apellido1',
                    'utamed.Usuario.apellido2',
                    'utamed.Usuario.email',
                    'utamed.Usuario.esta_activo',
                    'utamed.Usuario.username'
                );

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('utamed.Usuario.nombre1', 'ilike', "%{$search}%")
                        ->orWhere('utamed.Usuario.apellido1', 'ilike', "%{$search}%")
                        ->orWhere('utamed.Usuario.rut', 'ilike', "%{$search}%");
                });
            }

            $usuarios = $query->orderBy('utamed.Usuario.nombre1')
                ->orderBy('utamed.Usuario.apellido1')
                ->paginate($request->input('per_page', 15))
                ->withQueryString();
        } else {
            // Administradores: usuarios sin docente ni estudiante
            $query = Usuario::query()
                ->whereNotIn('id_usuario', function ($query) {
                    $query->select('id_usuario')
                        ->from('utamed.Docente')
                        ->whereNotNull('id_usuario');
                })
                ->whereNotIn('id_usuario', function ($query) {
                    $query->select('id_usuario')
                        ->from('utamed.Estudiante')
                        ->whereNotNull('id_usuario');
                });

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
        $availablePermissions = Permiso::orderBy('modulo')->orderBy('slug')->get()->groupBy('modulo');

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
     * Store a newly created usuario.
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
     * Store a new estudiante.
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
                'rut' => $validated['rut'],
                'nombre_completo' => trim($validated['nombre1'] . ' ' . ($validated['nombre2'] ?? '') . ' ' . $validated['apellido1'] . ' ' . ($validated['apellido2'] ?? '')),
                'agno_ingreso' => $validated['agno_ingreso'] ?? null,
                'id_carrera' => $validated['id_carrera'] ?? null,
            ]);

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'estudiante'])
                ->with('success', 'Estudiante creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear estudiante: ' . $e->getMessage());
        }
    }

    /**
     * Store a new docente.
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
                'rut' => $validated['rut'],
                'nombre_completo' => trim($validated['nombre1'] . ' ' . ($validated['nombre2'] ?? '') . ' ' . $validated['apellido1'] . ' ' . ($validated['apellido2'] ?? '')),
                'grado' => $validated['grado'] ?? null,
                'titulo' => $validated['titulo'] ?? null,
                'cargo' => $validated['cargo'] ?? null,
            ]);

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'docente'])
                ->with('success', 'Docente creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear docente: ' . $e->getMessage());
        }
    }

    /**
     * Store a new administrador.
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

        Usuario::create([
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

        return redirect()->route('admin.usuarios.index', ['tipo' => 'administrador'])
            ->with('success', 'Administrador creado exitosamente.');
    }


    /**
     * Display the specified usuario.
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
     * Update the specified usuario.
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
     * Update an estudiante.
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
                'rut' => $validated['rut'],
                'nombre_completo' => trim($validated['nombre1'] . ' ' . ($validated['nombre2'] ?? '') . ' ' . $validated['apellido1'] . ' ' . ($validated['apellido2'] ?? '')),
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
     * Update a docente.
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
                'rut' => $validated['rut'],
                'nombre_completo' => trim($validated['nombre1'] . ' ' . ($validated['nombre2'] ?? '') . ' ' . $validated['apellido1'] . ' ' . ($validated['apellido2'] ?? '')),
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
     * Update an administrador.
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
     * Remove the specified usuario.
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
     * Change password for a usuario.
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
     * Toggle active status for a usuario.
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
     * Get permissions for a specific user.
     */
    public function getUserPermissions($id)
    {
        $usuario = Usuario::findOrFail($id);

        // For simple UI, we assume Global context or primary context. 
        // Real implementation might need context selection.
        $contexto = Contexto::where('nombre', 'Global')->first();
        if (!$contexto) {
            // Fallback if Global not found
            $contexto = Contexto::first();
        }

        if (!$contexto) {
            return response()->json(['roles' => [], 'special_permissions' => []]);
        }

        $idContexto = $contexto->id_contexto;

        $roles = $usuario->rolesAsignados()
            ->where('id_contexto', $idContexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->pluck('id_rol');

        $special = $usuario->permisosEspeciales()
            ->where('id_contexto', $idContexto)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->get(['id_permiso', 'esta_permitido']);

        return response()->json([
            'roles' => $roles,
            'special_permissions' => $special
        ]);
    }

    /**
     * Sync permissions for a user.
     */
    public function syncPermissions(Request $request, $id)
    {
        \Illuminate\Support\Facades\Log::info("START syncPermissions for User {$id}");

        $validated = $request->validate([
            'roles' => 'array',
            'special_permissions' => 'array' // { id_permiso: true/false/null }
        ]);

        \Illuminate\Support\Facades\Log::info("Validated payload:", $validated);

        $usuario = Usuario::findOrFail($id);

        // Assuming Global context for now
        $contexto = Contexto::firstOrCreate(
            ['nombre' => 'Global'],
            ['descripcion' => 'Contexto Global por defecto']
        );
        $idContexto = $contexto->id_contexto;
        \Illuminate\Support\Facades\Log::info("Context ID: {$idContexto}");

        $adminId = auth()->id() ?? 1; // Fallback only for dev/seeder

        DB::beginTransaction();
        try {
            // 1. Sync Roles
            \Illuminate\Support\Facades\Log::info("Syncing Roles...");

            // Soft-delete all existing active assignments for this context
            $deleted = UsuarioRolAsignación::where('id_usuario_recipiente', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->where('esta_activo', true)
                ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

            \Illuminate\Support\Facades\Log::info("Soft-deleted {$deleted} existing roles.");

            if (!empty($validated['roles'])) {
                foreach ($validated['roles'] as $rolId) {
                    \Illuminate\Support\Facades\Log::info("Creating role assignment for role ID: {$rolId}");
                    UsuarioRolAsignación::create([
                        'id_usuario_recipiente' => $usuario->id_usuario,
                        'id_contexto' => $idContexto,
                        'id_rol' => $rolId,
                        'id_usuario_asignador' => $adminId,
                        'fecha_inicio_planificada' => now(),
                        'esta_activo' => true,
                        'fue_eliminado' => false
                    ]);
                }
            }

            // 2. Sync Special Permissions
            // Soft-delete existing
            UsuarioPermisoEspecial::where('id_usuario_recipiente', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->where('esta_activo', true)
                ->update(['esta_activo' => false, 'fue_borrado' => true, 'fecha_fin_real' => now()]);

            if (!empty($validated['special_permissions'])) {
                foreach ($validated['special_permissions'] as $permId => $status) {
                    if ($status !== null) { // true (allow) or false (deny)
                        UsuarioPermisoEspecial::create([
                            'id_usuario_recipiente' => $usuario->id_usuario,
                            'id_contexto' => $idContexto,
                            'id_permiso' => $permId,
                            'id_usuario_asignador' => $adminId,
                            'esta_permitido' => (bool) $status,
                            'esta_activo' => true,
                            'fue_borrado' => false
                        ]);
                    }
                }
            }

            DB::commit();
            \Illuminate\Support\Facades\Log::info("Transaction COMMITTED successfully.");
            return back()->with('success', 'Permisos actualizados correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Transaction FAILED: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return back()->with('error', 'Error al actualizar permisos: ' . $e->getMessage());
        }
    }
}
