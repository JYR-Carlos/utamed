<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Plan;
use App\Models\Usuario\Docente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CursoController extends Controller
{
    /**
     * Display a listing of cursos.
     */
    public function index(Request $request)
    {
        $query = Curso::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('utamed.Curso.nombre', 'ilike', "%{$search}%")
                    ->orWhere('utamed.Curso.cod_curso', 'ilike', "%{$search}%");
            });
        }

        // Filter by asignatura
        if ($request->has('id_asignatura')) {
            $query->where('utamed.Curso.id_asignatura', $request->input('id_asignatura'));
        }

        // Join with relationships to simplify data access in frontend
        $cursos = $query->join('utamed.Asignatura', 'utamed.Curso.id_asignatura', '=', 'utamed.Asignatura.id_asignatura')
            ->join('utamed.Plan', 'utamed.Curso.id_plan', '=', 'utamed.Plan.id_plan')
            ->join('utamed.Carrera', 'utamed.Plan.id_carrera', '=', 'utamed.Carrera.id_carrera')
            ->leftJoin('utamed.Docente', 'utamed.Curso.id_docente', '=', 'utamed.Docente.id_docente')
            ->select(
                'utamed.Curso.*',
                'utamed.Asignatura.nombre as asignatura_nombre',
                'utamed.Carrera.nombre as carrera_nombre',
                'utamed.Docente.nombre_completo as docente_nombre'
            )
            ->orderBy('utamed.Curso.fecha_inicio', 'desc')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get all asignaturas and planes for filters
        $asignaturas = Asignatura::orderBy('cod_asignatura')->get();
        $planes = Plan::with('carrera')->orderBy('agno', 'desc')->get();

        // RBAC Data for team management - Filtered if user is a Docente
        /** @var \App\Models\Usuario\Usuario $user */
        $user = auth()->user();
        $isDocente = $user && $user->docente;
        // In this system, someone might have both roles, but if they have a docente profile, we restrict them here
        // UNLESS they have a higher system-admin role. Let's be safe and check if they are explicitly restricted.

        $roleQuery = \App\Models\Usuario\Rol::orderBy('nombre');
        $permQuery = \App\Models\Usuario\Permiso::orderBy('modulo')->orderBy('slug');

        if ($isDocente) {
            $roleQuery->whereIn('nombre', ['Ayudante', 'Estudiante']);
            $permQuery->whereIn('modulo', ['Docencia', 'Ayudantía']);
        }

        $availableRoles = $roleQuery->get();
        $availablePermissions = $permQuery->get()->groupBy('modulo');

        return Inertia::render('admin/Cursos', [
            'cursos' => $cursos,
            'asignaturas' => $asignaturas,
            'planes' => $planes,
            'availableRoles' => $availableRoles,
            'availablePermissions' => $availablePermissions,
            'filters' => $request->only(['search', 'id_asignatura'])
        ]);
    }

    /**
     * Store a newly created curso.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_asignatura' => ['required', Rule::exists(Asignatura::class, 'id_asignatura')],
            'id_plan' => ['required', Rule::exists(Plan::class, 'id_plan')],
            'cod_curso' => 'required|string|max:50',
            'nombre' => 'nullable|string|max:255',
            'fecha_inicio' => 'nullable|date',
            'numero_semestre' => 'nullable|integer|min:1',
            'id_docente' => ['nullable', Rule::exists(Docente::class, 'id_docente')],
        ]);

        DB::beginTransaction();
        try {
            $data = $validated;

            // Create a specific Context for this course
            $nombreContexto = "Curso: " . $data['cod_curso'];
            // Check if context exists? Unique constraints might apply to name, but for now we create new
            $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                ['nombre' => $nombreContexto],
                ['descripcion' => 'Contexto para el curso ' . $data['cod_curso']]
            );
            $data['id_contexto'] = $contexto->id_contexto;

            $curso = Curso::create($data);

            // If a Docente is assigned, give them the 'Docente' role in this context automatically
            if (!empty($data['id_docente'])) {
                $docente = Docente::find($data['id_docente']);
                if ($docente && $docente->id_usuario) {
                    // Find or create 'Docente' role
                    $rolDocente = \App\Models\Usuario\Rol::where('nombre', 'Docente')->first();
                    if ($rolDocente) {
                        \App\Models\Usuario\UsuarioRolAsignación::create([
                            'id_usuario_recipiente' => $docente->id_usuario,
                            'id_contexto' => $contexto->id_contexto,
                            'id_rol' => $rolDocente->id_rol,
                            'id_usuario_asignador' => auth()->id() ?? 1, // Fallback for seeds
                            'fecha_inicio_planificada' => now(),
                            'esta_activo' => true,
                            'fue_eliminado' => false
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('admin.cursos.index')
                ->with('success', 'Curso creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear el curso: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified curso.
     */
    public function show(Curso $curso)
    {
        $curso->load('inscripcionCursos.estudiante');

        // Manually load AsignacionPlan with related data
        $curso->asignacion_plan = \App\Models\Administrativo\AsignacionPlan::with(['asignatura', 'plan.carrera'])
            ->where('id_asignatura', $curso->id_asignatura)
            ->where('id_plan', $curso->id_plan)
            ->first();

        return response()->json($curso);
    }

    /**
     * Update the specified curso.
     */
    public function update(Request $request, Curso $curso)
    {
        $validated = $request->validate([
            'id_asignatura' => ['required', Rule::exists(Asignatura::class, 'id_asignatura')],
            'id_plan' => ['required', Rule::exists(Plan::class, 'id_plan')],
            'cod_curso' => 'required|string|max:50',
            'nombre' => 'nullable|string|max:255',
            'fecha_inicio' => 'nullable|date',
            'numero_semestre' => 'nullable|integer|min:1',
            'id_docente' => ['nullable', Rule::exists(Docente::class, 'id_docente')],
        ]);

        DB::beginTransaction();
        try {
            $data = $validated;

            // Ensure context exists
            if (!$curso->id_contexto || $curso->id_contexto == 1) { // 1 is global default to move away from
                $nombreContexto = "Curso: " . $data['cod_curso'];
                $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                    ['nombre' => $nombreContexto],
                    ['descripcion' => 'Contexto para el curso ' . $data['cod_curso']]
                );
                $data['id_contexto'] = $contexto->id_contexto;
            } else {
                // Rename context if code changes? Optional but nice.
                $contexto = \App\Models\Usuario\Contexto::find($curso->id_contexto);
                if ($contexto && $data['cod_curso'] !== $curso->cod_curso) {
                    $contexto->update(['nombre' => "Curso: " . $data['cod_curso']]);
                }
            }

            // Check if Docente changed to sync Role
            $oldDocenteId = $curso->id_docente;
            $newDocenteId = $data['id_docente'] ?? null;

            $curso->update($data);

            // Sync Role logic
            if ($newDocenteId !== $oldDocenteId) {
                $idContexto = $curso->id_contexto; // Updated one
                $rolDocente = \App\Models\Usuario\Rol::where('nombre', 'Docente')->first();

                // Remove role from old docente in this context
                if ($oldDocenteId) {
                    $old = Docente::find($oldDocenteId);
                    if ($old && $old->id_usuario) {
                        \App\Models\Usuario\UsuarioRolAsignación::where('id_usuario_recipiente', $old->id_usuario)
                            ->where('id_contexto', $idContexto)
                            ->where('id_rol', $rolDocente->id_rol)
                            ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);
                    }
                }

                // Add role to new docente
                if ($newDocenteId) {
                    $new = Docente::find($newDocenteId);
                    if ($new && $new->id_usuario && $rolDocente) {
                        \App\Models\Usuario\UsuarioRolAsignación::create([
                            'id_usuario_recipiente' => $new->id_usuario,
                            'id_contexto' => $idContexto,
                            'id_rol' => $rolDocente->id_rol,
                            'id_usuario_asignador' => auth()->id() ?? 1,
                            'fecha_inicio_planificada' => now(),
                            'esta_activo' => true,
                            'fue_eliminado' => false
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('admin.cursos.index')
                ->with('success', 'Curso actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar el curso: ' . $e->getMessage());
        }
    }

    /**
     * Assign a docente to a curso.
     */
    public function assignDocente(Request $request, Curso $curso)
    {
        $validated = $request->validate([
            'id_docente' => ['required', Rule::exists(Docente::class, 'id_docente')],
        ]);

        $curso->update(['id_docente' => $validated['id_docente']]);

        return back()->with('success', 'Docente asignado al curso exitosamente.');
    }

    /**
     * Remove docente from curso.
     */
    public function unassignDocente(Curso $curso)
    {
        $curso->update(['id_docente' => null]);

        return back()->with('success', 'Docente removido del curso exitosamente.');
    }

    /**
     * Remove the specified curso.
     */
    public function destroy(Curso $curso)
    {
        try {
            $curso->delete();

            return redirect()->route('admin.cursos.index')
                ->with('success', 'Curso eliminado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.cursos.index')
                ->with('error', 'No se puede eliminar el curso porque tiene inscripciones asociadas.');
        }
    }
}
