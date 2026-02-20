<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCursoRequest;
use App\Http\Requests\UpdateCursoRequest;
use App\Http\Resources\CursoResource;
use App\Http\Resources\SeccionResource;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Administrativo\Plan;
use App\Models\Curso\TipoSeccion;
use App\Services\CursoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
<<<<<<< HEAD
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
=======
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

>>>>>>> 749a229 (fix: Precambios en los controladores para las claves subrogadas.)
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
    protected CursoService $cursoService;

    public function __construct(CursoService $cursoService)
    {
        $this->cursoService = $cursoService;
    }

    /**
     * Display a paginated list of all courses with search and filters.
     */
    public function index(Request $request): Response
    {
        $cursos = Curso::query()
            ->with([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ])
            ->when($request->search, function ($query, $search) {
                $query->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('cod_curso', 'ilike', "%{$search}%")
                    ->orWhereHas('asignacionPlan.asignatura', function ($q) use ($search) {
                        $q->where('nombre', 'ilike', "%{$search}%");
                    });
            })
            ->whereNull('fecha_eliminacion')
            ->orderBy('fecha_inicio', 'desc')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        $planes = Plan::with('carrera')->get();
        $tipos_seccion = TipoSeccion::all();

        return Inertia::render('admin/Cursos', [
            'cursos' => CursoResource::collection($cursos),
            'planes' => $planes,
            'tipos_seccion' => $tipos_seccion,
            'availableRoles' => [],
            'availablePermissions' => [],
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created course.
     */
    public function store(StoreCursoRequest $request)
    {
        try {
            $curso = $this->cursoService->create($request->validated());

            return redirect()
                ->route('admin.cursos.index')
                ->with('success', 'Curso creado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error creating curso: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            return back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified course.
     */
    public function show(Curso $curso)
    {
        try {
            $curso->load([
                'inscripcionCursos.estudiante',
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ]);

            // Load secciones directly to avoid composite key eager load issues
            $secciones = Seccion::where('id_curso', $curso->id_curso)
                ->where('es_plantilla', $curso->es_plantilla)
                ->with('docente.usuario', 'tipoSeccion')
                ->get();

            return response()->json([
                'curso' => new CursoResource($curso),
                'secciones' => SeccionResource::collection($secciones),
                'tipos_seccion' => TipoSeccion::all()
            ]);
        } catch (\Exception $e) {
            Log::error("Error in show curso: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'curso_id' => $curso->id_curso
            ]);
            return response()->json([
                'error' => 'Error al cargar el curso: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Update the specified course.
     */
    public function update(UpdateCursoRequest $request, Curso $curso)
    {
        try {
            $updated = $this->cursoService->update($curso, $request->validated());

            return redirect()
                ->route('admin.cursos.index')
                ->with('success', 'Curso actualizado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error updating curso: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated(),
                'curso_id' => $curso->id_curso
            ]);

            return back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Get subjects for a specific plan (for cascading select).
     */
    public function getAsignaturasByPlan(Plan $plan)
    {
        $asignaturas = $plan->asignaturas()
            ->whereNull('asignacion_plan.fecha_eliminacion')
            ->select(
                'asignatura.id_asignatura',
                'asignatura.cod_asignatura',
                'asignatura.nombre',
                'asignatura.creditos_sct'
            )
            ->orderBy('asignatura.cod_asignatura')
            ->get();

        return response()->json($asignaturas);
    }

    /**
     * Remove the specified course.
     */
    public function destroy(Curso $curso)
    {
        try {
            $curso->delete();

            return redirect()
                ->route('admin.cursos.index')
                ->with('success', 'Curso eliminado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error deleting curso: ' . $e->getMessage());
            
            return redirect()
                ->route('admin.cursos.index')
                ->withErrors(['error' => 'No se puede eliminar el curso porque tiene asociaciones. Error: ' . $e->getMessage()]);
        }
    }
}
