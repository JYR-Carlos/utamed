<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LimitsPageSize;
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
use App\Services\IntranetService;
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
    use LimitsPageSize;

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
        $this->authorize('viewAny', Curso::class);
        $cursos = Curso::query()
            ->with([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera',
                'componentes.tipoComponente',
                'docenteTitular.usuario',
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
            ->paginate($this->perPage($request))
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
        $this->authorize('create', Curso::class);
        Log::info('[CursoController@store] Iniciando creación de curso', [
            'data' => $request->validated()
        ]);

        try {
            Log::info('[CursoController@store] Llamando a CursoService::create');
            $curso = $this->cursoService->create($request->validated());
            Log::info('[CursoController@store] Curso creado exitosamente', ['id_curso' => $curso->id_curso]);

            $mensajeSuccess = 'Curso creado exitosamente.';

            if ($request->boolean('inscribir_automaticamente')) {
                try {
                    $intranetService = app(IntranetService::class);
                    $resultado = $intranetService->inscribirAutomaticamente($curso);
                    Log::info('[CursoController@store] Inscripción automática ejecutada tras creación', [
                        'id_curso' => $curso->id_curso,
                        'resultado' => $resultado->toArray()
                    ]);
                    return redirect()
                        ->route('admin.cursos.index')
                        ->with('success', "Curso creado exitosamente. Se inscribieron {$resultado->inscritos_exitosamente} alumnos automáticamente desde la Intranet.");
                } catch (\Exception $e) {
                    Log::error('[CursoController@store] Error en inscripción automática tras creación: ' . $e->getMessage());
                    return redirect()
                        ->route('admin.cursos.index')
                        ->with('success', 'Curso creado exitosamente en UTAMED.')
                        ->with('error', 'No se pudieron inscribir los alumnos desde la Intranet: ' . $e->getMessage());
                }
            }

            return redirect()
                ->route('admin.cursos.index')
                ->with('success', $mensajeSuccess);


        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[CursoController@store] ValidationException', ['errors' => $e->errors()]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('[CursoController@store] Error inesperado: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'data' => $request->validated()
            ]);

            return back()
                ->withErrors(['cod_curso' => 'Error al crear el curso: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified course.
     */
    public function show(Curso $curso)
    {
        $this->authorize('view', $curso);
        try {
            $curso->load([
                'inscripcionCursos.estudiante',
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ]);

            $componentes = Componente::where('id_curso', $curso->id_curso)
                ->with(['docenteComponentes.docente.usuario', 'tipoComponente'])
                ->get();

            return response()->json([
                'curso' => new CursoResource($curso),
                'componentes' => ComponenteResource::collection($componentes),
                'tipos_componente' => TipoComponente::all()
            ]);
        } catch (\Exception $e) {
            Log::error("Error en CursoController.show()", [
                'curso_id' => $curso->id_curso ?? 'UNKNOWN',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // La clase, la ruta del fichero y la traza describen el servidor: van
            // al log, no al cliente. Antes `error_file` viajaba siempre, al margen
            // de APP_DEBUG.
            return response()->json([
                'error' => config('app.debug')
                    ? 'Error al cargar el curso: ' . $e->getMessage()
                    : 'Error al cargar el curso.',
            ], 500);
        }
    }

    /**
     * Update the specified course.
     */
    public function update(UpdateCursoRequest $request, Curso $curso)
    {
        $this->authorize('update', $curso);
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
        $this->authorize('viewAny', Curso::class);
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
     * Returns previous courses for a given asignatura (for copy-from-previous feature).
     * Returns id_curso, cod_curso, period info, and component/activity counts.
     */
    public function getCursosAnteriores(Asignatura $asignatura)
    {
        $this->authorize('viewAny', Curso::class);
        $cursos = Curso::query()
            ->whereHas('asignacionPlan', fn($q) =>
                $q->where('id_asignatura', $asignatura->id_asignatura)
            )
            ->whereNull('fecha_eliminacion')
            ->with([
                'asignacionPlan',
                'componentes.actividades',
            ])
            ->withCount('componentes')
            ->orderBy('fecha_inicio', 'desc')
            ->limit(10)
            ->get();

        return response()->json($cursos->map(function ($c) {
            $ap = $c->asignacionPlan;
            $totalActividades = $c->componentes->sum(fn($comp) => $comp->actividades->count());
            return [
                'id_curso'          => $c->id_curso,
                'cod_curso'         => $c->cod_curso,
                'nombre'            => $c->nombre,
                'agno_planificado'  => $ap?->agno_planificado,
                'semestre_planificado' => $ap?->semestre_planificado,
                'fecha_inicio'      => $c->fecha_inicio?->format('Y-m-d'),
                'total_componentes' => $c->componentes_count,
                'total_actividades' => $totalActividades,
            ];
        })->values());
    }

    /**
     * Retorna docentes sugeridos para una asignatura.
     * Históricamente la han impartido primero, luego el resto.
     */
    public function getDocentesSugeridos(Asignatura $asignatura)
    {
        $this->authorize('viewAny', Curso::class);
        $historicos = Docente::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('esta_activo', true))
            ->whereHas('docenteComponentes.componente.curso.asignacionPlan', fn ($q) =>
                $q->where('id_asignatura', $asignatura->id_asignatura)
            )
            ->orderBy('id_docente')
            ->get();

        $historicosIds = $historicos->pluck('id_docente');

        $otros = Docente::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('esta_activo', true))
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
        $this->authorize('viewAny', Curso::class);
        // docente table has no fecha_eliminacion column – no soft-delete filter here
        $docentes = \App\Models\Usuario\Docente::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('esta_activo', true))
            ->orderBy('id_docente')
            ->get();

        return response()->json([
            'data' => \App\Http\Resources\DocenteResource::collection($docentes)
        ]);
    }

    /**
     * Returns the next available indice_grupo (letra) for a given
     * asignatura + plan + periodo, plus the indices already taken, so the
     * wizard can offer a manual letter selector.
     */
    public function getProximaLetra(Request $request)
    {
        $this->authorize('viewAny', Curso::class);
        $request->validate([
            'id_asignatura' => 'required|integer|exists:asignatura,id_asignatura',
            'id_plan'       => 'required|integer|exists:plan,id_plan',
            'agno_real'     => 'nullable|integer',
            'semestre_real' => 'nullable|integer',
        ]);

        $agnoReal = $request->input('agno_real');
        $semestreReal = $request->input('semestre_real');

        $asignacionPlan = \App\Models\Administrativo\AsignacionPlan::where('id_asignatura', $request->id_asignatura)
            ->where('id_plan', $request->id_plan)
            ->whereNull('fecha_eliminacion')
            ->first();

        if (!$asignacionPlan) {
            return response()->json(['proximo_indice' => 1, 'indices_tomados' => []]);
        }

        $query = Curso::where('id_asignacion_plan', $asignacionPlan->id_asignacion_plan)
            ->whereNull('fecha_eliminacion');

        $agnoReal === null ? $query->whereNull('agno_real') : $query->where('agno_real', $agnoReal);
        $semestreReal === null ? $query->whereNull('semestre_real') : $query->where('semestre_real', $semestreReal);

        $indicesTomados = $query->pluck('indice_grupo')->values()->all();

        return response()->json([
            'proximo_indice'  => $this->cursoService->nextIndiceGrupo(
                $asignacionPlan->id_asignacion_plan,
                $agnoReal,
                $semestreReal
            ),
            'indices_tomados' => $indicesTomados,
        ]);
    }

    /**
     * Returns preview data for course copy modal (syllabus, components, teachers, activities).
     * Excludes students and activity responses.
     */
    public function previewCopia(Curso $curso)
    {
        $this->authorize('view', $curso);
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'componentes.tipoComponente',
            'componentes.docenteComponentes.docente.usuario',
            'componentes.actividades',
            'docenteTitular.usuario',
        ]);

        $programa = $curso->programas()->where('es_actual', true)->first();

        $ap = $curso->asignacionPlan;
        $periodo = null;
        if ($ap?->agno_planificado && $ap?->semestre_planificado) {
            $periodo = "Año {$ap->agno_planificado} · Semestre {$ap->semestre_planificado}";
        }

        return response()->json([
            'curso' => [
                'id_curso'         => $curso->id_curso,
                'cod_curso'        => $curso->cod_curso,
                'nombre'           => $curso->nombre,
                'asignatura_nombre'=> $ap?->asignatura?->nombre,
                'cod_asignatura'   => $ap?->asignatura?->cod_asignatura,
                'carrera_nombre'   => $ap?->plan?->carrera?->nombre,
                'periodo'          => $periodo,
                'fecha_inicio'     => $curso->fecha_inicio?->format('Y-m-d'),
                'es_colegiado'     => $curso->es_colegiado,
            ],
            'programa' => $programa ? [
                'estado'        => $programa->estado,
                'tipo_syllabus' => $programa->getTipoSyllabus(),
                'version'       => $programa->version_programa,
            ] : null,
            'componentes' => $curso->componentes->map(function ($comp) {
                return [
                    'id_componente'  => $comp->id_componente,
                    'tipo'           => $comp->tipoComponente?->tipo ?? '—',
                    'genera_acta'    => $comp->genera_acta,
                    'porcentaje_aprobacion'            => $comp->porcentaje_aprobacion,
                    'aprobacion_obligatoria'           => $comp->aprobacion_obligatoria,
                    'porcentaje_asistencia_obligatoria'=> $comp->porcentaje_asistencia_obligatoria,
                    'docentes' => $comp->docenteComponentes->map(function ($dc) {
                        $u = $dc->docente?->usuario;
                        return [
                            'nombre'    => $u ? trim(($u->nombre1 ?? '') . ' ' . ($u->apellido1 ?? '')) : '—',
                            'cargo'     => $dc->docente?->cargo,
                            'es_titular'=> $dc->es_titular,
                        ];
                    })->values(),
                    'actividades' => $comp->actividades->map(fn($a) => [
                        'id_actividad'   => $a->id_actividad,
                        'nombre'         => $a->nombre,
                        'tipo_actividad' => $a->tipo_actividad instanceof \App\Enums\DB\TipoActividad
                            ? $a->tipo_actividad->value
                            : $a->tipo_actividad,
                        'ponderacion'    => $a->ponderacion,
                        'fecha_limite'   => $a->fecha_limite?->format('Y-m-d'),
                        'es_grupal'      => $a->es_grupal,
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * Copies a course (structure, components, teachers, activities) into a new course.
     * Activity dates are reset to null; students and responses are NOT copied.
     */
    public function copiar(Request $request, Curso $curso)
    {
        $this->authorize('create', Curso::class);
        $request->validate([
            'cod_curso'   => 'required|integer|min:1',
            'fecha_inicio'=> 'required|date',
        ]);

        try {
            $this->cursoService->copiar($curso, [
                'cod_curso'   => (int) $request->cod_curso,
                'fecha_inicio'=> $request->fecha_inicio,
            ]);

            return redirect()
                ->route('admin.cursos.index')
                ->with('success', 'Curso copiado exitosamente.');
        } catch (\Exception $e) {
            Log::error('[CursoController@copiar] Error: ' . $e->getMessage(), [
                'curso_id' => $curso->id_curso,
            ]);
            return back()->withErrors(['error' => 'Error al copiar el curso: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified course.
     */
    public function destroy(Curso $curso)
    {
        $this->authorize('delete', $curso);
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
