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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
/**
 * Controlador para la gestión de cursos (instancias de asignaturas).
 * 
 * Tablas implicadas:
 * - curso.curso: Instancias de asignaturas ofrecidas en un período académico.
 * - administrativo.asignatura: Asignaturas que conforman los cursos.
 * - administrativo.plan: Planes de estudio de los que se derivan los cursos.
 * - usuario.docente: Docentes responsables de cursos.
 * 
 * Los cursos representan la oferta académica de un semestre. Cada curso está vinculado
 * a una asignatura y puede tener múltiples secciones con diferentes docentes.
 */
class CursoController extends Controller
{
    /**
     * Muestra un listado paginado de todos los cursos con búsqueda y filtros.
     */
    public function index(Request $request)
    {
        $query = Curso::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('Curso.nombre', 'ilike', "%{$search}%")
                    ->orWhere('Curso.cod_curso', 'ilike', "%{$search}%");
            });
        }

        // Filter by asignatura
        if ($request->has('id_asignatura')) {
            $query->where('Curso.id_asignatura', $request->input('id_asignatura'));
        }

        // Join with relationships to simplify data access in frontend
        $cursos = $query->join('Asignatura', 'Curso.id_asignatura', '=', 'Asignatura.id_asignatura')
            ->join('Plan', 'Curso.id_plan', '=', 'Plan.id_plan')
            ->join('Carrera', 'Plan.id_carrera', '=', 'Carrera.id_carrera')
            ->select(
                'Curso.*',
                'Asignatura.nombre as asignatura_nombre',
                'Carrera.nombre as carrera_nombre'
            )
            ->whereNull('Curso.fecha_eliminacion')
            ->orderBy('Curso.fecha_inicio', 'desc')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get all asignaturas and planes for filters
        $asignaturas = Asignatura::orderBy('cod_asignatura')->get();
        $planes = Plan::with('carrera')->orderBy('agno', 'desc')->get();

        // RBAC Data for team management - Filtered if user is a Docente
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();
        $isDocente = $user && $user->docente;
        // In this system, someone might have both roles, but if they have a docente profile, we restrict them here
        // UNLESS they have a higher system-admin role. Let's be safe and check if they are explicitly restricted.

        $roleQuery = \App\Models\Usuario\Rol::orderBy('nombre');
        $permQuery = \App\Models\Usuario\Permiso::orderBy('slug');

        if ($isDocente) {
            $roleQuery->whereIn('nombre', ['Ayudante', 'Estudiante']);
            // $permQuery->whereIn('modulo', ['Docencia', 'Ayudantía']); // Modulo no longer exists
        }

        $availableRoles = $roleQuery->get();
        // Just return all permissions, maybe keyed by something else or just flat
        // Group permissions by 'General' category since 'modulo' column was removed
        $availablePermissions = $permQuery->get()->groupBy(fn() => 'General');

        return Inertia::render('admin/Cursos', [
            'cursos' => $cursos,
            'asignaturas' => $asignaturas,
            'planes' => $planes,
            'availableRoles' => $availableRoles,
            'availablePermissions' => $availablePermissions,
            'tipos_seccion' => \App\Models\Curso\TipoSeccion::all(),
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
            'cod_curso' => 'required|integer',
            'nombre' => 'nullable|string|max:255',
            'fecha_inicio' => 'nullable|date',
            'agno_real' => 'required|integer|min:2000|max:2100',
            'semestre_real' => 'required|integer|in:1,2',
            // Note: Docente is assigned through Sección, not directly to Curso
        ]);

        // BUSINESS RULE: Validate that plan is active (not soft-deleted)
        $plan = Plan::find($validated['id_plan']);
        if (!$plan || $plan->trashed()) {
            return back()->withErrors([
                'id_plan' => 'El plan seleccionado no está activo o no existe.'
            ])->withInput();
        }

        // BUSINESS RULE: Validate that asignatura belongs to the selected plan
        $asignacionExists = DB::table('Asignacion_Plan')
            ->where('id_plan', $validated['id_plan'])
            ->where('id_asignatura', $validated['id_asignatura'])
            ->whereNull('fecha_eliminacion')
            ->exists();

        if (!$asignacionExists) {
            return back()->withErrors([
                'id_asignatura' => 'La asignatura seleccionada no pertenece al plan especificado.'
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            $data = $validated;

            // Create a specific Context for this course
            $nombreContexto = "Curso: " . $data['cod_curso'];
            // Check if context exists? Unique constraints might apply to name, but for now we create new
            $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                ['contexto_display' => $nombreContexto],
                ['descripcion' => 'Contexto para el curso ' . $data['cod_curso']]
            );
            $data['id_contexto'] = $contexto->id_contexto;

            // Set default values for required NOT NULL fields
            $data['indice_grupo'] = $request->input('indice_grupo', 1);

            // If fecha_fin is not provided, set it to 6 months after fecha_inicio (typical semester duration)
            if (empty($data['fecha_fin']) && !empty($data['fecha_inicio'])) {
                $fechaInicio = new \DateTime($data['fecha_inicio']);
                $fechaInicio->modify('+6 months');
                $data['fecha_fin'] = $fechaInicio->format('Y-m-d');
            }

            $curso = Curso::create($data);

            // Note: Docente assignment is handled through Sección creation
            // Secciones can have different docentes for lab, theory, etc.

            DB::commit();
            return redirect()->route('admin.cursos.index')
                ->with('success', 'Curso creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating curso: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return back()->withErrors(['error' => 'Error al crear el curso: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified curso.
     */
    public function show(Curso $curso)
    {
        try {
            $curso->load('inscripcionCursos.estudiante');
            $curso->load('secciones.docente.usuario');
            $curso->load('secciones.tipoSeccion');

            // Manually load AsignacionPlan with related data
            $curso->asignacion_plan = \App\Models\Administrativo\AsignacionPlan::with(['asignatura', 'plan.carrera'])
                ->where('id_asignatura', $curso->id_asignatura)
                ->where('id_plan', $curso->id_plan)
                ->first();

            return response()->json([
                'curso' => $curso,
                'secciones' => $curso->secciones,
                'tipos_seccion' => \App\Models\Curso\TipoSeccion::all()
            ]);
        } catch (\Exception $e) {
            Log::error("Error in show curso: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    /**
     * Update the specified curso.
     */
    public function update(Request $request, Curso $curso)
    {
        $validated = $request->validate([
            'id_asignatura' => ['required', Rule::exists(Asignatura::class, 'id_asignatura')],
            'id_plan' => ['required', Rule::exists(Plan::class, 'id_plan')],
            'cod_curso' => 'required|integer',
            'nombre' => 'nullable|string|max:255',
            'fecha_inicio' => 'nullable|date',
            'agno_real' => 'required|integer|min:2000|max:2100',
            'semestre_real' => 'required|integer|in:1,2',
            'id_docente' => 'nullable|integer|exists:App\Models\Usuario\Docente,id_docente',
        ]);

        DB::beginTransaction();
        try {
            $data = $validated;

            // Ensure context exists
            if (!$curso->id_contexto || $curso->id_contexto == 1) { // 1 is global default to move away from
                $nombreContexto = "Curso: " . $data['cod_curso'];
                $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                    ['contexto_display' => $nombreContexto],
                    ['descripcion' => 'Contexto para el curso ' . $data['cod_curso']]
                );
                $data['id_contexto'] = $contexto->id_contexto;
            } else {
                // Rename context if code changes? Optional but nice.
                $contexto = \App\Models\Usuario\Contexto::find($curso->id_contexto);
                if ($contexto && $data['cod_curso'] !== $curso->cod_curso) {
                    $contexto->update(['contexto_display' => "Curso: " . $data['cod_curso']]);
                }
            }

            $curso->update($data);

            // Handle Docente assignment through Seccion
            if (array_key_exists('id_docente', $validated)) {
                // Find existing section or create a default one
                $seccion = \App\Models\Curso\Seccion::where('id_curso', $curso->id_curso)->first();

                if ($seccion) {
                    $seccion->update(['id_docente' => $validated['id_docente']]);
                } elseif ($validated['id_docente']) {
                    // Create new section if it doesn't exist AND we have a docente to assign
                    // Assuming default tipo_seccion = 1 (Catedra/Teoria)
                    \App\Models\Curso\Seccion::create([
                        'id_curso' => $curso->id_curso,
                        'id_docente' => $validated['id_docente'],
                        'id_tipo_seccion' => 1, // Default type
                        'es_plantilla' => false
                    ]);
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
     * Get asignaturas for a specific plan (for cascading select).
     */
    public function getAsignaturasByPlan(Plan $plan)
    {
        // Get asignaturas that belong to this plan and are not deleted
        $asignaturas = $plan->asignaturas()
            ->whereNull('Asignacion_Plan.fecha_eliminacion')
            ->select('Asignatura.id_asignatura', 'Asignatura.cod_asignatura', 'Asignatura.nombre')
            ->orderBy('Asignatura.cod_asignatura')
            ->get();

        return response()->json($asignaturas);
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
