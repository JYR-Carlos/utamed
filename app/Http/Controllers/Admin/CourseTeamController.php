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

    /**
     * Remove a member from the team.
     */
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
}
