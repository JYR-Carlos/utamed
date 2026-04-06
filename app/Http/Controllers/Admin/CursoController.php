<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCursoRequest;
use App\Http\Requests\UpdateCursoRequest;
use App\Http\Resources\CursoResource;
use App\Http\Resources\ComponenteResource;
use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Services\CursoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

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
                'asignacionPlan.plan.carrera',
                'componentes.tipoComponente',
                'componentes.docenteComponentes.docente.usuario',
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
        $tipos_componente = TipoComponente::all();
        $carreras = Carrera::orderBy('nombre')->get(['id_carrera', 'nombre', 'jornada', 'sede']);

        return Inertia::render('admin/Cursos', [
            'cursos' => CursoResource::collection($cursos),
            'planes' => $planes,
            'carreras' => $carreras,
            'tipos_componente' => $tipos_componente,
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
            Log::info("CursoController.show() - iniciando carga de curso", ['id_curso' => $curso->id_curso]);
            
            $curso->load([
                'inscripcionCursos.estudiante',
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ]);
            Log::info("CursoController.show() - curso cargado exitosamente");

            // Load secciones with all relationships upfront using eager loading
            Log::info("CursoController.show() - cargando secciones", [
                'id_curso' => $curso->id_curso
            ]);
            
            $componentes = Componente::where('id_curso', $curso->id_curso)
                ->with(['docenteComponentes.docente.usuario', 'tipoComponente'])
                ->get();
            
            Log::info("CursoController.show() - componentes cargados", [
                'cantidad_componentes' => $componentes->count()
            ]);
            
            Log::info("CursoController.show() - transformando con resources");
            $cursoResource = new CursoResource($curso);
            Log::info("CursoController.show() - CursoResource creado");
            
            $componentesResource = ComponenteResource::collection($componentes);
            Log::info("CursoController.show() - ComponenteResource collection creada");

            $tiposComponente = TipoComponente::all();
            Log::info("CursoController.show() - tipos componente cargados");

            return response()->json([
                'curso' => $cursoResource,
                'componentes' => $componentesResource,
                'tipos_componente' => $tiposComponente
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Error CRÍTICO en CursoController.show()", [
                'curso_id' => $curso->id_curso ?? 'UNKNOWN',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al cargar el curso: ' . $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile() . ':' . $e->getLine(),
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
     * Get subjects for a specific plan (for cascading select), including semestre/agno pivot.
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
            ->withPivot('agno_planificado', 'semestre_planificado', 'tipo_ramo')
            ->orderBy('asignacion_plan.agno_planificado')
            ->orderBy('asignacion_plan.semestre_planificado')
            ->orderBy('asignatura.cod_asignatura')
            ->get()
            ->map(fn ($a) => [
                'id_asignatura'        => $a->id_asignatura,
                'cod_asignatura'       => $a->cod_asignatura,
                'nombre'               => $a->nombre,
                'creditos_sct'         => $a->creditos_sct,
                'agno_planificado'     => $a->pivot->agno_planificado,
                'semestre_planificado' => $a->pivot->semestre_planificado,
                'tipo_ramo'            => $a->pivot->tipo_ramo,
            ]);

        return response()->json($asignaturas);
    }

    /**
     * Retorna docentes sugeridos para una asignatura.
     * Históricamente la han impartido primero, luego el resto.
     */
    public function getDocentesSugeridos(Asignatura $asignatura)
    {
        $historicos = Docente::with('usuario')
            ->whereHas('docenteComponentes.componente.curso.asignacionPlan', fn ($q) =>
                $q->where('id_asignatura', $asignatura->id_asignatura)
            )
            ->orderBy('id_docente')
            ->get();

        $historicosIds = $historicos->pluck('id_docente');

        $otros = Docente::with('usuario')
            ->whereNotIn('id_docente', $historicosIds)
            ->orderBy('id_docente')
            ->get();

        $format = fn ($d) => [
            'id_docente'      => $d->id_docente,
            'nombre_completo' => trim(
                ($d->usuario->nombre1  ?? '') . ' ' .
                ($d->usuario->nombre2  ?? '') . ' ' .
                ($d->usuario->apellido1 ?? '') . ' ' .
                ($d->usuario->apellido2 ?? '')
            ),
            'email'  => $d->usuario->email  ?? null,
            'grado'  => $d->grado,
            'titulo' => $d->titulo,
            'cargo'  => $d->cargo,
        ];

        return response()->json([
            'historicos' => $historicos->map($format)->values(),
            'otros'      => $otros->map($format)->values(),
        ]);
    }

    /**
     * Get all docentes.
     */
    public function getDocentes()
    {
        // docente table has no fecha_eliminacion column – no soft-delete filter here
        $docentes = \App\Models\Usuario\Docente::with('usuario')
            ->orderBy('id_docente')
            ->get();

        return response()->json([
            'data' => \App\Http\Resources\DocenteResource::collection($docentes)
        ]);
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
